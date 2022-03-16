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
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
class WSAL_Models_Occurrence extends WSAL_Models_ActiveRecord {

	/**
	 * List of migrated metadata fields.
	 *
	 * @var string[]
	 */
	public static $migrated_meta = array(
		'ClientIP'         => 'client_ip',
		'Severity'         => 'severity',
		'Object'           => 'object',
		'EventType'        => 'event_type',
		'UserAgent'        => 'user_agent',
		'CurrentUserRoles' => 'user_roles',
		'Username'         => 'username',
		'CurrentUserID'    => 'user_id',
		'SessionID'        => 'session_id',
		'PostStatus'       => 'post_status',
		'PostType'         => 'post_type',
		'PostID'           => 'post_id',
	);

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
	 * Client IP address.
	 *
	 * @var string
	 * @since 4.4.0
	 */
	public $client_ip = '';

	/**
	 * Severity.
	 *
	 * @var int
	 * @since 4.4.0
	 */
	public $severity = '';

	/**
	 * Event object.
	 *
	 * @var string
	 * @since 4.4.0
	 */
	public $object = '';

	/**
	 * Event type.
	 *
	 * @var string
	 * @since 4.4.0
	 */
	public $event_type = '';

	/**
	 * User agent string.
	 *
	 * @var string
	 * @since 4.4.0
	 */
	public $user_agent = '';

	/**
	 * Comma separated user roles of the user belonging to the event.
	 *
	 * @var string
	 * @since 4.4.0
	 */
	public $user_roles = '';

	/**
	 * Username of the user belonging to the event.
	 *
	 * @var string
	 * @since 4.4.0
	 */
	public $username = null;

	/**
	 * User ID of the user belonging to the event.
	 *
	 * @var int
	 * @since 4.4.0
	 */
	public $user_id = null;

	/**
	 * Session ID.
	 *
	 * @var string
	 * @since 4.4.0
	 */
	public $session_id = '';

	/**
	 * Post status.
	 *
	 * @var string
	 * @since 4.4.0
	 */
	public $post_status = '';

	/**
	 * Post status.
	 *
	 * @var string
	 * @since 4.4.0
	 */
	public $post_type = '';

	/**
	 * Post ID.
	 *
	 * @var int
	 * @since 4.4.0
	 */
	public $post_id = 0;

	/**
	 * Model Name.
	 *
	 * @var string
	 */
	protected $adapter_name = 'Occurrence';

	/**
	 * Cached message.
	 *
	 * @var string
	 */
	public $_cached_message;

	/**
	 * Returns the alert related to this occurrence.
	 *
	 * @return WSAL_Alert
	 * @see WSAL_AlertManager::get_alert()
	 */
	public function get_alert() {
		return WpSecurityAuditLog::get_instance()->alerts->get_alert(
			$this->alert_id,
			(object) array(
				'mesg' => esc_html__( 'Alert message not found.', 'wp-security-audit-log' ),
				'desc' => esc_html__( 'Alert description not found.', 'wp-security-audit-log' ),
			)
		);
	}

	/**
	 * Returns the value of a meta item.
	 *
	 * @param string $name    - Name of meta item.
	 * @param mixed  $default - Default value returned when meta does not exist.
	 *
	 * @return mixed The value, if meta item does not exist $default returned.
	 * @see WSAL_Adapters_MySQL_Occurrence::get_named_meta()
	 */
	public function get_meta_value( $name, $default = array() ) {
		$result = $default;

		// Check if the meta is part of the occurrences table.
		if ( in_array( $name, array_keys( self::$migrated_meta ), true ) ) {
			$property_name = self::$migrated_meta[ $name ];
			if ( property_exists( $this, $property_name ) ) {
				$result = $this->$property_name;
			}
		} else {
			// Get meta adapter.
			$meta = $this->get_adapter()->get_named_meta( $this, $name );
			if ( is_null( $meta ) || ! array_key_exists( 'value', $meta ) ) {
				return $default;
			}

			$result = $meta['value'];
		}

		$result = maybe_unserialize( $result );
		if ( 'CurrentUserRoles' === $name && is_string( $result ) ) {
			$result = preg_replace( '/[\[\]"]/', '', $result );
			$result = explode( ',', $result );
		}

		return $result;
	}

	/**
	 * Sets the value of a meta item (creates or updates meta item).
	 *
	 * @param string $name  - Meta name.
	 * @param mixed  $value - Meta value.
	 */
	public function set_meta_value( $name, $value ) {
		// check explicitly for `0` string values.
		if ( '0' === $value || ! empty( $value ) ) {
			// Check if the meta is part of the occurrences table.
			if ( in_array( $name, array_keys( self::$migrated_meta ), true ) ) {
				$property_name = self::$migrated_meta[ $name ];
				if ( property_exists( $this, $property_name ) ) {
					if ( 'CurrentUserRoles' === $name ) {
						$value = maybe_unserialize( $value );
						if ( is_array( $value ) && ! empty( $value ) ) {
							$this->$property_name = implode( ',', $value );
						}
					} else {
						$this->$property_name = $value;
					}
				}
			} else {
				// Get meta adapter.
				$model                = new WSAL_Models_Meta();
				$model->occurrence_id = $this->get_id();
				$model->name          = $name;
				$model->value         = maybe_serialize( $value );
				$model->save_meta();
			}
		}
	}

	/**
	 * Update Metadata of this occurrence by name.
	 *
	 * @param string $name  - Meta name.
	 * @param mixed  $value - Meta value.
	 *
	 * @see WSAL_Models_Meta::update_by_name_and_occurrence_id()
	 */
	public function update_meta_value( $name, $value ) {
		$model = new WSAL_Models_Meta();
		$model->update_by_name_and_occurrence_id( $name, $value, $this->get_id() );
	}

	/**
	 * Returns a key-value pair of metadata.
	 *
	 * @return array
	 * @see WSAL_Adapters_MySQL_Occurrence::get_multi_meta()
	 */
	public function get_meta_array() {
		$result = array();
		$metas  = $this->get_adapter()->get_multi_meta( $this );
		foreach ( $metas as $meta ) {
			$result[ $meta->name ] = maybe_unserialize( $meta->value );
		}

		foreach ( self::$migrated_meta as $meta_key => $column_name ) {
			$result[ $meta_key ] = $this->$column_name;
		}

		return $result;
	}

	/**
	 * Creates or updates all metadata passed as an array of meta-key/meta-value pairs.
	 *
	 * @param array $data - New meta data.
	 */
	public function SetMeta( $data ) {
		foreach ( (array) $data as $key => $val ) {
			$this->set_meta_value( $key, $val );
		}

		// The occurrence object itself needs to be saved again as some metadata is stored as its properties.
		$this->save();
	}

	/**
	 * Gets alert message.
	 *
	 * @param array  $meta    - Occurrence meta array.
	 * @param string $context Message context.
	 *
	 * @return string Full-formatted message.
	 * @see WSAL_Alert::get_message()
	 */
	public function get_message( $meta = null, $context = false ) {
		if ( ! isset( $this->_cached_message ) ) {
			// Message caching.
			if ( ! $this->_cached_message ) {
				$this->_cached_message = $this->get_alert()->mesg;
			}
			// Fill variables in message.
			$meta_array   = null === $meta ? $this->get_meta_array() : $meta;
			$alert_object = $this->get_alert();
			if ( null !== $alert_object && method_exists( $alert_object, 'get_message' ) ) {
				$this->_cached_message = $alert_object->get_message( $meta_array, $this->_cached_message, $this->get_id(), $context );
			} else {
				// Filter to allow items to be added elsewhere.
				$addon_event_codes = apply_filters( 'wsal_addon_event_codes', $addon_event_codes );

				$installer_nonce = wp_create_nonce( 'wsal-install-addon' );
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
				$this->_cached_message = isset( $cached_message ) ? $cached_message : sprintf(
					/* Translators: 1: html that opens a link, 2: html that closes a link. */
					__( 'This type of activity / change is no longer monitored. You can create your own custom event IDs to keep a log of such change. Read more about custom events %1$shere%2$s.', 'wp-security-audit-log' ),
					'<a href="https://wpactivitylog.com/support/kb/create-custom-events-wordpress-activity-log/" rel="noopener noreferrer" target="_blank">',
					'</a>'
				);
			}
		}
		return $this->_cached_message;
	}

	/**
	 * Delete occurrence as well as associated meta data.
	 *
	 * @see WSAL_Adapters_ActiveRecordInterface::delete()
	 * @return boolean True on success, false on failure.
	 *
	 * @phpcs:disable Generic.Commenting.DocComment.MissingShort
	 */
	public function delete() {
		/** @var WSAL_Adapters_MySQL_Occurrence $adapter */
		$adapter = $this->get_adapter();
		foreach ( $adapter->get_multi_meta() as $meta ) {
			$meta->delete();
		}
		return parent::delete();
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
	 * @return string IP address of request.
	 */
	public function get_source_ip() {
		return $this->get_meta_value( 'ClientIP', array() );
	}

	/**
	 * Gets if there are other IPs.
	 *
	 * @return string IP address of request (from proxies etc).
	 */
	public function get_other_ips() {
		$result = array();
		$data   = (array) $this->get_meta_value( 'OtherIPs', array() );
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
	 * @return array Array of user roles.
	 */
	public function get_user_roles() {
		return $this->get_meta_value( 'CurrentUserRoles', array() );
	}

	/**
	 * Sets the user roles.
	 *
	 * @param string $roles Array of user roles.
	 */
	public function set_user_roles( $roles ) {
		$this->user_roles = is_array( $roles ) ? implode( ',', $roles ) : $roles;
	}

	/**
	 * Gets the username.
	 *
	 * @return string User's username.
	 */
	public function get_username() {
		return WSAL_Utilities_UsersUtils::get_username( $this->get_meta_array() );
	}

	/**
	 * Method: Get Microtime.
	 *
	 * @return float - Number of seconds (and milliseconds as fraction) since unix Day 0.
	 * @todo This needs some caching.
	 */
	protected function get_microtime() {
		return microtime( true );
	}

	/**
	 * Finds occurrences of the same type by IP and Username within specified time frame.
	 *
	 * @param array $args - Query args.
	 * @return WSAL_Models_Occurrence[]
	 */
	public function check_known_users( $args = array() ) {
		return $this->get_adapter()->check_known_users( $args );
	}

	/**
	 * Finds occurrences of the same type by IP within specified time frame.
	 *
	 * @param array $args - Query args.
	 * @return WSAL_Models_Occurrence[]
	 */
	public function check_unknown_users( $args = array() ) {
		return $this->get_adapter()->check_unknown_users( $args );
	}

	/**
	 * Finds occurrences of the alert 1003.
	 *
	 * @param array $args - Query args.
	 * @return WSAL_Models_Occurrence[]
	 */
	public function check_alert_1003( $args = array() ) {
		return $this->get_adapter()->check_alert_1003( $args );
	}

	/**
	 * Gets occurrence by Post_id
	 *
	 * @param integer $post_id - Post ID.
	 *
	 * @return WSAL_Models_Occurrence[]
	 * @see WSAL_Adapters_MySQL_Occurrence::get_by_post_id()
	 */
	public function get_by_post_id( $post_id ) {
		return $this->get_adapter()->get_by_post_id( $post_id );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Extends the default mechanism for loading data to handle the migrated meta fields in version 4.4.0.
	 *
	 * @since 4.4.0
	 */
	public function load_data( $data ) {
		$copy = get_class( $this );
		$copy = new $copy();
		foreach ( (array) $data as $key => $val ) {
			if ( ! is_null( $val ) && in_array( $key, array( 'user_id', 'username' ), true ) ) {
				// Username and user_id cannot have the default value set because some database queries rely on having
				// null values in the database.
				if ( 'user_id' === $key ) {
					$this->user_id = intval( $val );
				} elseif ( 'username' === $key ) {
					$this->username = (string) $val;
				}
			} elseif ( 'roles' === $key ) {
				$this->set_user_roles( $val );
			} elseif ( isset( $copy->$key ) ) {
				// Default type casting is applied to the rest of the fields.
				$this->$key = $this->cast_to_correct_type( $copy, $key, $val );
			}
		}

		return $this;
	}

	/**
	 * Deprecated placeholder function.
	 *
	 * @return array
	 *
	 * @deprecated 4.4.1 Replaced by function get_meta_array.
	 * @see        WSAL_Models_Occurrence::get_meta_array()
	 */
	public function GetMetaArray() {
		return $this->get_meta_array();
	}
}
