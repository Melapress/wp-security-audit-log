<?php

class WSAL_Adapters_MySQL_Meta extends WSAL_Adapters_MySQL_ActiveRecord implements WSAL_Adapters_MetaInterface {

	protected $_table = 'wsal_metadata';
	protected $_idkey = 'id';

    public function GetModel()
    {
        return new WSAL_Models_Meta();
    }
	
	public function __construct($conn) {
		parent::__construct($conn);
	}
	
	protected function GetTableOptions(){
		return parent::GetTableOptions() . ',' . PHP_EOL
				. '    KEY occurrence_name (occurrence_id,name)';
	}

	public function deleteByOccurenceIds($occurenceIds)
	{
		//do sql to delete by occurence id
		$sql = 'DELETE FROM ' . $this->GetTable() . ' WHERE occurrence_id IN (' . implode(',', $occurenceIds) . ')';
		//execute the sql.
	}

	public function loadByNameAndOccurenceId($metaName, $occurenceId)
	{
		//sql for it..
		//return new Meta($data)
	}

}
