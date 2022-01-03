<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wrapper class for report filter arguments. Used mainly to prevent passing arbitrary arrays info data loading functions.
 *
 * @package wsal
 * @subpackage reports
 *
 * @author Martin Krcho <martin@wpwhitesecurity.com>
 * @since 4.3.2
 */
class WSAL_ReportArgs {

	/**
	 * An array of user IDs to include in report.
	 *
	 * @var int[]
	 */
	public $user__in;

	/**
	 * An array of user IDs to exclude from the report.
	 *
	 * @var int[]
	 */
	public $user__not_in;

	/**
	 * An array of site IDs to include in report.
	 *
	 * @var int[]
	 */
	public $site__in;

	/**
	 * An array of site IDs to exclude from the report.
	 *
	 * @var int[]
	 */
	public $site__not_in;

	/**
	 * An array of user role names to include in the report.
	 *
	 * Note: This holds role names, not the translatable display names.
	 *
	 * @var string[]
	 */
	public $role__in;

	/**
	 * An array of user role names to exclude from the report.
	 *
	 * Note: This holds role names, not the translatable display names.
	 *
	 * @var string[]
	 */
	public $role__not_in;

	/**
	 * An array of IP addresses to include in the report.
	 *
	 * @var string[]
	 */
	public $ip__in;

	/**
	 * An array of user role names to exclude from the report.
	 *
	 * @var string[]
	 */
	public $ip__not_in;

	/**
	 * An array of objects include in the report.
	 *
	 * @var string[]
	 */
	public $object__in;

	/**
	 * An array of objects to exclude from the report.
	 *
	 * @var string[]
	 */
	public $object__not_in;

	/**
	 * An array of event types include in the report.
	 *
	 * @var string[]
	 */
	public $type__in;

	/**
	 * An array of event types to exclude from the report.
	 *
	 * @var string[]
	 */
	public $type__not_in;

	/**
	 * Start date in format YYYY-MM-DD.
	 *
	 * @var string
	 */
	public $start_date;

	/**
	 * End date in format YYYY-MM-DD.
	 *
	 * @var string
	 */
	public $end_date;

	/**
	 * An array of event IDs to include in report.
	 *
	 * @var int[]
	 */
	public $code__in;

	/**
	 * An array of post types to include in report.
	 *
	 * @var string[]
	 */
	public $post_type__in;

	/**
	 * An array of post statuses to include in report.
	 *
	 * @var string[]
	 */
	public $post_status__in;

	/**
	 * @param array $filters
	 * @param WSAL_AlertManager $alert_manager
	 *
	 * @return false
	 */
	public static function build_from_alternative_filters( $filters, $alert_manager ) {

		$result = new WSAL_ReportArgs();

		$sites        = null;
		if ( is_array( $sites ) && ! empty( $sites ) ) {
			$result->site__in = $sites;
		}

		if ( ! empty( $filters['users'] ) ) {
			$result->user__in = $alert_manager->get_user_ids( $filters['users'] );
		}

		if ( ! empty( $filters['users-exclude'] ) ) {
			$result->user__not_in = $alert_manager->get_user_ids( $filters['users-exclude'] );
		}

		if ( is_array( $filters['roles'] ) && ! empty( $filters['roles'] ) ) {
			$result->role__in = $filters['roles'];
		}

		if ( is_array( $filters['roles-exclude'] ) && ! empty( $filters['roles-exclude'] ) ) {
			$result->role__not_in = $filters['roles-exclude'];
		}
		
		$ip_addresses = empty( $filters['ip-addresses'] ) ? null : $filters['ip-addresses'];
		$alert_groups = $filters['alert-codes']['groups'];
		$alert_codes  = $filters['alert-codes']['alerts'];
		$date_start   = empty( $filters['date-range']['start'] ) ? null : $filters['date-range']['start'];
		$date_end     = empty( $filters['date-range']['end'] ) ? null : $filters['date-range']['end'];

		// extract full list of alert codes
		$codes = $alert_manager->get_codes_by_groups( $alert_groups, $alert_codes );




		if ( is_array( $ip_addresses ) && ! empty( $ip_addresses ) ) {
			$result->ip__in = $ip_addresses;
		}

		if ( is_array( $codes ) && ! empty( $codes ) ) {
			$result->code__in = $codes;
		}

		if ( $date_start ) {
			$result->start_date = $date_start;
		}

		if ( $date_end ) {
			$result->end_date = $date_end;
		}

		return $result;
	}

	/**
	 * @param array $filters An array of filters as defined in the Reports extension form.
	 * @param WSAL_Rep_Common $report_utils
	 *
	 * @return WSAL_ReportArgs
	 */
	public static function build_from_extension_filters( $filters, $report_utils ) {

		$result = new WSAL_ReportArgs();

		if ( is_array( $filters['sites'] ) && ! empty( $filters['sites'] ) ) {
			$result->site__in = $filters['sites'];
		}

		if ( is_array( $filters['sites-exclude'] ) && ! empty( $filters['sites-exclude'] ) ) {
			$result->site__not_in = $filters['sites-exclude'];
		}

		if ( is_array( $filters['users'] ) && ! empty( $filters['users'] ) ) {
			$result->user__in = $filters['users'];
		}

		if ( is_array( $filters['users-exclude'] ) && ! empty( $filters['users-exclude'] ) ) {
			$result->user__not_in = $filters['users-exclude'];
		}

		if ( is_array( $filters['roles'] ) && ! empty( $filters['roles'] ) ) {
			$result->role__in = $filters['roles'];
		}

		if ( is_array( $filters['roles-exclude'] ) && ! empty( $filters['roles-exclude'] ) ) {
			$result->role__not_in = $filters['roles-exclude'];
		}

		if ( is_array( $filters['alert_codes_post_types'] ) && ! empty( $filters['alert_codes_post_types'] ) ) {
			$result->post_type__in = $filters['alert_codes_post_types'];
		}

		if ( is_array( $filters['alert_codes_post_statuses'] ) && ! empty( $filters['alert_codes_post_statuses'] ) ) {
			$result->post_status__in = $filters['alert_codes_post_statuses'];
		}

		if ( is_array( $filters['objects'] ) && ! empty( $filters['objects'] ) ) {
			$result->object__in = $filters['objects'];
		}

		if ( is_array( $filters['objects-exclude'] ) && ! empty( $filters['objects-exclude'] ) ) {
			$result->object__not_in = $filters['objects-exclude'];
		}

		if ( is_array( $filters['event-types'] ) && ! empty( $filters['event-types'] ) ) {
			$result->type__in = $filters['event-types'];
		}

		if ( is_array( $filters['event-types-exclude'] ) && ! empty( $filters['event-types-exclude'] ) ) {
			$result->type__not_in = $filters['event-types-exclude'];
		}

		if ( is_array( $filters['ip-addresses'] ) && ! empty( $filters['ip-addresses'] ) ) {
			$result->ip__in = $filters['ip-addresses'];
		}

		if ( is_array( $filters['ip-addresses-exclude'] ) && ! empty( $filters['ip-addresses-exclude'] ) ) {
			$result->ip__not_in = $filters['ip-addresses-exclude'];
		}

		// extract full list of alert codes
		$_codes = $report_utils->GetCodesByGroups( $filters['alert_codes_groups'], $filters['alert_codes_alerts'] );

		if ( is_array( $_codes ) && ! empty( $_codes ) ) {
			$result->code__in = $_codes;
		}

		if ( $filters['date_range_start'] ) {
			$result->start_date = $filters['date_range_start'];
		}

		if ( $filters['date_range_end'] ) {
			$result->end_date = $filters['date_range_end'];
		}

		return $result;
	}
}
