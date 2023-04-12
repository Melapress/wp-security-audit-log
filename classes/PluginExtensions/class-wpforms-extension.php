<?php
/**
 * WP Forms extension class.
 *
 * @package    wsal
 * @subpackage add-ons
 */

declare(strict_types=1);

namespace WSAL\PluginExtensions;

if ( ! class_exists( '\WSAL\PluginExtensions\WPForms_Extension' ) ) {
	/**
	 * Class provides basic information about WSAL extension for WP Forms.
	 *
	 * @package    wsal
	 * @subpackage add-ons
	 */
	class WPForms_Extension {
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
					'addon_for'          => 'wpforms',
					'title'              => self::get_plugin_name(),
					'image_filename'     => 'wpforms.png',
					'plugin_slug'        => self::get_plugin_filename(),
					'plugin_basename'    => 'wsal-wpforms.php',
					'plugin_url'         => 'https://downloads.wordpress.org/plugin/wp-security-audit-log-add-on-for-wpforms.latest-stable.zip',
					'event_tab_id'       => '#cat-wpforms',
					'plugin_description' => 'Keep a record of when someone adds, modifies or deletes forms, entries and more in the WPForms plugin.',
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
				'wpforms' => array(
					'name'      => self::get_plugin_name(),
					'event_ids' => array( 5500, 5501, 5502, 5503, 5504, 5505, 5506 ),
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
		public static function get_custom_post_types(): array {
			return array( 'wpforms' );
		}

		/**
		 * Retrieves a plugin name.
		 *
		 * @return string Plugin name.
		 *
		 * @latest
		 */
		public static function get_plugin_name(): string {
			return 'WPForms';
		}

		/**
		 * Retrieves a plugin name.
		 *
		 * @return string Plugin name.
		 *
		 * @since 4.5.0
		 */
		public static function get_plugin_icon_url(): string {
			return 'https://ps.w.org/wp-security-audit-log-add-on-for-wpforms/assets/icon-128x128.png?rev=2241926';
		}

		/**
		 * Retrieves the color to use when showing some info about the extension.
		 *
		 * @return string HEX color.
		 *
		 * @since 4.5.0
		 */
		public static function get_color(): string {
			return '#e27730';
		}

		/**
		 * Gets the filename of the plugin this extension is targeting.
		 *
		 * @return string Filename.
		 *
		 * @since 4.5.0
		 */
		public static function get_plugin_filename(): string {
			return 'wp-security-audit-log-add-on-for-wpforms/wsal-wpforms.php';
		}
	}
}
