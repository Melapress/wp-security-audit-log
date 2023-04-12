<?php
/**
 * Controller: Plugin Extensions.
 *
 * Plugin Extensions class file.
 *
 * @since     latest
 *
 * @package   wsal
 * @subpackage controllers
 */

declare(strict_types=1);

namespace WSAL\Controllers;

use WSAL\Helpers\Classes_Helper;

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
			add_filter( 'wsal_filter_installable_plugins', array( __CLASS__, 'filter_installable_plugins' ), 10, 1 );
			add_filter( 'wsal_addon_event_codes', array( __CLASS__, 'add_event_codes' ), 10, 1 );

			foreach ( self::get_extensions() as $extension ) {
				// Check if that sensor is for login or not.
				if ( method_exists( $extension, 'init' ) ) {
					call_user_func_array( array( $extension, 'init' ), array() );
				}
			}
		}

		/**
		 * Add our extensions event IDs to the array of available events
		 *
		 * @param array $addon_event_codes Current extension/addon events.
		 *
		 * @return array
		 *
		 * @since 4.5.0
		 */
		public static function add_event_codes( $addon_event_codes ) {
			foreach ( self::get_extensions() as $extension ) {
				// Check if that sensor is for login or not.
				if ( method_exists( $extension, 'add_event_codes' ) ) {
					$extension_event_codes = call_user_func_array( array( $extension, 'add_event_codes' ), array( $addon_event_codes ) );

					$addon_event_codes = array_merge( $addon_event_codes, $extension_event_codes );
				}
			}

			// combine the two arrays.
			return $addon_event_codes;
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
