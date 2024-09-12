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

	jQuery( '.js-query-box, #ViewerQueryBox, #EditorQueryBox, #ExRoleQueryBox, #ExUserQueryBox, #ExUserSubjectQueryBox, #CustomQueryBox, #IpAddrQueryBox, #IpAddrSubjectQueryBox, #ExCPTsQueryBox, #ExURLsQueryBox' ).keydown( function( event ) {
		if ( 13 === event.keyCode ) {
			var type = jQuery( this ).closest( 'fieldset' ).attr( 'data-type' );
			if (! type ) {
				type = jQuery( this ).attr( 'id' ).substr( 0, 6 );
			}
			jQuery( '#' + type + 'QueryAdd' ).click();
			return false;
		}
	});

	jQuery( '.js-query-add, #ViewerQueryAdd, #EditorQueryAdd, #ExRoleQueryAdd, #ExUserQueryAdd, #CustomQueryAdd, #IpAddrQueryAdd, #ExCPTsQueryAdd, #ExURLsQueryAdd, #StatusQueryAdd' ).click( function() {
		var buttonElm = jQuery( this );
		var fieldsetElm = buttonElm.closest( 'fieldset' );
		var type = fieldsetElm.attr( 'data-type' );
		if (! type ) {
			type = buttonElm.attr('id').substr(0, 6);
		}

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
				type: type,
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
					} else if ( 'Status' === type ) {
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
					} else if ( 'UserMeta' != type && 'PostMeta' != type && 'IpAddr' != type ) {
						if ( 'other' === data.tokenType ) {
							alert( wsal_data.invalidUser );
							jQuery( '#' + type + 'QueryBox' ).val( '' );
							return;
						}
					}
					jQuery( '#' + type + 'QueryBox' ).val( '' );
					jQuery( '#' + type + 'List' ).append( jQuery( '<span class="sectoken-' + data.tokenType + '"/>' ).text( data.token ).append(
						jQuery( '<input type="hidden" name="' + type + 's[]"/>' ).val( data.token ),
						jQuery( '<a href="javascript:;" title="' + wsal_data.remove + '">&times;</a>' ).click( RemoveSecToken )
					) );
				} else {
					alert( data.message );
					jQuery( '#' + type + 'QueryBox' ).val( '' );
					return;
				}
			}
		);
	});

	jQuery( '.js-list>span>a, #ViewerList>span>a, #EditorList>span>a, #ExRoleList>span>a, #ExUserList>span>a, #CustomList>span>a, #IpAddrList>span>a, #ExCPTsList>span>a, #ExURLsList>span>a, #StatusList>span>a' ).click( RemoveSecToken );

	var usersUrl = ajaxurl + '?action=AjaxGetAllUsers&wsal_nonce=' + wsal_data.wp_nonce;
	jQuery( '#ExUserQueryBox' ).autocomplete({
	    source: usersUrl,
	    minLength: 1
	});

    jQuery( '#ExUserSubjectQueryBox' ).autocomplete({
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

	var statusesUrl = ajaxurl + '?action=AjaxGetAllStatuses&wsal_nonce=' + wsal_data.wp_nonce;
	jQuery( '#StatusQueryBox' ).autocomplete({
	    source: statusesUrl,
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
	jQuery( '[data-remodal-id=wsal_reset_settings] button[data-remodal-action=confirm]' ).click( function() {
		resetWSAL( 'wsal_reset_settings', jQuery( '#wsal-reset-settings-nonce' ).val(), '.js-settings-reset' );
	});

	// Purge activity handler.
	jQuery( '[data-remodal-id=wsal_purge_activity] button[data-remodal-action=confirm]' ).click( function() {
		resetWSAL( 'wsal_purge_activity', jQuery( '#wsal-purge-activity-nonce' ).val(), '.js-purge-reset' );
	});

	/**
	 * Reset ajax function.
	 *
	 * @param {string} action – Ajax action hook.
	 * @param {string} nonce – Nonce for security.
	 * @param {string} triggerSelector - Selector expression of the event trigger element.
	 */
	function resetWSAL( action, nonce, triggerSelector ) {
		var triggerElm = null;
		if ( typeof  triggerSelector != 'undefined' && triggerSelector.length != 0 ){
			triggerElm = jQuery( triggerSelector );
			if ( triggerElm.length == 0) {
				triggerElm = null
			};
		}

		if ( triggerElm != null ) {
			jQuery( triggerSelector ).attr( 'disabled', 'disabled' );
			jQuery( triggerSelector ).after( '<span class="spinner is-active" style="float: none; margin-top: 0;"></span>');
			jQuery( triggerSelector ).siblings( '.notice' ).remove();
		}
		jQuery.ajax({
			type: 'POST',
			url: ajaxurl,
			async: true,
			data: {
				action: action,
				nonce: nonce
			},
			success: function( data ) {
				if ( null != triggerElm ) {
					jQuery( triggerSelector ).removeAttr( 'disabled' );
					jQuery( triggerSelector ).siblings( '.spinner' ).remove();
					if ( 'success' in data ) {
						var noticeCssClass = data.success ? 'notice-success' : 'notice-error';
						jQuery( triggerSelector ).after( '<span class="notice ' + noticeCssClass + '" style="margin-left: 10px; padding: 6px 10px;">' + data.data + '</span>');
					}
				} else {
					console.log(data);
				}
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

	/**
	 * Alert user to save settings before switching tabs.
	 */
	jQuery(function() {
		// Get form values as of page load.
		var $form = jQuery('form#audit-log-settings');
		var initialState = $form.serialize();

		jQuery( 'body' ).on( 'click', '.nav-tab:not(.nav-tab-active)', function ( e ) {
			// If the form has been modified, alert user.
			if (initialState !== $form.serialize()) {
				e.preventDefault();
				alert( wsal_data.saveSettingsChanges );
			}
		});
	});

	// Allow custom login message to be changed without saving/refreshing the page.
	jQuery('input[name="login_page_notification"]').on('change', function () {
		if ( 'true' == this.value ) {
			jQuery( '#login_page_notification_text' ).prop( 'disabled', false );
		} else {
			jQuery( '#login_page_notification_text' ).prop( 'disabled', true );
		}
	});

});

jQuery( document ).ready( function() {

    var severitiesUrl = ajaxurl + '?action=wsal_ajax_get_all_severities&wsal_nonce=' + wsal_data.wp_nonce;
	jQuery( '#SeveritiesQueryBox' ).autocomplete({
	    source: severitiesUrl,
	    minLength: 1
	});

	var eventTypesUrl = ajaxurl + '?action=wsal_ajax_get_all_event_types&wsal_nonce=' + wsal_data.wp_nonce;
	jQuery( '#EventTypeQueryBox' ).autocomplete({
	    source: eventTypesUrl,
	    minLength: 1
	});

	var objectTypesUrl = ajaxurl + '?action=wsal_ajax_get_all_object_types&wsal_nonce=' + wsal_data.wp_nonce;
	jQuery( '#ObjectTypeQueryBox' ).autocomplete({
	    source: objectTypesUrl,
	    minLength: 1
	});

	var eventIDTypesUrl = ajaxurl + '?action=wsal_ajax_get_all_event_ids&wsal_nonce=' + wsal_data.wp_nonce;
	jQuery( '#EventIDQueryBox' ).autocomplete({
	    source: eventIDTypesUrl,
	    minLength: 1
	});	  
 });