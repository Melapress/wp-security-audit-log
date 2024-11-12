<?php
/**
 * WP Activity Log.
 *
 * @copyright Copyright (C) 2013-2024, Melapress - support@melapress.com
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3 or higher
 *
 * @wordpress-plugin
 * Plugin Name: WP Activity Log
 * Version:     5.2.2
 * Plugin URI:  https://melapress.com/wordpress-activity-log/
 * Description: Identify WordPress security issues before they become a problem. Keep track of everything happening on your WordPress, including users activity. Similar to Linux Syslog, WP Activity Log generates an activity log with a record of everything that happens on your WordPress websites.
 * Author:      Melapress
 * Author URI:  https://melapress.com/
 * Text Domain: wp-security-audit-log
 * Domain Path: /languages/
 * License:     GPL v3
 * Requires at least: 5.5
 * Requires PHP: 7.2
 * Network: true
 *
 * @package wsal
 *
 * @fs_premium_only /extensions/, /third-party/woocommerce/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

use WSAL\Helpers\WP_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Require Composer autoloader if it exists.
if ( file_exists( plugin_dir_path( __FILE__ ) . 'vendor/autoload.php' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
}
if ( file_exists( plugin_dir_path( __FILE__ ) . 'third-party/vendor/autoload.php' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'third-party/vendor/autoload.php';
}

if ( ! defined( 'WSAL_PREFIX' ) ) {
	define( 'WSAL_VERSION', '5.2.2' );
	define( 'WSAL_PREFIX', 'wsal_' );
	define( 'WSAL_PREFIX_PAGE', 'wsal-' );
}

// Plugin file.
if ( ! defined( 'WSAL_BASE_FILE_NAME' ) ) {
	define( 'WSAL_BASE_FILE_NAME', __FILE__ );
}

// Plugin Name.
if ( ! defined( 'WSAL_BASE_NAME' ) ) {
	define( 'WSAL_BASE_NAME', plugin_basename( __FILE__ ) );
}
// Plugin Directory URL.
if ( ! defined( 'WSAL_BASE_URL' ) ) {
	define( 'WSAL_BASE_URL', plugin_dir_url( __FILE__ ) );
}
// Plugin Directory Path.
if ( ! defined( 'WSAL_BASE_DIR' ) ) {
	define( 'WSAL_BASE_DIR', plugin_dir_path( __FILE__ ) );
}
// Plugin Docs URL.
if ( ! defined( 'WSAL_DOCS_URL' ) ) {
	define( 'WSAL_DOCS_URL', 'https://mellapress.com/support/' );
}
// Plugin Issue Reporting URL.
if ( ! defined( 'WSAL_ISSUE_URL' ) ) {
	define( 'WSAL_ISSUE_URL', 'https://wordpress.org/support/plugin/wp-security-audit-log' );
}
// Plugin Classes Prefix.
if ( ! defined( 'WSAL_CLASS_PREFIX' ) ) {
	define( 'WSAL_CLASS_PREFIX', 'WSAL_' );
}

/**
 * Remove these in lets say 3 major versions from 5.1.1 and
 * switch to WpSecurityAuditLog:: ___ consts ___
 */
if ( ! defined( 'PREMIUM_VERSION_WHOLE_PLUGIN_NAME' ) ) {
	/**
	 * Premium version constant
	 *
	 * @var string
	 *
	 * @since 5.1.0
	 */
	define( 'PREMIUM_VERSION_WHOLE_PLUGIN_NAME', 'wp-security-audit-log-premium/wp-security-audit-log.php' );

	/**
	 * NOFS version constant
	 *
	 * @var string
	 *
	 * @since 5.1.0
	 */
	define( 'NOFS_VERSION_WHOLE_PLUGIN_NAME', 'wp-security-audit-log-nofs/wp-security-audit-log.php' );

	/**
	 * Free version constant
	 *
	 * @var string
	 *
	 * @since 5.1.0
	 */
	define( 'FREE_VERSION_WHOLE_PLUGIN_NAME', 'wp-security-audit-log/wp-security-audit-log.php' );
}

/**
 * Connections Prefix.
 */
if ( ! defined( 'WSAL_CONN_PREFIX' ) ) {
	define( 'WSAL_CONN_PREFIX', 'connection-' );
}
if ( ! defined( 'WSAL_MIRROR_PREFIX' ) ) {
	define( 'WSAL_MIRROR_PREFIX', 'mirror-' );
}
// phpcs:disable
/* @free:start */
// phpcs:enable
if ( ! function_exists( 'wsal_disable_freemius_on_free' ) ) {
	/**
	 * Disables the freemius
	 *
	 * @return WSAL_Freemius
	 *
	 * @since 4.5.0
	 */
	function wsal_disable_freemius_on_free() {
		require_once __DIR__ . '/nofs/lib/class-wsal-freemius.php';

		return WSAL_Freemius::get_instance();
	}
}
\add_filter( 'wsal_freemius_sdk_object', 'wsal_disable_freemius_on_free' );
\add_filter( 'wsal_disable_freemius_sdk', '__return_true' );
// phpcs:disable
/* @free:end */
// phpcs:enable

if ( ! function_exists( 'wsal_freemius' ) ) {

	// phpcs:disable
	// phpcs:enable

	// Load action scheduler for event mirroring.
	$action_scheduler_file_path = WSAL_BASE_DIR . implode(
		DIRECTORY_SEPARATOR,
		array(
			'third-party',
			'woocommerce',
			'action-scheduler',
			'action-scheduler.php',
		)
	);

	if ( file_exists( $action_scheduler_file_path ) ) {
		require_once $action_scheduler_file_path;
	}

	// Begin load sequence.
	WpSecurityAuditLog::get_instance();

	if ( ! WP_Helper::is_plugin_active( WSAL_BASE_NAME ) ) {
		WpSecurityAuditLog::load_freemius();

		if ( ! apply_filters( 'wsal_disable_freemius_sdk', false ) ) {
			wsal_freemius()->add_action( 'after_uninstall', array( '\WSAL\Helpers\Uninstall_Helper', 'uninstall' ) );
		}
	}
} elseif ( ! method_exists( 'WSAL_Freemius', 'set_basename' ) ) {
	global $wsal_freemius;
	$wsal_freemius = null;
	unset( $wsal_freemius );
} else {
	wsal_freemius()->set_basename( true, __FILE__ );
}
// phpcs:disable
/* @free:start */
// phpcs:enable
if ( ! function_exists( 'wsal_free_on_plugin_activation' ) ) {
	/**
	 * Takes care of deactivation of the premium plugin when the free plugin is activated. The opposite direction is handled
	 * by Freemius SDK.
	 *
	 * Note: This code MUST NOT be present in the premium version an is removed automatically during the build process.
	 *
	 * @since 4.3.2
	 */
	function wsal_free_on_plugin_activation() {
		if ( WP_Helper::is_plugin_active( PREMIUM_VERSION_WHOLE_PLUGIN_NAME ) ) {
			\deactivate_plugins( PREMIUM_VERSION_WHOLE_PLUGIN_NAME, true );
		}
		if ( WP_Helper::is_plugin_active( NOFS_VERSION_WHOLE_PLUGIN_NAME ) ) {
			\deactivate_plugins( NOFS_VERSION_WHOLE_PLUGIN_NAME, true );
		}
	}

	\register_activation_hook( __FILE__, 'wsal_free_on_plugin_activation' );
}
// phpcs:disable
/* @free:end */
// phpcs:enable

if ( ! function_exists( 'str_ends_with' ) ) {
	/**
	 * Substitute function if the PHP version is lower than PHP 8
	 *
	 * @param string $haystack - The search in string.
	 * @param string $needle - The search for string.
	 *
	 * @return boolean
	 *
	 * @since 5.0.0
	 */
	function str_ends_with( string $haystack, string $needle ): bool {
		$needle_len = strlen( $needle );
		return ( 0 === $needle_len || 0 === substr_compare( $haystack, $needle, - $needle_len ) );
	}
}

if ( ! function_exists( 'str_starts_with' ) ) {
	/**
	 * PHP lower than 8 is missing that function but it required in the newer versions of our plugin.
	 *
	 * @param string $haystack - The string to search in.
	 * @param string $needle - The needle to search for.
	 *
	 * @return bool
	 *
	 * @since 5.0.0
	 */
	function str_starts_with( $haystack, $needle ): bool {
		if ( '' === $needle ) {
			return true;
		}

		return 0 === strpos( $haystack, $needle );
	}
}
