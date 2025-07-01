<?php
/**
 * Version 5.3 migration class.
 *
 * @package    wsal
 * @subpackage utils
 * @copyright  2025 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WSAL\Utils;

use WSAL\Helpers\Notices;
use WSAL\Helpers\WP_Helper;
use WSAL\MainWP\MainWP_Addon;
use WSAL\Views\Notifications;
use WSAL\Controllers\Cron_Jobs;
use WSAL\Controllers\Connection;
use WSAL\Helpers\Settings_Helper;
use WSAL\Extensions\Views\Reports;
use WSAL\Controllers\Alert_Manager;
use WSAL\Entities\Occurrences_Entity;
use WSAL\Controllers\Twilio\Twilio_API;
use WSAL\Entities\Query_Builder_Parser;
use WSAL\Entities\Custom_Notifications_Entity;
use WSAL\Extensions\Helpers\Notification_Helper;
use WSAL\Helpers\Email_Helper;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Abstract Migration class
 */
if ( ! class_exists( '\WSAL\Utils\Migrate_53' ) ) {

	/**
	 * Utility class to ease the migration for version 5.3.
	 *
	 * @package WP2FA\Utils
	 *
	 * @since 5.2.2
	 */
	class Migrate_53 {

		/**
		 * Migration for version upto 5.3.0
		 *
		 * Migrates notification settings
		 *
		 * Note: The migration methods need to be in line with the @see WSAL\Utils\Abstract_Migration::$pad_length
		 *
		 * @return void
		 *
		 * @since 5.3.0
		 */
		public static function migrate_up_to_5300() {

			if ( ! Occurrences_Entity::check_index_exists( 'wsal_users' ) ) {
				// Add index to user_id column.
				$upgrade_sql = Occurrences_Entity::get_users_index_query();
				Occurrences_Entity::get_connection()->suppress_errors( true );
				Occurrences_Entity::get_connection()->query( $upgrade_sql );
				Occurrences_Entity::get_connection()->suppress_errors( false );
			}


			Settings_Helper::delete_option_value( Notices::EBOOK_NOTICE );

			if ( 'free' === \WpSecurityAuditLog::get_plugin_version() ) {
				Cron_Jobs::un_schedule_event( 'wsal_generate_reports_cron' );
				Cron_Jobs::un_schedule_event( 'wsal_periodic_reports_daily' );
				Cron_Jobs::un_schedule_event( 'wsal_periodic_reports_weekly' );
				Cron_Jobs::un_schedule_event( 'wsal_periodic_reports_monthly' );
				Cron_Jobs::un_schedule_event( 'wsal_periodic_reports_quarterly' );
			}

			if ( ! WP_Helper::is_multisite() || MainWP_Addon::check_mainwp_plugin_active() ) {

				if ( 'free' !== \WpSecurityAuditLog::get_plugin_version() ) {
					$columns = Settings_Helper::get_option_value( Reports::REPORT_GENERATE_COLUMNS_SETTINGS_NAME, array() );

					if ( ! empty( $columns ) ) {
						$columns['site_id'] = false;
						Settings_Helper::set_option_value( Reports::REPORT_GENERATE_COLUMNS_SETTINGS_NAME, $columns );
					}
				}
			}

			Settings_Helper::delete_option_value( 'extensions-merged-notice' );
			Settings_Helper::delete_option_value( 'installed_plugin_addon_available' );
			Settings_Helper::delete_option_value( 'addon_available_notice_dismissed' );

			Settings_Helper::delete_option_value( 'wsal_bbpress_addon_available_notice_dismissed' );
			Settings_Helper::delete_option_value( 'wsal_gravityforms_addon_available_notice_dismissed' );
			Settings_Helper::delete_option_value( 'wsal_woocommerce_addon_available_notice_dismissed' );
			Settings_Helper::delete_option_value( 'wsal_wp-seo_addon_available_notice_dismissed' );
			Settings_Helper::delete_option_value( 'wsal_wpforms_addon_available_notice_dismissed' );

			$options = Notifications::get_global_notifications_setting();

			$name         = 'builder';
			$template     = array();
			$opt_name     = 'email-template-' . $name;
			$opt_template = Settings_Helper::get_option_value( $opt_name );
			if ( ! empty( $opt_template ) ) {
				$template = json_decode( wp_json_encode( $opt_template ), true );

				$options['email_notifications_body']    = json_encode( \wpautop( \wp_unslash( $template['body'] ) ) );
				$options['email_notifications_subject'] = \sanitize_text_field( \wp_unslash( $template['subject'] ) );

				Settings_Helper::delete_option_value( $opt_name );
			}

			$template     = array();
			$opt_name     = 'sms-template-' . $name;
			$opt_template = Settings_Helper::get_option_value( $opt_name );
			if ( ! empty( $opt_template ) ) {
				$template = json_decode( wp_json_encode( $opt_template ), true );

				$options['sms_notifications_body'] = json_encode( stripslashes( \wp_unslash( $template['body'] ) ) );

				Settings_Helper::delete_option_value( $opt_name );
			}

			$options['shorten_notification_urls']      = (bool) Settings_Helper::get_boolean_option_value( 'is-url-shortner', false );
			$options['notification_bitly_shorten_key'] = (string) Settings_Helper::get_option_value( 'url-shortner-access-token' );

			Settings_Helper::delete_option_value( 'is-url-shortner' );
			Settings_Helper::delete_option_value( 'url-shortner-access-token' );

			Settings_Helper::set_option_value(
				Notifications::NOTIFICATIONS_SETTINGS_NAME,
				$options
			);

			if ( Settings_Helper::get_option_value( 'twilio-account-sid', false ) ) {
				$options['twilio_notification_account_sid'] = \sanitize_text_field( \wp_unslash( Settings_Helper::get_option_value( 'twilio-account-sid' ) ) );
			}

			Settings_Helper::delete_option_value( 'twilio-account-sid' );

			if ( Settings_Helper::get_option_value( 'twilio-auth-token', false ) ) {
				$options['twilio_notification_auth_token'] = \sanitize_text_field( \wp_unslash( Settings_Helper::get_option_value( 'twilio-auth-token' ) ) );
			}

			Settings_Helper::delete_option_value( 'twilio-auth-token' );

			if ( Settings_Helper::get_option_value( 'twilio-number', false ) ) {
				$options['twilio_notification_phone_number'] = \sanitize_text_field( \wp_unslash( Settings_Helper::get_option_value( 'twilio-number' ) ) );

			}

			Settings_Helper::delete_option_value( 'twilio-number' );

			if ( isset( $options['twilio_notification_account_sid'] ) && isset( $options['twilio_notification_auth_token'] ) && isset( $options['twilio_notification_phone_number'] ) ) {
				$twilio_valid = Twilio_API::check_credentials(
					(string) $options['twilio_notification_account_sid'],
					(string) $options['twilio_notification_auth_token'],
					(string) $options['twilio_notification_phone_number']
				);

				if ( $twilio_valid ) {
					Settings_Helper::set_option_value(
						Notifications::NOTIFICATIONS_SETTINGS_NAME,
						$options
					);
				}
			}

			$options = Settings_Helper::get_option_value( Notifications::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME, array() );

			if ( Settings_Helper::get_option_value( 'daily-summary-email', Email_Helper::get_default_email_to() ) ) {
				$options['daily_email_address'] = \sanitize_text_field( \wp_unslash( Settings_Helper::get_option_value( 'daily-summary-email', Email_Helper::get_default_email_to() ) ) );

				$options['daily_email_address'] = self::generate_email_string( $options['daily_email_address'] );

				$options['weekly_email_address'] = $options['daily_email_address'];
			}

			Settings_Helper::delete_option_value( 'daily-summary-email' );

			if ( 'free' === \WpSecurityAuditLog::get_plugin_version() ) {
				$options['weekly_summary_notification'] = true;
			} elseif ( ! Settings_Helper::string_to_bool( Settings_Helper::get_option_value( 'disable-daily-summary', false ) ) ) {
				$options['daily_summary_notification'] = true;

				$options['weekly_summary_notification'] = false;
			} else {
				$options['weekly_summary_notification'] = true;
			}

			Settings_Helper::delete_option_value( 'disable-daily-summary' );

			if ( ! Settings_Helper::get_boolean_option_value( 'disable-daily-summary-if-no-activity', false ) ) {
				$options['daily_send_empty_summary_emails'] = true;
			}

			Settings_Helper::delete_option_value( 'disable-daily-summary-if-no-activity' );

			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			if ( Settings_Helper::get_option_value( 'notification-built-in-16', false ) ) {
				$not_info = \maybe_unserialize( Settings_Helper::get_option_value( 'notification-built-in-16', false ) );

				if ( \is_object( $not_info ) && \property_exists( $not_info, 'failUser' ) ) {
					$options['event_1002_notification']               = true;
					$options['event_1002_notification_email_address'] = self::generate_email_string( $not_info->email );
					$options['event_1002_notification_phone']         = $not_info->phone;
					$options['event_1002_failed_more_than']           = $not_info->failUser;

					if ( 2 > $options['event_1002_failed_more_than'] ) {
						$options['event_1002_failed_more_than'] = 2;
					}
					if ( 30 < $options['event_1002_failed_more_than'] ) {
						$options['event_1002_failed_more_than'] = 30;
					}

					$options['notification_ids'][] = 1002;
				}
			}

			Settings_Helper::delete_option_value( 'notification-built-in-16' );

			if ( Settings_Helper::get_option_value( 'notification-built-in-17', false ) ) {
				$not_info = \maybe_unserialize( Settings_Helper::get_option_value( 'notification-built-in-17', false ) );

				if ( \is_object( $not_info ) && \property_exists( $not_info, 'failNotUser' ) ) {
					$options['event_1003_notification']               = true;
					$options['event_1003_notification_email_address'] = self::generate_email_string( $not_info->email );
					$options['event_1003_notification_phone']         = $not_info->phone;
					$options['event_1003_failed_more_than']           = $not_info->failNotUser;

					if ( 2 > $options['event_1003_failed_more_than'] ) {
						$options['event_1003_failed_more_than'] = 2;
					}
					if ( 30 < $options['event_1003_failed_more_than'] ) {
						$options['event_1003_failed_more_than'] = 30;
					}

					$options['notification_ids'][] = 1003;
				}
			}

			Settings_Helper::delete_option_value( 'notification-built-in-17' );

			Settings_Helper::delete_option_value( 'notification-built-in-8' ); // First time user logs in is no longer supported.

			$old_new_array = array(
				37 => 6004,
				9  => 5000,
				10 => 5001,
				11 => 2051,
				38 => 5002,
				39 => 5003,
				40 => 5004,
				12 => 5005,
				13 => 5006,
				14 => 2046,
				41 => 5007,
				42 => 5031,
				1  => 1000,
				3  => 4003,
				43 => 4005,
				5  => 4002,
				4  => 4004,
				2  => 4000,
				20 => 2001,
				21 => 2065,
				22 => 2002,
				33 => 9000,
				34 => 9027,
				35 => 9063,
				36 => 9035,
				26 => 4008,
				27 => 4009,
				28 => 4010,
				29 => 4011,
				30 => 7000,
				31 => 5008,
				32 => 5009,
			);

			foreach ( $old_new_array as $old => $new ) {
				if ( Settings_Helper::get_option_value( 'notification-built-in-' . $old, false ) ) {
					$not_info = \maybe_unserialize( Settings_Helper::get_option_value( 'notification-built-in-' . $old, false ) );

					if ( \is_object( $not_info ) ) {
						$options[ 'event_' . $new . '_notification' ]               = true;
						$options[ 'event_' . $new . '_notification_email_address' ] = self::generate_email_string( $not_info->email );
						$options[ 'event_' . $new . '_notification_phone' ]         = $not_info->phone;

						$options['notification_ids'][] = $new;

						if ( isset( Notifications::$additional_events_to_store[ $new ] ) ) {
							$options['notification_ids'] = \array_merge( $options['notification_ids'], Notifications::$additional_events_to_store[ $new ] );
						}
					}

					Settings_Helper::delete_option_value( 'notification-built-in-' . $old );
				}
			}

			$severities_to_collect = array(
				15 => 500, // This is a severity thats why.
			);

			foreach ( $severities_to_collect as $old => $new ) {
				if ( Settings_Helper::get_option_value( 'notification-built-in-' . $old, false ) ) {
					$not_info = \maybe_unserialize( Settings_Helper::get_option_value( 'notification-built-in-' . $old, false ) );

					if ( \is_object( $not_info ) ) {
						$options[ 'event_' . $new . '_notification' ]               = true;
						$options[ 'event_' . $new . '_notification_email_address' ] = self::generate_email_string( $not_info->email );
						$options[ 'event_' . $new . '_notification_phone' ]         = $not_info->phone;

						$options['notification_severities'][] = $new;
					}

					Settings_Helper::delete_option_value( 'notification-built-in-' . $old );
				}
			}
			// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

			if ( empty( $options ) ) {
				Settings_Helper::delete_option_value( Notifications::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME );
			} else {
				Settings_Helper::set_option_value( Notifications::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME, $options );
			}

			self::migrate_legacy_notifications();
		}

		/**
		 * Migrates legacy notifications
		 *
		 * @return void
		 *
		 * @since 5.2.2
		 */
		private static function migrate_legacy_notifications() {

			global $wpdb;

			if ( ! \defined( 'WSAL_OPT_PREFIX' ) ) {
				/**
				 * Holds the option prefix
				 */
				define( 'WSAL_OPT_PREFIX', 'notification-' );
			}

			$opt_prefix     = WSAL_PREFIX . WSAL_OPT_PREFIX;
			$prepared_query = $wpdb->prepare(
				"SELECT * FROM {$wpdb->base_prefix}options WHERE option_name LIKE %s;",
				$opt_prefix . '%%'
			);

			$notifications = $wpdb->get_results( $prepared_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			foreach ( $notifications as $notification ) {

				$report_options = array();

				$not_info = maybe_unserialize( $notification->option_value );

				$conditions = $not_info->triggers;
				$num        = count( $conditions );

				$user = \get_user_by( 'id', $not_info->owner );

				if ( $user ) {
					$report_options['notification_username'] = \get_user_by( 'id', $not_info->owner )->user_login;
				} else {
					$report_options['notification_username'] = 'None';
				}

				$report_options['notification_user_id'] = $not_info->owner;

				$report_options['notification_email_bcc']  = '';
				$report_options['notification_email_user'] = false;

				$report_options['notification_status']     = (bool) intval( $not_info->status );
				$report_options['notification_title']      = $not_info->title;
				$report_options['notification_email']      = self::generate_email_string( $not_info->email );
				$report_options['notification_phone']      = ! empty( $not_info->phone ) ? $not_info->phone : false;
				$report_options['notification_template']   = array();
				$report_options['notification_view_state'] = 1;

				if ( ! empty( $not_info->subject ) && ! empty( $not_info->body ) ) {
					$report_options['notification_template']['custom_notification_template_enabled'] = true;
					$report_options['notification_template']['email_custom_notifications_subject']   = $not_info->subject;
					$report_options['notification_template']['email_custom_notifications_body']      = $not_info->body;
				} else {
					$report_options['notification_template']['custom_notification_template_enabled'] = false;
				}

				if ( 1 === $num ) {
					$condition = $conditions[0];

					// Handle PAGE ID AND CUSTOM POST ID deprecation.
					if ( 7 === $condition['select2'] || 8 === $condition['select2'] ) {
						$condition['select2'] = 6;
					}

					$operator        = self::get_select_1_data()[ $condition['select1'] ]; // AND or OR.
					$filter          = self::notification_filter_mapper()[ $condition['select2'] ]; // Filter key.
					$filter_operator = self::get_select_3_data()[ $condition['select3'] ]; // Operater.

					$poststatus = isset( $condition['select4'] ) ? self::get_select_4_data()[ $condition['select4'] ] : false; // Post status select.
					$posttype   = ( isset( $condition['select5'] ) ) ? self::get_post_type_values()[ $condition['select5'] ] : false; // Post type select.
					$roles      = ( isset( $condition['select6'] ) ) ? self::get_roles_values()[ $condition['select6'] ] : false; // User roles select.
					$object     = ( isset( $condition['select7'] ) ) ? self::get_object_data_values()[ $condition['select7'] ] : false; // Object select.
					$eventtype  = ( isset( $condition['select8'] ) ) ? self::get_event_type_values()[ $condition['select8'] ] : false; // Event type select.

					$event_id = ( isset( $condition['input1'] ) ) ? $condition['input1'] : false;
					$date     = ( isset( $condition['input1'] ) ) ? $condition['input1'] : false;
					$time     = ( isset( $condition['input1'] ) ) ? $condition['input1'] : false;
					if ( $time ) {
						$time = \gmdate( 'H:i', \strtotime( $time ) );
					}
					$username          = ( isset( $condition['input1'] ) ) ? $condition['input1'] : false;
					$clientip          = ( isset( $condition['input1'] ) ) ? $condition['input1'] : false;
					$postid            = ( isset( $condition['input1'] ) ) ? $condition['input1'] : false;
					$siteid            = ( isset( $condition['input1'] ) ) ? $condition['input1'] : false;
					$custom_field_name = ( isset( $condition['input1'] ) ) ? $condition['input1'] : false;

					$mapped_fields = Notification_Helper::get_notifications_fields_name_mapping()[ $filter ];

					$notification_array = array(
						'condition' => $operator,
						'rules'     => array(
							array(
								'id'                 => $mapped_fields['interpolate'],
								'field'              => $mapped_fields['interpolate'],
								'type'               => $mapped_fields['type'],
								'input'              => 'text',
								'operator'           => $filter_operator,
								'value'              => $$filter,

								'condition_operator' => self::get_select_1_data()[ $condition['select1'] ],
							),
						),
						'valid'     => true,
					);

				} else {
					// #! n conditions
					$notification_array = array();

					$groups  = $not_info->viewState; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$last_id = 0;
					foreach ( $groups as $i => $entry ) {
						$i = $last_id;
						if ( \is_string( $entry ) ) {

							$condition = $conditions[ $i ];
							// Handle PAGE ID AND CUSTOM POST ID deprecation.
							if ( 7 === $condition['select2'] || 8 === $condition['select2'] ) {
								$condition['select2'] = 6;
							}

							$operator        = self::get_select_1_data()[ $condition['select1'] ]; // AND or OR.
							$filter          = self::notification_filter_mapper()[ $condition['select2'] ]; // Filter key.
							$filter_operator = self::get_select_3_data()[ $condition['select3'] ]; // Operater.

							$poststatus = isset( $condition['select4'] ) ? self::get_select_4_data()[ $condition['select4'] ] : false; // Post status select.
							$posttype   = ( isset( $condition['select5'] ) ) ? self::get_post_type_values()[ $condition['select5'] ] : false; // Post type select.
							$roles      = ( isset( $condition['select6'] ) ) ? self::get_roles_values()[ $condition['select6'] ] : false; // User roles select.
							$object     = ( isset( $condition['select7'] ) ) ? self::get_object_data_values()[ $condition['select7'] ] : false; // Object select.
							$eventtype  = ( isset( $condition['select8'] ) ) ? self::get_event_type_values()[ $condition['select8'] ] : false; // Event type select.

							$event_id = ( isset( $condition['input1'] ) ) ? $condition['input1'] : false;
							$date     = ( isset( $condition['input1'] ) ) ? $condition['input1'] : false;
							$time     = ( isset( $condition['input1'] ) ) ? $condition['input1'] : false;
							if ( $time ) {
								$time = \gmdate( 'H:i', \strtotime( $time ) );
							}
							$username          = ( isset( $condition['input1'] ) ) ? $condition['input1'] : false;
							$clientip          = ( isset( $condition['input1'] ) ) ? $condition['input1'] : false;
							$postid            = ( isset( $condition['input1'] ) ) ? $condition['input1'] : false;
							$siteid            = ( isset( $condition['input1'] ) ) ? $condition['input1'] : false;
							$custom_field_name = ( isset( $condition['input1'] ) ) ? $condition['input1'] : false;

							$mapped_fields = Notification_Helper::get_notifications_fields_name_mapping()[ $filter ];

							if ( empty( $notification_array ) ) {
								$notification_array = array(
									'condition' => $operator,
									'rules'     => array(
										array(
											'id'       => $mapped_fields['interpolate'],
											'field'    => $mapped_fields['interpolate'],
											'type'     => $mapped_fields['type'],
											'input'    => 'text',
											'operator' => $filter_operator,
											'value'    => $$filter,

											'condition_operator' => self::get_select_1_data()[ $condition['select1'] ],
										),
									),

								);
							} else {
								$notification_array['rules'][] = array(
									'condition' => $operator,
									'rules'     => array(
										array(
											'id'       => $mapped_fields['interpolate'],
											'field'    => $mapped_fields['interpolate'],
											'type'     => $mapped_fields['type'],
											'input'    => 'text',
											'operator' => $filter_operator,
											'value'    => $$filter,

											'condition_operator' => self::get_select_1_data()[ $condition['select1'] ],
										),
									),

								);
							}
							++$last_id;
						} elseif ( \is_array( $entry ) ) {
							$operator = null;
							$rules    = array();
							foreach ( $entry as $k => $item ) {
								$condition = $conditions[ $last_id ];
								// Handle PAGE ID AND CUSTOM POST ID deprecation.
								if ( 7 === $condition['select2'] || 8 === $condition['select2'] ) {
									$condition['select2'] = 6;
								}

								if ( null === $operator ) {
									$operator = self::get_select_1_data()[ $condition['select1'] ]; // AND or OR.
								}
								$filter          = self::notification_filter_mapper()[ $condition['select2'] ]; // Filter key.
								$filter_operator = self::get_select_3_data()[ $condition['select3'] ]; // Operater.

								$poststatus = isset( $condition['select4'] ) ? self::get_select_4_data()[ $condition['select4'] ] : false; // Post status select.
								$posttype   = ( isset( $condition['select5'] ) ) ? self::get_post_type_values()[ $condition['select5'] ] : false; // Post type select.
								$roles      = ( isset( $condition['select6'] ) ) ? self::get_roles_values()[ $condition['select6'] ] : false; // User roles select.
								$object     = ( isset( $condition['select7'] ) ) ? self::get_object_data_values()[ $condition['select7'] ] : false; // Object select.
								$eventtype  = ( isset( $condition['select8'] ) ) ? self::get_event_type_values()[ $condition['select8'] ] : false; // Event type select.

								$event_id = ( isset( $condition['input1'] ) ) ? $condition['input1'] : false;
								$date     = ( isset( $condition['input1'] ) ) ? $condition['input1'] : false;
								$time     = ( isset( $condition['input1'] ) ) ? $condition['input1'] : false;
								if ( $time ) {
									$time = \gmdate( 'H:i', \strtotime( $time ) );
								}
								$username          = ( isset( $condition['input1'] ) ) ? $condition['input1'] : false;
								$clientip          = ( isset( $condition['input1'] ) ) ? $condition['input1'] : false;
								$postid            = ( isset( $condition['input1'] ) ) ? $condition['input1'] : false;
								$siteid            = ( isset( $condition['input1'] ) ) ? $condition['input1'] : false;
								$custom_field_name = ( isset( $condition['input1'] ) ) ? $condition['input1'] : false;

								$mapped_fields = Notification_Helper::get_notifications_fields_name_mapping()[ $filter ];

								$rules[] = array(
									'id'                 => $mapped_fields['interpolate'],
									'field'              => $mapped_fields['interpolate'],
									'type'               => $mapped_fields['type'],
									'input'              => 'text',
									'operator'           => $filter_operator,
									'value'              => $$filter,

									'condition_operator' => self::get_select_1_data()[ $condition['select1'] ],
								);
								++$last_id;
							}

							if ( empty( $notification_array ) ) {
								$notification_array['rules'] = array();
							}

							$notification_array['rules'][] = array(
								'condition' => $operator,
								'rules'     => $rules,
							);
						}
					}
				}

				$notification_array['valid'] = true;

				$sql = '';
				foreach ( $notification_array['rules'] as $key => $rule ) {
					if ( isset( $rule['rules'] ) && ! empty( $rule['rules'] ) ) {
						$rules = $rule['rules'];
						if ( count( $rules ) > 1 ) {

							foreach ( $rules as $index => $sub_rule ) {
								$subsql = $sub_rule['field'] . ' ' . Query_Builder_Parser::get_operator_sql()[ $sub_rule['operator'] ]['operator'] . ' ' . Query_Builder_Parser::get_correct_value( $sub_rule['condition_operator'], $sub_rule, $sub_rule['value'] );
								if ( $index > 0 ) {
									$sql .= ' ' . $sub_rule['condition_operator'] . ' ' . $subsql;
								} elseif ( ! empty( $sql ) ) {
									$sql .= $sub_rule['condition_operator'] . ' ( ' . $subsql;
								} else {
									$sql = ' ( ' . $subsql;
								}
							}
							$sql .= ' ) ';
						} else {
							$sub_rule = $rules[0];
							$subsql   = $sub_rule['field'] . ' ' . Query_Builder_Parser::get_operator_sql()[ $sub_rule['operator'] ]['operator'] . ' ' . Query_Builder_Parser::get_correct_value( $sub_rule['condition_operator'], $sub_rule, $sub_rule['value'] );
							if ( ! empty( $sql ) ) {
								$sql .= $sub_rule['condition_operator'] . ' ( ' . $subsql . ' ) ';
							} else {
								$sql = ' ( ' . $subsql . ' ) ';
							}
						}
					} elseif ( ! empty( $rule ) ) {
						$sub_rule = $rule;
						$subsql   = $sub_rule['field'] . ' ' . Query_Builder_Parser::get_operator_sql()[ $sub_rule['operator'] ]['operator'] . ' ' . Query_Builder_Parser::get_correct_value( $sub_rule['condition_operator'], $sub_rule, $sub_rule['value'] );
						if ( ! empty( $sql ) ) {
							$sql .= $sub_rule['condition_operator'] . ' ( ' . $subsql . ' ) ';
						} else {
							$sql = ' ( ' . $subsql . ' ) ';
						}
					}
				}

				$report_options['notification_query'] = \json_encode( array( 'sql' => $sql ) );

				$report_options['notification_query_sql'] = ( ( isset( $sql ) ) ? ( \esc_sql( Notifications::obscure_query( $sql ) ) ) : '' );

				if ( ! empty( $report_options['notification_query_sql'] ) ) {
					$report_options['notification_query_sql'] = Notification_Helper::normalize_query( $report_options['notification_query_sql'] );
				}

				Custom_Notifications_Entity::save( $report_options );

				Settings_Helper::delete_option_value( (string) $notification->option_name );
			}
		}

		/**
		 * Return Select 1 Field Values.
		 *
		 * @return array
		 *
		 * @since 5.2.1
		 */
		private static function get_select_1_data() {
			return array(
				0 => 'AND',
				1 => 'OR',
			);
		}

		/**
		 * Return Select 2 Field Values.
		 *
		 * @return array
		 *
		 * @since 5.2.2
		 */
		private static function notification_filter_mapper() {

			return array(
				0  => 'event_id',
				1  => 'date',
				2  => 'time',
				3  => 'username',
				4  => 'roles',
				5  => 'clientip',
				6  => 'postid',
				9  => 'siteid',
				10 => 'posttype',
				11 => 'poststatus',
				12 => 'object',
				13 => 'eventtype',
				14 => 'custom_field_name',
			);
		}

		/**
		 * Return Select 3 Field Values.
		 *
		 * @return array
		 *
		 * @since 5.2.1
		 */
		private static function get_select_3_data() {

			return array(
				0 => 'equal',
				1 => 'contains',
				2 => 'greater',
				3 => 'less',
				4 => 'not_equal',
			);
		}

		/**
		 * Get Select4/Post Status data.
		 *
		 * @since 5.2.1
		 */
		private static function get_select_4_data() {
			return array(
				0 => 'draft',
				1 => 'future',
				2 => 'pending',
				3 => 'private',
				4 => 'publish',
			);
		}

		/**
		 * Collects and return the roles from WP
		 *
		 * @return array
		 *
		 * @since 5.2.2
		 */
		private static function get_roles_values() {
			$options = WP_Helper::get_roles_wp();

			$options = \array_values( \array_flip( $options ) );

			return $options;
		}

		/**
		 * Collects and returns post type values
		 *
		 * @return array
		 *
		 * @since 5.2.2
		 */
		private static function get_post_type_values() {
			$options = WP_Helper::get_post_types();

			$options = \array_values( \array_flip( $options ) );

			return $options;
		}

		/**
		 * Collects and returns object values
		 *
		 * @return array
		 *
		 * @since 5.2.2
		 */
		private static function get_object_data_values() {
			$options = Alert_Manager::get_event_objects_data();

			$options = \array_values( \array_flip( $options ) );

			return $options;
		}

		/**
		 * Collects and returns event types
		 *
		 * @return array
		 *
		 * @since 5.2.2
		 */
		private static function get_event_type_values() {
			$options = Alert_Manager::get_event_type_data();

			unset( $options['failed'] );

			$options = \array_values( \array_flip( $options ) );

			return $options;
		}

		/**
		 * Receives raw string with emails from old version and converts it to comma separated emails
		 *
		 * @param string $email - The raw emails / users string.
		 *
		 * @return string
		 *
		 * @since 5.3.0
		 */
		private static function generate_email_string( string $email ): string {
			$users = explode( ',', $email );

			$users = array_map( 'trim', $users );

			$emails = array();

			foreach ( $users as $value ) {
				$value = \htmlspecialchars( \stripslashes( trim( $value ) ) );
				// Check if e-mail address is well-formed.
				if ( ! \filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
					$user = \get_user_by( 'login', $value );
					if ( ! empty( $user ) ) {
						$emails[] = $user->user_email;
					}
				} else {
					$emails[] = $value;
				}
			}

			return \implode( ',', $emails );
		}
	}
}
