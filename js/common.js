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
			url: ajaxurl,
			async: false,
			data: { action: 'AjaxDismissNotice', notice: nfn }
		});
		nfe.fadeOut();
	});

	jQuery( 'head' ).append( '<style>.wp-submenu .dashicons-external:before{vertical-align: bottom;}</style>' );
});
