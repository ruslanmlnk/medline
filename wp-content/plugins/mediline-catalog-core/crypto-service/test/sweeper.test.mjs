import test from 'node:test';
import assert from 'node:assert/strict';
import { randomBytes } from 'node:crypto';
import { HDKey } from '@scure/bip32';
import { TronWeb, utils } from '../signer/node_modules/tronweb/lib/esm/index.js';
import { derive } from '../wallet.mjs';
import { SweepStore,Sweeper,validatePolicy } from '../signer/sweeper.mjs';
import { validateTransaction } from '../signer/tron-adapter.mjs';
const branch=HDKey.fromMasterSeed(randomBytes(32)).derive("m/44'/195'/0'/0");
const wallet={asset:'USDT_TRC20',network:'testnet',xpub:branch.publicExtendedKey};
const policy={...wallet,mode:'execute',provider:'https://api.shasta.trongrid.io',treasury:derive(wallet,100),gasAddress:derive(wallet,101),contract:derive(wallet,102),expectedFirstAddress:derive(wallet,0),minUsdtUnits:'1000000',maxPerOrderSun:'50000000',maxDailySun:'100000000',maxFeeLimitSun:'15000000',bandwidthReserveSun:'2000000',gasReserveSun:'5000000'};
const now=Math.floor(Date.now()/1000);
const candidate={id:'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',idx:0,address:derive(wallet,0),asset:wallet.asset,network:'testnet',due:'29900000',created:now-60,expires:now+1700};
function harness(p=policy) {
 const store=new SweepStore(':memory:',p),receipts=new Map(),calls={fund:0,sweep:0,broadcast:[]};
 let balance=0n,active=true,failBroadcast=false;
 const adapter={
  inspect:async()=>({confirmed:29900000n,inTime:29900000n,balance:29900000n,active}),
  estimate:async()=>({feeLimit:7000000n,deficit:balance>=9000000n?0n:9000000n-balance}),
  prepareFunding:async(c,amount)=>{calls.fund++;return {signed:{txID:'b'.repeat(64),raw_data:{expiration:Date.now()+60000}},reserve:BigInt(amount)+2000000n};},
  prepareSweep:async()=>{calls.sweep++;return {signed:{txID:'c'.repeat(64),raw_data:{expiration:Date.now()+60000}},reserve:9000000n};},
  receipt:async txid=>receipts.get(txid)||null,
  verifyReceipt:async(c,op,r)=>r.ok===true,
  broadcast:async tx=>{calls.broadcast.push(tx.txID);if(failBroadcast)throw Error('response lost');}
 };
 return {store,adapter,receipts,calls,sweeper:new Sweeper(p,store,adapter),setBalance(v){balance=v;},setActive(v){active=v;},loseResponse(){failBroadcast=true;}};
}
test('fund once, wait for solidification, then sweep once; repeat cycles are idempotent',async()=>{
 const h=harness();await h.sweeper.step(candidate);assert.equal(h.calls.fund,1);assert.equal(h.store.job(candidate.id).state,'funding_pending');
 await h.sweeper.step(candidate);assert.equal(h.calls.fund,1);assert.equal(h.calls.sweep,0);
 h.receipts.set('b'.repeat(64),{ok:true,fee:100});await h.sweeper.step(candidate);h.setBalance(9000000n);
 await h.sweeper.step(candidate);assert.equal(h.calls.sweep,1);
 h.receipts.set('c'.repeat(64),{ok:true,fee:100});await h.sweeper.step(candidate);
 assert.equal(h.store.job(candidate.id).state,'complete');await h.sweeper.step(candidate);assert.equal(h.calls.sweep,1);h.store.db.close();
});
test('lost broadcast response resumes the same persisted transaction after worker restart',async()=>{
 const h=harness();h.loseResponse();await h.sweeper.step(candidate);
 const restarted=new Sweeper(policy,h.store,h.adapter);await restarted.step(candidate);
 assert.equal(h.calls.fund,1);assert.deepEqual(h.calls.broadcast,['b'.repeat(64),'b'.repeat(64)]);h.store.db.close();
});
test('dry run never signs or broadcasts, including a pending transaction from execute mode',async()=>{
 const h=harness();await h.sweeper.step(candidate);const before=h.calls.broadcast.length;
 const dry=new Sweeper({...policy,mode:'dry-run'},h.store,h.adapter);await dry.step(candidate);
 assert.equal(h.calls.broadcast.length,before);
 const clean=harness({...policy,mode:'dry-run'});await clean.sweeper.step(candidate);assert.equal(clean.calls.fund,0);assert.equal(clean.calls.sweep,0);assert.equal(clean.store.job(candidate.id).state,'dry_run');h.store.db.close();clean.store.db.close();
});
test('no spending on dust, wrong derivation, unconfirmed or insufficient payments',async()=>{
 const h=harness();await h.sweeper.step({...candidate,due:'1'});assert.equal(h.calls.fund,0);
 await h.sweeper.step({...candidate,address:policy.treasury});assert.equal(h.calls.fund,0);
 h.adapter.inspect=async()=>({confirmed:1n,inTime:1n,balance:1n,active:true});await h.sweeper.step(candidate);
 assert.equal(h.calls.fund,0);assert.equal(h.store.job(candidate.id).state,'review');h.store.db.close();
});
test('fee increase after funding requires review, never a second top-up',async()=>{
 const h=harness();await h.sweeper.step(candidate);h.receipts.set('b'.repeat(64),{ok:true});await h.sweeper.step(candidate);
 await h.sweeper.step(candidate);assert.equal(h.calls.fund,1);assert.equal(h.store.job(candidate.id).error,'fee_changed_after_funding');h.store.db.close();
});
test('expired/failed transactions are not rebuilt or refunded automatically',async()=>{
 const h=harness();await h.sweeper.step(candidate);
 const tx=JSON.parse(h.store.op(candidate.id,'funding').signed);tx.raw_data.expiration=Date.now()-180000;
 h.store.db.prepare('UPDATE operations SET signed=?').run(JSON.stringify(tx));await h.sweeper.step(candidate);
 assert.equal(h.store.job(candidate.id).state,'review');assert.equal(h.calls.fund,1);h.store.db.close();
 const f=harness();await f.sweeper.step(candidate);f.receipts.set('b'.repeat(64),{ok:false});await f.sweeper.step(candidate);
 assert.equal(f.store.job(candidate.id).error,'transaction_failed_or_unexpected');f.store.db.close();
});
test('per-order and rolling-day budgets are reserved atomically before broadcast',async()=>{
 const h=harness({...policy,maxPerOrderSun:'10000000'});await h.sweeper.step(candidate);assert.equal(h.calls.broadcast.length,0);assert.equal(h.store.job(candidate.id).error,'trx_budget_exceeded');h.store.db.close();
 const d=harness({...policy,maxDailySun:'10000000'});await d.sweeper.step(candidate);assert.equal(d.calls.broadcast.length,0);d.store.db.close();
});
test('network policy validates destinations and live execution approval',()=>{
 assert.equal(validatePolicy(policy),policy);
 assert.throws(()=>validatePolicy({...policy,gasAddress:policy.treasury}));assert.throws(()=>validatePolicy({...policy,network:'mainnet'}));
 assert.throws(()=>validatePolicy({...policy,minUsdtUnits:'1'}));
});
test('transaction validation binds raw protobuf hash, destination, amount and contract type',()=>{
 const v={owner_address:TronWeb.address.toHex(policy.gasAddress),to_address:TronWeb.address.toHex(candidate.address),amount:1000000};
 const tx={raw_data:{contract:[{parameter:{value:v,type_url:'type.googleapis.com/protocol.TransferContract'},type:'TransferContract'}],ref_block_bytes:'abcd',ref_block_hash:'0123456789abcdef',expiration:Date.now()+60000,timestamp:Date.now()}};
 const pb=utils.transaction.txJsonToPb(tx);tx.raw_data_hex=utils.transaction.txPbToRawDataHex(pb);tx.txID=utils.transaction.txPbToTxID(pb).replace(/^0x/,'');
 const expected={kind:'funding',owner:policy.gasAddress,to:candidate.address,amount:'1000000'};
 assert.equal(validateTransaction(tx,expected),tx);
 assert.throws(()=>validateTransaction(tx,{...expected,to:policy.treasury}));
 assert.throws(()=>validateTransaction(tx,{...expected,amount:'1000001'}));
 const altered=structuredClone(tx);altered.raw_data.contract[0].parameter.value.amount=2000000;assert.throws(()=>validateTransaction(altered,expected));
});
