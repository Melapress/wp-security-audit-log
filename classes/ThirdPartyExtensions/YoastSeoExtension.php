<?php
/**
 * Yoast SEO extension class.
 *
 * @package    wsal
 * @subpackage add-ons
 */

if ( ! class_exists( 'WSAL_YoastSeoExtension' ) ) {

	/**
	 * Class provides basic information about WSAL extension for Yoast SEO.
	 *
	 * @package    wsal
	 * @subpackage add-ons
	 */
	class WSAL_YoastSeoExtension extends WSAL_AbstractExtension {

		/**
		 * {@inheritDoc}
		 */
		public function filter_installable_plugins( $plugins ) {
			$new_plugin = array(
				array(
					'addon_for'          => 'wp-seo',
					'title'              => $this->get_plugin_name(),
					'image_filename'     => 'yoast.png',
					'plugin_slug'        => $this->get_plugin_filename(),
					'plugin_basename'    => 'activity-log-wp-seo.php',
					'plugin_url'         => 'https://downloads.wordpress.org/plugin/activity-log-wp-seo.latest-stable.zip',
					'event_tab_id'       => '#cat-yoast-seo',
					'plugin_description' => 'Keep a log of all the changes that you and your team do in the Yoast SEO metabox, plugin settings & much more.',
				),
			);

			// combine the two arrays.
			return array_merge( $plugins, $new_plugin );
		}

		/**
		 * {@inheritDoc}
		 */
		public function add_event_codes( $addon_event_codes ) {
			$new_event_codes = array(
				'yoast' => array(
					'name'      => $this->get_plugin_name(),
					'event_ids' => array( 8801, 8802, 8803, 8804, 8805, 8806, 8807, 8808, 8850, 8851, 8852, 8809, 8810, 8811, 8812, 8815, 8816, 8817, 8818, 8819, 8820, 8821, 8822, 8824, 8825, 8826, 8827, 8828, 8829, 8838, 8839, 8840, 8842, 8843, 8813, 8814, 8830, 8831, 8832, 8833, 8834, 8835, 8836, 8837, 8853, 8854, 8841, 8844, 8845, 8846, 8847, 8848, 8855, 8856, 8857, 8858 ),
				),
			);

			// combine the two arrays.
			return array_merge( $addon_event_codes, $new_event_codes );
		}

		/**
		 * {@inheritDoc}
		 */
		public function modify_predefined_plugin_slug( $plugin ) {
			// Correct yoast addon.
			if ( 'yoast' === $plugin ) {
				return 'wp-seo';
			}

			return $plugin;
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_plugin_name() {
			return 'Yoast SEO';
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_plugin_icon_url() {
			return 'https://ps.w.org/activity-log-wp-seo/assets/icon-128x128.png?rev=2393849';
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_color() {
			return '#a4286a';
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_plugin_filename() {
			return 'activity-log-wp-seo/activity-log-wp-seo.php';
		}
	}
}
