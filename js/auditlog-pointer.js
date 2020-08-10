/**
 * WSAL Audit Log Pointer.
 *
 * @since 3.2
 */
jQuery(document).ready( function($) {
	wsal_open_pointer(0);
	function wsal_open_pointer(i) {
		pointer = wsalPointer.pointers[i];
		options = $.extend( pointer.options, {
			close: function() {
				$.post( ajaxurl, {
					pointer: pointer.pointer_id,
					action: 'wsal_dismiss_wp_pointer'
				});
			}
		});

		$(pointer.target).first().pointer( options ).pointer('open');
	}
});
