import { tronHex, units } from './wallet.mjs';

export async function fetchData(url, options = {}, json = true) {
  const response = await fetch(url, { ...options, redirect: 'error', signal: AbortSignal.timeout(15000) });
  if (!response.ok) throw Error('Provider unavailable');
  const reader = response.body.getReader();
  const chunks = []; let size = 0;
  try {
    for (;;) {
      const {done, value} = await reader.read(); if (done) break;
      size += value.length; if (size > 4 * 1024 * 1024) throw Error('Provider response too large');
      chunks.push(value);
    }
  } finally { await reader.cancel(); }
  const text = Buffer.concat(chunks).toString('utf8');
  return json ? JSON.parse(text) : text.trim();
}

export async function price(asset, currency, get = fetchData) {
  if (!['BTC','USDT_TRC20'].includes(asset) || !['USD','EUR'].includes(currency)) throw Error('Unsupported pair');
  const symbol = asset === 'BTC' ? 'BTC' : 'USDT';
  const result = await get(`https://api.coinbase.com/v2/prices/${symbol}-${currency}/spot`);
  if (result?.data?.base !== symbol || result?.data?.currency !== currency) throw Error('Wrong price pair');
  const amount = result.data.amount;
  if (units(amount, 12) <= 0n) throw Error('Invalid price');
  return amount;
}

export async function bitcoin(config, row, get = fetchData) {
  const base = config.provider;
  const expectedGenesis = config.network === 'mainnet'
    ? '000000000019d6689c085ae165831e934ff763ae46a2a6c172b3f1b60a8ce26f'
    : '000000000933ea01ad0ee984209779baaec3ced90fa3f408719526f8d77f4943';
  if (await get(base+'/block-height/0', {}, false) !== expectedGenesis) throw Error('Wrong Bitcoin network');
  const tipHash = await get(base+'/blocks/tip/hash', {}, false);
  if (!/^[a-f0-9]{64}$/.test(tipHash)) throw Error('Invalid Bitcoin tip');
  const tip = await get(base+'/block/'+tipHash);
  if (!Number.isSafeInteger(tip.height) || !Number.isSafeInteger(tip.timestamp) || tip.timestamp < Date.now()/1000-7200) throw Error('Stale Bitcoin tip');
  const all = []; const seen = new Set(); let cursor = '';
  for (let page=0;page<100;page++) {
    const batch = await get(base+'/address/'+row.address+'/txs/chain'+(cursor?'/'+cursor:''));
    if (!Array.isArray(batch) || batch.length>25) throw Error('Invalid Bitcoin history');
    for (const tx of batch) {
      if (!/^[a-f0-9]{64}$/.test(tx.txid) || seen.has(tx.txid) || tx.status?.confirmed !== true) throw Error('Inconsistent Bitcoin history');
      seen.add(tx.txid); all.push(tx);
    }
    if (batch.length<25) break;
    if (page===99) throw Error('Bitcoin history pagination limit');
    cursor=batch.at(-1).txid;
  }
  const mempool = await get(base+'/address/'+row.address+'/txs/mempool');
  if (!Array.isArray(mempool) || mempool.length>=50) throw Error('Incomplete Bitcoin mempool');
  for (const tx of mempool) { if (!seen.has(tx.txid)) { all.push(tx); seen.add(tx.txid); } }
  const observations=[]; const hashes=new Map();
  for (const tx of all) {
    if (!/^[a-f0-9]{64}$/.test(tx.txid) || !Array.isArray(tx.vout)) throw Error('Invalid Bitcoin transaction');
    let confirmed=false, time=0;
    if (tx.status?.confirmed) {
      const height=tx.status.block_height;
      if (!Number.isSafeInteger(height) || height>tip.height) throw Error('Unstable Bitcoin tip');
      if (!hashes.has(height)) hashes.set(height,await get(base+'/block-height/'+height,{},false));
      if (hashes.get(height)!==tx.status.block_hash) throw Error('Bitcoin chain changed');
      confirmed=tip.height-height+1>=config.confirmations;
      time=tx.status.block_time;
    }
    tx.vout.forEach((output,index)=>{
      if(output.scriptpubkey_address!==row.address)return;
      if(!Number.isSafeInteger(output.value)||output.value<0)throw Error('Invalid Bitcoin value');
      observations.push({key:tx.txid+':'+index,amount:String(output.value),confirmed,time});
    });
  }
  if(await get(base+'/block-height/'+tip.height,{},false)!==tipHash)throw Error('Bitcoin reorganization during scan');
  return observations;
}

const TRANSFER='ddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';
export function tronReceipt(receipt, txid, address, contract) {
  if(receipt.id!==txid || receipt.receipt?.result!=='SUCCESS' || !Number.isSafeInteger(receipt.blockNumber) || !Array.isArray(receipt.log))throw Error('Unconfirmed or failed TRON receipt');
  const recipient=tronHex(address), token=tronHex(contract);
  const time=Math.floor(receipt.blockTimeStamp/1000);
  if(!Number.isSafeInteger(time))throw Error('Invalid TRON timestamp');
  return receipt.log.flatMap((log,index)=>{
    if(log.address?.toLowerCase()!==token || log.topics?.[0]?.toLowerCase()!==TRANSFER)return [];
    if(log.topics.length!==3 || !log.topics.every(t=>/^[a-fA-F0-9]{64}$/.test(t)) || !/^[a-fA-F0-9]{64}$/.test(log.data))throw Error('Invalid transfer log');
    if(log.topics[2].toLowerCase()!=='0'.repeat(24)+recipient)return [];
    return [{key:txid+':'+index,amount:BigInt('0x'+log.data).toString(),confirmed:true,time}];
  });
}
export async function tron(config,row,get=fetchData) {
  const headers=config.apiKey?{'TRON-PRO-API-KEY':config.apiKey}:{};
  const post=async(path,body)=>get(config.provider+path,{method:'POST',headers:{...headers,'Content-Type':'application/json'},body:JSON.stringify(body)});
  const block=await post('/walletsolidity/getnowblock',{});
  if(!Number.isSafeInteger(block.block_header?.raw_data?.timestamp) || block.block_header.raw_data.timestamp < Date.now()-180000)throw Error('Stale TRON solidity node');
  const seen=new Set(), fingerprints=new Set();let fingerprint='';
  for(let page=0;page<100;page++) {
    const params=new URLSearchParams({limit:'200',only_confirmed:'true',only_to:'true',contract_address:config.contract});
    if(fingerprint)params.set('fingerprint',fingerprint);
    const result=await get(config.provider+'/v1/accounts/'+row.address+'/transactions/trc20?'+params,{headers});
    if(result.success!==true || !Array.isArray(result.data))throw Error('Invalid TRON history');
    for(const tx of result.data) {
      if(!/^[a-f0-9]{64}$/.test(tx.transaction_id))throw Error('Invalid TRON transaction id');
      if(tx.token_info?.address!==config.contract || tx.to!==row.address || tx.type!=='Transfer')continue;
      if(Number(tx.token_info.decimals)!==6)throw Error('Unexpected token decimals');
      seen.add(tx.transaction_id);
    }
    fingerprint=result.meta?.fingerprint || '';
    if(!fingerprint)break;
    if(page===99 || fingerprints.has(fingerprint))throw Error('Incomplete TRON history');
    fingerprints.add(fingerprint);
  }
  const observations=[];
  for(const txid of seen) {
    const receipt=await post('/walletsolidity/gettransactioninfobyid',{value:txid});
    observations.push(...tronReceipt(receipt,txid,row.address,config.contract));
  }
  return observations;
}
