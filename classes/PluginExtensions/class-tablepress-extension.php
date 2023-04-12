<?php
/**
 * TablePress extension class.
 *
 * @package    wsal
 * @subpackage add-ons
 */

declare(strict_types=1);

namespace WSAL\PluginExtensions;

if ( ! class_exists( '\WSAL\PluginExtensions\TablePress_Extension' ) ) {
	/**
	 * Class provides basic information about WSAL extension for TablePress.
	 *
	 * @package    wsal
	 * @subpackage add-ons
	 *
	 * @since 4.5.0
	 */
	class TablePress_Extension {
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
					'addon_for'          => 'tablepress',
					'title'              => self::get_plugin_name(),
					'image_filename'     => 'tablepress.png',
					'plugin_slug'        => self::get_plugin_filename(),
					'plugin_basename'    => 'tablepress.php',
					'plugin_url'         => 'https://downloads.wordpress.org/plugin/activity-log-tablepress.latest-stable.zip',
					'event_tab_id'       => '#cat-tablepress',
					'plugin_description' => 'Keep a log of all the changes in your TablePress tables.',
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
				'tablepress' => array(
					'name'      => self::get_plugin_name(),
					'event_ids' => array( 8900, 8901, 8902, 8903, 8904, 8905, 8906, 8907, 8908 ),
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
			return array( 'tablepress_table' );
		}

		/**
		 * Retrieves a plugin name.
		 *
		 * @return string Plugin name.
		 *
		 * @since 4.5.0
		 */
		public static function get_plugin_name(): string {
			return 'TablePress';
		}

		/**
		 * Retrieves a plugin name.
		 *
		 * @return string Plugin name.
		 *
		 * @since 4.5.0
		 */
		public static function get_plugin_icon_url(): string {
			return 'https://ps.w.org/activity-log-tablepress/assets/icon-128x128.png?rev=2393849';
		}

		/**
		 * Retrieves the color to use when showing some info about the extension.
		 *
		 * @return string HEX color.
		 *
		 * @since 4.5.0
		 */
		public static function get_color(): string {
			return '#a4286a';
		}

		/**
		 * Gets the filename of the plugin this extension is targeting.
		 *
		 * @return string Filename.
		 *
		 * @since 4.5.0
		 */
		public static function get_plugin_filename(): string {
			return 'activity-log-tablepress/wsal-tablepress.php';
		}
	}
}
