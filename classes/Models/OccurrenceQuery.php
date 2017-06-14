<?php
/**
 * @package Wsal
 *
 * OccurrenceQuery model adds or clears arguments in the Query model.
 */
class WSAL_Models_OccurrenceQuery extends WSAL_Models_Query
{
    protected $arguments = array();

    /**
     * Sets arguments.
     * @param string $field name field
     * @param mixed $value value
     * @return self
     */
    public function addArgument($field, $value)
    {
        $this->arguments[$field] = $value;
        return $this;
    }

    /**
     * Resets arguments.
     * @return self
     */
    public function clearArguments()
    {
        $this->arguments = array();
        return $this;
    }

    public function __construct()
    {
        parent::__construct();

        //TO DO: Consider if Get Table is the right method to call given that this is mysql specific
        $this->addFrom(
            $this->getConnector()->getAdapter("Occurrence")->GetTable()
        );
    }
}
