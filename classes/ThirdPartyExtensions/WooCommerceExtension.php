<?php

if ( ! class_exists( 'WSAL_WooCommerceExtension' ) ) {

	class WSAL_WooCommerceExtension extends WSAL_AbstractExtension {

		public function __construct() {
			parent::__construct();
			add_filter( 'wsal_save_settings_disabled_events', array( $this, 'save_settings_disabled_events' ), 10, 4 );
		}

		public function filter_installable_plugins( $plugins ) {
			$new_plugin = array(
				array(
					'addon_for'          => 'woocommerce',
					'title'              => 'WooCommerce',
					'image_filename'     => 'woocommerce.png',
					'plugin_slug'        => 'wp-activity-log-for-woocommerce/wsal-woocommerce.php',
					'plugin_basename'    => 'wsal-woocommerce.php',
					'plugin_url'         => 'https://downloads.wordpress.org/plugin/wp-activity-log-for-woocommerce.latest-stable.zip',
					'event_tab_id'       => '#tab-woocommerce',
					'plugin_description' => 'Keep a log of your team\'s store settings, products, orders, coupons and any other changes they might do on your eCommerce store.',
				),
			);

			// combine the two arrays.
			$plugins = array_merge( $plugins, $new_plugin );
			return $plugins;
		}

		public function add_event_codes( $addon_event_codes ) {
			$new_event_codes = array(
				'woocommerce' => array(
					'name'      => __( 'WooCommerce', 'wp-security-audit-log' ),
					'event_ids' => array( 9000, 9001, 9003, 9004, 9005, 9006, 9007, 9008, 9009, 9010, 9011, 9012, 9013, 9014, 9015, 9072, 9073, 9077, 9016, 9017, 9018, 9019, 9020, 9021, 9022, 9023, 9024, 9025, 9026, 9042, 9043, 9044, 9045, 9046, 9105, 9047, 9048, 9049, 9050, 9051, 9027, 9028, 9029, 9030, 9031, 9032, 9033, 9034, 9085, 9086, 9087, 9088, 9089, 9090, 9091, 9092, 9093, 9094, 9074, 9075, 9076, 9078, 9079, 9080, 9081, 9082, 9002, 9052, 9053, 9054, 9055, 9056, 9057, 9058, 9059, 9060, 9061, 9062, 9063, 9064, 9065, 9066, 9067, 9068, 9069, 9070, 9071, 9035, 9036, 9037, 9038, 9039, 9040, 9041, 9083, 9084, 9101, 9102, 9103, 9104 ),
				),
			);

			// combine the two arrays.
			$addon_event_codes = array_merge( $addon_event_codes, $new_event_codes );
			return $addon_event_codes;
		}

		/**
		 * Further process the $_POST data upon saving events in the ToggleAlerts view.
		 *
		 * @param  array  $disabled          Empty array which we will fill if needed.
		 * @param  object $registered_alerts Currently registered alerts.
		 * @param  array  $frontend_events   Array of currently enabled frontend events, taken from POST data.
		 * @param  array  $enabled           Currently enabled events.
		 *
		 * @return array                     Disabled events.
		 */
		public function save_settings_disabled_events( $disabled, $registered_alerts, $frontend_events, $enabled ) {
			// Now we check all registered events for further processing.
			foreach ( $registered_alerts as $alert ) {

				// Disable Visitor events if the user disabled the event there are "tied to" in the UI.
				if ( ! in_array( $alert->code, $enabled, true ) ) {
					if ( 9035 === $alert->code ) {
						$frontend_events = array_merge( $frontend_events, array( 'woocommerce' => false ) );
						WSAL_Settings::set_frontend_events( $frontend_events );
					}
					$disabled[] = $alert->code;
				}
			}

			return $disabled;
		}
	}
}
