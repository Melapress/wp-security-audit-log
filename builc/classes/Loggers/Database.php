<?php
/**
 * Class: Logger
 *
 * Logger class for wsal.
 *
 * @since 1.0.0
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loggers Class.
 *
 * This class store the logs in the Database and adds the promo
 * alerts, there is also the function to clean up alerts.
 *
 * @package Wsal
 */
class WSAL_Loggers_Database extends WSAL_AbstractLogger {

	/**
	 * Method: Constructor.
	 *
	 * @param WpSecurityAuditLog $plugin - Instance of WpSecurityAuditLog.
	 * @since 1.0.0
	 */
	public function __construct( WpSecurityAuditLog $plugin ) {
		parent::__construct( $plugin );
		$plugin->AddCleanupHook( array( $this, 'CleanUp' ) );
	}

	/**
	 * Log an event.
	 *
	 * 1. If native DB is being used then check DB connection.
	 *   1a. If connection is good, then log the event.
	 *   1b. If no connection then save the alert in a temp buffer.
	 *
	 * 2. If external DB is in action then send the event to a temp buffer.
	 *
	 * @param integer $type            - Alert code.
	 * @param array   $data            - Metadata.
	 * @param integer $date            - (Optional) created_on.
	 * @param integer $siteid          - (Optional) site_id.
	 * @param bool    $migrated        - (Optional) is_migrated.
	 * @param bool    $override_buffer - (Optional) Override buffer to log event immediately.
	 */
	public function Log( $type, $data = array(), $date = null, $siteid = null, $migrated = false, $override_buffer = false ) {
		// Is this a php alert, and if so, are we logging such alerts?
		if ( $type < 0010 && ! $this->plugin->settings()->IsPhpErrorLoggingEnabled() ) {
			return;
		}

		// Create new occurrence.
		$occ              = new WSAL_Models_Occurrence();
		$occ->is_migrated = $migrated;
		$occ->created_on  = is_null( $date ) ? microtime( true ) : $date;
		$occ->alert_id    = $type;
		$occ->site_id     = ! is_null( $siteid ) ? $siteid : ( function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 0 );

		// Get DB connector.
		$db_config = WSAL_Connector_ConnectorFactory::GetConfig(); // Get DB connector configuration.

		// Get external buffer option.
		$use_buffer = $this->plugin->GetGlobalBooleanSetting( 'adapter-use-buffer' );
		if ( $override_buffer ) {
			$use_buffer = false;
		}

		// Check external DB.
		if ( null === $db_config || ( is_array( $db_config ) && ! $use_buffer ) ) { // Not external DB.
			// Get connector for DB.
			$connector  = $this->plugin->getConnector( $db_config );
			$wsal_db    = $connector->getConnection(); // Get DB connection.
			$connection = true;
			if ( isset( $wsal_db->dbh->errno ) ) {
				$connection = 0 !== (int) $wsal_db->dbh->errno ? false : true; // Database connection error check.
			} elseif ( is_wp_error( $wsal_db->error ) ) {
				$connection = false;
			}

			// Check DB connection.
			if ( $connection ) { // If connected then save the alert in DB.
				// Save the alert occurrence.
				$occ->Save();

				// Set up meta data of the alert.
				$occ->SetMeta( $data );
			} else { // Else store the alerts in temporary option.
				$this->store_events_in_buffer( $occ, $data );
			}
		} elseif ( is_array( $db_config ) && $use_buffer ) { // External DB.
			$this->store_events_in_buffer( $occ, $data );
		}

		/**
		 * Inject for promoting the paid add-ons.
		 *
		 * @deprecated 3.2.4
		 */
		// $type = (int) $type;
		// if ( 9999 !== $type ) {
		// 	$this->AlertInject( $occ );
		// }

		/**
		 * Fires immediately after an alert is logged.
		 *
		 * @since 3.1.2
		 */
		do_action( 'wsal_logged_alert', $occ, $type, $data, $date, $siteid, $migrated );
	}

	/**
	 * Method: Store events in temporary buffer.
	 *
	 * @param WSAL_Models_Occurrence $occ – Event occurrence object.
	 * @param array                  $event_data – Event meta-data.
	 * @return boolean
	 */
	private function store_events_in_buffer( $occ, $event_data ) {
		// Check event occurrence object.
		if ( ! empty( $occ ) && $occ instanceof WSAL_Models_Occurrence ) {
			// Get temporary stored alerts.
			$temp_alerts = $this->plugin->GetGlobalSetting( 'temp_alerts', array() );

			// Store current event in a temporary buffer.
			$temp_alerts[ $occ->created_on ]['alert']      = array(
				'is_migrated' => $occ->is_migrated,
				'created_on'  => $occ->created_on,
				'alert_id'    => $occ->alert_id,
				'site_id'     => $occ->site_id,
			);
			$temp_alerts[ $occ->created_on ]['alert_data'] = $event_data;

			// Save temporary alerts to options.
			$this->plugin->SetGlobalSetting( 'temp_alerts', $temp_alerts );
			return true;
		}

		// Something wrong with $occ object.
		return false;
	}

	/**
	 * Clean Up alerts by date OR by max number.
	 */
	public function CleanUp() {
		$now       = current_time( 'timestamp' );
		$max_sdate = $this->plugin->settings()->GetPruningDate();
		$max_count = $this->plugin->settings()->GetPruningLimit();
		$is_date_e = $this->plugin->settings()->IsPruningDateEnabled();
		$is_limt_e = $this->plugin->settings()->IsPruningLimitEnabled();

		// Return if retention is disabled.
		if ( ! $is_date_e && ! $is_limt_e ) {
			return;
		}

		// Return if archiving cron is running.
		if ( $this->plugin->GetGlobalSetting( 'archiving-cron-started', false ) ) {
			return;
		}

		// If archiving is enabled then events are deleted from the archive database.
		if ( $this->plugin->settings()->IsArchivingEnabled() ) {
			// Switch to Archive DB.
			$this->plugin->settings()->SwitchToArchiveDB();
		}

		$occ       = new WSAL_Models_Occurrence();
		$cnt_items = $occ->Count();

		// Check if there is something to delete.
		if ( $is_limt_e && ( $cnt_items < $max_count ) ) {
			return;
		}

		$max_stamp = $now - ( strtotime( $max_sdate ) - $now );
		$max_items = (int) max( ( $cnt_items - $max_count ) + 1, 0 );

		$query = new WSAL_Models_OccurrenceQuery();
		$query->addOrderBy( 'created_on', false );
		// TO DO: Fixing data.
		if ( $is_date_e ) {
			$query->addCondition( 'created_on <= %s', intval( $max_stamp ) );
		}
		if ( $is_limt_e ) {
			$query->setLimit( $max_items );
		}

		if ( ( $max_items - 1 ) == 0 ) {
			return; // Nothing to delete.
		}

		$result        = $query->getAdapter()->GetSqlDelete( $query );
		$deleted_count = $query->getAdapter()->Delete( $query );

		if ( 0 == $deleted_count ) {
			return; // Nothing to delete.
		}

		// Notify system.
		do_action( 'wsal_prune', $deleted_count, vsprintf( $result['sql'], $result['args'] ) );
	}

	/**
	 * Inject Promo alert every $count alerts if no Add-ons are activated.
	 *
	 * @param WSAL_Models_Occurrence $occurrence - Occurrence, instance of WSAL_Models_Occurrence.
	 *
	 * @deprecated 3.2.4
	 */
	private function AlertInject( $occurrence ) {
		$count = $this->CheckPromoToShow();
		if ( $count && $occurrence->getId() != 0 ) {
			if ( ( $occurrence->getId() % $count ) == 0 ) {
				$promo_to_send = $this->GetPromoAlert();
				if ( ! empty( $promo_to_send ) ) {
					$upgrade_link   = add_query_arg( 'page', 'wsal-auditlog-pricing', admin_url( 'admin.php' ) );
					$more_info_link = add_query_arg(
						array(
							'utm_source'   => 'plugin',
							'utm_medium'   => 'referral',
							'utm_campaign' => 'WSAL',
							'utm_content'  => 'db+integrations',
						),
						'https://wpactivitylog.com/features/'
					);
					$upgrade        = '<a href="' . $upgrade_link . '">' . __( 'Upgrade to Premium', 'wp-security-audit-log' ) . '</a>';
					$more_info      = '<a href="' . $more_info_link . '" target="_blank">' . __( 'More Information', 'wp-security-audit-log' ) . '</a>';
					$this->Log(
						9999, array(
							'ClientIP'     => '127.0.0.1',
							'Username'     => 'Plugin',
							'PromoMessage' => sprintf( $promo_to_send['message'], $upgrade, $more_info ),
							'PromoName'    => $promo_to_send['name'],
						)
					);
				}
			}
		}
	}

	/**
	 * Get the promo id, to send each time a different promo,
	 * keeping the last id saved in the DB.
	 *
	 * @return integer $promoToSend - The array index.
	 */
	private function GetPromoAlert() {
		$last_promo_sent_id = $this->plugin->GetGlobalSetting( 'promo-send-id' );
		$last_promo_sent_id = empty( $last_promo_sent_id ) ? 0 : $last_promo_sent_id;
		$promo_to_send      = null;
		$promo_alerts       = $this->GetActivePromoText();
		if ( ! empty( $promo_alerts ) ) {
			$promo_to_send = isset( $promo_alerts[ $last_promo_sent_id ] ) ? $promo_alerts[ $last_promo_sent_id ] : $promo_alerts[0];

			if ( $last_promo_sent_id < count( $promo_alerts ) - 1 ) {
				$last_promo_sent_id++;
			} else {
				$last_promo_sent_id = 0;
			}
			$this->plugin->SetGlobalSetting( 'promo-send-id', $last_promo_sent_id );
		}
		return $promo_to_send;
	}

	/**
	 * Array of promo.
	 *
	 * @return array $promo_alerts - The array of promo.
	 */
	private function GetActivePromoText() {
		$promo_alerts   = array();
		$promo_alerts[] = array(
			'name'    => 'Upgrade to Premium',
			'message' => 'See who is logged in, create user productivity reports, get notified instantly via email of important changes, add search and much more. <strong>%1$s</strong> | <strong>%2$s</strong>',
		);
		$promo_alerts[] = array(
			'name'    => 'See Who is Logged In, receive Email Alerts, generate User Productivity Reports and more!',
			'message' => 'Upgrade to premium and extend the plugin’s features with email alerts, reports tool, free-text based search, user logins and sessions management and more! <strong>%1$s</strong> | <strong>%2$s</strong>',
		);
		return $promo_alerts;
	}

	/**
	 * Check condition to show promo.
	 *
	 * @return integer|null - Counter alert.
	 */
	private function CheckPromoToShow() {
		// If the package is free, show the promo.
		if ( ! class_exists( 'WSAL_NP_Plugin' )
			&& ! class_exists( 'WSAL_Ext_Plugin' )
			&& ! class_exists( 'WSAL_Rep_Plugin' )
			&& ! class_exists( 'WSAL_SearchExtension' )
			&& ! class_exists( 'WSAL_UserSessions_Plugin' ) ) {
			return 150;
		}
		return null;
	}
}
