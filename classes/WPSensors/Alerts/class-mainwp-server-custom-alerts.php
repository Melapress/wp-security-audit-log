<?php
/**
 * Custom Alerts for Gravity Forms plugin.
 *
 * Class file for alert manager.
 *
 * @since 5.1.0
 *
 * @package wsal
 * @subpackage wsal-gravity-forms
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.WP.I18n.UnorderedPlaceholdersText

declare(strict_types=1);

namespace WSAL\WP_Sensors\Alerts;

use WSAL\MainWP\MainWP_Addon;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Alerts\MainWP_Server_Custom_Alerts' ) ) {
	/**
	 * Custom sensor for Gravity Forms plugin.
	 *
	 * @since 5.0.0
	 */
	class MainWP_Server_Custom_Alerts {

		/**
		 * Returns the structure of the alerts for extension.
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function get_custom_alerts(): array {
			// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
			if ( MainWP_Addon::check_mainwp_plugin_active() || \WpSecurityAuditLog::is_mainwp_active() ) {
				return array(
					\esc_html__( 'MainWP Dashboard', 'wp-security-audit-log' ) => array(
						\esc_html__( 'Monitor MainWP Dashboard', 'wp-security-audit-log' ) =>
						self::get_alerts_array(),
					),
				);
			}

			return array();
		}

		/**
		 * Returns array with all the events attached to the sensor (if there are different types of events, that method will merge them into one array - the events ids will be uses as keys)
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function get_alerts_array(): array {
			return array(
				7700 => array(
					7700,
					WSAL_CRITICAL,
					esc_html__( 'User added the child site', 'wp-security-audit-log' ),
					esc_html__( 'The child site %friendly_name% %LineBreak% URL: %site_url%', 'wp-security-audit-log' ),
					array(),
					array(),
					'child-site',
					'added',
				),
				7701 => array(
					7701,
					WSAL_CRITICAL,
					esc_html__( 'User removed the child site', 'wp-security-audit-log' ),
					esc_html__( 'The child site %friendly_name% %LineBreak% URL: %site_url%', 'wp-security-audit-log' ),
					array(),
					array(),
					'child-site',
					'removed',
				),
				7702 => array(
					7702,
					WSAL_MEDIUM,
					esc_html__( 'User edited the child site', 'wp-security-audit-log' ),
					esc_html__( 'The child site %friendly_name% %LineBreak% URL: %site_url%', 'wp-security-audit-log' ),
					array(),
					array(),
					'child-site',
					'modified',
				),
				7703 => array(
					7703,
					WSAL_INFORMATIONAL,
					esc_html__( 'User synced data with the child site', 'wp-security-audit-log' ),
					esc_html__( 'Synced data with the child %friendly_name% %LineBreak% URL: %site_url%', 'wp-security-audit-log' ),
					array(),
					array(),
					'mainwp',
					'synced',
				),
				7704 => array(
					7704,
					WSAL_INFORMATIONAL,
					esc_html__( 'User synced data with all the child sites', 'wp-security-audit-log' ),
					esc_html__( 'Synced data with all the child sites', 'wp-security-audit-log' ),
					array(),
					array(),
					'mainwp',
					'synced',
				),
				7705 => array(
					7705,
					WSAL_CRITICAL,
					esc_html__( 'User installed the extension', 'wp-security-audit-log' ),
					esc_html__( 'The extension %extension_name%', 'wp-security-audit-log' ),
					array(),
					array(),
					'extension',
					'installed',
				),
				7706 => array(
					7706,
					WSAL_HIGH,
					esc_html__( 'User activated the extension', 'wp-security-audit-log' ),
					esc_html__( 'The extension %extension_name%', 'wp-security-audit-log' ),
					array(),
					array(),
					'extension',
					'activated',
				),
				7707 => array(
					7707,
					WSAL_HIGH,
					esc_html__( 'User deactivated the extension', 'wp-security-audit-log' ),
					esc_html__( 'The extension %extension_name%', 'wp-security-audit-log' ),
					array(),
					array(),
					'extension',
					'deactivated',
				),
				7708 => array(
					7708,
					WSAL_CRITICAL,
					esc_html__( 'User uninstalled the extension', 'wp-security-audit-log' ),
					esc_html__( 'The extension %extension_name%', 'wp-security-audit-log' ),
					array(),
					array(),
					'extension',
					'uninstalled',
				),
				7709 => array(
					7709,
					WSAL_INFORMATIONAL,
					esc_html__( 'User added/removed extension to/from the menu', 'wp-security-audit-log' ),
					esc_html__( 'The extension %extension% %option% the MainWP menu', 'wp-security-audit-log' ),
					array(),
					array(),
					'mainwp',
					'updated',
				),
				7710 => array(
					7710,
					WSAL_LOW,
					esc_html__( 'Extension failed to retrieve the activity log of a child site', 'wp-security-audit-log' ),
					esc_html__( 'Failed to retrieve the activity log of the child site %friendly_name% %LineBreak% URL: %site_url%', 'wp-security-audit-log' ),
					array(),
					array(),
					'activity-logs',
					'failed',
				),
				7711 => array(
					7711,
					WSAL_INFORMATIONAL,
					esc_html__( 'Extension started retrieving activity logs from the child sites', 'wp-security-audit-log' ),
					esc_html__( 'Retrieving activity logs from child sites', 'wp-security-audit-log' ),
					array(),
					array(),
					'activity-logs',
					'started',
				),
				7712 => array(
					7712,
					WSAL_INFORMATIONAL,
					esc_html__( 'Extension is ready retrieving activity logs from the child sites', 'wp-security-audit-log' ),
					esc_html__( 'Extension is ready retrieving activity logs from child sites', 'wp-security-audit-log' ),
					array(),
					array(),
					'activity-logs',
					'finished',
				),
				7713 => array(
					7713,
					WSAL_MEDIUM,
					esc_html__( 'Changed the enforcement settings of the Child sites activity log settings', 'wp-security-audit-log' ),
					esc_html__( 'The status of the <strong>Child sites activity log settings</strong> %LineBreak% Previous status: %old_status% %LineBreak% New status: %new_status%', 'wp-security-audit-log' ),
					array(),
					array(),
					'activity-logs',
					'modified',
				),
				7714 => array(
					7714,
					WSAL_MEDIUM,
					esc_html__( 'Added or removed a child site from the Child sites activity log settings', 'wp-security-audit-log' ),
					esc_html__( 'A child site to / from the <strong>Child sites activity log settings</strong> %LineBreak% Site name: %friendly_name% %LineBreak% URL: %site_url%', 'wp-security-audit-log' ),
					array(),
					array(),
					'activity-logs',
					'added',
				),
				7715 => array(
					7715,
					WSAL_MEDIUM,
					esc_html__( 'Modified the Child sites activity log settings that are propagated to the child sites', 'wp-security-audit-log' ),
					esc_html__( 'The <strong>child sites activity log settings</strong> that are propagated to the child sites', 'wp-security-audit-log' ),
					array(),
					array(),
					'activity-logs',
					'modified',
				),
				7716 => array(
					7716,
					WSAL_MEDIUM,
					esc_html__( 'Started or finished propagating the configured Child sites activity log settings to the child sites', 'wp-security-audit-log' ),
					esc_html__( 'Propagating the configured <strong>Child sites activity log settings</strong>', 'wp-security-audit-log' ),
					array(),
					array(),
					'activity-logs',
					'started',
				),
				7717 => array(
					7717,
					WSAL_HIGH,
					esc_html__( 'The propagation of the Child sites activity log settings failed on a child site site', 'wp-security-audit-log' ),
					esc_html__( 'The propagation of the <strong>Child sites activity log settings</strong> failed on this site %LineBreak% Site name: %friendly_name% %LineBreak% URL: %site_url% %LineBreak% Error message: %message%', 'wp-security-audit-log' ),
					array(),
					array(),
					'activity-logs',
					'failed',
				),
				7750 => array(
					7750,
					WSAL_INFORMATIONAL,
					esc_html__( 'User added a monitor for site', 'wp-security-audit-log' ),
					esc_html__( 'A monitor for the site %friendly_name% in Advanced Uptime Monitor extension %LineBreak% URL: %site_url%', 'wp-security-audit-log' ),
					array(),
					array(),
					'uptime-monitor',
					'added',
				),
				7751 => array(
					7751,
					WSAL_MEDIUM,
					esc_html__( 'User deleted a monitor for site', 'wp-security-audit-log' ),
					esc_html__( 'The monitor for the site %friendly_name% in Advanced Uptime Monitor extension %LineBreak% URL: %site_url%', 'wp-security-audit-log' ),
					array(),
					array(),
					'uptime-monitor',
					'deleted',
				),
				7752 => array(
					7752,
					WSAL_INFORMATIONAL,
					esc_html__( 'User started the monitor for the site', 'wp-security-audit-log' ),
					esc_html__( 'The monitor for the site %friendly_name% in Advanced Uptime Monitor extension %LineBreak% URL: %site_url%', 'wp-security-audit-log' ),
					array(),
					array(),
					'uptime-monitor',
					'started',
				),
				7753 => array(
					7753,
					WSAL_MEDIUM,
					esc_html__( 'User stopped the monitor for the site', 'wp-security-audit-log' ),
					esc_html__( 'Paused the monitor for the site %friendly_name% in Advanced Uptime Monitor extension %LineBreak% URL: %site_url%', 'wp-security-audit-log' ),
					array(),
					array(),
					'uptime-monitor',
					'stopped',
				),
				7754 => array(
					7754,
					WSAL_INFORMATIONAL,
					esc_html__( 'User created monitors for all child sites', 'wp-security-audit-log' ),
					esc_html__( 'Created monitors for all child sites', 'wp-security-audit-log' ),
					array(),
					array(),
					'uptime-monitor',
					'created',
				),
			);
		}
	}
}
