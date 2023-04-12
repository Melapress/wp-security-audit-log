<?php
/**
 * Woocommerce extension class.
 *
 * @package    wsal
 * @subpackage add-ons
 */

declare(strict_types=1);

namespace WSAL\PluginExtensions;

use WSAL\Helpers\Settings_Helper;

if ( ! class_exists( '\WSAL\PluginExtensions\WooCommerce_Extension' ) ) {
	/**
	 * Class provides basic information about WSAL extension for WooCommerce.
	 *
	 * @package    wsal
	 * @subpackage add-ons
	 */
	class WooCommerce_Extension {
		/**
		 * Inits the extension hooks.
		 *
		 * @return void
		 *
		 * @since 4.5.0
		 */
		public static function init() {
			add_filter( 'wsal_save_settings_disabled_events', array( __CLASS__, 'save_settings_disabled_events' ), 10, 4 );
		}

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
					'addon_for'          => 'woocommerce',
					'title'              => self::get_plugin_name(),
					'image_filename'     => 'woocommerce.png',
					'plugin_slug'        => self::get_plugin_filename(),
					'plugin_basename'    => 'wsal-woocommerce.php',
					'plugin_url'         => 'https://downloads.wordpress.org/plugin/wp-activity-log-for-woocommerce.latest-stable.zip',
					'event_tab_id'       => '#cat-woocommerce',
					'plugin_description' => 'Keep a log of your team\'s store settings, products, orders, coupons and any other changes they might do on your eCommerce store.',
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
				'woocommerce' => array(
					'name'      => self::get_plugin_name(),
					'event_ids' => array( 9000, 9001, 9003, 9004, 9005, 9006, 9007, 9008, 9009, 9010, 9011, 9012, 9013, 9014, 9015, 9072, 9073, 9077, 9016, 9017, 9018, 9019, 9020, 9021, 9022, 9023, 9024, 9025, 9026, 9042, 9043, 9044, 9045, 9046, 9105, 9047, 9048, 9049, 9050, 9051, 9027, 9028, 9029, 9030, 9031, 9032, 9033, 9034, 9085, 9086, 9087, 9088, 9089, 9090, 9091, 9092, 9093, 9094, 9074, 9075, 9076, 9078, 9079, 9080, 9081, 9082, 9002, 9052, 9053, 9054, 9055, 9056, 9057, 9058, 9059, 9060, 9061, 9062, 9063, 9064, 9065, 9066, 9067, 9068, 9069, 9070, 9071, 9035, 9036, 9037, 9038, 9039, 9040, 9041, 9083, 9084, 9101, 9102, 9103, 9104 ),
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
			return array(
				'product',
				'shop_coupon',
				'shop_order',
				'shop_order_refund',
				'product_variation',
				'wc_product_tab',
			);
		}

		/**
		 * Retrieves a plugin name.
		 *
		 * @return string Plugin name.
		 *
		 * @latest
		 */
		public static function get_plugin_name(): string {
			return 'WooCommerce';
		}

		/**
		 * Retrieves a plugin name.
		 *
		 * @return string Plugin name.
		 *
		 * @since 4.5.0
		 */
		public static function get_plugin_icon_url(): string {
			return 'https://ps.w.org/wp-activity-log-for-woocommerce/assets/icon-128x128.png?rev=2357550';
		}

		/**
		 * Retrieves the color to use when showing some info about the extension.
		 *
		 * @return string HEX color.
		 *
		 * @since 4.5.0
		 */
		public static function get_color(): string {
			return '#7f54b3';
		}

		/**
		 * Gets the filename of the plugin this extension is targeting.
		 *
		 * @return string Filename.
		 *
		 * @since 4.5.0
		 */
		public static function get_plugin_filename(): string {
			return 'wp-activity-log-for-woocommerce/wsal-woocommerce.php';
		}

		/**
		 * Further process the $_POST data upon saving events in the ToggleAlerts view.
		 *
		 * @param array  $disabled          Empty array which we will fill if needed.
		 * @param object $registered_alerts Currently registered alerts.
		 * @param array  $frontend_events   Array of currently enabled frontend events, taken from POST data.
		 * @param array  $enabled           Currently enabled events.
		 *
		 * @return array Disabled events.
		 */
		public static function save_settings_disabled_events( $disabled, $registered_alerts, $frontend_events, $enabled ) {
			// Now we check all registered events for further processing.
			foreach ( $registered_alerts as $alert ) {
				// Disable Visitor events if the user disabled the event there are "tied to" in the UI.
				if ( ! in_array( $alert['code'], $enabled, true ) ) {
					if ( 9035 === $alert['code'] ) {
						$frontend_events = array_merge( $frontend_events, array( 'woocommerce' => false ) );
						Settings_Helper::set_frontend_events( $frontend_events );
					}
					$disabled[] = $alert['code'];
				}
			}

			return $disabled;
		}
	}
}
