'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

let assertions = 0;
function assert(condition, message) {
	assertions += 1;
	if (!condition) {
		throw new Error(message);
	}
}

class FakeInput {
	constructor(name, type, value) {
		this.nodeName = 'INPUT';
		this.name = name || '';
		this.type = type || 'text';
		this.value = value || '';
		this.id = '';
		this.form = null;
		this.checked = true;
		this.attributes = {};
	}
	setAttribute(name, value) {
		this.attributes[name] = String(value);
	}
}

class FakeForm {
	constructor() {
		this.nodeName = 'FORM';
		this.attributes = {
			'data-mediline-crm-form': 'partner_application',
			'data-mediline-form-type': 'partner_application'
		};
		this.controls = [
			new FakeInput('firstname', 'text', 'Alice'),
			new FakeInput('lastname', 'text', 'Partner'),
			new FakeInput('username', 'email', 'alice@example.test'),
			new FakeInput('data26', 'text', '@alice'),
			new FakeInput('company_website', 'text', ''),
			new FakeInput('password', 'password', 'pap-login-secret'),
			new FakeInput('admin_password', 'password', 'generated-admin-secret'),
			new FakeInput('credit_card', 'text', '4111111111111111')
		];
		this.controls.forEach((control) => { control.form = this; });
		this.requestSubmitCount = 0;
		this.elements = {
			namedItem: (name) => this.controls.find((control) => control.name === name) || null
		};
	}
	hasAttribute(name) { return Object.prototype.hasOwnProperty.call(this.attributes, name); }
	getAttribute(name) { return this.attributes[name] || ''; }
	setAttribute(name, value) { this.attributes[name] = String(value); }
	removeAttribute(name) { delete this.attributes[name]; }
	matches(selector) { return selector === 'form[data-mediline-crm-form]' || selector === 'form[data-mediline-crm-form="partner_application"]'; }
	querySelectorAll(selector) { return selector === 'input[type="hidden"]' ? this.controls.filter((control) => control.type === 'hidden') : []; }
	appendChild(control) { control.form = this; this.controls.push(control); return control; }
	requestSubmit() { this.requestSubmitCount += 1; }
}

const form = new FakeForm();
const listeners = {};
let cookieValue = '';
const localStorageValues = {};
const fetchCalls = [];
let papCallbacksAtTrack = 0;
let papAccountId = '';
const location = new URL('https://mediline.test/apply?utm_source=google&utm_campaign=launch&password=url-secret#private');

const document = {
	readyState: 'loading',
	body: {},
	documentElement: { getAttribute: (name) => name === 'lang' ? 'uk-UA' : '' },
	querySelectorAll: (selector) => form.matches(selector) ? [form] : [],
	createElement: (tag) => tag.toLowerCase() === 'input' ? new FakeInput('', 'text', '') : {},
	getElementById: (id) => form.controls.find((control) => control.id === id) || null,
	addEventListener: (name, callback) => { listeners[name] = callback; }
};
Object.defineProperty(document, 'cookie', {
	get: () => cookieValue,
	set: (value) => { cookieValue = String(value); }
});

function HTMLFormElement() {}
HTMLFormElement.prototype.submit = function submit() {
	this.requestSubmitCount += 1;
};

const windowObject = {
	medilineIntegrationsAttribution: {
		cookieName: 'mediline_attribution',
		storageKey: 'mediline_attribution_v1',
		ttlSeconds: 86400,
		formSelector: 'form[data-mediline-crm-form]',
		requiredMarker: 'data-mediline-crm-form',
		language: 'uk',
		submitWaitMs: 100,
		leadEndpoint: 'https://mediline.test/wp-json/mediline-integrations/v1/lead',
		leadNonce: 'test-nonce',
		leadNonceHeader: 'X-Mediline-Lead-Nonce',
		pap: { enabled: true, accountId: 'default1', waitMs: 50 }
	},
	PostAffTracker: {
		executeOnResponseFinished: [],
		setAccountId: (value) => { papAccountId = String(value); },
		track() {
			papCallbacksAtTrack = this.executeOnResponseFinished.length;
			this.executeOnResponseFinished.slice().forEach((callback) => callback());
		},
		writeCookieToCustomField: (id) => {
			const field = document.getElementById(id);
			if (field) field.value = '0123456789abcdef0123456789abcdef';
		},
		writeAffiliateToCustomField: (id) => {
			const field = document.getElementById(id);
			if (field) field.value = 'partner-42';
		}
	},
	location,
	localStorage: {
		getItem: (key) => localStorageValues[key] || null,
		setItem: (key, value) => { localStorageValues[key] = String(value); }
	},
	crypto: { randomUUID: () => '12345678-1234-4123-8123-123456789abc' },
	setTimeout,
	clearTimeout,
	fetch: (url, options) => {
		fetchCalls.push({url, options});
		return Promise.resolve({ok: true, status: 202});
	}
};

const context = {
	window: windowObject,
	document,
	HTMLFormElement,
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

(async () => {
	const sourcePath = path.join(__dirname, '..', 'assets', 'js', 'attribution.js');
	vm.runInContext(fs.readFileSync(sourcePath, 'utf8'), context, {filename: sourcePath});
	assert(typeof listeners.DOMContentLoaded === 'function', 'Frontend did not register DOMContentLoaded.');
	listeners.DOMContentLoaded();
	assert(typeof listeners.submit === 'function', 'Frontend did not register the submit guard.');
	assert(papAccountId === 'default1', 'PAP account was not configured.');
	assert(papCallbacksAtTrack === 1, 'PAP completion callback must be registered before track().');

	let prevented = false;
	listeners.submit({
		target: form,
		submitter: null,
		preventDefault: () => { prevented = true; }
	});
	await new Promise((resolve) => setTimeout(resolve, 40));

	assert(prevented, 'Marked form must pause briefly for attribution sync.');
	assert(fetchCalls.length === 1, 'Marked form must mirror exactly one allowlisted lead request.');
	assert(form.requestSubmitCount === 1, 'Original form submission must resume after mirroring.');
	const request = fetchCalls[0];
	const payload = JSON.parse(request.options.body);
	const serialized = JSON.stringify(payload);

	assert(request.url.startsWith('https://mediline.test/'), 'Lead mirror must stay same-origin.');
	assert(payload.form_type === 'partner_application', 'Unexpected frontend form type.');
	assert(payload.firstname === 'Alice' && payload.lastname === 'Partner', 'Lead name was not copied.');
	assert(payload.email === 'alice@example.test', 'Lead email was not copied.');
	assert(payload.utm_source === 'google' && payload.utm_campaign === 'launch', 'UTM attribution was not captured.');
	assert(payload.landing_url.includes('utm_source=google'), 'Safe landing URL lost UTM data.');
	assert(!payload.landing_url.includes('password='), 'Safe landing URL leaked a non-attribution query field.');
	assert(!Object.prototype.hasOwnProperty.call(payload, 'password'), 'Payload must not contain password.');
	assert(!Object.prototype.hasOwnProperty.call(payload, 'admin_password'), 'Payload must not contain generated admin password.');
	assert(!Object.prototype.hasOwnProperty.call(payload, 'credit_card'), 'Payload must not contain arbitrary controls.');
	assert(payload.company_website === '', 'The anti-bot honeypot must be mirrored without collecting arbitrary fields.');
	assert(!serialized.includes('pap-login-secret'), 'Password value leaked into lead JSON.');
	assert(!serialized.includes('generated-admin-secret'), 'Generated admin password leaked into lead JSON.');
	assert(!serialized.includes('4111111111111111'), 'Arbitrary credit-card value leaked into lead JSON.');
	assert(form.controls.some((control) => control.name === 'pap_visitor_id' && control.type === 'hidden'), 'PAP visitor hidden input was not attached.');
	assert(form.controls.some((control) => control.name === 'pap_affiliate_id' && control.type === 'hidden'), 'PAP affiliate hidden input was not attached.');
	assert(payload.pap_visitor_id === '0123456789abcdef0123456789abcdef', 'PAP visitor ID was not harvested before the lead mirror.');

	console.log('[PASS] frontend marked-form allowlist, UTM normalization, hidden PAP fields, and password exclusion');
	console.log(`\nAssertions: ${assertions}; failures: 0`);
})().catch((error) => {
	console.error('[FAIL] frontend attribution regression');
	console.error(`       ${error.message}`);
	console.error(`\nAssertions: ${assertions}; failures: 1`);
	process.exitCode = 1;
});
