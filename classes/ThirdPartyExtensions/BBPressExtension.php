<?php
/**
 * The bbPress extension class.
 *
 * @package    wsal
 * @subpackage add-ons
 */

if ( ! class_exists( 'WSAL_BBPressExtension' ) ) {

	/**
	 * Class provides basic information about WSAL extension for bbPress.
	 *
	 * @package    wsal
	 * @subpackage add-ons
	 */
	class WSAL_BBPressExtension extends WSAL_AbstractExtension {

		/**
		 * {@inheritDoc}
		 */
		public function filter_installable_plugins( $plugins ) {
			$new_plugin = array(
				array(
					'addon_for'          => 'bbpress',
					'title'              => $this->get_plugin_name(),
					'image_filename'     => 'bbpress.png',
					'plugin_slug'        => $this->get_plugin_filename(),
					'plugin_basename'    => 'wsal-bbpress.php',
					'plugin_url'         => 'https://downloads.wordpress.org/plugin/wp-security-audit-log-add-on-for-bbpress.latest-stable.zip',
					'event_tab_id'       => '#cat-bbpress-forums',
					'plugin_description' => 'Keep a log of your sites bbPress activity, from forum and topic creation, user profile changes and more.',
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
				'bbpress' => array(
					'name'      => $this->get_plugin_name(),
					'event_ids' => array( 8000, 8001, 8002, 8003, 8004, 8005, 8006, 8007, 8008, 8009, 8010, 8011, 8012, 8013, 8014, 8015, 8016, 8017, 8018, 8019, 8020, 8021, 8022, 8023 ),
				),
			);

			// combine the two arrays.
			return array_merge( $addon_event_codes, $new_event_codes );
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_custom_post_types() {
			return array( 'forum', 'topic', 'reply' );
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_plugin_name() {
			return 'bbPress';
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_plugin_icon_url() {
			return 'https://ps.w.org/wp-security-audit-log-add-on-for-bbpress/assets/icon-128x128.png?rev=2253395';
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_color() {
			return '#8dc770';
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_plugin_filename() {
			return 'wp-security-audit-log-add-on-for-bbpress/wsal-bbpress.php';
		}
	}
}
