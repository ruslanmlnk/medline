/* First-party payment UI. No third-party QR service, wallet keys or payment assertions from the browser. */
(() => {
  'use strict';
  const cfg = window.MedilineCryptoConfig || {};
  const copy = {
    en: ['Pay with crypto','Order','Network','Amount to send','Wallet address','Copy','Copied','Waiting for payment','Payment detected; waiting for confirmations','Partial payment received','Payment confirmed','Payment confirmed; overpayment recorded','Payment window expired. Do not send a new payment.','Payment requires review. Do not send more funds.','Blockchain confirmation changed; contact support.','Checking payment…','Unable to refresh. We will retry automatically.','Send only the specified asset using the network shown. Network fees are additional.','TEST NETWORK — do not send real funds','Time remaining','Received','Continue shopping','The QR code contains the address only. Enter the USDT amount separately.'],
    fr: ['Payer en cryptomonnaie','Commande','Réseau','Montant à envoyer','Adresse du portefeuille','Copier','Copié','En attente du paiement','Paiement détecté ; confirmations en attente','Paiement partiel reçu','Paiement confirmé','Paiement confirmé ; trop-perçu enregistré','Le délai de paiement a expiré. N’envoyez pas de nouveau paiement.','Paiement à vérifier. N’envoyez pas de fonds supplémentaires.','La confirmation blockchain a changé ; contactez le support.','Vérification du paiement…','Actualisation impossible. Nouvelle tentative automatique.','Envoyez uniquement l’actif indiqué sur le réseau affiché. Les frais de réseau sont en supplément.','RÉSEAU DE TEST — n’envoyez pas de fonds réels','Temps restant','Reçu','Continuer les achats','Le QR code contient uniquement l’adresse. Saisissez le montant en USDT séparément.'],
    de: ['Mit Kryptowährung bezahlen','Bestellung','Netzwerk','Zu sendender Betrag','Wallet-Adresse','Kopieren','Kopiert','Warten auf Zahlung','Zahlung erkannt; Bestätigungen stehen aus','Teilzahlung erhalten','Zahlung bestätigt','Zahlung bestätigt; Überzahlung erfasst','Die Zahlungsfrist ist abgelaufen. Senden Sie keine neue Zahlung.','Zahlung muss geprüft werden. Senden Sie kein weiteres Geld.','Blockchain-Bestätigung geändert; kontaktieren Sie den Support.','Zahlung wird geprüft…','Aktualisierung fehlgeschlagen. Wir versuchen es automatisch erneut.','Senden Sie nur die angegebene Kryptowährung über das angezeigte Netzwerk. Netzwerkgebühren fallen zusätzlich an.','TESTNETZWERK — kein echtes Geld senden','Verbleibende Zeit','Erhalten','Weiter einkaufen','Der QR-Code enthält nur die Adresse. Geben Sie den USDT-Betrag separat ein.'],
    es: ['Pagar con criptomonedas','Pedido','Red','Importe a enviar','Dirección de la cartera','Copiar','Copiado','Esperando el pago','Pago detectado; esperando confirmaciones','Pago parcial recibido','Pago confirmado','Pago confirmado; exceso registrado','El plazo de pago ha vencido. No envíes un nuevo pago.','El pago requiere revisión. No envíes más fondos.','La confirmación de blockchain ha cambiado; contacta con soporte.','Comprobando el pago…','No se puede actualizar. Lo intentaremos automáticamente.','Envía únicamente el activo indicado por la red mostrada. Las comisiones de red son adicionales.','RED DE PRUEBA — no envíes fondos reales','Tiempo restante','Recibido','Seguir comprando','El código QR solo contiene la dirección. Introduce el importe en USDT por separado.'],
    it: ['Paga in criptovaluta','Ordine','Rete','Importo da inviare','Indirizzo del portafoglio','Copia','Copiato','In attesa del pagamento','Pagamento rilevato; in attesa delle conferme','Pagamento parziale ricevuto','Pagamento confermato','Pagamento confermato; eccedenza registrata','Il termine di pagamento è scaduto. Non inviare un nuovo pagamento.','Il pagamento richiede una verifica. Non inviare altri fondi.','La conferma blockchain è cambiata; contatta l’assistenza.','Verifica del pagamento…','Aggiornamento non disponibile. Riproveremo automaticamente.','Invia solo la valuta indicata sulla rete mostrata. Le commissioni di rete sono aggiuntive.','RETE DI TEST — non inviare fondi reali','Tempo rimanente','Ricevuto','Continua gli acquisti','Il codice QR contiene solo l’indirizzo. Inserisci separatamente l’importo in USDT.']
  };
  const statusIndex = {awaiting:7,confirming:8,partial:9,paid:10,overpaid:11,expired:12,late_review:13,reorg_review:14};
  const storageKey = 'mediline_crypto_checkout_v1';
  let stopPrevious = () => {};
  function element(tag, text, cls) { const e=document.createElement(tag); if(text!==undefined)e.textContent=text; if(cls)e.className=cls; return e; }
  function mount(payload, target, persist = true) {
    if (!target || !payload.order_id || !payload.crypto_order_key) return false;
    stopPrevious();
    const t=copy[payload.language || cfg.lang] || copy.en;
    const ref={order_id:payload.order_id,crypto_order_key:payload.crypto_order_key,language:payload.language || cfg.lang};
    if(persist)try{sessionStorage.setItem(storageKey,JSON.stringify(ref));}catch{}
    const root=element('section',undefined,'mediline-crypto-panel');root.setAttribute('aria-labelledby','ml-crypto-title');
    const title=element('h2',t[0]);title.id='ml-crypto-title';root.append(title,element('p',`${t[1]} #${payload.invoice_id || payload.order_id}`));
    const test=element('p',t[18],'ml-crypto-test');root.append(test);test.hidden=true;
    const status=element('p',t[15],'ml-crypto-status');status.setAttribute('role','status');root.append(status);
    const error=element('p','','ml-crypto-error');error.setAttribute('role','status');root.append(error);
    const details=element('div',undefined,'ml-crypto-details');root.append(details);details.hidden=true;
    const network=element('p');details.append(network);
    const received=element('p');root.append(received);
    const qr=element('img');qr.width=280;qr.height=280;qr.alt=t[4];details.append(qr);
    const fields=element('div',undefined,'ml-crypto-fields');details.append(fields);
    function field(label) {
      const box=element('div',undefined,'ml-crypto-field'), value=element('input'), button=element('button',t[5]);
      const caption=element('label',label);value.readOnly=true;value.spellcheck=false;caption.append(value);button.type='button';button.setAttribute('aria-label',t[5]+' — '+label);
      button.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(value.value);button.textContent=t[6];setTimeout(()=>button.textContent=t[5],1500);}catch{value.focus();value.select();}});
      box.append(caption,button);fields.append(box);return value;
    }
    const amount=field(t[3]),address=field(t[4]);
    const timer=element('p');details.append(timer,element('p',t[17]));
    const tronNote=element('p',t[22]);details.append(tronNote);
    const done=element('button',t[21]);done.type='button';done.addEventListener('click',()=>{try{sessionStorage.removeItem(storageKey);}catch{}stopPrevious();window.location.assign(document.querySelector('.custom-logo-link')?.href || '/');});root.append(done);
    if(target.matches('.mediline-crypto-receipt'))target.replaceChildren(root);else target.replaceWith(root);
    let active=true,delay,current=null;
    function show(data) {
      if(data?.pending){status.textContent=t[15];details.hidden=true;return;}
      if(!data || data.unavailable){error.textContent=t[16];details.hidden=true;return;}
      if(!Object.hasOwn(statusIndex,data.status)||!['BTC','USDT_TRC20'].includes(data.asset)||!['mainnet','testnet'].includes(data.network))return;
      current=data;test.hidden=data.network!=='testnet';status.textContent=t[statusIndex[data.status]];
      error.textContent=data.stale?t[16]:'';
      const payable=['awaiting','confirming','partial'].includes(data.status)&&data.expires*1000>Date.now()&&!data.stale&&Number(data.remaining??data.amount)>0;
      details.hidden=!payable;
      network.textContent=`${t[2]}: ${data.asset==='BTC'?'BTC · Bitcoin':'USDT · TRON (TRC-20)'} — ${data.network}`;
      amount.value=data.remaining??data.amount;address.value=data.address;
      received.textContent=`${t[20]}: ${data.received} ${data.asset==='BTC'?'BTC':'USDT'}`;
      if(/^data:image\/png;base64,[A-Za-z0-9+/=]+$/.test(data.qr||''))qr.src=data.qr;
      tronNote.hidden=data.asset!=='USDT_TRC20';tick();
    }
    function tick() {
      if(!current)return;
      const seconds=Math.max(0,Math.floor(current.expires-Date.now()/1000));
      timer.textContent=`${t[19]}: ${Math.floor(seconds/60)}:${String(seconds%60).padStart(2,'0')}`;
      if(!seconds){details.hidden=true;if(['awaiting','confirming','partial'].includes(current.status))status.textContent=t[12];}
    }
    async function poll() {
      try {
        const response=await fetch(cfg.endpoint,{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json'},body:JSON.stringify({order_id:ref.order_id,order_key:ref.crypto_order_key}),signal:AbortSignal.timeout(50000)});
        if(!response.ok)throw Error('Payment unavailable');const data=await response.json();if(active)show(data);
      }catch{if(active){error.textContent=t[16];details.hidden=true;}}
      finally{if(active)delay=setTimeout(poll,15000);}
    }
    const clock=setInterval(tick,1000);stopPrevious=()=>{active=false;clearTimeout(delay);clearInterval(clock);};
    show(payload.crypto_payment);poll();root.scrollIntoView({block:'start',behavior:'smooth'});return true;
  }
  window.MedilineCrypto={mount};
  function init() {
    const receipt=document.querySelector('.mediline-crypto-receipt');
    if(receipt){try{mount(JSON.parse(receipt.dataset.payment),receipt,false);}catch{}return;}
    const form=document.querySelector('.js-order-form');if(!form)return;
    try{const ref=JSON.parse(sessionStorage.getItem(storageKey));if(ref)mount(ref,form);}catch{}
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
