<?php
/**
 * Gravity Forms extension class.
 *
 * @package    wsal
 * @subpackage add-ons
 */

declare(strict_types=1);

namespace WSAL\PluginExtensions;

if ( ! class_exists( '\WSAL\PluginExtensions\MemberPress_Extension' ) ) {
	/**
	 * Class provides basic information about WSAL extension for Gravity Forms.
	 *
	 * @package    wsal
	 * @subpackage add-ons
	 *
	 * @since 4.5.0
	 */
	class MemberPress_Extension {
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
					'addon_for'          => 'memberpress',
					'title'              => self::get_plugin_name(),
					'image_filename'     => 'memberpress.png',
					'plugin_slug'        => self::get_plugin_filename(),
					'plugin_basename'    => 'wsal-memberpress.php',
					'plugin_url'         => 'https://downloads.wordpress.org/plugin/activity-log-memberpress.latest-stable.zip',
					'event_tab_id'       => '#cat-memberpress',
					'plugin_description' => __( 'Keep a record of when someone adds, modifies or deletes Memerships, Groups, Rules and more in the MemberPress plugin.', 'wp-security-audit-log' ),
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
				'memberpress' => array(
					'name'      => self::get_plugin_name(),
					'event_ids' => array( 6200, 6201, 6202, 6203, 6204, 6205, 6206, 6207, 6208, 6210, 6211, 6212, 6250, 6251, 6252, 6253, 6254, 6255 ),
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
			return array( 'memberpressproduct', 'memberpressgroup', 'memberpressrule' );
		}

		/**
		 * Retrieves a plugin name.
		 *
		 * @return string Plugin name.
		 *
		 * @since 4.5.0
		 */
		public static function get_plugin_name(): string {
			return 'MemberPress';
		}

		/**
		 * Retrieves a plugin name.
		 *
		 * @return string Plugin name.
		 *
		 * @since 4.5.0
		 */
		public static function get_plugin_icon_url(): string {
			return 'https://ps.w.org/activity-log-memberpress/assets/icon-128x128.png?rev=2465070';
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
			return 'activity-log-memberpress/wsal-memberpress.php';
		}
	}
}
