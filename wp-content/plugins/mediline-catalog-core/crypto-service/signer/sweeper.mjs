import { DatabaseSync } from 'node:sqlite';
import { derive, tronHex, TRON_USDT } from '../wallet.mjs';

export class Review extends Error {}
export function validatePolicy(p) {
  if(!['testnet','mainnet'].includes(p.network)||!['disabled','dry-run','execute'].includes(p.mode))throw Error('Invalid signer mode');
  if(p.mode==='execute'&&p.network==='mainnet'&&p.liveApproved!==true)throw Error('Mainnet signing needs explicit approval');
  if(p.provider!==(p.network==='mainnet'?'https://api.trongrid.io':'https://api.shasta.trongrid.io'))throw Error('Wrong TRON provider');
  tronHex(p.treasury);tronHex(p.gasAddress);tronHex(p.contract);
  if(p.treasury===p.gasAddress)throw Error('Gas wallet and treasury must be separate');
  if(p.network==='mainnet'&&p.contract!==TRON_USDT)throw Error('Wrong USDT contract');
  if(p.network==='testnet'&&p.contract===TRON_USDT)throw Error('Use a test token');
  for(const key of ['minUsdtUnits','maxPerOrderSun','maxDailySun','maxFeeLimitSun','bandwidthReserveSun','gasReserveSun']) {
    if(typeof p[key]!=='string'||!/^\d{1,15}$/.test(p[key])||BigInt(p[key])<=0n)throw Error('Missing positive limit: '+key);
  }
  if(BigInt(p.minUsdtUnits)<1000000n)throw Error('Minimum collection is at least 1 USDT');
  if(BigInt(p.maxFeeLimitSun)>BigInt(p.maxPerOrderSun)||BigInt(p.maxDailySun)<BigInt(p.maxPerOrderSun))throw Error('Inconsistent budgets');
  if(derive({asset:'USDT_TRC20',network:p.network,xpub:p.xpub},0)!==p.expectedFirstAddress)throw Error('First address must match your recovery wallet');
  return p;
}
export class SweepStore {
  constructor(path,policy) {
    this.db=new DatabaseSync(path);
    this.db.exec(`PRAGMA journal_mode=WAL; PRAGMA synchronous=FULL; PRAGMA busy_timeout=5000;
     CREATE TABLE IF NOT EXISTS binding (id INTEGER PRIMARY KEY CHECK(id=1), value TEXT NOT NULL);
     CREATE TABLE IF NOT EXISTS jobs (id TEXT PRIMARY KEY,address TEXT NOT NULL UNIQUE,candidate TEXT NOT NULL,state TEXT NOT NULL,error TEXT NOT NULL DEFAULT '',updated INTEGER NOT NULL,reported INTEGER NOT NULL DEFAULT 0);
     CREATE TABLE IF NOT EXISTS operations (job TEXT NOT NULL,kind TEXT NOT NULL,txid TEXT NOT NULL UNIQUE,signed TEXT NOT NULL,reserved TEXT NOT NULL,created INTEGER NOT NULL,status TEXT NOT NULL DEFAULT 'pending',fee TEXT NOT NULL DEFAULT '0',PRIMARY KEY(job,kind));
     CREATE TABLE IF NOT EXISTS audit (id INTEGER PRIMARY KEY,job TEXT NOT NULL,event TEXT NOT NULL,at INTEGER NOT NULL);`);
    const value=JSON.stringify([policy.network,policy.xpub,policy.treasury,policy.gasAddress,policy.contract]);
    const old=this.db.prepare('SELECT value FROM binding WHERE id=1').get();
    if(old&&old.value!==value)throw Error('Signer wallet/network/destination changed: explicit migration required');
    this.db.prepare('INSERT OR IGNORE INTO binding VALUES (1,?)').run(value);
    this.policy=policy;
  }
  job(id){return this.db.prepare('SELECT * FROM jobs WHERE id=?').get(id);}
  op(id,kind){return this.db.prepare('SELECT * FROM operations WHERE job=? AND kind=?').get(id,kind);}
  state(id,state,error='') {
    const old=this.job(id);
    if(old.state===state&&old.error===error)return;
    this.db.prepare('UPDATE jobs SET state=?,error=?,updated=?,reported=0 WHERE id=?').run(state,error,Date.now(),id);
    this.db.prepare('INSERT INTO audit(job,event,at) VALUES (?,?,?)').run(id,state,Date.now());
  }
  add(c) {
    const immutable=JSON.stringify({id:c.id,idx:c.idx,address:c.address,asset:c.asset,network:c.network,due:c.due,created:c.created,expires:c.expires});
    const old=this.job(c.id);if(old&&old.candidate!==immutable)throw new Review('invoice_changed');
    this.db.prepare("INSERT OR IGNORE INTO jobs(id,address,candidate,state,updated) VALUES (?,?,?,'new',?)").run(c.id,c.address,immutable,Date.now());
  }
  reserve(id,kind,signed,reserved,now=Date.now()) {
    // Persist the exact signed bytes and reserve the worst-case budget BEFORE any broadcast.
    this.db.exec('BEGIN IMMEDIATE');
    try {
      if(this.op(id,kind))throw new Review('operation_already_exists');
      const sum=rows=>rows.reduce((n,r)=>n+BigInt(r.reserved),0n);
      const order=sum(this.db.prepare('SELECT reserved FROM operations WHERE job=?').all(id));
      const daily=sum(this.db.prepare('SELECT reserved FROM operations WHERE created>=?').all(now-86400000));
      if(BigInt(reserved)<=0n||order+BigInt(reserved)>BigInt(this.policy.maxPerOrderSun)||daily+BigInt(reserved)>BigInt(this.policy.maxDailySun))throw new Review('trx_budget_exceeded');
      this.db.prepare('INSERT INTO operations(job,kind,txid,signed,reserved,created) VALUES (?,?,?,?,?,?)').run(id,kind,signed.txID,JSON.stringify(signed),String(reserved),now);
      this.state(id,kind+'_pending');this.db.exec('COMMIT');
    }catch(e){this.db.exec('ROLLBACK');throw e;}
  }
  report(id) {
    const j=this.job(id),activation=this.op(id,'activation'),fund=this.op(id,'funding'),sweep=this.op(id,'sweep');
    return {invoice:id,status:j.state==='new'?'dry_run':j.state,
      funding_tx:fund?.txid||activation?.txid||'',sweep_tx:sweep?.txid||''};
  }
}
export class Sweeper {
  constructor(policy,store,adapter){this.p=policy;this.store=store;this.adapter=adapter;this.busy=false;}
  async step(candidate) {
    if(this.busy)return;this.busy=true;
    let admitted=false;
    try {
      const c=candidate,p=this.p;
      if(p.mode==='disabled')return;
      if(!/^[a-f0-9-]{36}$/.test(c.id)||c.asset!=='USDT_TRC20'||c.network!==p.network||!/^\d+$/.test(c.due)||BigInt(c.due)<BigInt(p.minUsdtUnits))return;
      if(!Number.isSafeInteger(c.created)||!Number.isSafeInteger(c.expires)||c.expires<=c.created)throw new Review('invalid_invoice_window');
      if(c.address!==derive({asset:'USDT_TRC20',network:p.network,xpub:p.xpub},c.idx)||[p.treasury,p.gasAddress].includes(c.address))throw new Review('invalid_deposit_address');
      this.store.add(c);admitted=true;
      this.store.db.prepare('UPDATE jobs SET updated=? WHERE id=?').run(Date.now(),c.id);
      const job=this.store.job(c.id);
      if(['complete','review'].includes(job.state))return;
      // Reconcile a previously signed transaction before looking at balances or constructing another.
      for(const kind of ['activation','funding','sweep']) {
        const op=this.store.op(c.id,kind);
        if(op?.status==='pending') {await this.reconcile(c,op);return;}
      }
      if(this.store.op(c.id,'sweep')?.status==='confirmed'){this.store.state(c.id,'complete');return;}
      const evidence=await this.adapter.inspect(c);
      if(evidence.confirmed<BigInt(c.due)||evidence.inTime<BigInt(c.due)||evidence.balance<BigInt(c.due))throw new Review('confirmed_funds_missing');
      if(p.mode==='dry-run'){this.store.state(c.id,'dry_run');return;}
      if(!evidence.active) {
        if(this.store.op(c.id,'activation'))throw new Review('activation_not_effective');
        const built=await this.adapter.prepareFunding(c,'1',true);
        this.store.reserve(c.id,'activation',built.signed,built.reserve);
        await this.reconcile(c,this.store.op(c.id,'activation'));return;
      }
      const estimate=await this.adapter.estimate(c,evidence.balance);
      if(estimate.feeLimit>BigInt(p.maxFeeLimitSun))throw new Review('fee_limit_exceeded');
      if(estimate.deficit>0n) {
        if(this.store.op(c.id,'funding'))throw new Review('fee_changed_after_funding');
        const built=await this.adapter.prepareFunding(c,String(estimate.deficit),false);
        this.store.reserve(c.id,'funding',built.signed,built.reserve);
        await this.reconcile(c,this.store.op(c.id,'funding'));return;
      }
      const built=await this.adapter.prepareSweep(c,evidence.balance,estimate.feeLimit);
      this.store.reserve(c.id,'sweep',built.signed,built.reserve);
      await this.reconcile(c,this.store.op(c.id,'sweep'));
    }catch(e) {
      if(admitted&&e instanceof Review)this.store.state(candidate.id,'review',e.message);
      else if(admitted)this.store.db.prepare("UPDATE jobs SET error='provider_unavailable',updated=? WHERE id=?").run(Date.now(),candidate.id);
      // No key, signed payload, RPC body, or raw error is written to logs.
    }finally{this.busy=false;}
  }
  async reconcile(c,op) {
    const signed=JSON.parse(op.signed);
    const receipt=await this.adapter.receipt(op.txid);
    if(receipt) {
      if(!await this.adapter.verifyReceipt(c,op,receipt))throw new Review('transaction_failed_or_unexpected');
      this.store.db.prepare("UPDATE operations SET status='confirmed',fee=? WHERE job=? AND kind=?").run(String(receipt.fee||0),c.id,op.kind);
      if(op.kind==='sweep')this.store.state(c.id,'complete');
      return;
    }
    if(Date.now()>signed.raw_data.expiration+120000)throw new Review('transaction_expired_check_manually');
    if(this.p.mode==='execute'&&Date.now()<signed.raw_data.expiration)await this.adapter.broadcast(signed,op);
    // Lost broadcast response is safe: the next pass retries the identical txID/bytes.
  }
}
