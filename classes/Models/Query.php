<?php
/**
 * @package Wsal
 * Query Class.
 *
 * Query model is the class for all the query conditions.
 */
class WSAL_Models_Query
{
    protected $columns = array();
    protected $conditions = array();
    protected $orderBy = array();
    protected $offset = null;
    protected $limit = null;
    protected $from = array();
    protected $meta_join = false;
    protected $searchCondition = null;
    protected $useDefaultAdapter = false;

    public function __construct()
    {
    }

    /**
     * Initialize a connector singleton.
     * @return WSAL_Connector_ConnectorInterface
     */
    public function getConnector()
    {
        if (!empty($this->connector)) {
            return $this->connector;
        }
        if ($this->useDefaultAdapter) {
            $this->connector = WSAL_Connector_ConnectorFactory::GetDefaultConnector();
        } else {
            $this->connector = WSAL_Connector_ConnectorFactory::GetConnector();
        }
        return $this->connector;
    }

    /**
     * Gets the adapter.
     * @return WSAL_Adapters_MySQL_Query
     */
    public function getAdapter()
    {
        return $this->getConnector()->getAdapter('Query');
    }

    /**
     * Add a column.
     * @param mixed $column column value
     * @return self
     */
    public function addColumn($column)
    {
        $this->columns[] = $column;
        return $this;
    }

    /**
     * Clear all columns.
     * @return self
     */
    public function clearColumns()
    {
        $this->columns = array();
        return $this;
    }

    /**
     * Get columns.
     * @return array $columns
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Set all columns.
     * @param array $columns columns values
     * @return self
     */
    public function setColumns($columns)
    {
        $this->columns = $columns;
        return $this;
    }

    /**
     * Add conditions.
     * @param string $field condition field
     * @param mixed $value condition value
     * @return self
     */
    public function addCondition($field, $value)
    {
        $this->conditions[$field] = $value;
        return $this;
    }

    /**
     * Add OR condition.
     * @param array $aConditions multi conditions
     */
    public function addORCondition($aConditions)
    {
        $this->conditions[] = $aConditions;
    }

    /**
     * Clear all conditions.
     * @return self
     */
    public function clearConditions()
    {
        $this->conditions = array();
        return $this;
    }

    /**
     * Get all conditions.
     * @return array $conditions
     */
    public function getConditions()
    {
        return $this->conditions;
    }

    /**
     * Add order by.
     * @param string $field field name
     * @param boolean $isDescending (Optional) ascending/descending
     * @return self
     */
    public function addOrderBy($field, $isDescending = false)
    {
        $order = ($isDescending) ? 'DESC' : 'ASC';
        $this->orderBy[$field] = $order;
        return $this;
    }

    /**
     * Clear order by.
     * @return self
     */
    public function clearOrderBy()
    {
        $this->orderBy = array();
        return $this;
    }

    /**
     * Get order by.
     * @return array $orderBy
     */
    public function getOrderBy()
    {
        return $this->orderBy;
    }

    /**
     * Add from.
     * @param string $fromDataSet data set
     * @return self
     */
    public function addFrom($fromDataSet)
    {
        $this->from[] = $fromDataSet;
        return $this;
    }

    /**
     * Reset from.
     * @return self
     */
    public function clearFrom()
    {
        $this->from = array();
        return $this;
    }

    /**
     * Get from.
     * @return string $from data set
     */
    public function getFrom()
    {
        return $this->from;
    }

    /**
     * Gets the value of limit.
     * @return mixed
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * Sets the value of limit.
     * @param mixed $limit the limit
     * @return self
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Gets the value of offset.
     * @return mixed
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * Sets the value of offset.
     * @param mixed $offset the offset
     * @return self
     */
    public function setOffset($offset)
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Adds condition.
     * @param mixed $value condition
     * @return self
     */
    public function addSearchCondition($value)
    {
        $this->searchCondition = $value;
        return $this;
    }

    /**
     * Gets condition.
     * @return self
     */
    public function getSearchCondition()
    {
        return $this->searchCondition;
    }

    /**
     * Check meta join.
     * @return boolean
     */
    public function hasMetaJoin()
    {
        return $this->meta_join;
    }

    /**
     * Adds meta join.
     * @return self
     */
    public function addMetaJoin()
    {
        $this->meta_join = true;
        return $this;
    }
}
