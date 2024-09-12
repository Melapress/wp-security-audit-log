<?php
/**
 * Custom Alerts for Multisites.
 *
 * @since   4.6.0
 *
 * @package wsal
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.WP.I18n.UnorderedPlaceholdersText

declare(strict_types=1);

namespace WSAL\WP_Sensors\Alerts;

use WSAL\Helpers\WP_Helper;
use WSAL\MainWP\MainWP_Addon;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Alerts\Multisite_Custom_Alerts' ) ) {
	/**
	 * Custom sensor for Gravity Forms plugin.
	 *
	 * @since 4.6.0
	 */
	class Multisite_Custom_Alerts {

		/**
		 * Returns the structure of the alerts for extension.
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		public static function get_custom_alerts(): array {
			// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
			if ( WP_Helper::is_multisite() || MainWP_Addon::check_mainwp_plugin_active() ) {
				return array(
					esc_html__( 'Multisite Network Sites', 'wp-security-audit-log' ) => array(
						esc_html__( 'MultiSite', 'wp-security-audit-log' ) =>
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
		 * @since 4.6.0
		 */
		public static function get_alerts_array(): array {
			return array(
				7000 => array(
					7000,
					WSAL_CRITICAL,
					esc_html__( 'New site added on the network', 'wp-security-audit-log' ),
					esc_html__( 'Added the new site %SiteName% to the network.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'URL', 'wp-security-audit-log' ) => '%BlogURL%',
					),
					array(),
					'multisite-network',
					'added',
				),
				7001 => array(
					7001,
					WSAL_HIGH,
					esc_html__( 'Existing site archived', 'wp-security-audit-log' ),
					esc_html__( 'Archived the site %SiteName% on the network.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'URL', 'wp-security-audit-log' ) => '%BlogURL%',
					),
					array(),
					'multisite-network',
					'modified',
				),
				7002 => array(
					7002,
					WSAL_HIGH,
					esc_html__( 'Archived site has been unarchived', 'wp-security-audit-log' ),
					esc_html__( 'Unarchived the site %SiteName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'URL', 'wp-security-audit-log' ) => '%BlogURL%',
					),
					array(),
					'multisite-network',
					'modified',
				),
				7003 => array(
					7003,
					WSAL_HIGH,
					esc_html__( 'Deactivated site has been activated', 'wp-security-audit-log' ),
					esc_html__( 'Activated the site %SiteName% on the network.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'URL', 'wp-security-audit-log' ) => '%BlogURL%',
					),
					array(),
					'multisite-network',
					'activated',
				),
				7004 => array(
					7004,
					WSAL_HIGH,
					esc_html__( 'Site has been deactivated', 'wp-security-audit-log' ),
					esc_html__( 'Deactiveated the site %SiteName% on the network.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'URL', 'wp-security-audit-log' ) => '%BlogURL%',
					),
					array(),
					'multisite-network',
					'deactivated',
				),
				7005 => array(
					7005,
					WSAL_HIGH,
					esc_html__( 'Existing site deleted from network', 'wp-security-audit-log' ),
					esc_html__( 'The site: %SiteName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'URL', 'wp-security-audit-log' ) => '%BlogURL%',
					),
					array(),
					'multisite-network',
					'deleted',
				),
				7007 => array(
					7007,
					WSAL_CRITICAL,
					esc_html__( 'Allow site administrators to add new users to their sites settings changed', 'wp-security-audit-log' ),
					__( 'Changed the status of the network setting <strong>Allow site administrators to add new users to their sites</strong>.', 'wp-security-audit-log' ),
					array(),
					array(),
					'multisite-network',
					'enabled',
				),
				7008 => array(
					7008,
					WSAL_HIGH,
					esc_html__( 'Site upload space settings changed', 'wp-security-audit-log' ),
					__( 'Changed the status of the network setting <strong>Site upload space</strong> (to limit space allocated for each site\'s upload directory).', 'wp-security-audit-log' ),
					array(),
					array(),
					'multisite-network',
					'enabled',
				),
				7009 => array(
					7009,
					WSAL_MEDIUM,
					esc_html__( 'Site upload space file size settings changed', 'wp-security-audit-log' ),
					__( 'Changed the file size in the <strong>Site upload space</strong> network setting to %new_value%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous size (MB)', 'wp-security-audit-log' ) => '%old_value%',
					),
					array(),
					'multisite-network',
					'modified',
				),
				7010 => array(
					7010,
					WSAL_CRITICAL,
					esc_html__( 'Site Upload file types settings changed', 'wp-security-audit-log' ),
					__( 'Changed the network setting <strong>Upload file types (list of allowed file types)</strong>.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous value', 'wp-security-audit-log' ) => '%old_value%',
						esc_html__( 'New value', 'wp-security-audit-log' )      => '%new_value%',
					),
					array(),
					'multisite-network',
					'modified',
				),
				7011 => array(
					7011,
					WSAL_CRITICAL,
					esc_html__( 'Site Max upload file size settings changed', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Max upload file size</strong> network setting to %new_value%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous size (KB)', 'wp-security-audit-log' ) => '%old_value%',
					),
					array(),
					'multisite-network',
					'modified',
				),
				7012 => array(
					7012,
					WSAL_HIGH,
					esc_html__( 'Allow new registrations settings changed', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Allow new registrations</strong> setting to %new_setting%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous setting', 'wp-security-audit-log' ) => '%previous_setting%',
					),
					array(),
					'multisite-network',
					'modified',
				),
				7013 => array(
					7013,
					WSAL_HIGH,
					esc_html__( 'Sub site was updated', 'wp-security-audit-log' ),
					__( 'Updated the network site %SiteName% version to %NewVersion%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'URL', 'wp-security-audit-log' ) => '%BlogURL%',
					),
					array(),
					'multisite-network',
					'updated',
				),
			);
		}
	}
}
