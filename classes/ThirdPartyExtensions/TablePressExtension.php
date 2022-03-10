<?php
/**
 * TablePress extension class.
 *
 * @package    wsal
 * @subpackage add-ons
 */

if ( ! class_exists( 'WSAL_TablePressExtension' ) ) {

	/**
	 * Class provides basic information about WSAL extension for TablePress.
	 *
	 * @package    wsal
	 * @subpackage add-ons
	 */
	class WSAL_TablePressExtension extends WSAL_AbstractExtension {

		/**
		 * {@inheritDoc}
		 */
		public function filter_installable_plugins( $plugins ) {
			$new_plugin = array(
				array(
					'addon_for'          => 'tablepress',
					'title'              => $this->get_plugin_name(),
					'image_filename'     => 'tablepress.png',
					'plugin_slug'        => $this->get_plugin_filename(),
					'plugin_basename'    => 'tablepress.php',
					'plugin_url'         => 'https://downloads.wordpress.org/plugin/activity-log-tablepress.latest-stable.zip',
					'event_tab_id'       => '#cat-tablepress',
					'plugin_description' => 'Keep a log of all the changes in your TablePress tables.',
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
					'event_ids' => array( 8900, 8901, 8902, 8903, 8904, 8905, 8906, 8907, 8908 ),
				),
			);

			// combine the two arrays.
			return array_merge( $addon_event_codes, $new_event_codes );
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_custom_post_types() {
			return array( 'tablepress_table' );
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_plugin_name() {
			return 'TablePress';
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_plugin_icon_url() {
			return 'https://ps.w.org/activity-log-tablepress/assets/icon-128x128.png?rev=2393849';
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
			return 'activity-log-tablepress/wsal-tablepress.php';
		}
	}
}
