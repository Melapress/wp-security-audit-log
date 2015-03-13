<?php

class WSAL_Sensors_Database extends WSAL_AbstractSensor {

	public function HookEvents() {
		add_action('dbdelta_queries', array($this, 'EventDBDeltaQuery'));
	}
	
	public function EventDBDeltaQuery($queries){
		$actype = basename($_SERVER['SCRIPT_NAME'], '.php');
		$is_themes = $actype == 'themes';
		$is_plugins = $actype == 'plugins';
		$table_names = "";
		$type_query = "";
		foreach($queries as $qry) {
			$str = explode(" ", $qry);
			if (preg_match("|CREATE TABLE ([^ ]*)|", $qry)) {
				$table_names .= $str[2] . ",";
				$type_query = 'create';	
			} else if (preg_match("|UPDATE ([^ ]*)|", $qry)) {
				$table_names .= $str[1] . ",";
				$type_query = 'update';
			} else if (preg_match("|DELETE ([^ ]*)|", $qry)) {
				$table_names .= $str[1] . ",";
				$type_query = 'delete';
			}
		}
		$table_names = rtrim($table_names, ",");
		//Action Plugin Component
		if ($is_plugins) {
			$event_code = $this->GetEventQueryType($actype, $type_query);
			if (isset($_REQUEST['plugin'])) {
				$pluginFile = $_REQUEST['plugin'];
			} else {
				$pluginFile = $_REQUEST['checked'][0];
			}
			$pluginName = basename($pluginFile, '.php');
			$pluginName = str_replace(array('_', '-', '  '), ' ', $pluginName);
			$pluginName = ucwords($pluginName);
			$this->plugin->alerts->Trigger($event_code, array(
				'Plugin' => (object)array(
					'Name' => $pluginName,
				),
				'TableNames' => $table_names
			));
		//Action Theme Component
		} else if ($is_themes) {
			if (isset($_REQUEST['theme'])) {
				$themeName = $_REQUEST['theme'];
			} else {
				$themeName = $_REQUEST['checked'][0];
			}
			$themeName = str_replace(array('_', '-', '  '), ' ', $themeName);
			$themeName = ucwords($themeName);
			$event_code = $this->GetEventQueryType($actype, $type_query);
			$this->plugin->alerts->Trigger($event_code, array(
				'Theme' => (object)array(
					'Name' => $themeName,
				),
				'TableNames' => $table_names
			));
		//Action Unknown Component
		} else {
			$event_code = $this->GetEventQueryType($actype, $type_query);
			$this->plugin->alerts->Trigger($event_code, array(
				'Component' => 'Unknown',
				'TableNames' => $table_names
			));
		}
		return $queries;
	}
	
	protected function GetEventQueryType($type_action, $type_query){
		switch($type_action){
			case 'plugins':
				if ($type_query == 'create') return 5010;
				else if ($type_query == 'update') return 5011;
				else if ($type_query == 'delete') return 5012;
			case 'themes':
				if ($type_query == 'create') return 5013;
				else if ($type_query == 'update') return 5014;
				else if ($type_query == 'delete') return 5015;
			default:
				if ($type_query == 'create') return 5016;
				else if ($type_query == 'update') return 5017;
				else if ($type_query == 'delete') return 5018;
		}
	}

}
