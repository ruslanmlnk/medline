import { HDKey } from '@scure/bip32';
import { base58check, bech32 } from '@scure/base';
import { sha256 } from '@noble/hashes/sha2.js';
import { ripemd160 } from '@noble/hashes/legacy.js';
import { keccak_256 } from '@noble/hashes/sha3.js';
import { ECDH } from 'node:crypto';

const b58 = base58check(sha256);
export const TRON_USDT = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
export function tronHex(address) {
  const raw = b58.decode(address);
  if (raw.length !== 21 || raw[0] !== 0x41) throw Error('Invalid TRON address');
  return Buffer.from(raw.slice(1)).toString('hex');
}
// Import only an external/change-0 branch public key, never an account private key.
// BTC: m/84'/0'/account'/0 (testnet coin type 1); TRON: m/44'/195'/account'/0.
export function branch(config) {
  const versions = config.asset === 'BTC' && config.network === 'testnet'
    ? { public: 0x043587cf, private: 0x04358394 } : undefined;
  const key = HDKey.fromExtendedKey(config.xpub, versions);
  if (key.privateKey || key.depth !== 4 || key.index !== 0) throw Error('A branch public key at depth 4, index 0 is required');
  return key;
}
export function derive(config, index) {
  if (!Number.isSafeInteger(index) || index < 0 || index >= 0x80000000) throw Error('Invalid address index');
  const publicKey = branch(config).deriveChild(index).publicKey;
  if (config.asset === 'BTC') {
    return bech32.encode(config.network === 'mainnet' ? 'bc' : 'tb', [0, ...bech32.toWords(ripemd160(sha256(publicKey)))]);
  }
  if (config.asset !== 'USDT_TRC20') throw Error('Unsupported asset');
  const uncompressed = ECDH.convertKey(publicKey, 'secp256k1', undefined, undefined, 'uncompressed');
  return b58.encode(Uint8Array.from([0x41, ...keccak_256(uncompressed.subarray(1)).slice(-20)]));
}
export function units(value, decimals) {
  if (typeof value !== 'string' || !/^\d{1,18}(\.\d{1,18})?$/.test(value)) throw Error('Invalid decimal');
  const [whole, fraction = ''] = value.split('.');
  if (fraction.length > decimals) throw Error('Too many decimal places');
  return BigInt(whole) * 10n ** BigInt(decimals) + BigInt(fraction.padEnd(decimals, '0') || '0');
}
export function display(value, decimals) {
  const raw = BigInt(value).toString().padStart(decimals + 1, '0');
  return raw.slice(0, -decimals) + '.' + raw.slice(-decimals);
}
export function quote(total, price, decimals) {
  const fiat = units(total, 2), rate = units(price, 12);
  if (fiat <= 0n || fiat > 100000000n || rate <= 0n) throw Error('Invalid amount or rate');
  const numerator = fiat * 10n ** BigInt(decimals) * 10n ** 12n;
  const denominator = 100n * rate;
  return (numerator + denominator - 1n) / denominator;
}
