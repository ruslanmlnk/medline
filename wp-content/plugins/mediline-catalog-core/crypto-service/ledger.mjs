import { DatabaseSync } from 'node:sqlite';
import { randomUUID, createHash } from 'node:crypto';
import { derive, display, quote } from './wallet.mjs';

export class Ledger {
  constructor(path) {
    this.db = new DatabaseSync(path);
    this.db.exec(`PRAGMA journal_mode=WAL; PRAGMA busy_timeout=5000;
      CREATE TABLE IF NOT EXISTS invoices (
        id TEXT PRIMARY KEY, reference TEXT NOT NULL UNIQUE, asset TEXT NOT NULL, network TEXT NOT NULL,
        wallet TEXT NOT NULL, idx INTEGER NOT NULL, address TEXT NOT NULL UNIQUE,
        fiat TEXT NOT NULL, currency TEXT NOT NULL, rate TEXT NOT NULL, due TEXT NOT NULL,
        created INTEGER NOT NULL, expires INTEGER NOT NULL, status TEXT NOT NULL DEFAULT 'awaiting',
        received TEXT NOT NULL DEFAULT '0', checked INTEGER NOT NULL DEFAULT 0,
        error TEXT NOT NULL DEFAULT '', issued INTEGER NOT NULL DEFAULT 0, attempted INTEGER NOT NULL DEFAULT 0, revision INTEGER NOT NULL DEFAULT 0,
        delivered INTEGER NOT NULL DEFAULT 0, transactions TEXT NOT NULL DEFAULT '[]',
        UNIQUE(wallet, idx));
      CREATE TABLE IF NOT EXISTS nonces (nonce TEXT PRIMARY KEY, created INTEGER NOT NULL);
      CREATE TABLE IF NOT EXISTS events (id INTEGER PRIMARY KEY, invoice TEXT NOT NULL, at INTEGER NOT NULL, status TEXT NOT NULL);
      CREATE TABLE IF NOT EXISTS sweeps (invoice TEXT PRIMARY KEY, status TEXT NOT NULL, funding_tx TEXT NOT NULL DEFAULT '', sweep_tx TEXT NOT NULL DEFAULT '', updated INTEGER NOT NULL);
      CREATE TABLE IF NOT EXISTS settings (name TEXT PRIMARY KEY, value TEXT NOT NULL);`);
  }
  bindWallets(configs) {
    // Reject accidental network/key changes against an existing ledger.
    for (const c of configs) {
      const value = JSON.stringify([c.network, c.xpub, c.contract || '']);
      const old = this.db.prepare('SELECT value FROM settings WHERE name=?').get(c.asset);
      if (old && old.value !== value) throw Error('Wallet change requires explicit ledger migration');
      this.db.prepare('INSERT OR IGNORE INTO settings VALUES (?,?)').run(c.asset, value);
    }
    for (const row of this.db.prepare('SELECT DISTINCT asset FROM invoices').all()) {
      if (!configs.some(c => c.asset === row.asset)) throw Error('Cannot stop watching an existing asset');
    }
  }
  get(id) { return this.db.prepare('SELECT * FROM invoices WHERE id=?').get(id); }
  byReference(reference) { return this.db.prepare('SELECT * FROM invoices WHERE reference=?').get(reference); }
  create(input, config, rate, now = Math.floor(Date.now()/1000)) {
    if (!/^[a-zA-Z0-9:_-]{1,120}$/.test(input.reference) || !['USD','EUR'].includes(input.currency)) throw Error('Invalid invoice');
    const due = quote(input.total, rate, config.asset === 'BTC' ? 8 : 6).toString();
    this.db.exec('BEGIN IMMEDIATE');
    try {
      let row = this.byReference(input.reference);
      if (row) {
        if (row.asset !== config.asset || row.fiat !== input.total || row.currency !== input.currency) throw Error('Invoice conflict');
      } else {
        const wallet = createHash('sha256').update(config.xpub).digest('hex');
        const idx = this.db.prepare('SELECT COALESCE(MAX(idx),-1)+1 AS idx FROM invoices WHERE wallet=?').get(wallet).idx;
        const id = randomUUID();
        this.db.prepare(`INSERT INTO invoices (id,reference,asset,network,wallet,idx,address,fiat,currency,rate,due,created,expires)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)`).run(id,input.reference,config.asset,config.network,wallet,idx,derive(config,idx),input.total,input.currency,rate,due,now,now+1800);
        row = this.get(id);
      }
      this.db.exec('COMMIT'); return row;
    } catch (e) { this.db.exec('ROLLBACK'); throw e; }
  }
  nonce(value, now) {
    this.db.prepare('DELETE FROM nonces WHERE created<?').run(now-600);
    try { this.db.prepare('INSERT INTO nonces VALUES (?,?)').run(value,now); return true; } catch { return false; }
  }
  observe(id, transactions, now = Math.floor(Date.now()/1000)) {
    const row = this.get(id), seen = new Set();
    let confirmed=0n, inTime=0n, pending=0n;
    for (const tx of transactions) {
      if (!/^[a-f0-9]{64}:\d+$/.test(tx.key) || !/^\d+$/.test(tx.amount) || seen.has(tx.key)) throw Error('Invalid/duplicate observation');
      seen.add(tx.key);
      if (tx.confirmed) {
        if (!Number.isSafeInteger(tx.time) || tx.time < row.created-300 || tx.time > now+300) throw Error('Unexpected transaction time; review required');
        confirmed += BigInt(tx.amount);
        if (tx.time <= row.expires) inTime += BigInt(tx.amount);
      } else { pending += BigInt(tx.amount); }
    }
    const due=BigInt(row.due);
    let status = inTime >= due ? (confirmed > due ? 'overpaid' : 'paid')
      : confirmed >= due ? 'late_review' : now > row.expires ? (confirmed > 0n || pending > 0n ? 'late_review' : 'expired')
      : confirmed > 0n ? 'partial' : pending > 0n ? 'confirming' : 'awaiting';
    if (['paid','overpaid','reorg_review'].includes(row.status) && !['paid','overpaid'].includes(status)) status='reorg_review';
    // Once review is needed, a manager decides the outcome. Never silently clear it.
    if (['late_review','reorg_review'].includes(row.status)) status=row.status;
    const changed = status !== row.status || confirmed.toString() !== row.received || JSON.stringify(transactions) !== row.transactions;
    this.db.prepare(`UPDATE invoices SET status=?, received=?, checked=?, error='', transactions=?, revision=revision+? WHERE id=?`)
      .run(status,confirmed.toString(),now,JSON.stringify(transactions),changed ? 1 : 0,id);
    if (status !== row.status) this.db.prepare('INSERT INTO events (invoice,at,status) VALUES (?,?,?)').run(id,now,status);
    return this.get(id);
  }
  public(row) {
    const decimals=row.asset==='BTC'?8:6;
    const pending=JSON.parse(row.transactions).filter(t=>!t.confirmed).reduce((n,t)=>n+BigInt(t.amount),0n);
    const remaining=BigInt(row.due)-BigInt(row.received)-pending;
    return {id:row.id,reference:row.reference,asset:row.asset,network:row.network,address:row.address,
      amount:display(row.due,decimals),remaining:display(remaining>0n?remaining:0n,decimals),received:display(row.received,decimals),fiat:row.fiat,currency:row.currency,
      expires:row.expires,status:row.status,checked:row.checked,revision:row.revision,
      stale:!!row.error || !row.checked || row.checked < Math.floor(Date.now()/1000)-180,
      transactions:[...new Set(JSON.parse(row.transactions).filter(t=>t.confirmed).map(t=>t.key.split(':')[0]))],
      collection:this.db.prepare('SELECT status,funding_tx,sweep_tx,updated FROM sweeps WHERE invoice=?').get(row.id)||null};
  }
}
