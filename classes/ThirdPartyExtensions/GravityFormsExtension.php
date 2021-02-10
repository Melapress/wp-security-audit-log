<?php

if ( ! class_exists( 'WSAL_GravityFormsExtension' ) ) {

	class WSAL_GravityFormsExtension extends WSAL_AbstractExtension {

		public function filter_installable_plugins( $plugins ) {
			$new_plugin = array(
				array(
					'addon_for'          => 'gravityforms',
					'title'              => __( 'Gravity Forms', 'wp-security-audit-log' ),
					'image_filename'     => 'gravityforms.png',
					'plugin_slug'        => 'activity-log-gravity-forms/activity-log-gravity-forms.php',
					'plugin_url'         => 'https://downloads.wordpress.org/plugin/activity-log-gravity-forms.latest-stable.zip',
					'event_tab_id'       => '#tab-gravity-forms',
					'plugin_description' => __( 'Keep a record of when someone adds, modifies or deletes forms, entries and more in the Gravity Forms plugin.', 'wp-security-audit-log' ),
				)
			);

			// combine the two arrays.
			$plugins = array_merge( $plugins, $new_plugin );
			return $plugins;
		}

		public function add_event_codes( $addon_event_codes ) {
			$new_event_codes = array(
				'yoast' => array(
					'name'      => __( 'Gravity Forms', 'wp-security-audit-log' ),
					'event_ids' => array( 5700, 5702, 5703, 5704, 5709, 5715, 5705, 5708, 5706, 5707, 5710, 5711, 5712, 5713, 5714, 5716 ),
				),
			);

			// combine the two arrays.
			$addon_event_codes = array_merge( $addon_event_codes, $new_event_codes );
			return $addon_event_codes;
		}
	}
}
