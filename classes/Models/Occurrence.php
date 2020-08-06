<?php
/**
 * Class: Occurrence Model Class
 *
 * Occurrence model is the model for the Occurrence adapter,
 * used for get the alert, set the meta fields, etc.
 *
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Occurrence model is the model for the Occurrence adapter,
 * used for get the alert, set the meta fields, etc.
 *
 * @package Wsal
 */
class WSAL_Models_Occurrence extends WSAL_Models_ActiveRecord {

	/**
	 * Occurrence ID.
	 *
	 * @var integer
	 */
	public $id = 0;

	/**
	 * Site ID.
	 *
	 * @var integer
	 */
	public $site_id = 0;

	/**
	 * Alert ID.
	 *
	 * @var integer
	 */
	public $alert_id = 0;

	/**
	 * Created On.
	 *
	 * @var string
	 */
	public $created_on = 0.0;

	/**
	 * Is read.
	 *
	 * @var bool
	 */
	public $is_read = false;

	/**
	 * Is migrated.
	 *
	 * @var bool
	 */
	public $is_migrated = false;

	/**
	 * Model Name.
	 *
	 * @var string
	 */
	protected $adapterName = 'Occurrence';

	/**
	 * Returns the alert related to this occurrence.
	 *
	 * @see WSAL_AlertManager::GetAlert()
	 * @return WSAL_Alert
	 */
	public function GetAlert() {
		return WpSecurityAuditLog::GetInstance()->alerts->GetAlert(
			$this->alert_id,
			(object) array(
				'mesg' => __( 'Alert message not found.', 'wp-security-audit-log' ),
				'desc' => __( 'Alert description not found.', 'wp-security-audit-log' ),
			)
		);
	}

	/**
	 * Returns the value of a meta item.
	 *
	 * @see WSAL_Adapters_MySQL_Occurrence::GetNamedMeta()
	 * @param string $name - Name of meta item.
	 * @param mixed  $default - Default value returned when meta does not exist.
	 * @return mixed The value, if meta item does not exist $default returned.
	 */
	public function GetMetaValue( $name, $default = array() ) {
		// Get meta adapter.
		$meta = $this->getAdapter()->GetNamedMeta( $this, $name );
		return maybe_unserialize( $meta['value'] );

		// TO DO: re-introduce add is loaded check before running query
		// return $meta->IsLoaded() ? $meta->value : $default;
	}

	/**
	 * Sets the value of a meta item (creates or updates meta item).
	 *
	 * @param string $name - Meta name.
	 * @param mixed  $value - Meta value.
	 */
	public function SetMetaValue( $name, $value ) {
		// check explicitly for `0` string values.
		if ( '0' === $value || ! empty( $value ) ) {
			// Get meta adapter.
			$model                = new WSAL_Models_Meta();
			$model->occurrence_id = $this->getId();
			$model->name          = $name;
			$model->value         = maybe_serialize( $value );
			$model->SaveMeta();
		}
	}

	/**
	 * Update Metadata of this occurrence by name.
	 *
	 * @see WSAL_Models_Meta::UpdateByNameAndOccurenceId()
	 * @param string $name - Meta name.
	 * @param mixed  $value - Meta value.
	 */
	public function UpdateMetaValue( $name, $value ) {
		$model = new WSAL_Models_Meta();
		$model->UpdateByNameAndOccurenceId( $name, $value, $this->getId() );
	}

	/**
	 * Returns a key-value pair of meta data.
	 *
	 * @see WSAL_Adapters_MySQL_Occurrence::GetMultiMeta()
	 * @return array
	 */
	public function GetMetaArray() {
		$result = array();
		$metas  = $this->getAdapter()->GetMultiMeta( $this );
		foreach ( $metas as $meta ) {
			$result[ $meta->name ] = maybe_unserialize( $meta->value );
		}
		return $result;
	}

	/**
	 * Creates or updates all meta data passed as an array of meta-key/meta-value pairs.
	 *
	 * @param array $data - New meta data.
	 */
	public function SetMeta( $data ) {
		foreach ( (array) $data as $key => $val ) {
			$this->SetMetaValue( $key, $val );
		}
	}

	/**
	 * Gets alert message.
	 *
	 * @see WSAL_Alert::GetMessage()
	 *
	 * @param callable|null $meta_formatter - (Optional) Meta formatter callback.
	 * @param mixed         $highlight      - (Optional) Highlight format.
	 * @param array         $meta           - Occurrence meta array.
	 * @return string Full-formatted message.
	 */
	public function GetMessage( $meta_formatter = null, $highlight = false, $meta = null ) {
		if ( ! isset( $this->_cachedmessage ) ) {
			// Get correct message entry.
			if ( $this->is_migrated ) {
				$this->_cachedmessage = $this->GetMetaValue( 'MigratedMesg', false );
			}
			if ( ! $this->is_migrated || ! $this->_cachedmessage ) {
				$this->_cachedmessage = $this->GetAlert()->mesg;
			}
			// Fill variables in message.
			$meta_array   = null === $meta ? $this->GetMetaArray() : $meta;
			$alert_object = $this->GetAlert();
			if ( null !== $alert_object && method_exists( $alert_object, 'GetMessage' ) ) {
				$this->_cachedmessage = $alert_object->GetMessage( $meta_array, $meta_formatter, $this->_cachedmessage, $this->getId(), $highlight );
			} else {
				/**
				 * Reaching this point means we have an event we don't know
				 * about. It could be a custom event or possibly a removed
				 * event.
				 *
				 * We currently have 2 sets of custom events that we can flag
				 * specific messages about. WPForms and BBPress. Both are
				 * available as plugin add-ons.
				 *
				 * @since 4.0.2
				 */
				$addon_event_codes = array(
					'wpforms' => array(
						'name'      => __( 'WPForms', 'wp-security-audit-log' ),
						'event_ids' => array( 5500, 5501, 5502, 5503, 5504, 5505, 5506 ),
					),
					'bbpress' => array(
						'name'      => __( 'BBPress', 'wp-security-audit-log' ),
						'event_ids' => array( 8000, 8001, 8002, 8003, 8004, 8005, 8006, 8007, 8008, 8009, 8010, 8011, 8012, 8013, 8014, 8015, 8016, 8017, 8018, 8019, 8020, 8021, 8022, 8023 ),
					),
					'woocommerce' => array(
						'name'      => __( 'WooCommerce', 'wp-security-audit-log' ),
						'event_ids' => array( 9000, 9001, 9003, 9004, 9005, 9006, 9007, 9008, 9009, 9010, 9011, 9012, 9013, 9014, 9015, 9072, 9073, 9077, 9016, 9017, 9018, 9019, 9020, 9021, 9022, 9023, 9024, 9025, 9026, 9042, 9043, 9044, 9045, 9046, 9105, 9047, 9048, 9049, 9050, 9051, 9027, 9028, 9029, 9030, 9031, 9032, 9033, 9034, 9085, 9086, 9087, 9088, 9089, 9090, 9091, 9092, 9093, 9094, 9074, 9075, 9076, 9078, 9079, 9080, 9081, 9082, 9002, 9052, 9053, 9054, 9055, 9056, 9057, 9058, 9059, 9060, 9061, 9062, 9063, 9064, 9065, 9066, 9067, 9068, 9069, 9070, 9071, 9035, 9036, 9037, 9038, 9039, 9040, 9041, 9083, 9084, 9101, 9102, 9103, 9104 ),
					),
					'wfcm' => array(
						'name'      => __( 'WFCM', 'wp-security-audit-log' ),
						'event_ids' => array( 6028, 6029, 6030, 6031, 6032, 6033 ),
					),
				);
				$installer_nonce   = wp_create_nonce( 'wsal-install-addon' );
				foreach ( $addon_event_codes as $key => $addon ) {
					$f1 = in_array( $this->alert_id, $addon['event_ids'], true );
					if ( in_array( $this->alert_id, $addon['event_ids'], true ) ) {
						// check key and update message here.
						$message = sprintf(
							'To view this event you need to install the activity log extension for %1$s. %2$s%3$sInstall and activate extension %4$s',
							esc_html( $addon['name'] ),
							'<br />',
							'<button type="button" class="button-primary wsal-addon-install-trigger" data-nonce="' . esc_attr( $installer_nonce ) . '" data-addon-name="' . esc_attr( $key ) . '">',
							'</button>'
						);
						// return this message early.
						return $message;
					}
				}
				$this->_cachedmessage = isset( $cached_message ) ? $cached_message : sprintf(
					/* Translators: 1: html that opens a link, 2: html that closes a link. */
					__( 'Alert message was not available, this may have been a custom alert that no longer exists. Read more about custom events %1$shere%2$s.', 'wp-security-audit-log' ),
					'<a href="https://wpactivitylog.com/support/kb/create-custom-events-wordpress-activity-log/" target="_blank">',
					'</a>'
				);
			}
		}
		return $this->_cachedmessage;
	}

	/**
	 * Delete occurrence as well as associated meta data.
	 *
	 * @see WSAL_Adapters_ActiveRecordInterface::Delete()
	 * @return boolean True on success, false on failure.
	 */
	public function Delete() {
		foreach ( $this->getAdapter()->GetMeta() as $meta ) {
			$meta->Delete();
		}
		return parent::Delete();
	}

	/**
	 * Gets the actual alert event ID.
	 *
	 * @method get_alert_id
	 * @since  4.0.0
	 * @return int
	 */
	public function get_alert_id() {
		return ( isset( $this->alert_id ) ) ? $this->alert_id : 0;
	}

	/**
	 * Gets the username.
	 *
	 * @see WSAL_Adapters_MySQL_Occurrence::GetFirstNamedMeta()
	 *
	 * @param array $meta - Occurrence meta array.
	 * @return string User's username.
	 */
	public function GetUsername( $meta = null ) {
		if ( null === $meta ) {
			$meta = $this->getAdapter()->GetFirstNamedMeta( $this, array( 'Username', 'CurrentUserID' ) );

			if ( $meta ) {
				switch ( true ) {
					case 'Username' === $meta->name:
						return $meta->value;
					case 'CurrentUserID' === $meta->name:
						$data = get_userdata( $meta->value );
						return $data ? $data->user_login : null;
				}
			}
		} else {
			if ( isset( $meta['Username'] ) ) {
				return $meta['Username'];
			} elseif ( isset( $meta['CurrentUserID'] ) ) {
				$data = get_userdata( $meta['CurrentUserID'] );
				return $data ? $data->user_login : null;
			}
		}
		return null;
	}

	/**
	 * Gets the Client IP.
	 *
	 * @param array $meta - Occurrence meta array.
	 * @return string IP address of request.
	 */
	public function GetSourceIP( $meta = null ) {
		if ( null === $meta ) {
			return $this->GetMetaValue( 'ClientIP', '' );
		}
		return isset( $meta['ClientIP'] ) ? $meta['ClientIP'] : '';
	}

	/**
	 * Gets if there are other IPs.
	 *
	 * @return string IP address of request (from proxies etc).
	 */
	public function GetOtherIPs() {
		$result = array();
		$data   = (array) $this->GetMetaValue( 'OtherIPs', array() );
		foreach ( $data as $ips ) {
			foreach ( $ips as $ip ) {
				$result[] = $ip;
			}
		}
		return array_unique( $result );
	}

	/**
	 * Gets user roles.
	 *
	 * @param array $meta - Occurrence meta array.
	 * @return array Array of user roles.
	 */
	public function GetUserRoles( $meta = null ) {
		if ( null === $meta ) {
			return $this->GetMetaValue( 'CurrentUserRoles', array() );
		}
		return isset( $meta['CurrentUserRoles'] ) ? $meta['CurrentUserRoles'] : array();
	}

	/**
	 * Method: Get Microtime.
	 *
	 * @return float - Number of seconds (and milliseconds as fraction) since unix Day 0.
	 * @todo This needs some caching.
	 */
	protected function GetMicrotime() {
		return microtime( true );// + get_option('gmt_offset') * HOUR_IN_SECONDS;
	}

	/**
	 * Finds occurrences of the same type by IP and Username within specified time frame.
	 *
	 * @param array $args - Query args.
	 * @return WSAL_Models_Occurrence[]
	 */
	public function CheckKnownUsers( $args = array() ) {
		return $this->getAdapter()->CheckKnownUsers( $args );
	}

	/**
	 * Finds occurrences of the same type by IP within specified time frame.
	 *
	 * @param array $args - Query args.
	 * @return WSAL_Models_Occurrence[]
	 */
	public function CheckUnKnownUsers( $args = array() ) {
		return $this->getAdapter()->CheckUnKnownUsers( $args );
	}

	/**
	 * Finds occurrences of the alert 1003.
	 *
	 * @param array $args - Query args.
	 * @return WSAL_Models_Occurrence[]
	 */
	public function check_alert_1003( $args = array() ) {
		return $this->getAdapter()->check_alert_1003( $args );
	}

	/**
	 * Gets occurrence by Post_id
	 *
	 * @see WSAL_Adapters_MySQL_Occurrence::GetByPostID()
	 * @param integer $post_id - Post ID.
	 * @return WSAL_Models_Occurrence[]
	 */
	public function GetByPostID( $post_id ) {
		return $this->getAdapter()->GetByPostID( $post_id );
	}

	/**
	 * Gets occurrences of the same type by IP within specified time frame.
	 *
	 * @see WSAL_Adapters_MySQL_Occurrence::CheckAlert404()
	 * @param array $args - Query args.
	 * @return WSAL_Models_Occurrence[]
	 */
	public function CheckAlert404( $args = array() ) {
		return $this->getAdapter()->CheckAlert404( $args );
	}
}
