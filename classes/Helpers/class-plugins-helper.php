<?php
/**
 * Responsible for the PLugins core functionalities.
 *
 * @package    wsal
 * @subpackage helpers
 *
 * @since 4.5.0
 *
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WSAL\Helpers;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/*
 * WP helper class
 */
if ( ! class_exists( '\WSAL\Helpers\Plugins_Helper' ) ) {
	/**
	 * All the Plugins functionality must go trough this class.
	 *
	 * @since 4.5.0
	 */
	class Plugins_Helper {

		/**
		 * Caching all the installable plugins.
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		private static $installable_plugins = array();

		/**
		 * Checks if the plugin is already available/installed on the site.
		 *
		 * @method is_plugin_installed
		 *
		 * @since 4.6.0
		 *
		 * @param string $plugin_slug installed plugin slug.
		 *
		 * @return void|bool
		 */
		public static function is_plugin_installed( $plugin_slug = '' ) {
			// bail early if we don't have a slug to work with.
			if ( empty( $plugin_slug ) ) {
				return;
			}

			// check if the slug is in the installable list.
			$is_allowed_slug = false;
			$allowed_plugins = self::get_installable_plugins();
			if ( is_array( $allowed_plugins ) ) {
				foreach ( $allowed_plugins as $allowed_plugin ) {
					// if we already found an allowed slug then break.
					if ( true === $is_allowed_slug ) {
						break;
					}
					$is_allowed_slug = isset( $allowed_plugin['plugin_slug'] ) && $allowed_plugin['plugin_slug'] === $plugin_slug;
				}
			}

			// bail early if this is not an allowed plugin slug.
			if ( ! $is_allowed_slug ) {
				return;
			}

			// get core plugin functions if they are not already in runtime.
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$all_plugins = get_plugins();

			if ( ! empty( $all_plugins[ $plugin_slug ] ) ) {
				return true;
			} else {
				return false;
			}
		}

		/**
		 * Get a list of the data for the plugins that are allowable.
		 *
		 * @method get_installable_plugins
		 *
		 * @since 4.6.0
		 */
		public static function get_installable_plugins() {
			if ( empty( self::$installable_plugins ) ) {
				self::$installable_plugins = array(
					array(
						'addon_for'   => 'wfcm',
						'title'       => 'Website File Changes Monitor',
						'plugin_slug' => 'website-file-changes-monitor/website-file-changes-monitor.php',
						'plugin_url'  => 'https://downloads.wordpress.org/plugin/website-file-changes-monitor.latest-stable.zip',
					),
				);
			}

			return self::$installable_plugins;
		}
	}
}
