<?php
require_once('ConnectorFactory.php');
/**
 * @todo Add group-by support
 */
class WSAL_Models_Query {
	/**
	 * @var string
	 */

	protected $connector;

	public $adapterName = "Query";
	
	/**
	 * @param string $ar_class Name of class that extends ActiveRecord class.
	 */
	public function __construct() {
		$this->connector = $this->getConnector();
	}
	
	protected function getConnector()
	{
		return WSAL_Models_ConnectorFactory::GetConnector();
	}

	protected function getAdapter()
	{
		return $this->connector->GetAdapter($this->adapterName);
	}

	public function Execute() {
		return $this->getAdapter()->Execute();
	}

	public function count() {
		return $this->getAdapter()->count();
	}

	public function Delete() {
		$this->getAdapter()->Delete();
	}

	public function Where($cond, $args) {
		$this->getAdapter()->Where($cond, $args);
	}
}
