<?php

class WSAL_MySQL_MetaAdapter extends WSAL_MySQL_ActiveRecordAdapter implements WSAL_MetaInterface {

	protected $_table = 'wsal_metadata';
	protected $_idkey = 'id';
	
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
