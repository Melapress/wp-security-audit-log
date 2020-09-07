<?php

if ( ! class_exists( 'WSAL_BBPressExtension' ) ) {

	class WSAL_BBPressExtension extends WSAL_AbstractExtension {

		public function filter_installable_plugins( $plugins ) {
			$new_plugin = array(
				array(
					'addon_for'          => 'bbpress',
					'title'              => 'BBPress',
					'image_filename'     => 'bbpress.png',
					'plugin_slug'        => 'wp-security-audit-log-add-on-for-bbpress/wsal-bbpress.php',
					'plugin_url'         => 'https://downloads.wordpress.org/plugin/wp-security-audit-log-add-on-for-bbpress.latest-stable.zip',
					'event_tab_id'       => '#tab-bbpress-forums',
					'plugin_description' => 'Keep a log of your sites bbPress activity, from forum and topic creation, user profile changes and more.',
				)
			);

			// combine the two arrays.
			$plugins = array_merge( $plugins, $new_plugin );
			return $plugins;
		}

		public function add_event_codes( $addon_event_codes ) {
			$new_event_codes = array(
				'bbpress' => array(
					'name'      => __( 'BBPress', 'wp-security-audit-log' ),
					'event_ids' => array( 8000, 8001, 8002, 8003, 8004, 8005, 8006, 8007, 8008, 8009, 8010, 8011, 8012, 8013, 8014, 8015, 8016, 8017, 8018, 8019, 8020, 8021, 8022, 8023 ),
				),
			);

			// combine the two arrays.
			$addon_event_codes = array_merge( $addon_event_codes, $new_event_codes );
			return $addon_event_codes;
		}

		public function modify_predefined_plugin_slug( $plugin ) {
			// Purposefully here to avoid "WSAL_BBPressExtension contains 1 abstract
			// method and must therefore be declared abstract or implement the remaining methods".
			return $plugin;
		}
	}
}
