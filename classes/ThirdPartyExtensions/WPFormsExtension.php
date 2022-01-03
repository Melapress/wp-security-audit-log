<?php

if ( ! class_exists( 'WSAL_WPFormsExtension' ) ) {

	class WSAL_WPFormsExtension extends WSAL_AbstractExtension {

		public function filter_installable_plugins( $plugins ) {
			$new_plugin = array(
				array(
					'addon_for'          => 'wpforms',
					'title'              => $this->get_plugin_name(),
					'image_filename'     => 'wpforms.png',
					'plugin_slug'        => 'wp-security-audit-log-add-on-for-wpforms/wsal-wpforms.php',
					'plugin_basename'    => 'wsal-wpforms.php',
					'plugin_url'         => 'https://downloads.wordpress.org/plugin/wp-security-audit-log-add-on-for-wpforms.latest-stable.zip',
					'event_tab_id'       => '#tab-wpforms',
					'plugin_description' => 'Keep a record of when someone adds, modifies or deletes forms, entries and more in the WPForms plugin.',
				)
			);

			// combine the two arrays.
			return array_merge( $plugins, $new_plugin );
		}

		public function add_event_codes( $addon_event_codes ) {
			$new_event_codes = array(
				'wpforms' => array(
					'name'      => $this->get_plugin_name(),
					'event_ids' => array( 5500, 5501, 5502, 5503, 5504, 5505, 5506 ),
				),
			);

			// combine the two arrays.
			return array_merge( $addon_event_codes, $new_event_codes );
		}

		public function get_custom_post_types() {
			return [ 'wpforms' ];
		}

		public function get_plugin_name() {
			return 'WPForms';
		}

		public function get_plugin_icon_url() {
			return 'https://ps.w.org/wp-security-audit-log-add-on-for-wpforms/assets/icon-128x128.png?rev=2241926';
		}

		public function get_color() {
			return '#e27730';
		}
	}
}
