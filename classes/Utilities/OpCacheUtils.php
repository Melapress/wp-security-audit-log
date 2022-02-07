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
		if ( ! array_key_exists( 'SERVER_SOFTWARE', $_SERVER ) ) {
			return false;
		}

		$software = strtolower( $_SERVER['SERVER_SOFTWARE'] ); // @codingStandardsIgnoreLine

		return ( false !== strpos( $software, 'microsoft-iis' ) );
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

		return wincache_ucache_clear();
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
		if ( ! is_array( $opcache_status ) || ! array_key_exists( 'opcache_enabled', $opcache_status ) || false === $opcache_status['opcache_enabled'] ) {
			// Extension loaded but OPcache not enabled.
			return;
		}

		if ( ! function_exists( 'opcache_reset' ) || ! opcache_reset() ) {
			return false;
		}

		/**
		 * Function opcache_reset() was executed, now try to clear the
		 * file cache.
		 *
		 * Please note: http://stackoverflow.com/a/23587079/1297898
		 *   "Opcache does not evict invalid items from memory - they
		 *   stay there until the pool is full at which point the
		 *   memory is completely cleared"
		 */
		if ( array_key_exists( 'scripts', $opcache_status ) && ! empty( $opcache_status['scripts'] ) ) {
			if ( function_exists( 'opcache_invalidate' ) ) {
				foreach ( $opcache_status['scripts'] as $data ) {
					opcache_invalidate( $data['full_path'], true );
				}
			}
		}

		return true;
	}
}
