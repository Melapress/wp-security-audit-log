/**
 * Reports script.
 */

jQuery( document ).ready( function( $ ) {

	const reportGenerateNotice = '#mwpal-report-generate-response';
	const reportExtendNotice = '#mwpal-extend-report';

	if ( 'periodic' === reportsData.reportType ) {
		const reportSites = '#mwpal-rep-sites';
		const reportSitesOption = '#mwpal-rb-sites-2';
		const reportEvents = '#mwpal-rep-alert-codes';
		const reportEventsOption = '#mwpal-rb-alert-codes';
		const alertGroups = '.mwpal-alert-groups';
		const sendReportNowBtn = '.report-send-now';

		$( reportSites ).select2({
			data: JSON.parse( reportsData.sites ),
			placeholder: reportsData.selectSites,
			minimumResultsForSearch: 10,
			multiple: true
		}).on( 'select2-open', function() {
			let selectValue = $( this ).val();

			if ( ! selectValue.length ) {
				$( reportSitesOption ).prop( 'checked', true );
			}
		}).on( 'select2-removed', function() {
			let selectValue = $( this ).val();

			if ( ! selectValue.length ) {
				$( '#mwpal-rb-sites-1' ).prop( 'checked', true );
			}
		}).on( 'select2-close', function() {
			let selectValue = $( this ).val();

			if ( ! selectValue.length ) {
				$( '#mwpal-rb-sites-1' ).prop( 'checked', true );
			}
		});

		$( reportEvents ).select2({
			data: JSON.parse( reportsData.events ),
			placeholder: reportsData.selectEvents,
			minimumResultsForSearch: 10,
			multiple: true
		}).on( 'select2-open', function( event ) {
			let selectValue = $( event ).val;

			if ( selectValue ) {
				$( reportEventsOption ).prop( 'checked', true );
				$( '#mwpal-rb-groups' ).prop( 'checked', false );
			}
		}).on( 'select2-selecting', function( event ) {
			let selectValue = $( event ).val;

			if ( selectValue.length ) {
				$( reportEventsOption ).prop( 'checked', true );
				$( '#mwpal-rb-groups' ).prop( 'checked', false );
			}
		}).on( 'select2-removed', function( e ) {
			let selectValue = $( this ).val();

			if ( ! selectValue.length ) {
				$( reportEventsOption ).prop( 'checked', false );

				// if none is checked, check the Select All input
				const checked = $( '.mwpal-alert-groups:checked' );

				if ( ! checked.length ) {
					$( '#mwpal-rb-groups' ).prop( 'checked', true );
				}
			}
		});

		if ( '' !== reportsData.selectedSites ) {
			$( reportSitesOption ).prop( 'checked', true );
			$( reportSites ).select2( 'val', reportsData.selectedSites );
		}

		if ( '' !== reportsData.selectedEvents ) {
			$( reportEventsOption ).prop( 'checked', true );
			$( reportEvents ).select2( 'val', reportsData.selectedEvents );
		}

		$( alertGroups ).on( 'change', function() {
			if ( $( this ).is( ':checked' ) ) {
				$( '#mwpal-rb-groups' ).prop( 'checked', false );
			} else {
				let checked = $( '.mwpal-alert-groups:checked' ); // If none is checked, check the Select All input.

				if ( ! checked.length ) {
					let e = $( '#mwpal-rep-alert-codes' ).select2( 'val' );

					if ( ! e.length ) {
						$( '#mwpal-rb-groups' ).prop( 'checked', true );
						$( reportEventsOption ).prop( 'checked', false );
					}
				}
			}
		});

		$( '#mwpal-rb-groups' ).on( 'change', function() {
			if ( $( this ).is( ':checked' ) ) {

				// deselect all
				deselectEventGroups();

				// Deselect the alert codes checkbox if selected and no alert codes are provided.
				if ( $( reportEventsOption ).is( ':checked' ) ) {
					if ( ! $( '#mwpal-rep-alert-codes' ).val().length ) {
						$( reportEventsOption ).prop( 'checked', false );
					}
				}
			} else {
				$( this ).prop( 'checked', false );

				// select first
				$( '.mwpal-alert-groups' ).get( 0 ).prop( 'checked', true );
			}
		});

		$( reportEventsOption ).on( 'change', function() {
			if ( $( this ).prop( 'checked' ) ) {
				$( '#mwpal-rb-groups' ).prop( 'checked', false );
			} else {

				// If none is checked, check the Select All input.
				let checked = $( '.mwpal-alert-groups:checked' );

				if ( ! checked.length ) {
					$( '#mwpal-rb-groups' ).prop( 'checked', true );
				}
			}
		});

		$( '#mwpal-rep-users, #mwpal-rep-roles, #mwpal-rep-ip-addresses' ).on( 'focus', function() {
			const type = this.getAttribute( 'id' ).substr( 10 );
			jQuery( `#mwpal-rb-${type}-2` ).prop( 'checked', true );
		});

		$( '#mwpal-rep-users, #mwpal-rep-roles, #mwpal-rep-ip-addresses' ).focusout( function() {
			if ( ! jQuery( this ).val() ) {
				const type = this.getAttribute( 'id' ).substr( 10 );
				jQuery( `#mwpal-rb-${type}-1` ).prop( 'checked', true );
			}
		});

		createDateRangePicker( '#mwpal-rep-start-date' );
		createDateRangePicker( '#mwpal-rep-end-date' );
		
		disableEventGroups();

		// Add required to report email and name
		$( 'input[name="mwpal-periodic"]' ).click( function() {
			let valid = true;
			$( '#mwpal-notif-email' ).attr( 'required', true );
			$( '#mwpal-notif-name' ).attr( 'required', true );

			let reportEmail = $( '#mwpal-notif-email' ).val();
			let reportName = $( '#mwpal-notif-name' ).val();

			if ( ! validateEmail( reportEmail ) ) {
				$( '#mwpal-notif-email' ).css( 'border-color', '#dd3d36' );
				valid = false;
			} else {
				$( '#mwpal-notif-email' ).css( 'border-color', '#ddd' );
			}

			if ( ! reportName.match( /^[A-Za-z0-9_\s\-]{1,32}$/ ) ) {
				$( '#mwpal-notif-name' ).css( 'border-color', '#dd3d36' );
				valid = false;
			} else {
				$( '#mwpal-notif-name' ).css( 'border-color', '#ddd' );
			}

			return valid;
		});

		$( '#mwpal-reports' ).on( 'submit', function() {

			// Sites.
			let e = $( '#mwpal-rep-sites' ).val();
			if ( ! $( '#mwpal-rb-sites-1' ).is( ':checked' ) ) {
				if ( ! e.length ) {
					alert( reportsData.siteRequired );
					return false;
				}
			}

			// Users.
			if ( ! $( '#mwpal-rb-users-1' ).is( ':checked' ) ) {
				e = $( '#mwpal-rep-users' ).val();
				if ( ! e.length ) {
					alert( reportsData.userRequired );
					return false;
				}
			}

			// Roles.
			if ( ! $( '#mwpal-rb-roles-1' ).is( ':checked' ) ) {
				e = $( '#mwpal-rep-roles' ).val();
				if ( ! e.length ) {
					alert( reportsData.roleRequired );
					return false;
				}
			}

			// IP addresses.
			if ( ! $( '#mwpal-rb-ip-addresses-1' ).is( ':checked' ) ) {
				e = $( '#mwpal-rep-ip-addresses' ).val();
				if ( ! e.length ) {
					alert( reportsData.ipRequired );
					return false;
				}
			}

			// Event groups.
			if ( ( ! $( '#mwpal-rb-groups' ).is( ':checked' ) && ! $( '.mwpal-alert-groups:checked' ).length ) ) {
				if ( ! $( '#mwpal-rep-alert-codes' ).val().length ) {
					alert( reportsData.eventRequired );
					return false;
				}
			}

			return true;
		});

		$( sendReportNowBtn ).click( function() {
			ajaxSendReportNow( $( this ) );
		});
	}

	const filters = JSON.parse( reportsData.generateFilters );
	const sites = filters ? filters.sites : [];

	if ( typeof sites != 'undefined' && 1 === sites.length && -1 !== sites.indexOf( 'dashboard' ) ) {
		// Do nothing.
	} else {
		jQuery( reportExtendNotice ).show();
	}

	jQuery( `${reportExtendNotice} input[type=button]` ).click( function() {
		const isExtend = jQuery( this ).data( 'extend' );

		if ( ! isExtend && reportsData.generateNow ) {
			if ( 'periodic' === reportsData.reportType ) {
				jQuery( reportExtendNotice ).hide();
				jQuery( reportGenerateNotice ).removeAttr( 'style' );
				ajaxGenerateReport( reportsData.generateFilters );
			}
		} else if ( isExtend && reportsData.generateNow ) {
			if ( 'periodic' === reportsData.reportType ) {
				jQuery( reportExtendNotice ).hide();
				jQuery( reportGenerateNotice ).removeAttr( 'style' );
				ajaxGenerateReport( reportsData.generateFilters, null, true );
			}
		}
	});
});

/**
 * Deselect alert groups.
 */
function deselectEventGroups() {
	jQuery( '.mwpal-alert-groups' ).each( function() {
		jQuery( this ).prop( 'checked', false );
	});
}

/**
 * Criteria disables all the alert codes.
 */
function disableEventGroups() {
	jQuery( '#mwpal-rb-alert-groups' ).find( 'input' ).each( function() {
		jQuery( this ).attr( 'disabled', false );
	});
}

/**
 * Create date calendar selector.
 *
 * @param {string} inputDate Date element ID.
 */
function createDateRangePicker( inputDate ) {
	jQuery( inputDate ).daterangepicker({
		singleDatePicker: true,
		showDropdowns: true,
		minDate: '2000-01-01',
		maxDate: moment().format( 'YYYY-MM-DD' ),
		locale: {
			format: reportsData.dateFormat
		}
	});
	jQuery( inputDate ).val( '' ).attr( 'autocomplete', 'off' );
}

/**
 * Validate email for reports.
 *
 * @param {string} email Email.
 */
function validateEmail( email ) {
	let atpos = email.indexOf( '@' );
	let dotpos = email.lastIndexOf( '.' );

	if ( 1 > atpos || dotpos < atpos + 2 || dotpos + 2 >= email.length ) {
		return false;
	} else {
		return true;
	}
}

/**
 * Generate report AJAX handler.
 *
 * @param {string} filters JSON string of filters.
 * @param {string} nextDate Date for the next report.
 * @param {boolean} liveReport Set to true if report is live from child sites.
 */
function ajaxGenerateReport( filters, nextDate = null, liveReport = false ) {
	const reportGenerateNotice = '#mwpal-report-generate-response';

	jQuery.ajax({
		type: 'POST',
		url: reportsData.ajaxUrl,
		async: true,
		dataType: 'json',
		data: {
			action: 'generate_periodic_report',
			security: reportsData.security,
			filters: JSON.parse( filters ),
			nextDate: nextDate,
			limit: reportsData.reportsLimit,
			liveReport: liveReport
		},
		success: function( response ) {
			nextDate = response[0];

			if ( 0 != nextDate ) {
				let dateString = nextDate;
				dateString = dateString.split( '.' );
				let lastDate = new Date( dateString[0] * 1000 );

				jQuery( `${reportGenerateNotice} span` ).html( ' Last day examined: ' + lastDate.toDateString() + ' last day.' );
				ajaxGenerateReport( filters, nextDate, liveReport );
			} else {
				jQuery( `${reportGenerateNotice} .mwpal-lds-dual-ring` ).hide();

				if ( null !== response[1]) {
					jQuery( `${reportGenerateNotice} p` ).html( reportsData.processComplete );
					window.setTimeout( function() {
						window.location.href = response[1];
					}, 1000 );
				} else {
					jQuery( `${reportGenerateNotice} p` ).html( reportsData.noMatchEvents );
				}
			}
		},
		error: function( xhr, textStatus, error ) {
			jQuery( `${reportGenerateNotice} .mwpal-lds-dual-ring` ).hide();
			jQuery( `${reportGenerateNotice} p` ).html( textStatus );

			console.log( xhr.statusText );
			console.log( textStatus );
			console.log( error );
		}
	});
}

/**
 * Manual periodic report send now AJAX handler.
 *
 * @param {object} btn Send now button.
 * @param {mixed} nextDate Next date for the report.
 */
function ajaxSendReportNow( btn, nextDate = null ) {
	const reportName = btn.data( 'report-name' );
	btn.attr( 'disabled', true );
	btn.val( reportsData.sendingReport );

	jQuery.ajax({
		type: 'POST',
		url: reportsData.ajaxUrl,
		async: true,
		dataType: 'json',
		data: {
			action: 'mwpal_send_periodic_report',
			security: reportsData.security,
			reportName: reportName,
			nextDate: nextDate,
			limit: reportsData.reportsLimit
		},
		success: function( response ) {
			nextDate = response;
			if ( 0 != nextDate ) {
				ajaxSendReportNow( name, nextDate );
			} else {
				btn.val( reportsData.reportSent );
			}
		},
		error: function( xhr, textStatus, error ) {
			console.log( xhr.statusText );
			console.log( textStatus );
			console.log( error );
		}
	});
}
