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

    abstract public function filter_installable_plugins( $plugins );

    abstract public function add_event_codes( $addon_event_codes );

		public function modify_predefined_plugin_slug( $plugin ) {
			return $plugin;
		}
	}
}
