<?php
/**
 * WFCM extension class.
 *
 * @package    wsal
 * @subpackage add-ons
 */

if ( ! class_exists( 'WSAL_WFCMExtension' ) ) {

	/**
	 * Class provides basic information about WSAL extension for WFCM.
	 *
	 * @package    wsal
	 * @subpackage add-ons
	 */
	class WSAL_WFCMExtension extends WSAL_AbstractExtension {

		/**
		 * {@inheritDoc}
		 */
		public function __construct() {
			add_filter( 'append_dailynotification_email_content', array( $this, 'append_dailynotification_email_content' ), 10, 2 );
		}

		/**
		 * {@inheritDoc}
		 */
		public function filter_installable_plugins( $plugins ) {
			$new_plugin = array(
				array(
					'addon_for'          => 'wfcm',
					'title'              => $this->get_plugin_name(),
					'image_filename'     => 'wfcm.png',
					'plugin_slug'        => $this->get_plugin_filename(),
					'plugin_basename'    => 'website-file-changes-monitor.php',
					'plugin_url'         => 'https://downloads.wordpress.org/plugin/website-file-changes-monitor.latest-stable.zip',
					'event_tab_id'       => '#cat-wfcm',
					'plugin_description' => 'To keep a log of file changes please install Website File Changes Monitor, a plugin which is also developed by us.',
				),
			);

			// combine the two arrays.
			return array_merge( $plugins, $new_plugin );
		}

		/**
		 * {@inheritDoc}
		 */
		public function add_event_codes( $addon_event_codes ) {
			$new_event_codes = array(
				'wfcm' => array(
					'name'      => $this->get_plugin_name(),
					'event_ids' => array( 6028, 6029, 6030, 6031, 6032, 6033 ),
				),
			);

			// combine the two arrays.
			return array_merge( $addon_event_codes, $new_event_codes );
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_plugin_name() {
			return 'Website File Changes Monitor';
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_plugin_icon_url() {
			return 'https://ps.w.org/website-file-changes-monitor/assets/icon-128x128.png?rev=2393849';
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_color() {
			return '#a4286a';
		}

		/**
		 * Appends content to the daily notification email content.
		 *
		 * @param string $body   Email body (text).
		 * @param array  $events Events.
		 *
		 * @return string
		 */
		public function append_dailynotification_email_content( $body, $events ) {

			if ( ! empty( $events ) ) {
				foreach ( $events as $event ) {
					if ( 6028 === $event->alert_id ) {
						$files_modified[] = $event;
					} elseif ( 6029 === $event->alert_id ) {
						$files_added[] = $event;
					} elseif ( 6030 === $event->alert_id ) {
						$files_deleted[] = $event;
					}
				}
			}

			if ( class_exists( 'Website_File_Changes_Monitor' ) ) {
				// File changes.
				if ( ! empty( $files_added ) || ! empty( $files_modified ) || ! empty( $files_deleted ) ) {
					$body .= '<!-- Website File Changes Start --><tr><td><table width="100%" cellpadding="0" cellspacing="0" border="0"><!-- Title Start --><tr><td style="font-family: Verdana, sans-serif; font-weight: bold; font-size: 20px; line-height: 28px; color: #404040; text-align: left; padding-bottom: 13px;">Website File Changes</td></tr><!-- Title End --><!-- Desc Start --><!-- Table Border Start --><tr><td style="padding-bottom: 40px;"><table width="100%" cellpadding="0" cellspacing="0" border="0">';

					$body .= '<tr><td style="font-family: Verdana, sans-serif; font-weight: normal; font-size: 16px; line-height: 28px; color: #404040;padding-bottom: 20px;">During the last file integrity scan on ' . date( 'd/m/Y', get_option( 'wfcm_last-scan-timestamp' ) ) . ' at ' . date( 'H:i:s', get_option( 'wfcm_last-scan-timestamp' ) ) . ' we detected the following file changes:</td></tr>'; // phpcs:disable

					if ( ! empty( $files_added ) ) {
						$body .= '<tr><td style="font-family: Verdana, sans-serif; font-weight: normal; font-size: 16px; line-height: 28px; color: #404040;padding-bottom: 5px;"><img src="' . $this->media['check'] . '" style="height: 14px; width: 14px; position: relative; top: 1px;" /> New files identified</td></tr>';
					}

					if ( ! empty( $files_modified ) ) {
						$body .= '<tr><td style="font-family: Verdana, sans-serif; font-weight: normal; font-size: 16px; line-height: 28px; color: #404040;padding-bottom: 5px;padding-top: 0px;"><img src="' . $this->media['check'] . '" style="height: 14px; width: 14px; position: relative; top: 1px;" /> Some files were changed</td></tr>';
					}

					if ( ! empty( $files_deleted ) ) {
						$body .= '<tr><td style="font-family: Verdana, sans-serif; font-weight: normal; font-size: 16px; line-height: 28px; color: #404040;padding-bottom: 5px;padding-top: 0px;"><img src="' . $this->media['check'] . '" style="height: 14px; width: 14px; position: relative; top: 1px;" /> Some files were deleted</td></tr>';
					}

					$body .= '<tr><td style="font-family: Verdana, sans-serif; font-weight: normal; font-size: 16px; line-height: 28px; color: #404040;padding-bottom: 20px;padding-top: 20px;">Click <a href="' . add_query_arg( 'page', 'wfcm-file-changes', admin_url( 'admin.php' ) ) . '" target="_blank" style="font-family: Verdana, sans-serif; font-weight: normal; font-size: 16px; line-height: 28px; color: #149247;">here</a> to see the file changes.</td></tr>';

					$body .= '</table></td></tr><!-- Table Border Start --><!-- Desc End --></table></td></tr><!-- Website File Changes End -->';
				}

				// No changes to report.
				if ( empty( $files_added ) && empty( $files_modified ) && empty( $files_deleted ) && class_exists( 'Website_File_Changes_Monitor' ) ) {
					$body .= '<!-- Website File Changes Start --><tr><td><table width="100%" cellpadding="0" cellspacing="0" border="0"><!-- Title Start --><tr><td style="font-family: Verdana, sans-serif; font-weight: bold; font-size: 20px; line-height: 28px; color: #404040; text-align: left; padding-bottom: 13px;">Website File Changes</td></tr><!-- Title End --><!-- Desc Start --><!-- Table Border Start --><tr><td style="padding-bottom: 40px;"><table width="100%" cellpadding="0" cellspacing="0" border="0">';

					$body .= '<tr><td style="font-family: Verdana, sans-serif; font-weight: normal; font-size: 16px; line-height: 28px; color: #404040;padding-bottom: 5px;">Everything is looking good. No file changes detected during the last scan that ran on ' . date( 'd/m/Y', get_option( 'wfcm_last-scan-timestamp' ) ) . ' at ' . date( 'H:i:s', get_option( 'wfcm_last-scan-timestamp' ) ) . '.</td></tr>';

					$body .= '</table></td></tr><!-- Table Border Start --><!-- Desc End --></table></td></tr><!-- Website File Changes End -->';
				}
			}

			// No WFCM plugin found.
			if ( ! class_exists( 'Website_File_Changes_Monitor' ) ) {
				$body .= '<!-- Website File Changes Start --><tr><td><table width="100%" cellpadding="0" cellspacing="0" border="0"><!-- Title Start --><tr><td style="font-family: Verdana, sans-serif; font-weight: bold; font-size: 20px; line-height: 28px; color: #404040; text-align: left; padding-bottom: 13px;">Website File Changes</td></tr><!-- Title End --><!-- Desc Start --><!-- Table Border Start --><tr><td style="padding-bottom: 40px;"><table width="100%" cellpadding="0" cellspacing="0" border="0">';

				$body .= '<tr><td style="font-family: Verdana, sans-serif; font-weight: normal; font-size: 16px; line-height: 28px; color: #404040;padding-bottom: 20px;padding-top: 0px;"><img src="' . $this->media['check'] . '" style="height: 14px; width: 14px; position: relative; top: 1px;" /> To be alerted of file changes install the <a href="https://www.wpwhitesecurity.com/wordpress-plugins/website-file-changes-monitor/" target="_blank" style="font-family: Verdana, sans-serif; font-weight: normal; font-size: 16px; line-height: 28px; color: #149247;">Website File Changes Monitor</a>, a plugin we developed to detect file changes. Once installed, the plugin fully integrates with WP Activity Log.</td></tr>';

				$body .= '</table></td></tr><!-- Table Border Start --><!-- Desc End --></table></td></tr><!-- Website File Changes End -->';
			}

			return $body;
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_plugin_filename() {
			return 'website-file-changes-monitor/website-file-changes-monitor.php';
		}
	}
}
