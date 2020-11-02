<?php

if ( ! class_exists( 'WSAL_AbstractExtension' ) ) {

	abstract class WSAL_AbstractExtension {

		public function __construct() {
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
	}
}
