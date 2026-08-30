(function () {
	'use strict';

	var rawConfig = window.medilineIntegrationsAttribution || {};
	var ATTRIBUTION_KEYS = [
		'utm_source',
		'utm_medium',
		'utm_campaign',
		'utm_term',
		'utm_content',
		'gclid',
		'fbclid'
	];
	var FIELD_LIMITS = {
		utm_source: 100,
		utm_medium: 100,
		utm_campaign: 100,
		utm_term: 100,
		utm_content: 100,
		gclid: 160,
		fbclid: 160,
		language: 16,
		landing_url: 500
	};
	var config = {
		cookieName: boundedText(rawConfig.cookieName || 'mediline_attribution', 64).replace(/[^A-Za-z0-9_-]/g, ''),
		storageKey: boundedText(rawConfig.storageKey || 'mediline_attribution_v1', 80),
		ttlSeconds: clampNumber(rawConfig.ttlSeconds, 86400, 31536000, 90 * 86400),
		formSelector: boundedText(rawConfig.formSelector || 'form[data-mediline-crm-form]', 240),
		requiredMarker: boundedText(rawConfig.requiredMarker || 'data-mediline-crm-form', 80).toLowerCase().replace(/[^a-z0-9-]/g, ''),
		language: sanitizeLanguage(rawConfig.language || ''),
		submitWaitMs: clampNumber(rawConfig.submitWaitMs, 100, 900, 900),
		leadEndpoint: boundedText(rawConfig.leadEndpoint || '', 700),
		leadNonce: boundedText(rawConfig.leadNonce || '', 160),
		leadNonceHeader: boundedText(rawConfig.leadNonceHeader || 'X-Mediline-Lead-Nonce', 80).replace(/[^A-Za-z0-9-]/g, ''),
		pap: {
			enabled: Boolean(rawConfig.pap && rawConfig.pap.enabled),
			scriptUrl: boundedText(rawConfig.pap && rawConfig.pap.scriptUrl || '', 700),
			accountId: boundedText(rawConfig.pap && rawConfig.pap.accountId || '', 64).replace(/[^A-Za-z0-9_-]/g, ''),
			waitMs: clampNumber(rawConfig.pap && rawConfig.pap.waitMs, 50, 700, 450)
		}
	};
	var formIndex = 0;
	var papFinished = false;
	var resolvePapReady;
	var papReady = new Promise(function (resolve) {
		resolvePapReady = resolve;
	});
	var state = captureAttribution(loadState());

	function clampNumber(value, minimum, maximum, fallback) {
		var number = Number(value);
		if (!Number.isFinite(number)) {
			return fallback;
		}
		return Math.min(maximum, Math.max(minimum, Math.round(number)));
	}

	function boundedText(value, limit) {
		if (value === null || typeof value === 'undefined') {
			return '';
		}
		return String(value)
			.replace(/[\u0000-\u001F\u007F]/g, '')
			.trim()
			.slice(0, limit);
	}

	function sanitizeLanguage(value) {
		return boundedText(value, FIELD_LIMITS.language)
			.toLowerCase()
			.replace(/_/g, '-')
			.replace(/[^a-z0-9-]/g, '')
			.replace(/^-+|-+$/g, '');
	}

	function sanitizeAffiliateId(value) {
		return boundedText(value, 100).replace(/[^A-Za-z0-9_.@-]/g, '');
	}

	function strictLocationAffiliateId(value) {
		return typeof value === 'string' && /^[A-Za-z0-9_.@-]{1,100}$/.test(value) ? value : '';
	}

	function singleLocationAffiliateId(params, name) {
		var values = params.getAll(name);
		return values.length === 1 ? strictLocationAffiliateId(values[0]) : '';
	}

	function papAffiliateIdFromLocation() {
		try {
			var query = new URLSearchParams(window.location.search || '');
			var queryId = singleLocationAffiliateId(query, 'a_aid');
			if (!queryId) {
				queryId = singleLocationAffiliateId(query, 'pap_affiliate_id');
			}
			if (queryId) {
				return queryId;
			}
		} catch (error) {
			// Fall through to PAP's anchor-link format.
		}

		try {
			var hash = typeof window.location.hash === 'string' ? window.location.hash : '';
			if (hash.length > 2048 || hash.indexOf('#a_aid=') !== 0) {
				return '';
			}
			return singleLocationAffiliateId(new URLSearchParams(hash.slice(1)), 'a_aid');
		} catch (error) {
			return '';
		}
	}

	function normalizeVisitorId(value) {
		var normalized = boundedText(value, 160).replace(/[^A-Za-z0-9]/g, '');
		if (normalized.length > 32) {
			normalized = normalized.slice(-32);
		}
		return normalized.length === 32 ? normalized : '';
	}

	function sanitizeSubmissionId(value) {
		var clean = boundedText(value, 64).replace(/[^A-Za-z0-9_-]/g, '');
		return clean.length >= 16 ? clean : '';
	}

	function safeLandingUrl(value) {
		try {
			var source = new URL(value, window.location.href);
			if (source.protocol !== 'http:' && source.protocol !== 'https:') {
				return '';
			}
			var clean = new URL(source.origin + source.pathname);
			ATTRIBUTION_KEYS.forEach(function (key) {
				var candidate = boundedText(source.searchParams.get(key) || '', FIELD_LIMITS[key]);
				if (candidate) {
					clean.searchParams.set(key, candidate);
				}
			});
			return boundedText(clean.toString(), FIELD_LIMITS.landing_url);
		} catch (error) {
			return '';
		}
	}

	function sanitizeTouch(candidate) {
		var touch = {};
		candidate = candidate && typeof candidate === 'object' ? candidate : {};
		ATTRIBUTION_KEYS.forEach(function (key) {
			var value = boundedText(candidate[key] || '', FIELD_LIMITS[key]);
			if (value) {
				touch[key] = value;
			}
		});
		var language = sanitizeLanguage(candidate.language || '');
		var landingUrl = safeLandingUrl(candidate.landing_url || '');
		if (language) {
			touch.language = language;
		}
		if (landingUrl) {
			touch.landing_url = landingUrl;
		}
		var capturedAt = Number(candidate.captured_at);
		if (Number.isFinite(capturedAt) && capturedAt > 0) {
			touch.captured_at = Math.floor(capturedAt);
		}
		return touch;
	}

	function sanitizeState(candidate) {
		candidate = candidate && typeof candidate === 'object' ? candidate : {};
		var expiresAt = Number(candidate.expires_at) || 0;
		if (expiresAt && expiresAt < nowSeconds()) {
			return {};
		}
		return {
			version: 1,
			first: sanitizeTouch(candidate.first),
			current: sanitizeTouch(candidate.current),
			pap_visitor_id: normalizeVisitorId(candidate.pap_visitor_id || ''),
			pap_affiliate_id: sanitizeAffiliateId(candidate.pap_affiliate_id || ''),
			updated_at: Math.max(0, Math.floor(Number(candidate.updated_at) || 0)),
			expires_at: Math.max(0, Math.floor(expiresAt))
		};
	}

	function nowSeconds() {
		return Math.floor(Date.now() / 1000);
	}

	function parseStored(value) {
		if (!value || value.length > 8192) {
			return {};
		}
		try {
			return sanitizeState(JSON.parse(value));
		} catch (error) {
			return {};
		}
	}

	function readCookie() {
		if (!config.cookieName) {
			return {};
		}
		var prefix = config.cookieName + '=';
		var parts = document.cookie ? document.cookie.split(';') : [];
		for (var index = 0; index < parts.length; index += 1) {
			var part = parts[index].trim();
			if (part.indexOf(prefix) === 0) {
				try {
					return parseStored(decodeURIComponent(part.slice(prefix.length)));
				} catch (error) {
					return {};
				}
			}
		}
		return {};
	}

	function readLocalStorage() {
		try {
			return parseStored(window.localStorage.getItem(config.storageKey) || '');
		} catch (error) {
			return {};
		}
	}

	function hasValues(candidate) {
		return candidate && typeof candidate === 'object' && Object.keys(candidate).length > 0;
	}

	function loadState() {
		var cookieState = readCookie();
		var localState = readLocalStorage();
		var newest = (localState.updated_at || 0) > (cookieState.updated_at || 0) ? localState : cookieState;
		var older = newest === localState ? cookieState : localState;
		newest = sanitizeState(newest);
		if (!hasValues(newest.first) && hasValues(older.first)) {
			newest.first = sanitizeTouch(older.first);
		}
		if (!hasValues(newest.current) && hasValues(older.current)) {
			newest.current = sanitizeTouch(older.current);
		}
		if (!newest.pap_visitor_id) {
			newest.pap_visitor_id = normalizeVisitorId(older.pap_visitor_id || '');
		}
		if (!newest.pap_affiliate_id) {
			newest.pap_affiliate_id = sanitizeAffiliateId(older.pap_affiliate_id || '');
		}
		return newest;
	}

	function currentLanguage() {
		var configured = sanitizeLanguage(config.language);
		if (configured) {
			return configured;
		}
		var htmlLanguage = document.documentElement ? sanitizeLanguage(document.documentElement.getAttribute('lang') || '') : '';
		return htmlLanguage || 'en';
	}

	function captureAttribution(previous) {
		var next = sanitizeState(previous);
		var timestamp = nowSeconds();
		var papAffiliateId = papAffiliateIdFromLocation();
		var incoming = {
			language: currentLanguage(),
			landing_url: safeLandingUrl(window.location.href),
			captured_at: timestamp
		};
		var hasCampaignSignal = false;
		try {
			var params = new URLSearchParams(window.location.search);
			ATTRIBUTION_KEYS.forEach(function (key) {
				var value = boundedText(params.get(key) || '', FIELD_LIMITS[key]);
				if (value) {
					incoming[key] = value;
					hasCampaignSignal = true;
				}
			});
		} catch (error) {
			// Keep a safe landing path even in browsers with an invalid URL implementation.
		}
		if (papAffiliateId) {
			next.pap_affiliate_id = papAffiliateId;
		}

		if (!hasValues(next.first)) {
			next.first = sanitizeTouch(incoming);
		}
		if (!hasValues(next.current) || hasCampaignSignal) {
			next.current = sanitizeTouch(incoming);
		} else {
			next.current.language = incoming.language;
		}
		next.updated_at = timestamp;
		next.expires_at = timestamp + config.ttlSeconds;
		persistState(next);
		return next;
	}

	function compactCookieState(candidate) {
		var compact = sanitizeState(candidate);
		var encoded = encodeURIComponent(JSON.stringify(compact));
		if (encoded.length <= 3500) {
			return encoded;
		}
		['first', 'current'].forEach(function (touchName) {
			if (compact[touchName] && compact[touchName].landing_url) {
				compact[touchName].landing_url = compact[touchName].landing_url.slice(0, 240);
			}
		});
		encoded = encodeURIComponent(JSON.stringify(compact));
		if (encoded.length <= 3500) {
			return encoded;
		}
		delete compact.first.utm_term;
		delete compact.first.utm_content;
		encoded = encodeURIComponent(JSON.stringify(compact));
		if (encoded.length <= 3500) {
			return encoded;
		}
		compact.first = {
			language: compact.first.language || '',
			landing_url: boundedText(compact.first.landing_url || '', 180),
			captured_at: compact.first.captured_at || 0
		};
		encoded = encodeURIComponent(JSON.stringify(compact));
		if (encoded.length <= 3500) {
			return encoded;
		}
		ATTRIBUTION_KEYS.forEach(function (key) {
			if (compact.current[key]) {
				compact.current[key] = compact.current[key].slice(0, 40);
			}
		});
		compact.current.landing_url = boundedText(compact.current.landing_url || '', 180);
		encoded = encodeURIComponent(JSON.stringify(compact));
		if (encoded.length <= 3500) {
			return encoded;
		}
		delete compact.current.utm_term;
		delete compact.current.utm_content;
		delete compact.first.landing_url;
		return encodeURIComponent(JSON.stringify(compact));
	}

	function persistState(candidate) {
		state = sanitizeState(candidate);
		var serialized = JSON.stringify(state);
		try {
			window.localStorage.setItem(config.storageKey, serialized);
		} catch (error) {
			// Cookie persistence remains available when storage is disabled.
		}
		if (!config.cookieName) {
			return;
		}
		var cookie = config.cookieName + '=' + compactCookieState(state);
		cookie += '; Max-Age=' + config.ttlSeconds;
		cookie += '; Expires=' + new Date(Date.now() + config.ttlSeconds * 1000).toUTCString();
		cookie += '; Path=/; SameSite=Lax';
		if (window.location.protocol === 'https:') {
			cookie += '; Secure';
		}
		document.cookie = cookie;
	}

	function createSubmissionId() {
		if (window.crypto && typeof window.crypto.randomUUID === 'function') {
			return window.crypto.randomUUID();
		}
		if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
			var bytes = new Uint8Array(16);
			window.crypto.getRandomValues(bytes);
			bytes[6] = (bytes[6] & 15) | 64;
			bytes[8] = (bytes[8] & 63) | 128;
			var hex = Array.prototype.map.call(bytes, function (byte) {
				return byte.toString(16).padStart(2, '0');
			}).join('');
			return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' + hex.slice(16, 20) + '-' + hex.slice(20);
		}
		return String(Date.now()) + '-' + Math.random().toString(36).slice(2, 15) + Math.random().toString(36).slice(2, 10);
	}

	function isMarkedForm(form) {
		if (!form || form.nodeName !== 'FORM' || !config.requiredMarker || !form.hasAttribute(config.requiredMarker)) {
			return false;
		}
		try {
			return form.matches(config.formSelector);
		} catch (error) {
			return form.matches('form[' + config.requiredMarker + ']');
		}
	}

	function markedForms() {
		var nodes;
		try {
			nodes = document.querySelectorAll(config.formSelector);
		} catch (error) {
			nodes = document.querySelectorAll('form[' + config.requiredMarker + ']');
		}
		return Array.prototype.filter.call(nodes, isMarkedForm);
	}

	function formType(form) {
		return boundedText(form.getAttribute('data-mediline-form-type') || form.getAttribute(config.requiredMarker) || 'lead', 64)
			.toLowerCase()
			.replace(/[^a-z0-9_-]/g, '') || 'lead';
	}

	function ownedHidden(form, name) {
		var inputs = form.querySelectorAll('input[type="hidden"]');
		for (var index = 0; index < inputs.length; index += 1) {
			if (inputs[index].name === name) {
				return inputs[index];
			}
		}
		return null;
	}

	function ensureHidden(form, name, value) {
		var input = ownedHidden(form, name);
		if (!input) {
			input = document.createElement('input');
			input.type = 'hidden';
			input.name = name;
			input.setAttribute('data-mediline-attribution-field', name);
			form.appendChild(input);
		}
		input.value = boundedText(value || '', name === 'landing_url' ? FIELD_LIMITS.landing_url : 200);
		return input;
	}

	function uniqueFieldId(base, index) {
		var id = index === 1 ? base : base + '_' + index;
		while (document.getElementById(id)) {
			formIndex += 1;
			id = base + '_' + formIndex;
		}
		return id;
	}

	function enhanceForm(form) {
		if (!isMarkedForm(form)) {
			return;
		}
		var index = Number(form.getAttribute('data-mediline-attribution-index'));
		if (!Number.isFinite(index) || index < 1) {
			formIndex += 1;
			index = formIndex;
			form.setAttribute('data-mediline-attribution-index', String(index));
		}
		var current = state.current || {};
		ATTRIBUTION_KEYS.forEach(function (key) {
			ensureHidden(form, key, current[key] || '');
		});
		ensureHidden(form, 'language', current.language || currentLanguage());
		ensureHidden(form, 'landing_url', current.landing_url || safeLandingUrl(window.location.href));
		ensureHidden(form, 'form_type', formType(form));
		var submission = ensureHidden(form, 'submission_id', '');
		if (!sanitizeSubmissionId(submission.value)) {
			submission.value = createSubmissionId();
		}
		var visitor = ensureHidden(form, 'pap_visitor_id', state.pap_visitor_id || '');
		var affiliate = ensureHidden(form, 'pap_affiliate_id', state.pap_affiliate_id || '');
		if (!visitor.id) {
			visitor.id = uniqueFieldId('pap_visitor_id', index);
		}
		if (!affiliate.id) {
			affiliate.id = uniqueFieldId('pap_affiliate_id', index);
		}
	}

	function enhanceAllForms() {
		markedForms().forEach(enhanceForm);
	}

	function syncPapFields() {
		var visitorFromForm = '';
		var affiliateFromForm = '';
		markedForms().forEach(function (form) {
			var visitor = ownedHidden(form, 'pap_visitor_id');
			var affiliate = ownedHidden(form, 'pap_affiliate_id');
			visitorFromForm = normalizeVisitorId(visitor && visitor.value || '') || visitorFromForm;
			affiliateFromForm = sanitizeAffiliateId(affiliate && affiliate.value || '') || affiliateFromForm;
		});
		var visitorId = visitorFromForm || normalizeVisitorId(state.pap_visitor_id || '');
		var affiliateId = affiliateFromForm || sanitizeAffiliateId(state.pap_affiliate_id || '');
		markedForms().forEach(function (form) {
			ensureHidden(form, 'pap_visitor_id', visitorId);
			ensureHidden(form, 'pap_affiliate_id', affiliateId);
		});
		if (visitorId !== state.pap_visitor_id || affiliateId !== state.pap_affiliate_id) {
			state.pap_visitor_id = visitorId;
			state.pap_affiliate_id = affiliateId;
			state.updated_at = nowSeconds();
			state.expires_at = nowSeconds() + config.ttlSeconds;
			persistState(state);
		}
	}

	function writePapToMarkedFields(tracker) {
		enhanceAllForms();
		markedForms().forEach(function (form) {
			var visitor = ownedHidden(form, 'pap_visitor_id');
			var affiliate = ownedHidden(form, 'pap_affiliate_id');
			try {
				if (visitor && visitor.id && typeof tracker.writeCookieToCustomField === 'function') {
					tracker.writeCookieToCustomField(visitor.id);
				}
				if (affiliate && affiliate.id && typeof tracker.writeAffiliateToCustomField === 'function') {
					tracker.writeAffiliateToCustomField(affiliate.id);
				}
			} catch (error) {
				// Attribution still falls back to the persisted first-party values.
			}
		});
		window.setTimeout(syncPapFields, 0);
	}

	function finishPap(tracker) {
		if (papFinished) {
			return;
		}
		papFinished = true;
		if (tracker) {
			writePapToMarkedFields(tracker);
		}
		window.setTimeout(function () {
			syncPapFields();
			resolvePapReady(true);
		}, 0);
	}

	function initializePap() {
		if (!config.pap.enabled || !config.pap.accountId) {
			finishPap(null);
			return;
		}
		var tracker = window.PostAffTracker;
		if (!tracker || typeof tracker.setAccountId !== 'function' || typeof tracker.track !== 'function') {
			finishPap(null);
			return;
		}
		try {
			tracker.setAccountId(config.pap.accountId);
			if (tracker.executeOnResponseFinished && typeof tracker.executeOnResponseFinished.push === 'function') {
				tracker.executeOnResponseFinished.push(function () {
					finishPap(tracker);
				});
			}
			tracker.track();
		} catch (error) {
			finishPap(null);
			return;
		}
		window.setTimeout(function () {
			if (!papFinished) {
				finishPap(tracker);
			}
		}, config.pap.waitMs);
	}

	function delay(milliseconds) {
		return new Promise(function (resolve) {
			window.setTimeout(resolve, Math.max(0, milliseconds));
		});
	}

	function allowedControlValue(form, names) {
		for (var index = 0; index < names.length; index += 1) {
			var control = form.elements.namedItem(names[index]);
			if (!control) {
				continue;
			}
			if (typeof control.length === 'number' && !control.nodeName) {
				control = Array.prototype.find.call(control, function (item) {
					return item && item.checked !== false;
				});
			}
			if (!control || String(control.type || '').toLowerCase() === 'password') {
				continue;
			}
			return boundedText(control.value || '', 500);
		}
		return '';
	}

	function attributionPayload(form) {
		var current = sanitizeTouch(state.current);
		var first = sanitizeTouch(state.first);
		var submissionId = sanitizeSubmissionId(ownedHidden(form, 'submission_id') && ownedHidden(form, 'submission_id').value || '');
		var visitorId = normalizeVisitorId(ownedHidden(form, 'pap_visitor_id') && ownedHidden(form, 'pap_visitor_id').value || state.pap_visitor_id || '');
		var affiliateId = sanitizeAffiliateId(ownedHidden(form, 'pap_affiliate_id') && ownedHidden(form, 'pap_affiliate_id').value || state.pap_affiliate_id || '');
		var language = sanitizeLanguage(ownedHidden(form, 'language') && ownedHidden(form, 'language').value || current.language || currentLanguage());
		var payload = {
			form_type: formType(form),
			company_website: allowedControlValue(form, ['company_website']),
			firstname: allowedControlValue(form, ['firstname']),
			lastname: allowedControlValue(form, ['lastname']),
			username: allowedControlValue(form, ['username', 'email']),
			email: allowedControlValue(form, ['email', 'username']),
			data26: allowedControlValue(form, ['data26', 'messenger']),
			messenger: allowedControlValue(form, ['messenger', 'data26']),
			pap_visitor_id: visitorId,
			pap_affiliate_id: affiliateId,
			language: language,
			submission_id: submissionId,
			attribution: {
				version: 1,
				first: first,
				current: current,
				pap_visitor_id: visitorId,
				pap_affiliate_id: affiliateId,
				language: language,
				submission_id: submissionId,
				form_type: formType(form)
			}
		};
		ATTRIBUTION_KEYS.forEach(function (key) {
			payload[key] = boundedText(current[key] || '', FIELD_LIMITS[key]);
		});
		payload.landing_url = safeLandingUrl(current.landing_url || window.location.href);
		return payload;
	}

	function sameOriginEndpoint() {
		if (!config.leadEndpoint) {
			return '';
		}
		try {
			var endpoint = new URL(config.leadEndpoint, window.location.href);
			return endpoint.origin === window.location.origin ? endpoint.toString() : '';
		} catch (error) {
			return '';
		}
	}

	function mirrorLead(form, timeoutMs) {
		var endpoint = sameOriginEndpoint();
		if (!endpoint || typeof window.fetch !== 'function' || timeoutMs < 40) {
			return Promise.resolve();
		}
		var headers = {'Content-Type': 'application/json', 'Accept': 'application/json'};
		if (config.leadNonce && config.leadNonceHeader) {
			headers[config.leadNonceHeader] = config.leadNonce;
		}
		var payload = attributionPayload(form);
		payload.lead_nonce = config.leadNonce;
		var request = window.fetch(endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: headers,
			body: JSON.stringify(payload),
			keepalive: true
		}).catch(function () {
			// CRM delivery is retried server-side when accepted; it must never block PAP signup.
		});
		return Promise.race([request, delay(timeoutMs)]);
	}

	function resumeSubmission(form, submitter) {
		var honeypot = form.elements.namedItem('company_website');
		if (honeypot && honeypot.nodeName) {
			honeypot.disabled = true;
		}
		form.removeAttribute('data-mediline-attribution-preparing');
		form.setAttribute('data-mediline-attribution-submitting', '1');
		try {
			if (typeof form.requestSubmit === 'function') {
				if (submitter && submitter.form === form) {
					form.requestSubmit(submitter);
				} else {
					form.requestSubmit();
				}
			} else {
				HTMLFormElement.prototype.submit.call(form);
			}
		} catch (error) {
			HTMLFormElement.prototype.submit.call(form);
		}
		window.setTimeout(function () {
			form.removeAttribute('data-mediline-attribution-submitting');
		}, 0);
	}

	function onSubmit(event) {
		var form = event.target;
		if (!isMarkedForm(form) || form.hasAttribute('data-mediline-attribution-submitting')) {
			return;
		}
		if (form.hasAttribute('data-mediline-attribution-preparing')) {
			event.preventDefault();
			return;
		}
		event.preventDefault();
		form.setAttribute('data-mediline-attribution-preparing', '1');
		var submitter = event.submitter || null;
		var deadline = Date.now() + config.submitWaitMs;
		var papWait = Math.min(config.pap.waitMs, Math.max(0, deadline - Date.now()));
		Promise.race([papReady, delay(papWait)]).then(function () {
			syncPapFields();
			enhanceForm(form);
			return mirrorLead(form, Math.max(0, deadline - Date.now()));
		}).catch(function () {
			// Always continue the original PAP submission.
		}).then(function () {
			resumeSubmission(form, submitter);
		});
	}

	function observeForms() {
		if (typeof window.MutationObserver !== 'function' || !document.body) {
			return;
		}
		var observer = new window.MutationObserver(function (mutations) {
			mutations.forEach(function (mutation) {
				Array.prototype.forEach.call(mutation.addedNodes || [], function (node) {
					if (!node || node.nodeType !== 1) {
						return;
					}
					if (isMarkedForm(node)) {
						enhanceForm(node);
					}
					if (typeof node.querySelectorAll === 'function') {
						try {
							Array.prototype.forEach.call(node.querySelectorAll(config.formSelector), enhanceForm);
						} catch (error) {
							// Invalid configured selectors fall back at the document level.
						}
					}
				});
			});
		});
		observer.observe(document.body, {childList: true, subtree: true});
	}

	function start() {
		enhanceAllForms();
		document.addEventListener('submit', onSubmit, true);
		observeForms();
		initializePap();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start, {once: true});
	} else {
		start();
	}
}());
