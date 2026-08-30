(function () {
	'use strict';

	var root = document.querySelector('[data-pap-builder-bridge]');
	if (!root) return;

	var title = root.querySelector('[data-bridge-title]');
	var message = root.querySelector('[data-bridge-message]');
	var progress = root.querySelector('[data-bridge-progress]');
	var retry = root.querySelector('[data-bridge-retry]');
	var credentials = null;
	var errorMessages = {
		pap_bridge_request_invalid: 'PAP did not pass a valid affiliate session. Check the Store Builder URL-page template.',
		pap_session_invalid: 'PAP passed an invalid partner-panel session. Reopen Store Builder from the PAP menu.',
		pap_session_rejected: 'Your PAP session is expired or no longer valid. Sign in to the partner panel again.',
		pap_v3_not_configured: 'PAP API v3 identity verification is not configured in WordPress.',
		mediline_pap_v3_configuration: 'The PAP API v3 URL or Bearer key is missing.',
		mediline_pap_v3_network: 'WordPress could not reach PAP API v3. Try again in a moment.',
		mediline_pap_v3_http_400: 'PAP rejected the affiliate lookup. Reopen Store Builder from the PAP menu.',
		mediline_pap_v3_http_401: 'PAP rejected the API v3 key. Create or re-enter an Affiliates Read key in WordPress.',
		mediline_pap_v3_http_403: 'The PAP API v3 key does not have Affiliates Read access or its IP whitelist rejected the server.',
		mediline_pap_v3_http_429: 'PAP received requests too quickly. Wait a few seconds, then try again.',
		mediline_pap_v3_json: 'PAP API v3 returned an unreadable response. Try again in a moment.',
		mediline_pap_v3_userid: 'PAP returned an invalid internal affiliate ID.',
		mediline_pap_v3_not_found: 'PAP API v3 could not find the session-owned affiliate.',
		pap_v3_profile_incomplete: 'PAP API v3 did not return userid and refid.',
		pap_v3_identity_mismatch: 'PAP session and API v3 returned different affiliate identities.',
		pap_v3_username_mismatch: 'PAP session and API v3 returned different usernames.',
		pap_affiliate_inactive: 'This PAP partner account is not active.',
		pap_profile_incomplete: 'PAP accepted the session but did not return the required partner profile fields.',
		pap_bridge_not_configured: 'The WordPress PAP API bridge is not configured on this site.',
		pap_api_unavailable: 'WordPress could not verify the session with PAP. Try again in a moment.',
		pap_bridge_rate_limited: 'Too many attempts. Wait one minute, then reopen Store Builder from the PAP menu.'
	};

	function showError(text) {
		title.textContent = 'Store Builder sign-in failed.';
		message.textContent = text;
		progress.hidden = true;
		retry.hidden = false;
	}

	function parseFragment() {
		var values = {};
		window.location.hash.replace(/^#/, '').split('&').forEach(function (part) {
			var separator = part.indexOf('=');
			if (separator < 1) return;
			try {
				values[decodeURIComponent(part.slice(0, separator))] = decodeURIComponent(part.slice(separator + 1));
			} catch (error) {
				// Invalid percent encoding is handled as a missing credential below.
			}
		});
		var parsed = {
			session: values.session || ''
		};
		// Remove the PAP bearer session before any subsequent navigation or copy.
		window.history.replaceState(null, '', window.location.pathname + window.location.search);
		return parsed;
	}

	function exchange() {
		if (!credentials || !credentials.session) {
			showError('Reopen Store Builder from your Post Affiliate Pro partner panel.');
			return;
		}

		title.textContent = 'Opening Store Builder...';
		message.textContent = 'Securely confirming your Post Affiliate Pro session.';
		progress.hidden = false;
		retry.hidden = true;

		fetch(root.getAttribute('data-endpoint'), {
			method: 'POST',
			credentials: 'omit',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ session: credentials.session })
		}).then(function (response) {
			return response.json().catch(function () { return {}; }).then(function (payload) {
				if (!response.ok || !payload.iframe_url) {
					var error = new Error('rejected');
					error.bridgeCode = payload.code || '';
					error.bridgeData = payload.data || {};
					throw error;
				}
				return payload;
			});
		}).then(function (payload) {
			credentials = null;
			window.location.replace(payload.iframe_url);
		}).catch(function (error) {
			var errorMessage = errorMessages[error.bridgeCode] || 'The Store Builder authentication request failed. Reopen this page from the PAP partner-panel menu.';
			if (error.bridgeCode === 'pap_profile_incomplete' && error.bridgeData) {
				var missing = Array.isArray(error.bridgeData.missing) ? error.bridgeData.missing.join(', ') : '';
				var fields = Array.isArray(error.bridgeData.available_fields) ? error.bridgeData.available_fields.join(', ') : '';
				if (missing || fields) errorMessage += ' Missing: ' + (missing || 'unknown') + '. PAP fields: ' + (fields || 'none') + '.';
			}
			showError(errorMessage);
		});
	}

	credentials = parseFragment();
	retry.addEventListener('click', exchange);
	exchange();
})();
