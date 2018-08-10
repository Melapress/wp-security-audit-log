/**
 * WSAL Wizard
 *
 * Entry file for webpack.
 *
 * @since 3.2.3
 */

// Wizard styles.
import '../../css/src/wsal-wizard.scss';

import jQuery from 'jquery';

jQuery( document ).ready( function() {

	/**
	 * Step: `Access`
	 *
	 * Usernames & roles access setting.
	 */
	jQuery( '#editor-users-add, #editor-roles-add, #exuser-query-add, #exrole-query-add, #ipaddr-query-add' ).click( function() {
		const type = jQuery( this ).attr( 'id' ).substr( 0, 6 );
		const tokenType = jQuery( this ).attr( 'id' ).substr( 7, 5 );
		const tokenValue = jQuery.trim( jQuery( `#${type}-${tokenType}-box` ).val() );
		const existing = jQuery( `#${type}-list input` ).filter( function() {
			return this.value === tokenValue;
		});

		if ( ! tokenValue || existing.length ) {
			return;
		} // if value is empty or already used, stop here

		// checkDataToken( 'user' );
		jQuery( `#${type}-${tokenType}-box, #${type}-${tokenType}-add` ).attr( 'disabled', true );

		jQuery.ajax({
			type: 'POST',
			url: wsalData.ajaxURL,
			async: true,
			data: {
				action: 'setup_check_security_token',
				token: tokenValue,
				nonce: wsalData.nonce
			},
			dataType: 'json',
			success: function( data ) {

				// Remove disabled attribute.
				jQuery( `#${type}-${tokenType}-box, #${type}-${tokenType}-add` ).removeAttr( 'disabled' );
				jQuery( `#${type}-${tokenType}-box` ).val( '' );

				if ( data.success ) {

					// Error handling.
					if ( 'other' === data.tokenType && ( 'users' === tokenType || 'exuser' === type ) ) {
						alert( wsalData.usersError );
						return;
					} else if ( 'other' === data.tokenType && ( 'roles' === tokenType || 'exrole' === type ) ) {
						alert( wsalData.rolesError );
						return;
					} else if ( 'other' === data.tokenType && ( 'ip' === tokenType || 'ipaddr' === type ) ) {
						alert( wsalData.ipError );
						return;
					}

					jQuery( `#${type}-list` ).append( jQuery( `<span class="sectoken-${data.tokenType}"/>` ).text( data.token ).append(
						jQuery( `<input type="hidden" name="${type}s[]"/>` ).val( data.token ),
						jQuery( '<a href="javascript:;" title="Remove">&times;</a>' ).click( removeSecToken )
					) );
				} else {
					alert( data.message );
				}
			},
			error: function( xhr, textStatus, error ) {
				console.log( xhr.statusText );
				console.log( textStatus );
				console.log( error );
			}
		});
	});

	jQuery( '#editor-users-box, #editor-roles-box, #exuser-query-box, #exrole-query-box, #ipaddr-query-box' ).keydown( function( event ) {
		if ( 13 === event.keyCode ) {
			const type = jQuery( this ).attr( 'id' ).substr( 0, 6 );
			const tokenType = jQuery( this ).attr( 'id' ).substr( 7, 5 );
			jQuery( `#${type}-${tokenType}-add` ).click();
			return false;
		}
	});

	/**
	 * Remove access settings token.
	 */
	jQuery( '#editor-list>span>a, #exuser-list>span>a, #exrole-list>span>a, #ipaddr-list>span>a' ).click( removeSecToken );
	function removeSecToken() {
		const token = jQuery( this ).parents( 'span:first' );
		token.addClass( 'sectoken-del' ).fadeOut( 'fast', function() {
			token.remove();
		});
	};
});

