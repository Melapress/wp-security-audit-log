<?php
/**
 * WP AI connectors helper.
 *
 * @since 5.6.4
 *
 * @package Wsal
 * @subpackage Sensors
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors\Helpers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Helpers\WP_AI_Connectors_Helper' ) ) {
	/**
	 * Helper class for WP AI connector sensors.
	 *
	 * @package Wsal
	 * @subpackage Sensors
	 *
	 * @since 5.6.4
	 */
	class WP_AI_Connectors_Helper {
		/**
		 * Gets the AI connector slug from an option name.
		 *
		 * @param mixed $option - Option name.
		 *
		 * @return string $connector_slug - Connector slug or empty string.
		 *
		 * @since 5.6.4
		 */
		public static function get_connector_slug_from_option_name( $option ): string {
			if ( ! is_string( $option ) ) {
				return '';
			}

			$option_prefix = 'connectors_ai_';
			$option_suffix = '_api_key';

			if ( 0 !== strpos( $option, $option_prefix ) ) {
				return '';
			}

			if ( substr( $option, -strlen( $option_suffix ) ) !== $option_suffix ) {
				return '';
			}

			$connector_slug = substr(
				$option,
				strlen( $option_prefix ),
				-strlen( $option_suffix )
			);

			if ( '' === $connector_slug ) {
				return '';
			}

			return \sanitize_text_field( $connector_slug );
		}

		/**
		 * Checks if an AI connector API key option has a value.
		 *
		 * @param mixed $value - Option value.
		 *
		 * @return bool $key_set - True when the API key option has a value.
		 *
		 * @since 5.6.4
		 */
		public static function is_connector_api_key_set( $value ): bool {
			if ( is_string( $value ) ) {
				return '' !== trim( $value );
			}

			if ( is_scalar( $value ) ) {
				return '' !== trim( (string) $value );
			}

			return ! empty( $value );
		}

		/**
		 * Gets the AI connector enabled status for an option change.
		 *
		 * @param string $option_action - Option action.
		 * @param mixed  $old_value     - The old option value.
		 * @param mixed  $new_value     - The new option value.
		 *
		 * @return bool|null $connected - True if connected, false if disconnected, null if no event should be triggered.
		 *
		 * @since 5.6.4
		 */
		public static function is_ai_connector_enabled( string $option_action, $old_value = null, $new_value = null ) {
			if ( 'added' === $option_action ) {
				if ( ! self::is_connector_api_key_set( $new_value ) ) {
					return null;
				}

				return true;
			}

			if ( 'updated' === $option_action ) {
				$old_key_set = self::is_connector_api_key_set( $old_value );
				$new_key_set = self::is_connector_api_key_set( $new_value );

				if ( $old_key_set === $new_key_set ) {
					return null;
				}

				return $new_key_set;
			}

			if ( 'deleted' === $option_action ) {
				if ( ! self::is_connector_api_key_set( $old_value ) ) {
					return null;
				}

				return false;
			}

			return null;
		}

		/**
		 * Gets event data for an AI connector option.
		 *
		 * @param string $option - Option name.
		 *
		 * @return array $connector_event_data - Connector event data.
		 *
		 * @since 5.6.4
		 */
		public static function get_connector_event_data( string $option ): array {
			$connector_slug = self::get_connector_slug_from_option_name( $option );

			if ( '' === $connector_slug ) {
				return array();
			}

			if ( ! function_exists( 'wp_get_connectors' ) ) {
				return array();
			}

			try {
				$connectors = \wp_get_connectors();
			} catch ( \Throwable $exception ) {
				return array();
			}

			if ( ! is_array( $connectors ) ) {
				return array();
			}

			foreach ( $connectors as $connector_data ) {
				if ( ! is_array( $connector_data ) ) {
					continue;
				}

				$authentication = $connector_data['authentication'] ?? array();

				if ( ! is_array( $authentication ) ) {
					continue;
				}

				if ( ( $authentication['setting_name'] ?? '' ) !== $option ) {
					continue;
				}

				if ( 'api_key' !== ( $authentication['method'] ?? '' ) ) {
					continue;
				}

				if ( 'ai_provider' !== ( $connector_data['type'] ?? '' ) ) {
					continue;
				}

				return array(
					'name'   => self::get_connector_name( $connector_data, $connector_slug ),
					'plugin' => self::get_connector_plugin( $connector_data ),
				);
			}

			return array();
		}

		/**
		 * Gets a readable AI connector name.
		 *
		 * @param array  $connector_data - Connector data.
		 * @param string $connector_slug - Connector slug.
		 *
		 * @return string $connector_name - Connector name.
		 *
		 * @since 5.6.4
		 */
		public static function get_connector_name( array $connector_data, string $connector_slug ): string {
			if ( ! empty( $connector_data['name'] ) && is_string( $connector_data['name'] ) ) {
				return \sanitize_text_field( $connector_data['name'] );
			}

			return \sanitize_text_field( ucwords( str_replace( array( '-', '_' ), ' ', $connector_slug ) ) );
		}

		/**
		 * Gets the provider plugin for an AI connector.
		 *
		 * @param array $connector_data - Connector data.
		 *
		 * @return string $connector_plugin - Connector plugin file.
		 *
		 * @since 5.6.4
		 */
		public static function get_connector_plugin( array $connector_data ): string {
			$plugin = $connector_data['plugin'] ?? array();

			if ( ! is_array( $plugin ) ) {
				return \__( 'Unknown', 'wp-security-audit-log' );
			}

			if ( empty( $plugin['file'] ) || ! is_string( $plugin['file'] ) ) {
				return \__( 'Unknown', 'wp-security-audit-log' );
			}

			return \sanitize_text_field( $plugin['file'] );
		}
	}
}
