<?php
/**
 * Sensor: WooCommerce
 *
 * WooCommerce sensor file.
 *
 * @package Wsal
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors_Helpers;

use WSAL\Controllers\Alert_Manager;
use WSAL\WP_Sensors\Helpers\Woocommerce_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Plugin_Sensors\WooCommerce_Sensor_Helper_Third' ) ) {

	/**
	 * Support for WooCommerce Plugin.
	 *
	 * @package Wsal
	 */
	class WooCommerce_Sensor_Helper_Third {

		/**
		 * Holds the options names array to check for changes, along with the data manipulation rules.
		 *
		 * @var array
		 *
		 * @since 5.4.0
		 */
		private static $options_to_check = array(
			'woocommerce_manage_stock'            => array(
				'event_id'            => 9169,
				'function_to_convert' => '\wc_string_to_bool',

				'data_to_store'       => array(
					'EventType' => 'event_type',
				),
			),
			'woocommerce_hold_stock_minutes'      => array(
				'event_id'      => 9170,

				'data_to_store' => array(
					'old_value' => 'old_value',
					'new_value' => 'value',
				),
			),
			'woocommerce_notify_low_stock'        => array(
				'event_id'            => 9171,
				'function_to_convert' => '\wc_string_to_bool',

				'data_to_store'       => array(
					'EventType' => 'event_type',
				),
			),
			'woocommerce_notify_no_stock'         => array(
				'event_id'            => 9172,
				'function_to_convert' => '\wc_string_to_bool',

				'data_to_store'       => array(
					'EventType' => 'event_type',
				),
			),
			'woocommerce_stock_email_recipient'   => array(
				'event_id'      => 9173,

				'data_to_store' => array(
					'old_value' => 'old_value',
					'new_value' => 'value',
				),
			),
			'woocommerce_notify_low_stock_amount' => array(
				'event_id'      => 9174,

				'data_to_store' => array(
					'old_value' => 'old_value',
					'new_value' => 'value',
				),
			),
			'woocommerce_notify_no_stock_amount'  => array(
				'event_id'      => 9175,

				'data_to_store' => array(
					'old_value' => 'old_value',
					'new_value' => 'value',
				),
			),
			'woocommerce_hide_out_of_stock_items' => array(
				'event_id'            => 9176,
				'function_to_convert' => '\wc_string_to_bool',

				'data_to_store'       => array(
					'EventType' => 'event_type',
				),
			),
			'woocommerce_stock_format'            => array(
				'event_id'      => 9177,

				'data_to_store' => array(
					'old_value' => 'old_value',
					'new_value' => 'value',
				),
			),
		);

		/**
		 * Inits the class hooks.
		 *
		 * @return void
		 *
		 * @since 5.4.0
		 */
		public static function init() {
			if ( Woocommerce_Helper::is_woocommerce_active() ) {

				\add_action( 'updated_option', array( __CLASS__, 'settings_options_update' ), 10, 3 );

			}
		}

		/**
		 * Check the settings options for changes and trigger the appropriate event.
		 *
		 * @param string $option - The option name.
		 * @param mixed  $old_value - The previous value.
		 * @param mixed  $value - New value.
		 *
		 * @return void
		 *
		 * @since 5.4.0
		 */
		public static function settings_options_update( $option, $old_value, $value ) {
			if ( in_array( $option, array_keys( self::$options_to_check, true ) ) ) {

				if ( 9177 === self::$options_to_check[ $option ]['event_id'] ) {

					$woo_stock_display_titles = array(
						''           => __( 'Always show quantity remaining in stock e.g. "12 in stock"', 'wp-security-audit-log' ),
						'low_amount' => __( 'Only show quantity remaining in stock when low e.g. "Only 2 left in stock"', 'wp-security-audit-log' ),
						'no_amount'  => __( 'Never show quantity remaining in stock', 'wp-security-audit-log' ),
					);

					$old_value = $woo_stock_display_titles[ $old_value ];
					$value     = $woo_stock_display_titles[ $value ];
				}

				if ( $old_value !== $value ) {

					if ( isset( self::$options_to_check[ $option ]['function_to_convert'] ) ) {
						$event_type = self::$options_to_check[ $option ]['function_to_convert']( $value ) ? 'enabled' : 'disabled';
					}

					foreach ( self::$options_to_check[ $option ]['data_to_store'] as &$value_to_store ) {
						$value_to_store = ${$value_to_store};
					}
					unset( $value_to_store );

					Alert_Manager::trigger_event(
						self::$options_to_check[ $option ]['event_id'],
						self::$options_to_check[ $option ]['data_to_store']
					);
				}
			}
		}

	}
}
