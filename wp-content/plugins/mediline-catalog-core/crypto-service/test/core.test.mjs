import test from 'node:test';
import assert from 'node:assert/strict';
import { randomBytes } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { HDKey } from '@scure/bip32';
import { keccak_256 } from '@noble/hashes/sha3.js';
import { derive,quote,units,tronHex,TRON_USDT } from '../wallet.mjs';
import { Ledger } from '../ledger.mjs';
import { signature,verify,createApp,validateConfig } from '../server.mjs';
import { tronReceipt,bitcoin,price } from '../providers.mjs';

// Test-only random wallets are never written to configuration or deployment artifacts.
const key=HDKey.fromMasterSeed(randomBytes(32)).derive("m/44'/195'/0'/0");
const wallet={asset:'USDT_TRC20',network:'testnet',xpub:key.publicExtendedKey};
const invoice={reference:'testshop:1',asset:wallet.asset,total:'29.90',currency:'USD'};
const now=Math.floor(Date.now()/1000);
const tx=(amount,confirmed=true,time=now,idx=0)=>({key:'a'.repeat(64)+':'+idx,amount,confirmed,time});

test('decimal conversion is exact and rounds up, not floating point',()=>{
 assert.equal(quote('29.90','1',6),29900000n);assert.equal(quote('1.00','3',8),33333334n);
 assert.equal(units('0.000001',6),1n);assert.throws(()=>units('1e3',6));assert.throws(()=>units('1.0000001',6));
 assert.throws(()=>quote('-1','1',6));assert.throws(()=>quote('10.00','0',6));
});
test('only public branch keys accepted; separate addresses match private derivation',()=>{
 const a=derive(wallet,0);assert.notEqual(a,derive(wallet,1));assert.equal(tronHex(a).length,40);
 assert.throws(()=>derive({...wallet,xpub:key.privateExtendedKey},0));assert.throws(()=>derive(wallet,0x80000000));
 assert.throws(()=>derive({...wallet,xpub:HDKey.fromMasterSeed(randomBytes(32)).publicExtendedKey},0));
});
test('public-key preparation accepts an account xpub and refuses private material',()=>{
 const account=HDKey.fromMasterSeed(randomBytes(32)).derive("m/44'/195'/0'");
 const run=input=>spawnSync(process.execPath,['tools/prepare-public-wallet.mjs','USDT_TRC20','testnet'],{input,encoding:'utf8'});
 const good=run(account.publicExtendedKey);assert.equal(good.status,0,good.stderr);
 const prepared=JSON.parse(good.stdout);assert.equal(prepared.xpub,account.deriveChild(0).publicExtendedKey);
 assert.equal(prepared.expectedFirstAddress,derive({asset:'USDT_TRC20',network:'testnet',xpub:prepared.xpub},0));
 assert.notEqual(run(account.privateExtendedKey).status,0);
});
test('idempotency, immutable invoice amounts and immutable wallet configuration',()=>{
 const l=new Ledger(':memory:');l.bindWallets([wallet]);const a=l.create(invoice,wallet,'1',now);
 assert.equal(a.id,l.create(invoice,wallet,'2',now).id);assert.equal(a.due,'29900000');
 assert.throws(()=>l.create({...invoice,total:'30.00'},wallet,'1',now));
 assert.notEqual(a.address,l.create({...invoice,reference:'testshop:2'},wallet,'1',now).address);
 assert.throws(()=>l.bindWallets([{...wallet,network:'mainnet'}]));assert.throws(()=>l.bindWallets([]));l.db.close();
});
test('partial, unconfirmed, paid, duplicate outputs, reorganization and late payments',()=>{
 const l=new Ledger(':memory:'),a=l.create(invoice,wallet,'1',now);
 assert.equal(l.observe(a.id,[tx('29900000',false)],now).status,'confirming');
 assert.equal(l.public(l.get(a.id)).remaining,'0.000000');
 assert.equal(l.observe(a.id,[tx('9900000')],now).status,'partial');
 assert.equal(l.public(l.get(a.id)).remaining,'20.000000');
 assert.equal(l.observe(a.id,[tx('29900000')],now).status,'paid');
 assert.throws(()=>l.observe(a.id,[tx('29900000'),tx('29900000')],now));
 assert.equal(l.observe(a.id,[],now).status,'reorg_review');
 const b=l.create({...invoice,reference:'testshop:2'},wallet,'1',now);
 assert.equal(l.observe(b.id,[tx('29900000',true,now+1900)],now+1900).status,'late_review');
 const c=l.create({...invoice,reference:'testshop:3'},wallet,'1',now);
 assert.equal(l.observe(c.id,[tx('30000000')],now).status,'overpaid');l.db.close();
});
test('HMAC binds method, path, body, timestamp and rejects nonce replay',()=>{
 const l=new Ledger(':memory:'),secret='a'.repeat(64),body='{"test":1}',nonce='b'.repeat(32),stamp=String(now),path='/v1/invoices';
 const headers={'x-mediline-time':stamp,'x-mediline-nonce':nonce,'x-mediline-signature':signature(secret,'POST',path,stamp,nonce,body)};
 assert.equal(verify(secret,'GET',path,headers,body,l,now),false);
 assert.equal(verify(secret,'POST',path,headers,'{}',l,now),false);
 assert.equal(verify(secret,'POST',path,headers,body,l,now+121),false);
 assert.equal(verify(secret,'POST',path,headers,body,l,now),true);
 assert.equal(verify(secret,'POST',path,headers,body,l,now),false);l.db.close();
});
test('TRON uses successful solidified logs and exact token/recipient',()=>{
 const address=derive(wallet,0),hash='a'.repeat(64),topic=Buffer.from(keccak_256(new TextEncoder().encode('Transfer(address,address,uint256)'))).toString('hex');
 const receipt={id:hash,blockNumber:100,blockTimeStamp:now*1000,receipt:{result:'SUCCESS'},log:[{address:tronHex(TRON_USDT),topics:[topic,'0'.repeat(64),'0'.repeat(24)+tronHex(address)],data:(29900000n).toString(16).padStart(64,'0')}]};
 assert.equal(tronReceipt(receipt,hash,address,TRON_USDT)[0].amount,'29900000');
 assert.equal(tronReceipt(receipt,hash,derive(wallet,1),TRON_USDT).length,0);
 assert.equal(tronReceipt(receipt,hash,address,derive(wallet,1)).length,0);
 assert.throws(()=>tronReceipt({...receipt,receipt:{result:'OUT_OF_ENERGY'}},hash,address,TRON_USDT));
});
test('BTC refuses wrong chain and exchange refuses mismatched quote',async()=>{
 await assert.rejects(bitcoin({network:'mainnet',provider:'https://example.test'}, {address:'test'},async()=> 'wrong'));
 await assert.rejects(price('BTC','USD',async()=>({data:{base:'USDT',currency:'USD',amount:'1'}})));
});
test('mainnet requires explicit setup and wrong first address fails',()=>{
 assert.throws(()=>validateConfig({mode:'mainnet',secret:'a'.repeat(64),wallets:[wallet]}));
 assert.throws(()=>validateConfig({mode:'testnet',secret:'a'.repeat(64),wallets:[{...wallet,provider:'https://api.shasta.trongrid.io',contract:derive(wallet,3),expectedFirstAddress:'wrong'}]}));
});
test('service rejects unsigned access, issues QR, retries callbacks and reports stale providers',async()=>{
 const l=new Ledger(':memory:');let observations=[],down=false,deliver=false;
 const cfg={mode:'testnet',secret:'a'.repeat(64),sweepSecret:'b'.repeat(64),wallets:[wallet],callback:'https://example.test/events'};
 const app=createApp(cfg,l,{price:async()=> '1',scan:async()=>{if(down)throw Error('outage');return observations;},send:async()=>({ok:deliver})});
 await app.sweep();await new Promise(resolve=>app.server.listen(0,'127.0.0.1',resolve));
 const origin='http://127.0.0.1:'+app.server.address().port;
 const request=async(method,path,data=null,secret=cfg.secret)=>{
  const body=data===null?'':JSON.stringify(data),nonce=randomBytes(16).toString('hex'),stamp=String(Math.floor(Date.now()/1000));
  return fetch(origin+path,{method,headers:{'X-Mediline-Time':stamp,'X-Mediline-Nonce':nonce,'X-Mediline-Signature':signature(secret,method,path,stamp,nonce,body)},...(body?{body}:{})});
 };
 try{
  assert.equal((await fetch(origin+'/v1/status')).status,401);
  const res=await request('POST','/v1/invoices',invoice);assert.equal(res.status,200);
  const data=await res.json();assert.match(data.qr,/^data:image\/png;base64,/);
  observations=[tx('29900000')];await app.sweep();assert.equal(l.get(data.id).status,'paid');assert.equal(l.get(data.id).delivered,0);
  deliver=true;await app.sweep();assert.ok(l.get(data.id).delivered>0);
  down=true;await app.sweep();const stale=await (await request('GET','/v1/invoices/'+data.id)).json();assert.equal(stale.stale,true);
  assert.equal((await request('GET','/v1/sweep-candidates?after=0')).status,401);
  assert.equal((await request('GET','/v1/sweep-candidates?after=0',null,cfg.sweepSecret)).status,200);
 }finally{app.stop();app.server.closeAllConnections();l.db.close();}
});
