<?php
/**
 * Controller: Plugin Extensions.
 *
 * Plugin Extensions class file.
 *
 * @since     4.6.0
 *
 * @package   wsal
 * @subpackage controllers
 */

declare(strict_types=1);

namespace WSAL\Controllers;

use WSAL\Helpers\Classes_Helper;
use WSAL\Actions\Plugin_Installer;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Controllers\Plugin_Extensions' ) ) {

	/**
	 * Provides plugin extensions functionality.
	 *
	 * @since 4.5.0
	 */
	class Plugin_Extensions {

		/**
		 * All the extensions classes
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		private static $extensions = array();

		/**
		 * Holds the extensions post types
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		private static $post_types_map = null;

		/**
		 * Inits the class variables.
		 *
		 * @return void
		 *
		 * @since 4.5.0
		 */
		public static function init() {
			foreach ( self::get_extensions() as $extension ) {
				// Check if that sensor is for login or not.
				if ( method_exists( $extension, 'init' ) ) {
					call_user_func_array( array( $extension, 'init' ), array() );
				}
			}
		}

		/**
		 * Add our extension to the array of installable extensions.
		 *
		 * @param array $plugins Array of installable plugins.
		 *
		 * @return array
		 *
		 * @since 4.5.0
		 */
		public static function filter_installable_plugins( $plugins ) {
			foreach ( self::get_extensions() as $extension ) {
				// Check if that sensor is for login or not.
				if ( method_exists( $extension, 'filter_installable_plugins' ) ) {
					$plugin = call_user_func_array( array( $extension, 'filter_installable_plugins' ), array( $plugins ) );

					$plugins = array_merge( $plugins, $plugin );
				}
			}

			// combine the two arrays.
			return $plugins;
		}

		/**
		 * Retrieves an extension class associated with given pst type.
		 *
		 * @param string $post_type Post type.
		 *
		 * @return mixed
		 *
		 * @since 4.5.0
		 */
		public static function get_extension_for_post_type( $post_type ) {
			if ( is_null( self::$post_types_map ) ) {
				self::$post_types_map = array();
				if ( ! empty( self::get_extensions() ) ) {
					foreach ( self::get_extensions() as $extension ) {
						$post_types = array();
						if ( method_exists( $extension, 'get_custom_post_types' ) ) {
							$post_types = call_user_func_array( array( $extension, 'get_custom_post_types' ), array() );
						}

						if ( ! empty( $post_types ) ) {
							foreach ( $post_types as $_post_type ) {
								self::$post_types_map[ $_post_type ] = $extension;
							}
						}
					}
				}
			}

			if ( array_key_exists( $post_type, self::$post_types_map ) ) {
				return self::$post_types_map[ $post_type ];
			}

			return null;
		}

		/**
		 * Deactivates all of the WSAL plugins.
		 *
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function deactivate_plugins() {
			$plugins = Classes_Helper::get_classes_by_namespace( '\WSAL\WP_Sensors\Helpers' );

			foreach ( $plugins as $plugin ) {
				if ( method_exists( $plugin, 'get_plugin_filename' ) ) {
					Plugin_Installer::deactivate_plugin( call_user_func_array( array( $plugin, 'get_plugin_filename' ), array() ) );
				}
			}
		}

		/**
		 * Returns all the extensions classes (if the local cache var is empty - first collects them)
		 *
		 * @return array
		 *
		 * @since 4.5.0
		 */
		private static function get_extensions(): array {
			if ( empty( self::$extensions ) ) {
				self::$extensions = Classes_Helper::get_classes_by_namespace( '\WSAL\PluginExtensions' );
			}

			return self::$extensions;
		}
	}
}
