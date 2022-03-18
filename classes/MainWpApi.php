<?php
/**
 * Class WSAL_MainWpApi.
 *
 * @package    wsal
 * @subpackage main-wp
 * @since      4.4.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handler for MainWP API endpoints.
 *
 * @package    wsal
 * @subpackage main-wp
 * @since      4.4.0
 */
class WSAL_MainWpApi {

	/**
	 * Instance of WpSecurityAuditLog.
	 *
	 * @var WpSecurityAuditLog
	 */
	protected $plugin;

	/**
	 * Method: Constructor.
	 *
	 * @param WpSecurityAuditLog $plugin - Instance of WpSecurityAuditLog.
	 */
	public function __construct( WpSecurityAuditLog $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * MainWP API Handler.
	 *
	 * @param array $info      – Information to return.
	 * @param array $post_data – Post data array from MainWP.
	 *
	 * @return mixed
	 * @since 3.2.5
	 */
	public function handle_callback( $info, $post_data ) {
		if ( isset( $post_data['action'] ) ) {
			switch ( $post_data['action'] ) {
				case 'check_wsal':
					return $this->handle_wsal_info_check();

				case 'get_events':
					$limit      = isset( $post_data['events_count'] ) ? $post_data['events_count'] : false;
					$offset     = isset( $post_data['events_offset'] ) ? $post_data['events_offset'] : false;
					$query_args = isset( $post_data['query_args'] ) ? $post_data['query_args'] : false;
					return $this->get_events_data( $limit, $offset, $query_args );

				case 'get_report':
					$filters     = isset( $post_data['filters'] ) ? $post_data['filters'] : array();
					$report_type = isset( $post_data['report_type'] ) ? $post_data['report_type'] : false;
					return $this->get_report_data( $filters, $report_type );

				case 'latest_event':
					// run the query and return it.
					$event = $this->query_for_latest_event();
					$event = $event->get_adapter()->execute_query( $event );

					// Set the return object.
					if ( isset( $event[0] ) ) {
						$info             = new stdClass();
						$info->alert_id   = $event[0]->alert_id;
						$info->created_on = $event[0]->created_on;
					} else {
						$info = false;
					}
					break;
				case 'enforce_settings':
					return $this->handle_settings_enforcement( $post_data );

				case 'get_event_definitions':
					return $this->get_event_definitions();

				default:
					break;
			}
		}

		return $info;
	}

	/**
	 * Handles API call requesting info about WSAL plugin.
	 *
	 * @return stdClass
	 * @since 4.4.0
	 */
	protected function handle_wsal_info_check() {
		$info                 = new stdClass();
		$info->wsal_installed = true;
		$info->is_premium     = false;
		return $info;
	}

	/**
	 * Return alerts for MainWP Extension.
	 *
	 * @param integer       $limit      - Number of alerts to retrieve.
	 * @param int|bool      $offset     - Events offset, otherwise false.
	 * @param stdClass|bool $query_args - Events query arguments, otherwise false.
	 *
	 * @return stdClass
	 */
	public function get_events_data( $limit = 100, $offset = false, $query_args = false ) {
		$mwp_events = new stdClass();

		// Check if limit is not empty.
		if ( empty( $limit ) ) {
			return $mwp_events;
		}

		// Initiate query occurrence object.
		$events_query = new WSAL_Models_OccurrenceQuery();
		$events_query->add_condition( 'site_id = %s ', 1 ); // Set site id.

		// Check query arguments.
		if ( false !== $query_args ) {
			if ( isset( $query_args['get_count'] ) && $query_args['get_count'] ) {
				$mwp_events->total_items = $events_query->get_adapter()->count( $events_query );
			} else {
				$mwp_events->total_items = false;
			}
		}

		// Set order by.
		$events_query->add_order_by( 'created_on', true );

		// Set the limit.
		$events_query->set_limit( $limit );

		// Set the offset.
		if ( false !== $offset ) {
			$events_query->set_offset( $offset );
		}

		// Execute the query.
		/** @var WSAL_Models_Occurrence[] $events */
		$events = $events_query->get_adapter()->execute_query( $events_query );

		if ( ! empty( $events ) && is_array( $events ) ) {
			foreach ( $events as $event ) {
				// Get event meta.
				$meta_data                                    = $event->get_meta_array();
				$meta_data['UserData']                        = $this->plugin->alerts->get_event_user_data( WSAL_Utilities_UsersUtils::get_username( $meta_data ) );
				$mwp_events->events[ $event->id ]             = new stdClass();
				$mwp_events->events[ $event->id ]->id         = $event->id;
				$mwp_events->events[ $event->id ]->alert_id   = $event->alert_id;
				$mwp_events->events[ $event->id ]->created_on = $event->created_on;
				$mwp_events->events[ $event->id ]->meta_data  = $meta_data;
			}

			$mwp_events->users = $this->plugin->alerts->get_wp_users();
		}

		return $mwp_events;
	}

	/**
	 * Generate report matching the filter passed.
	 *
	 * @param array $filters     - Filters.
	 * @param mixed $report_type - Type of report.
	 *
	 * @return stdClass
	 *
	 * @since 4.4.0 Removed support for report type "statistics_unique_ips".
	 */
	public function get_report_data( array $filters, $report_type ) {
		$report       = new stdClass();
		$report->data = array();
		return $report;
	}

	/**
	 * Performs a query to retrieve the latest event in the logs.
	 *
	 * @return array
	 * @since  4.0.3
	 */
	public function query_for_latest_event() {
		$event_query = new WSAL_Models_OccurrenceQuery();
		// order by creation.
		$event_query->add_order_by( 'created_on', true );
		// only request 1 item.
		$event_query->set_limit( 1 );

		return $event_query;
	}

	/**
	 * Handles API call enforcing certain WSAL settings.
	 *
	 * @param array $post_data Received request data.
	 *
	 * @return string[]
	 * @since 4.4.0
	 */
	private function handle_settings_enforcement( $post_data ) {
		// Check subaction.
		if ( ! array_key_exists( 'subaction', $post_data ) || empty( $post_data['subaction'] ) ) {
			return array(
				'success' => 'no',
				'message' => 'Missing subaction parameter.',
			);
		}

		$subaction = filter_var( $post_data['subaction'], FILTER_SANITIZE_STRING );
		if ( ! in_array( $subaction, array( 'update', 'remove' ), true ) ) {
			return array(
				'success' => 'no',
				'message' => 'Unsupported subaction parameter value.',
			);
		}

		if ( 'update' === $subaction ) {
			// Store the enforced settings in local database (used for example to disable related parts
			// of the settings UI).
			$settings_to_enforce = $post_data['settings'];
			$this->plugin->settings()->set_mainwp_enforced_settings( $settings_to_enforce );

			// Change the existing settings.
			if ( array_key_exists( 'pruning_enabled', $settings_to_enforce ) ) {
				$this->plugin->settings()->set_pruning_date_enabled( $settings_to_enforce['pruning_enabled'] );
				if ( array_key_exists( 'pruning_date', $settings_to_enforce ) && array_key_exists( 'pruning_unit', $settings_to_enforce ) ) {
					$this->plugin->settings()->set_pruning_date( $settings_to_enforce['pruning_date'] . ' ' . $settings_to_enforce['pruning_unit'] );
					$this->plugin->settings()->set_pruning_unit( $settings_to_enforce['pruning_unit'] );
				}
			}

			if ( array_key_exists( 'disabled_events', $settings_to_enforce ) ) {
				$disabled_event_ids = array_map( 'intval', explode( ',', $settings_to_enforce['disabled_events'] ) );
				$this->plugin->settings()->set_disabled_alerts( $disabled_event_ids );
			}

			if ( array_key_exists( 'incognito_mode_enabled', $settings_to_enforce ) ) {
				$this->plugin->settings()->set_incognito( $settings_to_enforce['incognito_mode_enabled'] );
			}

			if ( array_key_exists( 'login_notification_enabled', $settings_to_enforce ) ) {
				$login_page_notification_enabled = $settings_to_enforce['login_notification_enabled'];
				$this->plugin->settings()->set_login_page_notification( $login_page_notification_enabled );
				if ( 'yes' === $login_page_notification_enabled ) {
					$this->plugin->settings()->set_login_page_notification_text( $settings_to_enforce['login_notification_text'] );
				}
			}
		} elseif ( 'remove' === $subaction ) {
			$this->plugin->settings()->delete_mainwp_enforced_settings();
		}

		$this->plugin->alerts->trigger_event( 6043 );

		return array(
			'success' => 'yes',
		);
	}

	/**
	 * Generates report for MainWP extension.
	 *
	 * @param array $filters - Filters.
	 *
	 * @return array
	 */
	private function generate_report( $filters ) {
		// Check the report format.
		$report_format = empty( $filters['report-format'] ) ? 'html' : $filters['report-format'];
		if ( ! in_array( $report_format, array( 'csv', 'html' ), true ) ) {
			$report_format = WSAL_Rep_DataFormat::get_default();
		}

		// Alert codes filter needs to renamed to work correctly with further report processing.
		if ( array_key_exists( 'alert-codes', $filters ) ) {
			$filters['alert_codes'] = $filters['alert-codes'];
			unset( $filters['alert-codes'] );
		}

		$args      = WSAL_ReportArgs::build_from_alternative_filters( $filters );
		$next_date = empty( $filters['nextDate'] ) ? null : $filters['nextDate'];
		$limit     = empty( $filters['limit'] ) ? 0 : $filters['limit'];
		$last_date = null;

		$results = $this->plugin->get_connector()->get_adapter( 'Occurrence' )->get_report_data( $args, $next_date, $limit );

		if ( ! empty( $results['lastDate'] ) ) {
			$last_date = $results['lastDate'];
			unset( $results['lastDate'] );
		}

		if ( empty( $results ) ) {
			return false;
		}

		$data = array();

		// Get alert details.
		foreach ( $results as $entry ) {
			if ( 9999 === (int) $entry->alert_id ) {
				continue;
			}

			array_push( $data, $this->plugin->alerts->get_alert_details( $entry, 'report-' . $report_format ) );
		}

		if ( empty( $data ) ) {
			return false;
		}

		return array(
			'data'     => $data,
			'filters'  => $filters,
			'lastDate' => $last_date,
		);
	}

	/**
	 * Retrieves the events definitions along with some other related pieces of information, such as event object and
	 * event type labels.
	 *
	 * @return array Events definitions data.
	 *
	 * @since 4.4.0
	 */
	private function get_event_definitions() {
		$alert_manager = $this->plugin->alerts;

		return array(
			'events'  => $alert_manager->get_alerts(),
			'objects' => $alert_manager->get_event_objects_data(),
			'types'   => $alert_manager->get_event_type_data(),
		);
	}
}
