<?php
/**
 * Gravity Forms extension class.
 *
 * @package    wsal
 * @subpackage add-ons
 */

if ( ! class_exists( 'WSAL_MemberPressExtension' ) ) {

	/**
	 * Class provides basic information about WSAL extension for Gravity Forms.
	 *
	 * @package    wsal
	 * @subpackage add-ons
	 */
	class WSAL_MemberPressExtension extends WSAL_AbstractExtension {

		/**
		 * {@inheritDoc}
		 */
		public function filter_installable_plugins( $plugins ) {
			$new_plugin = array(
				array(
					'addon_for'          => 'memberpress',
					'title'              => $this->get_plugin_name(),
					'image_filename'     => 'memberpress.png',
					'plugin_slug'        => $this->get_plugin_filename(),
					'plugin_basename'    => 'wsal-memberpress.php',
					'plugin_url'         => 'https://downloads.wordpress.org/plugin/activity-log-memberpress.latest-stable.zip',
					'event_tab_id'       => '#cat-memberpress',
					'plugin_description' => __( 'Keep a record of when someone adds, modifies or deletes Memerships, Groups, Rules and more in the MemberPress plugin.', 'wp-security-audit-log' ),
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
				'memberpress' => array(
					'name'      => $this->get_plugin_name(),
					'event_ids' => array( 6200, 6201, 6202, 6203 ,6204, 6205, 6206, 6207, 6208, 6210, 6211, 6212, 6250, 6251, 6252, 6253, 6254, 6255 ),
				),
			);

			// combine the two arrays.
			return array_merge( $addon_event_codes, $new_event_codes );
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_plugin_name() {
			return 'MemberPress';
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_plugin_icon_url() {
			return 'https://ps.w.org/activity-log-memberpress/assets/icon-128x128.png?rev=2465070';
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_color() {
			return '#F15A29';
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_plugin_filename() {
			return 'activity-log-memberpress/wsal-memberpress.php';
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_custom_post_types() {
			return array( 'memberpressproduct', 'memberpressgroup', 'memberpressrule' );
		}
	}
}
