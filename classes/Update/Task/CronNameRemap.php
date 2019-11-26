<?php
/**
 * Task to handle remapping cron names that were renamed in later versions.
 *
 * NOTE: This in intended to be run as part of the plugin update routine when
 * it detects a version change.
 *
 * @package Wsal
 * @since 3.5.1
 */

namespace WSAL\Update\Task;

/**
 * Remaps remaps old cron names to new ones and schedules them at the same
 * time as the original was scheduled.
 */
class CronNameRemap {

	/**
	 * Holds the main plugin instance to work on.
	 *
	 * @var \WpSecurityAuditLog
	 */
	private $wsal;

	/**
	 * Setups up the class properties.
	 *
	 * @method __construct
	 * @since  3.5.1
	 * @param  \WpSecurityAuditLog $wsal An instance of the main plugin.
	 */
	public function __construct( $wsal ) {
		$this->wsal = $wsal;
	}
	/**
	 * Loop through an array of cron tasks and remap them if the name changed.
	 *
	 * @method run
	 * @since  3.5.1
	 */
	public function run() {
		// Get crons to be remapped.
		if ( null === $this->wsal || ! is_a( $this->wsal, 'WpSecurityAuditLog' ) ) {
			$crons = $this->get_crons_to_remap();

			// Loop through list of crons and check if scheduled.
			foreach ( $crons as $cron ) {
				$args = ( isset( $cron['args'] ) ) ? $cron['args'] : array();
				$time = wp_next_scheduled( $cron['name_old'], $args );
				if ( $time ) {
					// Cron with old name is scheduled - unschedule.
					wp_unschedule_event( $time, $cron['name_old'], $args );
					$rescheduled = wp_next_scheduled( $cron['name_new'], $args );
					if ( ! $rescheduled ) {
						// New cron is not scheduled already - schedule it.
						wp_schedule_event( $time, $cron['frequency'], $cron['name_new'], $args );
					}
				}
			}
		}
	}

	/**
	 * Gets an array of cron tasks and their remapped names, frequencies, args.
	 *
	 * @method get_crons_to_remap
	 * @since  3.5.1
	 * @return array
	 */
	private function get_crons_to_remap() {
		// Get an array of crons that have args.
		$complex_crons       = $this->get_crons_with_args_for_remap();
		$archiving_frequency = $this->wsal->GetGlobalOption( 'archiving-run-every', 'hourly' );
		// This is a list of _mostly_ static crons that are to be remapped.
		$simple_crons = array(
			array(
				'name_old'  => 'summary_email_reports',
				'name_new'  => 'wsal_summary_email_reports',
				'frequency' => 'hourly',
			),
			array(
				'name_old'  => 'log_files_pruning',
				'name_new'  => 'wsal_log_files_pruning',
				'frequency' => 'daily',
			),
			array(
				'name_old'  => 'reports_pruning',
				'name_new'  => 'wsal_reports_pruning',
				'frequency' => 'daily',
			),
			array(
				'name_old'  => 'destroy_expired',
				'name_new'  => 'wsal_destroy_expired',
				'frequency' => 'hourly',
			),
			array(
				'name_old'  => 'run_archiving',
				'name_new'  => 'wsal_run_archiving',
				'frequency' => strtolower( $archiving_frequency ), // this is not static data.
			),
		);

		// Return an array of all the crons that are flagged for unscheduling.
		return array_merge( $simple_crons, $complex_crons );
	}

	/**
	 * Get a list of crons that have args to maybe reschedule.
	 *
	 * @method get_crons_with_args_for_remap
	 * @since  3.5.1
	 * @param  array $crons an array of existing crons we might add to.
	 * @return array
	 */
	private function get_crons_with_args_for_remap( $crons = array() ) {
		/*
		 * Get the scheduled mirrors and their args.
		 */
		$mirrors = $this->wsal->GetNotificationsSetting( 'mirror-' );
		if ( ! empty( $mirrors ) && is_array( $mirrors ) ) {
			foreach ( $mirrors as $mirror ) {
				$mirror_details = maybe_unserialize( $mirror->option_value );
				// Check if mirror details are valid.
				if ( ! empty( $mirror_details ) && $mirror_details instanceof \stdClass ) {
					// Got a mirror to check.
					if ( isset( $mirror_details->state ) && true === $mirror_details->state ) {
						// Mirror is enabled so add it to the $crons array.
						$crons[] = array(
							'name_old'  => 'run_mirroring',
							'name_new'  => 'wsal_run_mirroring',
							'frequency' => $mirror_details->frequency,
							'args'      => array( $mirror_details->name ),
						);
					}
				}
			}
		}
		return $crons;
	}

}
