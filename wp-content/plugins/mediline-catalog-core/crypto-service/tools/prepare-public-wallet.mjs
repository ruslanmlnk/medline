// Public-key-only conversion. This utility refuses private keys; never paste a seed here.
import { readFileSync } from 'node:fs';
import { HDKey } from '@scure/bip32';
import { derive } from '../wallet.mjs';
const [asset,network]=process.argv.slice(2);
if(!['BTC','USDT_TRC20'].includes(asset)||!['mainnet','testnet'].includes(network))throw Error('Usage: node tools/prepare-public-wallet.mjs BTC|USDT_TRC20 mainnet|testnet < public-key.txt');
const input=readFileSync(0,'utf8').trim();
if(!/^(xpub|tpub|zpub|vpub)[A-Za-z0-9]+$/.test(input))throw Error('Only public extended keys accepted');
const version=input.startsWith('zpub')?{public:0x04b24746,private:0x04b2430c}:input.startsWith('vpub')?{public:0x045f1cf6,private:0x045f18bc}:input.startsWith('tpub')?{public:0x043587cf,private:0x04358394}:undefined;
let key=HDKey.fromExtendedKey(input,version);
if(key.privateKey||![3,4].includes(key.depth))throw Error('An account or external branch public key is required');
if(asset==='BTC' && ((network==='testnet')!==/^(tpub|vpub)/.test(input)))throw Error('Public key network mismatch');
if(asset==='USDT_TRC20' && !input.startsWith('xpub'))throw Error('TRON requires an xpub');
if(key.depth===3)key=key.deriveChild(0);
if(key.index!==0)throw Error('Only external branch 0 is supported');
const versions=asset==='BTC'&&network==='testnet'?{public:0x043587cf,private:0x04358394}:undefined;
key=new HDKey({publicKey:key.publicKey,chainCode:key.chainCode,depth:key.depth,index:key.index,parentFingerprint:key.parentFingerprint,versions});
const config={asset,network,xpub:key.publicExtendedKey};
console.log(JSON.stringify({...config,expectedFirstAddress:derive(config,0),verifyNextAddresses:[derive(config,1),derive(config,2)]},null,2));
