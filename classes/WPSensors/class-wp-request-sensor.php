<?php
/**
 * Sensor: Request
 *
 * Request sensor class file.
 *
 * @since      4.6.0
 * @package    wsal
 * @subpackage sensors
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors;

use WSAL\Helpers\Settings_Helper;
use WSAL\Controllers\Alert_Manager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\WP_Request_Sensor' ) ) {
	/**
	 * Writes the Request.log.php file.
	 *
	 * @package    wsal
	 * @subpackage sensors
	 */
	class WP_Request_Sensor {
		/**
		 * Environment Variables.
		 *
		 * @var array
		 */
		private static $env_vars = array();

		/**
		 * Inits the main hooks
		 *
		 * @return void
		 *
		 * @since 4.5.0
		 */
		public static function init() {
			if ( Settings_Helper::is_request_logging_enabled() ) {
				add_action( 'shutdown', array( __CLASS__, 'event_shutdown' ) );
			}
		}

		/**
		 * Fires just before PHP shuts down execution.
		 *
		 * @since 4.5.0
		 */
		public static function event_shutdown() {
			// Filter global arrays for security.
			$post_array   = filter_input_array( INPUT_POST );
			$server_array = filter_input_array( INPUT_SERVER );

			// get the custom logging path from settings.
			$custom_logging_path = \WSAL\Helpers\Settings_Helper::get_working_dir_path_static();
			if ( is_wp_error( $custom_logging_path ) ) {
				return;
			}

			$file = $custom_logging_path . 'Request.log.php';

			$request_method = isset( $server_array['REQUEST_METHOD'] ) ? $server_array['REQUEST_METHOD'] : false;
			$request_uri    = isset( $server_array['REQUEST_URI'] ) ? $server_array['REQUEST_URI'] : false;

			$line = '[' . gmdate( 'Y-m-d H:i:s' ) . '] '
			. $request_method . ' '
			. $request_uri . ' '
			. ( ! empty( $post_array ) ? str_pad( PHP_EOL, 24 ) . json_encode( $post_array ) : '' ) // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			. ( ! empty( self::$env_vars ) ? str_pad( PHP_EOL, 24 ) . json_encode( self::$env_vars ) : '' ) // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			. PHP_EOL;

			if ( ! file_exists( $file ) && ! file_put_contents( $file, '<' . '?php die(\'Access Denied\'); ?>' . PHP_EOL ) ) { // phpcs:ignore
				Alert_Manager::log_error(
					'Could not initialize request log file',
					array(
						'file' => $file,
					)
				);

				return;
			}

			$f = fopen( $file, 'a' );
			if ( $f ) {
				if ( ! fwrite( $f, $line ) ) {
					Alert_Manager::log_warn(
						'Could not write to log file',
						array(
							'file' => $file,
						)
					);
				}
				if ( ! fclose( $f ) ) {
					Alert_Manager::log_warn(
						'Could not close log file',
						array(
							'file' => $file,
						)
					);
				}
			} else {
				Alert_Manager::log_warn(
					'Could not open log file',
					array(
						'file' => $file,
					)
				);
			}
		}

		/**
		 * Sets $envvars element with key and value.
		 *
		 * @param mixed $name  - Key name of the variable.
		 * @param mixed $value - Value of the variable.
		 */
		public static function set_var( $name, $value ) {
			self::$env_vars[ $name ] = $value;
		}

		/**
		 * Copy data array into $envvars array.
		 *
		 * @param array $data - Data array.
		 */
		public static function set_vars( $data ) {
			foreach ( $data as $name => $value ) {
				self::set_var( $name, $value );
			}
		}
	}
}
