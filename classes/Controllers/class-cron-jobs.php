<?php
/**
 * Controller: Cron Jobs.
 *
 * @since 5.1.0
 *
 * @package   wsal
 * @subpackage controllers
 */

declare(strict_types=1);

namespace WSAL\Controllers;

use WSAL\Entities\Occurrences_Entity;
use WSAL\Helpers\Settings_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Controllers\Cron_Jobs' ) ) {
	/**
	 * Provides cron jobs functionality for the plugin.
	 *
	 * @since 5.0.0
	 */
	class Cron_Jobs {

		public const CRON_JOBS_NAMES = array(
			'wsal_generate_reports_cron'      => array(
				'time' => 'tenminutes',
				'hook' => array( __CLASS__, 'generate_reports' ),
				'args' => array(),
			),
			'wsal_periodic_reports_daily'     => array(
				'time'     => 'daily',
				'hook'     => array( __CLASS__, 'generate_daily_reports' ),
				'args'     => array(),
				'next_run' => '00:00 tomorrow',
			),
			'wsal_periodic_reports_weekly'    => array(
				'time'     => 'weekly',
				'hook'     => array( __CLASS__, 'generate_weekly_reports' ),
				'args'     => array(),
				'next_run' => '00:00 next monday',
			),
			'wsal_periodic_reports_monthly'   => array(
				'time'     => 'monthly',
				'hook'     => array( __CLASS__, 'generate_monthly_reports' ),
				'args'     => array(),
				'next_run' => '00:00 first day of next month',
			),
			'wsal_periodic_reports_quarterly' => array(
				'time'     => 'quarterly',
				'hook'     => array( __CLASS__, 'generate_quarterly_reports' ),
				'args'     => array(),
				'next_run' => '00:00 first day of +4 months',
			),
			'wsal_reports_pruning_cron'       => array(
				'time' => 'daily',
				'hook' => array( __CLASS__, 'clear_reports' ),
				'args' => array(),
			),
			'wsal_cleanup_hook'               => array(
				'time' => 'hourly',
				'hook' => array( __CLASS__, 'cleanup_hook' ),
				'args' => array(),
			),
		);

		public const CRON_JOBS_SETTINGS_NAME = 'cron_jobs_options';

		/**
		 * Inits the class and its hooks.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function init() {
			// Add custom schedules for WSAL early otherwise they won't work.
			\add_filter( 'cron_schedules', array( __CLASS__, 'recurring_schedules' ) );
			\add_filter( 'wsal_cron_hooks', array( __CLASS__, 'settings_hooks' ) );

			if ( \wp_next_scheduled( 'wsal_cleanup', array() ) ) {
				wp_clear_scheduled_hook( 'wsal_cleanup' );
			}

			if ( Settings_Helper::get_boolean_option_value( 'pruning-date-e', false ) ) {
				\add_action( 'wsal_cleanup', array( Occurrences_Entity::class, 'prune_records' ) );
			}

			self::initialize_hooks();
		}

		/**
		 * Adds cron jobs stored in the globals settings (options table).
		 *
		 * @param array $crons - The list of cron jobs to add.
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function settings_hooks( array $crons ): array {
			$available_cron_jobs = Settings_Helper::get_option_value( self::CRON_JOBS_SETTINGS_NAME, array() );

			if ( ! empty( $available_cron_jobs ) ) {
				$crons = array_merge( $crons, $available_cron_jobs );
			}

			return $crons;
		}

		/**
		 * Hook method for generating the reports.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function generate_reports() {
			\do_action( 'wsal_generate_reports', array() );
		}

		/**
		 * Hook method for generating the reports.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function generate_daily_reports() {
			\do_action( 'wsal_generate_reports_daily', array() );
		}

		/**
		 * Hook method for generating the reports.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function generate_weekly_reports() {
			\do_action( 'wsal_generate_reports_weekly', array() );
		}

		/**
		 * Hook method for generating the reports.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function generate_monthly_reports() {
			\do_action( 'wsal_generate_reports_monthly', array() );
		}

		/**
		 * Hook method for generating the reports.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function generate_quarterly_reports() {
			\do_action( 'wsal_generate_reports_quarterly', array() );
		}

		/**
		 * Hook method for clearing the reports.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function clear_reports() {
			\do_action( 'wsal_clear_reports', array() );
		}

		public static function cleanup_hook() {
			\do_action( 'wsal_cleanup', array() );
		}

		/**
		 * Extend WP cron time intervals for scheduling.
		 *
		 * @param  array $schedules - Array of schedules.
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function recurring_schedules( $schedules ) {
			$schedules['sixhours']         = array(
				'interval' => 21600,
				'display'  => __( 'Every 6 hours', 'wp-security-audit-log' ),
			);
			$schedules['fortyfiveminutes'] = array(
				'interval' => 2700,
				'display'  => __( 'Every 45 minutes', 'wp-security-audit-log' ),
			);
			$schedules['thirtyminutes']    = array(
				'interval' => 1800,
				'display'  => __( 'Every 30 minutes', 'wp-security-audit-log' ),
			);
			$schedules['fifteenminutes']   = array(
				'interval' => 900,
				'display'  => __( 'Every 15 minutes', 'wp-security-audit-log' ),
			);
			$schedules['tenminutes']       = array(
				'interval' => 600,
				'display'  => __( 'Every 10 minutes', 'wp-security-audit-log' ),
			);
			$schedules['fiveminutes']      = array(
				'interval' => 300,
				'display'  => __( 'Every 5 minutes', 'wp-security-audit-log' ),
			);
			$schedules['oneminute']        = array(
				'interval' => 60,
				'display'  => __( 'Every minute', 'wp-security-audit-log' ),
			);
			$schedules['monthly']          = array(
				'interval' => 2635200,
				'display'  => __( 'Once monthly', 'wp-security-audit-log' ),
			);
			$schedules['quarterly']        = array(
				'interval' => 2635200 * 4,
				'display'  => __( 'Once quarterly', 'wp-security-audit-log' ),
			);
			return $schedules;
		}

		/**
		 * Adds a cron job to the stored ones.
		 *
		 * @param array $cron_job - Array with the cron job information.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function store_cron_option( array $cron_job ) {

			$available_cron_jobs = Settings_Helper::get_option_value( self::CRON_JOBS_SETTINGS_NAME, array() );

			$available_cron_jobs = array_merge( $available_cron_jobs, $cron_job );

			Settings_Helper::set_option_value( self::CRON_JOBS_SETTINGS_NAME, $available_cron_jobs );
		}

		/**
		 * Unsets cron job from global settings
		 *
		 * @param string $cron_name - The name of the cron job to remove.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function remove_cron_option( string $cron_name ) {
			$available_cron_jobs = Settings_Helper::get_option_value( self::CRON_JOBS_SETTINGS_NAME, array() );

			unset( $available_cron_jobs[ $cron_name ] );

			\wp_clear_scheduled_hook( $cron_name );

			Settings_Helper::set_option_value( self::CRON_JOBS_SETTINGS_NAME, $available_cron_jobs );
		}

		/**
		 * Initializes the plugin cron jobs.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		private static function initialize_hooks() {
			$hooks_array = self::CRON_JOBS_NAMES;

			/**
			 * Gives an option to add hooks which must be enabled.
			 *
			 * @var array - The current hooks.
			 *
			 * @since 5.0.0
			 */
			$hooks_array = \apply_filters( 'wsal_cron_hooks', $hooks_array );

			foreach ( $hooks_array as $name => $parameters ) {
				if ( ! \wp_next_scheduled( $name, ( isset( $parameters['args'] ) ) ? $parameters['args'] : array() ) ) {

					$time = time();

					if ( isset( $parameters['next_run'] ) ) {
						$ve = get_option( 'gmt_offset' ) > 0 ? ' -' : ' +';

						$time = strtotime( $parameters['next_run'] . $ve . get_option( 'gmt_offset' ) . ' HOURS' );
					}

					\wp_schedule_event( $time, ( isset( $parameters['time'] ) ) ? $parameters['time'] : 'daily', $name );
				}

				\add_action( $name, $parameters['hook'] );
			}
		}
	}
}
