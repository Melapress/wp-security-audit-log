<?php

class WSAL_Loggers_Database extends WSAL_AbstractLogger {
	
	public function __construct(WpSecurityAuditLog $plugin) {
		parent::__construct($plugin);
		$plugin->AddCleanupHook(array($this, 'CleanUp'));
	}
	
	public function Log($type, $data = array(), $date = null, $siteid = null, $migrated = false) {
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
	
	public function CleanUp() {
		$now = current_time('timestamp');
		$max_count = $this->plugin->settings->GetPruningLimit();
		$max_sdate = $this->plugin->settings->GetPruningDate();
		$max_stamp = $now - (strtotime($max_sdate) - $now);
		$cnt_items = WSAL_DB_Occurrence::Count();
		if($cnt_items == $max_count)return;
		$max_items = max(($cnt_items - $max_count) + 1, 0);
		
		$is_date_e = $this->plugin->settings->IsPruningDateEnabled();
		$is_limt_e = $this->plugin->settings->IsPruningLimitEnabled();
		
		switch(true){
			case $is_date_e && $is_limt_e:
				$cond = 'created_on < %d ORDER BY created_on ASC LIMIT %d';
				$args = array($max_stamp, $max_items);
				break;
			case $is_date_e && !$is_limt_e:
				$cond = 'created_on < %d';
				$args = array($max_stamp);
				break;
			case !$is_date_e && $is_limt_e:
				$cond = '1 ORDER BY created_on ASC LIMIT %d';
				$args = array($max_items);
				break;
			case !$is_date_e && !$is_limt_e:
				return;
		}
		if(!isset($cond))return;
		
		$items = WSAL_DB_Occurrence::LoadMulti($cond, $args);
		if(!count($items))return;
		
		foreach($items as $item)$item->Delete();
		do_action('wsal_prune', $items, vsprintf($cond, $args));
	}
	
}