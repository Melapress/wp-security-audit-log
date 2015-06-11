<?php
require_once(__DIR__ . '/../Connector/ConnectorFactory.php');

//TO DO: Get rid of OccurenceQuery and Query in favour of models
class WSAL_Models_Query {
	public $adapterName = "Query";
	
	protected function getConnector()
	{
		return WSAL_Connector_ConnectorFactory::GetConnector();
	}

	protected function getAdapter()
	{
		return $this->getConnector()->getAdapter($this->adapterName);
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
