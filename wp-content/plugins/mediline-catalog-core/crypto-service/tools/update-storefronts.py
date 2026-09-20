"""Apply crypto checkout support locally. No network/deployment or wallet configuration."""
from pathlib import Path
import argparse, zipfile, io, shutil, datetime

NOTES = {
 'en': 'Pay on this site after placing your order: the address, exact amount and QR code will be displayed here.',
 'fr': 'Payez sur ce site après la commande : l’adresse, le montant exact et le QR code seront affichés ici.',
 'de': 'Bezahlen Sie nach der Bestellung direkt auf dieser Website: Adresse, genauer Betrag und QR-Code werden hier angezeigt.',
 'es': 'Paga en este sitio después de hacer el pedido: aquí se mostrarán la dirección, el importe exacto y el código QR.',
 'it': 'Paga su questo sito dopo aver effettuato l’ordine: qui saranno mostrati indirizzo, importo esatto e codice QR.'
}
def patch(files):
    js=files['assets/js/storefront.js'].decode('utf-8')
    if 'window.MedilineCrypto?.mount' not in js:
        old='      window.location.assign(payload.checkout_url);'
        assert old in js, 'Checkout redirect not found'
        js=js.replace(old,"      if (payload.crypto_payment && window.MedilineCrypto?.mount(payload, form)) return;\n"+old,1)
    if 'form.dataset.cryptoSubmission' not in js:
        old='    const requestBody = {'
        assert old in js
        js=js.replace(old,"    form.dataset.cryptoSubmission ||= crypto.randomUUID();\n"+old+"\n      attribution: { submission_id: form.dataset.cryptoSubmission },",1)
    cart=files['storefront/cart.php'].decode('utf-8')
    if 'mediline_store_online_crypto' not in cart:
        marker="$t = $copy[ $lang ] ?? $copy['en'];"
        assert marker in cart
        lines=[marker,"if ( get_option( 'mediline_store_online_crypto', false ) ) {",' $crypto_notes = array(']
        for lang,note in NOTES.items(): lines.append("  '%s' => '%s',"%(lang,note.replace("'","\\'")))
        lines += [" );", " $t['btc_note'] = $t['usdt_note'] = $crypto_notes[ $lang ] ?? $crypto_notes['en'];",'}']
        cart=cart.replace(marker,'\n'.join(lines),1)
    return {'assets/js/storefront.js':js.encode(),'storefront/cart.php':cart.encode()}

if __name__=='__main__':
    parser=argparse.ArgumentParser();parser.add_argument('--domains',type=Path,required=True);parser.add_argument('--project',type=Path,required=True);args=parser.parse_args()
    backup=args.project/'.mediline-test-runtime/deploy-tools'/('crypto-ui-backup-'+datetime.datetime.now().strftime('%Y%m%d-%H%M%S'));backup.mkdir(parents=True)
    for name in ('aeris','nova24','pulse','bloom','apotheke','verde'):
        root=args.domains/('mediline-'+name)/'wp-content/themes'/('mediline-'+name)
        result=patch({rel:(root/rel).read_bytes() for rel in ('assets/js/storefront.js','storefront/cart.php')})
        for rel,data in result.items():
            b=backup/name/rel;b.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(root/rel,b);(root/rel).write_bytes(data)
    for path in (args.project/'wp-content/mediline-private-packages').glob('template-*.zip'):
        out=io.BytesIO()
        with zipfile.ZipFile(path) as src,zipfile.ZipFile(out,'w',zipfile.ZIP_DEFLATED) as dest:
            roots={name.split('/')[0] for name in src.namelist() if name.endswith('/storefront/cart.php')}
            changes={}
            for root in roots:
                changes.update({root+'/'+rel:data for rel,data in patch({rel:src.read(root+'/'+rel) for rel in ('assets/js/storefront.js','storefront/cart.php')}).items()})
            for item in src.infolist():
                data=changes.get(item.filename,src.read(item))
                if item.filename in changes:item.date_time=datetime.datetime.now().timetuple()[:6]
                dest.writestr(item,data)
        shutil.copy2(path,backup/path.name);path.write_bytes(out.getvalue());print(path.name)
    for name in ('payment.js','payment.css'):
        src=args.project/'wp-content/plugins/mediline-catalog-core/assets/crypto'/name
        dest=args.project/'wp-content/plugins/mediline-store-core/assets/crypto'/name
        dest.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(src,dest)
    print('Backups:',backup)
