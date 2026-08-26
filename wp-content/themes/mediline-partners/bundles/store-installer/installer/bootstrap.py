#!/usr/bin/env python3
import ipaddress
import json
import os
import re
import secrets
import urllib.error
import urllib.request
from pathlib import Path

ROOT = Path('/work')
manifest_path = ROOT / 'mediline-installation.json'
runtime_dir = ROOT / '.mediline-runtime'
runtime_file = runtime_dir / 'installation.json'
env_file = ROOT / '.env'
host_label = re.compile(r'^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$')


def read_json(path, label):
    try:
        value = json.loads(path.read_text('utf-8'))
    except (OSError, UnicodeError, json.JSONDecodeError) as exc:
        raise SystemExit(f'{label} is unreadable or invalid: {exc}')
    if not isinstance(value, dict):
        raise SystemExit(f'{label} must contain a JSON object.')
    return value


def normalized_domain(raw):
    domain = str(raw).strip().lower()
    if domain == 'localhost':
        return domain, True
    try:
        address = ipaddress.ip_address(domain)
    except ValueError:
        if not domain or len(domain) > 253 or all(char in '0123456789.' for char in domain):
            raise SystemExit('Provisioned domain is invalid.')
        labels = domain.split('.')
        if any(len(label) > 63 or not host_label.fullmatch(label) for label in labels):
            raise SystemExit('Provisioned domain is invalid. Ports and URL paths are not accepted.')
        return domain, False
    if not isinstance(address, ipaddress.IPv4Address):
        raise SystemExit('Provisioned domain must be a hostname or IPv4 address.')
    return str(address), True


def validate_installation(installation):
    catalog = installation.get('catalog') or {}
    required = ['installation_id', 'domain', 'store_name', 'admin_email', 'admin_username', 'admin_password', 'primary_language', 'languages']
    for key in required:
        if key not in installation or installation[key] in ('', None, []):
            raise SystemExit(f'Provisioned field {key} is missing.')
    for key in ('api_url', 'store_id', 'store_secret'):
        if not catalog.get(key):
            raise SystemExit(f'Catalog field {key} is missing.')
    domain, localish = normalized_domain(installation['domain'])
    installation['domain'] = domain
    site_url = ('http://' if localish else 'https://') + domain
    site_address = ('http://' + domain) if localish else domain
    return site_url, site_address


def rnd(length=24):
    return secrets.token_urlsafe(length).replace('-', 'A').replace('_', 'B')


def write_runtime(installation):
    runtime_dir.mkdir(mode=0o700, exist_ok=True)
    temporary = runtime_dir / 'installation.json.tmp'
    temporary.write_text(json.dumps(installation, indent=2, ensure_ascii=False), 'utf-8')
    os.chmod(temporary, 0o600)
    temporary.replace(runtime_file)


def write_env(installation):
    site_url, site_address = validate_installation(installation)
    env = {
        'WORDPRESS_DB_NAME': 'mediline',
        'WORDPRESS_DB_USER': 'mediline',
        'WORDPRESS_DB_PASSWORD': rnd(24),
        'MARIADB_ROOT_PASSWORD': rnd(28),
        'WORDPRESS_TABLE_PREFIX': 'wp_',
        'MEDILINE_SITE_URL': site_url,
        'MEDILINE_SITE_ADDRESS': site_address,
        'MEDILINE_DOMAIN': installation['domain'],
    }
    temporary = ROOT / '.env.tmp'
    temporary.write_text('\n'.join(f'{key}={value}' for key, value in env.items()) + '\n', 'utf-8')
    os.chmod(temporary, 0o600)
    temporary.replace(env_file)
    return site_url


if runtime_file.exists():
    runtime = read_json(runtime_file, 'Local provisioning data')
    validate_installation(runtime)
    if manifest_path.exists():
        manifest = read_json(manifest_path, 'Installation manifest')
        if manifest.get('installation_id') != runtime.get('installation_id'):
            raise SystemExit('Local provisioning data belongs to another installation. Restore its matching package or remove that interrupted deployment before retrying.')
    if env_file.exists():
        print('Provisioning payload already claimed locally; reusing it.')
        raise SystemExit(0)
    site_url = write_env(runtime)
    print(f'Recovered local environment for {runtime.get("store_name")} at {site_url}.')
    raise SystemExit(0)

if env_file.exists():
    raise SystemExit('.env exists without local provisioning data. Refusing to claim a new installation into a mixed runtime.')
if not manifest_path.exists():
    raise SystemExit('mediline-installation.json is missing.')

manifest = read_json(manifest_path, 'Installation manifest')
for key in ('installation_id', 'token', 'provision_url'):
    if not manifest.get(key):
        raise SystemExit(f'Manifest field {key} is missing.')

payload = json.dumps({'installation_id': manifest['installation_id'], 'token': manifest['token']}).encode()
request = urllib.request.Request(
    manifest['provision_url'],
    data=payload,
    method='POST',
    headers={'Content-Type': 'application/json', 'Accept': 'application/json', 'User-Agent': 'Mediline-Installer/1.1.5'},
)
try:
    with urllib.request.urlopen(request, timeout=30) as response:
        raw = response.read().decode('utf-8')
except urllib.error.HTTPError as exc:
    body = exc.read().decode('utf-8', 'replace')
    raise SystemExit(f'Provisioning failed ({exc.code}): {body[:500]}')
except Exception as exc:
    raise SystemExit(f'Provisioning request failed: {exc}')

try:
    data = json.loads(raw)
except json.JSONDecodeError as exc:
    raise SystemExit(f'Provisioning response is not valid JSON: {exc}')
installation = data.get('installation') if isinstance(data, dict) else None
if not isinstance(installation, dict):
    raise SystemExit('Provisioning response does not contain installation configuration.')

site_url, _ = validate_installation(installation)
write_runtime(installation)
write_env(installation)
print(f'Provisioned {installation.get("store_name")} for {site_url}.')
