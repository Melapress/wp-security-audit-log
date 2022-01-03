<?php
/**
 * Class: Occurrence Model Class
 *
 * Occurrence model is the model for the Occurrence adapter,
 * used for get the alert, set the meta fields, etc.
 *
 * @package wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Occurrence model is the model for the Occurrence adapter,
 * used for get the alert, set the meta fields, etc.
 *
 * @package wsal
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
	 * @deprecated 4.3.2
	 */
	public $is_read = false;

	/**
	 * Is migrated.
	 *
	 * @var bool
	 * @deprecated 4.3.2
	 */
	public $is_migrated = false;

	/**
	 * Model Name.
	 *
	 * @var string
	 */
	protected $adapterName = 'Occurrence';

	/**
	 * @var string
	 */
	public $_cachedMessage;

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
	 * @param string $name - Name of meta item.
	 * @param mixed $default - Default value returned when meta does not exist.
	 *
	 * @return mixed The value, if meta item does not exist $default returned.
	 * @see WSAL_Adapters_MySQL_Occurrence::GetNamedMeta()
	 */
	public function GetMetaValue( $name, $default = array() ) {
		// Get meta adapter.
		$meta = $this->getAdapter()->GetNamedMeta( $this, $name );
		if ( is_null( $meta ) || ! array_key_exists( 'value', $meta ) ) {
			return $default;
		}

		return maybe_unserialize( $meta['value'] );
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
	 * @param array $meta - Occurrence meta array.
	 * @param string $context Message context.
	 *
	 * @return string Full-formatted message.
	 * @throws Freemius_Exception
	 * @see WSAL_Alert::GetMessage()
	 */
	public function GetMessage( $meta = null, $context = false ) {
		if ( ! isset( $this->_cachedMessage ) ) {
			// message caching
			if ( ! $this->_cachedMessage ) {
				$this->_cachedMessage = $this->GetAlert()->mesg;
			}
			// Fill variables in message.
			$meta_array   = null === $meta ? $this->GetMetaArray() : $meta;
			$alert_object = $this->GetAlert();
			if ( null !== $alert_object && method_exists( $alert_object, 'GetMessage' ) ) {
				$this->_cachedMessage = $alert_object->GetMessage( $meta_array, $this->_cachedMessage, $this->getId(), $context );
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
					'wfcm' => array(
						'name'      => __( 'WFCM', 'wp-security-audit-log' ),
						'event_ids' => array( 6028, 6029, 6030, 6031, 6032, 6033 ),
					),
				);

				// Filter to allow items to be added elsewhere.
				$addon_event_codes = apply_filters( 'wsal_addon_event_codes', $addon_event_codes );

				$installer_nonce   = wp_create_nonce( 'wsal-install-addon' );
				foreach ( $addon_event_codes as $key => $addon ) {
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
				$this->_cachedMessage = isset( $cached_message ) ? $cached_message : sprintf(
					/* Translators: 1: html that opens a link, 2: html that closes a link. */
					__( 'This type of activity / change is no longer monitored. You can create your own custom event IDs to keep a log of such change. Read more about custom events %1$shere%2$s.', 'wp-security-audit-log' ),
					'<a href="https://wpactivitylog.com/support/kb/create-custom-events-wordpress-activity-log/" rel="noopener noreferrer" target="_blank">',
					'</a>'
				);
			}
		}
		return $this->_cachedMessage;
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
	 * Gets the username.
	 *
	 * @return string User's username.
	 */
	public function GetUsername() {
		return WSAL_Utilities_UsersUtils::GetUsername( $this->GetMetaArray() );
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
}
