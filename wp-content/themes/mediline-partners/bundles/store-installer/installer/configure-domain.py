#!/usr/bin/env python3
"""Render the domain routes from private provisioning data, without host config edits."""
import ipaddress
import json
import os
import re
from pathlib import Path


def domain_config(raw):
    domain = str(raw).strip().lower()
    if domain.startswith('www.'):
        domain = domain[4:]
    try:
        address = ipaddress.ip_address(domain)
    except ValueError:
        address = None
    if address and address.version != 4:
        raise ValueError('IPv6 is not supported by this installer.')
    if not address and (not domain or len(domain) > 249 or
            not all(re.fullmatch(r'[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?', label)
                    for label in domain.split('.'))):
        raise ValueError('Invalid storefront domain.')
    local = bool(address) or domain == 'localhost'
    url = ('http://' if local else 'https://') + domain
    route = url if local else domain
    config = f'''{route} {{
    encode zstd gzip
    reverse_proxy wordpress:80
    header {{
        -Server
        X-Content-Type-Options nosniff
        Referrer-Policy strict-origin-when-cross-origin
    }}
}}
'''
    if not local:
        config += f'''\nhttp://www.{domain}, https://www.{domain} {{
    redir https://{domain}{{uri}} 301
}}
'''
    return domain, url, config


def configure(root):
    runtime = root / '.mediline-runtime/installation.json'
    data = json.loads(runtime.read_text('utf-8'))
    domain, url, config = domain_config(data['domain'])
    env_path = root / '.env'
    lines = env_path.read_text('utf-8').splitlines()
    values = {'MEDILINE_DOMAIN': domain, 'MEDILINE_SITE_URL': url,
              'MEDILINE_SITE_ADDRESS': domain if url.startswith('https:') else url}
    lines = [line for line in lines if line.split('=', 1)[0] not in values]
    lines.extend(f'{key}={value}' for key, value in values.items())
    temp = root / '.env.domain.tmp'
    temp.write_text('\n'.join(lines) + '\n', 'utf-8')
    os.chmod(temp, 0o600)
    temp.replace(env_path)
    (root / 'Caddyfile').write_text(config, 'utf-8')
    print(f'Domain configured: {url} (www redirects to the canonical host for public domains).')


if __name__ == '__main__':
    configure(Path('/work'))
