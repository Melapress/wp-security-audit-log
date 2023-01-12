 jQuery( document ).ready( function() {

	jQuery(function() {

		// Handle exporting of settings to JSON.
		jQuery( 'body' ).on( 'click', '[data-export-wpws-settings]', function ( e ) {
			e.preventDefault();
			var ourButton = jQuery( this );
			var nonce     = ourButton.attr( 'data-nonce' );

			var key = jQuery( this ).closest( '.logs-management-settings').data( 'key' );
			jQuery.ajax({
				type: 'POST',
				url: ajaxurl,
				async: true,
				data: {
					action: key + '_export_settings',
					nonce: nonce,
				},
				success: function ( result ) {
					// Convert JSON Array to string.
					var json = JSON.stringify( result.data );
					var blob = new Blob([json]);
					var link = document.createElement('a');
					link.href = window.URL.createObjectURL(blob);
					link.download = key + "_settings.json";
					link.click();
				}
			});
		});

		// Check and import settings.
		jQuery( 'body' ).on( 'click', '[data-import-wpws-settings]', function ( e ) {
			e.preventDefault();

			jQuery( '#wpws-settings-file-output li, #wpws-import-read' ).remove();

			// Check extension.
			var jsonFile = jQuery( '#wpws-settings-file' );
			var ext = jsonFile.val().split(".").pop().toLowerCase();
			
			// Alert if wrong file type.
			if( jQuery.inArray( ext, ["json"] ) === -1 ){
				alert( wpws_import_data.wrongFormat );
				return false;
			}

			build_file_info( 'false' );
		});

		// Proceed with import after checks.
		jQuery( 'body' ).on( 'click', '#proceed', function ( e ) {
			build_file_info( 'true' );
		});

		jQuery( 'body' ).on( 'click', '.import-settings-modal-close', function ( e ) {
			var modal = document.getElementById( "import-settings-modal" );
			modal.style.display = "none";
		});

		/**
		 * Check settings to make sure roles/users exists.
		 */
		function checkSettingPreImport( option_name, option_value, do_import ) {

			// Show popup.
			var modal = document.getElementById( "import-settings-modal" );
			modal.style.display = "block";
			
			if ( do_import == 'true' ) {
				jQuery( '#wpws-modal-title' ).text( wpws_import_data.importingMessage );
				jQuery( '[data-wpws-option-name] > span' ).addClass( 'complete' );
			} else {
				jQuery( '#wpws-modal-title' ).text( wpws_import_data.checkingMessage );
			}
			
			var key = jQuery( '.logs-management-settings').data( 'key' );
			jQuery.ajax({
				type: 'POST',
				url: ajaxurl,
				async: true,
				data: {
					action: key + '_check_setting_pre_import',
					setting_name : option_name,
					setting_value : option_value,
					process_import : do_import,
					nonce : wpws_import_data.wp_nonce,
				},
				success: function ( result ) {
					var wasSuccess = false;
					if ( result.success ) {
						wasSuccess = true;
						if ( do_import == 'true' && typeof result.data['import_confirmation'] != 'undefined' ) {
							var markup = '<span style="color: green;"> ' + result.data['import_confirmation']  + '</span>';
						} else {
							var markup = '<span style="color: green;" class="dashicons dashicons-yes-alt"></span>';
						}
						jQuery( '[data-wpws-option-name="'+ option_name +'"]' ).append( markup )
					} else {
						if ( 'not_found' == result.data['failure_reason_type'] ) {
							var helpText = wpws_import_data.notFoundMessage;
						} else if ( 'not_supported' == result.data['failure_reason_type'] ) {
							var helpText = wpws_import_data.notSupportedMessage;
						} else if ( 'check_restrict_access' == result.data['failure_reason_type'] ) {
							var helpText = wpws_import_data.restrictAccessMessage;
						}
						var helpLink = "<a href='" + wpws_import_data.helpPage + "'>"+ wpws_import_data.helpLinkText +"</a>";
						jQuery( '[data-wpws-option-name="'+ option_name +'"]' ).append( '<span style="color: red;" class="dashicons dashicons-info"> <span>' + result.data['failure_reason'] + '</span> <a href="#" class="toolip" data-help="' + result.data['failure_reason_type'] + '" data-help-text="' + helpText + ' ' + helpLink +'">' + wpws_import_data.helpMessage + '</a></span>' );
					}

					var countNeeded = jQuery( '[data-wpws-option-name]' ).length;
					var countDone   = jQuery( '[data-wpws-option-name] > span:not(.complete)' ).length;
					
					if ( countNeeded == countDone ) {
						if ( do_import == 'true' ) {
							jQuery( '#wpws-modal-title' ).text( wpws_import_data.importedMessage );
							jQuery( '#ready-text').text( wpws_import_data.proceedMessage );
							jQuery( '#proceed').remove();
							jQuery( '#wpws-import-read' ).removeClass( 'disabled' );
							jQuery( '#cancel').val( wpws_import_data.ok );
						} else {
							var errorCount = jQuery( '[data-wpws-option-name] .dashicons-info' ).length;
							if ( errorCount ) {
								jQuery( '#wpws-modal-title' ).text( wpws_import_data.checksFailedMessage );
								var errorText = 'Proceed and skip invalid settings';
							} else {
								jQuery( '#wpws-modal-title' ).text( wpws_import_data.checksPassedMessage );
								var errorText = 'Proceed';
							}
							jQuery( '#wpws-import-read' ).remove();
							jQuery( '#wpws-settings-file-output' ).append( '<div id="wpws-import-read" style="display: inline-block;"><p id="ready-text">'+ wpws_import_data.readyMessage +'</p><input type="button" id="cancel" class="button-secondary import-settings-modal-close" value="'+ wpws_import_data.cancelMessage +'"> <input style="margin-left: 10px;" type="button" id="proceed" class="button-primary" value="'+ errorText +'"></div>' );
						}
					}
				}
			});
		}

		// Turn JSON into string and process it.
		function build_file_info( do_import ) {

			if ( do_import == 'false' ) {
				jQuery( '#wpws-settings-file-output' ).parent().append( '<div id="wpws-import-read" style="display: inline-block;" class="disabled"><input type="button" id="cancel" class="button-secondary import-settings-modal-close" value="'+ wpws_import_data.cancelMessage +'"> <input style="margin-left: 10px;" type="button" id="proceed" class="button-primary" value="'+ wpws_import_data.proceed +'"></div>' );
			} else {
				jQuery( '#wpws-import-read' ).addClass( 'disabled' );
			}

			var fileInput = document.getElementById( "wpws-settings-file" );
			var reader = new FileReader();
			reader.readAsText( fileInput.files[0] );
			
			var key = jQuery( '.logs-management-settings' ).data( 'key' );
			
			reader.onload = function () {
				var result = JSON.parse(reader.result);
				var resultsObj = JSON.parse( result );
				for (var i = 0; i < resultsObj.length; i++) {
					if ( resultsObj[i] != "" ) {
						var row = '';

						var option_name = resultsObj[i].option_name;
						var option_value = resultsObj[i].option_value;
						var cols = "<li data-wpws-option-name=" + resultsObj[i].option_name + "><div>" + resultsObj[i].option_name.replace( key + '_', '' ).replaceAll( '_', ' ' ).replaceAll( '-', ' ' ) + "</div></li>";
						row += cols;

						if ( do_import == 'false' ) {
							document.getElementById( 'wpws-settings-file-output' ).innerHTML += row;
							checkSettingPreImport( option_name, option_value, 'false' );
						} else {
							checkSettingPreImport( option_name, option_value, 'true' );
						}
					}
				}
			};
		}

		jQuery( 'body' ).on( 'mouseenter', '[data-help]', function ( e ) {
			var message = jQuery( this ).data( 'help-text' );
			jQuery( this ).append( '<div class="tooltip help-msg">'+ message +'</div>' );
		});

		jQuery( 'body' ).on( 'mouseout', '[data-help]', function ( e ) {
			if ( jQuery( '.help-msg:hover' ).length != 0 ) {
				setTimeout( function() {
					jQuery( '.help-msg' ).fadeOut( 800 );
				}, 1000 );
			}
		});
	});
 });
