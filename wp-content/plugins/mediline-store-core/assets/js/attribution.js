(() => {
  'use strict';

  const cfg = window.MedilineStoreAttributionConfig || {};
  const cookieName = String(cfg.cookieName || 'mediline_attribution');
  const storageKey = String(cfg.storageKey || 'mediline_attribution_v1');
  const ttlSeconds = Math.min(31536000, Math.max(86400, Number(cfg.ttlSeconds) || 7776000));
  const touchFields = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid', 'language', 'landing_url'];
  const fields = [
    'pap_visitor_id', 'pap_affiliate_id',
    'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
    'gclid', 'fbclid', 'language', 'landing_url', 'submission_id'
  ];
  const textLimits = {
    pap_visitor_id: 64,
    pap_affiliate_id: 100,
    utm_source: 100,
    utm_medium: 100,
    utm_campaign: 100,
    utm_term: 100,
    utm_content: 100,
    gclid: 160,
    fbclid: 160,
    language: 16,
    landing_url: 500,
    submission_id: 64
  };

  const safeDecode = (value) => {
    try { return decodeURIComponent(value); } catch (_) { return value; }
  };
  const cookieValue = (name) => {
    const prefix = `${encodeURIComponent(name)}=`;
    const part = document.cookie.split(';').map((item) => item.trim()).find((item) => item.startsWith(prefix));
    return part ? safeDecode(part.slice(prefix.length)) : '';
  };
  const readObject = (value) => {
    if (!value || typeof value !== 'string' || value.length > 8192) return {};
    try {
      const parsed = JSON.parse(value);
      return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
    } catch (_) { return {}; }
  };
  const cleanText = (value, key) => {
    if (typeof value !== 'string' && typeof value !== 'number') return '';
    const limit = textLimits[key] || 255;
    return String(value).replace(/[\u0000-\u001F\u007F]/g, '').trim().slice(0, limit);
  };
  const cleanAffiliateId = (value) => cleanText(value, 'pap_affiliate_id').replace(/[^A-Za-z0-9._@-]/g, '');
  const strictLocationAffiliateId = (value) => typeof value === 'string' && /^[A-Za-z0-9._@-]{1,100}$/.test(value) ? value : '';
  const singleLocationAffiliateId = (params, name) => {
    const values = params.getAll(name);
    return values.length === 1 ? strictLocationAffiliateId(values[0]) : '';
  };
  const sanitize = (raw) => {
    const clean = {};
    if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return clean;
    fields.forEach((key) => {
      let value = cleanText(raw[key], key);
      if (!value) return;
      if (key === 'pap_visitor_id') {
        value = value.replace(/[^A-Za-z0-9]/g, '');
        if (value.length > 32) value = value.slice(-32);
        if (value.length !== 32) return;
      }
      if (key === 'pap_affiliate_id') value = cleanAffiliateId(value);
      if (key === 'gclid' || key === 'fbclid') value = value.replace(/[^A-Za-z0-9._~-]/g, '');
      if (key === 'language') value = value.toLowerCase().replace(/[^a-z0-9_-]/g, '');
      if (key === 'submission_id') value = value.replace(/[^A-Za-z0-9_-]/g, '');
      if (key === 'landing_url') {
        try {
          const source = new URL(value, window.location.href);
          if (source.protocol !== 'http:' && source.protocol !== 'https:') return;
          const url = new URL(`${source.origin}${source.pathname}`);
          touchFields.slice(0, 7).forEach((field) => {
            const candidate = cleanText(source.searchParams.get(field) || '', field);
            if (candidate) url.searchParams.set(field, candidate);
          });
          value = url.href.slice(0, textLimits.landing_url);
        } catch (_) { return; }
      }
      if (value) clean[key] = value;
    });
    return clean;
  };
  const papAffiliateIdFromLocation = () => {
    try {
      const query = new URLSearchParams(window.location.search || '');
      const queryId = singleLocationAffiliateId(query, 'a_aid') || singleLocationAffiliateId(query, 'pap_affiliate_id');
      if (queryId) return queryId;
    } catch (_) {}
    try {
      const hash = typeof window.location.hash === 'string' ? window.location.hash : '';
      if (hash.length > 2048 || !hash.startsWith('#a_aid=')) return '';
      return singleLocationAffiliateId(new URLSearchParams(hash.slice(1)), 'a_aid');
    } catch (_) { return ''; }
  };
  const sanitizeTouch = (raw) => {
    const flat = sanitize(raw);
    const touch = {};
    touchFields.forEach((key) => { if (flat[key]) touch[key] = flat[key]; });
    const capturedAt = Number(raw && raw.captured_at);
    if (Number.isFinite(capturedAt) && capturedAt > 0) touch.captured_at = Math.floor(capturedAt);
    return touch;
  };
  const rawStored = () => {
    let local = {};
    try { local = readObject(window.localStorage.getItem(storageKey) || ''); } catch (_) {}
    let cookie = readObject(cookieValue(cookieName));
    if (Number(local.expires_at) > 0 && Number(local.expires_at) < Math.floor(Date.now() / 1000)) local = {};
    if (Number(cookie.expires_at) > 0 && Number(cookie.expires_at) < Math.floor(Date.now() / 1000)) cookie = {};
    if (!Object.keys(cookie).length) return local;
    if (!Object.keys(local).length) return cookie;
    const newest = Number(local.updated_at || 0) > Number(cookie.updated_at || 0) ? local : cookie;
    return newest && typeof newest === 'object' ? newest : {};
  };
  const readStored = () => {
    const stored = rawStored();
    const current = stored.current && typeof stored.current === 'object' ? stored.current : stored;
    return sanitize({ ...current, pap_visitor_id: stored.pap_visitor_id, pap_affiliate_id: stored.pap_affiliate_id });
  };
  const writeStored = (raw) => {
    const value = sanitize(raw);
    delete value.submission_id;
    const previous = rawStored();
    const current = sanitizeTouch(value);
    current.captured_at = Math.floor(Date.now() / 1000);
    const previousFirst = sanitizeTouch(previous.first || {});
    const first = Object.keys(previousFirst).length ? previousFirst : current;
    const state = {
      version: 1,
      first,
      current,
      pap_visitor_id: value.pap_visitor_id || '',
      pap_affiliate_id: value.pap_affiliate_id || '',
      updated_at: current.captured_at,
      expires_at: current.captured_at + ttlSeconds
    };
    const json = JSON.stringify(state);
    try { window.localStorage.setItem(storageKey, json); } catch (_) {}
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${encodeURIComponent(cookieName)}=${encodeURIComponent(json)}; Path=/; Max-Age=${ttlSeconds}; SameSite=Lax${secure}`;
    return value;
  };
  const firstLandingUrl = () => {
    const url = new URL(window.location.href);
    url.hash = '';
    return url.href;
  };
  const capture = (extra = {}) => {
    const stored = readStored();
    const query = new URLSearchParams(window.location.search);
    const papAffiliateId = papAffiliateIdFromLocation();
    const hasCampaignSignal = touchFields.slice(0, 7).some((key) => query.has(key) && cleanText(query.get(key), key));
    const next = hasCampaignSignal ? {
      pap_visitor_id: stored.pap_visitor_id,
      pap_affiliate_id: stored.pap_affiliate_id
    } : { ...stored };
    ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid'].forEach((key) => {
      if (query.has(key)) next[key] = query.get(key);
    });
    if (query.has('pap_visitor_id')) next.pap_visitor_id = query.get('pap_visitor_id');
    if (papAffiliateId) next.pap_affiliate_id = papAffiliateId;
    if (!next.pap_visitor_id) next.pap_visitor_id = cookieValue('PAPVisitorId') || cookieValue('pap_visitor_id');
    if (!next.pap_affiliate_id) next.pap_affiliate_id = cookieValue('pap_affiliate_id');
    if (!next.landing_url || hasCampaignSignal) next.landing_url = firstLandingUrl();
    next.language = cfg.language || document.documentElement.lang || next.language || '';
    return writeStored({ ...next, ...extra });
  };
  const uuid = () => {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
    const bytes = new Uint8Array(16);
    if (window.crypto && typeof window.crypto.getRandomValues === 'function') window.crypto.getRandomValues(bytes);
    else bytes.forEach((_, index) => { bytes[index] = Math.floor(Math.random() * 256); });
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
  };
  const checkoutPayload = (extra = {}) => {
    const current = sanitize({ ...capture(), ...extra });
    const stored = rawStored();
    return {
      version: 1,
      first: sanitizeTouch(stored.first || {}),
      current: sanitizeTouch(stored.current || {}),
      pap_visitor_id: current.pap_visitor_id || stored.pap_visitor_id || '',
      pap_affiliate_id: current.pap_affiliate_id || stored.pap_affiliate_id || '',
      updated_at: Number(stored.updated_at) || 0,
      expires_at: Number(stored.expires_at) || 0,
      ...current,
      language: cfg.language || current.language || '',
      submission_id: current.submission_id || uuid()
    };
  };

  let papResolve;
  let papFinished = false;
  const papReady = new Promise((resolve) => { papResolve = resolve; });
  const finishPap = () => {
    if (papFinished) return;
    papFinished = true;
    papResolve();
  };
  const papField = (id) => {
    let field = document.getElementById(id);
    if (!field) {
      field = document.createElement('input');
      field.type = 'hidden';
      field.id = id;
      field.name = id;
      field.setAttribute('aria-hidden', 'true');
      document.body.appendChild(field);
    }
    return field;
  };
  const harvestPap = () => {
    const visitor = papField('mediline_pap_visitor_id');
    const affiliate = papField('mediline_pap_affiliate_id');
    const tracker = window.PostAffTracker;
    try {
      if (tracker && typeof tracker.writeCookieToCustomField === 'function') tracker.writeCookieToCustomField(visitor.id);
      if (tracker && typeof tracker.writeAffiliateToCustomField === 'function') tracker.writeAffiliateToCustomField(affiliate.id);
    } catch (_) {}
    window.setTimeout(() => {
      const pap = {};
      const visitorId = visitor.value || cookieValue('PAPVisitorId');
      if (visitorId) pap.pap_visitor_id = visitorId;
      if (affiliate.value) pap.pap_affiliate_id = affiliate.value;
      capture(pap);
      finishPap();
    }, 150);
  };
  const initializePap = () => {
    capture();
    const tracker = window.PostAffTracker;
    if (!cfg.papEnabled || !tracker) { finishPap(); return; }
    if (!Array.isArray(tracker.executeOnResponseFinished)) tracker.executeOnResponseFinished = [];
    tracker.executeOnResponseFinished.push(harvestPap);
    try {
      if (typeof tracker.setAccountId === 'function') tracker.setAccountId(String(cfg.papAccountId || 'default1'));
      if (typeof tracker.track === 'function') tracker.track();
      else finishPap();
    } catch (_) { finishPap(); }
    window.setTimeout(() => { capture(); finishPap(); }, 900);
  };

  const originalFetch = window.fetch;
  const checkoutTarget = (() => {
    try { return new URL(String(cfg.checkoutUrl || cfg.checkoutPath || ''), window.location.href); } catch (_) { return null; }
  })();
  const normalizedPath = (path) => String(path || '').replace(/\/+$/, '') || '/';
  const isCheckoutRequest = (url, method) => {
    if (!checkoutTarget || method !== 'POST' || url.origin !== checkoutTarget.origin) return false;
    if (checkoutTarget.searchParams.has('rest_route')) {
      return url.searchParams.get('rest_route') === checkoutTarget.searchParams.get('rest_route');
    }
    return normalizedPath(url.pathname) === normalizedPath(checkoutTarget.pathname);
  };
  if (typeof originalFetch === 'function') {
    window.fetch = function medilineAttributionFetch(input, init) {
      let requestUrl;
      let method;
      try {
        requestUrl = new URL(typeof input === 'string' || input instanceof URL ? String(input) : input.url, window.location.href);
        method = String((init && init.method) || (input && input.method) || 'GET').toUpperCase();
      } catch (_) { return originalFetch.call(this, input, init); }
      if (!isCheckoutRequest(requestUrl, method) || !init || typeof init.body !== 'string') {
        return originalFetch.call(this, input, init);
      }

      const context = this;
      return papReady.then(() => {
        try {
          const body = JSON.parse(init.body);
          if (!body || typeof body !== 'object' || Array.isArray(body)) return originalFetch.call(context, input, init);
          const supplied = sanitize(body.attribution || {});
          body.attribution = checkoutPayload({ ...supplied, language: cfg.language || supplied.language || '', submission_id: supplied.submission_id || uuid() });
          return originalFetch.call(context, input, { ...init, body: JSON.stringify(body) });
        } catch (_) { return originalFetch.call(context, input, init); }
      });
    };
  }

  window.MedilineStoreAttribution = Object.freeze({
    capture,
    get: () => checkoutPayload({ submission_id: uuid() })
  });
  initializePap();
})();
