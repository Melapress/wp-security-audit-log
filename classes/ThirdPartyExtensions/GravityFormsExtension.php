<?php
/**
 * Gravity Forms extension class.
 *
 * @package    wsal
 * @subpackage add-ons
 */

if ( ! class_exists( 'WSAL_GravityFormsExtension' ) ) {

	/**
	 * Class provides basic information about WSAL extension for Gravity Forms.
	 *
	 * @package    wsal
	 * @subpackage add-ons
	 */
	class WSAL_GravityFormsExtension extends WSAL_AbstractExtension {

		/**
		 * {@inheritDoc}
		 */
		public function filter_installable_plugins( $plugins ) {
			$new_plugin = array(
				array(
					'addon_for'          => 'gravityforms',
					'title'              => $this->get_plugin_name(),
					'image_filename'     => 'gravityforms.png',
					'plugin_slug'        => $this->get_plugin_filename(),
					'plugin_url'         => 'https://downloads.wordpress.org/plugin/activity-log-gravity-forms.latest-stable.zip',
					'event_tab_id'       => '#cat-gravity-forms',
					'plugin_description' => __( 'Keep a record of when someone adds, modifies or deletes forms, entries and more in the Gravity Forms plugin.', 'wp-security-audit-log' ),
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
				'gravityforms' => array(
					'name'      => $this->get_plugin_name(),
					'event_ids' => array( 5700, 5702, 5703, 5704, 5709, 5715, 5705, 5708, 5706, 5707, 5710, 5711, 5712, 5713, 5714, 5716 ),
				),
			);

			// combine the two arrays.
			return array_merge( $addon_event_codes, $new_event_codes );
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_plugin_name() {
			return 'Gravity Forms';
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_plugin_icon_url() {
			return 'https://ps.w.org/activity-log-gravity-forms/assets/icon-128x128.png?rev=2465070';
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
			return 'activity-log-gravity-forms/activity-log-gravity-forms.php';
		}
	}
}
