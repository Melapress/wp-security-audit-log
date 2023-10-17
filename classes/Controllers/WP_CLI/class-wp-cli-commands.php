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

use WSAL\Helpers\Settings_Helper;
use WSAL\Controllers\Alert_Manager;

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
				Settings_Helper::set_boolean_option_value( 'disable-daily-summary', true );
				Alert_Manager::trigger_event( 6310, array( 'EventType' => 'disabled' ) );
				\WP_CLI::log(
					__( 'The WSAL daily notification is removed', 'wp-security-audit-log' )
				);
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
