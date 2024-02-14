<?php
/**
 * Responsible for logging.
 *
 * @package    wsal
 * @subpackage utils
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-security-audit-log/
 * @since 4.4.3
 */

declare(strict_types=1);

namespace WSAL\Helpers;

if ( ! class_exists( '\WSAL\Helpers\Logger' ) ) {

	/**
	 * Provides logging functionality when debugging  for the plugin
	 *
	 * @since 4.4.3
	 */
	class Logger {

		/**
		 * Local cache for the logging dir so that it doesn't need to be repopulated each time get_logging_dir_path is called.
		 *
		 * @var string
		 *
		 * @since 4.4.3
		 */
		private static $logging_dir_path = '';

		/**
		 * Retrieve the logging status
		 *
		 * @return boolean
		 *
		 * @since 4.4.3
		 */
		private static function is_logging_enabled() {
			/**
			 * Enables / Disables the logging for the plugin.
			 *
			 * @param bool $disabled - Default logging for the plugin.
			 *
			 * @since 4.4.3
			 */
			return apply_filters( WSAL_PREFIX . 'logging_enabled', true );
		}

		/**
		 * Logs the given message
		 *
		 * @param string $message - The message to log.
		 *
		 * @return void
		 *
		 * @since 4.4.3
		 */
		public static function log( $message ) {
			if ( self::is_logging_enabled() ) {
				self::write_to_log( self::get_log_timestamp() . "\n" . $message . "\n" . __( 'Current memory usage: ', 'wp-security-audit-log' ) . memory_get_usage( true ) . "\n" );
			}
		}

		/**
		 * Retrieves the path to the log file
		 *
		 * @return string
		 *
		 * @since 4.4.3
		 */
		private static function get_logging_dir_path() {
			if ( strlen( self::$logging_dir_path ) === 0 ) {
				$log_dir = '';
				if ( defined( '\WSAL_WORKING_DIR_PATH' ) ) {
					$log_dir = trailingslashit( \WSAL_WORKING_DIR_PATH );
				} else {
					$log_dir = wp_upload_dir( null, false );
					if ( is_array( $log_dir ) && array_key_exists( 'basedir', $log_dir ) ) {
						$log_dir = $log_dir['basedir'] . '/wp-activity-log/';
					} elseif ( defined( 'WP_CONTENT_DIR' ) ) {
						// Fallback in case there is a problem with filesystem.
						$log_dir = WP_CONTENT_DIR . '/uploads/wp-activity-log/';
					}
				}

				self::$logging_dir_path = trailingslashit( trailingslashit( $log_dir ) );
			}

			return self::$logging_dir_path;
		}

		/**
		 * Write data to log file.
		 *
		 * @param string $data     - Data to write to file.
		 * @param bool   $override - Set to true if overriding the file.
		 *
		 * @return bool
		 *
		 * @since 4.4.3
		 */
		private static function write_to_log( $data, $override = false ) {
			$logging_dir_path = self::get_logging_dir_path();
			if ( ! is_dir( $logging_dir_path ) ) {
				self::create_index_file();
				self::create_htaccess_file();
			}

			$log_file_name = gmdate( 'Y-m-d' );
			return self::write_to_file( 'wp-security-audit-log-debug-' . $log_file_name . '.log', $data, $override );
		}

		/**
		 * Create an index.php file, if none exists, in order to
		 * avoid directory listing in the specified directory.
		 *
		 * @return bool
		 *
		 * @since 4.4.3
		 */
		private static function create_index_file() {
			return self::write_to_file( 'index.php', '<?php // Silence is golden' );
		}

		/**
		 * Create an .htaccess file, if none exists, in order to
		 * block access to directory listing in the specified directory.
		 *
		 * @return bool
		 *
		 * @since 4.4.3
		 */
		private static function create_htaccess_file() {
			return self::write_to_file( '.htaccess', 'Deny from all' );
		}

		/**
		 * Write data to log file in the uploads directory.
		 *
		 * @param string $filename - File name.
		 * @param string $content  - Contents of the file.
		 * @param bool   $override - (Optional) True if overriding file contents.
		 *
		 * @return bool
		 *
		 * @since 4.4.3
		 */
		private static function write_to_file( $filename, $content, $override = false ) {
			return File_Helper::write_to_file( self::get_logging_dir_path() . $filename, $content, $override );
		}

		/**
		 * Returns the timestamp for log files.
		 *
		 * @return string
		 *
		 * @since 4.4.3
		 */
		private static function get_log_timestamp() {
			return '[' . gmdate( 'd-M-Y H:i:s' ) . ' UTC]';
		}
	}
}
