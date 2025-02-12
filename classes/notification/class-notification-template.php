<?php
/**
 * Class: Notification template.
 *
 * Logger class for wsal.
 *
 * @since 5.3.0
 *
 * @package    wsal
 * @subpackage helpers
 */

namespace WSAL\Extensions\Helpers;

use WSAL\Helpers\WP_Helper;
use WSAL\Helpers\User_Helper;
use WSAL\Views\Notifications;
use WSAL\Helpers\Settings_Helper;
use WSAL\Controllers\Alert_Manager;
use WSAL\Helpers\Plugin_Settings_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Notifications Helper class
 */
if ( ! class_exists( '\WSAL\Extensions\Helpers\Notification_Template' ) ) {
	/**
	 * This class triggers notifications if set.
	 *
	 * @package    wsal
	 * @subpackage helpers
	 *
	 * @since 5.3.0
	 */
	class Notification_Template {

		/**
		 * User Data.
		 *
		 * @since 5.3.0
		 *
		 * @var array
		 */
		private static $user_data = array();

		/**
		 * Generate Report Body.
		 *
		 * @param WSAL_Models_Occurrence[] $events       - Array of events.
		 * @param string                   $report_date  - Date of report.
		 * @param int                      $total_events - Number of events.
		 * @param string                   $report_wsal_start_date - The start date of the report.
		 * @param bool|string              $report_wsal_end_date - End date (if present - if that is a weekly report - there should be one).
		 *
		 * @since 5.3.0
		 */
		public static function generate_report_body( $events, $report_date, $total_events, $report_wsal_start_date, $report_wsal_end_date = false ): string {
			$header = '
			<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
			<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
			<head>
				<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
				<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
				<title>WP Activity Log</title>
			
				<!--[if mso]>
					<style>
					  body,table,td,h2,h3,span,p {
					  font-family: \'Quicksand\', \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif !important;
					  }
					</style>
				<![endif]-->
			
				<link rel="preconnect" href="https://fonts.googleapis.com"/>
				<link rel="preconnect" href="https://fonts.gstatic.com"/>
				<link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300..700&display=swap" rel="stylesheet"/>
			
				<style type="text/css">
					html, body {
						margin: 0 auto !important;
						padding: 0 !important;
						height: 100% !important;
						width: 100% !important;
					}
			
					/* Override blue links in footer */
					.footer-text a[x-apple-data-detectors] {
						color: #ffffff !important;
						font-size: inherit !important;
						font-family: inherit !important;
						font-weight: inherit !important;
						line-height: inherit !important;
					}
			
					u+#body .footer-text a {
						color: #ffffff !important;
						font-size: inherit !important;
						font-family: inherit !important;
						font-weight: inherit !important;
						line-height: inherit !important;
					}
			
					#MessageViewBody .footer-text a {
						color: #ffffff !important;
						font-size: inherit !important;
						font-family: inherit !important;
						font-weight: inherit !important;
						line-height: inherit !important;
					}
			
					table, td {
						border-spacing: 0;
						mso-table-lspace: 0;
						mso-table-rspace: 0;
					}
			
					img {
						border: 0;
						height: auto;
						line-height: 100%;
						outline: none;
						text-decoration: none;
						-ms-interpolation-mode: bicubic;
					}
			
					a {
						color: #0000EE;
						text-decoration: underline;
					}
			
					a:hover, a:hover img {
						opacity: 0.5;
						filter: alpha(opacity=50);
						transition: opacity .2s ease-in-out;
					}
			
					.applelink-white a {
						color: #ffffff !important;
					}
					
					/* Zebra striping for tables */
					table.zebra-striped tr:nth-child(even) {
					  background-color: #F0F4FE;
					}
					
					table.zebra-striped tr:nth-child(odd) {
					  background-color: #ffffff;
					}
			
					@media only screen and (max-width: 599px), only screen and (max-device-width: 599px) {
						.hide {
							display: none !important;
						}
			
						.responsive-full {
							width: 100% !important;
							min-width: 100% !important;
						}
			
						.responsive {
							width: 100% !important;
							min-width: 100% !important;
							padding-left: 30px !important;
							padding-right: 30px !important;
						}
			
						.inner-td {
							padding-bottom: 60px !important;
						}
			
						.responsive-image img {
							width: 50%% !important;
							min-width: 50%% !important;
						}
			
						.responsive-icon img {
							width: 48px !important;
							min-width: 48px !important;
						}
			
						.responsive-stack {
							width: 100% !important;
							display: block !important;
						}
			
						.mob-body-text {
							font-size: 20px !important;
							line-height: 28px !important;
						}
			
						.mob-title-text {
							font-size: 40px !important;
							line-height: 56px !important;
						}
			
						.center {
							text-align: center !important;
							padding-bottom: 15px !important;
						}
					}
				</style>
			</head>
			
			<body style="margin:0;padding:0;min-width:100%;background-color:#ffffff;font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-size:18px;line-height:24px;color:#1A3060;font-weight: 400;" id="body" class="body">
			';

			$footer = '</body></html>';

			$media['wp-activity-log']   = trailingslashit( WSAL_BASE_URL ) . 'img/mails/daily-notification/wp-activity-log.png';
			$media['documentation']     = trailingslashit( WSAL_BASE_URL ) . 'img/mails/daily-notification/documentation.png';
			$media['support']           = trailingslashit( WSAL_BASE_URL ) . 'img/mails/daily-notification/support.png';
			$media['melapress-icon']    = trailingslashit( WSAL_BASE_URL ) . 'img/mails/daily-notification/melapress-icon@2x.png';
			$media['wsal-dg-footer-bg'] = trailingslashit( WSAL_BASE_URL ) . 'img/mails/daily-notification/wsal-dg-footer-bg.png';

			$current_settings = Settings_Helper::get_option_value( Notifications::NOTIFICATIONS_SETTINGS_NAME, array() );

			$home_url = \home_url();
			$safe_url = str_replace( array( 'http://', 'https://' ), '', $home_url );

			$number_of_logins         = 0;       // Number of logins.
			$login_events             = array(); // Login events.
			$failed_logins_wrong_pass = array(); // Failed logins wrong pass.
			$failed_logins_wrong_user = array(); // Failed logins wrong user.
			$password_changes         = array(); // Password changes.
			$forced_password_changes  = array(); // Forced password changes.
			$user_profile_changes     = array(); // User profile changes.
			$multisite_activity       = array(); // Multisite network activity.
			$plugin_activity          = array(); // Plugin activity.
			$system_activity          = array(); // System activity.
			$posts_published          = array(); // Posts published.
			$posts_trashed            = array(); // Posts trashed.
			$posts_deleted            = array(); // Posts deleted.
			$posts_modified           = array(); // Posts modified.
			$posts_status_changed     = array(); // Posts status changes.
			$files_added              = array(); // Files added.
			$files_modified           = array(); // Files modified.
			$files_deleted            = array(); // Files deleted.

			$login_events_ids                = array( 1000, 1005 ); // Login events.
			$password_changes_events         = array( 4003 ); // Login events.
			$forced_password_changes_events  = array( 4004 ); // Login events.
			$failed_logins_events            = array( 1002, 1003 ); // Failed login events.
			$failed_logins_wrong_pass_events = array( 1002 ); // Failed login events.
			$failed_logins_wrong_user_events = array( 1003 ); // Failed login events.
			$posts_published_events          = array( 2001 ); // Login events.
			$posts_trashed_events            = array( 2012 ); // Login events.
			$posts_deleted_events            = array( 2008 ); // Login events.
			$posts_modified_events           = array( 2065 ); // Login events.
			$posts_status_changed_events     = array( 2021 ); // Login events.
			$system_activity_events          = \array_keys( Alert_Manager::get_alerts_by_category( esc_html__( 'WordPress & System', 'wp-security-audit-log' ) ) );
			$plugin_events                   = array( 5000, 5001, 5002, 5003, 5004 ); // Plugin events.
			$user_profile_events             = array( 4000, 4001, 4002, 4007 ); // Multisite events.
			$multisite_events                = array( 4010, 4011, 7000, 7001, 7002, 7003, 7004, 7005 ); // Multisite events.

			if ( ! empty( $events ) ) {
				foreach ( $events as $event ) {
					if ( in_array( (int) $event['alert_id'], $login_events_ids, true ) ) {
						++$number_of_logins;
						$login_events[] = $event;
					} elseif ( 1002 === (int) $event['alert_id'] ) {
						$failed_logins_wrong_pass[] = $event;
					} elseif ( 1003 === (int) $event['alert_id'] ) {
						$failed_logins_wrong_user[] = $event;
					} elseif ( 4003 === (int) $event['alert_id'] ) {
						$password_changes[] = $event;
					} elseif ( 4004 === (int) $event['alert_id'] ) {
						$forced_password_changes[] = $event;
					} elseif ( in_array( (int) $event['alert_id'], $plugin_events, true ) ) {
						$plugin_activity[] = $event;
					} elseif ( in_array( (int) $event['alert_id'], $system_activity_events, true ) ) {
						$system_activity[] = $event;
					} elseif ( 2001 === (int) $event['alert_id'] ) {
						$posts_published[] = $event;
					} elseif ( 2012 === (int) $event['alert_id'] ) {
						$posts_trashed[] = $event;
					} elseif ( 2008 === (int) $event['alert_id'] ) {
						$posts_deleted[] = $event;
					} elseif ( 2065 === (int) $event['alert_id'] ) {
						$posts_modified[] = $event;
					} elseif ( in_array( (int) $event['alert_id'], $posts_status_changed_events, true ) ) {
						$posts_status_changed[] = $event;
					} elseif ( in_array( (int) $event['alert_id'], $user_profile_events, true ) ) {
						$user_profile_changes[] = $event;
					} elseif ( in_array( (int) $event['alert_id'], $multisite_events, true ) ) {
						$multisite_activity[] = $event;
					} elseif ( 6028 === (int) $event['alert_id'] ) {
						$files_modified[] = $event;
					} elseif ( 6029 === (int) $event['alert_id'] ) {
						$files_added[] = $event;
					} elseif ( 6030 === (int) $event['alert_id'] ) {
						$files_deleted[] = $event;
					}
				}
			}

			$body = '
			<!-- Main Content Start -->
			<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" style="min-width: 100%;" role="presentation">
				<tr>
					<td align="center">
						
						<!-- Logo Start -->
						<table role="presentation" width="640" border="0" cellpadding="0" cellspacing="0" role="presentation" class="responsive">
							<tr>
								<td align="center" style="padding: 0 6px;">
									<table role="presentation" align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="min-width: 100%;">
										<tr>
											<td align="center" style="padding: 20px 0 15px;">
												<a href="https://melapress.com/wordpress-activity-log/" style="color:#1A3060; font-weight: 700;" target="_blank">
													<img src="' . $media['wp-activity-log'] . '" border="0" width="280" height="41" style="display: block;" alt="WP Activity Log"/>
												</a>
											</td>
										</tr>
									</table>
								</td>
							</tr>
						</table>
						<!-- Logo End -->
						
						<!-- Title Start -->
						<table role="presentation" width="640" border="0" cellpadding="0" cellspacing="0" role="presentation" class="responsive">
							<tr>
								<td align="center">
									<table role="presentation" align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="min-width: 100%;">
										<tr>
											<td align="center" valign="top" style="font-family: \'Quicksand\', \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-size:41px;line-height:56px;color:#1A3060;font-weight: 700;" class="mob-title-text">
												<h1 style="font-family: \'Quicksand\', \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-size:32px;line-height:42px;color:#8AAAF1;font-weight: 700;padding: 0;margin: 0;">Your website\'s daily<br> WordPress activity log highlights</h1>
											</td>
										</tr>
									</table>
								</td>
							</tr>
						</table>
						<!-- Title End -->
						
						<!-- Hello Start -->
						<table role="presentation" width="640" border="0" cellpadding="0" cellspacing="0" role="presentation" class="responsive">
						<tr>
							<td align="center">
								<table width="100%" cellpadding="0" cellspacing="0" border="0">
									<tr>
										<td style="font-family: \'Quicksand\',\'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: bold; font-size: 20px; line-height: 22px; color: #1A3060; text-align: left; padding-top: 30px; padding-bottom: 8px;">Hello,</td>
									</tr>
									<tr>
										<td style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; text-align: left; padding-bottom: 20px;">
			';

			if ( $report_wsal_end_date ) {
				$date_format  = Settings_Helper::get_date_format();
				$end          = strtotime( $report_wsal_end_date );
				$display_date = gmdate( $date_format, $end );

				$body .= sprintf( 'This email was sent from your <a href="%1$s" target="_blank" style="color: #404040; text-decoration: none; display: inline-block;">%2$s</a>. It is a summary generated by the <a href="https://wpactivitylog.com" target="_blank" style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 16px; line-height: 28px; color: #009344; text-decoration: underline; display: inline-block;">WP Activity Log plugin</a> about what happened between %3$s and %4$s.', $home_url, $safe_url, $report_date, $display_date );
			} else {
				$body .= sprintf( 'This email was sent from your <a href="%1$s" target="_blank" style="color: #404040; text-decoration: none; display: inline-block;">%2$s</a>. It is a summary generated by the <a href="https://wpactivitylog.com" target="_blank" style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 16px; line-height: 28px; color: #009344; text-decoration: underline; display: inline-block;">WP Activity Log plugin</a> about what happened on %3$s.', $home_url, $safe_url, $report_date );
			}

			$body .= '</td></tr><!-- Desc End -->';

			if ( empty( $events ) ) {
				$body .= '<tr><td style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 28px; color: #404040; text-align: left; padding-bottom: 34px;">No events so far.</td></tr><!-- Desc End -->';
			}

			$body .= '
										</td>
									</tr>
								</table>
							</td>
						</tr>
					</table>
					<!-- Hello End -->
			';

			if ( ! empty( $events ) ) {

				// User logins.
				if ( ( isset( $current_settings['notification_summary_user_logins'] ) && $current_settings['notification_summary_user_logins'] ) || ! isset( $current_settings['notification_summary_user_logins'] ) ) {
					if ( $number_of_logins && ! empty( $login_events ) ) {
						$user_logins = array();
						foreach ( $login_events as $login_event ) {
							$username = $login_event['username'];
							$ipaddr   = $login_event['client_ip'];

							if ( ! empty( $username ) && ! empty( $ipaddr ) ) {
								$user_logins[ $username ][] = $ipaddr;
							}
						}
						$login_count_string = sprintf(
							// translators: singular or plural form of a login total count.
							_n( 'was %d login', 'were %d logins', $number_of_logins, 'wp-security-audit-log' ),
							$number_of_logins
						);
						$users_logged_count = ( is_array( $user_logins ) && ! empty( $user_logins ) ) ? count( $user_logins ) : '1';
						$user_count_string  = sprintf(
							// translators: a number that is total count of unique users in a login group.
							_n( '%d unique user', '%d unique users', $users_logged_count, 'wp-security-audit-log' ),
							$users_logged_count
						);

						$body .= '
						<!-- User Logins Start -->
						<table role="presentation" width="640" border="0" cellpadding="0" cellspacing="0" role="presentation" class="responsive">
							<tr>
								<td align="center">
									<table width="100%" cellpadding="0" cellspacing="0" border="0">
										<tr>
											<td style="font-family: \'Quicksand\',\'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: bold; font-size: 20px; line-height: 22px; color: #1A3060; text-align: left; padding-top: 24px; padding-bottom: 8px;">User Logins</td>
										</tr>
										<tr>
											<td style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; text-align: left;">
						';
						$body .= sprintf(
							/* Translators: 1 - number of logins. 2 - total unique users */
							__( 'There %1$s on your site from %2$s.', 'wp-security-audit-log' ),
							$login_count_string,
							$user_count_string
						);

						$body .= '<div>';

						if ( 'free' === \WpSecurityAuditLog::get_plugin_version() ) {
							$body .= '<a href="https://melapress.com/wordpress-activity-log/pricing/#utm_source=wpal_email&utm_medium=email&utm_campaign=product_email&utm_content=cta_main" target="_blank" style="color: #009344;  display: inline-block;">' . __( 'Upgrade to Premium to see these events specifically', 'wp-security-audit-log' ) . '</a>';
						} else {
							$filters_string = '';
							foreach ( $login_events_ids as $key => $event_id ) {
								$filters_string .= '&filters%5B' . $key . '%5D=event%3A' . $event_id;
							}

							if ( $report_wsal_end_date ) {
								$filters_string .= '&filters%5B' . ( $key + 1 ) . '%5D=from%3A' . $report_wsal_start_date;
								$filters_string .= '&filters%5B' . ( $key + 3 ) . '%5D=to%3A' . $report_wsal_end_date;
							} else {
								$filters_string .= '&filters%5B' . ( $key + 1 ) . '%5D=on%3A' . $report_wsal_start_date;
							}
							$body .= '<a href="' . \WpSecurityAuditLog::get_plugin_admin_url_page() . '&wsal-cbid=-1' . $filters_string . '" target="_blank" style="color: #009344;  display: inline-block;">' . __( 'Show me all these events', 'wp-security-audit-log' ) . '</a>';
						}

						$body .= '</div>';

						if ( ( isset( $current_settings['notification_summary_number_of_events_included'] ) && $current_settings['notification_summary_number_of_events_included'] ) ) {
							$body .= __( 'Below is a list of the users and the IP addresses they logged in from:', 'wp-security-audit-log' );
						}

						$body .= '</td></tr>';

						if ( ( isset( $current_settings['notification_summary_number_of_events_included'] ) && $current_settings['notification_summary_number_of_events_included'] ) ) {

							$body .= '<tr>
							<td style="padding-top: 20px; padding-bottom: 40px;">
								<table class="zebra-striped" width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid #D9E4FD; border-radius: 2px;">';

							if ( ! empty( $user_logins ) ) {
								$user_logins = array_slice( $user_logins, 0, Notification_Helper::NUMBER_OF_EVENTS_TO_INCLUDE );
								foreach ( $user_logins as $username => $ipaddrs ) {
									$ipaddr = array_unique( $ipaddrs );
									$ipaddr = implode( ',', $ipaddr );

									$body .= '<tr>';
									$body .= '<td style="border: 1px solid #D9E4FD; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; padding-left: 24px; padding-right: 24px; padding-top: 10px; padding-bottom: 10px;">';
									/* 1. Username 2. IP Address */
									$body .= sprintf( 'User %1$s from %2$s', '<span style="display: inline-block; color: #009344;">' . self::get_user_for_email( $username ) . '</span>', '<span style="display: inline-block; color: #009344;">' . $ipaddr . '</span>' );
									$body .= '</td></tr>';
								}
								$body .= '</table></td>';
							}

							$body .= '
										</tr>
									</table>
								</td>
							</tr>
						</table>
						<!-- User Logins End -->
						';
						}
					}

					// Failed user logins.
					if ( ( isset( $current_settings['notification_summary_failed_logins'] ) && $current_settings['notification_summary_failed_logins'] ) || ! isset( $current_settings['notification_summary_failed_logins'] ) ) {
						if ( ! empty( $failed_logins_wrong_pass ) || ! empty( $failed_logins_wrong_user ) ) {
							$body .= '
							<!-- Failed User Logins Start -->
							<table role="presentation" width="640" border="0" cellpadding="0" cellspacing="0" role="presentation" class="responsive">
								<tr>
									<td align="center">
										<table width="100%" cellpadding="0" cellspacing="0" border="0">
											<tr>
												<td style="font-family: \'Quicksand\',\'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: bold; font-size: 20px; line-height: 22px; color: #1A3060; text-align: left; padding-top: 24px; padding-bottom: 8px;">Failed User Logins</td>
											</tr>
											<tr>
												<td style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; text-align: left;">';

							/*
							 * Logs when logins were attempted that used the wrong password.
							 * Displays a message and a <table> of IPs.
							 */
							if ( ( isset( $current_settings['notification_wrong_password'] ) && $current_settings['notification_wrong_password'] ) || ! isset( $current_settings['notification_wrong_password'] ) ) {
								if ( ! empty( $failed_logins_wrong_pass ) ) {
									$user_failed_count = count( $failed_logins_wrong_pass );
									$user_failed_pass  = sprintf(
										// translators: a number that is total count of unique users in a login group.
										_n( '%d failed login due to a wrong password', '%d failed logins due to a wrong passwords', $user_failed_count, 'wp-security-audit-log' ),
										$user_failed_count
									);

									$body .= sprintf(
										/* Translators: 1 - number of logins. 2 - total unique users */
										__( 'There were %1$s on your site.', 'wp-security-audit-log' ),
										$user_failed_pass
									);

									$body .= '<div>';

									if ( 'free' === \WpSecurityAuditLog::get_plugin_version() ) {
										$body .= '<a href="https://melapress.com/wordpress-activity-log/pricing/#utm_source=wpal_email&utm_medium=email&utm_campaign=product_email&utm_content=cta_main" target="_blank" style="color: #009344;  display: inline-block;">' . __( 'Upgrade to Premium to see these events specifically', 'wp-security-audit-log' ) . '</a>';
									} else {
										$filters_string = '';
										foreach ( $failed_logins_wrong_pass_events as $key => $event_id ) {
											$filters_string .= '&filters%5B' . $key . '%5D=event%3A' . $event_id;
										}

										if ( $report_wsal_end_date ) {
											$filters_string .= '&filters%5B' . ( $key + 1 ) . '%5D=from%3A' . $report_wsal_start_date;
											$filters_string .= '&filters%5B' . ( $key + 3 ) . '%5D=to%3A' . $report_wsal_end_date;
										} else {
											$filters_string .= '&filters%5B' . ( $key + 1 ) . '%5D=on%3A' . $report_wsal_start_date;
										}
										$body .= '<a href="' . \WpSecurityAuditLog::get_plugin_admin_url_page() . '&wsal-cbid=-1' . $filters_string . '"  target="_blank" style="color: #009344;  display: inline-block;">' . __( 'Show me all these events', 'wp-security-audit-log' ) . '</a>';
									}

									$body .= '</div>';

									if ( ( isset( $current_settings['notification_summary_number_of_events_included'] ) && $current_settings['notification_summary_number_of_events_included'] ) ) {
										$body .= '<tr><td style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; text-align: left;">';
										$body .= esc_html__( 'They are from the following IP addresses:', 'wp-security-audit-log' );
										$body .= '</td></tr>';
										$body .= '<tr>
											<td style="padding-top: 20px; padding-bottom: 40px;">
												<table class="zebra-striped" width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid #D9E4FD; border-radius: 2px;">';

										$failed_logins_wrong_pass = array_slice( $failed_logins_wrong_pass, 0, Notification_Helper::NUMBER_OF_EVENTS_TO_INCLUDE );

										$previous_ips = array();
										foreach ( $failed_logins_wrong_pass as $event ) {
											$current_ip = $event['client_ip'];
											if ( ! in_array( $current_ip, $previous_ips, true ) ) {
												$body          .= '<tr><td style="border: 1px solid #D9E4FD; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; padding-left: 24px; padding-right: 24px; padding-top: 10px; padding-bottom: 10px;"><span style="display: inline-block; color: #009344;">' . $current_ip . '</span></td></tr>';
												$previous_ips[] = $current_ip;
											}
										}

										$body .= '</table>
											</td>
										</tr>';
									}
								}
							}

							/*
							 * Logs when logins were attempted that used the wrong username.
							 * Displays a message and a <table> of IPs.
							 */
							if ( ( isset( $current_settings['notification_summary_wrong_username'] ) && $current_settings['notification_summary_wrong_username'] ) || ! isset( $current_settings['notification_summary_wrong_username'] ) ) {
								if ( ! empty( $failed_logins_wrong_user ) ) {
									$body                          .= '<tr>
										<td style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; text-align: left;">';
										$user_failed_username_count = count( $failed_logins_wrong_user );
										$user_failed_pass           = sprintf(
											// translators: a number that is total count of unique users in a login group.
											_n( '%d failed login due to a wrong username', '%d failed logins due to a wrong usernames', $user_failed_username_count, 'wp-security-audit-log' ),
											$user_failed_username_count
										);

										$body .= sprintf(
											/* Translators: 1 - number of logins. 2 - total unique users */
											__( 'There were %1$s on your site.', 'wp-security-audit-log' ),
											$user_failed_pass
										);

										$body .= '<div>';

									if ( 'free' === \WpSecurityAuditLog::get_plugin_version() ) {
										$body .= '<a href="https://melapress.com/wordpress-activity-log/pricing/#utm_source=wpal_email&utm_medium=email&utm_campaign=product_email&utm_content=cta_main" target="_blank" style="color: #009344;  display: inline-block;">' . __( 'Upgrade to Premium to see these events specifically', 'wp-security-audit-log' ) . '</a>';
									} else {
										$filters_string = '';
										foreach ( $failed_logins_wrong_user_events as $key => $event_id ) {
											$filters_string .= '&filters%5B' . $key . '%5D=event%3A' . $event_id;
										}

										if ( $report_wsal_end_date ) {
											$filters_string .= '&filters%5B' . ( $key + 1 ) . '%5D=from%3A' . $report_wsal_start_date;
											$filters_string .= '&filters%5B' . ( $key + 3 ) . '%5D=to%3A' . $report_wsal_end_date;
										} else {
											$filters_string .= '&filters%5B' . ( $key + 1 ) . '%5D=on%3A' . $report_wsal_start_date;
										}
										$body .= '<a href="' . \WpSecurityAuditLog::get_plugin_admin_url_page() . '&wsal-cbid=-1' . $filters_string . '"  target="_blank" style="color: #009344;  display: inline-block;">' . __( 'Show me all these events', 'wp-security-audit-log' ) . '</a>';
									}

										$body .= '</div>';
									$body     .= '</td></tr>';

									if ( ( isset( $current_settings['notification_summary_number_of_events_included'] ) && $current_settings['notification_summary_number_of_events_included'] ) ) {
										$body .= '<tr><td style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; text-align: left;">';
										$body .= esc_html__( 'They are from the following IP addresses:', 'wp-security-audit-log' );
										$body .= '</td></tr>';
										$body .= '<tr>
											<td style="padding-top: 20px; padding-bottom: 40px;">
												<table class="zebra-striped" width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid #D9E4FD; border-radius: 2px;">';

										$failed_logins_wrong_user = array_slice( $failed_logins_wrong_user, 0, Notification_Helper::NUMBER_OF_EVENTS_TO_INCLUDE );
										$previous_ips             = array();
										foreach ( $failed_logins_wrong_user as $event ) {
											$current_ip = $event['client_ip'];
											if ( ! in_array( $current_ip, $previous_ips, true ) ) {
												$body          .= '<tr><td style="border: 1px solid #D9E4FD; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; padding-left: 24px; padding-right: 24px; padding-top: 10px; padding-bottom: 10px;"><span style="display: inline-block; color: #009344;">' . $current_ip . '</span></td></tr>';
												$previous_ips[] = $current_ip;
											}
										}

										$body .= '</table>
											</td>
										</tr>';
									}
								}
							}

							$body .= '
										</table>
									</td>
								</tr>
							</table>
							<!-- Failed User Logins End -->';
						}
					}

					// Password changes.
					if ( ( isset( $current_settings['notification_summary_password_changes'] ) && $current_settings['notification_summary_password_changes'] ) || ! isset( $current_settings['notification_summary_password_changes'] ) ) {
						if ( ! empty( $password_changes ) || ! empty( $forced_password_changes ) ) {

							$body .= '
						<!-- Password Changes Start -->
						<table role="presentation" width="640" border="0" cellpadding="0" cellspacing="0" role="presentation" class="responsive">
							<tr>
								<td align="center">
									<table width="100%" cellpadding="0" cellspacing="0" border="0">
										<tr>
											<td style="font-family: \'Quicksand\',\'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: bold; font-size: 20px; line-height: 22px; color: #1A3060; text-align: left; padding-top: 24px; padding-bottom: 8px;">Password Changes</td>
										</tr>
						';

							if ( ( isset( $current_settings['notification_summary_password_user_change_own_password'] ) && $current_settings['notification_summary_password_user_change_own_password'] ) || ! isset( $current_settings['notification_summary_password_user_change_own_password'] ) ) {
								if ( ! empty( $password_changes ) ) {
									$password_changes_string = sprintf(
									// translators: singular or plural form of a login total count.
										_n( 'was %d password change on your site (user changed their own password)', 'were %d password changes on your site (user changed their own password)', count( $password_changes ), 'wp-security-audit-log' ),
										count( $password_changes )
									);
									$body .= '<tr>
									<td style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; text-align: left;">';
									$body .= sprintf(
									/* Translators: 1 - number of logins. 2 - total unique users */
										__( 'There %1$s on your site.', 'wp-security-audit-log' ),
										$password_changes_string
									);

									$body .= '<div>';

									if ( 'free' === \WpSecurityAuditLog::get_plugin_version() ) {
										$body .= '<a href="https://melapress.com/wordpress-activity-log/pricing/#utm_source=wpal_email&utm_medium=email&utm_campaign=product_email&utm_content=cta_main" target="_blank" style="color: #009344;  display: inline-block;">' . __( 'Upgrade to Premium to see these events specifically', 'wp-security-audit-log' ) . '</a>';
									} else {
										$filters_string = '';
										foreach ( $password_changes_events as $key => $event_id ) {
											$filters_string .= '&filters%5B' . $key . '%5D=event%3A' . $event_id;
										}

										if ( $report_wsal_end_date ) {
											$filters_string .= '&filters%5B' . ( $key + 1 ) . '%5D=from%3A' . $report_wsal_start_date;
											$filters_string .= '&filters%5B' . ( $key + 3 ) . '%5D=to%3A' . $report_wsal_end_date;
										} else {
											$filters_string .= '&filters%5B' . ( $key + 1 ) . '%5D=on%3A' . $report_wsal_start_date;
										}
										$body .= '<a href="' . \WpSecurityAuditLog::get_plugin_admin_url_page() . '&wsal-cbid=-1' . $filters_string . '"  target="_blank" style="color: #009344;  display: inline-block;">' . __( 'Show me all these events', 'wp-security-audit-log' ) . '</a>';
									}

									$body .= '</div>';

									if ( ( isset( $current_settings['notification_summary_number_of_events_included'] ) && $current_settings['notification_summary_number_of_events_included'] ) ) {
										$body .= __( 'These users changed their password:', 'wp-security-audit-log' );
									}
									$body .= '</td></tr>';

									if ( ( isset( $current_settings['notification_summary_number_of_events_included'] ) && $current_settings['notification_summary_number_of_events_included'] ) ) {

										$body .= '<tr>
										<td style="padding-top: 20px; padding-bottom: 40px;">
											<table class="zebra-striped" width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid #D9E4FD; border-radius: 2px;">';

										$password_changes = array_slice( $password_changes, 0, Notification_Helper::NUMBER_OF_EVENTS_TO_INCLUDE );

										foreach ( $password_changes as $event ) {
											$user_data = ( ( isset( $event['meta_values']['TargetUserData'] ) ) ? $event['meta_values']['TargetUserData'] : false );
											if ( ! $user_data ) {
												continue;
											}

											$body .= '
											<tr>
											<td style="border: 1px solid #D9E4FD; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; padding-left: 24px; padding-right: 24px; padding-top: 10px; padding-bottom: 10px;">';
											$body .= '<span style="display: inline-block; color: #009344;">' . self::get_user_for_email( $user_data->Username ) . '</span> from <span style="display: inline-block; color: #009344;">' . $event['client_ip'] . '</span>'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
											$body .= '</td></tr>';
										}

										$body .= '</table></td></tr>';
									}
								}
							}

							if ( ( isset( $current_settings['notification_summary_password_user_change_other_password'] ) && $current_settings['notification_summary_password_user_change_other_password'] ) || ! isset( $current_settings['notification_summary_password_user_change_other_password'] ) ) {
								if ( ! empty( $forced_password_changes ) ) {
									$forced_password_changes_string = sprintf(
									// translators: singular or plural form of a login total count.
										_n( 'was %d password change on your site (user changed the password of another user)', 'were %d password changes on your site (user changed the password of another user)', count( $forced_password_changes ), 'wp-security-audit-log' ),
										count( $forced_password_changes )
									);

									$body .= '<tr>
									<td style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; text-align: left;">';
									$body .= sprintf(
									/* Translators: 1 - number of logins. 2 - total unique users */
										__( 'There %1$s on your site.', 'wp-security-audit-log' ),
										$forced_password_changes_string
									);

									$body .= '<div>';

									if ( 'free' === \WpSecurityAuditLog::get_plugin_version() ) {
										$body .= '<a href="https://melapress.com/wordpress-activity-log/pricing/#utm_source=wpal_email&utm_medium=email&utm_campaign=product_email&utm_content=cta_main" target="_blank" style="color: #009344;  display: inline-block;">' . __( 'Upgrade to Premium to see these events specifically', 'wp-security-audit-log' ) . '</a>';
									} else {
										$filters_string = '';
										foreach ( $forced_password_changes_events as $key => $event_id ) {
											$filters_string .= '&filters%5B' . $key . '%5D=event%3A' . $event_id;
										}

										if ( $report_wsal_end_date ) {
											$filters_string .= '&filters%5B' . ( $key + 1 ) . '%5D=from%3A' . $report_wsal_start_date;
											$filters_string .= '&filters%5B' . ( $key + 3 ) . '%5D=to%3A' . $report_wsal_end_date;
										} else {
											$filters_string .= '&filters%5B' . ( $key + 1 ) . '%5D=on%3A' . $report_wsal_start_date;
										}
										$body .= '<a href="' . \WpSecurityAuditLog::get_plugin_admin_url_page() . '&wsal-cbid=-1' . $filters_string . '" target="_blank" style="color: #009344;  font-weight: bold; display: inline-block;"  target="_blank" style="color: #009344;  display: inline-block;">' . __( 'Show me all these events', 'wp-security-audit-log' ) . '</a>';
									}

									$body .= '</div>';
									$body .= '</td></tr>';

									if ( ( isset( $current_settings['notification_summary_number_of_events_included'] ) && $current_settings['notification_summary_number_of_events_included'] ) ) {
										$body .= '
									<tr>
										<td style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; text-align: left;">';
										$body .= __( 'These users had their password changed:', 'wp-security-audit-log' );
										$body .= '</td></tr>';
									}

									if ( ( isset( $current_settings['notification_summary_number_of_events_included'] ) && $current_settings['notification_summary_number_of_events_included'] ) ) {

										$body .= '<tr>
										<td style="padding-top: 20px; padding-bottom: 40px;">
											<table class="zebra-striped" width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid #D9E4FD; border-radius: 2px;">';

										$forced_password_changes = array_slice( $forced_password_changes, 0, Notification_Helper::NUMBER_OF_EVENTS_TO_INCLUDE );
										foreach ( $forced_password_changes as $event ) {
											$user_data = ( ( isset( $event['meta_values']['TargetUserData'] ) ) ? $event['meta_values']['TargetUserData'] : false );
											if ( ! $user_data ) {
												continue;
											}

											$body .= '
											<tr>
											<td style="border: 1px solid #D9E4FD; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; padding-left: 24px; padding-right: 24px; padding-top: 10px; padding-bottom: 10px;">';
											$body .= '<span style="display: inline-block; color: #009344;">' . self::get_user_for_email( $user_data->Username ) . '</span> — password changed by <span style="display: inline-block; color: #009344;">' . ( ! is_null( $event['username'] ) ? self::get_user_for_email( $event['username'] ) : self::get_user_name( $event['user_id'] ) ) . '</span> from <span style="display: inline-block; color: #009344;">' . $event['client_ip'] . '</span>'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
											$body .= '</td></tr>';
										}

										$body .= '</table>
										</td>
									</tr>';
									}
								}
							}

							$body .= '
									</table>
								</td>
							</tr>
						</table>
						<!-- Password Changes End -->
						';
						}
					}

					// User profile changes.
					if ( ! empty( $user_profile_changes ) ) {
						$user_profile_changes_string = sprintf(
							// translators: singular or plural form of a login total count.
							_n( 'was %d profile change', 'were %d profile changes', count( $user_profile_changes ), 'wp-security-audit-log' ),
							count( $user_profile_changes )
						);
						$body .= '<!-- User Profile Changes Start -->
						<table role="presentation" width="640" border="0" cellpadding="0" cellspacing="0" role="presentation" class="responsive">
							<tr>
								<td align="center">
									<table width="100%" cellpadding="0" cellspacing="0" border="0">
										<tr>
											<td style="font-family: \'Quicksand\',\'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: bold; font-size: 20px; line-height: 22px; color: #1A3060; text-align: left; padding-top: 24px; padding-bottom: 8px;">User Profile Changes</td>
										</tr>
										<tr>
											<td style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; text-align: left;">';
						$body .= sprintf(
							/* Translators: 1 - number of logins. 2 - total unique users */
							__( 'There %1$s on your site.', 'wp-security-audit-log' ),
							$user_profile_changes_string
						);

						$body .= '<div>';

						if ( 'free' === \WpSecurityAuditLog::get_plugin_version() ) {
							$body .= '<a href="https://melapress.com/wordpress-activity-log/pricing/#utm_source=wpal_email&utm_medium=email&utm_campaign=product_email&utm_content=cta_main" target="_blank" style="color: #009344;  display: inline-block;">' . __( 'Upgrade to Premium to see these events specifically', 'wp-security-audit-log' ) . '</a>';
						} else {
							$filters_string = '';
							foreach ( $user_profile_events as $key => $event_id ) {
								$filters_string .= '&filters%5B' . $key . '%5D=event%3A' . $event_id;
							}

							if ( $report_wsal_end_date ) {
								$filters_string .= '&filters%5B' . ( $key + 1 ) . '%5D=from%3A' . $report_wsal_start_date;
								$filters_string .= '&filters%5B' . ( $key + 3 ) . '%5D=to%3A' . $report_wsal_end_date;
							} else {
								$filters_string .= '&filters%5B' . ( $key + 1 ) . '%5D=on%3A' . $report_wsal_start_date;
							}
							$body .= '<a href="' . \WpSecurityAuditLog::get_plugin_admin_url_page() . '&wsal-cbid=-1' . $filters_string . '" target="_blank" style="color: #009344;  display: inline-block;">' . __( 'Show me all these events', 'wp-security-audit-log' ) . '</a>';
						}

						$body .= '</div>';

						if ( ( isset( $current_settings['notification_summary_number_of_events_included'] ) && $current_settings['notification_summary_number_of_events_included'] ) ) {
							$body .= __( 'Below is a list of important user profile changes that happened on your website:', 'wp-security-audit-log' );
							$body .= '</td></tr>
							<tr>
								<td style="padding-top: 20px; padding-bottom: 40px;">
									<table class="zebra-striped" width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid #D9E4FD; border-radius: 2px;">';

							// Include the loop for events only if there are user profile changes.
							$user_profile_changes = array_slice( $user_profile_changes, 0, Notification_Helper::NUMBER_OF_EVENTS_TO_INCLUDE );

							foreach ( $user_profile_changes as $event ) {
								if ( 4000 === (int) $event['alert_id'] ) {
									$user_data = ( ( isset( $event['meta_values']['NewUserData'] ) ) ? $event['meta_values']['NewUserData'] : false );
									if ( $user_data ) {
										$body .= '<tr><td style="border: 1px solid #D9E4FD; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; padding-left: 24px; padding-right: 24px; padding-top: 10px; padding-bottom: 10px;">';
										$body .= 'User <span style="display: inline-block; color: #009344;">' . self::get_user_for_email( $user_data->Username ) . '</span> has registered on your website from <span style="display: inline-block; color: #009344;">' . $event['client_ip'] . '</span>'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
										$body .= '</td></tr>';
									}
								} elseif ( 4001 === (int) $event['alert_id'] ) {
									$user_data = ( ( isset( $event['meta_values']['NewUserData'] ) ) ? $event['meta_values']['NewUserData'] : false );
									if ( $user_data ) {
										$body .= '<tr><td style="border: 1px solid #D9E4FD; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; padding-left: 24px; padding-right: 24px; padding-top: 10px; padding-bottom: 10px;">';
										$body .= 'User <span style="display: inline-block; color: #009344;">' . ( ! is_null( $event['username'] ) ? self::get_user_for_email( $event['username'] ) : self::get_user_name( $event['user_id'] ) ) . '</span> has created the user <span style="display: inline-block; color: #009344;">' . self::get_user_for_email( $user_data->Username ) . '</span> with the role <span style="display: inline-block; color: #009344;">' . $user_data->Roles . '</span>'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
										$body .= '</td></tr>';
									}
								} elseif ( 4002 === (int) $event['alert_id'] ) {
									$username = ( ( isset( $event['meta_values']['TargetUsername'] ) ) ? $event['meta_values']['TargetUsername'] : false );
									$userrole = ( ( isset( $event['meta_values']['NewRole'] ) ) ? $event['meta_values']['NewRole'] : false );
									if ( $username ) {
										$body .= '<tr><td style="border: 1px solid #D9E4FD; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; padding-left: 24px; padding-right: 24px; padding-top: 10px; padding-bottom: 10px;">';
										$body .= 'User <span style="display: inline-block; color: #009344;">' . ( ! is_null( $event['username'] ) ? self::get_user_for_email( $event['username'] ) : self::get_user_name( $event['user_id'] ) ) . '</span> has changed the role of the user <span style="display: inline-block; color: #009344;">' . self::get_user_for_email( $username ) . '</span> to <span style="display: inline-block; color: #009344;">' . $userrole . '</span>';
										$body .= '</td></tr>';
									}
								} elseif ( 4007 === (int) $event['alert_id'] ) {
									$user_data = ( ( isset( $event['meta_values']['TargetUserData'] ) ) ? $event['meta_values']['TargetUserData'] : false );
									if ( $user_data ) {
										$body .= '<tr><td style="border: 1px solid #D9E4FD; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; padding-left: 24px; padding-right: 24px; padding-top: 10px; padding-bottom: 10px;">';
										$body .= 'User <span style="display: inline-block; color: #009344;">' . ( ! is_null( $event['username'] ) ? self::get_user_for_email( $event['username'] ) : self::get_user_name( $event['user_id'] ) ) . '</span> has deleted the user <span style="display: inline-block; color: #009344;">' . self::get_user_for_email( $user_data->Username ) . '</span> with the role <span style="display: inline-block; color: #009344;">' . $user_data->Roles . '</span>'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
										$body .= '</td></tr>';
									}
								}
							}

							$body .= '</table>';
						}

						$body .= '
									</td>
								</tr>
							</table>
						</td>
					</tr>
					</table>
					<!-- User Profile Changes End -->';
					}

					// Multisite activity.
					// Untested!
					if ( ! empty( $multisite_activity ) ) {
						$multisite_activity_string = sprintf(
							// translators: singular or plural form of a login total count.
							_n( 'was %d multisite activity', 'were %d multisite activities', count( $multisite_activity ), 'wp-security-audit-log' ),
							count( $multisite_activity )
						);
						$body                 .= '<!-- Multisite Activity Start -->
						<table role="presentation" width="640" border="0" cellpadding="0" cellspacing="0" role="presentation" class="responsive">
							<tr>
								<td align="center">
									<table width="100%" cellpadding="0" cellspacing="0" border="0">
										<tr>
											<td style="font-family: \'Quicksand\',\'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: bold; font-size: 20px; line-height: 22px; color: #1A3060; text-align: left; padding-top: 24px; padding-bottom: 8px;">Multisite Activity</td>
										</tr>';
										$body .= '<tr>
										<td style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; text-align: left;">';
										$body .= sprintf(
											/* Translators: 1 - number of logins. 2 - total unique users */
											__( 'There %1$s on your site.', 'wp-security-audit-log' ),
											$multisite_activity_string
										);
										$body .= '</td>
									</tr>';
										$body .= '<tr>
										<td style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; text-align: left;">';
						$body                 .= '<div>';

						if ( 'free' === \WpSecurityAuditLog::get_plugin_version() ) {
							$body .= '<a href="hhttps://melapress.com/wordpress-activity-log/pricing/#utm_source=wpal_email&utm_medium=email&utm_campaign=product_email&utm_content=cta_main" target="_blank" style="color: #009344;  display: inline-block;">' . __( 'Upgrade to Premium to see these events specifically', 'wp-security-audit-log' ) . '</a>';
						} else {
							$filters_string = '';
							foreach ( $multisite_events as $key => $event_id ) {
								$filters_string .= '&filters%5B' . $key . '%5D=event%3A' . $event_id;
							}

							if ( $report_wsal_end_date ) {
								$filters_string .= '&filters%5B' . ( $key + 1 ) . '%5D=from%3A' . $report_wsal_start_date;
								$filters_string .= '&filters%5B' . ( $key + 3 ) . '%5D=to%3A' . $report_wsal_end_date;
							} else {
								$filters_string .= '&filters%5B' . ( $key + 1 ) . '%5D=on%3A' . $report_wsal_start_date;
							}
							$body .= '<a href="' . \WpSecurityAuditLog::get_plugin_admin_url_page() . '&wsal-cbid=-1' . $filters_string . '" target="_blank" style="color: #009344;  display: inline-block;">' . __( 'Show me all these events', 'wp-security-audit-log' ) . '</a>';
						}

						$body .= '</div>';
						$body .= '</td>
									</tr>';

						if ( ( isset( $current_settings['notification_summary_number_of_events_included'] ) && $current_settings['notification_summary_number_of_events_included'] ) ) {
							$body .= '<tr>
							<td style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; text-align: left;">Below is a list of important events that occurred on your multisite network:</td>
						</tr>
						<tr>
							<td style="padding-top: 20px; padding-bottom: 40px;">
								<table class="zebra-striped" width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid #D9E4FD; border-radius: 2px;">';

							$multisite_activity = array_slice( $multisite_activity, 0, Notification_Helper::NUMBER_OF_EVENTS_TO_INCLUDE );

							foreach ( $multisite_activity as $event ) {
								$sitename = ( ( isset( $event['meta_values']['SiteName'] ) ) ? $event['meta_values']['SiteName'] : false );
								if ( 7000 === (int) $event['alert_id'] ) {
									$body .= '<tr>';
									$body .= '<td style="border: 1px solid #D9E4FD; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; padding-left: 24px; padding-right: 24px; padding-top: 10px; padding-bottom: 10px;">';
									$body .= 'User <span style="display: inline-block; color: #009344;">' . ( ! is_null( $event['username'] ) ? self::get_user_for_email( $event['username'] ) : self::get_user_name( $event['user_id'] ) ) . '</span> has added site <span style="display: inline-block; color: #009344;">' . $sitename . '</span>';
									$body .= '</td>';
									$body .= '</tr>';
								} elseif ( 7001 === (int) $event['alert_id'] ) {
									$body .= '<tr>';
									$body .= '<td style="border: 1px solid #D9E4FD; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; padding-left: 24px; padding-right: 24px; padding-top: 10px; padding-bottom: 10px;">';
									$body .= 'User <span style="display: inline-block; color: #009344;">' . ( ! is_null( $event['username'] ) ? self::get_user_for_email( $event['username'] ) : self::get_user_name( $event['user_id'] ) ) . '</span> has archived site <span style="display: inline-block; color: #009344;">' . $sitename . '</span>';
									$body .= '</td>';
									$body .= '</tr>';
								} elseif ( 7002 === (int) $event['alert_id'] ) {
									$body .= '<tr>';
									$body .= '<td style="border: 1px solid #D9E4FD; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; padding-left: 24px; padding-right: 24px; padding-top: 10px; padding-bottom: 10px;">';
									$body .= 'User <span style="display: inline-block; color: #009344;">' . ( ! is_null( $event['username'] ) ? self::get_user_for_email( $event['username'] ) : self::get_user_name( $event['user_id'] ) ) . '</span> has unarchived site <span style="display: inline-block; color: #009344;">' . $sitename . '</span>';
									$body .= '</td>';
									$body .= '</tr>';
								} elseif ( 7003 === (int) $event['alert_id'] ) {
									$body .= '<tr>';
									$body .= '<td style="border: 1px solid #D9E4FD; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; padding-left: 24px; padding-right: 24px; padding-top: 10px; padding-bottom: 10px;">';
									$body .= 'User <span style="display: inline-block; color: #009344;">' . ( ! is_null( $event['username'] ) ? self::get_user_for_email( $event['username'] ) : self::get_user_name( $event['user_id'] ) ) . '</span> has activated site <span style="display: inline-block; color: #009344;">' . $sitename . '</span>';
									$body .= '</td>';
									$body .= '</tr>';
								} elseif ( 7004 === (int) $event['alert_id'] ) {
									$body .= '<tr>';
									$body .= '<td style="border: 1px solid #D9E4FD; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; padding-left: 24px; padding-right: 24px; padding-top: 10px; padding-bottom: 10px;">';
									$body .= 'User <span style="display: inline-block; color: #009344;">' . ( ! is_null( $event['username'] ) ? self::get_user_for_email( $event['username'] ) : self::get_user_name( $event['user_id'] ) ) . '</span> has deactivated site <span style="display: inline-block; color: #009344;">' . $sitename . '</span>';
									$body .= '</td>';
									$body .= '</tr>';
								} elseif ( 7005 === (int) $event['alert_id'] ) {
									$body .= '<tr>';
									$body .= '<td style="border: 1px solid #D9E4FD; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; padding-left: 24px; padding-right: 24px; padding-top: 10px; padding-bottom: 10px;">';
									$body .= 'User <span style="display: inline-block; color: #009344;">' . ( ! is_null( $event['username'] ) ? self::get_user_for_email( $event['username'] ) : self::get_user_name( $event['user_id'] ) ) . '</span> has deleted site <span style="display: inline-block; color: #009344;">' . $sitename . '</span>';
									$body .= '</td>';
									$body .= '</tr>';
								} elseif ( 4010 === (int) $event['alert_id'] ) {
									$username = ( ( isset( $event['meta_values']['TargetUsername'] ) ) ? $event['meta_values']['TargetUsername'] : false );
									$userrole = ( ( isset( $event['meta_values']['TargetUserRole'] ) ) ? $event['meta_values']['TargetUserRole'] : false );

									$body .= '<tr>';
									$body .= '<td style="border: 1px solid #D9E4FD; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; padding-left: 24px; padding-right: 24px; padding-top: 10px; padding-bottom: 10px;">';
									$body .= 'User <span style="display: inline-block; color: #009344;">' . ( ! is_null( $event['username'] ) ? self::get_user_for_email( $event['username'] ) : self::get_user_name( $event['user_id'] ) ) . '</span> added the user <span style="display: inline-block; color: #009344;">' . self::get_user_for_email( $username ) . '</span> to the site <span style="display: inline-block; color: #009344;">' . $sitename . '</span> with the role of <span style="display: inline-block; color: #009344;">' . $userrole . '</span>';
									$body .= '</td>';
									$body .= '</tr>';
								} elseif ( 4011 === (int) $event['alert_id'] ) {
									$username = ( ( isset( $event['meta_values']['TargetUsername'] ) ) ? $event['meta_values']['TargetUsername'] : false );

									$body .= '<tr>';
									$body .= '<td style="border: 1px solid #D9E4FD; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; padding-left: 24px; padding-right: 24px; padding-top: 10px; padding-bottom: 10px;">';
									$body .= 'User <span style="display: inline-block; color: #009344;">' . ( ! is_null( $event['username'] ) ? self::get_user_for_email( $event['username'] ) : self::get_user_name( $event['user_id'] ) ) . '</span> removed the user <span style="display: inline-block; color: #009344;">' . self::get_user_for_email( $username ) . '</span> from the site <span style="display: inline-block; color: #009344;">' . $sitename . '</span>';
									$body .= '</td>';
									$body .= '</tr>';
								}
							}
						}

						$body .= '</table>
										</td>
									</tr>
								</table>
							</td>
						</tr>
					</table>
					<!-- Multisite Activity End -->';
					}

					// Plugin activity.
					if ( ( isset( $current_settings['notification_summary_plugins_activity'] ) && $current_settings['notification_summary_plugins_activity'] ) || ! isset( $current_settings['notification_summary_plugins_activity'] ) ) {
						if ( ! empty( $plugin_activity ) ) {
							$plugin_activity_string = sprintf(
							// translators: singular or plural form of a login total count.
								_n( 'was %d plugin change', 'were %d plugin changes', count( $plugin_activity ), 'wp-security-audit-log' ),
								count( $plugin_activity )
							);
							$body .= '<!-- Plugin Activity Start -->
							<table role="presentation" width="640" border="0" cellpadding="0" cellspacing="0" role="presentation" class="responsive">
								<tr>
									<td align="center">
										<table width="100%" cellpadding="0" cellspacing="0" border="0">
											<tr>
												<td style="font-family: \'Quicksand\',\'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: bold; font-size: 20px; line-height: 22px; color: #1A3060; text-align: left; padding-top: 24px; padding-bottom: 8px;">Plugin Activity</td>
											</tr>
											<tr>
												<td style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; text-align: left;">';
							$body .= sprintf(
							/* Translators: 1 - number of logins. 2 - total unique users */
								__( 'There %1$s on your site.', 'wp-security-audit-log' ),
								$plugin_activity_string
							);

							$body .= '<div>';

							if ( 'free' === \WpSecurityAuditLog::get_plugin_version() ) {
								$body .= '<a href="https://melapress.com/wordpress-activity-log/pricing/#utm_source=wpal_email&utm_medium=email&utm_campaign=product_email&utm_content=cta_main" target="_blank" style="color: #009344;  display: inline-block;">' . __( 'Upgrade to Premium to see these events specifically', 'wp-security-audit-log' ) . '</a>';
							} else {
								$filters_string = '';
								foreach ( $plugin_events as $key => $event_id ) {
									$filters_string .= '&filters%5B' . $key . '%5D=event%3A' . $event_id;
								}

								if ( $report_wsal_end_date ) {
									$filters_string .= '&filters%5B' . ( $key + 1 ) . '%5D=from%3A' . $report_wsal_start_date;
									$filters_string .= '&filters%5B' . ( $key + 3 ) . '%5D=to%3A' . $report_wsal_end_date;
								} else {
									$filters_string .= '&filters%5B' . ( $key + 1 ) . '%5D=on%3A' . $report_wsal_start_date;
								}
								$body .= '<a href="' . \WpSecurityAuditLog::get_plugin_admin_url_page() . '&wsal-cbid=-1' . $filters_string . '" target="_blank" style="color: #009344;  display: inline-block;">' . __( 'Show me all these events', 'wp-security-audit-log' ) . '</a>';
							}

							$body .= '</div>';

							if ( ( isset( $current_settings['notification_summary_number_of_events_included'] ) && $current_settings['notification_summary_number_of_events_included'] ) ) {
								$body .= __( 'Below is a list of plugin changes that happened on your website:', 'wp-security-audit-log' );
							}
							$body .= '</td></tr>';

							if ( ( isset( $current_settings['notification_summary_number_of_events_included'] ) && $current_settings['notification_summary_number_of_events_included'] ) ) {

								$body .= '<tr>
								<td style="padding-top: 20px; padding-bottom: 40px;">
									<table class="zebra-striped" width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid #D9E4FD; border-radius: 2px;">';

								$plugin_activity = array_slice( $plugin_activity, 0, Notification_Helper::NUMBER_OF_EVENTS_TO_INCLUDE );

								foreach ( $plugin_activity as $event ) {
									$plugin_data = false;
									if ( 5000 === (int) $event['alert_id'] ) {
										$plugin_data = ( ( isset( $event['meta_values']['Plugin'] ) ) ? $event['meta_values']['Plugin'] : false );
									} else {
										$plugin_data = ( ( isset( $event['meta_values']['PluginData'] ) ) ? $event['meta_values']['PluginData'] : false );
									}

									if ( ! $plugin_data ) {
										continue;
									}

									if ( ! ( $plugin_data instanceof \stdClass ) || ! property_exists( $plugin_data, 'Name' ) ) {
										continue;
									}

									$body .= '<tr>';
									$body .= '<td style="border: 1px solid #D9E4FD; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; padding-left: 24px; padding-right: 24px; padding-top: 10px; padding-bottom: 10px;">';
									if ( 5000 === (int) $event['alert_id'] ) {
										$body .= 'User <span style="display: inline-block; color: #009344;">' . ( ! is_null( $event['username'] ) ? self::get_user_for_email( $event['username'] ) : self::get_user_name( (int) $event['user_id'] ) ) . '</span> installed the plugin <span style="display: inline-block; color: #009344;">' . $plugin_data->Name . '</span>'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
									} elseif ( 5001 === (int) $event['alert_id'] ) {
										$body .= 'User <span style="display: inline-block; color: #009344;">' . ( ! is_null( $event['username'] ) ? self::get_user_for_email( $event['username'] ) : self::get_user_name( (int) $event['user_id'] ) ) . '</span> activated the plugin <span style="display: inline-block; color: #009344;">' . $plugin_data->Name . '</span>'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
									} elseif ( 5002 === (int) $event['alert_id'] ) {
										$body .= 'User <span style="display: inline-block; color: #009344;">' . ( ! is_null( $event['username'] ) ? self::get_user_for_email( $event['username'] ) : self::get_user_name( (int) $event['user_id'] ) ) . '</span> deactivated the plugin <span style="display: inline-block; color: #009344;">' . $plugin_data->Name . '</span>'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
									} elseif ( 5003 === (int) $event['alert_id'] ) {
										$body .= 'User <span style="display: inline-block; color: #009344;">' . ( ! is_null( $event['username'] ) ? self::get_user_for_email( $event['username'] ) : self::get_user_name( (int) $event['user_id'] ) ) . '</span> uninstalled the plugin <span style="display: inline-block; color: #009344;">' . $plugin_data->Name . '</span>'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
									} elseif ( 5004 === (int) $event['alert_id'] ) {
										$body .= 'User <span style="display: inline-block; color: #009344;">' . ( ! is_null( $event['username'] ) ? self::get_user_for_email( $event['username'] ) : self::get_user_name( (int) $event['user_id'] ) ) . '</span> upgraded the plugin <span style="display: inline-block; color: #009344;">' . $plugin_data->Name . '</span>'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
									}
									$body .= '</td></tr>';
								}

								$body .= '</table></td></tr>';

							}

							$body .= '
									</table>
								</td>
							</tr>
						</table>
						<!-- Plugin Activity End -->';
						}
					}

					// System activity.
					if ( ( isset( $current_settings['notification_summary_system_activity'] ) && $current_settings['notification_summary_system_activity'] ) || ! isset( $current_settings['notification_summary_system_activity'] ) ) {
						if ( ! empty( $system_activity ) ) {
							$system_activity_count_string = sprintf(
							// translators: singular or plural form of a login total count.
								_n( 'was %d system event', 'were %d system events', count( $system_activity ), 'wp-security-audit-log' ),
								count( $system_activity )
							);

							$body .= '<!-- System Activity Start -->
							<table role="presentation" width="640" border="0" cellpadding="0" cellspacing="0" role="presentation" class="responsive">
								<tr>
									<td align="center">
										<table width="100%" cellpadding="0" cellspacing="0" border="0">
											<tr>
												<td style="font-family: \'Quicksand\',\'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: bold; font-size: 20px; line-height: 22px; color: #1A3060; text-align: left; padding-top: 24px; padding-bottom: 8px;">System Activity</td>
											</tr>
											<tr>
												<td style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; text-align: left;">';

							$body .= sprintf(
								/* Translators: 1 - number of logins. 2 - total unique users */
								__( 'There %1$s on your site.', 'wp-security-audit-log' ),
								$system_activity_count_string
							);

							$body .= '<div>';

							if ( 'free' === \WpSecurityAuditLog::get_plugin_version() ) {
								$body .= '<a href="https://melapress.com/wordpress-activity-log/pricing/#utm_source=wpal_email&utm_medium=email&utm_campaign=product_email&utm_content=cta_main" target="_blank" style="color: #009344;  display: inline-block;">' . __( 'Upgrade to Premium to see these events specifically', 'wp-security-audit-log' ) . '</a>';
							} else {
								$filters_string = '';
								foreach ( $system_activity_events as $key => $event_id ) {
									$filters_string .= '&filters%5B' . $key . '%5D=event%3A' . $event_id;
								}

								if ( $report_wsal_end_date ) {
									$filters_string .= '&filters%5B' . ( $key + 1 ) . '%5D=from%3A' . $report_wsal_start_date;
									$filters_string .= '&filters%5B' . ( $key + 3 ) . '%5D=to%3A' . $report_wsal_end_date;
								} else {
									$filters_string .= '&filters%5B' . ( $key + 1 ) . '%5D=on%3A' . $report_wsal_start_date;
								}
								$body .= '<a href="' . \WpSecurityAuditLog::get_plugin_admin_url_page() . '&wsal-cbid=-1' . $filters_string . '" target="_blank" style="color: #009344;  display: inline-block;">' . __( 'Show me all these events', 'wp-security-audit-log' ) . '</a>';
							}

							$body .= '</div>';

							if ( ( isset( $current_settings['notification_summary_number_of_events_included'] ) && $current_settings['notification_summary_number_of_events_included'] ) ) {
								$body .= __( 'Below is a list of system changes that happened:', 'wp-security-audit-log' );
							}

							$body .= '</td></tr>';

							if ( ( isset( $current_settings['notification_summary_number_of_events_included'] ) && $current_settings['notification_summary_number_of_events_included'] ) ) {

								$body .= '<tr>
								<td style="padding-top: 20px; padding-bottom: 40px;">
									<table class="zebra-striped" width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid #D9E4FD; border-radius: 2px;">';

								$system_activity = array_slice( $system_activity, 0, Notification_Helper::NUMBER_OF_EVENTS_TO_INCLUDE );

								foreach ( $system_activity as $event ) {
									$plugin_data = false;
									if ( ( isset( $event['meta_values']['Plugin'] ) ) ) {
										$plugin_data = $event['meta_values']['Plugin'];
									} elseif ( ( isset( $event['meta_values']['PluginData'] ) ) ) {
										$plugin_data = $event['meta_values']['PluginData'];
									}

									$body .= '<tr><td style="border: 1px solid #D9E4FD; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; padding-left: 24px; padding-right: 24px; padding-top: 10px; padding-bottom: 10px;">';

									$body .= Alert_Manager::get_alerts()[ $event['alert_id'] ]['desc'];

									$user = ( ! is_null( $event['username'] ) ? self::get_user_for_email( $event['username'] ) : ( ( ! is_null( $event['username'] ) ) ? self::get_user_name( (int) $event['user_id'] ) : null ) );

									if ( $user ) {
										$body .= ' by <span style="display: inline-block; color: #009344;">' . ( ! is_null( $event['username'] ) ? self::get_user_for_email( $event['username'] ) : self::get_user_name( (int) $event['user_id'] ) ) . '</span>';
									}

									if ( $plugin_data ) {
										$body .= ' plugin <span style="display: inline-block; color: #009344;">' . $plugin_data->Name . '</span>'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
									}

									$body .= '</td></tr>';
								}

								$body .= '</table></td></tr>';
							}

							$body .= '	
										</table>
									</td>
								</tr>
							</table>
							<!-- System Activity End -->';
						}
					}
				}

				// Content changes.
				if ( ( isset( $current_settings['notification_summary_content_changes'] ) && $current_settings['notification_summary_content_changes'] ) || ! isset( $current_settings['notification_summary_content_changes'] ) ) {
					if (
						! empty( $posts_published )
						|| ! empty( $posts_trashed )
						|| ! empty( $posts_deleted )
						|| ! empty( $posts_modified )
						|| ! empty( $posts_status_changed )
					) {
						$body .= '<!-- Content Changes Start -->
						<table role="presentation" width="640" border="0" cellpadding="0" cellspacing="0" role="presentation" class="responsive">
							<tr>
								<td align="center">
									<table width="100%" cellpadding="0" cellspacing="0" border="0">
										<tr>
											<td style="font-family: \'Quicksand\',\'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: bold; font-size: 20px; line-height: 22px; color: #1A3060; text-align: left; padding-top: 24px; padding-bottom: 8px;">Content Changes</td>
										</tr>
										<tr>
											<td style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; text-align: left;">';

						// Posts Published.
						if ( ( isset( $current_settings['notification_summary_published_posts'] ) && $current_settings['notification_summary_published_posts'] ) || ! isset( $current_settings['notification_summary_published_posts'] ) ) {
							if ( ! empty( $posts_published ) ) {
								$posts_published_string = sprintf(
									// translators: singular or plural form of a login total count.
									_n( 'was %d post published', 'were %d posts published', count( $posts_published ), 'wp-security-audit-log' ),
									count( $posts_published )
								);
								$body .= '<tr><td style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; text-align: left;">';
								$body .= sprintf(
								/* Translators: 1 - number of logins. 2 - total unique users */
									__( 'There %1$s on your site.', 'wp-security-audit-log' ),
									$posts_published_string
								);

								$body .= '<div>';

								if ( 'free' === \WpSecurityAuditLog::get_plugin_version() ) {
									$body .= '<a href="https://melapress.com/wordpress-activity-log/pricing/#utm_source=wpal_email&utm_medium=email&utm_campaign=product_email&utm_content=cta_main" target="_blank" style="color: #009344;  display: inline-block;">' . __( 'Upgrade to Premium to see these events specifically', 'wp-security-audit-log' ) . '</a>';
								} else {
									$filters_string = '';
									foreach ( $posts_published_events as $key => $event_id ) {
										$filters_string .= '&filters%5B' . $key . '%5D=event%3A' . $event_id;
									}

									if ( $report_wsal_end_date ) {
										$filters_string .= '&filters%5B' . ( $key + 1 ) . '%5D=from%3A' . $report_wsal_start_date;
										$filters_string .= '&filters%5B' . ( $key + 3 ) . '%5D=to%3A' . $report_wsal_end_date;
									} else {
										$filters_string .= '&filters%5B' . ( $key + 1 ) . '%5D=on%3A' . $report_wsal_start_date;
									}
									$body .= '<a href="' . \WpSecurityAuditLog::get_plugin_admin_url_page() . '&wsal-cbid=-1' . $filters_string . '" target="_blank" style="color: #009344;  display: inline-block;">' . __( 'Show me all these events', 'wp-security-audit-log' ) . '</a>';
								}

								$body .= '</div>';

								if ( ( isset( $current_settings['notification_summary_number_of_events_included'] ) && $current_settings['notification_summary_number_of_events_included'] ) ) {
									$body .= __( 'These posts were published:', 'wp-security-audit-log' );
								}
								$body .= '</td></tr>';

								if ( ( isset( $current_settings['notification_summary_number_of_events_included'] ) && $current_settings['notification_summary_number_of_events_included'] ) ) {
									$body .= '<tr>
										<td style="padding-top: 20px; padding-bottom: 40px;">
											<table class="zebra-striped" width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid #D9E4FD; border-radius: 2px;">';

									$posts_published = array_slice( $posts_published, 0, Notification_Helper::NUMBER_OF_EVENTS_TO_INCLUDE );

									foreach ( $posts_published as $post_event ) {
										$post_title = ( ( isset( $post_event['meta_values']['PostTitle'] ) ) ? $post_event['meta_values']['PostTitle'] : false );
										$post_id    = ( ( isset( $post_event['meta_values']['PostID'] ) ) ? $post_event['meta_values']['PostID'] : false );
										if ( ! $post_title || ! $post_id ) {
											continue;
										}

										$body .= '<tr>';
										$body .= '<td style="border: 1px solid #D9E4FD; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; padding-left: 24px; padding-right: 24px; padding-top: 10px; padding-bottom: 10px;">';
										if ( WP_Helper::is_multisite() ) {
											$site_url = ( ( isset( $post_event['meta_values']['SiteURL'] ) ) ? $post_event['meta_values']['SiteURL'] : $safe_url );
											$body    .= '<a style="display: inline-block; color: #009344;" href="' . get_permalink( $post_id ) . '" target="_blank">' . $post_title . '</a> by <span style="display: inline-block; color: #009344;">' . ( ! is_null( $post_event['username'] ) ? self::get_user_for_email( $post_event['username'] ) : self::get_user_name( $post_event['user_id'] ) ) . '</span> on site <span style="display: inline-block; color: #009344;">' . $site_url . '</span>';
										} else {
											$body .= '<a style="display: inline-block; color: #009344;" href="' . get_permalink( $post_id ) . '" target="_blank">' . $post_title . '</a> by <span style="display: inline-block; color: #009344;">' . ( ! is_null( $post_event['username'] ) ? self::get_user_for_email( $post_event['username'] ) : self::get_user_name( $post_event['user_id'] ) ) . '</span>';
										}
										$body .= '</td>';
										$body .= '</tr>';
									}

									$body .= '</table>
										</td>
									</tr>';
								}
							}
						}

						// Posts Trashed.
						if ( ( isset( $current_settings['notification_summary_deleted_posts'] ) && $current_settings['notification_summary_deleted_posts'] ) || ! isset( $current_settings['notification_summary_deleted_posts'] ) ) {
							if ( ! empty( $posts_trashed ) ) {
								$posts_trashed_string = sprintf(
									// translators: singular or plural form of a login total count.
									_n( 'was %d post trashed', 'were %d posts trashed', count( $posts_trashed ), 'wp-security-audit-log' ),
									count( $posts_trashed )
								);
								$body .= '<tr><td style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; text-align: left;">';
								$body .= sprintf(
								/* Translators: 1 - number of logins. 2 - total unique users */
									__( 'There %1$s on your site.', 'wp-security-audit-log' ),
									$posts_trashed_string
								);

								$body .= '<div>';

								if ( 'free' === \WpSecurityAuditLog::get_plugin_version() ) {
									$body .= '<a href="https://melapress.com/wordpress-activity-log/pricing/#utm_source=wpal_email&utm_medium=email&utm_campaign=product_email&utm_content=cta_main" target="_blank" style="color: #009344;  display: inline-block;">' . __( 'Upgrade to Premium to see these events specifically', 'wp-security-audit-log' ) . '</a>';
								} else {
									$filters_string = '';
									foreach ( $posts_trashed_events as $key => $event_id ) {
										$filters_string .= '&filters%5B' . $key . '%5D=event%3A' . $event_id;
									}

									if ( $report_wsal_end_date ) {
										$filters_string .= '&filters%5B' . ( $key + 1 ) . '%5D=from%3A' . $report_wsal_start_date;
										$filters_string .= '&filters%5B' . ( $key + 3 ) . '%5D=to%3A' . $report_wsal_end_date;
									} else {
										$filters_string .= '&filters%5B' . ( $key + 1 ) . '%5D=on%3A' . $report_wsal_start_date;
									}
									$body .= '<a href="' . \WpSecurityAuditLog::get_plugin_admin_url_page() . '&wsal-cbid=-1' . $filters_string . '" target="_blank" style="color: #009344;  display: inline-block;">' . __( 'Show me all these events', 'wp-security-audit-log' ) . '</a>';
								}

								$body .= '</div>';

								if ( ( isset( $current_settings['notification_summary_number_of_events_included'] ) && $current_settings['notification_summary_number_of_events_included'] ) ) {
									$body .= __( 'These posts were moved to trash:', 'wp-security-audit-log' );
								}
								$body .= '</td></tr>';

								if ( ( isset( $current_settings['notification_summary_number_of_events_included'] ) && $current_settings['notification_summary_number_of_events_included'] ) ) {
									$body .= '<tr>
										<td style="padding-top: 20px; padding-bottom: 40px;">
											<table class="zebra-striped" width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid #D9E4FD; border-radius: 2px;">';

									$posts_trashed = array_slice( $posts_trashed, 0, Notification_Helper::NUMBER_OF_EVENTS_TO_INCLUDE );

									foreach ( $posts_trashed as $post_event ) {
										$post_title = ( ( isset( $post_event['meta_values']['PostTitle'] ) ) ? $post_event['meta_values']['PostTitle'] : false );
										$post_id    = ( ( isset( $post_event['meta_values']['PostID'] ) ) ? $post_event['meta_values']['PostID'] : false );
										if ( ! $post_title || ! $post_id ) {
											continue;
										}

										$body .= '<tr>';
										$body .= '<td style="border: 1px solid #D9E4FD; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; padding-left: 24px; padding-right: 24px; padding-top: 10px; padding-bottom: 10px;">';
										if ( WP_Helper::is_multisite() ) {
											$site_url = ( ( isset( $post_event['meta_values']['SiteURL'] ) ) ? $post_event['meta_values']['SiteURL'] : $safe_url );
											$body    .= '<a style="display: inline-block; color: #009344;" href="' . get_permalink( $post_id ) . '" target="_blank">' . $post_title . '</a> sent to trash by <span style="display: inline-block; color: #009344;">' . ( ! is_null( $post_event['username'] ) ? self::get_user_for_email( $post_event['username'] ) : self::get_user_name( $post_event['user_id'] ) ) . '</span> on site <span style="display: inline-block; color: #009344;">' . $site_url . '</span>';
										} else {
											$body .= '<a style="display: inline-block; color: #009344;" href="' . get_permalink( $post_id ) . '" target="_blank">' . $post_title . '</a> sent to trash by <span style="display: inline-block; color: #009344;">' . ( ! is_null( $post_event['username'] ) ? self::get_user_for_email( $post_event['username'] ) : self::get_user_name( $post_event['user_id'] ) ) . '</span>';
										}
										$body .= '</td>';
										$body .= '</tr>';
									}

									$body .= '</table>
										</td>
									</tr>';
								}
							}
						}

						// Posts Deleted.
						if ( ( isset( $current_settings['notification_summary_deleted_posts'] ) && $current_settings['notification_summary_deleted_posts'] ) || ! isset( $current_settings['notification_summary_deleted_posts'] ) ) {
							if ( ! empty( $posts_deleted ) ) {
								$posts_deleted_string = sprintf(
									// translators: singular or plural form of a login total count.
									_n( 'was %d post deleted', 'were %d posts deleted', count( $posts_deleted ), 'wp-security-audit-log' ),
									count( $posts_deleted )
								);
								$body .= '<tr><td style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; text-align: left;">';
								$body .= sprintf(
								/* Translators: 1 - number of logins. 2 - total unique users */
									__( 'There %1$s on your site.', 'wp-security-audit-log' ),
									$posts_deleted_string
								);

								$body .= '<div>';

								if ( 'free' === \WpSecurityAuditLog::get_plugin_version() ) {
									$body .= '<a href="https://melapress.com/wordpress-activity-log/pricing/#utm_source=wpal_email&utm_medium=email&utm_campaign=product_email&utm_content=cta_main" target="_blank" style="color: #009344;  display: inline-block;">' . __( 'Upgrade to Premium to see these events specifically', 'wp-security-audit-log' ) . '</a>';
								} else {
									$filters_string = '';
									foreach ( $posts_deleted_events as $key => $event_id ) {
										$filters_string .= '&filters%5B' . $key . '%5D=event%3A' . $event_id;
									}

									if ( $report_wsal_end_date ) {
										$filters_string .= '&filters%5B' . ( $key + 1 ) . '%5D=from%3A' . $report_wsal_start_date;
										$filters_string .= '&filters%5B' . ( $key + 3 ) . '%5D=to%3A' . $report_wsal_end_date;
									} else {
										$filters_string .= '&filters%5B' . ( $key + 1 ) . '%5D=on%3A' . $report_wsal_start_date;
									}
									$body .= '<a href="' . \WpSecurityAuditLog::get_plugin_admin_url_page() . '&wsal-cbid=-1' . $filters_string . '" target="_blank" style="color: #009344;  display: inline-block;">' . __( 'Show me all these events', 'wp-security-audit-log' ) . '</a>';
								}

								$body .= '</div>';

								if ( ( isset( $current_settings['notification_summary_number_of_events_included'] ) && $current_settings['notification_summary_number_of_events_included'] ) ) {
									$body .= __( 'These posts were deleted:', 'wp-security-audit-log' );
								}
								$body .= '</td></tr>';

								if ( ( isset( $current_settings['notification_summary_number_of_events_included'] ) && $current_settings['notification_summary_number_of_events_included'] ) ) {
									$body .= '<tr>
										<td style="padding-top: 20px; padding-bottom: 40px;">
											<table class="zebra-striped" width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid #D9E4FD; border-radius: 2px;">';

									$posts_deleted = array_slice( $posts_deleted, 0, Notification_Helper::NUMBER_OF_EVENTS_TO_INCLUDE );

									foreach ( $posts_deleted as $post_event ) {
										$post_title = ( ( isset( $post_event['meta_values']['PostTitle'] ) ) ? $post_event['meta_values']['PostTitle'] : false );
										$post_id    = ( ( isset( $post_event['meta_values']['PostID'] ) ) ? $post_event['meta_values']['PostID'] : false );
										if ( ! $post_title || ! $post_id ) {
											continue;
										}

										$body .= '<tr>';
										$body .= '<td style="border: 1px solid #D9E4FD; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; padding-left: 24px; padding-right: 24px; padding-top: 10px; padding-bottom: 10px;">';
										if ( WP_Helper::is_multisite() ) {
											$site_url = ( ( isset( $post_event['meta_values']['SiteURL'] ) ) ? $post_event['meta_values']['SiteURL'] : $safe_url );
											$body    .= '<a style="display: inline-block; color: #009344;" href="' . get_permalink( $post_id ) . '" target="_blank">' . $post_title . '</a> deleted permanently by <span style="display: inline-block; color: #009344;">' . ( ! is_null( $post_event['username'] ) ? self::get_user_for_email( $post_event['username'] ) : self::get_user_name( $post_event['user_id'] ) ) . '</span> on site <span style="display: inline-block; color: #009344;">' . $site_url . '</span>';
										} else {
											$body .= '<a style="display: inline-block; color: #009344;" href="' . get_permalink( $post_id ) . '" target="_blank">' . $post_title . '</a> deleted permanently by <span style="display: inline-block; color: #009344;">' . ( ! is_null( $post_event['username'] ) ? self::get_user_for_email( $post_event['username'] ) : self::get_user_name( $post_event['user_id'] ) ) . '</span>';
										}
										$body .= '</td>';
										$body .= '</tr>';
									}

									$body .= '</table>
										</td>
									</tr>';
								}
							}
						}

						// Posts Modified.
						if ( ( isset( $current_settings['notification_summary_changed_posts'] ) && $current_settings['notification_summary_changed_posts'] ) || ! isset( $current_settings['notification_summary_changed_posts'] ) ) {
							if ( ! empty( $posts_modified ) ) {
								$posts_modified_string = sprintf(
									// translators: singular or plural form of a login total count.
									_n( 'was %d post modified', 'were %d posts modified', count( $posts_modified ), 'wp-security-audit-log' ),
									count( $posts_modified )
								);

								$body .= '<tr><td style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; text-align: left;">';
								$body .= sprintf(
								/* Translators: 1 - number of logins. 2 - total unique users */
									__( 'There %1$s on your site.', 'wp-security-audit-log' ),
									$posts_modified_string
								);

								$body .= '<div>';

								if ( 'free' === \WpSecurityAuditLog::get_plugin_version() ) {
									$body .= '<a href="https://melapress.com/wordpress-activity-log/pricing/#utm_source=wpal_email&utm_medium=email&utm_campaign=product_email&utm_content=cta_main" target="_blank" style="color: #009344;  display: inline-block;">' . __( 'Upgrade to Premium to see these events specifically', 'wp-security-audit-log' ) . '</a>';
								} else {
									$filters_string = '';
									foreach ( $posts_modified_events as $key => $event_id ) {
										$filters_string .= '&filters%5B' . $key . '%5D=event%3A' . $event_id;
									}

									if ( $report_wsal_end_date ) {
										$filters_string .= '&filters%5B' . ( $key + 1 ) . '%5D=from%3A' . $report_wsal_start_date;
										$filters_string .= '&filters%5B' . ( $key + 3 ) . '%5D=to%3A' . $report_wsal_end_date;
									} else {
										$filters_string .= '&filters%5B' . ( $key + 1 ) . '%5D=on%3A' . $report_wsal_start_date;
									}
									$body .= '<a href="' . \WpSecurityAuditLog::get_plugin_admin_url_page() . '&wsal-cbid=-1' . $filters_string . '" target="_blank" style="color: #009344;  display: inline-block;">' . __( 'Show me all these events', 'wp-security-audit-log' ) . '</a>';
								}

								$body .= '</div>';

								if ( ( isset( $current_settings['notification_summary_number_of_events_included'] ) && $current_settings['notification_summary_number_of_events_included'] ) ) {
									$body .= __( 'The content of these posts was changed:', 'wp-security-audit-log' );
								}
								$body .= '</td></tr>';

								if ( ( isset( $current_settings['notification_summary_number_of_events_included'] ) && $current_settings['notification_summary_number_of_events_included'] ) ) {

									$body .= '<tr>
										<td style="padding-top: 20px; padding-bottom: 40px;">
											<table class="zebra-striped" width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid #D9E4FD; border-radius: 2px;">';

									$posts_modified = array_slice( $posts_modified, 0, Notification_Helper::NUMBER_OF_EVENTS_TO_INCLUDE );

									foreach ( $posts_modified as $post_event ) {
										$post_title = ( ( isset( $post_event['meta_values']['PostTitle'] ) ) ? $post_event['meta_values']['PostTitle'] : false );
										if ( ! $post_title ) {
											continue;
										}

										$body .= '<tr>';
										$body .= '<td style="border: 1px solid #D9E4FD; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; padding-left: 24px; padding-right: 24px; padding-top: 10px; padding-bottom: 10px;">';
										if ( WP_Helper::is_multisite() ) {
											$site_url = ( ( isset( $post_event['meta_values']['SiteURL'] ) ) ? $post_event['meta_values']['SiteURL'] : $safe_url );
											$body    .= '<span style="display: inline-block; color: #009344;">' . $post_title . '</span> by <span style="display: inline-block; color: #009344;">' . ( ! is_null( $post_event['username'] ) ? self::get_user_for_email( $post_event['username'] ) : self::get_user_name( $post_event['user_id'] ) ) . '</span> on site <span style="color: #1A3060;">' . $site_url . '</span>'; // Changed color for 'on site'.
										} else {
											$body .= '<span style="display: inline-block; color: #009344;">' . $post_title . '</span> by <span style="display: inline-block; color: #009344;">' . ( ! is_null( $post_event['username'] ) ? self::get_user_for_email( $post_event['username'] ) : self::get_user_name( $post_event['user_id'] ) ) . '</span>';
										}
										$body .= '</td>';
										$body .= '</tr>';
									}

									$body .= '</table>
										</td>
									</tr>';
								}
							}
						}

						// Posts Status Changed.
						if ( ( isset( $current_settings['notification_summary_status_changed_posts'] ) && $current_settings['notification_summary_status_changed_posts'] ) || ! isset( $current_settings['notification_summary_status_changed_posts'] ) ) {
							if ( ! empty( $posts_status_changed ) ) {
								$posts_status_changed_string = sprintf(
									// translators: singular or plural form of a login total count.
									_n( 'was %d post status changed', 'were %d posts status changes', count( $posts_status_changed ), 'wp-security-audit-log' ),
									count( $posts_status_changed )
								);
								$body .= '<tr><td style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; text-align: left;">';
								$body .= sprintf(
								/* Translators: 1 - number of logins. 2 - total unique users */
									__( 'There %1$s on your site.', 'wp-security-audit-log' ),
									$posts_status_changed_string
								);

								$body .= '<div>';

								if ( 'free' === \WpSecurityAuditLog::get_plugin_version() ) {
									$body .= '<a href="https://melapress.com/wordpress-activity-log/pricing/#utm_source=wpal_email&utm_medium=email&utm_campaign=product_email&utm_content=cta_main" target="_blank" style="color: #009344;  display: inline-block;">' . __( 'Upgrade to Premium to see these events specifically', 'wp-security-audit-log' ) . '</a>';
								} else {
									$filters_string = '';
									foreach ( $posts_status_changed_events as $key => $event_id ) {
										$filters_string .= '&filters%5B' . $key . '%5D=event%3A' . $event_id;
									}

									if ( $report_wsal_end_date ) {
										$filters_string .= '&filters%5B' . ( $key + 1 ) . '%5D=from%3A' . $report_wsal_start_date;
										$filters_string .= '&filters%5B' . ( $key + 3 ) . '%5D=to%3A' . $report_wsal_end_date;
									} else {
										$filters_string .= '&filters%5B' . ( $key + 1 ) . '%5D=on%3A' . $report_wsal_start_date;
									}
									$body .= '<a href="' . \WpSecurityAuditLog::get_plugin_admin_url_page() . '&wsal-cbid=-1' . $filters_string . '" target="_blank" style="color: #009344;  display: inline-block;">' . __( 'Show me all these events', 'wp-security-audit-log' ) . '</a>';
								}

								$body .= '</div>';

								if ( ( isset( $current_settings['notification_summary_number_of_events_included'] ) && $current_settings['notification_summary_number_of_events_included'] ) ) {
									$body .= __( 'These posts were with status changes:', 'wp-security-audit-log' );
								}
								$body .= '</td></tr>';

								if ( ( isset( $current_settings['notification_summary_number_of_events_included'] ) && $current_settings['notification_summary_number_of_events_included'] ) ) {
									$body .= '<tr>
										<td style="padding-top: 20px; padding-bottom: 40px;">
											<table class="zebra-striped" width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid #D9E4FD; border-radius: 2px;">';

									$posts_status_changed = array_slice( $posts_status_changed, 0, Notification_Helper::NUMBER_OF_EVENTS_TO_INCLUDE );

									foreach ( $posts_status_changed as $post_event ) {
										$post_title = ( ( isset( $post_event['meta_values']['PostTitle'] ) ) ? $post_event['meta_values']['PostTitle'] : false );
										$post_id    = ( ( isset( $post_event['meta_values']['PostID'] ) ) ? $post_event['meta_values']['PostID'] : false );
										if ( ! $post_title || ! $post_id ) {
											continue;
										}

										$body .= '<tr>';
										$body .= '<td style="border: 1px solid #D9E4FD; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; padding-left: 24px; padding-right: 24px; padding-top: 10px; padding-bottom: 10px;">';
										if ( WP_Helper::is_multisite() ) {
											$site_url = ( ( isset( $post_event['meta_values']['SiteURL'] ) ) ? $post_event['meta_values']['SiteURL'] : $safe_url );
											$body    .= '<a style="display: inline-block; color: #009344;" href="' . get_permalink( $post_id ) . '" target="_blank">' . $post_title . '</a> by <span style="display: inline-block; color: #009344;">' . ( ! is_null( $post_event['username'] ) ? self::get_user_for_email( $post_event['username'] ) : self::get_user_name( $post_event['user_id'] ) ) . '</span> on site <span style="display: inline-block; color: #009344;">' . $site_url . '</span>';
										} else {
											$body .= '<a style="display: inline-block; color: #009344;" href="' . get_permalink( $post_id ) . '" target="_blank">' . $post_title . '</a> by <span style="display: inline-block; color: #009344;">' . ( ! is_null( $post_event['username'] ) ? self::get_user_for_email( $post_event['username'] ) : self::get_user_name( $post_event['user_id'] ) ) . '</span>';
										}
										$body .= '</td>';
										$body .= '</tr>';
									}

									$body .= '</table>
										</td>
									</tr>';
								}
							}
						}

						$body .= '</table>
								</td>
							</tr>
						</table>
						<!-- Content Changes End -->';
					}
				}

				$body = apply_filters( 'wsal_append_dailynotification_email_content', $body, $events );

				// File changes.
				if ( ! empty( $files_added ) || ! empty( $files_modified ) || ! empty( $files_deleted ) ) {
					$body .= '<!-- File Changes Start -->
					<table role="presentation" width="640" border="0" cellpadding="0" cellspacing="0" role="presentation" class="responsive">
						<tr>
							<td align="center">
								<table width="100%" cellpadding="0" cellspacing="0" border="0">
									<tr>
										<td style="font-family: \'Quicksand\',\'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: bold; font-size: 20px; line-height: 22px; color: #1A3060; text-align: left; padding-top: 24px; padding-bottom: 8px;">File Changes</td>
									</tr>
									<tr>
										<td style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; text-align: left;">Below are the changes to files on your website:</td>
									</tr>
									<tr>
										<td style="padding-top: 20px; padding-bottom: 40px;">
											<table class="zebra-striped" width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid #D9E4FD; border-radius: 2px;">';

					if ( ! empty( $files_added ) ) {
						$body .= '<tr><td style="border: 1px solid #D9E4FD; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; padding-left: 24px; padding-right: 24px; padding-top: 10px; padding-bottom: 10px;">';
						$body .= 'Files added: <span style="display: inline-block; color: #009344;">' . count( $files_added ) . '</span>';
						$body .= '</td></tr>';
					}

					if ( ! empty( $files_modified ) ) {
						$body .= '<tr><td style="border: 1px solid #D9E4FD; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; padding-left: 24px; padding-right: 24px; padding-top: 10px; padding-bottom: 10px;">';
						$body .= 'Files modified: <span style="display: inline-block; color: #009344;">' . count( $files_modified ) . '</span>';
						$body .= '</td></tr>';
					}

					if ( ! empty( $files_deleted ) ) {
						$body .= '<tr><td style="border: 1px solid #D9E4FD; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #1A3060; padding-left: 24px; padding-right: 24px; padding-top: 10px; padding-bottom: 10px;">';
						$body .= 'Files deleted: <span style="display: inline-block; color: #009344;">' . count( $files_deleted ) . '</span>';
						$body .= '</td></tr>';
					}

					$body .= '</table>
										</td>
									</tr>
								</table>
							</td>
						</tr>
					</table>
					<!-- File Changes End -->';
				}
			}

			// Total events.
			$body .= '<!-- Total Events Start -->
			<table role="presentation" width="640" border="0" cellpadding="0" cellspacing="0" role="presentation" class="responsive">
				<tr>
					<td align="center">
						<table width="100%" cellpadding="0" cellspacing="0" border="0">
							<tr>
								<td style="padding-top: 20px; padding-bottom: 20px;">
									<table width="100%" cellpadding="0" cellspacing="0" border="0" style="border: none; border-radius: 4px;">
										<tr>
											<td style="border: 2px solid #BDD63A; border-radius: 4px; background: #E5EFB0; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #384A2F; padding-left: 24px; padding-right: 24px; padding-top: 20px; padding-bottom: 20px;">This email is only a summary of ' . $total_events . ' events recorded in your website\'s activity log ' . ( ( $report_wsal_end_date ) ? ' last week' : 'yesterday' ) . '. For a comprehensive overview and detailed insights into the activities on your website, please visit the <a href="' . \WpSecurityAuditLog::get_plugin_admin_url_page() . '" target="_blank" style="color: #384A2F;  font-weight: bold; display: inline-block;">activity log page</a></td>
										</tr>
									</table>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
			<!-- Total Events End -->';

			// Soft CTA.
			// This should only appear on the free version of the plugin.
			$body .= '<!-- Soft CTA Start -->
			<table role="presentation" width="640" border="0" cellpadding="0" cellspacing="0" role="presentation" class="responsive">
				<tr>
					<td align="center">
						<table width="100%" cellpadding="0" cellspacing="0" border="0">
							<tr>
								<td style="padding-top: 20px; padding-bottom: 20px;">
									<table width="100%" cellpadding="0" cellspacing="0" border="0" style="border: none; border-radius: 4px;">
										<tr>
											<td style="border: 2px solid #D9E4FD; border-radius: 4px; background: #F0F4FE; font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-weight: normal; font-size: 18px; line-height: 24px; color: #485D89; padding-left: 24px; padding-right: 24px; padding-top: 20px; padding-bottom: 20px;"><span style="font-weight: bold;">Elevate your security:</span> Access advanced insights, real-time monitoring, and instant alerts for an enhanced WordPress management experience.  <br><a href="https://melapress.com/wordpress-activity-log/?utm_source=wpal_email&utm_medium=email&utm_campaign=product_email&utm_content=cta_main" target="_blank" style="text-decoration:none; color: #485D89; font-weight: bold; line-height: 28px; font-size: 16px; display: inline-block; margin-top: 6px; margin-bottom: 4px; background-color: #B9CCF7; border-radius: 3px; padding-left: 12px; padding-right: 12px; padding-top: 3px; padding-bottom: 3px;">LEARN MORE &#8680;</a></td>
										</tr>
									</table>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
			<!-- Soft CTA End -->';

			// Documentation & Get Support.
			$body .= '<!-- Documentation & Get Support Start -->
			<table role="presentation" width="640" border="0" cellpadding="0" cellspacing="0" role="presentation" class="responsive">
				<tr>
					<td align="center" style="padding-bottom:80px;" class="inner-td">
			
						<!-- Two Columns Start -->
						<table role="presentation" align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
							<tr>
								<td align="center" valign="top" width="370" class="responsive-stack" style="padding-top:60px;">
									
									<!-- Documentation Start -->
									<table role="presentation" align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
										<tr>
											<td align="center" style="padding-bottom: 25px;" class="responsive-image">
												<img src="' . $media['documentation'] . '" width="99" height="117" border="0" style="display: block;" alt="" >
											</td>
										</tr>
										<tr>
											<td align="center" style="font-family: \'Quicksand\', \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-size:24px;line-height:28px;color:#1A3060;font-weight: 700; padding:0px 10px 15px;" class="mob-body-text" >
												Documentation
											</td>
										</tr>
										<tr>
											<td align="center" style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-size:18px;line-height:24px;color:#1A3060;font-weight: 400; padding:0px 10px 25px;" class="mob-body-text" >
												Refer to our <a style="color: #009344;" href="https://melapress.com/support/kb/?utm_source=wpal_email&utm_medium=email&utm_campaign=product_email&utm_content=cta_main">knowledge base</a> for plugin documentation
											</td>
										</tr>
									</table>
									<!-- Documentation End -->
			
								</td>
								<td style="font-size:1px; line-height: 1px;" width="60" class="hide">&nbsp;</td>
								<td align="center" valign="top" width="370" class="responsive-stack" style="padding-top:60px;">
									
									<!-- Get Support Start -->
									<table role="presentation" align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
										<tr>
											<td align="center" style="padding-bottom: 25px;" class="responsive-image">
												<img src="' . $media['support'] . '" width="120" height="117" border="0" style="display: block;" alt="" >
											</td>
										</tr>
										<tr>
											<td align="center" style="font-family: \'Quicksand\', \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-size:24px;line-height:28px;color:#1A3060;font-weight: 700; padding:0px 10px 15px;" class="mob-body-text" >
												Get Support
											</td>
										</tr>
										<tr>
											<td align="center" style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-size:18px;line-height:24px;color:#1A3060;font-weight: 400; padding:0px 10px 25px;" class="mob-body-text" >
												Need help? Email us on <a style="color: #009344;" href="mailto:info@melapress.com">info@melapress.com</a>
											</td>
										</tr>
									</table>
									<!-- Get Support End -->
			
								</td>
							</tr>
						</table>
						<!-- Two Columns Start -->
						
					</td>
				</tr>
			</table>
			<!-- Documentation & Get Support End -->';

			// Footer.
			$body .= '<!-- Footer Start -->
			<table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" bgcolor="#384A2F" style="min-width: 100%; background-image: url(' . $media['wsal-dg-footer-bg'] . '); background-repeat: no-repeat; background-position: top center; background-position-y: -1px;">
				<tr>
					<td width="100%" align="center" style="padding: 105px 0 16px 0;">
						<a href="https://melapress.com" target="_blank"><img src="' . $media['melapress-icon'] . '" width="42" height="42" border="0" style="display: block;" alt="Melapress"/></a>
					</td>
				</tr>
				<tr>
					<td align="center" style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-size: 16px; line-height: 22px; color: #ffffff; padding-top: 25px;" class="footer-text">
						If you\'re finding WP Activity Log helpful, consider trying our other plugins:<br> <a href="https://melapress.com/wordpress-2fa/?utm_source=wpal_email&utm_medium=email&utm_campaign=product_email&utm_content=cta_footer" target="_blank" style="color:#ffffff;">WP 2FA</a> and <a href="https://melapress.com/wordpress-login-security/?utm_source=wpal_email&utm_medium=email&utm_campaign=product_email&utm_content=cta_footer" target="_blank" style="color:#ffffff;">Melapress Login Security</a>.
					</td>
				</tr>
				<tr>
					<td width="100%" align="center" style="padding: 16px 0 16px;">
						<table role="presentation" align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
							<tr>
								<td align="center" style="font-family: \'Helvetica Neue\', Helvetica, Arial, \'Lucida Grande\', sans-serif; font-size: 13px; line-height: 22px; color: #E5EFB0; padding-top: 25px;" class="footer-text">
									This email is generated by WP Activity Log. To disable this daily overview navigate to the <a href="' . \add_query_arg( 'page', 'wsal-notifications', \network_admin_url( 'admin.php' ) ) . '#wsal-options-tab-notifications-highlights" target="_blank" style="color: #E5EFB0;">email notifications settings</a><br><br>
									<a href="https://melapress.com/wordpress-activity-log/?utm_source=wpal_email&utm_medium=email&utm_campaign=product_email&utm_content=cta_footer_byline" target="_blank" style="color: #E5EFB0;">WP Activity Log</a> is developed and maintained by <a href="https://melapress.com/?utm_source=wpal_email&utm_medium=email&utm_campaign=product_email&utm_content=cta_footer_byline" target="_blank" style="color: #E5EFB0;">Melapress</a>.<br><span style="white-space: nowrap;">Melapress Blaak 520 Rotterdam,</span> <span style="white-space: nowrap;">Zuid-Holland 3011 TA Netherlands</span>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
			<!-- Footer End -->';

			return $header . $body . $footer;
		}

		/**
		 * Get User Details for Email.
		 *
		 * Get Username/First Name/Last Name/Public Name to display in the emails.
		 *
		 * @param string $username – Username.
		 *
		 * @return string
		 *
		 * @since 5.2.2
		 */
		private static function get_user_for_email( $username ) {
			if ( 'username' === Plugin_Settings_Helper::get_type_username() ) {
				return $username;
			} else {
				// Check if user details are already set.
				if ( isset( self::$user_data[ $username ] ) && ! empty( self::$user_data[ $username ] ) ) {
					$user = self::$user_data[ $username ];
				} else {
					// If not set, then get user details.
					$user = get_user_by( 'login', $username );

					// Set user details.
					self::$user_data[ $username ] = $user;
				}

				// Type of detail to display.
				$display_name = '';

				if ( $user ) {
					// Check for the type of name to display.
					if ( 'display_name' === Plugin_Settings_Helper::get_type_username() && ! empty( $user->display_name ) ) {
						$display_name = $user->display_name;
					} elseif ( 'first_last_name' === Plugin_Settings_Helper::get_type_username() && ( ! empty( $user->first_name ) || ! empty( $user->last_name ) ) ) {
						$display_name = $user->first_name . ' ' . $user->last_name;
					} else {
						$display_name = $user->user_login;
					}
				} else {
					$display_name = $username;
				}

				return ( null !== $display_name ) ? $display_name : esc_html__( 'System', 'wp-security-audit-log' );
			}
		}

		/**
		 * Get User Details.
		 *
		 * Get Username/First Name/Last Name/Public Name to display in the emails.
		 *
		 * @param string $user_id – The user ID.
		 *
		 * @return string
		 *
		 * @since 5.2.2
		 */
		private static function get_user_name( $user_id ) {
			if ( null === $user_id ) {
				return \esc_html__( 'System', 'wp-security-audit-log' );
			}
			$user = User_Helper::get_user_object( $user_id );
			if ( 'username' === Plugin_Settings_Helper::get_type_username() ) {
				return $user->user_login;
			} else {
				// Type of detail to display.
				$display_name = '';

				if ( $user ) {
					// Check for the type of name to display.
					if ( 'display_name' === Plugin_Settings_Helper::get_type_username() && ! empty( $user->display_name ) ) {
						$display_name = $user->display_name;
					} elseif ( 'first_last_name' === Plugin_Settings_Helper::get_type_username() && ( ! empty( $user->first_name ) || ! empty( $user->last_name ) ) ) {
						$display_name = $user->first_name . ' ' . $user->last_name;
					} else {
						$display_name = $user->user_login;
					}
				} else {
					$display_name = null;
				}

				return ( null !== $display_name ) ? $display_name : esc_html__( 'System', 'wp-security-audit-log' );
			}
		}
	}
}
