/**
 * WSAL Admin Scripts
 *
 * @since 1.0.0
 */

jQuery( document ).ready( function() {

	/**
	 * Check & Load New Alerts on WP-Admin bar.
	 *
	 * @since 3.2.4
	 */
	if ( wsalCommonData.liveEvents ) {
		function wsalRefresh() {
			jQuery.ajax({
				type: 'POST',
				url: wsalCommonData.ajaxURL,
				async: true,
				dataType: 'json',
				data: {
					action: 'wsal_adminbar_events_refresh',
					nonce: wsalCommonData.commonNonce,
					eventsCount: wsalCommonData.eventsCount
				},
				success: function( data ) {
					if ( data.success ) {
						wsalCommonData.eventsCount = data.count;
						jQuery( '.wsal-live-notif-item a' ).html( data.message );
					}
				}
			});
		};

		// Check for new alerts every 30 secs.
		setInterval( wsalRefresh, 30000 );

		// Make the first call on page load.
		wsalRefresh();
	}

	jQuery( 'a.wsal-dismiss-notification' ).click( function() {
		var nfe = jQuery( this ).parents( 'div:first' );
		var nfn = nfe.attr( 'data-notice-name' );
		jQuery.ajax({
			type: 'POST',
			url: wsalCommonData.ajaxURL,
			async: false,
			data: { action: 'AjaxDismissNotice', notice: nfn }
		});
		nfe.fadeOut();
	});

	jQuery( 'head' ).append( '<style>.wp-submenu .dashicons-external:before{vertical-align: bottom;}</style>' );

	// Add on installer
	jQuery(".install-addon").not('.disabled').click( function(e) {
		// Disable other buttons whilst the process is happening.
		jQuery(".install-addon").not(this).prop('disabled', true);

		jQuery(this).html( wsalCommonData.installing );
		var currentButton = jQuery(this);
		var PluginSlug = jQuery(this).attr('data-plugin-slug');
		var nonceValue = jQuery(this).attr('data-nonce');
		var PluginDownloadUrl = jQuery(this).attr('data-plugin-download-url');
		var RedirectToTab = jQuery(this).attr('data-plugin-event-tab-id');
		jQuery(currentButton).next('.spinner').show('200');
		e.preventDefault();
		jQuery.ajax({
			type: 'POST',
			dataType : "json",
			url: wsalCommonData.ajaxURL,
			data : {
				action: "wsal_run_addon_install",
				plugin_slug: PluginSlug,
				plugin_url: PluginDownloadUrl,
				_wpnonce: nonceValue
			},
			complete: function( data ) {
				var do_redirect = true;
				if( data.responseText == '"already_installed"' ) {
					jQuery(currentButton).html( wsalCommonData.already_installed ).addClass('disabled');
					jQuery(currentButton).next('.spinner').hide('200');
					jQuery(currentButton).addClass('disabled');
				} else if ( data.responseText == '"activated"' ) {
					jQuery(currentButton).html( wsalCommonData.activated ).addClass('disabled');
					jQuery(currentButton).next('.spinner').hide('200');
					jQuery(currentButton).addClass('disabled');
				} else if ( JSON.stringify(data.responseText).toLowerCase().indexOf('failed') >= 0 ) {
					jQuery(currentButton).html( wsalCommonData.failed ).addClass('disabled');
					jQuery(currentButton).next('.spinner').hide('200');
					do_redirect = false;
				} else if ( data.responseText == '"success"' || JSON.stringify(data.responseText).toLowerCase().indexOf('success') >= 0 ) {
				 jQuery(currentButton).html( wsalCommonData.installed ).addClass('disabled');
				 jQuery(currentButton).next('.spinner').hide('200');
				}

				if (do_redirect && typeof RedirectToTab !== 'undefined') {
					setTimeout(function(){
						window.location="admin.php?page=wsal-togglealerts" + RedirectToTab;
						jQuery('[href="' + RedirectToTab + '"]').trigger('click');
						// Reload as tabs are not present on page.
						window.location.reload();
					},100);
				}
			 jQuery(".install-addon").not(this).prop('disabled', false);
			},
		});
	});

	// Totally disabling the button.
	jQuery(".install-addon.disabled").prop('disabled', true);

	// Hide save button when 3rd party plugins tab is Opened
	jQuery('.nav-tab').click(function(){
		if( jQuery('[href="#tab-third-party-plugins"]').hasClass('nav-tab-active') ) {
			jQuery('.submit #submit').hide(0);
		} else {
			jQuery('.submit #submit').show(0);
		}
	});
	if( jQuery('[href="#tab-third-party-plugins"]').hasClass('nav-tab-active') ) {
		jQuery('.submit #submit').hide(0);
	} else {
		jQuery('.submit #submit').show(0);
	}
});
