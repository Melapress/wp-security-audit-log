<?php
/**
 * Report arguments class.
 *
 * @package    wsal
 * @subpackage reports
 *
 * @author     Martin Krcho <martin@wpwhitesecurity.com>
 * @since      4.3.2
 */

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
	 * An array of event IDs to exclude from report.
	 *
	 * @var int[]
	 */
	public $code__not_in;

	/**
	 * An array of post IDs to include in report.
	 *
	 * @var string[]
	 */
	public $post__in;

	/**
	 * An array of post IDs to exclude from report.
	 *
	 * @var string[]
	 */
	public $post__not_in;

	/**
	 * An array of post types to include in report.
	 *
	 * @var string[]
	 */
	public $post_type__in;

	/**
	 * An array of post types to exclude from report.
	 *
	 * @var string[]
	 */
	public $post_type__not_in;

	/**
	 * An array of post statuses to include in report.
	 *
	 * @var string[]
	 */
	public $post_status__in;

	/**
	 * An array of post statuses to exclude from report.
	 *
	 * @var string[]
	 */
	public $post_status__not_in;

	/**
	 * Builds the object from alternative filters data.
	 *
	 * @param array $filters Filters data.
	 *
	 * @return WSAL_ReportArgs Report args object.
	 */
	public static function build_from_alternative_filters( $filters ) {
		$_filters = array();
		foreach ( $filters as $key => $value ) {
			if ( is_array( $value ) && ! is_int( array_key_first( $value ) ) ) {
				foreach ( $value as $sub_key => $sub_value ) {
					$_filters[ $key . '_' . $sub_key ] = $sub_value;
				}
			} else {
				$_filters[ $key ] = $value;
			}
		}

		return self::build_from_extension_filters( $_filters, WpSecurityAuditLog::get_instance()->reports_util );
	}

	/**
	 * Builds the object from extension filters data.
	 *
	 * @param array           $filters      An array of filters as defined in the Reports extension form.
	 * @param WSAL_Rep_Common $report_utils Reporting utils.
	 *
	 * @return WSAL_ReportArgs Report args object.
	 */
	public static function build_from_extension_filters( $filters, $report_utils ) {

		$result = new WSAL_ReportArgs();

		if ( self::is_field_present_and_non_empty_array( 'sites', $filters ) ) {
			$result->site__in = $filters['sites'];
		}

		if ( self::is_field_present_and_non_empty_array( 'sites-exclude', $filters ) ) {
			$result->site__not_in = $filters['sites-exclude'];
		}

		if ( self::is_field_present_and_non_empty_array( 'users', $filters ) ) {
			$result->user__in = self::extract_user_ids( $filters['users'] );
		}

		if ( self::is_field_present_and_non_empty_array( 'users-exclude', $filters ) ) {
			$result->user__not_in = self::extract_user_ids( $filters['users-exclude'] );
		}

		if ( self::is_field_present_and_non_empty_array( 'roles', $filters ) ) {
			$result->role__in = $filters['roles'];
		}

		if ( self::is_field_present_and_non_empty_array( 'roles-exclude', $filters ) ) {
			$result->role__not_in = $filters['roles-exclude'];
		}

		if ( self::is_field_present_and_non_empty_array( 'post_ids', $filters ) ) {
			$result->post__in = $filters['post_ids'];
		}

		if ( self::is_field_present_and_non_empty_array( 'post_ids-exclude', $filters ) ) {
			$result->post__not_in = $filters['post_ids-exclude'];
		}

		if ( self::is_field_present_and_non_empty_array( 'alert_codes_post_types', $filters ) ) {
			$result->post_type__in = $filters['alert_codes_post_types'];
		}

		if ( self::is_field_present_and_non_empty_array( 'post_types', $filters ) ) {
			$result->post_type__in = $filters['post_types'];
		}

		if ( self::is_field_present_and_non_empty_array( 'alert_codes_post_types-exclude', $filters ) ) {
			$result->post_type__not_in = $filters['alert_codes_post_types-exclude'];
		}

		if ( self::is_field_present_and_non_empty_array( 'post_types-exclude', $filters ) ) {
			$result->post_type__not_in = $filters['post_types-exclude'];
		}

		if ( self::is_field_present_and_non_empty_array( 'alert_codes_post_statuses', $filters ) ) {
			$result->post_status__in = $filters['alert_codes_post_statuses'];
		}

		if ( self::is_field_present_and_non_empty_array( 'post_statuses', $filters ) ) {
			$result->post_status__in = $filters['post_statuses'];
		}

		if ( self::is_field_present_and_non_empty_array( 'alert_codes_post_statuses-exclude', $filters ) ) {
			$result->post_status__not_in = $filters['alert_codes_post_statuses-exclude'];
		}

		if ( self::is_field_present_and_non_empty_array( 'post_statuses-exclude', $filters ) ) {
			$result->post_status__not_in = $filters['post_statuses-exclude'];
		}

		if ( self::is_field_present_and_non_empty_array( 'objects', $filters ) ) {
			$result->object__in = $filters['objects'];
		}

		if ( self::is_field_present_and_non_empty_array( 'objects-exclude', $filters ) ) {
			$result->object__not_in = $filters['objects-exclude'];
		}

		if ( self::is_field_present_and_non_empty_array( 'event-types', $filters ) ) {
			$result->type__in = $filters['event-types'];
		}

		if ( self::is_field_present_and_non_empty_array( 'event-types-exclude', $filters ) ) {
			$result->type__not_in = $filters['event-types-exclude'];
		}

		if ( self::is_field_present_and_non_empty_array( 'ip-addresses', $filters ) ) {
			$result->ip__in = $filters['ip-addresses'];
		}

		if ( self::is_field_present_and_non_empty_array( 'ip-addresses-exclude', $filters ) ) {
			$result->ip__not_in = $filters['ip-addresses-exclude'];
		}

		$_codes = self::get_codes( $filters, 'alert_codes_groups', 'alert_codes_alerts', $report_utils );
		if ( is_array( $_codes ) && ! empty( $_codes ) ) {
			$result->code__in = $_codes;
		}

		$_excluded_codes = self::get_codes( $filters, 'alert_codes_groups-exclude', 'alert_codes_alerts-exclude', $report_utils );
		if ( is_array( $_excluded_codes ) && ! empty( $_excluded_codes ) ) {
			$result->code__not_in = $_excluded_codes;
		}

		if ( array_key_exists( 'date_range_start', $filters ) && $filters['date_range_start'] ) {
			$result->start_date = $filters['date_range_start'];
		}

		if ( array_key_exists( 'date_range_end', $filters ) && $filters['date_range_end'] ) {
			$result->end_date = $filters['date_range_end'];
		}

		return $result;
	}

	/**
	 * Checks if the key is present in the array and the value is non-empty array.
	 *
	 * @param string $key   Key to look for.
	 * @param array  $array Report filtering data.
	 *
	 * @return bool True if the key is present in the array and the value is non-empty array.
	 *
	 * @since 4.4.0
	 */
	private static function is_field_present_and_non_empty_array( $key, $array ) {
		return array_key_exists( $key, $array ) && is_array( $array[ $key ] ) && ! empty( $array[ $key ] );
	}

	/**
	 * Determines all alert IDs/codes for given array of data. The groups and keys are located using the provided keys.
	 *
	 * @param array           $array        Filter data.
	 * @param string          $groups_key   Key of the event groups array.
	 * @param string          $codes_key    Key of the event codes array.
	 * @param WSAL_Rep_Common $report_utils Report utilities.
	 *
	 * @return int[]
	 *
	 * @since 4.4.0
	 */
	private static function get_codes( $array, $groups_key, $codes_key, $report_utils ) {
		$groups = self::is_field_present_and_non_empty_array( $groups_key, $array ) ? $array[ $groups_key ] : array();
		$alerts = self::is_field_present_and_non_empty_array( $codes_key, $array ) ? $array[ $codes_key ] : array();

		$result = $report_utils->get_codes_by_groups( $groups, $alerts, false );
		if ( false === $result ) {
			return array();
		}

		return $result;
	}

	/**
	 * Determines user ID based on given value.
	 *
	 * @param int|string $value User ID or login username.
	 *
	 * @return int User ID.
	 * @since 4.4.0
	 */
	public static function grab_uid( $value ) {
		if ( is_numeric( $value ) ) {
			return intval( $value );
		}

		// Load user by id.
		$user = get_user_by( 'login', $value );
		if ( $user instanceof WP_User ) {
			return $user->ID;
		}

		return 0;
	}

	/**
	 * Extracts user IDs.
	 *
	 * @param array $values User IDs or login usernames (or both).
	 *
	 * @return int[] User IDs.
	 * @since 4.4.0
	 */
	public static function extract_user_ids( $values ) {
		return array_filter(
			array_map(
				array( __CLASS__, 'grab_uid' ),
				$values
			),
			function ( $item ) {
				return $item > 0;
			}
		);
	}
}
