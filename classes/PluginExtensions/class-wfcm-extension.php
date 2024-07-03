<?php
/**
 * WFCM extension class.
 *
 * @package    wsal
 * @subpackage add-ons
 */

declare(strict_types=1);

namespace WSAL\PluginExtensions;

if ( ! class_exists( '\WSAL\PluginExtensions\WFCM_Extension' ) ) {
	/**
	 * Class provides basic information about WSAL extension for WFCM.
	 *
	 * @package    wsal
	 * @subpackage add-ons
	 */
	class WFCM_Extension {
		/**
		 * Inits the extension hooks.
		 *
		 * @return void
		 *
		 * @since 4.5.0
		 */
		public static function init() {
			add_filter( 'append_dailynotification_email_content', array( __CLASS__, 'append_dailynotification_email_content' ), 10, 2 );
		}

		/**
		 * Add our extension to the array of installable extensions.
		 *
		 * @return array
		 *
		 * @since 4.5.0
		 */
		public static function filter_installable_plugins(): array {
			$new_plugin = array(
				array(
					'addon_for'          => 'wfcm',
					'title'              => self::get_plugin_name(),
					'image_filename'     => 'wfcm.png',
					'plugin_slug'        => self::get_plugin_filename(),
					'plugin_basename'    => 'website-file-changes-monitor.php',
					'plugin_url'         => 'https://downloads.wordpress.org/plugin/website-file-changes-monitor.latest-stable.zip',
					'event_tab_id'       => '#cat-wfcm',
					'plugin_description' => 'To keep a log of file changes please install Website File Changes Monitor, a plugin which is also developed by us.',
				),
			);

			// combine the two arrays.
			return $new_plugin;
		}

		/**
		 * Retrieves a plugin name.
		 *
		 * @return string Plugin name.
		 *
		 * @since 5.1.0
		 */
		public static function get_plugin_name(): string {
			return 'Website File Changes Monitor';
		}

		/**
		 * Gets a plugin icon URL.
		 *
		 * @return string Plugin icon URL.
		 *
		 * @since 5.1.0
		 */
		public static function get_plugin_icon_url(): string {
			return 'https://ps.w.org/website-file-changes-monitor/assets/icon-128x128.png?rev=2393849';
		}

		/**
		 * Retrieves the color to use when showing some info about the extension.
		 *
		 * @return string HEX color.
		 *
		 * @since 4.5.0
		 */
		public static function get_color(): string {
			return '#a4286a';
		}

		/**
		 * Gets the filename of the plugin this extension is targeting.
		 *
		 * @return string Filename.
		 *
		 * @since 4.5.0
		 */
		public static function get_plugin_filename(): string {
			return 'website-file-changes-monitor/website-file-changes-monitor.php';
		}

		/**
		 * Appends content to the daily notification email content.
		 *
		 * @param string $body   Email body (text).
		 * @param array  $events Events.
		 *
		 * @return string
		 * 
		 * @since 5.1.0
		 */
		public static function append_dailynotification_email_content( $body, $events ): string {
			if ( ! empty( $events ) ) {
				foreach ( $events as $event ) {
					if ( 6028 === (int) $event['alert_id'] ) {
						$files_modified[] = $event;
					} elseif ( 6029 === (int) $event['alert_id'] ) {
						$files_added[] = $event;
					} elseif ( 6030 === (int) $event['alert_id'] ) {
						$files_deleted[] = $event;
					}
				}
			}

			$media_check = trailingslashit( WSAL_BASE_URL ) . 'img/mails/daily-notification/alert-icon.png';

			if ( class_exists( 'Website_File_Changes_Monitor' ) ) {
				// File changes.
				if ( ! empty( $files_added ) || ! empty( $files_modified ) || ! empty( $files_deleted ) ) {
					$body .= '<!-- Website File Changes Start --><tr><td><table width="100%" cellpadding="0" cellspacing="0" border="0"><!-- Title Start --><tr><td style="font-family: Verdana, sans-serif; font-weight: bold; font-size: 20px; line-height: 28px; color: #404040; text-align: left; padding-bottom: 13px;">Website File Changes</td></tr><!-- Title End --><!-- Desc Start --><!-- Table Border Start --><tr><td style="padding-bottom: 40px;"><table width="100%" cellpadding="0" cellspacing="0" border="0">';

					$body .= '<tr><td style="font-family: Verdana, sans-serif; font-weight: normal; font-size: 16px; line-height: 28px; color: #404040;padding-bottom: 20px;">During the last file integrity scan on ' . date('d/m/Y', get_option('wfcm_last-scan-timestamp')) . ' at ' . date('H:i:s', get_option('wfcm_last-scan-timestamp')) . ' we detected the following file changes:</td></tr>'; // phpcs:disable

					if (! empty($files_added)) {
						$body .= '<tr><td style="font-family: Verdana, sans-serif; font-weight: normal; font-size: 16px; line-height: 28px; color: #404040;padding-bottom: 5px;"><img src="' . $media_check . '" style="height: 14px; width: 14px; position: relative; top: 1px;" /> New files identified</td></tr>';
					}

					if (! empty($files_modified)) {
						$body .= '<tr><td style="font-family: Verdana, sans-serif; font-weight: normal; font-size: 16px; line-height: 28px; color: #404040;padding-bottom: 5px;padding-top: 0px;"><img src="' . $media_check . '" style="height: 14px; width: 14px; position: relative; top: 1px;" /> Some files were changed</td></tr>';
					}

					if (! empty($files_deleted)) {
						$body .= '<tr><td style="font-family: Verdana, sans-serif; font-weight: normal; font-size: 16px; line-height: 28px; color: #404040;padding-bottom: 5px;padding-top: 0px;"><img src="' . $media_check . '" style="height: 14px; width: 14px; position: relative; top: 1px;" /> Some files were deleted</td></tr>';
					}

					$body .= '<tr><td style="font-family: Verdana, sans-serif; font-weight: normal; font-size: 16px; line-height: 28px; color: #404040;padding-bottom: 20px;padding-top: 20px;">Click <a href="' . add_query_arg('page', 'wfcm-file-changes', \network_admin_url('admin.php')) . '" target="_blank" style="font-family: Verdana, sans-serif; font-weight: normal; font-size: 16px; line-height: 28px; color: #149247;">here</a> to see the file changes.</td></tr>';

					$body .= '</table></td></tr><!-- Table Border Start --><!-- Desc End --></table></td></tr><!-- Website File Changes End -->';
				}

				// No changes to report.
				if (empty($files_added) && empty($files_modified) && empty($files_deleted) && class_exists('Website_File_Changes_Monitor')) {
					$body .= '<!-- Website File Changes Start --><tr><td><table width="100%" cellpadding="0" cellspacing="0" border="0"><!-- Title Start --><tr><td style="font-family: Verdana, sans-serif; font-weight: bold; font-size: 20px; line-height: 28px; color: #404040; text-align: left; padding-bottom: 13px;">Website File Changes</td></tr><!-- Title End --><!-- Desc Start --><!-- Table Border Start --><tr><td style="padding-bottom: 40px;"><table width="100%" cellpadding="0" cellspacing="0" border="0">';

					$body .= '<tr><td style="font-family: Verdana, sans-serif; font-weight: normal; font-size: 16px; line-height: 28px; color: #404040;padding-bottom: 5px;">Everything is looking good. No file changes detected during the last scan that ran on ' . date('d/m/Y', get_option('wfcm_last-scan-timestamp')) . ' at ' . date('H:i:s', get_option('wfcm_last-scan-timestamp')) . '.</td></tr>';

					$body .= '</table></td></tr><!-- Table Border Start --><!-- Desc End --></table></td></tr><!-- Website File Changes End -->';
				}
			}

			// No WFCM plugin found.
			if (! class_exists('Website_File_Changes_Monitor')) {
				$body .= '<!-- Website File Changes Start --><tr><td><table width="100%" cellpadding="0" cellspacing="0" border="0"><!-- Title Start --><tr><td style="font-family: Verdana, sans-serif; font-weight: bold; font-size: 20px; line-height: 28px; color: #404040; text-align: left; padding-bottom: 13px;">Website File Changes</td></tr><!-- Title End --><!-- Desc Start --><!-- Table Border Start --><tr><td style="padding-bottom: 40px;"><table width="100%" cellpadding="0" cellspacing="0" border="0">';

				$body .= '<tr><td style="font-family: Verdana, sans-serif; font-weight: normal; font-size: 16px; line-height: 28px; color: #404040;padding-bottom: 20px;padding-top: 0px;"><img src="' . $media_check . '" style="height: 14px; width: 14px; position: relative; top: 1px;" /> To be alerted of file changes install the <a href="https://www.wpwhitesecurity.com/wordpress-plugins/website-file-changes-monitor/" target="_blank" style="font-family: Verdana, sans-serif; font-weight: normal; font-size: 16px; line-height: 28px; color: #149247;">Website File Changes Monitor</a>, a plugin we developed to detect file changes. Once installed, the plugin fully integrates with WP Activity Log.</td></tr>';

				$body .= '</table></td></tr><!-- Table Border Start --><!-- Desc End --></table></td></tr><!-- Website File Changes End -->';
			}

			return $body;
		}
	}
}
