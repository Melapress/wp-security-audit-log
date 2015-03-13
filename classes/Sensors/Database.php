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

		$typeQueries = array(
			"create" => array(),
			"update" => array(),
			"delete" => array()
		);
		foreach($queries as $qry) {
			$str = explode(" ", $qry);
			if (preg_match("|CREATE TABLE ([^ ]*)|", $qry)) {
				$typeQueries['create'] = $str[2];
			} else if (preg_match("|ALTER TABLE ([^ ]*)|", $qry)) {
				$typeQueries['update'] = $str[2];
			} else if (preg_match("|DROP TABLE ([^ ]*)|", $qry)) {
				$typeQueries['delete'] = $str[2];
			}
		}

		//Action Plugin Component
		$alertOptions = array();
		if ($is_plugins) {
			if (isset($_REQUEST['plugin'])) {
				$pluginFile = $_REQUEST['plugin'];
			} else {
				$pluginFile = $_REQUEST['checked'][0];
			}
			$pluginName = basename($pluginFile, '.php');
			$pluginName = str_replace(array('_', '-', '  '), ' ', $pluginName);
			$pluginName = ucwords($pluginName);
			$alertOptions["Plugin"] = (object)array(
				'Name' => $pluginName,
			);
		//Action Theme Component
		} else if ($is_themes) {
			if (isset($_REQUEST['theme'])) {
				$themeName = $_REQUEST['theme'];
			} else {
				$themeName = $_REQUEST['checked'][0];
			}
			$themeName = str_replace(array('_', '-', '  '), ' ', $themeName);
			$themeName = ucwords($themeName);
			$alertOptions["Theme"] = (object)array(
				'Name' => $themeName,
			);
		//Action Unknown Component
		} else {
			$alertOptions["Component"] = "Unknown";
		}

		foreach($typeQueries as $queryType => $tableNames) {
			if (!empty($tableNames)) {
				$event_code = $this->GetEventQueryType($actype, $queryType);
				$alertOptions["TableNames"] = $tableNames;
				$this->plugin->alerts->Trigger($event_code, $alertOptions);
			}
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
