<?php

if ( ! class_exists( 'WSAL_TablePressExtension' ) ) {

	class WSAL_TablePressExtension extends WSAL_AbstractExtension {

		public function filter_installable_plugins( $plugins ) {
			$new_plugin = array(
				array(
					'addon_for'          => 'tablepress',
					'title'              => $this->get_plugin_name(),
					'image_filename'     => 'tablepress.png',
					'plugin_slug'        => 'tablepress/tablepress.php',
					'plugin_basename'    => 'tablepress.php',
					'plugin_url'         => 'https://downloads.wordpress.org/plugin/activity-log-tablepress.latest-stable.zip',
					'event_tab_id'       => '#tab-tablepress',
					'plugin_description' => 'Keep a log of all the changes in your TablePress tables.',
				)
			);

			// combine the two arrays.
			return array_merge( $plugins, $new_plugin );
		}

		public function add_event_codes( $addon_event_codes ) {
			$new_event_codes = array(
				'yoast' => array(
					'name'      => $this->get_plugin_name(),
					'event_ids' => array( 8900, 8901, 8902, 8903, 8904, 8905, 8906, 8907, 8908 ),
				),
			);

			// combine the two arrays.
			return array_merge( $addon_event_codes, $new_event_codes );
		}

		public function get_custom_post_types() {
			return [ 'tablepress_table' ];
		}

		public function get_plugin_name() {
			return 'TablePress';
		}

		public function get_plugin_icon_url() {
			return 'https://ps.w.org/activity-log-wp-seo/assets/icon-128x128.png?rev=2393849';
		}

		public function get_color() {
			return '#a4286a';
		}
	}
}
