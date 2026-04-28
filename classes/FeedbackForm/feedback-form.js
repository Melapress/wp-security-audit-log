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
	const feedbackFieldSelector = 'textarea, input[type="text"]';
	const reasonValidationMessage = deactivationPopover.querySelector(
		'.wsal-reason-validation'
	);
	const feedbackValidationMessage = deactivationPopover.querySelector(
		'.wsal-feedback-validation'
	);

	/**
	 * Normalize free-text feedback before validation or submission.
	 *
	 * @param {string} feedbackValue The feedback field value.
	 * @param {boolean} trimValue Whether to trim whitespace.
	 *
	 * @since 5.6.3
	 */
	const sanitizeFeedbackValue = (feedbackValue, trimValue = true) => {
		const sanitizedFeedbackValue = String(feedbackValue || '')
			.replace(/&(?:lt|gt|#60|#62|#x3c|#x3e);/gi, '')
			.replace(/[<>]/g, '');

		return trimValue ? sanitizedFeedbackValue.trim() : sanitizedFeedbackValue;
	};

	/**
	 * Show a validation message, or hide all validation messages.
	 *
	 * @param {HTMLElement|null} validationElement The message element to show.
	 *
	 * @since 5.6.3
	 */
	const toggleValidationMessage = (validationElement = null) => {
		[reasonValidationMessage, feedbackValidationMessage].forEach(
			(messageElement) => {
				if (messageElement) {
					messageElement.hidden = messageElement !== validationElement;
				}
			}
		);
	};

	/**
	 * Toggle the selected feedback field and reset the others.
	 *
	 * @param {HTMLElement|null} reasonWrapper The selected reason wrapper.
	 *
	 * @since 5.6.3
	 */
	const toggleFeedbackFields = (reasonWrapper = null) => {
		deactivationPopover
			.querySelectorAll('.wsal-feedback-wrapper')
			.forEach((feedbackWrapper) => {
				feedbackWrapper.style.display = 'none';

				const feedbackField = feedbackWrapper.querySelector(
					feedbackFieldSelector
				);

				if (feedbackField) {
					feedbackField.value = '';
				}
			});

		if (!reasonWrapper) {
			return;
		}

		const feedbackWrapper = reasonWrapper.querySelector(
			'.wsal-feedback-wrapper'
		);
		if (feedbackWrapper) {
			feedbackWrapper.style.display = 'block';
		}
	};

	/**
	 * Get the selected reason data from the popover.
	 *
	 * @return {Object|null} The selected reason data or null.
	 *
	 * @since 5.6.3
	 */
	const getSelectedReasonData = () => {
		const checkedRadio = deactivationPopover.querySelector(
			'input[name="reason"]:checked'
		);

		if (!checkedRadio) {
			return null;
		}

		const reasonWrapper = checkedRadio.closest('.wsal-reason-wrapper');
		const feedbackField = reasonWrapper
			? reasonWrapper.querySelector(feedbackFieldSelector)
			: null;
		const feedbackValue = feedbackField
			? sanitizeFeedbackValue(feedbackField.value)
			: '';

		if (feedbackField) {
			feedbackField.value = feedbackValue;
		}

		return {
			feedbackField: feedbackField,
			feedbackValue: feedbackValue,
			reason: checkedRadio.value
		};
	};

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
		} catch (error) {
			return {};
		}
	};

	// Intercept at document level in capture phase runs before any other handler.
	document.addEventListener(
		'click',
		(clickEvent) => {
			const target = clickEvent.target.closest('span.deactivate a');

			if (!target) {
				return;
			}

			// Check if this is the WSAL deactivate link, if not return early.
			if (!target.href || target.href.indexOf('wp-security-audit-log') === -1) {
				return;
			}

			deactivateButton = target;

			clickEvent.preventDefault();
			clickEvent.stopPropagation();
			clickEvent.stopImmediatePropagation();

			toggleValidationMessage();

			deactivationPopover.showPopover();
		},
		true
	);

	// Show/hide the feedback fields based on the selected reason.
	deactivationPopover.addEventListener('click', (clickEvent) => {
		const reasonWrapper = clickEvent.target.closest('.wsal-reason-wrapper');

		if (!reasonWrapper || !deactivationPopover.contains(reasonWrapper)) {
			return;
		}

		const reasonRadio = reasonWrapper.querySelector('input[type="radio"]');

		toggleValidationMessage();

		if (!reasonRadio || reasonRadio.checked) {
			return;
		}

		reasonRadio.checked = true;
		toggleFeedbackFields(reasonWrapper);
	});

	deactivationPopover.addEventListener('change', (changeEvent) => {
		if (!changeEvent.target.matches('input[name="reason"]')) {
			return;
		}

		toggleValidationMessage();
		toggleFeedbackFields(changeEvent.target.closest('.wsal-reason-wrapper'));
	});

	deactivationPopover.addEventListener('input', (inputEvent) => {
		if (!inputEvent.target.matches(feedbackFieldSelector)) {
			return;
		}

		inputEvent.target.value = sanitizeFeedbackValue(
			inputEvent.target.value,
			false
		);
		toggleValidationMessage();
	});

	// Handle clicking on the dismiss button skip feedback, deactivate directly.
	deactivationPopover
		.querySelector('button.wsal-dismiss')
		.addEventListener('click', (dismissEvent) => {
			dismissEvent.preventDefault();

			if (deactivateButton) {
				window.location.href = deactivateButton.href;
			}
		});

	// Handle clicking on the close button to dismiss the popover.
	deactivationPopover
		.querySelector('button.wsal-close-button')
		.addEventListener('click', () => {
			toggleValidationMessage();
			deactivationPopover.hidePopover();
		});

	// Handle clicking on the submit button to validate, send feedback, then deactivate.
	deactivationPopover
		.querySelector('button.wsal-submit')
		.addEventListener('click', async (submitEvent) => {
			submitEvent.preventDefault();

			const selectedReasonData = getSelectedReasonData();

			if (!selectedReasonData) {
				toggleValidationMessage(reasonValidationMessage);

				return;
			}

			const { feedbackField, feedbackValue, reason } = selectedReasonData;

			if (feedbackField && !feedbackValue) {
				toggleValidationMessage(feedbackValidationMessage);
				feedbackField.focus();

				return;
			}

			const requestData = {
				action: 'plugin_deactivation',
				plugin: wsalFeedbackForm.plugin,
				site: wsalFeedbackForm.siteUrl,
				reason: reason,
				feedback: feedbackValue || 'no-feedback-detail-available'
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
			} catch (error) {
				// Silent fail in case this fetch fails, feedback lost, deactivation continues.
			}

			if (deactivateButton) {
				window.location.href = deactivateButton.href;
			}
		});
})();
