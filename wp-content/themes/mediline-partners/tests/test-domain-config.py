import importlib.util
import json
import tempfile
import unittest
from pathlib import Path

source = Path(__file__).resolve().parents[1] / 'bundles/store-installer/installer/configure-domain.py'
spec = importlib.util.spec_from_file_location('domain_config', source)
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class DomainConfigTests(unittest.TestCase):
    def test_www_and_path_preservation(self):
        domain, url, config = module.domain_config('WWW.Shop.Example.COM')
        self.assertEqual(domain, 'shop.example.com')
        self.assertEqual(url, 'https://shop.example.com')
        self.assertIn('http://www.shop.example.com, https://www.shop.example.com', config)
        self.assertIn('redir https://shop.example.com{uri} 301', config)

    def test_local(self):
        for host in ['localhost', '127.0.0.1']:
            _, url, config = module.domain_config(host)
            self.assertEqual(url, 'http://' + host)
            self.assertNotIn('www.', config)

    def test_untrusted_hosts(self):
        for host in ['example.com\n:80', 'example.com/path', 'example.com:80', '*.example.com', '-bad.com', '::1', '']:
            with self.assertRaises(ValueError):
                module.domain_config(host)

    def test_preserves_database_credentials_on_retry(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / '.mediline-runtime').mkdir()
            (root / '.mediline-runtime/installation.json').write_text(json.dumps({'domain':'www.example.com'}))
            (root / '.env').write_text('WORDPRESS_DB_PASSWORD=keep_this\nMEDILINE_DOMAIN=www.example.com\n')
            module.configure(root)
            first = (root / '.env').read_text()
            module.configure(root)
            self.assertEqual(first, (root / '.env').read_text())
            self.assertIn('WORDPRESS_DB_PASSWORD=keep_this', first)
            self.assertIn('MEDILINE_SITE_URL=https://example.com', first)


if __name__ == '__main__':
    unittest.main()
