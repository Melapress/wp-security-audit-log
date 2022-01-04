<?php
/**
 * Utility class for opcache clearing.
 *
 * @package   wsal
 * @subpacage utilities
 * @since     4.3.4
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Utility class for opcache clearing.
 *
 * @package   wsal
 * @subpacage utilities
 * @since     4.3.4
 */
class WSAL_Utilities_OpCacheUtils {

	/**
	 * Clears PHP code caches before a plugin installation or update. This is to avoid various plugin files to be out of
	 * sync.
	 *
	 * @param bool|WP_Error $response   Response.
	 * @param array         $hook_extra Extra arguments passed to hooked filters.
	 *
	 * @since 4.3.4
	 */
	public static function clear_caches( $response, array $hook_extra ) {
		if ( ( array_key_exists( 'type', $hook_extra ) && 'plugin' === $hook_extra['type'] )
		     || array_key_exists( 'plugin', $hook_extra ) ) {
			if ( self::is_iis() ) {
				self::clear_iis_wincache();
			} else {
				return self::clear_php_opcache();
			}
		}

		return $response;
	}

	/**
	 * Checks if the web server is running IIS software.
	 *
	 * @return bool
	 */
	public static function is_iis() {
		$software = strtolower( $_SERVER["SERVER_SOFTWARE"] );
		if ( false !== strpos( $software, "microsoft-iis" ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Clears the IIS cache.
	 *
	 * @return bool|void
	 */
	public static function clear_iis_wincache() {
		if ( ! function_exists( 'wincache_ucache_get' ) ) {
			return;
		}
		if ( ! wincache_ucache_clear() ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Clears the PHP opcache.
	 *
	 * @return bool|void
	 */
	public static function clear_php_opcache() {
		if ( ! extension_loaded( 'Zend OPcache' ) ) {
			return;
		}
		$opcache_status = opcache_get_status();
		if ( false === $opcache_status["opcache_enabled"] ) {
			// extension loaded but OPcache not enabled
			return;
		}
		if ( ! opcache_reset() ) {
			return false;
		} else {
			/**
			 * opcache_reset() is performed, now try to clear the
			 * file cache.
			 * Please note: http://stackoverflow.com/a/23587079/1297898
			 *   "Opcache does not evict invalid items from memory - they
			 *   stay there until the pool is full at which point the
			 *   memory is completely cleared"
			 */
			foreach ( $opcache_status['scripts'] as $key => $data ) {
				$dirs[ dirname( $key ) ][ basename( $key ) ] = $data;
				opcache_invalidate( $data['full_path'], true );
			}

			return true;
		}
	}
}