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
	protected $adapter_name = null;

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
	protected $state = self::STATE_UNKNOWN;

	/**
	 * Cache.
	 *
	 * @var array
	 */
	protected static $cache = array();

	/**
	 * Returns this records' fields.
	 *
	 * @return array
	 */
	public function get_fields() {
		if ( ! isset( $this->_column_cache ) ) {
			$this->_column_cache = array();
			foreach ( array_keys( get_object_vars( $this ) ) as $col ) {
				if ( trim( $col ) && '_' != $col[0] ) { // phpcs:ignore
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
	public function set_id( $id ) {
		$this->id = $id;
	}

	/**
	 * Gets the id.
	 *
	 * @return integer $id.
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Method: Constructor.
	 *
	 * @param array $data - Active data.
	 * @throws Exception - Requires adapterName.
	 */
	public function __construct( $data = null ) {
		if ( ! $this->adapter_name ) {
			throw new Exception( 'Class "' . __CLASS__ . '" requires "adapterName" to be set.' );
		}
		if ( ! is_null( $data ) ) {
			$this->load_data( $data );
			$this->state = self::STATE_LOADED;
		}
	}

	/**
	 * Gets the connector.
	 *
	 * @return WSAL_Connector_ConnectorInterface
	 */
	protected function get_connector() {
		if ( ! empty( $this->connector ) ) {
			return $this->connector;
		}

		if ( $this->use_default_adapter ) {
			$this->connector = WSAL_Connector_ConnectorFactory::get_default_connector();
		} else {
			$this->connector = WSAL_Connector_ConnectorFactory::get_connector();
		}
		return $this->connector;
	}

	/**
	 * Gets an adapter for the specified model
	 * based on the adapter name.
	 *
	 * @return WSAL_Adapters_ActiveRecordInterface
	 */
	public function get_adapter() {
		// Use forcefully set adapter if set.
		if ( ! empty( $this->adapter ) ) {
			return $this->adapter;
		}

		// Create adapter using the connector returned from get_connector method.
		return $this->get_connector()->get_adapter( $this->adapter_name );
	}

	/**
	 * Allows the database adapter to be set from the outside. This is useful during data migrations.
	 *
	 * @param WSAL_Adapters_ActiveRecordInterface $adapter Active record adapter.
	 *
	 * @since 4.4.0
	 */
	public function set_adapter( $adapter ) {
		$this->adapter = $adapter;
	}

	/**
	 * Load record from DB.
	 *
	 * @param string $cond (Optional) Load condition.
	 * @param array  $args (Optional) Load condition arguments.
	 *
	 * @see WSAL_Adapters_MySQL_ActiveRecord::load()
	 */
	public function load( $cond = '%d', $args = array( 1 ) ) {
		$this->state = self::STATE_UNKNOWN;

		$data = $this->get_adapter()->load( $cond, $args );
		if ( ! is_null( $data ) ) {
			$this->load_data( $data );
			$this->state = self::STATE_LOADED;
		}
	}

	/**
	 * Casts given value to a correct type based on the type of property (identified by the $key) in the $copy object.
	 * This is to allow automatic type casting instead of handling each database column individually.
	 *
	 * @param object $copy Model object copy to populate.
	 * @param string $key  Column key.
	 * @param mixed  $val  Value.
	 *
	 * @return mixed
	 * @throws Exception
	 */
	protected function cast_to_correct_type( $copy, $key, $val ) {
		switch ( true ) {
			case is_string( $copy->$key ):
			case WSAL_Utilities_RequestUtils::is_ip_address( $val ):
				return (string) $val;
			case is_array( $copy->$key ):
			case is_object( $copy->$key ):
				$json_decoded_val = WSAL_Helpers_DataHelper::json_decode( $val );
				return is_null( $json_decoded_val ) ? $val : $json_decoded_val;
			case is_int( $copy->$key ):
				return (int) $val;
			case is_float( $copy->$key ):
				return (float) $val;
			case is_bool( $copy->$key ):
				return (bool) $val;
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
	public function load_data( $data ) {
		$copy = get_class( $this );
		$copy = new $copy();
		foreach ( (array) $data as $key => $val ) {
			if ( isset( $copy->$key ) ) {
				$this->$key = $this->cast_to_correct_type( $copy, $key, $val );
			}
		}
		return $this;
	}

	/**
	 * Save this active record
	 *
	 * @return integer|boolean Either the number of modified/inserted rows or false on failure.
	 * @see WSAL_Adapters_MySQL_ActiveRecord::save()
	 */
	public function save() {
		$this->state = self::STATE_UNKNOWN;

		// Use today's date if not set up.
		if ( is_null( $this->created_on ) ) {
			$this->created_on = $this->get_microtime();
		}

		$update_id = $this->get_id();
		$result    = $this->get_adapter()->save( $this );

		if ( false !== $result ) {
			$this->state = ( ! empty( $update_id ) ) ? self::STATE_UPDATED : self::STATE_CREATED;
		}
		return $result;
	}

	/**
	 * Deletes this active record.
	 *
	 * @return int|boolean Either the amount of deleted rows or False on error.
	 * @see WSAL_Adapters_MySQL_ActiveRecord::delete()
	 */
	public function delete() {
		$this->state = self::STATE_UNKNOWN;
		$result      = $this->get_adapter()->delete( $this );
		if ( false !== $result ) {
			$this->state = self::STATE_DELETED;
		}

		return $result;
	}

	/**
	 * Count records that matching a condition.
	 *
	 * @param string $cond - Condition.
	 * @param array  $args - Arguments.
	 *
	 * @return int count
	 * @see WSAL_Adapters_MySQL_ActiveRecord::count()
	 */
	public function count( $cond = '%d', $args = array( 1 ) ) {
		return (int) $this->get_adapter()->count( $cond, $args );
	}

	/**
	 * Check state loaded.
	 *
	 * @return bool
	 */
	public function is_loaded() {
		return self::STATE_LOADED === $this->state;
	}

	/**
	 * Check state saved.
	 *
	 * @return bool
	 */
	public function is_saved() {
		return self::STATE_CREATED === $this->state
			|| self::STATE_UPDATED === $this->state;
	}

	/**
	 * Check state created.
	 *
	 * @return bool
	 */
	public function is_created() {
		return self::STATE_CREATED === $this->state;
	}

	/**
	 * Check state updated.
	 *
	 * @return bool
	 */
	public function is_updated() {
		return self::STATE_UPDATED === $this->state;
	}

	/**
	 * Check state deleted.
	 *
	 * @return bool
	 */
	public function is_deleted() {
		return self::STATE_DELETED === $this->state;
	}

	/**
	 * Check if the Record structure is created.
	 *
	 * @return bool
	 * @see WSAL_Adapters_MySQL_ActiveRecord::is_installed()
	 */
	public function is_installed() {
		return $this->get_adapter()->is_installed();
	}

	/**
	 * Install the Record structure.
	 *
	 * @see WSAL_Adapters_MySQL_ActiveRecord::install()
	 */
	public function install() {
		return $this->get_adapter()->install();
	}

	/**
	 * Load ActiveRecord from DB or cache.
	 *
	 * @param string $target ActiveRecord class name.
	 * @param string $query Load condition.
	 * @param array  $args Arguments used in condition.
	 * @return WSAL_Models_ActiveRecord
	 */
	protected static function cache_load( $target, $query, $args ) {
		$index = $target . '::' . vsprintf( $query, $args );
		if ( ! isset( self::$cache[ $index ] ) ) {
			self::$cache[ $index ] = new $target();
			self::$cache[ $index ]->Load( $query, $args );
		}
		return self::$cache[ $index ];
	}

	/**
	 * Remove ActiveRecord cache.
	 *
	 * @param string $target ActiveRecord class name.
	 * @param string $query Load condition.
	 * @param array  $args Arguments used in condition.
	 */
	protected static function cache_remove( $target, $query, $args ) {
		$index = $target . '::' . sprintf( $query, $args );
		if ( ! isset( self::$cache[ $index ] ) ) {
			unset( self::$cache[ $index ] );
		}
	}

	/**
	 * Clear the cache.
	 */
	protected static function cache_clear() {
		self::$cache = array();
	}
}
