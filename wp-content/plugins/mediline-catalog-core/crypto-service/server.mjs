import { createServer } from 'node:http';
import { readFileSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { createHash, createHmac, randomBytes, timingSafeEqual } from 'node:crypto';
import QRCode from 'qrcode';
import { Ledger } from './ledger.mjs';
import { derive, TRON_USDT, tronHex } from './wallet.mjs';
import { bitcoin, tron, price, fetchData } from './providers.mjs';

export function signature(secret,method,path,timestamp,nonce,body) {
  return createHmac('sha256',secret).update([method,path,timestamp,nonce,createHash('sha256').update(body).digest('hex')].join('\n')).digest('hex');
}
export function verify(secret,method,path,headers,body,ledger,now=Math.floor(Date.now()/1000)) {
  const stamp=headers['x-mediline-time'],nonce=headers['x-mediline-nonce'],sig=headers['x-mediline-signature'];
  if(!/^\d{10}$/.test(stamp||'') || Math.abs(now-Number(stamp))>120 || !/^[a-f0-9]{32}$/.test(nonce||'') || !/^[a-f0-9]{64}$/.test(sig||''))return false;
  const expected=signature(secret,method,path,stamp,nonce,body);
  return timingSafeEqual(Buffer.from(expected,'hex'),Buffer.from(sig,'hex')) && ledger.nonce(nonce,now);
}
export function validateConfig(config) {
  if(!['testnet','mainnet'].includes(config.mode) || !/^[a-f0-9]{64,128}$/.test(config.secret||''))throw Error('Set mode and a random authentication secret');
  if(config.mode==='mainnet' && config.liveApproved!==true)throw Error('Mainnet must be explicitly enabled after wallet recovery and payment tests');
  if(config.sweepSecret && (!/^[a-f0-9]{64,128}$/.test(config.sweepSecret)||config.sweepSecret===config.secret))throw Error('Sweeper needs a separate authentication secret');
  if(!Array.isArray(config.wallets)||!config.wallets.length)throw Error('Configure watch-only wallets');
  const assets=new Set();
  for(const w of config.wallets) {
    if(assets.has(w.asset)||!['BTC','USDT_TRC20'].includes(w.asset)||w.network!==config.mode)throw Error('Invalid wallet network or duplicate asset');
    assets.add(w.asset);
    const u=new URL(w.provider);
    if(u.protocol!=='https:'||u.username||u.password||u.search||u.hash)throw Error('Provider must use HTTPS');
    w.provider=w.provider.replace(/\/$/,'');
    if(w.asset==='BTC' && (!Number.isInteger(w.confirmations)||w.confirmations<3))throw Error('At least 3 Bitcoin confirmations required');
    if(w.asset==='USDT_TRC20') {
      if(w.provider!==(config.mode==='mainnet'?'https://api.trongrid.io':'https://api.shasta.trongrid.io'))throw Error('Use the matching TRON network endpoint');
      tronHex(w.contract);
      if(config.mode==='mainnet'&&w.contract!==TRON_USDT)throw Error('Wrong mainnet USDT contract');
      if(config.mode==='testnet'&&w.contract===TRON_USDT)throw Error('Configure a test token on Shasta');
    }
    if(!w.expectedFirstAddress || derive(w,0)!==w.expectedFirstAddress)throw Error('Verify the first derived address on your wallet before configuration');
  }
  if(config.callback) {
    const u=new URL(config.callback);
    if(u.protocol!=='https:'||u.username||u.password||u.search||u.hash)throw Error('Callback must use HTTPS without query strings');
  } else if(config.mode==='mainnet')throw Error('Mainnet requires a WordPress callback');
  return config;
}
export function createApp(config,ledger,dependencies={}) {
  let busy=false,lastSweep=0,stopped=false;
  const getPrice=dependencies.price||price,scan=dependencies.scan||((c,r)=>c.asset==='BTC'?bitcoin(c,r):tron(c,r));
  const send=dependencies.send||fetchData;
  async function payment(row) {
    const data=ledger.public(row);
    const content=row.asset==='BTC'?`bitcoin:${row.address}?amount=${data.remaining}`:row.address;
    // TRON QR intentionally contains only the address: token/amount are displayed separately.
    data.qr=await QRCode.toDataURL(content,{errorCorrectionLevel:'M',margin:4,width:280});
    return data;
  }
  async function sweep() {
    if(busy||stopped)return;busy=true;
    try {
      // Never delete/reuse expired addresses. Rotate by oldest scan, including paid invoices.
      const rows=ledger.db.prepare('SELECT * FROM invoices WHERE issued=1 ORDER BY attempted ASC, created ASC LIMIT 100').all();
      for(const row of rows) {
        if(stopped)break;
        const wallet=config.wallets.find(c=>c.asset===row.asset);
        ledger.db.prepare('UPDATE invoices SET attempted=? WHERE id=?').run(Math.floor(Date.now()/1000),row.id);
        try { ledger.observe(row.id,await scan(wallet,row)); }
        catch { ledger.db.prepare("UPDATE invoices SET error='provider_check_failed' WHERE id=?").run(row.id); }
        // Separate callback retries from scanning. Do not notify a stale 'paid' state after a provider error.
        const current=ledger.get(row.id);
        if(config.callback && current.revision>current.delivered && !current.error) {
          const body=JSON.stringify({invoice_id:current.id,reference:current.reference,revision:current.revision});
          const path=new URL(config.callback).pathname,stamp=String(Math.floor(Date.now()/1000)),nonce=randomBytes(16).toString('hex');
          try {
            const result=await send(config.callback,{method:'POST',headers:{'Content-Type':'application/json','X-Mediline-Time':stamp,'X-Mediline-Nonce':nonce,'X-Mediline-Signature':signature(config.secret,'POST',path,stamp,nonce,body)},body});
            if(result?.ok===true)ledger.db.prepare('UPDATE invoices SET delivered=? WHERE id=?').run(current.revision,current.id);
          }catch { /* Keep the revision pending; retry without duplicating payment. */ }
        }
      }
      lastSweep=Date.now();
    }finally{busy=false;}
  }
  const server=createServer(async(req,res)=>{
    res.setHeader('Cache-Control','no-store');res.setHeader('Content-Type','application/json');res.setHeader('X-Content-Type-Options','nosniff');
    const reply=(code,data)=>{res.writeHead(code);res.end(JSON.stringify(data));};
    try {
      const chunks=[];let size=0;
      for await(const c of req){size+=c.length;if(size>8192){reply(413,{error:'body_too_large'});req.destroy();return;}chunks.push(c);}
      const body=Buffer.concat(chunks).toString('utf8');
      if(req.url==='/health'&&req.method==='GET'){reply(200,{ok:true});return;}
      const sweepRoute=/^\/v1\/sweep(s|\-candidates)(\?|$)/.test(req.url);
      const authSecret=sweepRoute?config.sweepSecret:config.secret;
      if(!authSecret||!verify(authSecret,req.method,req.url,req.headers,body,ledger)){reply(401,{error:'unauthorized'});return;}
      if(req.method==='GET' && /^\/v1\/sweep-candidates\?after=\d+$/.test(req.url)) {
        const after=Number(new URL(req.url,'http://localhost').searchParams.get('after'));
        if(!Number.isSafeInteger(after)||after<0)throw Error('Invalid cursor');
        const rows=ledger.db.prepare("SELECT rowid AS seq,id,idx,address,asset,network,due,created,expires,status FROM invoices WHERE rowid>? AND issued=1 AND asset='USDT_TRC20' AND status IN ('paid','overpaid') AND error='' AND checked>? ORDER BY rowid LIMIT 50").all(after,Math.floor(Date.now()/1000)-180);
        reply(200,{items:rows,next:rows.length===50?rows.at(-1).seq:0});return;
      }
      if(req.method==='POST' && req.url==='/v1/sweeps') {
        const data=JSON.parse(body),row=ledger.get(data.invoice);
        if(!row || !['dry_run','activation_pending','funding_pending','sweep_pending','complete','review'].includes(data.status) || ![data.funding_tx||'',data.sweep_tx||''].every(tx=>tx===''||/^[a-f0-9]{64}$/.test(tx)))throw Error('Invalid collection status');
        const previous=ledger.db.prepare('SELECT * FROM sweeps WHERE invoice=?').get(row.id);
        if(!previous || previous.status!==data.status || previous.funding_tx!==(data.funding_tx||'') || previous.sweep_tx!==(data.sweep_tx||'')) {
          ledger.db.prepare('INSERT INTO sweeps VALUES (?,?,?,?,?) ON CONFLICT(invoice) DO UPDATE SET status=excluded.status,funding_tx=excluded.funding_tx,sweep_tx=excluded.sweep_tx,updated=excluded.updated').run(row.id,data.status,data.funding_tx||'',data.sweep_tx||'',Math.floor(Date.now()/1000));
          ledger.db.prepare('UPDATE invoices SET revision=revision+1 WHERE id=?').run(row.id);
        }
        reply(200,{ok:true});return;
      }
      if(req.url==='/v1/status'&&req.method==='GET') {
        reply(200,{mode:config.mode,assets:config.wallets.map(w=>w.asset),last_sweep:lastSweep,pending_notifications:ledger.db.prepare('SELECT COUNT(*) AS n FROM invoices WHERE revision>delivered').get().n});return;
      }
      if(req.url==='/v1/invoices'&&req.method==='POST') {
        const input=JSON.parse(body),wallet=config.wallets.find(w=>w.asset===input.asset);
        if(!wallet)throw Error('Asset unavailable');
        let row=ledger.byReference(input.reference);
        if(row) {
          if(row.asset!==input.asset||row.fiat!==input.total||row.currency!==input.currency)throw Error('Invoice conflict');
        } else {
          // Stop taking new payments if background processing has stalled.
          if(!lastSweep || Date.now()-lastSweep>180000) {reply(503,{error:'watcher_not_ready'});return;}
          row=ledger.create(input,wallet,await getPrice(wallet.asset,input.currency));
        }
        if(!row.issued) {
          const history=await scan(wallet,row);
          if(history.length)throw Error('Address already used; manual wallet recovery required');
          ledger.db.prepare("UPDATE invoices SET issued=1, checked=?, error='' WHERE id=?").run(Math.floor(Date.now()/1000),row.id);
          row=ledger.get(row.id);
        }
        reply(200,await payment(row));return;
      }
      const match=req.url.match(/^\/v1\/invoices\/([a-f0-9-]{36})$/);
      if(match&&req.method==='GET') {
        const row=ledger.get(match[1]);if(!row || !row.issued){reply(404,{error:'not_found'});return;}
        reply(200,await payment(row));return;
      }
      reply(404,{error:'not_found'});
    }catch {if(!res.headersSent)reply(503,{error:'payment_service_unavailable'});else res.end();}
  });
  server.requestTimeout=20000;server.headersTimeout=10000;server.maxHeadersCount=30;
  return {server,sweep,stop(){stopped=true;server.close();}};
}
if(process.argv[1] && import.meta.url===pathToFileURL(resolve(process.argv[1])).href) {
  process.umask(0o077);
  const config=validateConfig(JSON.parse(readFileSync(process.env.MEDILINE_CRYPTO_CONFIG||'config.json','utf8')));
  const path=process.env.MEDILINE_CRYPTO_DB||'data/payments.sqlite';mkdirSync(dirname(path),{recursive:true});
  const ledger=new Ledger(path);ledger.bindWallets(config.wallets);
  const app=createApp(config,ledger);
  await app.sweep();
  const interval=setInterval(()=>app.sweep().catch(()=>console.error('watcher_failed')),30000);
  app.server.listen(Number(process.env.PORT||8787),process.env.HOST||'127.0.0.1',()=>console.log('Watch-only payment service listening'));
  for(const signal of ['SIGINT','SIGTERM'])process.on(signal,()=>{clearInterval(interval);app.stop();});
}
