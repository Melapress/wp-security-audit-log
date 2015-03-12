<?php

class WSAL_Sensors_Database extends WSAL_AbstractSensor {

	public function HookEvents() {
		add_action('dbdelta_queries', array($this, 'EventDBDeltaQuery'));
	}
	
	public function EventDBDeltaQuery($queries){
		$actype = basename($_SERVER['SCRIPT_NAME'], '.php');
		$is_themes = $actype == 'themes';
		$is_plugins = $actype == 'plugins';
		if ($is_plugins) {
			foreach($queries as $qry) {
				$str = explode(" ", $qry);
				if (preg_match("|CREATE TABLE ([^ ]*)|", $qry)) {
					$table_name = $str[2];
					if (isset($_REQUEST['plugin'])) {
						$pluginFile = WP_PLUGIN_DIR . '/' . $_REQUEST['plugin'];
						$plugin = get_plugin_data($pluginFile, false, true);
					}
					error_log("Excution create query db delta");
					$this->plugin->alerts->Trigger(5010, array(
						'Plugin' => (object)array(
							'Name' => $plugin['Name'],
							'PluginURI' => $plugin['PluginURI'],
							'Version' => $plugin['Version'],
							'Author' => $plugin['Author'],
							'Network' => $plugin['Network'] ? 'True' : 'False'
						),
						'TableNames' => $table_name
					));
				} else if (preg_match("|UPDATE ([^ ]*)|", $qry)) {
					error_log("Excution update query db delta");
				} else if (preg_match("|DELETE ([^ ]*)|", $qry)) {
					error_log("Excution delete query db delta");
				}
			}
		} else if ($is_themes) {
			foreach($queries as $qry) {
				if (preg_match("|CREATE TABLE ([^ ]*)|", $qry)) {
					$theme = wp_get_themes();
					error_log("Excution create query db delta");
				} else if (preg_match("|UPDATE ([^ ]*)|", $qry)) {
					error_log("Excution update query db delta");
				} else if (preg_match("|DELETE ([^ ]*)|", $qry)) {
					error_log("Excution delete query db delta");
				}
			}
		} else {
			foreach($queries as $qry) {
				if (preg_match("|CREATE TABLE ([^ ]*)|", $qry)) {
					error_log("Excution create query db delta");
				} else if (preg_match("|UPDATE ([^ ]*)|", $qry)) {
					error_log("Excution update query db delta");
				} else if (preg_match("|DELETE ([^ ]*)|", $qry)) {
					error_log("Excution delete query db delta");
				}
			}
		}
		//error_log($action. " - ".$actype);
		return $queries;
	}
	
}
