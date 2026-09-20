import { readFileSync, existsSync, mkdirSync } from 'node:fs';
import { dirname } from 'node:path';
import { randomBytes } from 'node:crypto';
import { signature } from '../server.mjs';
import { fetchData } from '../providers.mjs';
import { validatePolicy, SweepStore, Sweeper } from './sweeper.mjs';
import { TronAdapter } from './tron-adapter.mjs';

process.umask(0o077);
// The production entrypoint must hold a process-wide flock for the entire lifetime.
if(process.env.MEDILINE_SIGNER_LOCKED!=='1')throw Error('Start through the flock entrypoint');
const configPath=process.env.MEDILINE_SIGNER_CONFIG;
if(!configPath)throw Error('External signer configuration path required');
const policy=validatePolicy(JSON.parse(readFileSync(configPath,'utf8')));
if(policy.mode==='disabled'){console.log('Signing disabled');process.exit(0);}
const observer=new URL(policy.observer);
if(observer.protocol!=='https:'||observer.pathname!=='/'||observer.search||observer.hash||observer.username)throw Error('Watcher must be an HTTPS origin');
if(!/^[a-f0-9]{64,128}$/.test(policy.observerSecret||''))throw Error('Separate watcher/sweeper authentication secret required');
const dbPath=process.env.MEDILINE_SIGNER_DB;
if(!dbPath)throw Error('External durable ledger path required');
mkdirSync(dirname(dbPath),{recursive:true});
const store=new SweepStore(dbPath,policy),adapter=new TronAdapter(policy),sweeper=new Sweeper(policy,store,adapter);
const pauseFile=process.env.MEDILINE_SIGNER_PAUSE||dirname(dbPath)+'/PAUSE';
let running=false,stopped=false,after=0;
async function request(method,path,data=null) {
 const body=data===null?'':JSON.stringify(data),stamp=String(Math.floor(Date.now()/1000)),nonce=randomBytes(16).toString('hex');
 const headers={'Content-Type':'application/json','X-Mediline-Time':stamp,'X-Mediline-Nonce':nonce,'X-Mediline-Signature':signature(policy.observerSecret,method,path,stamp,nonce,body)};
 return fetchData(new URL(path,observer),{method,headers,...(body?{body}:{})});
}
async function cycle() {
 if(running||stopped||existsSync(pauseFile))return;
 running=true;
 try {
  // Resume local pending operations even when the watcher is down or the order left its candidate list.
  const saved=store.db.prepare("SELECT candidate FROM jobs WHERE state NOT IN ('complete','review') ORDER BY updated LIMIT 50").all();
  for(const row of saved) {if(stopped||existsSync(pauseFile))break;await sweeper.step(JSON.parse(row.candidate));}
  const result=await request('GET','/v1/sweep-candidates?after='+after);
  if(!Array.isArray(result.items)||!Number.isSafeInteger(result.next)||result.next<0)throw Error('Invalid candidate response');
  for(const candidate of result.items){if(stopped||existsSync(pauseFile))break;await sweeper.step(candidate);}
  after=result.next;
  // Reports are idempotent. They never change the invoice's payment state.
  for(const row of store.db.prepare('SELECT id FROM jobs WHERE reported=0 ORDER BY updated LIMIT 100').all()) {
    const result=await request('POST','/v1/sweeps',store.report(row.id));
    if(result?.ok===true)store.db.prepare('UPDATE jobs SET reported=1 WHERE id=?').run(row.id);
  }
 }catch{console.error('Collection cycle deferred; inspect protected ledger and provider availability');}
 finally{running=false;}
}
await cycle();const timer=setInterval(cycle,30000);
for(const signal of ['SIGTERM','SIGINT'])process.on(signal,()=>{stopped=true;clearInterval(timer);});
