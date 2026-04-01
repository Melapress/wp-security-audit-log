/**
 * JavaScript for feedback form in wp-admin/plugins
 *
 * @since 5.6.2
 */
(function () {
	const { __ } = wp.i18n;

	const deactivationPopover = document.getElementById(
		'wp-security-audit-log-popover'
	);

	if (!deactivationPopover) {
		return;
	}

	let deactivateButton = null;

	/**
	 * Send a POST request to the remote server.
	 *
	 * @since 5.6.2
	 */
	const sendRequest = async (url, data) => {
		const formData = new FormData();

		for (const [key, value] of Object.entries(data)) {
			formData.append(key, value);
		}

		const response = await fetch(url, { method: 'POST', body: formData });

		try {
			return await response.json();
		} catch (e) {
			return {};
		}
	};

	// Intercept at document level in capture phase runs before any other handler.
	document.addEventListener(
		'click',
		function (e) {
			const target = e.target.closest('span.deactivate a');

			if (!target) {
				return;
			}

			// Check if this is the WSAL deactivate link, if not return early.
			if (!target.href || target.href.indexOf('wp-security-audit-log') === -1) {
				return;
			}

			deactivateButton = target;

			e.preventDefault();
			e.stopPropagation();
			e.stopImmediatePropagation();

			deactivationPopover.showPopover();
		},
		true
	);

	// Show/hide the feedback fields based on the selected reason.
	let activeWrapper = null;

	deactivationPopover
		.querySelectorAll('.wsal-reason-wrapper')
		.forEach((reasonWrapper) => {
			reasonWrapper.addEventListener('click', () => {
				// Return early if clicking on the active element
				if (reasonWrapper === activeWrapper) {
					return;
				}

				activeWrapper = reasonWrapper;

				// Ensure the radio button in this wrapper is checked.
				const radio = reasonWrapper.querySelector('input[type="radio"]');

				if (radio) {
					radio.checked = true;
				}

				// Hide all feedback fields first.
				deactivationPopover
					.querySelectorAll('.wsal-feedback-wrapper')
					.forEach((feedbackWrapper) => {
						feedbackWrapper.style.display = 'none';

						// Clear feedback values when switching options.
						const field = feedbackWrapper.querySelector(
							'textarea, input[type="text"]'
						);

						if (field) {
							field.value = '';
						}
					});

				const feedbackWrapper = reasonWrapper.querySelector(
					'.wsal-feedback-wrapper'
				);

				// Display the clicked reason feedback wrapper if exists.
				if (feedbackWrapper) {
					feedbackWrapper.style.display = 'block';
				}
			});
		});

	// Handle clicking on the dismiss button skip feedback, deactivate directly.
	deactivationPopover
		.querySelector('button.wsal-dismiss')
		.addEventListener('click', (e) => {
			e.preventDefault();

			if (deactivateButton) {
				window.location.href = deactivateButton.href;
			}
		});

	// Handle clicking on the close button to dismiss the popover.
	deactivationPopover
		.querySelector('button.wsal-close-button')
		.addEventListener('click', () => {
			deactivationPopover.hidePopover();
		});

	// Handle clicking on the submit button to send feedback, then deactivate.
	deactivationPopover
		.querySelector('button.wsal-submit')
		.addEventListener('click', async (e) => {
			e.preventDefault();

			const checkedRadio = deactivationPopover.querySelector(
				'input[name="reason"]:checked'
			);

			const reason = checkedRadio ? checkedRadio.value : 'no-reason-given';

			const activeReasonWrapper = checkedRadio
				? checkedRadio.closest('.wsal-reason-wrapper')
				: null;

			const feedbackField = activeReasonWrapper
				? activeReasonWrapper.querySelector('textarea, input[type="text"]')
				: null;

			const requestData = {
				action: 'plugin_deactivation',
				plugin: wsalFeedbackForm.plugin,
				site: wsalFeedbackForm.siteUrl,
				reason: reason,
				feedback:
					feedbackField && feedbackField.value
						? feedbackField.value
						: 'no-feedback-detail-available'
			};

			// Hide the popover and show deactivating feedback after collecting form data.
			deactivationPopover.hidePopover();

			// Show deactivating feedback on the button.
			if (deactivateButton) {
				const isNetwork = window.location.pathname.indexOf('/network/') !== -1;
				deactivateButton.textContent = isNetwork
					? __('Network Deactivating...', 'wp-security-audit-log')
					: __('Deactivating...', 'wp-security-audit-log');
			}

			try {
				// Request 1: Get nonce from remote server.
				const nonceResponse = await sendRequest(
					wsalFeedbackForm.remoteUrl +
						'/?rest_route=/deactivation-feedback-server/v1/get-nonce',
					requestData
				);

				// Request 2: Submit feedback to remote server.
				if (nonceResponse.nonce) {
					requestData.nonce = nonceResponse.nonce;

					await sendRequest(
						wsalFeedbackForm.remoteUrl +
							'/?rest_route=/deactivation-feedback-server/v1/submit-feedback',
						requestData
					);
				}
			} catch (e) {
				// Silent fail in case this fetch fails, feedback lost, deactivation continues.
			}

			if (deactivateButton) {
				window.location.href = deactivateButton.href;
			}
		});
})();
