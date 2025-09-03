<?php
/**
 * Plugin & Themes Sensor helper.
 *
 * @since     5.5.0
 *
 * @package   wsal
 * @subpackage sensors
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors\Helpers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Helpers\WP_Plugins_Themes_Helper' ) ) {

	/**
	 * Helper Sensor class for Tablepress.
	 *
	 * @package    wsal
	 * @subpackage sensors-helpers
	 *
	 * @since      5.5.0
	 */
	class WP_Plugins_Themes_Helper {

		/**
		 * Trim the name of a plugin or theme folder, in case it's not already a clean folder name.
		 *
		 * @param string $folder_name The folder name to trim.
		 *
		 * @return string|false The trimmed folder name or false if invalid.
		 *
		 * @since 5.5.0
		 */
		public static function trim_folder_name( $folder_name ) {
			$folder_name = trim( (string) $folder_name, "/\\ \t\n\r\0\x0B" );

			if ( '' === $folder_name ) {
				return false;
			}

			return $folder_name;
		}

		/**
		 * Checks if a plugin/theme directory or if a plugin file exists with a given name.
		 *
		 * @param string $name - The name of the folder or file to check.
		 * @param string $type The type of the item to check, either 'plugin' or 'theme'. Default is 'plugin'.
		 *
		 * @return bool - Returns true if the plugin directory or file exists, false otherwise.
		 *
		 * @since 5.5.0
		 */
		public static function does_dir_or_file_exist( $name, $type = 'plugin' ) {

			if ( 'plugin' === $type ) {
				$plugin_root = defined( 'WP_PLUGIN_DIR' )
				? \untrailingslashit( WP_PLUGIN_DIR )
				: \untrailingslashit( WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'plugins' );

				$is_plugin_dir  = is_dir( $plugin_root . DIRECTORY_SEPARATOR . $name );
				$is_plugin_file = file_exists( $plugin_root . DIRECTORY_SEPARATOR . $name . '.php' );

				return $is_plugin_dir || $is_plugin_file;
			}

			if ( 'theme' === $type ) {
				// Get the theme using wp_get_theme.
				$theme = \wp_get_theme( $name );
				if ( $theme->exists() ) {
						return true;
				}
			}

			return false;
		}

		/**
		 * Get the plugin information for an event from a folder name.
		 *
		 * @param mixed $folder - the folder name to get the plugin information from.
		 *
		 * @return array|bool - array with theme information or false if not found.
		 *
		 * @since 5.5.0
		 */
		public static function get_plugin_event_info_from_folder( $folder ) {

			$folder = self::trim_folder_name( $folder );

			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$plugins = \get_plugins( DIRECTORY_SEPARATOR . $folder );

			if ( ! empty( $plugins ) ) {
				$first_slug = array_key_first( $plugins );
				$first      = $plugins[ $first_slug ];

				$plugin_event_data = array(
					'Name'            => $first['Name'] ?? false,
					'Version'         => $first['Version'] ?? false,
					'PluginURI'       => $first['Plugin URI'] ?? false,
					'Author'          => $first['Author'] ?? false,
					'Network'         => $first['Network'] ?? false,
					'Slug'            => $first_slug,
					'Title'           => $first['Title'] ?? ( $first['Name'] ?? false ),
					'plugin_dir_path' => WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $first_slug,
				);

				$required_keys   = array( 'Name', 'Version', 'plugin_dir_path' );
				$values          = array_intersect_key( $plugin_event_data, array_flip( $required_keys ) );
				$has_keys_values = count( $values ) === count( $required_keys ) && ! in_array( '', $values, true );

				if ( ! $has_keys_values ) {
					return false;
				}

				return $plugin_event_data;
			}

			$single = \untrailingslashit( WP_PLUGIN_DIR ) . DIRECTORY_SEPARATOR . $folder . '.php';

			if ( file_exists( $single ) ) {
				$data = \get_file_data(
					$single,
					array(
						'Name'        => 'Plugin Name',
						'Version'     => 'Version',
						'PluginURI'   => 'Plugin URI',
						'Author'      => 'Author',
						'Network'     => 'Network',
						'Title'       => 'Title',
						'Description' => 'Description',
						'TextDomain'  => 'Text Domain',
					)
				);

				return array(
					'Name'            => $data['Name'] ?? false,
					'Version'         => $data['Version'] ?? false,
					'PluginURI'       => $data['PluginURI'] ?? false,
					'Author'          => $data['Author'] ?? false,
					'Network'         => $data['Network'] ?? false,
					'Slug'            => $folder,
					'Title'           => $data['Title'] ?? ( $data['Name'] ?? false ),
					'plugin_dir_path' => WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $slug,
				);
			}

			return false;
		}

		/**
		 * Get the theme information for an event from a folder name.
		 *
		 * @param mixed $folder - the folder name to get the theme information from.
		 *
		 * @return array|bool - array with theme information or false if not found.
		 *
		 * @since 5.5.0
		 */
		public static function get_theme_event_info_from_folder( $folder ) {

			$folder = self::trim_folder_name( $folder );

			if ( ! function_exists( 'wp_get_theme' ) ) {
				require_once ABSPATH . 'wp-includes/theme.php';
			}

			$theme = \wp_get_theme( $folder );

			if ( ! $theme->exists() ) {
				return false;
			}

			$theme_event_data = array(
				'Name'                   => $theme->get( 'Name' ) ?? false,
				'ThemeURI'               => $theme->get( 'ThemeURI' ) ?? false,
				'Description'            => $theme->get( 'Description' ) ?? false,
				'Author'                 => $theme->get( 'Author' ) ?? false,
				'Version'                => $theme->get( 'Version' ) ?? false,
				'get_template_directory' => $theme->get_template_directory(),
			);

			$required_keys   = array( 'Name', 'Version', 'get_template_directory' );
			$values          = array_intersect_key( $theme_event_data, array_flip( $required_keys ) );
			$has_keys_values = count( $values ) === count( $required_keys ) && ! in_array( '', $values, true );

			if ( ! $has_keys_values ) {
				return false;
			}

			return $theme_event_data;
		}
	}
}
