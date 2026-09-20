import { readFileSync, statSync } from 'node:fs';
import { isAbsolute } from 'node:path';
import { HDKey } from '@scure/bip32';
import { TronWeb, utils } from 'tronweb';
import { tronHex } from '../wallet.mjs';
import { fetchData, tron, tronReceipt } from '../providers.mjs';
import { Review } from './sweeper.mjs';

function secretFile(path) {
  if(!path||!isAbsolute(path))throw Error('Absolute external secret-file path required');
  const stat=statSync(path);
  if(!stat.isFile() || (process.platform!=='win32'&&(stat.mode&0o077)!==0))throw Error('Secret file must be private (0600/0400)');
  return readFileSync(path,'utf8').trim();
}
function integer(value) {if(!Number.isSafeInteger(value)||value<0)throw Error('Invalid chain parameter');return BigInt(value);}
function hexAddress(address){return '41'+tronHex(address);}
export function validateTransaction(tx,expected,now=Date.now()) {
  const raw=tx.raw_data,contracts=raw?.contract;
  if(!raw || !Array.isArray(contracts)||contracts.length!==1 || !Number.isSafeInteger(raw.expiration)||raw.expiration<now+10000||raw.expiration>now+600000 ||
    !Number.isSafeInteger(raw.timestamp)||Math.abs(raw.timestamp-now)>120000 || raw.data || tx.signature?.length || !utils.transaction.txCheck(tx))throw new Review('unsafe_unsigned_transaction');
  const c=contracts[0],v=c.parameter?.value;
  if(!v || c.Permission_id || v.owner_address?.toLowerCase()!==hexAddress(expected.owner).toLowerCase())throw new Review('unexpected_transaction_owner');
  if(expected.kind==='funding') {
    if(c.type!=='TransferContract'||v.to_address?.toLowerCase()!==hexAddress(expected.to).toLowerCase()||String(v.amount)!==String(expected.amount)||raw.fee_limit)throw new Review('unexpected_funding_transaction');
  }else{
    const data='a9059cbb'+'0'.repeat(24)+tronHex(expected.to)+BigInt(expected.amount).toString(16).padStart(64,'0');
    if(c.type!=='TriggerSmartContract'||v.contract_address?.toLowerCase()!==hexAddress(expected.contract).toLowerCase()||v.data?.toLowerCase()!==data||
      (v.call_value||0)!==0||(v.call_token_value||0)!==0||(v.token_id||0)!==0||BigInt(raw.fee_limit||0)!==BigInt(expected.feeLimit))throw new Review('unexpected_token_transaction');
  }
  return tx;
}
export class TronAdapter {
  constructor(policy) {
    this.p=policy;
    this.web=new TronWeb({fullHost:policy.provider,headers:policy.apiKey?{'TRON-PRO-API-KEY':policy.apiKey}:{}});
    if(policy.mode==='execute') {
      this.branch=HDKey.fromExtendedKey(secretFile(policy.branchKeyFile));
      this.gasKey=secretFile(policy.gasKeyFile);
      if(!this.branch.privateKey || this.branch.publicExtendedKey!==policy.xpub || this.branch.depth!==4 || this.branch.index!==0)throw Error('Private branch does not match approved watch-only branch');
      if(!/^[a-f0-9]{64}$/i.test(this.gasKey)||TronWeb.address.fromPrivateKey(this.gasKey)!==policy.gasAddress)throw Error('Gas wallet key mismatch');
    }
  }
  async post(path,body={}) {
    const result=await fetchData(this.p.provider+path,{method:'POST',headers:{'Content-Type':'application/json',...(this.p.apiKey?{'TRON-PRO-API-KEY':this.p.apiKey}:{})},body:JSON.stringify(body)});
    if(result.Error || result.error)throw Error('TRON RPC failed');return result;
  }
  async fresh() {
    const b=await this.post('/walletsolidity/getnowblock');
    if(!Number.isSafeInteger(b.block_header?.raw_data?.timestamp)||Math.abs(Date.now()-b.block_header.raw_data.timestamp)>180000)throw Error('Stale chain');
  }
  async tokenBalance(address) {
    const response=await this.post('/walletsolidity/triggerconstantcontract',{owner_address:address,contract_address:this.p.contract,function_selector:'balanceOf(address)',parameter:'0'.repeat(24)+tronHex(address),visible:true});
    if(response.result?.result!==true||!/^([a-f0-9]{64})$/i.test(response.constant_result?.[0]||''))throw Error('Token balance unavailable');
    return BigInt('0x'+response.constant_result[0]);
  }
  async inspect(c) {
    const observations=await tron({...this.p,asset:'USDT_TRC20'},c);
    let confirmed=0n,inTime=0n;
    for(const t of observations) {
      if(t.time<c.created-300||t.time>Date.now()/1000+300)throw new Review('unexpected_deposit_time');
      confirmed+=BigInt(t.amount);if(t.time<=c.expires)inTime+=BigInt(t.amount);
    }
    const account=await this.post('/walletsolidity/getaccount',{address:c.address,visible:true});
    // A token can exist on an address before a native TRON account is activated.
    return {confirmed,inTime,balance:await this.tokenBalance(c.address),active:!!account.address};
  }
  async parameters() {
    const result=await this.post('/wallet/getchainparameters');
    if(!Array.isArray(result.chainParameter))throw Error('Chain parameters unavailable');
    const map=new Map(result.chainParameter.map(x=>[x.key,x.value||0]));
    const read=key=>{if(!map.has(key))throw Error('Missing chain parameter');return integer(map.get(key));};
    return {energy:read('getEnergyFee'),bandwidth:read('getTransactionFee'),activation:read('getCreateNewAccountFeeInSystemContract')+read('getCreateAccountFee'),maxFee:read('getMaxFeeLimit')};
  }
  async estimate(c,amount) {
    await this.fresh();
    const result=await this.web.transactionBuilder.estimateEnergy(this.p.contract,'transfer(address,uint256)',{},[{type:'address',value:this.p.treasury},{type:'uint256',value:amount.toString()}],c.address);
    if(result.result?.result!==true||!Number.isSafeInteger(result.energy_required)||result.energy_required<=0)throw new Review('energy_estimate_unavailable');
    const chain=await this.parameters();
    const resource=await this.post('/wallet/getaccountresource',{address:c.address,visible:true});
    const account=await this.post('/wallet/getaccount',{address:c.address,visible:true});
    const energy=(integer(result.energy_required)*120n+99n)/100n;
    const available=integer(resource.EnergyLimit||0)-integer(resource.EnergyUsed||0);
    const missing=energy-(available>0n?available:0n);
    const feeLimit=energy*chain.energy;
    if(feeLimit>chain.maxFee)throw new Review('chain_fee_limit_exceeded');
    const required=(missing>0n?missing:0n)*chain.energy+BigInt(this.p.bandwidthReserveSun);
    const balance=integer(account.balance||0);
    return {feeLimit,deficit:required>balance?required-balance:0n};
  }
  async signed(tx,expected,key) {
    validateTransaction(tx,expected);
    const chain=await this.parameters();
    // Conservative upper bound includes one signature, protobuf overhead and receipt allowance.
    const maxBytes=BigInt(tx.raw_data_hex.length/2+256);
    if(maxBytes*chain.bandwidth>BigInt(this.p.bandwidthReserveSun))throw new Review('bandwidth_budget_too_small');
    return this.web.trx.sign(tx,key);
  }
  async prepareFunding(c,amount,activate) {
    if(this.p.mode!=='execute')throw new Review('signing_disabled');
    await this.fresh();
    const params=await this.parameters();
    const reserve=BigInt(amount)+BigInt(this.p.bandwidthReserveSun)+(activate?params.activation:0n);
    const gas=await this.post('/wallet/getaccount',{address:this.p.gasAddress,visible:true});
    if(integer(gas.balance||0)<reserve+BigInt(this.p.gasReserveSun))throw new Review('gas_wallet_low_balance');
    if(BigInt(amount)>BigInt(this.p.maxPerOrderSun))throw new Review('funding_budget_exceeded');
    const tx=await this.web.transactionBuilder.sendTrx(c.address,Number(amount),this.p.gasAddress);
    return {signed:await this.signed(tx,{kind:'funding',owner:this.p.gasAddress,to:c.address,amount},this.gasKey),reserve};
  }
  async prepareSweep(c,amount,feeLimit) {
    if(this.p.mode!=='execute')throw new Review('signing_disabled');
    const key=this.branch.deriveChild(c.idx);
    if(TronWeb.address.fromPrivateKey(Buffer.from(key.privateKey).toString('hex'))!==c.address)throw new Review('derived_key_mismatch');
    if(feeLimit>BigInt(this.p.maxFeeLimitSun))throw new Review('fee_limit_exceeded');
    try {
      const result=await this.web.transactionBuilder.triggerSmartContract(this.p.contract,'transfer(address,uint256)',{feeLimit:Number(feeLimit),callValue:0},[{type:'address',value:this.p.treasury},{type:'uint256',value:amount.toString()}],c.address);
      if(result.result?.result!==true||!result.transaction)throw Error('Unable to build token transfer');
      return {signed:await this.signed(result.transaction,{kind:'sweep',owner:c.address,to:this.p.treasury,amount,contract:this.p.contract,feeLimit},Buffer.from(key.privateKey).toString('hex')),reserve:feeLimit+BigInt(this.p.bandwidthReserveSun)};
    }finally{key.wipePrivateData();}
  }
  async receipt(txid) {
    await this.fresh();
    const result=await this.post('/walletsolidity/gettransactioninfobyid',{value:txid});
    if(!result.id)return null;
    if(result.id!==txid||!Number.isSafeInteger(result.blockNumber))throw Error('Invalid receipt');return result;
  }
  async verifyReceipt(c,op,receipt) {
    const signed=JSON.parse(op.signed);
    if(integer(receipt.fee||0)>BigInt(op.reserved))throw new Review('actual_fee_exceeded_reservation');
    if(receipt.result==='FAILED'||receipt.receipt?.result&&receipt.receipt.result!=='SUCCESS')return false;
    if(op.kind==='sweep') {
      const logs=tronReceipt(receipt,op.txid,this.p.treasury,this.p.contract);
      const expected=BigInt('0x'+signed.raw_data.contract[0].parameter.value.data.slice(-64));
      return logs.reduce((n,t)=>n+BigInt(t.amount),0n)===expected;
    }
    // Native transfer receipts may omit receipt.result. Inspect the solidified transaction too.
    const tx=await this.post('/walletsolidity/gettransactionbyid',{value:op.txid});
    if(tx.txID!==op.txid||tx.ret?.[0]?.contractRet!=='SUCCESS')return false;
    const v=tx.raw_data?.contract?.[0]?.parameter?.value,expected=signed.raw_data.contract[0].parameter.value;
    return tx.raw_data.contract.length===1&&tx.raw_data.contract[0].type==='TransferContract'&&
      v.owner_address===expected.owner_address&&v.to_address===expected.to_address&&String(v.amount)===String(expected.amount);
  }
  async broadcast(signed,op) {
    await this.fresh();
    const p=await this.parameters();
    const bandwidth=BigInt(signed.raw_data_hex.length/2+256)*p.bandwidth;
    const value=signed.raw_data.contract[0].parameter.value;
    const worst=op.kind==='sweep'?BigInt(signed.raw_data.fee_limit)+bandwidth:
      BigInt(value.amount)+bandwidth+(op.kind==='activation'?p.activation:0n);
    if(worst>BigInt(op.reserved))throw new Review('network_fees_changed_before_broadcast');
    const result=await this.web.trx.sendRawTransaction(signed);
    if(result.result!==true && result.code!=='DUP_TRANSACTION_ERROR')throw Error('Broadcast not acknowledged');
  }
}
