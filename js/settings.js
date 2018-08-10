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

	// Enable/disable file changes.
	var file_changes = jQuery( 'input[name=wsal-file-changes]' );

	// File change settings.
	var file_changes_settings = [
		jQuery( '#wsal-file-alert-types' ),
		jQuery( '#wsal-scan-frequency' ),
		jQuery( '#wsal-scan-directories' ),
		jQuery( '#wsal-scan-exclude-extensions' ),
		jQuery( '#wsal-scan-time fieldset' ),
		jQuery( '#wsal_add_file_name' ),
		jQuery( '#wsal_add_file' ),
		jQuery( '#wsal_remove_exception_file' ),
		jQuery( '#wsal_add_file_type_name' ),
		jQuery( '#wsal_add_file_type' ),
		jQuery( '#wsal_remove_exception_file_type' ),
		jQuery( '#wsal_add_dir_name' ),
		jQuery( '#wsal_add_dir' ),
		jQuery( '#wsal_remove_exception_dir' ),
		jQuery( '#wsal_files input[type=checkbox]' ),
		jQuery( '#wsal_files_types input[type=checkbox]' ),
		jQuery( '#wsal_dirs input[type=checkbox]' )
	];

	// Update settings of file changes on page load.
	if ( file_changes.prop( 'checked' ) ) {
		file_changes_settings.forEach( wsal_enable_setting ); // Enable the settings.
	} else {
		file_changes_settings.forEach( wsal_disable_setting ); // Disable the settings.
	}

	// Update settings when file changes is enabled or disabled.
	file_changes.on( 'change', function() {
		if ( file_changes.prop( 'checked' ) ) {
			file_changes_settings.forEach( wsal_enable_setting ); // Enable the settings.
		} else {
			file_changes_settings.forEach( wsal_disable_setting ); // Disable the settings.
		}
	});

	// Scan frequency.
	var scan_frequency = jQuery( 'select[name=wsal-scan-frequency]' ); // Frequency.
	var scan_days = jQuery( 'span#wsal-scan-day' ); // Day of the week.
	var scan_date = jQuery( 'span#wsal-scan-date' ); // Date of the month.
	wsal_update_scan_time( scan_frequency, scan_days, scan_date ); // Update on page load.

	// Update when frequency is changed.
	scan_frequency.change( function() {
		wsal_update_scan_time( scan_frequency, scan_days, scan_date );
	});

	/**
	 * Updates the display of days and date option based on
	 * selected frequency.
	 *
	 * @param {object} frequency Frequency selector.
	 * @param {object} days Days selector.
	 * @param {object} date Date selector.
	 */
	function wsal_update_scan_time( frequency, days, date ) {
		if ( 'weekly' === frequency.val() ) {
			date.addClass( 'hide' );
			days.removeClass( 'hide' );
		} else if ( 'monthly' === frequency.val() ) {
			days.addClass( 'hide' );
			date.removeClass( 'hide' );
		} else {
			date.addClass( 'hide' );
			days.addClass( 'hide' );
		}
	}

	// Add directory to scan file exception list.
	jQuery( '#wsal_add_dir' ).click( function() {
		wsal_add_scan_exception( 'dir' );
	});

	// Add file to scan file exception list.
	jQuery( '#wsal_add_file' ).click( function() {
		wsal_add_scan_exception( 'file' );
	});

	// Add file extension to scan extension exception list.
	jQuery( '#wsal_add_file_type' ).click( function() {
		wsal_add_scan_exception( 'extension' );
	});

	/**
	 * Add exception for file changes scan.
	 *
	 * @param {string} type Type of exception added. For example, a `file` or an `extension`.
	 */
	function wsal_add_scan_exception( type ) {
		if ( 'file' === type ) {
			var setting_input = jQuery( '#wsal_add_file_name' );
			var setting_value = setting_input.val();
			var setting_container = jQuery( '#wsal_files' );
			var setting_nonce = jQuery( '#wsal_scan_exception_file' ).val();
			var setting_error = jQuery( '#wsal_file_name_error' );

			// Validate file name.
			var pattern = /^\s*[a-z-._\d,\s]+\s*$/i;
		} else if ( 'extension' === type ) {
			var setting_input = jQuery( '#wsal_add_file_type_name' );
			var setting_value = setting_input.val();
			var setting_container = jQuery( '#wsal_files_types' );
			var setting_nonce = jQuery( '#wsal_scan_exception_file_type' ).val();
			var setting_error = jQuery( '#wsal_file_type_error' );

			// Validate file name.
			var pattern = /^\s*[a-z-._\d,\s]+\s*$/i;
		} else if ( 'dir' === type ) {
			var setting_input = jQuery( '#wsal_add_dir_name' );
			var setting_value = setting_input.val();
			var setting_container = jQuery( '#wsal_dirs' );
			var setting_nonce = jQuery( '#wsal_scan_exception_dir' ).val();
			var setting_error = jQuery( '#wsal_dir_error' );

			// Validate file name.
			var pattern = /^\s*[a-z-._\d,\s/]+\s*$/i;
		}
		setting_error.addClass( 'hide' );

		if ( setting_value.match( pattern ) ) {

			// Ajax request to add file to scan file exception list.
			jQuery.ajax({
				type: 'POST',
				url: ajaxurl,
				async: true,
				dataType: 'json',
				data: {
					action: 'wsal_scan_add_exception',
					nonce: setting_nonce,
					data_name: setting_value,
					data_type: type
				},
				success: function( data ) {
					if ( data.success ) {
						var file = jQuery( '<span></span>' );
						var file_input = jQuery( '<input />' );
						file_input.prop( 'type', 'checkbox' );
						file_input.prop( 'id', setting_value );
						file_input.prop( 'value', setting_value );

						var file_label = jQuery( '<label></label>' );
						file_label.prop( 'for', setting_value );
						file_label.text( setting_value );

						file.append( file_input );
						file.append( file_label );

						setting_container.append( file );
						setting_input.removeAttr( 'value' );
					} else {
						console.log( data.message );
						setting_error.text( data.message );
						setting_error.removeClass( 'hide' );
					}
				},
				error: function( xhr, textStatus, error ) {
					console.log( xhr.statusText );
					console.log( textStatus );
					console.log( error );
				}
			});
		} else {
			if ( 'file' === type ) {
				alert( wsal_data.invalidFile );
			} else if ( 'extension' === type ) {
				alert( wsal_data.invalidFileExt );
			} else if ( 'dir' === type ) {
				alert( wsal_data.invalidDir );
			}
		}
	}

	// Remove directories from scan file exception list.
	jQuery( '#wsal_remove_exception_dir' ).click( function() {
		wsal_remove_scan_exception( 'dir' );
	});

	// Remove files from scan file exception list.
	jQuery( '#wsal_remove_exception_file' ).click( function() {
		wsal_remove_scan_exception( 'file' );
	});

	// Remove file extensions from scan file extensions exception list.
	jQuery( '#wsal_remove_exception_file_type' ).click( function() {
		wsal_remove_scan_exception( 'extension' );
	});

	/**
	 * Remove exception for changes scan.
	 *
	 * @param {string} type Type of exception removed. For example, a `file` or an `extension`.
	 */
	function wsal_remove_scan_exception( type ) {
		if ( 'file' === type ) {
			var setting_values = jQuery( '#wsal_files input[type=checkbox]' ); // Get files.
			var setting_nonce  = jQuery( '#wsal_scan_remove_exception_file' ).val(); // Nonce.
		} else if ( 'extension' === type ) {
			var setting_values = jQuery( '#wsal_files_types input[type=checkbox]' ); // Get files.
			var setting_nonce  = jQuery( '#wsal_scan_remove_exception_file_type' ).val(); // Nonce.
		} else if ( 'dir' === type ) {
			var setting_values = jQuery( '#wsal_dirs input[type=checkbox]' ); // Get files.
			var setting_nonce  = jQuery( '#wsal_scan_remove_exception_dir' ).val(); // Nonce.
		}

		// Make array of files which are checked.
		var removed_values = [];
		for ( var index = 0; index < setting_values.length; index++ ) {
			if ( jQuery( setting_values[ index ]).is( ':checked' ) ) {
				removed_values.push( jQuery( setting_values[ index ]).val() );
			}
		}

		// Ajax request to remove array of files from file exception list.
		jQuery.ajax({
			type: 'POST',
			url: ajaxurl,
			async: true,
			dataType: 'json',
			data: {
				action: 'wsal_scan_remove_exception',
				nonce: setting_nonce,
				data_type: type,
				data_removed: removed_values
			},
			success: function( data ) {
				if ( data.success ) {

					// Remove files from list on the page.
					for ( index = 0; index < removed_values.length; index++ ) {
						var setting_value = jQuery( 'input[value="' + removed_values[ index ] + '"]' );
						if ( setting_value ) {
							setting_value.parent().remove();
						}
					}
				} else {
					console.log( data.message );
				}
			},
			error: function( xhr, textStatus, error ) {
				console.log( xhr.statusText );
				console.log( textStatus );
				console.log( error );
			}
		});
	}

	// Scan now start button.
	jQuery( '#wsal-scan-now' ).click( function( event ) {
		event.preventDefault();

		// Change button text.
		var scan_btn = jQuery( this );
		scan_btn.attr( 'disabled', 'disabled' );
		scan_btn.text( wsal_data.scanInProgress );

		// Stop scan button.
		var stop_scan_btn = jQuery( '#wsal-stop-scan' );
		stop_scan_btn.removeAttr( 'disabled' );


		// Get start scan nonce.
		var manual_scan_nonce = jQuery( '#wsal-scan-now-nonce' ).val();

		// Ajax request to remove array of files from file exception list.
		jQuery.ajax({
			type: 'POST',
			url: ajaxurl,
			async: true,
			dataType: 'json',
			data: {
				action: 'wsal_manual_scan_now',
				nonce: manual_scan_nonce
			},
			success: function( data ) {
				if ( data.success ) {

					// Change button text.
					scan_btn.text( wsal_data.scanNow );
					scan_btn.removeAttr( 'disabled' );
					stop_scan_btn.attr( 'disabled', 'disabled' );
				} else {
					scan_btn.text( wsal_data.scanFailed );
					console.log( data.message );
				}
			},
			error: function( xhr, textStatus, error ) {
				console.log( xhr.statusText );
				console.log( textStatus );
				console.log( error );
			}
		});
	});

	// Stop scan start button.
	jQuery( '#wsal-stop-scan' ).click( function( event ) {
		event.preventDefault();

		// Change button attributes.
		var stop_scan_btn = jQuery( this );
		stop_scan_btn.attr( 'disabled', 'disabled' );

		// Ajax request to remove array of files from file exception list.
		jQuery.ajax({
			type: 'POST',
			url: ajaxurl,
			async: true,
			dataType: 'json',
			data: {
				action: 'wsal_stop_file_changes_scan',
				nonce: jQuery( '#wsal-stop-scan-nonce' ).val()
			},
			success: function( data ) {
				if ( data.success ) {

					// Change button text.
					// stop_scan_btn.removeAttr( 'disabled' );
				} else {
					console.log( data.message );
				}
			},
			error: function( xhr, textStatus, error ) {
				console.log( xhr.statusText );
				console.log( textStatus );
				console.log( error );
			}
		});
	});

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
});
