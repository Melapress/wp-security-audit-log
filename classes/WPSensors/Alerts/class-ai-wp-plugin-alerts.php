<?php
/**
 * Custom Alerts for the WordPress AI plugin.
 *
 * Class file for alert manager.
 *
 * @since 5.6.4
 *
 * @package Wsal
 * @subpackage Sensors
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors\Alerts;

use WSAL\MainWP\MainWP_Addon;
use WSAL\WP_Sensors\Helpers\AI_WP_Plugin_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Alerts\AI_WP_Plugin_Alerts' ) ) {
	/**
	 * Custom alerts for the WordPress AI plugin.
	 *
	 * @since 5.6.4
	 */
	class AI_WP_Plugin_Alerts {
		/**
		 * Returns the structure of the alerts for this plugin extension.
		 *
		 * @return array $alerts - Alerts array.
		 *
		 * @since 5.6.4
		 */
		public static function get_custom_alerts(): array {
			if ( ( \method_exists( AI_WP_Plugin_Helper::class, 'load_alerts_for_sensor' ) && AI_WP_Plugin_Helper::load_alerts_for_sensor() ) || MainWP_Addon::check_mainwp_plugin_active() ) {
				/**
				 * For the moment, we're adding this one in the 'WordPress & System' category.
				 * We may need to create a dedicated category when we get more events from this plugin.
				 */
				return array(
					esc_html__( 'WordPress & System', 'wp-security-audit-log' ) => array(
						esc_html__( 'System', 'wp-security-audit-log' ) => self::get_alerts_array(),
					),
				);
			}

			return array();
		}

		/**
		 * Returns array with all the events attached to the sensor.
		 *
		 * @return array $alerts - Alerts array.
		 *
		 * @since 5.6.4
		 */
		public static function get_alerts_array(): array {
			return array(
				6083 => array(
					6083,
					WSAL_HIGH,
					esc_html__( 'AI master switch enabled / disabled', 'wp-security-audit-log' ),
					/* translators: %EventVerb%: Enabled or Disabled. */
					esc_html__( '%EventVerb% the AI master switch in the AI plugin settings.', 'wp-security-audit-log' ),
					array(),
					array(),
					'system',
					'enabled',
				),
				6084 => array(
					6084,
					WSAL_MEDIUM,
					esc_html__( 'AI plugin feature enabled / disabled', 'wp-security-audit-log' ),

					/* translators: %EventVerb%: Enabled or Disabled; %FeatureName%: AI plugin feature name. */
					esc_html__( '%EventVerb% the %FeatureName% feature in the AI plugin settings.', 'wp-security-audit-log' ), // phpcs:ignore WordPress.WP.I18n.UnorderedPlaceholdersText
					array(
						esc_html__( 'Feature slug', 'wp-security-audit-log' ) => '%FeatureSlug%',
					),
					array(),
					'system',
					'enabled',
				),
			);
		}
	}
}
