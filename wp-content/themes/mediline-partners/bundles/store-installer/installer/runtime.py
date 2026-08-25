#!/usr/bin/env python3
import json, sys
from pathlib import Path
p=Path('/work/.mediline-runtime/installation.json')
if not p.exists(): raise SystemExit('Runtime provisioning data is missing.')
data=json.loads(p.read_text('utf-8'))
path=sys.argv[1].split('.') if len(sys.argv)>1 else []
value=data
for key in path:
    if not isinstance(value,dict) or key not in value: raise SystemExit(2)
    value=value[key]
if isinstance(value,list): print(','.join(str(x) for x in value))
elif isinstance(value,(dict,list)): print(json.dumps(value,separators=(',',':')))
else: print(str(value))
