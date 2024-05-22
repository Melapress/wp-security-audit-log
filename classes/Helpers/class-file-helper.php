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
		 */
		public static function create_index_file( string $path ): bool {
			// Check if directory exists.
			$path = trailingslashit( $path );

			return self::write_to_file( $path . 'index.php', '<?php /*[WP Activity Log plugin: This file was auto-generated to prevent directory listing ]*/ exit;' );
		}

		/**
		 * Creates htaccess file in given directory.
		 *
		 * @param string $path - Path in which htaccess file should be created. If does not exist - the method will try to create it.
		 *
		 * @return boolean
		 *
		 * @since 4.4.3
		 */
		public static function create_htaccess_file( string $path ): bool {
			// Check if directory exists.
			$path = trailingslashit( $path );

			return self::write_to_file( $path . '.htaccess', 'Deny from all' );
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
		 */
		public static function write_to_file( string $filename, string $content, bool $append = false ): bool {
			global $wp_filesystem;
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();

			$logging_dir = dirname( $filename );

			$result = false;

			if ( ! is_dir( $logging_dir ) ) {
				if ( false === wp_mkdir_p( $logging_dir ) ) {
					self::$last_error = 'Unable to create directory';
					return $result;
				}
			}

			$filepath = $filename;
			if ( ! $wp_filesystem->exists( $filepath ) || $append ) {
				$result = $wp_filesystem->put_contents( $filepath, $content );
			} elseif ( $append ) {
				$existing_content = $wp_filesystem->get_contents( $filepath );
				$result           = $wp_filesystem->put_contents( $filepath, $existing_content . $content );
			}

			if ( false === $result ) {
				self::$last_error = 'Trying to write to the file failed';
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
		 */
		public static function read_entire_content_memory( string $filename ): string {
			global $wp_filesystem;
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();

			if ( $wp_filesystem->exists( $filename ) ) {
				return $wp_filesystem->get_contents( $filename );
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
		 */
		public static function format_file_size( string $filename ): string {
			global $wp_filesystem;
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();

			if ( $wp_filesystem->exists( $filename ) ) {

				$size = filesize( $filename );

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
