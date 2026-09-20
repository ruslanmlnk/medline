"""Build reviewable artifacts without secrets, runtime state or signing code in WP packages."""
from pathlib import Path
import argparse, zipfile, shutil, hashlib, json
parser=argparse.ArgumentParser();parser.add_argument('--project',type=Path,required=True);parser.add_argument('--output',type=Path,required=True);args=parser.parse_args()
args.output.mkdir(parents=True,exist_ok=True)
exclude={'node_modules','data','__pycache__','.git'}
def allowed(p):
    return not any(s in exclude for s in p.parts) and p.suffix not in {'.key','.pem','.sqlite','.pyc'} and not any(s in p.name for s in ('.sqlite-','.sqlite.')) and p.name not in {'config.json','signer-config.json'}
def pack(root,dest,service=False):
    with zipfile.ZipFile(dest,'w',zipfile.ZIP_DEFLATED) as z:
        for p in sorted(root.rglob('*')):
            rel=p.relative_to(root)
            if not p.is_file() or not allowed(rel):continue
            if not service and 'crypto-service' in rel.parts:continue
            if not service and any(s in {'test','tests'} for s in rel.parts):continue
            z.write(p,root.name+'/'+rel.as_posix())
    with zipfile.ZipFile(dest) as z:
        assert z.testzip() is None
        assert all('\\' not in n and allowed(Path(n)) for n in z.namelist())
plugins=args.project/'wp-content/plugins'
for name in ['mediline-catalog-core','mediline-store-core','mediline-integrations']:
    pack(plugins/name,args.output/(name+'.zip'))
pack(plugins/'mediline-catalog-core/crypto-service',args.output/'mediline-crypto-service.zip',True)
bundle=args.project/'wp-content/themes/mediline-partners/bundles/mediline-store-core.zip'
if bundle.exists() and not (args.output/'previous-store-core.zip').exists():shutil.copy2(bundle,args.output/'previous-store-core.zip')
shutil.copy2(args.output/'mediline-store-core.zip',bundle)
for p in (args.project/'wp-content/mediline-private-packages').glob('template-*.zip'):shutil.copy2(p,args.output/p.name)
manifest={p.name:hashlib.sha256(p.read_bytes()).hexdigest() for p in args.output.glob('*.zip')}
(args.output/'sha256.json').write_text(json.dumps(manifest,indent=2),encoding='utf-8')
print(json.dumps({'output':str(args.output),'packages':list(manifest)},indent=2))
