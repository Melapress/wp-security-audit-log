<?php
/**
 * Class: Query Model Class
 *
 * Query model is the class for all the query conditions.
 *
 * @package wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query Class.
 *
 * Query model is the class for all the query conditions.
 *
 * @package wsal
 */
class WSAL_Models_Query {

	/**
	 * Table Column.
	 *
	 * @var array
	 */
	protected $columns = array();

	/**
	 * Query Conditions.
	 *
	 * @var array
	 */
	protected $conditions = array();

	/**
	 * Order By.
	 *
	 * @var array
	 */
	protected $order_by = array();

	/**
	 * Offset.
	 *
	 * @var mixed
	 */
	protected $offset = null;

	/**
	 * Limit.
	 *
	 * @var mixed
	 */
	protected $limit = null;

	/**
	 * From.
	 *
	 * @var array
	 */
	protected $from = array();

	/**
	 * Meta Join.
	 *
	 * @var bool
	 */
	protected $meta_join = false;

	/**
	 * Search Condition.
	 *
	 * @var mixed
	 */
	protected $search_condition = null;

	/**
	 * Use Default Adapter.
	 *
	 * @var bool
	 */
	protected $use_default_adapter = false;

	/**
	 * Method: Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
	}

	/**
	 * Initialize a connector singleton.
	 *
	 * @return WSAL_Connector_ConnectorInterface
	 */
	public function get_connector() {
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
	 * Gets the adapter.
	 *
	 * @return WSAL_Adapters_QueryInterface
	 */
	public function get_adapter() {
		return $this->get_connector()->get_adapter( 'Query' );
	}

	/**
	 * Add a column.
	 *
	 * @param mixed $column - Column value.
	 * @return self
	 */
	public function add_column( $column ) {
		$this->columns[] = $column;
		return $this;
	}

	/**
	 * Clear all columns.
	 *
	 * @return self
	 */
	public function clear_columns() {
		$this->columns = array();
		return $this;
	}

	/**
	 * Get columns.
	 *
	 * @return array $columns
	 */
	public function get_columns() {
		return $this->columns;
	}

	/**
	 * Set all columns.
	 *
	 * @param array $columns - Columns values.
	 * @return self
	 */
	public function set_columns( $columns ) {
		$this->columns = $columns;
		return $this;
	}

	/**
	 * Add conditions.
	 *
	 * @param string $field - Condition field.
	 * @param mixed  $value - Condition value.
	 * @return self
	 */
	public function add_condition( $field, $value ) {
		$this->conditions[ $field ] = $value;
		return $this;
	}

	/**
	 * Add OR condition.
	 *
	 * @param array $add_conditions - Multi conditions.
	 */
	public function add_or_condition( $add_conditions ) {
		$this->conditions[] = $add_conditions;
	}

	/**
	 * Clear all conditions.
	 *
	 * @return self
	 */
	public function clear_conditions() {
		$this->conditions = array();
		return $this;
	}

	/**
	 * Get all conditions.
	 *
	 * @return array $conditions
	 */
	public function get_conditions() {
		return $this->conditions;
	}

	/**
	 * Add order by.
	 *
	 * @param string  $field         - Field name.
	 * @param boolean $is_descending - (Optional) Ascending/descending.
	 *
	 * @return self
	 */
	public function add_order_by( $field, $is_descending = false ) {
		$order                    = ( $is_descending ) ? 'DESC' : 'ASC';
		$this->order_by[ $field ] = $order;

		return $this;
	}

	/**
	 * Clear order by.
	 *
	 * @return self
	 */
	public function clear_order_by() {
		$this->order_by = array();
		return $this;
	}

	/**
	 * Get order by.
	 *
	 * @return array $orderBy
	 */
	public function get_order_by() {
		return $this->order_by;
	}

	/**
	 * Add from.
	 *
	 * @param string $from_data_set - Data set.
	 * @return self
	 */
	public function add_from( $from_data_set ) {
		$this->from[] = $from_data_set;
		return $this;
	}

	/**
	 * Reset from.
	 *
	 * @return self
	 */
	public function clear_from() {
		$this->from = array();
		return $this;
	}

	/**
	 * Get from.
	 *
	 * @return string $from data set
	 */
	public function get_from() {
		return $this->from;
	}

	/**
	 * Gets the value of limit.
	 *
	 * @return mixed
	 */
	public function get_limit() {
		return $this->limit;
	}

	/**
	 * Sets the value of limit.
	 *
	 * @param mixed $limit - The limit.
	 * @return self
	 */
	public function set_limit( $limit ) {
		$this->limit = $limit;
		return $this;
	}

	/**
	 * Gets the value of offset.
	 *
	 * @return mixed
	 */
	public function get_offset() {
		return $this->offset;
	}

	/**
	 * Sets the value of offset.
	 *
	 * @param mixed $offset - The offset.
	 * @return self
	 */
	public function set_offset( $offset ) {
		$this->offset = $offset;
		return $this;
	}

	/**
	 * Adds condition.
	 *
	 * @param mixed $value - Condition.
	 * @return self
	 */
	public function add_search_condition( $value ) {
		$this->search_condition = $value;
		return $this;
	}

	/**
	 * Gets condition.
	 *
	 * @return self
	 */
	public function get_search_condition() {
		return $this->search_condition;
	}

	/**
	 * Check meta join.
	 *
	 * @return boolean
	 */
	public function has_meta_join() {
		return $this->meta_join;
	}

	/**
	 * Adds meta join.
	 *
	 * @return self
	 */
	public function add_meta_join() {
		$this->meta_join = true;
		return $this;
	}

	/**
	 * Deprecated placeholder function.
	 *
	 * @return WSAL_Adapters_QueryInterface
	 * @see    WSAL_Models_Query::get_adapter()
	 *
	 * @deprecated 4.4.1 Replaced by function get_adapter.
	 */
	public function getAdapter() {
		return $this->get_adapter();
	}

	/**
	 * Deprecated placeholder function.
	 *
	 * @param string  $field         - Field name.
	 * @param boolean $is_descending - (Optional) Ascending/descending.
	 *
	 * @return self
	 * @see    WSAL_Models_Query::add_order_by()
	 *
	 * @deprecated 4.4.1 Replaced by function add_order_by.
	 */
	public function addOrderBy( $field, $is_descending = false ) {
		return $this->add_order_by( $field, $is_descending );
	}

	/**
	 * Deprecated placeholder function.
	 *
	 * @param mixed $limit - The limit.
	 *
	 * @return self
	 * @see    WSAL_Models_Query::set_limit()
	 *
	 * @deprecated 4.4.1 Replaced by function set_limit.
	 */
	public function setLimit( $limit ) {
		return $this->set_limit( $limit );
	}
}
