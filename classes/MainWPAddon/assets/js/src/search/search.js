/**
 * Search.
 */
window.mwpalSearch = ( function( window ) {
	let searchObj = window;
	let attachEvents = [];

	/**
	 * Attach event to search object.
	 */
	searchObj.attach = function( callBack ) {
		if ( 'undefined' === typeof callBack ) {
			for ( let i = 0; i < attachEvents.length; i++ ) {
				attachEvents[i](); // Execute callbacks.
			}
		} else {
			attachEvents.push( callBack ); // Add callbacks.
		}
	};

	/**
	 * Initialize search object.
	 */
	searchObj.attach( function() {
		searchObj.list = '#mwpal-search-list';
		searchObj.activeFilters = [];
		searchObj.clearSearchBtn = '#mwpal-clear-search';
		searchObj.textSearchId = '#mwpal-search-box-search-input';

		jQuery( searchObj.textSearchId ).keypress( function( event ) {
			if ( 13 === event.which ) {
				jQuery( '#audit-log-viewer' ).submit();
			}

			jQuery( searchObj.clearSearchBtn ).removeAttr( 'disabled' );
		});

		if ( ' ' !== jQuery( searchObj.textSearchId ).val() ) {
			jQuery( searchObj.clearSearchBtn ).removeAttr( 'disabled' );
		}
	});

	/**
	 * Add filter.
	 */
	searchObj.addFilter = function( text, customList = false ) {
		let filter = text.split( ':' );
		let filtersList;

		if ( ! customList ) {
			filtersList = jQuery( searchObj.list );
			searchObj.activeFilters.push( text );
		} else {
			filtersList = jQuery( customList );
		}

		if ( 'from' === filter[0] || 'to' === filter[0] || 'on' === filter[0]) {
			if ( ! searchObj.checkDate( filter[1]) ) { // Validation date format.
				return;
			}
		}

		if ( ! jQuery( 'input[name="filters[]"][value="' + text + '"]' ).length ) {
			filtersList.append(
				jQuery( '<span/>' ).append(
					jQuery( '<input type="text" name="filters[]"/>' ).val( text ),
					jQuery( '<a href="javascript:;" title="' + searchScriptData.remove + '">&times;</a></span>' )
						.click( function() {
							jQuery( this ).parents( 'span:first' ).fadeOut( 'fast', function() {
								jQuery( this ).remove();
								searchObj.countFilters( jQuery( filtersList ) );
							});
						})
				)
			);

			jQuery( searchObj.clearSearchBtn ).removeAttr( 'disabled' );
		}

		searchObj.countFilters( jQuery( filtersList ) );
	};

	/**
	 * Update filter count.
	 */
	searchObj.countFilters = function( customList = false ) {
		const filtersList = false === customList ? jQuery( searchObj.list ) : jQuery( customList );
		const count       = filtersList.find( '>span' ).length;
		if ( 0 === count ) {
			filtersList.addClass( 'no-filters' );
		} else {
			filtersList.removeClass( 'no-filters' );
		}
	};

	/**
	 * Check date.
	 *
	 * @param {string} value Date value.
	 */
	searchObj.checkDate = function( value ) {
		let regularExp;

		if ( 'MM-DD-YYYY' == searchScriptData.dateFormat || 'DD-MM-YYYY' == searchScriptData.dateFormat ) {
			regularExp = /^(\d{1,2})-(\d{1,2})-(\d{4})$/; // Regular expression to match date format mm-dd-yyyy or dd-mm-yyyy.
		} else {
			regularExp = /^(\d{4})-(\d{1,2})-(\d{1,2})$/; // Regular expression to match date format yyyy-mm-dd.
		}

		if ( '' != value && ! value.match( regularExp ) ) {
			return false;
		}

		return true;
	};

	/**
	 * Clear search text and filters.
	 */
	searchObj.clearSearch = function() {
		jQuery( searchObj.list ).empty();
		jQuery( searchObj.textSearchId ).removeAttr( 'value' );
	};

	return searchObj;

}( window ) );

jQuery( document ).ready( function( $ ) {
	const liveSearchNotice = '#mwpal-live-search-notice';
	const liveSearchNoticeDismiss = `${liveSearchNotice} .dismiss`;
	const liveSearchNoticeSaveDismiss = `${liveSearchNotice} .dismiss-save`;

	window.mwpalSearch.attach();

	createDateRangePicker( '#mwpal_search_widget_from' );
	createDateRangePicker( '#mwpal_search_widget_to' );
	createDateRangePicker( '#mwpal_search_widget_on' );

	$( '.mwpal-add-button' ).click( function( event ) {
		event.preventDefault();
		let filterInput = $( this ).parent().find( 'input' );
		addToFilterList( filterInput );
	});

	$( '.mwpal_widget_select_single' ).change( function() {
		let filterInput = $( this );
		let filterPrefix = filterInput.data( 'prefix' );

		if ( ! filterInput.val() ) {
			return;
		}

		window.mwpalSearch.addFilter( `${filterPrefix}:${filterInput.val()}` );
	});

	$( window.mwpalSearch.clearSearchBtn ).click( function( event ) {
		event.preventDefault();
		window.mwpalSearch.clearSearch();

		// Get URL.
		const locationURL = window.location.href;
		const searchStr = searchScriptData.extensionName;
		let searchIndex = locationURL.search( searchStr ); // Search for wsal-auditlog value in URL.
		searchIndex += searchStr.length; // Add the length of the searched string to the index.
		window.location.href = locationURL.substr( 0, searchIndex ); // Redirect.
	});

	$( liveSearchNoticeDismiss ).click( function() {
		$( liveSearchNotice ).hide();
	});

	$( liveSearchNoticeSaveDismiss ).click( function() {
		const requestUrl = `${searchScriptData.adminAjax}?action=mwpal_dismiss_live_search_notice&security=${searchScriptData.security}`;
		let requestParams = { method: 'GET' };
		fetch( requestUrl, requestParams )
			.then( ( response ) => response.json() )
			.then( function( data ) {
				if ( data ) {
					$( liveSearchNotice ).hide();
				}
			})
			.catch( function( error ) {
				console.log( error );
			});
	});

	// show/hide the filter box.
	jQuery( '#filter-container-toggle' ).click(
		function( event ) {
			var button           = jQuery( this );
			var filterContainter = jQuery( '#almwp-filters-container' );
			jQuery( button.parent().parent() ).addClass( 'filters-opened' );
			button.html( searchScriptData.filterBtnOpen );
			filterContainter.slideToggle(
				'600',
				function() {
					if ( jQuery( filterContainter ).is( ':visible' ) ) {
						jQuery( button ).addClass( 'active' );
					} else {
						jQuery( button ).removeClass( 'active' );
						jQuery( button.parent().parent() ).removeClass( 'filters-opened' );
						button.html( searchScriptData.filterBtnClose );
					}
				}
			);
		}
	);

	// hide all the groupped inputs.
	jQuery( '.almwp-filter-group-inputs .filter-wrap' ).hide();
	// bind a change function to these select inputs.
	jQuery( '.almwp-filter-group-select select' ).change(
		function() {
			var options  = jQuery( this ).children();
			var selected = jQuery( this ).children( 'option:selected' ).val()
			jQuery( options ).each(
				function() {
					var item = jQuery( this ).val();
					if ( item === selected ) {
						jQuery( '.almwp-filter-wrap-' + selected ).show();
					} else {
						jQuery( '.almwp-filter-wrap-' + item ).hide();
					}
				}
			);

			jQuery( '.wsal-filter-wrap-' + selected ).show();
		}
	);
	// fire the a change on each of the input group selects.
	jQuery( '.almwp-filter-group-select select' ).each(
		function() {
			jQuery( this ).change();
		}
	);

	var submitButton       = document.getElementById( 'almwp-search-submit' );
	submitButton.outerHTML = submitButton.outerHTML.replace( /^\<input/, '<button' ) + submitButton.value + '</button>';
	jQuery( '#almwp-search-submit' ).addClass( 'dashicons-before dashicons-search' );
	jQuery( '#almwp-search-submit' ).attr( 'type', 'submit' );

	$( '#mwpal-search-box-search-input' ).focus(
		function() {
			$( '#almwp-search-submit' ).addClass( 'active' );
		}
	).blur(
		function() {
			$( '#almwp-search-submit' ).removeClass( 'active' );
		}
	);

	// Delay the filter change checker so it doesn't fire when initial filters
	// are loaded in.
	var filterNoticeDelay = window.setTimeout(
		function() {

			var filterNoticeSessionClosed = false;
			// Fire on change of the filters area.
			jQuery( 'body' ).on(
				'DOMSubtreeModified',
				'.mwpal-search-filters-list',
				function() {

					var filterNoticeZone = jQuery( '.almwp-filter-notice-zone' );
					if ( filterNoticeSessionClosed !== false || $( filterNoticeZone ).is( ':visible' ) ) {
						return;
					}
					jQuery( '.almwp-notice-message' ).html( searchScriptData.filterChangeMsg );
					jQuery( filterNoticeZone ).addClass( 'notice notice-error almwp-admin-notice is-dismissible' );
					jQuery( filterNoticeZone ).slideDown();
				}
			);

			jQuery( '.almwp-filter-notice-zone .notice-dismiss' ).click(
				function() {
					jQuery( this ).parent().slideUp();
					filterNoticeSessionClosed = true;
				}
			);

			jQuery( '#almwp-filter-notice-permanant-dismiss' ).click(
				function() {
					jQuery.ajax(
						{
							url : ajaxurl,
							type : 'POST',
							data : {
								notice : 'search-filters-changed',
								action : 'mwpal_user_notice_dismissed',
								nonce  : searchScriptData.security,
							},
							dataType : 'json'
						}
					);
					jQuery( this ).parent().parent().slideUp();
					filterNoticeSessionClosed = true;
				}
			);
		},
		500
	);

});

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
			format: searchScriptData.dateFormat
		}
	});
	jQuery( inputDate ).val( '' ).attr( 'autocomplete', 'off' );
}

/**
 * Add filter to filters list.
 *
 * @param {element} filterInput Filter input element.
 */
function addToFilterList( filterInput ) {
	let filterValue  = filterInput.val();
	let filterPrefix = filterInput.data( 'prefix' );

	if ( 0 === filterValue.length ) {
		return;
	}

	window.mwpalSearch.addFilter( `${filterPrefix}:${filterValue}` );
	filterInput.removeAttr( 'value' );
}
