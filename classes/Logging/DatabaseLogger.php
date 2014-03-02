<?php

class WSAL_Logging_DatabaseLogger extends WSAL_Logging_AbstractLogger {
	public function Log($type, $message, $data = array()) {
		// load or create lg entry
		$log = new WSAL_DB_Log();
		if(!$log->IsLoaded()){
			$log->type = $type;
			$log->message = $message;
			$log->Save();
		}
		// create new occurrence
		$occ = new WSAL_DB_Occurrence();
		$occ->created_on = time();
		$occ->log_id = $log->id;
		$occ->Save();
		// set up meta data
		$occ->SetMeta($data);
	}
}