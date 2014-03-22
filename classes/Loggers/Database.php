<?php

class WSAL_Loggers_Database extends WSAL_AbstractLogger {
	public function Log($type, $data = array(), $date = null, $migrated = false) {
		// use today's date if not set up
		if(is_null($date))$date = time();
		
		// create new occurrence
		$occ = new WSAL_DB_Occurrence();
		$occ->is_migrated = $migrated;
		$occ->created_on = $date;
		$occ->alert_id = $type;
		$occ->Save();
		
		// set up meta data
		$occ->SetMeta($data);
	}
}