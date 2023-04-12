<?php
/**
 * The bbPress extension class.
 *
 * @package    wsal
 * @subpackage add-ons
 */

declare(strict_types=1);

namespace WSAL\PluginExtensions;

if ( ! class_exists( '\WSAL\PluginExtensions\BBPress_Extension' ) ) {
	/**
	 * Class provides basic information about WSAL extension for bbPress.
	 *
	 * @package    wsal
	 * @subpackage add-ons
	 *
	 * @since 4.5.0
	 */
	class BBPress_Extension {

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
					'addon_for'          => 'bbpress',
					'title'              => self::get_plugin_name(),
					'image_filename'     => 'bbpress.png',
					'plugin_slug'        => self::get_plugin_filename(),
					'plugin_basename'    => 'wsal-bbpress.php',
					'plugin_url'         => 'https://downloads.wordpress.org/plugin/wp-security-audit-log-add-on-for-bbpress.latest-stable.zip',
					'event_tab_id'       => '#cat-bbpress-forums',
					'plugin_description' => 'Keep a log of your sites bbPress activity, from forum and topic creation, user profile changes and more.',
				),
			);

			// combine the two arrays.
			return $new_plugin;
		}

		/**
		 * Add our extensions event IDs to the array of available events
		 *
		 * @return array
		 *
		 * @since 4.5.0
		 */
		public static function add_event_codes(): array {
			$new_event_codes = array(
				'bbpress' => array(
					'name'      => self::get_plugin_name(),
					'event_ids' => array( 8000, 8001, 8002, 8003, 8004, 8005, 8006, 8007, 8008, 8009, 8010, 8011, 8012, 8013, 8014, 8015, 8016, 8017, 8018, 8019, 8020, 8021, 8022, 8023 ),
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
			return array( 'forum', 'topic', 'reply' );
		}

		/**
		 * Retrieves a plugin name.
		 *
		 * @return string Plugin name.
		 *
		 * @latest
		 */
		public static function get_plugin_name(): string {
			return 'bbPress';
		}

		/**
		 * Gets a plugin icon URL.
		 *
		 * @return string Plugin icon URL.
		 *
		 * @latest
		 */
		public static function get_plugin_icon_url(): string {
			return 'https://ps.w.org/wp-security-audit-log-add-on-for-bbpress/assets/icon-128x128.png?rev=2253395';
		}

		/**
		 * Retrieves the color to use when showing some info about the extension.
		 *
		 * @return string HEX color.
		 *
		 * @since 4.5.0
		 */
		public static function get_color(): string {
			return '#8dc770';
		}

		/**
		 * Gets the filename of the plugin this extension is targeting.
		 *
		 * @return string Filename.
		 *
		 * @since 4.5.0
		 */
		public static function get_plugin_filename(): string {
			return 'wp-security-audit-log-add-on-for-bbpress/wsal-bbpress.php';
		}
	}
}
