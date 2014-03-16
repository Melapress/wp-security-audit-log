<?php

class WSAL_Loggers_Database extends WSAL_AbstractLogger {
	public function Log($type, $code, $message, $data = array(), $date = null) {
		// use today's date if not set up
		if(is_null($date))$date = time();
		
		// attempt loading existing log entry
		$log = new WSAL_DB_Log();
		$log->Load(
			'code = %d and type = %d and message = %s',
			array($code, $type, $message)
		);
		
		// if log entry was not found, create it now
		if(!$log->IsLoaded()){
			$log->code = $code;
			$log->type = $type;
			$log->message = $message;
			$log->Save();
		}
		
		// create new occurrence
		$occ = new WSAL_DB_Occurrence();
		$occ->created_on = $date;
		$occ->log_id = $log->id;
		$occ->Save();
		
		// set up meta data
		$occ->SetMeta($data);
	}
}