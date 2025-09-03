<?php
/**
 * Class: Notification Helper.
 *
 * Logger class for wsal.
 *
 * @since 5.1.1
 *
 * @package    wsal
 * @subpackage helpers
 */

namespace WSAL\Extensions\Helpers;

use WSAL\Controllers\Alert_Manager;
use WSAL\Controllers\Slack\Slack;
use WSAL\Controllers\Slack\Slack_API;
use WSAL\Controllers\Twilio\Twilio;
use WSAL\Controllers\Twilio\Twilio_API;
use WSAL\Entities\Metadata_Entity;
use WSAL\Entities\Occurrences_Entity;
use WSAL\Helpers\DateTime_Formatter_Helper;
use WSAL\Helpers\Email_Helper;
use WSAL\Helpers\Settings_Helper;
use WSAL\Helpers\WP_Helper;
use WSAL\Views\Notifications;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Notifications Helper class
 */
if ( ! class_exists( '\WSAL\Extensions\Helpers\Notification_Helper' ) ) {
	/**
	 * This class triggers notifications if set.
	 *
	 * @package    wsal
	 * @subpackage helpers
	 *
	 * @since 5.1.1
	 */
	class Notification_Helper {

		public const VALUE_QUERY_PREFIX          = '^GGG^';
		public const NUMBER_OF_EVENTS_TO_INCLUDE = 10;

		/**
		 * Daily Report Events.
		 *
		 * Events to be included in the daily report summary.
		 *
		 * @var array
		 *
		 * @since 5.2.2
		 */
		public static $daily_report_events;

		// phpcs:disable
		// phpcs:enable

		/**
		 * Responsible for proper mapping between the event collected fields and the Query fields.
		 *
		 * @since 5.2.1
		 */
		public static function get_notifications_fields_name_mapping(): array {
			return array(
				'event_id'           => array(
					'interpolate' => '___QWE_EVENT_ID_QWE___',
					'type'        => 'integer',
					'label'       => esc_html__( 'Event ID', 'wp-security-audit-log' ),
					'operators'   => "'equal', 'not_equal'",
				),
				'eventtype'          => array(
					'interpolate' => '___QWE_TYPE_QWE___',
					'type'        => 'string',
					'label'       => esc_html__( 'Type', 'wp-security-audit-log' ),
					'operators'   => "'equal', 'not_equal'",
				),
				'severity'           => array(
					'interpolate' => '___QWE_SEVERITY_QWE___',
					'type'        => 'integer',
					'label'       => esc_html__( 'Severity', 'wp-security-audit-log' ),
					'operators'   => "'equal', 'not_equal'",
				),
				'posttype'           => array(
					'interpolate' => '___QWE_POSTTYPE_QWE___',
					'type'        => 'string',
					'label'       => esc_html__( 'Post Type', 'wp-security-audit-log' ),
					'operators'   => "'equal', 'not_equal'",
				),
				'poststatus'         => array(
					'interpolate' => '___QWE_POSTSTATUS_QWE___',
					'type'        => 'string',
					'label'       => esc_html__( 'Post Status', 'wp-security-audit-log' ),
					'operators'   => "'equal', 'not_equal'",
				),
				'object'             => array(
					'interpolate' => '___QWE_OBJECT_QWE___',
					'type'        => 'string',
					'label'       => esc_html__( 'Object', 'wp-security-audit-log' ),
					'operators'   => "'equal', 'not_equal'",
				),
				'roles'              => array(
					'interpolate' => '___QWE_USER_ROLE_QWE___',
					'type'        => 'string',
					'label'       => esc_html__( 'User Role', 'wp-security-audit-log' ),
					'operators'   => "'equal', 'not_equal'",
				),
				'currentuserroles'   => array(
					'interpolate' => '___QWE_USER_ROLE_QWE___',
					'type'        => 'string',
					'label'       => esc_html__( 'User Role', 'wp-security-audit-log' ),
					'operators'   => "'equal', 'not_equal'",
				),
				'username'           => array(
					'interpolate' => '___QWE_USER_NAME_QWE___',
					'type'        => 'string',
					'label'       => esc_html__( 'User Name', 'wp-security-audit-log' ),
					'operators'   => "'equal', 'not_equal'",
				),
				'postid'             => array(
					'interpolate' => '___QWE_POST_ID_QWE___',
					'type'        => 'integer',
					'label'       => esc_html__( 'Post ID', 'wp-security-audit-log' ),
					'operators'   => "'equal', 'not_equal'",
				),
				'orderid'            => array(
					'interpolate' => '___QWE_ORDER_ID_QWE___',
					'type'        => 'integer',
					'label'       => esc_html__( 'Order ID', 'wp-security-audit-log' ),
					'operators'   => "'equal', 'not_equal'",
				),
				'productid'          => array(
					'interpolate' => '___QWE_PRODUCT_ID_QWE___',
					'type'        => 'integer',
					'label'       => esc_html__( 'Product ID', 'wp-security-audit-log' ),
					'operators'   => "'equal', 'not_equal'",
				),
				'couponid'           => array(
					'interpolate' => '___QWE_COUPON_ID_QWE___',
					'type'        => 'integer',
					'label'       => esc_html__( 'Coupon ID', 'wp-security-audit-log' ),
					'operators'   => "'equal', 'not_equal'",
				),
				'productstatus'      => array(
					'interpolate' => '___QWE_PRODUCT_STATUS_QWE___',
					'type'        => 'string',
					'label'       => esc_html__( 'Product Status', 'wp-security-audit-log' ),
					'operators'   => "'equal', 'not_equal'",
				),
				'sku'                => array(
					'interpolate' => '___QWE_SKU_QWE___',
					'type'        => 'string',
					'label'       => esc_html__( 'Product SKU', 'wp-security-audit-log' ),
					'operators'   => "'equal', 'not_equal'",
				),
				'clientip'           => array(
					'interpolate' => '___QWE_SOURCE_IP_QWE___',
					'type'        => 'string',
					'label'       => esc_html__( 'Source IP', 'wp-security-audit-log' ),
					'operators'   => "'equal', 'contains', 'not_contains', 'not_equal'",
				),
				'date'               => array(
					'interpolate' => '___QWE_DATE_QWE___',
					'type'        => 'date',
					'label'       => esc_html__( 'Date', 'wp-security-audit-log' ),
					'operators'   => "'equal', 'less', 'greater', 'not_equal'",
				),
				'time'               => array(
					'interpolate' => '___QWE_TIME_QWE___',
					'type'        => 'string',
					'label'       => esc_html__( 'Time', 'wp-security-audit-log' ),
					'operators'   => "'equal', 'less', 'greater'",
				),
				'siteid'             => array(
					'interpolate' => '___QWE_SITE_ID_QWE___',
					'type'        => 'string',
					'label'       => esc_html__( 'Site', 'wp-security-audit-log' ),
					'operators'   => "'equal', 'not_equal'",
				),
				'custom_field_name'  => array(
					'interpolate' => '___QWE_CUSTOM_FIELD_NAME_QWE___',
					'type'        => 'string',
					'label'       => esc_html__( 'Custom User Field', 'wp-security-audit-log' ),
					'operators'   => "'equal', 'not_equal'",
				),
				'affected_user_role' => array(
					'interpolate' => '___QWE_AFFECTED_USER_ROLE_QWE___',
					'type'        => 'string',
					'label'       => esc_html__( 'Affected User Role', 'wp-security-audit-log' ),
					'operators'   => "'equal', 'not_equal'",
				),
			);
		}

		/**
		 * Returns all the alert_ids that must be included in the daily report.
		 *
		 * @since 5.3.0
		 */
		private static function get_default_report_event_ids(): array {
			if ( null === self::$daily_report_events ) {
				self::$daily_report_events = array_merge( array( 1000, 1005, 1002, 1003, 2001, 2008, 2012, 2065, 4000, 4001, 4002, 4003, 4004, 4007, 4010, 4011, 5000, 5001, 5002, 5003, 5004, 6028, 6029, 6030, 7000, 7001, 7002, 7003, 7004, 7005, 2021 ), \array_keys( Alert_Manager::get_alerts_by_category( esc_html__( 'WordPress & System', 'wp-security-audit-log' ) ) ) );
			}

			return self::$daily_report_events;
		}

		/**
		 * Returns report email body.
		 *
		 * @param bool $test - Test report (Sends current date's report).
		 * @param bool $weekly - Is that weekly report or not.
		 *
		 * @since 5.2.2
		 */
		public static function get_report( $test = false, $weekly = false ): array {
			$date_format = Settings_Helper::get_date_format(); // Get date format.
			$date_obj    = new \DateTime();
			$date_obj->setTime( 0, 0 ); // Set time of the object to 00:00:00.
			$date_string = $date_obj->format( 'U' ); // Get the date in UNIX timestamp.

			$current_settings = Settings_Helper::get_option_value( Notifications::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME, array() );

			if ( $weekly ) {
				$disable_if_empty = isset( $current_settings['weekly_send_empty_summary_emails'] ) ? ! (bool) $current_settings['weekly_send_empty_summary_emails'] : false; // Option to disable if no alerts found.
			} else {
				$disable_if_empty = isset( $current_settings['daily_send_empty_summary_emails'] ) ? ! (bool) $current_settings['daily_send_empty_summary_emails'] : false; // Option to disable if no alerts found.
			}

			if ( ! $test ) {
				if ( $weekly ) {
					$start = strtotime( '-7 day +1 second', $date_string ); // Get yesterday's starting timestamp.
				} else {
					$start = strtotime( '-1 day +1 second', $date_string ); // Get yesterday's starting timestamp.
				}
				$end = strtotime( '-1 second', $date_string ); // Get yesterday's ending timestamp.
			} else {
				// If test then set the start and end timestamps to today's date.
				$start = strtotime( '+1 second', $date_string );
				$end   = strtotime( '+1 day -1 second', $date_string );
			}

			if ( $test ) {
				$site_id = 0;
			} else {
				$site_id = WP_Helper::get_blog_id();
			}

			$query = array();
			// if we have a site ID then add it as condition.
			if ( $site_id ) {
				$query['AND'][] = array( ' site_id = %s ' => $site_id );
			}
			// add condition to check only alerts that are daily report events.
			$query['AND'][] = array( 'find_in_set( alert_id, %s ) > 0 ' => implode( ',', self::get_default_report_event_ids() ) );
			// from this time.
			$query['AND'][] = array( ' created_on >= %s ' => $start );
			// till this time.
			$query['AND'][] = array( ' created_on <= %s ' => $end ); // To the hour 23:59:59.

			$meta_table_name = Metadata_Entity::get_table_name();
			$join_clause     = array(
				$meta_table_name => array(
					'direction'   => 'LEFT',
					'join_fields' => array(
						array(
							'join_field_left'  => 'occurrence_id',
							'join_table_right' => Occurrences_Entity::get_table_name(),
							'join_field_right' => 'id',
						),
					),
				),
			);
			// order results by date and return the query.
			$meta_full_fields_array       = Metadata_Entity::prepare_full_select_statement();
			$occurrence_full_fields_array = Occurrences_Entity::prepare_full_select_statement();
			$events                       = Occurrences_Entity::build_query( array_merge( $meta_full_fields_array, $occurrence_full_fields_array ), $query, array( 'created_on' => 'ASC' ), array(), $join_clause );

			$events       = Occurrences_Entity::prepare_with_meta_data( $events );
			$total_events = count( $events );

			if ( ! $test && $disable_if_empty && empty( $events ) ) {
				return array();
			}

			$home_url = home_url();
			$safe_url = str_replace( array( 'http://', 'https://' ), '', $home_url );

			// the date displayed in daily reports.
			$display_date    = gmdate( $date_format, $start );
			$report_date     = gmdate( 'Y-m-d', $start );
			$report_end_date = false;
			if ( $weekly ) {
				$report_end_date  = gmdate( 'Y-m-d', $end );
				$display_end_date = gmdate( $date_format, $end );
			}

			// Report object.
			$report            = array();
			$report['subject'] = 'Activity Log Highlight from ' . $safe_url . ' on ' . $display_date; // Email subject.
			if ( $weekly ) {
				$report['subject'] .= ' - ' . $display_end_date;
			}
			$report['body'] = Notification_Template::generate_report_body( $events, $display_date, $total_events, $report_date, $report_end_date ); // Email body.

			return $report;
		}

		/**
		 * Send notifications email.
		 *
		 * @param string $email_address - Email Address.
		 * @param string $subject       - Email subject.
		 * @param string $content       - Email content.
		 * @param int    $alert_id      - (Optional) Alert ID.
		 *
		 * @return bool
		 *
		 * @since 5.2.2
		 */
		public static function send_notification_email( $email_address, $subject, $content, $alert_id = 0 ) {
			if ( class_exists( '\WSAL\Helpers\Email_Helper' ) ) {
				// Get email addresses even when there is the Username.
				$email_address = Email_Helper::get_emails( $email_address );
				if ( WSAL_NOTIFICATIONS_DEBUG ) {
					error_log('WP Activity Log Notification'); // phpcs:ignore
					error_log('Email address: ' . $email_address); // phpcs:ignore
					error_log('Alert ID: ' . $alert_id); // phpcs:ignore
				}

				// Give variable a value.
				$result = false;

				// Get email template.
				$result = Email_Helper::send_email( $email_address, $subject, $content );
			}

			if ( WSAL_NOTIFICATIONS_DEBUG ) {
				error_log('Email success: ' . print_r($result, true)); // phpcs:ignore
			}

			return $result;
		}

		/**
		 * Returns no default email is set text.
		 *
		 * @since 5.3.0
		 */
		public static function no_default_email_is_set(): string {
			return '<span style="color:red">' . esc_html__( ' Currently no default email is set.', 'wp-security-audit-log' ) . '</span>';
		}

		/**
		 * Returns no default phone is set text.
		 *
		 * @since 5.3.0
		 */
		public static function no_default_phone_is_set(): string {
			return '<span style="color:red">' . esc_html__( ' Currently no default phone is set.', 'wp-security-audit-log' ) . '</span>';
		}

		/**
		 * Returns no default slack channel is set text.
		 *
		 * @since 5.3.4
		 */
		public static function no_default_slack_is_set(): string {
			return '<span style="color:red">' . esc_html__( ' Currently no default slack channel is set.', 'wp-security-audit-log' ) . '</span>';
		}

		/**
		 * Email settings array function.
		 *
		 * @param string $id            - The name of the id of the field.
		 * @param string $settings_name - The name of the setting to use.
		 * @param string $name          - The name (title) of the field.
		 *
		 * @since 5.3.0
		 */
		public static function email_settings_array( string $id, string $settings_name, string $name = '' ): array {
			$options = array(
				'id'            => $id,
				'type'          => 'text',
				'pattern'       => '([a-zA-Z0-9\._\%\+\-]+@[a-zA-Z0-9\.\-]+\.[a-zA-Z]{2,20}[,]{0,}){0,}',
				'hint'          => esc_html__( 'Leave empty if you want to use default one. You can enter multiple email addresses separated by commas. Do not use a space in between the email addresses and commas. For example: support@melapress.com,info@melapress.com', 'wp-security-audit-log' ),
				'settings_name' => $settings_name,
			);
			if ( '' === $name ) {
				$name = esc_html__( 'Email address(es): ', 'wp-security-audit-log' );
			}

			$options['name'] = $name;

			return $options;
		}

		/**
		 * Phone settings default array function.
		 *
		 * @param string $id            - The name of the id of the field.
		 * @param string $settings_name - The name of the setting to use.
		 * @param string $name          - The name (title) of the field.
		 * @param string $hint          - The hint text to show under field.
		 *
		 * @since 5.3.0
		 */
		public static function phone_settings_array( string $id, string $settings_name, string $name = '', string $hint = '' ): array {
			$options = array(
				'id'            => $id,
				'type'          => 'text',
				'pattern'       => '\+\d+',
				'validate'      => 'tel',
				'title_attr'    => esc_html__( 'Please use the following format: +16175551212', 'wp-security-audit-log' ),
				'max_chars'     => 20,
				'placeholder'   => esc_html__( '+16175551212', 'wp-security-audit-log' ),
				'hint'          => ( empty( $hint ) ) ? esc_html__( 'Leave empty if you want to use default one. Format you must use is: +16175551212', 'wp-security-audit-log' ) : $hint,
				'settings_name' => $settings_name,
			);
			if ( '' === $name ) {
				$name = esc_html__( 'Phone number: ', 'wp-security-audit-log' );
			}

			$options['name'] = $name;

			return $options;
		}

		/**
		 * Returns the default phone settings error array.
		 *
		 * @param string $id            - The name of the id of the field.
		 * @param string $settings_name - The name of the setting to use.
		 *
		 * @since 5.3.0
		 */
		public static function phone_settings_error_array( string $id, string $settings_name ): array {
			$options = array(
				'name'          => esc_html__( 'Phone number: ', 'wp-security-audit-log' ),
				'id'            => $id,
				'type'          => 'error_text',
				'text'          => '<span>' . esc_html__( 'In order to send notifications via SMS messages please configure the Twilio integration in the ', 'wp-security-audit-log' ) . '<a class="inner_links" href="#" data-section="twilio-notification-settings" data-url="wsal-options-tab-notification-settings">' . esc_html__( 'settings.', 'wp-security-audit-log' ) . ' </a></span>',
				'settings_name' => $settings_name,
			);

			return $options;
		}


		/**
		 * Slack settings default array function.
		 *
		 * @param string $id            - The name of the id of the field.
		 * @param string $settings_name - The name of the setting to use.
		 * @param string $name          - The name (title) of the field.
		 * @param string $hint          - The hint text to show under field.
		 *
		 * @since 5.3.4
		 */
		public static function slack_settings_array( string $id, string $settings_name, string $name = '', string $hint = '' ): array {
			$options = array(
				'id'            => $id,
				'type'          => 'text',
				'max_chars'     => 80,
				'placeholder'   => esc_html__( 'WSAL notifications', 'wp-security-audit-log' ),
				'hint'          => ( empty( $hint ) ) ? esc_html__( 'Leave empty if you want to use default one.', 'wp-security-audit-log' ) : $hint,
				'settings_name' => $settings_name,
			);
			if ( '' === $name ) {
				$name = esc_html__( 'Slack channel: ', 'wp-security-audit-log' );
			}

			$options['name'] = $name;

			return $options;
		}

		/**
		 * Returns the default slack settings error array.
		 *
		 * @param string $id            - The name of the id of the field.
		 * @param string $settings_name - The name of the setting to use.
		 *
		 * @since 5.3.4
		 */
		public static function slack_settings_error_array( string $id, string $settings_name ): array {
			$options = array(
				'name'          => esc_html__( 'Slack channel: ', 'wp-security-audit-log' ),
				'id'            => $id,
				'type'          => 'error_text',
				'text'          => '<span>' . esc_html__( 'In order to send notifications via Slack messages please configure the Slack integration in the ', 'wp-security-audit-log' ) . '<a class="inner_links" href="#" data-section="slack-notification-settings" data-url="wsal-options-tab-notification-settings">' . esc_html__( 'settings.', 'wp-security-audit-log' ) . ' </a></span>',
				'settings_name' => $settings_name,
			);

			return $options;
		}

		/**
		 * Default channel hint for email, phone and slack.
		 *
		 * @return string
		 *
		 * @since 5.4.2
		 */
		public static function default_hint_channels_set() {

			$defaults = '';
			if ( Notifications::is_default_mail_set() ) {
				return '';
				$current_default_mail = Notifications::get_default_mail();
				$defaults            .= esc_html__( ' Currently default email is set to: ', 'wp-security-audit-log' ) . $current_default_mail;
			} else {
				$defaults .= self::no_default_email_is_set();
			}

			if ( Notifications::is_default_twilio_set() ) {
				$current_default_twilio = Notifications::get_default_twilio();
				$defaults              .= esc_html__( ' Currently default phone is set to: ', 'wp-security-audit-log' ) . $current_default_twilio;
			} else {
				$defaults .= self::no_default_phone_is_set();
			}

			if ( Notifications::is_default_slack_set() ) {
				$current_default_twilio = Notifications::get_default_slack();
				$defaults              .= esc_html__( ' Currently default slack channel is set to: ', 'wp-security-audit-log' ) . $current_default_twilio;
			} else {
				$defaults .= self::no_default_slack_is_set();
			}

			return \esc_html__( 'You can set default email / phone for all notifications in ', 'wp-security-audit-log' ) . '<a class="inner_links" href="#" data-section="notification-default-settings" data-url="wsal-options-tab-notification-settings">' . \esc_html__( 'settings', 'wp-security-audit-log' ) . '</a>, ' . \esc_html__( 'or check this and specify ones for this event', 'wp-security-audit-log' ) . $defaults;
		}
	}
}
