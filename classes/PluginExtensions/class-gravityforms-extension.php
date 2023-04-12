<?php
/**
 * Gravity Forms extension class.
 *
 * @package    wsal
 * @subpackage add-ons
 */

declare(strict_types=1);

namespace WSAL\PluginExtensions;

if ( ! class_exists( '\WSAL\PluginExtensions\GravityForms_Extension' ) ) {
	/**
	 * Class provides basic information about WSAL extension for Gravity Forms.
	 *
	 * @package    wsal
	 * @subpackage add-ons
	 *
	 * @since 4.5.0
	 */
	class GravityForms_Extension {
		/**
		 * Add our extension to the array of installable extensions.
		 *
		 * @return array
		 *
		 * @since 4.5.0
		 */
		public static function filter_installable_plugins(): array {
			$new_plugin = array(
				array(
					'addon_for'          => 'gravityforms',
					'title'              => self::get_plugin_name(),
					'image_filename'     => 'gravityforms.png',
					'plugin_slug'        => self::get_plugin_filename(),
					'plugin_url'         => 'https://downloads.wordpress.org/plugin/activity-log-gravity-forms.latest-stable.zip',
					'event_tab_id'       => '#cat-gravity-forms',
					'plugin_description' => __( 'Keep a record of when someone adds, modifies or deletes forms, entries and more in the Gravity Forms plugin.', 'wp-security-audit-log' ),
				),
			);

			// combine the two arrays.
			return $new_plugin;
		}

		/**
		 * Add our extensions event IDs to the array of available events.
		 *
		 * @return array
		 *
		 * @since 4.5.0
		 */
		public static function add_event_codes(): array {
			$new_event_codes = array(
				'gravityforms' => array(
					'name'      => self::get_plugin_name(),
					'event_ids' => array( 5700, 5702, 5703, 5704, 5709, 5715, 5705, 5708, 5706, 5707, 5710, 5711, 5712, 5713, 5714, 5716 ),
				),
			);

			// combine the two arrays.
			return $new_event_codes;
		}

		/**
		 * Returns a list of custom post types associated with particular extension.
		 *
		 * @return array List of custom post types.
		 *
		 * @since 4.5.0
		 */
		public static function get_plugin_name(): string {
			return 'Gravity Forms';
		}

		/**
		 * Retrieves a plugin name.
		 *
		 * @return string Plugin name.
		 *
		 * @since 4.5.0
		 */
		public static function get_plugin_icon_url(): string {
			return 'https://ps.w.org/activity-log-gravity-forms/assets/icon-128x128.png?rev=2465070';
		}

		/**
		 * Retrieves the color to use when showing some info about the extension.
		 *
		 * @return string HEX color.
		 *
		 * @since 4.5.0
		 */
		public static function get_color(): string {
			return '#F15A29';
		}

		/**
		 * Gets the filename of the plugin this extension is targeting.
		 *
		 * @return string Filename.
		 *
		 * @since 4.5.0
		 */
		public static function get_plugin_filename(): string {
			return 'activity-log-gravity-forms/activity-log-gravity-forms.php';
		}
	}
}
