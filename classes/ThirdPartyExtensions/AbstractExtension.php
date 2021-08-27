<?php

if ( ! class_exists( 'WSAL_AbstractExtension' ) ) {

	abstract class WSAL_AbstractExtension {

		/**
		 * @var WSAL_AbstractExtension[]
		 * @since 4.3.2
		 */
		private static $extensions = [];

		/**
		 * @var WSAL_AbstractExtension[]
		 * @since 4.3.2
		 */
		private static $post_types_map;

		/**
		 * @param string $post_type
		 *
		 * @return WSAL_AbstractExtension|null
		 * @since 4.3.2
		 */
		public static function get_extension_for_post_type( $post_type ) {
			if ( is_null( self::$post_types_map ) ) {
				self::$post_types_map = [];
				if ( ! empty( self::$extensions ) ) {
					foreach ( self::$extensions as $extension ) {
						$post_types = $extension->get_custom_post_types();
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
		 * WSAL_AbstractExtension constructor.
		 *
		 */
		public function __construct() {
			array_push( self::$extensions, $this );
			$this->add_filters();
		}

		public function add_filters() {
			add_filter( 'wsal_filter_installable_plugins', array( $this, 'filter_installable_plugins' ), 10, 1 );
			add_filter( 'wsal_addon_event_codes', array( $this, 'add_event_codes' ), 10, 1 );
			add_filter( 'wsal_modify_predefined_plugin_slug', array( $this, 'modify_predefined_plugin_slug' ), 10, 1 );
		}

		/**
		 * Add our extension to the array of installable extensions.
		 *
		 * @param array $plugins Array of installable plugins.
		 *
		 * @return array
		 */
		abstract public function filter_installable_plugins( $plugins );

		/**
		 * Add our extensions event IDs to the array of available events
		 *
		 * @param array $addon_event_codes Current extension/addon events.
		 *
		 * @return array
		 */
		abstract public function add_event_codes( $addon_event_codes );

		/**
		 * Correct plugin slug depending on the context.
		 *
		 * @param string $plugin Current slug to alter.
		 *
		 * @return string         Modified slug.
		 */
		public function modify_predefined_plugin_slug( $plugin ) {
			return $plugin;
		}

		/**
		 * Returns a list of custom post types associated with particular extension.
		 *
		 * @return array List of custom post types.
		 * @since 4.3.2
		 */
		public function get_custom_post_types() {
			return [];
		}

		abstract public function get_plugin_name();

		abstract public function get_plugin_icon_url();

		abstract public function get_color();

	}
}
