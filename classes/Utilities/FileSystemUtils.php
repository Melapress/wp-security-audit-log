<?php
/**
 * File system utility class.
 *
 * @package wsal
 * @since 4.4.0
 */

/**
 * Utility class for handling certain file system related functionality.
 *
 * @package wsal
 * @since 4.4.0
 */
class WSAL_Utilities_FileSystemUtils {
	/**
	 * Returns a list of files matching given pattern in a given directory.
	 *
	 * It uses transients to cache the list of files for a day.
	 *
	 * @param string $directory Directory to search.
	 * @param string $pattern Filename pattern to narrow down the list of files.
	 *
	 * @return string
	 *
	 * @since 4.4.0
	 */
	public static function read_files_in_folder( $directory, $pattern ) {
		$folder_slashed = trailingslashit( $directory );
		$cache_key      = WpSecurityAuditLog::OPTIONS_PREFIX . '_file_list_' . md5( $folder_slashed . $pattern );
		$cached_data    = get_transient( $cache_key );
		if ( is_array( $cached_data ) ) {
			return $cached_data;
		}

		$result = array();
		$handle = opendir( $directory );
		if ( $handle ) {
			$ignore_list = array( '.', '..' );
			$regexp      = '/' . str_replace( array( '.', '*' ), array( '\.', '.*' ), $pattern ) . '/';
			while ( false !== ( $file_name = readdir( $handle ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
				if ( ! in_array( $file_name, $ignore_list, true ) && preg_match( $regexp, $file_name ) ) {
					array_push( $result, $folder_slashed . $file_name );
				}
			}
			closedir( $handle );
		}

		set_transient( $cache_key, $result, DAY_IN_SECONDS );

		return $result;
	}
}
