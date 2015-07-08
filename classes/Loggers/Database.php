<?php

class WSAL_Loggers_Database extends WSAL_AbstractLogger {

	public function __construct(WpSecurityAuditLog $plugin) {
		parent::__construct($plugin);
		$plugin->AddCleanupHook(array($this, 'CleanUp'));
	}

	public function Log($type, $data = array(), $date = null, $siteid = null, $migrated = false) { 
		// is this a php alert, and if so, are we logging such alerts?
		if ($type < 0010 && !$this->plugin->settings->IsPhpErrorLoggingEnabled()) return;

		// create new occurrence
		$occ = new WSAL_Models_Occurrence();
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
		$max_sdate = $this->plugin->settings->GetPruningDate();
		$max_count = $this->plugin->settings->GetPruningLimit();
		$is_date_e = $this->plugin->settings->IsPruningDateEnabled(); 
		$is_limt_e = $this->plugin->settings->IsPruningLimitEnabled();

        if (!$is_date_e && !$is_limt_e) {
            return;
        } // pruning disabled
        $occ = new WSAL_Models_Occurrence();
		$cnt_items = $occ->Count();

        // Check if there is something to delete
        if($is_limt_e && ($cnt_items < $max_count)){
            return;
        }

        $max_stamp = $now - (strtotime($max_sdate) - $now);
		$max_items = (int)max(($cnt_items - $max_count) + 1, 0);

		$query = new WSAL_Models_OccurrenceQuery();
		$query->addOrderBy("created_on", false);
		// TO DO Fixing data
		if ($is_date_e) $query->addCondition('created_on <= %s', intval($max_stamp));
		if ($is_limt_e) $query->setLimit($max_items); 

		if (($max_items-1) == 0) return; // nothing to delete

		$result = $query->getAdapter()->GetSqlDelete($query);
		$deletedCount = $query->getAdapter()->Delete($query);

		if ($deletedCount == 0) return; // nothing to delete
		// keep track of what we're doing
		$this->plugin->alerts->Trigger(0003, array(
				'Message' => 'Running system cleanup.',
				'Query SQL' => $result['sql'],
				'Query Args' => $result['args'],
			), true);

		// notify system
		do_action('wsal_prune', $deletedCount, vsprintf($result['sql'], $result['args']));
	}

}