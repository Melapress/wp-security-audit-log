<?php
/**
 * AI sensor helper.
 *
 * @since 5.6.4
 *
 * @package Wsal
 * @subpackage Sensors
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors\Helpers;

use WSAL\Helpers\WP_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Helpers\AI_WP_Plugin_Helper' ) ) {
	/**
	 * Helper class for AI plugin sensors.
	 *
	 * @package Wsal
	 * @subpackage Sensors
	 *
	 * @since 5.6.4
	 */
	class AI_WP_Plugin_Helper {
		/**
		 * AI plugin basename.
		 *
		 * @var string
		 *
		 * @since 5.6.4
		 */
		private const AI_PLUGIN_BASENAME = 'ai/ai.php';

		/**
		 * Class cache to store the AI plugin active state.
		 *
		 * @var bool|null
		 *
		 * @since 5.6.4
		 */
		private static $plugin_active = null;

		/**
		 * Class cache to store the AI plugin active state for sensors.
		 *
		 * @var bool|null
		 *
		 * @since 5.6.4
		 */
		private static $plugin_active_for_sensors = null;

		/**
		 * Checks if the AI plugin is active.
		 *
		 * @return bool $plugin_active - True if the AI plugin is active.
		 *
		 * @since 5.6.4
		 */
		public static function is_ai_plugin_active(): bool {
			if ( null === self::$plugin_active ) {
				self::$plugin_active = WP_Helper::is_plugin_active( self::AI_PLUGIN_BASENAME );
			}

			return self::$plugin_active;
		}

		/**
		 * Checks if the AI plugin alerts should be loaded.
		 *
		 * @return bool $plugin_active_for_sensors - True if the AI plugin is active.
		 *
		 * @since 5.6.4
		 */
		public static function load_alerts_for_sensor(): bool {
			if ( null === self::$plugin_active_for_sensors ) {
				self::$plugin_active_for_sensors = WP_Helper::is_plugin_active( self::AI_PLUGIN_BASENAME );
			}

			return self::$plugin_active_for_sensors;
		}

		/**
		 * Gets the AI feature slug from an option name.
		 *
		 * @param mixed $option_name - Option name.
		 *
		 * @return string $feature_slug - Feature slug or empty string.
		 *
		 * @since 5.6.4
		 */
		public static function get_feature_slug_from_option_name( $option_name ): string {
			if ( ! is_string( $option_name ) ) {
				return '';
			}

			$feature_option_prefix = 'wpai_feature_';
			$feature_option_suffix = '_enabled';

			$prefix_length = strlen( $feature_option_prefix );
			$suffix_length = strlen( $feature_option_suffix );

			if ( 0 !== strpos( $option_name, $feature_option_prefix ) ) {
				return '';
			}

			if ( substr( $option_name, -$suffix_length ) !== $feature_option_suffix ) {
				return '';
			}

			$feature_slug = substr(
				$option_name,
				$prefix_length,
				-$suffix_length
			);

			if ( ! is_string( $feature_slug ) || '' === $feature_slug ) {
				return '';
			}

			return \sanitize_text_field( $feature_slug );
		}

		/**
		 * Checks if an option name belongs to the AI plugin master switch.
		 *
		 * @param mixed $option_name - Option name.
		 *
		 * @return bool $is_master_switch_option - True if this is the master switch option.
		 *
		 * @since 5.6.4
		 */
		public static function is_master_switch_option_name( $option_name ): bool {
			if ( ! is_string( $option_name ) ) {
				return false;
			}

			return 'wpai_features_enabled' === $option_name;
		}

		/**
		 * Gets a readable AI feature name from a slug.
		 *
		 * @param string $feature_slug - Feature slug.
		 *
		 * @return string $feature_name - Feature name.
		 *
		 * @since 5.6.4
		 */
		public static function get_feature_name_from_slug( string $feature_slug ): string {
			return \sanitize_text_field( ucwords( str_replace( array( '-', '_' ), ' ', $feature_slug ) ) );
		}
	}
}
