<?php
/**
 * Sensor: AI Plugin Activity.
 *
 * AI plugin activity sensor class file.
 *
 * @since 5.6.4
 *
 * @package Wsal
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors;

use WSAL\Helpers\Settings_Helper;
use WSAL\Controllers\Alert_Manager;
use WSAL\WP_Sensors\Helpers\AI_WP_Plugin_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\AI_WP_Plugin_Sensor' ) ) {
	/**
	 * Custom sensor for the WordPress AI plugin.
	 *
	 * @since 5.6.4
	 */
	class AI_WP_Plugin_Sensor {
		/**
		 * Feature option values before deletion.
		 *
		 * @var array<string, mixed>
		 *
		 * @since 5.6.4
		 */
		private static $deleted_feature_options = array();

		/**
		 * Master switch option values before deletion.
		 *
		 * @var array<string, mixed>
		 *
		 * @since 5.6.4
		 */
		private static $deleted_master_switch_options = array();

		/**
		 * Listening to events using WP hooks.
		 *
		 * @return void
		 *
		 * @since 5.6.4
		 */
		public static function init() {
			if ( AI_WP_Plugin_Helper::is_ai_plugin_active() ) {
				\add_action( 'updated_option', array( __CLASS__, 'updated_option' ), 10, 3 );
				\add_action( 'added_option', array( __CLASS__, 'added_option' ), 10, 2 );
				\add_action( 'delete_option', array( __CLASS__, 'delete_option' ), 10, 1 );
				\add_action( 'deleted_option', array( __CLASS__, 'deleted_option' ), 10, 1 );
			}
		}

		/**
		 * Tracks updated AI feature toggle options.
		 *
		 * @param mixed $option_name - Option name.
		 * @param mixed $old_value - Old option value.
		 * @param mixed $new_value - New option value.
		 *
		 * @return void
		 *
		 * @since 5.6.4
		 */
		public static function updated_option( $option_name, $old_value, $new_value ) {
			if ( AI_WP_Plugin_Helper::is_master_switch_option_name( $option_name ) ) {
				self::maybe_trigger_master_switch_updated_event( $old_value, $new_value );

				return;
			}

			$feature_slug = AI_WP_Plugin_Helper::get_feature_slug_from_option_name( $option_name );

			if ( '' === $feature_slug ) {
				return;
			}

			$old_enabled = Settings_Helper::string_to_bool( $old_value );
			$new_enabled = Settings_Helper::string_to_bool( $new_value );

			if ( $old_enabled === $new_enabled ) {
				return;
			}

			self::trigger_feature_status_event( $feature_slug, $new_enabled );
		}

		/**
		 * Tracks added AI feature toggle options.
		 *
		 * @param mixed $option_name - Option name.
		 * @param mixed $new_value - New option value.
		 *
		 * @return void
		 *
		 * @since 5.6.4
		 */
		public static function added_option( $option_name, $new_value ) {
			if ( AI_WP_Plugin_Helper::is_master_switch_option_name( $option_name ) ) {
				self::maybe_trigger_master_switch_added_event( $new_value );

				return;
			}

			$feature_slug = AI_WP_Plugin_Helper::get_feature_slug_from_option_name( $option_name );

			if ( '' === $feature_slug ) {
				return;
			}

			if ( ! Settings_Helper::string_to_bool( $new_value ) ) {
				return;
			}

			self::trigger_feature_status_event( $feature_slug, true );
		}

		/**
		 * Stores AI feature toggle option values before deletion.
		 *
		 * @param mixed $option_name - Option name.
		 *
		 * @return void
		 *
		 * @since 5.6.4
		 */
		public static function delete_option( $option_name ) {
			if ( AI_WP_Plugin_Helper::is_master_switch_option_name( $option_name ) && is_string( $option_name ) ) {
				self::$deleted_master_switch_options[ $option_name ] = \get_option( $option_name );

				return;
			}

			$feature_slug = AI_WP_Plugin_Helper::get_feature_slug_from_option_name( $option_name );

			if ( '' === $feature_slug || ! is_string( $option_name ) ) {
				return;
			}

			self::$deleted_feature_options[ $option_name ] = \get_option( $option_name );
		}

		/**
		 * Tracks deleted AI feature toggle options.
		 *
		 * @param mixed $option_name - Option name.
		 *
		 * @return void
		 *
		 * @since 5.6.4
		 */
		public static function deleted_option( $option_name ) {
			if ( AI_WP_Plugin_Helper::is_master_switch_option_name( $option_name ) && is_string( $option_name ) && array_key_exists( $option_name, self::$deleted_master_switch_options ) ) {
				$old_value = self::$deleted_master_switch_options[ $option_name ];
				unset( self::$deleted_master_switch_options[ $option_name ] );

				if ( Settings_Helper::string_to_bool( $old_value ) ) {
					self::trigger_master_switch_status_event( false );
				}

				return;
			}

			if ( ! is_string( $option_name ) || ! array_key_exists( $option_name, self::$deleted_feature_options ) ) {
				return;
			}

			$old_value = self::$deleted_feature_options[ $option_name ];
			unset( self::$deleted_feature_options[ $option_name ] );

			if ( ! Settings_Helper::string_to_bool( $old_value ) ) {
				return;
			}

			$feature_slug = AI_WP_Plugin_Helper::get_feature_slug_from_option_name( $option_name );

			if ( '' === $feature_slug ) {
				return;
			}

			self::trigger_feature_status_event( $feature_slug, false );
		}

		/**
		 * Tracks updated AI master switch option.
		 *
		 * @param mixed $old_value - Old option value.
		 * @param mixed $new_value - New option value.
		 *
		 * @return void
		 *
		 * @since 5.6.4
		 */
		private static function maybe_trigger_master_switch_updated_event( $old_value, $new_value ) {
			$old_enabled = Settings_Helper::string_to_bool( $old_value );
			$new_enabled = Settings_Helper::string_to_bool( $new_value );

			if ( $old_enabled === $new_enabled ) {
				return;
			}

			self::trigger_master_switch_status_event( $new_enabled );
		}

		/**
		 * Tracks added AI master switch option.
		 *
		 * @param mixed $new_value - New option value.
		 *
		 * @return void
		 *
		 * @since 5.6.4
		 */
		private static function maybe_trigger_master_switch_added_event( $new_value ) {
			if ( ! Settings_Helper::string_to_bool( $new_value ) ) {
				return;
			}

			self::trigger_master_switch_status_event( true );
		}

		/**
		 * Triggers the AI master switch status event.
		 *
		 * @param bool $enabled - Whether the master switch was enabled.
		 *
		 * @return void
		 *
		 * @since 5.6.4
		 */
		private static function trigger_master_switch_status_event( bool $enabled ) {
			$event_type = $enabled ? 'enabled' : 'disabled';

			Alert_Manager::trigger_event(
				6083,
				array(
					'EventType' => $event_type,
					'EventVerb' => Alert_Manager::get_event_type_data( $event_type ),
				)
			);
		}

		/**
		 * Triggers the AI feature status event.
		 *
		 * @param string $feature_slug - Feature slug.
		 * @param bool   $enabled - Whether the feature was enabled.
		 *
		 * @return void
		 *
		 * @since 5.6.4
		 */
		private static function trigger_feature_status_event( string $feature_slug, bool $enabled ) {
			$event_type   = $enabled ? 'enabled' : 'disabled';
			$feature_name = AI_WP_Plugin_Helper::get_feature_name_from_slug( $feature_slug );

			Alert_Manager::trigger_event(
				6084,
				array(
					'EventType'   => $event_type,
					'EventVerb'   => Alert_Manager::get_event_type_data( $event_type ),
					'FeatureName' => $feature_name,
					'FeatureSlug' => $feature_slug,
				)
			);
		}
	}
}
