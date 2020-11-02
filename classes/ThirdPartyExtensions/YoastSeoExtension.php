<?php

if ( ! class_exists( 'WSAL_YoastSeoExtension' ) ) {

	class WSAL_YoastSeoExtension extends WSAL_AbstractExtension {

		public function filter_installable_plugins( $plugins ) {
			$new_plugin = array(
				array(
					'addon_for'          => 'wp-seo',
					'title'              => 'Yoast SEO',
					'image_filename'     => 'yoast.png',
					'plugin_slug'        => 'activity-log-wp-seo/activity-log-wp-seo.php',
					'plugin_basename'    => 'activity-log-wp-seo.php',
					'plugin_url'         => 'https://downloads.wordpress.org/plugin/activity-log-wp-seo.latest-stable.zip',
					'event_tab_id'       => '#tab-yoast-seo',
					'plugin_description' => 'Keep a log of all the changes that you and your team do in the Yoast SEO metabox, plugin settings & much more.',
				)
			);

			// combine the two arrays.
			$plugins = array_merge( $plugins, $new_plugin );
			return $plugins;
		}

		public function add_event_codes( $addon_event_codes ) {
			$new_event_codes = array(
				'yoast' => array(
					'name'      => __( 'Yoast SEO', 'wp-security-audit-log' ),
					'event_ids' => array( 8801, 8802, 8803, 8804, 8805, 8806, 8807, 8808, 8809, 8810, 8811, 8812, 8813, 8814, 8815, 8816, 8817, 8818, 8819, 8820, 8821, 8822, 8823, 8824, 8825 ),
				),
			);

			// combine the two arrays.
			$addon_event_codes = array_merge( $addon_event_codes, $new_event_codes );
			return $addon_event_codes;
		}

		public function modify_predefined_plugin_slug( $plugin ) {
			// Correct yoast addon.
			if ( 'yoast' === $plugin ) {
				$plugin = 'wp-seo';
			}

			return $plugin;
		}
	}
}
