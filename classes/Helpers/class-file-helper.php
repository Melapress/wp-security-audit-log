<?php
/**
 * Class: File functions helper file.
 *
 * Helper class used for extraction / loading classes.
 *
 * @package wsal
 */

declare(strict_types=1);

namespace WSAL\Helpers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Helpers\File_Helper' ) ) {
	/**
	 * Responsible for file operations.
	 *
	 * @since 4.4.3
	 */
	class File_Helper {

		/**
		 * Keeps the string representation of the last error
		 *
		 * @var string
		 *
		 * @since 4.4.3
		 */
		private static $last_error = '';

		/**
		 * Creates index file in the given directory.
		 *
		 * @param string $path - Path in which index file should be created. If does not exist - the method will try to create it.
		 *
		 * @return boolean
		 *
		 * @since 4.4.3
		 * @since 5.6.6 - Avoided rewriting an existing directory listing protection file.
		 */
		public static function create_index_file( string $path ): bool {
			// Check if directory exists.
			$path = trailingslashit( $path );

			$filename = $path . 'index.php';

			if ( file_exists( $filename ) ) {
				return true;
			}

			return self::write_to_file( $filename, '<?php /*[WP Activity Log plugin: This file was auto-generated to prevent directory listing ]*/ exit;' );
		}

		/**
		 * Creates htaccess file in given directory.
		 *
		 * @param string $path - Path in which htaccess file should be created. If does not exist - the method will try to create it.
		 *
		 * @return boolean
		 *
		 * @since 4.4.3
		 * @since 5.6.6 - Avoided rewriting an existing directory access protection file.
		 */
		public static function create_htaccess_file( string $path ): bool {
			// Check if directory exists.
			$path = trailingslashit( $path );

			$filename = $path . '.htaccess';

			if ( file_exists( $filename ) ) {
				return true;
			}

			return self::write_to_file( $filename, 'Deny from all' );
		}

		/**
		 * Initializes the WordPress filesystem API and makes sure a usable filesystem object is available.
		 *
		 * Relaxed file ownership is requested, so WordPress uses direct disk access whenever the context
		 * directory is writable, instead of falling back to the FTP or SSH transports. The plugin holds no
		 * credentials for those transports, and using the resulting object would raise a fatal error.
		 *
		 * @param string $context - Optional. Full path to an existing directory used to determine the filesystem method. Defaults to the WordPress context.
		 *
		 * @return boolean $initialized - True when the filesystem object is ready to be used.
		 *
		 * @since 5.6.6
		 */
		private static function init_file_system( string $context = '' ): bool {
			global $wp_filesystem;

			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			$initialized = \WP_Filesystem( false, $context, true );

			if ( true !== $initialized || ! is_object( $wp_filesystem ) ) {
				self::$last_error = 'The WordPress filesystem could not be initialized';

				return false;
			}

			return true;
		}

		/**
		 * Writes content to given file
		 *
		 * @param string  $filename - Full path to the file.
		 * @param string  $content - Content to write into the file.
		 * @param boolean $append - Appends the content to the file if it exists.
		 *
		 * @return boolean
		 *
		 * @since 4.4.3
		 * @since 5.6.6 - Added filesystem initialization checks and concurrency-safe append behaviour.
		 */
		public static function write_to_file( string $filename, string $content, bool $append = false ): bool {
			global $wp_filesystem;

			$logging_dir = dirname( $filename );

			if ( ! is_dir( $logging_dir ) ) {
				if ( false === \wp_mkdir_p( $logging_dir ) ) {
					self::$last_error = 'Unable to create directory ' . $logging_dir;

					return false;
				}
			}

			/**
			 * The directory is passed as the context and has to exist at this point, as WordPress runs
			 * its write test inside it to decide which filesystem method to use.
			 */
			if ( ! self::init_file_system( $logging_dir ) ) {
				return false;
			}

			if ( $append ) {
				// WP_Filesystem has no atomic append operation, so use PHP's locked append mode.
				$bytes_written = file_put_contents( $filename, $content, FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				$result        = false !== $bytes_written && strlen( $content ) === $bytes_written;
			} else {
				$result = $wp_filesystem->put_contents( $filename, $content );
			}

			if ( false === $result ) {
				self::$last_error = 'Trying to write to the file ' . $filename . ' failed';
			}

			return (bool) $result;
		}

		/**
		 * Getter for the last error variable of the class
		 *
		 * @return string
		 *
		 * @since 4.4.3
		 */
		public static function get_last_error(): string {
			return self::$last_error;
		}

		/**
		 * Reads entire file into memory and returns the content as a string.
		 * IMPORTANT: Don't use that method if you are expecting large files.
		 *
		 * @param string $filename - The full name of the file (including the path).
		 *
		 * @return string
		 *
		 * @since 4.4.3.2
		 * @since 5.6.6 - Added filesystem initialization and read failure handling.
		 */
		public static function read_entire_content_memory( string $filename ): string {
			global $wp_filesystem;

			if ( ! self::init_file_system() ) {
				return '';
			}

			if ( $wp_filesystem->exists( $filename ) ) {
				$content = $wp_filesystem->get_contents( $filename );

				if ( false === $content ) {
					self::$last_error = 'Trying to read the file ' . $filename . ' failed';

					return '';
				}

				return $content;
			}

			return '';
		}

		/**
		 * Returns the file size in human readable format.
		 *
		 * @param string $filename - The name of the file (including path) to check the size of.
		 *
		 * @return string
		 *
		 * @since 5.0.0
		 * @since 5.6.6 - Added filesystem initialization and size failure handling.
		 */
		public static function format_file_size( string $filename ): string {
			global $wp_filesystem;

			if ( ! self::init_file_system() ) {
				return '0KB';
			}

			if ( $wp_filesystem->exists( $filename ) ) {

				$size = $wp_filesystem->size( $filename );

				if ( false === $size ) {
					return '0KB';
				}

				$units          = array( 'B', 'KB', 'MB', 'GB', 'TB' );
				$formatted_size = $size;

				$units_length = count( $units ) - 1;

				for ( $i = 0; $size >= 1024 && $i < $units_length; $i++ ) {
					$size          /= 1024;
					$formatted_size = round( $size, 2 );
				}

				return $formatted_size . ' ' . $units[ $i ];
			}

			return '0KB';
		}
	}
}
