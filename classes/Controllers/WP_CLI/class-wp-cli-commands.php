<?php
/**
 * Controller: wp-cli.
 *
 * WP-CLI commands.
 *
 * @since     4.6
 *
 * @package   wsal
 * @subpackage controllers
 */

declare(strict_types=1);

namespace WSAL\Controllers\WP_CLI;

use WSAL\Views\Notifications;
use WSAL\Helpers\Settings_Helper;
use WSAL\Controllers\Alert_Manager;
use WSAL\Helpers\Plugin_Settings_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( '\WP_CLI_Command' ) ) {
	if ( ! class_exists( '\WSAL\Controllers\WP_CLI\WP_CLI_Commands' ) ) {
		/**
		 * Responsible for execution of the wp-cli commands passed.
		 *
		 * @since 4.6.0
		 */
		class WP_CLI_Commands extends \WP_CLI_Command {
			/**
			 * Sets wizard as completed
			 *
			 * @param array $args - The arguments passed.
			 * @param array $assoc_args - The associative array of the arguments passed.
			 *
			 * @return void
			 *
			 * @since 4.6.0
			 *
			 * @subcommand remove_wizard
			 */
			public function remove_wizard( $args, $assoc_args ) {
				Settings_Helper::set_boolean_option_value( 'setup-modal-dismissed', true, true );
				\WP_CLI::log(
					__( 'The WSAL wizard is marked as dismissed', 'wp-security-audit-log' )
				);
			}

			/**
			 * Removes daily email notification.
			 *
			 * @param array $args - The arguments passed.
			 * @param array $assoc_args - The associative array of the arguments passed.
			 *
			 * @return void
			 *
			 * @since 4.6.0
			 *
			 * @subcommand remove_daily_notification
			 */
			public function remove_daily_notification( $args, $assoc_args ) {

				$built_notifications = Settings_Helper::get_option_value( Notifications::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME, array() );

				$built_notifications['daily_summary_notification'] = false;

				Settings_Helper::set_option_value( Notifications::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME, $built_notifications );

				Alert_Manager::trigger_event( 6310, array( 'EventType' => 'disabled' ) );
				\WP_CLI::log(
					__( 'The WSAL daily notification is removed', 'wp-security-audit-log' )
				);
			}

			/**
			 * Removes weekly email notification.
			 *
			 * @param array $args - The arguments passed.
			 * @param array $assoc_args - The associative array of the arguments passed.
			 *
			 * @return void
			 *
			 * @since 5.3.0
			 *
			 * @subcommand remove_weekly_notification
			 */
			public function remove_weekly_notification( $args, $assoc_args ) {

				$built_notifications = Settings_Helper::get_option_value( Notifications::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME, array() );

				$built_notifications['weekly_summary_notification'] = false;

				Settings_Helper::set_option_value( Notifications::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME, $built_notifications );

				Alert_Manager::trigger_event( 6319, array( 'EventType' => 'disabled' ) );
				\WP_CLI::log(
					__( 'The WSAL daily notification is removed', 'wp-security-audit-log' )
				);
			}

			/**
			 * Enables / Disables alert by its Id.
			 *
			 * @param array $args - The arguments passed.
			 * @param array $assoc_args - The associative array of the arguments passed.
			 *
			 * @return void
			 *
			 * @since 5.2.2
			 *
			 * @subcommand disable_enable_alert
			 */
			public function disable_enable_alert( $args, $assoc_args ) {

				Alert_Manager::disable_enable_alert( $args );
			}

			/**
			 * Sets Hide/Show plugin option in the list of installed plugins.
			 *
			 * ## OPTIONS
			 *
			 * [<1|true|0|false>]
			 * : The boolean (true|false) or integer (1|0). Everything different than positive or negative numbers or string 'true' will be treated as false. 0 - means false.
			 *
			 * @param array $args - The arguments passed.
			 * @param array $assoc_args - The associative array of the arguments passed.
			 *
			 * @return void
			 *
			 * @since 5.2.2
			 *
			 * @subcommand set_hide_plugin
			 */
			public function set_hide_plugin( $args, $assoc_args ) {

				$val = reset( $args );

				if ( is_numeric( $val ) ) {
					$val = (bool) $val;
				} elseif ( is_string( $val ) ) {
					if ( 'true' === $val ) {
						$val = true;
					} else {
						$val = false;
					}
				}

				Settings_Helper::set_boolean_option_value( 'hide-plugin', $val );
			}

			/**
			 * Sets Hide/Show plugin option in the list of installed plugins.
			 *
			 * ## OPTIONS
			 *
			 * [--enabled=<bool>]
			 * : The boolean (true|false). Default is true.
			 *
			 * [--text=<text>]
			 * : Text to show. HTML is enabled.
			 * 
			 * @param array $args - The arguments passed.
			 * @param array $assoc_args - The associative array of the arguments passed.
			 *
			 * @return void
			 *
			 * @since 5.2.2
			 *
			 * @subcommand login_page_notification
			 */
			public function login_page_notification( $args, $assoc_args ) {
				$notification_enabled = \WP_CLI\Utils\get_flag_value( $assoc_args, 'enabled', 'true' );
				$notification_enabled = trim( $notification_enabled );
				if ( 'false' === $notification_enabled ) {
					$notification_enabled = false;
				} else {
					$notification_enabled = true;
				}

				$notification_text = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'text', '' );

				Plugin_Settings_Helper::set_login_page_notification( $notification_enabled );

				Plugin_Settings_Helper::set_login_page_notification_text( $notification_text );
			}

			/**
			 * Sets the pruning date
			 *
			 * ## OPTIONS
			 *
			 * [--enabled=<bool>]
			 * : The boolean (true|false). Default is true.
			 *
			 * [--pruning-value=<size>]
			 * : The time value. Integer numbers only. Default is 6.
			 *
			 * [--pruning-unit=<days|months|years>]
			 * : The time unit. Could be either of the following: days, months, years. Default is months.
			 *
			 * @param array $args - The arguments passed.
			 * @param array $assoc_args - The associative array of the arguments passed.
			 *
			 * @return void
			 *
			 * @since 4.6.0
			 *
			 * @subcommand set_retention
			 */
			public function set_retention( $args, $assoc_args ) {
				$units = array( 'days', 'months', 'years' );

				$pruning_enabled = \WP_CLI\Utils\get_flag_value( $assoc_args, 'enabled', 'true' );
				$pruning_enabled = trim( $pruning_enabled );
				if ( 'false' === $pruning_enabled ) {
					$pruning_enabled = false;
				} else {
					$pruning_enabled = true;
				}

				$pruning_date = absint( \WP_CLI\Utils\get_flag_value( $assoc_args, 'pruning-value', 6 ) );

				$pruning_unit = \WP_CLI\Utils\get_flag_value( $assoc_args, 'pruning-unit', 'months' );

				if ( ! \in_array( $pruning_unit, $units, true ) ) {
					$pruning_unit = 'months';
				}

				$pruning_date .= ' ' . $pruning_unit;

				Settings_Helper::set_pruning_date_settings( $pruning_enabled, $pruning_date, $pruning_unit );

				\WP_CLI::log(
					__( 'The WSAL pruning date is set', 'wp-security-audit-log' )
				);
			}
		}
	}
}
