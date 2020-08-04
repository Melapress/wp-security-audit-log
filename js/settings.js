/**
 * Settings Page Script
 *
 * @since 1.0.0
 */
jQuery( document ).ready( function() {
	var RemoveSecToken = function() {
		var $this = jQuery( this ).parents( 'span:first' );
		$this.addClass( 'sectoken-del' ).fadeOut( 'fast', function() {
			$this.remove();
		});
	};

	jQuery( '#ViewerQueryBox, #EditorQueryBox, #ExRoleQueryBox, #ExUserQueryBox, #CustomQueryBox, #IpAddrQueryBox, #ExCPTsQueryBox, #ExURLsQueryBox' ).keydown( function( event ) {
		if ( 13 === event.keyCode ) {
			var type = jQuery( this ).attr( 'id' ).substr( 0, 6 );
			console.log( type );
			jQuery( '#' + type + 'QueryAdd' ).click();
			return false;
		}
	});

	jQuery( '#ViewerQueryAdd, #EditorQueryAdd, #ExRoleQueryAdd, #ExUserQueryAdd, #CustomQueryAdd, #IpAddrQueryAdd, #ExCPTsQueryAdd, #ExURLsQueryAdd' ).click( function() {
		var type 	 = jQuery( this ).attr( 'id' ).substr( 0, 6 );
		var value 	 = jQuery.trim( jQuery( '#' + type + 'QueryBox' ).val() );
		var existing = jQuery( '#' + type + 'List input' ).filter( function() {
			return this.value === value;
		});

		if ( ! value || existing.length ) {
			return;
		} // if value is empty or already used, stop here

		jQuery( '#' + type + 'QueryBox, #' + type + 'QueryAdd' ).attr( 'disabled', true );
		jQuery.post(
			jQuery( '#ajaxurl' ).val(),
			{
				action: 'AjaxCheckSecurityToken',
				token: value,
				nonce: wsal_data.wp_nonce
			},
			function( data ) {
				data = JSON.parse( data );
				jQuery( '#' + type + 'QueryBox, #' + type + 'QueryAdd' ).attr( 'disabled', false );
				if ( data.success ) {
					if ( 'ExURLs' === type ) {
						if ( 'other' === data.tokenType ) {
							alert( wsal_data.invalidURL );
							jQuery( '#' + type + 'QueryBox' ).val( '' );
							return;
						}
					} else if ( 'ExCPTs' === type ) {
						if ( 'other' === data.tokenType ) {
							alert( wsal_data.invalidCPT );
							jQuery( '#' + type + 'QueryBox' ).val( '' );
							return;
						}
					} else if ( 'IpAddr' === type ) {
						if ( 'other' === data.tokenType ) {
							alert( wsal_data.invalidIP );
							jQuery( '#' + type + 'QueryBox' ).val( '' );
							return;
						}
					} else if ( 'Custom' != type && 'IpAddr' != type ) {
						if ( 'other' === data.tokenType ) {
							alert( wsal_data.invalidUser );
							jQuery( '#' + type + 'QueryBox' ).val( '' );
							return;
						}
					}
					jQuery( '#' + type + 'QueryBox' ).val( '' );
					jQuery( '#' + type + 'List' ).append( jQuery( '<span class="sectoken-' + data.tokenType + '"/>' ).text( data.token ).append(
						jQuery( '<input type="hidden" name="' + type + 's[]"/>' ).val( data.token ),
						jQuery( '<a href="javascript:;" title="Remove">&times;</a>' ).click( RemoveSecToken )
					) );
				} else {
					alert( data.message );
					jQuery( '#' + type + 'QueryBox' ).val( '' );
					return;
				}
			}
		);
	});

	jQuery( '#ViewerList>span>a, #EditorList>span>a, #ExRoleList>span>a, #ExUserList>span>a, #CustomList>span>a, #IpAddrList>span>a, #ExCPTsList>span>a, #ExURLsList>span>a' ).click( RemoveSecToken );

	var usersUrl = ajaxurl + '?action=AjaxGetAllUsers&wsal_nonce=' + wsal_data.wp_nonce;
	jQuery( '#ExUserQueryBox' ).autocomplete({
	    source: usersUrl,
	    minLength: 1
	});

	var rolesUrl = ajaxurl + '?action=AjaxGetAllRoles&wsal_nonce=' + wsal_data.wp_nonce;
	jQuery( '#ExRoleQueryBox' ).autocomplete({
	    source: rolesUrl,
	    minLength: 1
	});

	var cptsUrl = ajaxurl + '?action=AjaxGetAllCPT&wsal_nonce=' + wsal_data.wp_nonce;
	jQuery( '#ExCPTsQueryBox' ).autocomplete({
	    source: cptsUrl,
	    minLength: 1
	});

	// Enable setting.
	function wsal_enable_setting( setting ) {
		setting.removeProp( 'disabled' );
	}

	// Disable setting.
	function wsal_disable_setting( setting ) {
		setting.prop( 'disabled', 'disabled' );
	}

	// Reset settings handler.
	var resetSettings = jQuery( '[data-remodal-id=wsal_reset_settings] button[data-remodal-action=confirm]' );
	resetSettings.click( function() {
		resetWSAL( 'wsal_reset_settings', jQuery( '#wsal-reset-settings-nonce' ).val() );
	});

	// Purge activity handler.
	var purgeActivity = jQuery( '[data-remodal-id=wsal_purge_activity] button[data-remodal-action=confirm]' );
	purgeActivity.click( function() {
		resetWSAL( 'wsal_purge_activity', jQuery( '#wsal-purge-activity-nonce' ).val() );
	});

	/**
	 * Reset ajax function.
	 *
	 * @param {string} action – Ajax action hook.
	 * @param {string} nonce – Nonce for security.
	 */
	function resetWSAL( action, nonce ) {
		jQuery.ajax({
			type: 'POST',
			url: ajaxurl,
			async: true,
			data: {
				action: action,
				nonce: nonce
			},
			success: function( data ) {
				console.log( data );
			},
			error: function( xhr, textStatus, error ) {
				console.log( xhr.statusText );
				console.log( textStatus );
				console.log( error );
			}
		});
	}

	if ( 0 < jQuery( 'input[name="restrict-log-viewer"][value="only_me"]' ).length ) {
		jQuery( 'input[name="restrict-plugin-settings"]' ).on( 'change', function() {
			if ( 'only_me' == this.value ) {

				//	re-enable "Only me" option in the log viewer access settings
				jQuery( 'input[name="restrict-log-viewer"][value="only_me"]' ).removeAttr( 'disabled' );
				if ( 'yes' == jQuery( 'input[name="restrict-log-viewer"][value="only_me"]' ).attr( 'data-revert-to-only-me' ) ) {
					jQuery( 'input[name="restrict-log-viewer"][value="only_me"]' ).attr( 'checked', 'checked' );
					jQuery( 'input[name="restrict-log-viewer"][value="only_me"]' ).removeAttr( 'data-revert-to-only-me' );
				}
			} else {

				//	disable "Only me" option in the log viewer access settings and change the selection to
				//	"Super administators and site administrators" if "Only me" was selected
				jQuery( 'input[name="restrict-log-viewer"][value="only_me"]' ).attr( 'disabled', 'disabled' );
				if ( 'only_me' == jQuery( 'input[name="restrict-log-viewer"]:checked' ).val() ) {
					jQuery( 'input[name="restrict-log-viewer"][value="only_admins"]' ).attr( 'checked', 'checked' );
					jQuery( 'input[name="restrict-log-viewer"][value="only_me"]' ).attr( 'data-revert-to-only-me', 'yes' );
				}
			}
		});
	}

    jQuery('input[name="mwp_stealth_mode"]').on('change', function () {
        var admin_blocking_support_input = jQuery('input[name="mwp_admin_blocking_support"]');
        if ('yes' == this.value) {

            //	re-enable the admin blocking support checkbox
            admin_blocking_support_input.removeAttr('disabled');
            if ('yes' == admin_blocking_support_input.attr('data-check-on-revert')) {
                admin_blocking_support_input.attr('checked', 'checked')
                    .removeAttr('data-check-on-revert');
            }
        } else {

            //	disable the admin blocking support checkbox and uncheck it as well
            admin_blocking_support_input.attr('disabled', 'disabled');
            if (admin_blocking_support_input.attr('checked')) {
                admin_blocking_support_input.removeAttr('checked', 'checked')
                    .attr('data-check-on-revert', 'yes')
            }
        }
    });
});
