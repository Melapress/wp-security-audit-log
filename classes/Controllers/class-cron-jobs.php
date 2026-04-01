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

use WSAL\Helpers\WP_Helper;
use WSAL\Views\Notifications;
use WSAL\MainWP\MainWP_Helper;
use WSAL\Helpers\Settings_Helper;
use WSAL\Entities\Occurrences_Entity;

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
			'wsal_summary_daily_report'       => array(
				'time'     => 'daily',
				'hook'     => array( __CLASS__, 'generate_daily_summary_reports' ),
				'args'     => array(),
				'next_run' => '03:00 tomorrow',
			),
			'wsal_summary_weekly_report'      => array(
				'time'     => 'weekly',
				'hook'     => array( __CLASS__, 'generate_weekly_summary_reports' ),
				'args'     => array(),
				'next_run' => '03:00 next monday',
			),
		);

		/**
		 * The name of the option where plugin stores the cron jobs names (related to the plugin itself).
		 *
		 * @since 5.0.0
		 */
		public const CRON_JOBS_SETTINGS_NAME = 'cron_jobs_options';

		/**
		 * Class cache for initialization
		 *
		 * @var boolean
		 *
		 * @since 5.3.2
		 */
		private static $initialized = false;

		/**
		 * Inits the class and its hooks.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function init() {
			// Add custom schedules for WSAL early otherwise they won't work.
			\add_filter( 'cron_schedules', array( __CLASS__, 'recurring_schedules' ), PHP_INT_MAX );
			\add_filter( 'wsal_cron_hooks', array( __CLASS__, 'settings_hooks' ) );
			\add_filter( 'after_setup_theme', array( __CLASS__, 'initialize_hooks' ), 30000 );
			\add_action( 'update_option_start_of_week', array( __CLASS__, 'reschedule_weekly_crons' ) );

			if ( Settings_Helper::get_boolean_option_value( 'pruning-date-e', false ) ) {
				\add_action( 'wsal_cleanup', array( Occurrences_Entity::class, 'prune_records' ) );
			}
		}

		/**
		 * Adds cron jobs stored in the globals settings (options table).
		 *
		 * @param array $crons - The list of cron jobs to add.
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
		 * Hook method for generating the reports.
		 *
		 * @return void
		 *
		 * @since 5.3.0
		 */
		public static function generate_daily_summary_reports() {
			Notifications::send_daily_summary_cron();
		}

		/**
		 * Hook method for generating the weekly reports.
		 *
		 * @return void
		 *
		 * @since 5.3.0
		 */
		public static function generate_weekly_summary_reports() {
			Notifications::send_weekly_summary_cron();
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

		/**
		 * Hook method for clearing the plugins data.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function cleanup_hook() {
			\do_action( 'wsal_cleanup', array() );
		}

		/**
		 * Extend WP cron time intervals for scheduling.
		 *
		 * @param array $schedules - Array of schedules.
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function recurring_schedules( $schedules ) {
			global $wp_actions;

			$remove_it = false;

			// if ( ! isset( $wp_actions['after_setup_theme'] ) ) {
			// $remove_it                       = true;
			// $wp_actions['after_setup_theme'] = true; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			// }

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

			if ( $remove_it ) {
				unset( $wp_actions['after_setup_theme'] );
			}

			return $schedules;
		}

		/**
		 * Adds a cron job to the stored ones.
		 *
		 * @param array $cron_job - Array with the cron job information. Every cron job information includes 'time', 'hook', 'args', if it is a recurring one - and 'next_run'.
		 *
		 * Example:
		 * 'hook_name'   => array(
		 *      'time'     => 'monthly',
		 *      'hook'     => array( __CLASS_TO_CALL__, 'method_to_call' ),
		 *      'args'     => array(),
		 *      'next_run' => '00:00 first day of next month',
		 *  )
		 * .
		 *
		 * @return void
		 *
		 * @throws \InvalidArgumentException When cron job information passed not contains required keys.
		 *
		 * @since 5.0.0
		 */
		public static function store_cron_option( array $cron_job ) {
			if ( empty( $cron_job ) || 1 < count( $cron_job ) ) {
				throw new \InvalidArgumentException( __( 'Only one cron at a time', 'wp-security-audit-log' ) );
			}

			$keys = array(
				'time',
				'hook',
				'args',
			);
			if ( count( $keys ) === count(
				array_filter(
					array_keys( \reset( $cron_job ) ),
					function ( $key ) use ( $keys ) {
						return in_array( $key, $keys, true );
					}
				)
			) ) {
				$available_cron_jobs = Settings_Helper::get_option_value( self::CRON_JOBS_SETTINGS_NAME, array() );

				$available_cron_jobs = array_merge( $available_cron_jobs, $cron_job );

				Settings_Helper::set_option_value( self::CRON_JOBS_SETTINGS_NAME, $available_cron_jobs );
			} else {
				throw new \InvalidArgumentException( __( 'Invalid cron job format', 'wp-security-audit-log' ) );
			}
		}

		/**
		 * Unset cron job from global settings.
		 *
		 * @param string $cron_name - The name of the cron job to remove.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function remove_cron_option( string $cron_name ) {
			$available_cron_jobs = Settings_Helper::get_option_value( self::CRON_JOBS_SETTINGS_NAME, array() );

			if ( isset( $available_cron_jobs[ $cron_name ] ) ) {
				\wp_clear_scheduled_hook( $cron_name, $available_cron_jobs[ $cron_name ]['args'] );

				unset( $available_cron_jobs[ $cron_name ] );

				Settings_Helper::set_option_value( self::CRON_JOBS_SETTINGS_NAME, $available_cron_jobs );
			}
		}

		/**
		 * Removes event from the cron by given name.
		 *
		 * @param string $event_name -The name of the event.
		 * @param array  $args       - Arguments passed to the cron event.
		 *
		 * @return void
		 *
		 * @since 4.4.2.1
		 */
		public static function un_schedule_event( string $event_name, array $args = array() ) {
			$schedule_time = \wp_next_scheduled( $event_name, $args );
			if ( $schedule_time ) {
				\wp_unschedule_event( $schedule_time, $event_name, $args );
			}
		}

		/**
		 * Returns the next occurrence of the WP start-of-week day as a strtotime-compatible string.
		 *
		 * @param string $time_prefix - The time prefix to use (e.g. '00:00' or '03:00').
		 *
		 * @return string
		 *
		 * @since 5.6.2
		 */
		private static function get_next_week_start( string $time_prefix = '00:00' ): string {
			$days = array(
				0 => 'sunday',
				1 => 'monday',
				2 => 'tuesday',
				3 => 'wednesday',
				4 => 'thursday',
				5 => 'friday',
				6 => 'saturday',
			);

			$start_of_week = (int) \get_option( 'start_of_week', 1 );
			$day_name      = $days[ $start_of_week ] ?? 'monday';

			return $time_prefix . ' next ' . $day_name;
		}

		/**
		 * Unschedules weekly cron jobs so they get recreated with the new start-of-week day.
		 *
		 * @return void
		 *
		 * @since 5.6.2
		 */
		public static function reschedule_weekly_crons() {
			self::un_schedule_event( 'wsal_periodic_reports_weekly' );
			self::un_schedule_event( 'wsal_summary_weekly_report' );
		}

		/**
		 * Initializes the plugin cron jobs.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 * @since 5.6.2 Weekly cron jobs respect the WP start_of_week setting.
		 */
		public static function initialize_hooks() {
			$hooks_array = self::CRON_JOBS_NAMES;

			$hooks_array['wsal_periodic_reports_weekly']['next_run'] = self::get_next_week_start( '00:00' );
			$hooks_array['wsal_summary_weekly_report']['next_run']   = self::get_next_week_start( '03:00' );

			if ( WP_Helper::is_multisite() || 'free' === \WpSecurityAuditLog::get_plugin_version() ) {
				/*
				 * Multisite crons are running for every single sub-site instance. This is completely wrong as it leads to multiple reports being generated / fired. For that reason only the main site is allowed to run them, and that is the reason for that code existence.
				 *
				 * Also Free version of the plugin does not need these to be present
				 */
				if ( ! \is_main_site() || 'free' === \WpSecurityAuditLog::get_plugin_version() ) {
					unset(
						$hooks_array['wsal_generate_reports_cron'],
						$hooks_array['wsal_periodic_reports_daily'],
						$hooks_array['wsal_periodic_reports_weekly'],
						$hooks_array['wsal_periodic_reports_monthly'],
						$hooks_array['wsal_periodic_reports_quarterly']
					);
				}

				if ( 'free' === \WpSecurityAuditLog::get_plugin_version() ) {
					unset( $hooks_array['wsal_reports_pruning_cron'] );
				}
			}

			if ( WP_Helper::is_multisite() ) {
				if ( ! \is_main_site() || 'free' !== \WpSecurityAuditLog::get_plugin_version() ) {
					$per_site_report = ( isset( Notifications::get_global_notifications_setting()['notification_summary_multisite_individual_site'] ) ? Notifications::get_global_notifications_setting()['notification_summary_multisite_individual_site'] : true );

					if ( ! $per_site_report ) {
						unset(
							$hooks_array['wsal_summary_daily_report'],
							$hooks_array['wsal_summary_weekly_report']
						);
					}
				}

				if ( ! \is_main_site() ) {
					unset( $hooks_array['wsal_cleanup_hook'] );
				}
			}

			$built_notifications = Settings_Helper::get_option_value( Notifications::BUILT_IN_NOTIFICATIONS_SETTINGS_NAME, array() );

			if ( ! isset( $built_notifications['daily_summary_notification'] ) || ! $built_notifications['daily_summary_notification'] ) {
				unset( $hooks_array['wsal_summary_daily_report'] );

				self::un_schedule_event( 'wsal_summary_daily_report' );
			}

			if ( ! isset( $built_notifications['weekly_summary_notification'] ) || ! $built_notifications['weekly_summary_notification'] ) {
				unset( $hooks_array['wsal_summary_weekly_report'] );

				self::un_schedule_event( 'wsal_summary_weekly_report' );
			}

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

		/**
		 * Legacy cron hook names that may still exist on older installs.
		 *
		 * @since 5.6.2
		 */
		public const LEGACY_CRON_HOOKS = array(
			'wsal_cleanup',
			'wsal_delete_logins',
			'wsal_daily_summary_report',
			'wsal_auto_destroy_sessions',
		);

		/**
		 * Extension cron hooks registered via the wsal_cron_hooks filter.
		 *
		 * These cannot be discovered at deactivation time because the filter
		 * callbacks may not be loaded yet, so they are listed here explicitly.
		 *
		 * @since 5.6.2
		 */
		public const EXTENSION_CRON_HOOKS = array(
			'wsal_run_archiving',
		);

		/**
		 * Unschedules all WSAL cron jobs. Called on plugin deactivation.
		 *
		 * Clears all hooks from the CRON_JOBS_NAMES constant, any dynamically
		 * registered hooks stored in the cron_jobs_options setting, extension
		 * hooks registered via filter, and legacy hooks that may still exist
		 * on older installs.
		 *
		 * @return void
		 *
		 * @since 5.6.2
		 */
		public static function single_site_unschedule_all_cron_jobs() {
			$hooks = array_keys( self::CRON_JOBS_NAMES );

			$dynamic_hooks = Settings_Helper::get_option_value( self::CRON_JOBS_SETTINGS_NAME, array() );

			if ( ! empty( $dynamic_hooks ) ) {
				$hooks = array_merge( $hooks, array_keys( $dynamic_hooks ) );
			}

			$hooks = array_merge( $hooks, self::LEGACY_CRON_HOOKS );
			$hooks = array_merge( $hooks, self::EXTENSION_CRON_HOOKS );
			$hooks = array_merge( $hooks, array_keys( MainWP_Helper::CRON_JOBS ) );

			foreach ( $hooks as $hook_name ) {
				\wp_clear_scheduled_hook( $hook_name );
			}
		}

		/**
		 * Unschedules all WSAL cron jobs across all sites in a multisite network. Called on plugin deactivation in a multisite context.
		 *
		 * @return void
		 *
		 * @since 5.6.2
		 */
		public static function multisite_unschedule_all_cron_jobs() {
			// By default get_sites only returns 100 sites, so we need to set 'number' to 0 to get all of them.
			$sites = \get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);

			foreach ( $sites as $site_id ) {
				\switch_to_blog( $site_id );
				self::single_site_unschedule_all_cron_jobs();
				\restore_current_blog();
			}
		}

		/**
		 * Unschedules all WSAL cron jobs. Routes to single-site or multisite cleanup based on context.
		 *
		 * @param bool $network_deactivating - Whether the plugin is being deactivated network-wide.
		 *
		 * @return void
		 *
		 * @since 5.6.2
		 */
		public static function unschedule_all_cron_jobs( $network_deactivating = false ) {
			if ( $network_deactivating && WP_Helper::is_multisite() ) {
				self::multisite_unschedule_all_cron_jobs();
			} else {
				self::single_site_unschedule_all_cron_jobs();
			}
		}
	}
}
