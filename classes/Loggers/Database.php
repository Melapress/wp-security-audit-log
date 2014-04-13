<?php

class WSAL_Loggers_Database extends WSAL_AbstractLogger {
	public function Log($type, $data = array(), $date = null, $siteid = null, $migrated = false) {
		// use today's date if not set up
		if(is_null($date))$date = time();
		
		// create new occurrence
		$occ = new WSAL_DB_Occurrence();
		$occ->is_migrated = $migrated;
		$occ->created_on = $date;
		$occ->alert_id = $type;
		$occ->site_id = !is_null($siteid) ? $siteid
			: (function_exists('get_current_blog_id') ? get_current_blog_id() : 0);
		$occ->Save();
		
		// set up meta data
		$occ->SetMeta($data);
	}
}