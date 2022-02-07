<?php
/**
 * Class: Abstract Active Record
 *
 * Abstract ActiveRecord model is the generic model for any kind
 * of adapter.
 *
 * @package wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract ActiveRecord model is the generic model for any kind
 * of adapter.
 *
 * @package wsal
 */
abstract class WSAL_Models_ActiveRecord {

	const STATE_UNKNOWN = 'unknown';
	const STATE_CREATED = 'created';
	const STATE_UPDATED = 'updated';
	const STATE_DELETED = 'deleted';
	const STATE_LOADED  = 'loaded';

	/**
	 * Data connector
	 *
	 * @var WSAL_Connector_ConnectorFactory
	 */
	protected $connector;

	/**
	 * Record ID.
	 *
	 * @var boolean
	 */
	protected $id = false;

	/**
	 * This property is used in conjunction with its setter method to force an adapter used by this record. It completely
	 * bypasses the default way of creating the adapter using its name and a connector returned by function getConnector.
	 *
	 * @var WSAL_Adapters_ActiveRecordInterface
	 * @since 4.4.0
	 */
	protected $adapter = null;

	/**
	 * Adapter Name.
	 *
	 * @var null
	 */
	protected $adapterName = null;

	/**
	 * Use Default Adapter.
	 *
	 * @var boolean
	 */
	protected $use_default_adapter = false;

	/**
	 * Record State.
	 *
	 * @var string
	 */
	protected $_state = self::STATE_UNKNOWN;

	/**
	 * Cache.
	 *
	 * @var array
	 */
	protected static $_cache = array();

	/**
	 * Returns this records' fields.
	 *
	 * @return array
	 */
	public function GetFields() {
		if ( ! isset( $this->_column_cache ) ) {
			$this->_column_cache = array();
			foreach ( array_keys( get_object_vars( $this ) ) as $col ) {
				if ( trim( $col ) && '_' != $col[0] ) {
					$this->_column_cache[] = $col;
				}
			}
		}
		return $this->_column_cache;
	}

	/**
	 * Sets the id.
	 *
	 * @param integer $id - ID.
	 */
	public function setId( $id ) {
		$this->id = $id;
	}

	/**
	 * Gets the id.
	 *
	 * @return integer $id.
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Method: Constructor.
	 *
	 * @param array $data - Active data.
	 * @throws Exception - Requires adapterName.
	 */
	public function __construct( $data = null ) {
		if ( ! $this->adapterName ) {
			throw new Exception( 'Class "' . __CLASS__ . '" requires "adapterName" to be set.' );
		}
		if ( ! is_null( $data ) ) {
			$this->LoadData( $data );
			$this->_state = self::STATE_LOADED;
		}
	}

	/**
	 * Gets the connector.
	 *
	 * @return WSAL_Connector_ConnectorInterface
	 */
	protected function getConnector() {
		if ( ! empty( $this->connector ) ) {
			return $this->connector;
		}

		if ( $this->use_default_adapter ) {
			$this->connector = WSAL_Connector_ConnectorFactory::GetDefaultConnector();
		} else {
			$this->connector = WSAL_Connector_ConnectorFactory::GetConnector();
		}
		return $this->connector;
	}

	/**
	 * Gets an adapter for the specified model
	 * based on the adapter name.
	 *
	 * @return WSAL_Adapters_ActiveRecordInterface
	 */
	public function getAdapter() {
		//  use forcefully set adapter if set
		if ( ! empty( $this->adapter ) ) {
			return $this->adapter;
		}

		//  create adapter using the connector returned from getConnector method
		return $this->getConnector()->getAdapter( $this->adapterName );
	}

	/**
	 * Allows the database adapter to be set from the outside. This is useful during data migrations.
	 *
	 * @param WSAL_Adapters_ActiveRecordInterface $adapter
	 *
	 * @since 4.4.0
	 */
	public function setAdapter( $adapter ) {
		$this->adapter = $adapter;
	}

	/**
	 * Load record from DB.
	 *
	 * @see WSAL_Adapters_MySQL_ActiveRecord::Load()
	 * @param string $cond (Optional) Load condition.
	 * @param array  $args (Optional) Load condition arguments.
	 */
	public function Load( $cond = '%d', $args = array( 1 ) ) {
		$this->_state = self::STATE_UNKNOWN;

		$data = $this->getAdapter()->Load( $cond, $args );
		if ( ! is_null( $data ) ) {
			$this->LoadData( $data );
			$this->_state = self::STATE_LOADED;
		}
	}

	/**
	 * Casts given value to a correct type based on the type of property (identified by the $key) in the $copy object.
	 * This is to allow automatic type casting instead of handling each database column individually.
	 *
	 * @param object $copy
	 * @param string $key
	 * @param mixed $val
	 *
	 * @return mixed
	 * @throws Exception
	 */
	protected function cast_to_correct_type( $copy, $key, $val ){
		switch ( true ) {
			case is_string( $copy->$key ):
			case WSAL_Utilities_RequestUtils::is_ip_address( $val ):
				return (string) $val;
			case is_array( $copy->$key ):
			case is_object( $copy->$key ):
				$json_decoded_val = WSAL_Helpers_DataHelper::JsonDecode( $val );
				return is_null( $json_decoded_val ) ? $val : $json_decoded_val;
			case is_int( $copy->$key ):
				return (int) $val;
			case is_float( $copy->$key ):
				return (float) $val;
			case is_bool( $copy->$key ):
				return  (bool) $val;
			default:
				throw new Exception( 'Unsupported type "' . gettype( $copy->$key ) . '"' );
		}
	}

	/**
	 * Load object data from variable.
	 *
	 * @param array|object $data Data array or object.
	 * @throws Exception - Unsupported type.
	 */
	public function LoadData( $data ) {
		$copy = get_class( $this );
		$copy = new $copy();
		foreach ( (array) $data as $key => $val ) {
			if ( isset( $copy->$key ) ) {
				$this->$key = $this->cast_to_correct_type($copy, $key, $val);
			}
		}
		return $this;
	}

	/**
	 * Save this active record
	 *
	 * @see WSAL_Adapters_MySQL_ActiveRecord::Save()
	 * @return integer|boolean Either the number of modified/inserted rows or false on failure.
	 */
	public function Save() {
		$this->_state = self::STATE_UNKNOWN;

		// Use today's date if not set up.
		if ( is_null( $this->created_on ) ) {
			$this->created_on = $this->GetMicrotime();
		}
		$update_id = $this->getId();
		$result = $this->getAdapter()->Save( $this );

		if ( false !== $result ) {
			$this->_state = ( ! empty( $update_id )) ? self::STATE_UPDATED : self::STATE_CREATED;
		}
		return $result;
	}

	/**
	 * Deletes this active record.
	 *
	 * @see WSAL_Adapters_MySQL_ActiveRecord::Delete()
	 * @return int|boolean Either the amount of deleted rows or False on error.
	 */
	public function Delete() {
		$this->_state = self::STATE_UNKNOWN;
		$result = $this->getAdapter()->Delete( $this );
		if ( false !== $result ) {
			$this->_state = self::STATE_DELETED;
		}
		return $result;
	}

	/**
	 * Count records that matching a condition.
	 *
	 * @param string $cond - Condition.
	 * @param array  $args - Arguments.
	 * @see WSAL_Adapters_MySQL_ActiveRecord::Count()
	 * @return int count
	 */
	public function Count( $cond = '%d', $args = array( 1 ) ) {
		return (int) $this->getAdapter()->Count( $cond, $args );
	}

	/**
	 * Check state loaded.
	 *
	 * @return bool
	 */
	public function IsLoaded() {
		return self::STATE_LOADED === $this->_state;
	}

	/**
	 * Check state saved.
	 *
	 * @return bool
	 */
	public function IsSaved() {
		return self::STATE_CREATED === $this->_state
			|| self::STATE_UPDATED === $this->_state;
	}

	/**
	 * Check state created.
	 *
	 * @return bool
	 */
	public function IsCreated() {
		return self::STATE_CREATED === $this->_state;
	}

	/**
	 * Check state updated.
	 *
	 * @return bool
	 */
	public function IsUpdated() {
		return self::STATE_UPDATED === $this->_state;
	}

	/**
	 * Check state deleted.
	 *
	 * @return bool
	 */
	public function IsDeleted() {
		return self::STATE_DELETED === $this->_state;
	}

	/**
	 * Check if the Record structure is created.
	 *
	 * @see WSAL_Adapters_MySQL_ActiveRecord::IsInstalled()
	 * @return bool
	 */
	public function IsInstalled() {
		return $this->getAdapter()->IsInstalled();
	}

	/**
	 * Install the Record structure.
	 *
	 * @see WSAL_Adapters_MySQL_ActiveRecord::Install()
	 */
	public function Install() {
		return $this->getAdapter()->Install();
	}

	/**
	 * Load ActiveRecord from DB or cache.
	 *
	 * @param string $target ActiveRecord class name.
	 * @param string $query Load condition.
	 * @param array  $args Arguments used in condition.
	 * @return WSAL_Models_ActiveRecord
	 */
	protected static function CacheLoad( $target, $query, $args ) {
		$index = $target . '::' . vsprintf( $query, $args );
		if ( ! isset( self::$_cache[ $index ] ) ) {
			self::$_cache[ $index ] = new $target();
			self::$_cache[ $index ]->Load( $query, $args );
		}
		return self::$_cache[ $index ];
	}

	/**
	 * Remove ActiveRecord cache.
	 *
	 * @param string $target ActiveRecord class name.
	 * @param string $query Load condition.
	 * @param array  $args Arguments used in condition.
	 */
	protected static function CacheRemove( $target, $query, $args ) {
		$index = $target . '::' . sprintf( $query, $args );
		if ( ! isset( self::$_cache[ $index ] ) ) {
			unset( self::$_cache[ $index ] );
		}
	}

	/**
	 * Clear the cache.
	 */
	protected static function CacheClear() {
		self::$_cache = array();
	}
}
