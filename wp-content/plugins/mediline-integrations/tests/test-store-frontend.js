'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

let assertions = 0;
function assert(condition, message) {
	assertions += 1;
	if (!condition) throw new Error(message);
}

const sourcePath = path.join(__dirname, '..', '..', 'mediline-store-core', 'assets', 'js', 'attribution.js');
const source = fs.readFileSync(sourcePath, 'utf8');

function executeAttribution(url, initialState) {
	const location = new URL(url);
	const storage = initialState ? {mediline_attribution_v1: JSON.stringify(initialState)} : {};
	const fields = [];
	let browserCookie = '';
	const document = {
		body: {
			appendChild(field) {
				fields.push(field);
				return field;
			}
		},
		documentElement: {lang: 'en'},
		getElementById: (id) => fields.find((field) => field.id === id) || null,
		createElement: () => ({setAttribute() {}})
	};
	Object.defineProperty(document, 'cookie', {
		get: () => browserCookie,
		set: (value) => { browserCookie = String(value); }
	});

	const windowObject = {
		MedilineStoreAttributionConfig: {
			cookieName: 'mediline_attribution',
			storageKey: 'mediline_attribution_v1',
			ttlSeconds: 90 * 86400,
			language: 'en',
			checkoutUrl: 'https://store.test/wp-json/mediline-store/v1/checkout',
			papEnabled: false,
			papAccountId: 'default1'
		},
		location,
		localStorage: {
			getItem: (key) => storage[key] || null,
			setItem: (key, value) => { storage[key] = String(value); }
		},
		crypto: {randomUUID: () => '12345678-1234-4123-8123-123456789abc'},
		fetch: () => Promise.resolve({ok: true}),
		setTimeout,
		clearTimeout
	};
	const context = {
		window: windowObject,
		document,
		URL,
		URLSearchParams,
		Promise,
		Date,
		Math,
		Number,
		String,
		Object,
		Array,
		JSON,
		Uint8Array,
		setTimeout,
		clearTimeout,
		console
	};
	vm.createContext(context);
	vm.runInContext(source, context, {filename: sourcePath});
	return {
		payload: windowObject.MedilineStoreAttribution.get(),
		stored: JSON.parse(storage.mediline_attribution_v1 || '{}')
	};
}

try {
	const anchor = executeAttribution('https://store.test/product?utm_source=google&utm_campaign=launch#a_aid=anchor.partner&a_bid=banner-7');
	assert(anchor.payload.pap_affiliate_id === 'anchor.partner', 'Store Core did not capture a_aid from the PAP anchor.');
	assert(anchor.payload.utm_source === 'google' && anchor.payload.utm_campaign === 'launch', 'Query UTM values were lost when PAP used an anchor link.');
	assert(anchor.payload.landing_url.includes('utm_source=google'), 'Safe landing URL lost an allowed UTM parameter.');
	assert(!anchor.payload.landing_url.includes('a_aid='), 'PAP anchor data leaked into the safe landing URL.');
	assert(anchor.stored.current && anchor.stored.current.landing_url, 'An anchor affiliate click did not create a current attribution touch.');

	const queryWins = executeAttribution('https://store.test/?a_aid=query-owner#a_aid=anchor-owner&a_bid=banner-7');
	assert(queryWins.payload.pap_affiliate_id === 'query-owner', 'Explicit query a_aid must take precedence over an anchor a_aid.');

	const legacyQuery = executeAttribution('https://store.test/?pap_affiliate_id=legacy-owner#a_aid=anchor-owner');
	assert(legacyQuery.payload.pap_affiliate_id === 'legacy-owner', 'Existing pap_affiliate_id query links lost precedence.');

	const encodedAnchor = executeAttribution('https://store.test/#a_aid=partner%2Dencoded&a_bid=banner-7');
	assert(encodedAnchor.payload.pap_affiliate_id === 'partner-encoded', 'Encoded PAP anchor affiliate ID was not normalized.');

	const emptyQueryFallback = executeAttribution('https://store.test/?a_aid=#a_aid=anchor-owner&a_bid=banner-7');
	assert(emptyQueryFallback.payload.pap_affiliate_id === 'anchor-owner', 'An empty query a_aid prevented a valid anchor fallback.');

	const invalidQueryFallback = executeAttribution('https://store.test/?a_aid=bad%2Fowner#a_aid=anchor-owner&a_bid=banner-7');
	assert(invalidQueryFallback.payload.pap_affiliate_id === 'anchor-owner', 'An invalid query a_aid prevented a valid anchor fallback.');

	const ordinaryAnchor = executeAttribution('https://store.test/#faq');
	assert(ordinaryAnchor.payload.pap_affiliate_id === '', 'A normal page anchor was mistaken for a PAP affiliate link.');

	const spaAnchor = executeAttribution('https://store.test/#tab=details&a_aid=not-a-pap-anchor');
	assert(spaAnchor.payload.pap_affiliate_id === '', 'A non-canonical SPA fragment was mistaken for a PAP affiliate link.');

	const shortAnchor = executeAttribution('https://store.test/#partner&a_bid=banner-7');
	assert(shortAnchor.payload.pap_affiliate_id === '', 'A disabled PAP short-anchor format was accepted.');

	const duplicateAnchor = executeAttribution('https://store.test/#a_aid=first&a_aid=second');
	assert(duplicateAnchor.payload.pap_affiliate_id === '', 'Conflicting duplicate anchor affiliate IDs were accepted.');

	const doubleEncoded = executeAttribution('https://store.test/#a_aid=partner%252D42');
	assert(doubleEncoded.payload.pap_affiliate_id === '', 'A double-encoded affiliate ID was transformed into another ID.');

	const timestamp = Math.floor(Date.now() / 1000);
	const priorState = {
		version: 1,
		first: {utm_source: 'original', landing_url: 'https://store.test/original?utm_source=original', captured_at: timestamp - 60},
		current: {utm_campaign: 'kept-campaign', landing_url: 'https://store.test/previous?utm_campaign=kept-campaign', captured_at: timestamp - 30},
		pap_affiliate_id: 'old-owner',
		updated_at: timestamp - 30,
		expires_at: timestamp + 86400
	};
	const anchorOverStored = executeAttribution('https://store.test/#a_aid=new-owner&a_bid=banner-7', priorState);
	assert(anchorOverStored.payload.pap_affiliate_id === 'new-owner', 'Anchor affiliate ID did not replace the stored affiliate ID.');
	assert(anchorOverStored.payload.current.utm_campaign === 'kept-campaign', 'Anchor-only traffic unexpectedly erased current UTM attribution.');
	assert(anchorOverStored.payload.current.landing_url.includes('/previous'), 'Anchor-only traffic unexpectedly replaced the current landing URL.');

	console.log('[PASS] Store Core supports PAP query and anchor links with deterministic precedence');
	console.log(`\nAssertions: ${assertions}; failures: 0`);
} catch (error) {
	console.error('[FAIL] Store Core frontend attribution regression');
	console.error(`       ${error.message}`);
	console.error(`\nAssertions: ${assertions}; failures: 1`);
	process.exitCode = 1;
}
