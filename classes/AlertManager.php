<?php

final class WSAL_AlertManager {
	
	/**
	 * @var WSAL_Alert[]
	 */
	protected $_alerts = array();
	
	/**
	 * @var WSAL_AbstractLogger[]
	 */
	protected $_loggers = array();
	
	/**
	 * @var WpSecurityAuditLog
	 */
	protected $plugin;
	
	/**
	 * Disabled alerts option name.
	 */
	const OPT_DISABLED_TYPES = 'wsal-disabled-alerts';
	
	/**
	 * Create new AlertManager instance.
	 * @param WpSecurityAuditLog $plugin
	 */
	public function __construct(WpSecurityAuditLog $plugin){
		$this->plugin = $plugin;
		foreach(glob(dirname(__FILE__) . '/Loggers/*.php') as $file){
			$class = $plugin->GetClassFileClassName($file);
			$this->_loggers[] = new $class($plugin);
		}
	}
	
	/**
	 * Trigger an alert.
	 * @param integer $type Alert type.
	 * @param array $data Alert data.
	 */
	public function Trigger($type, $data){
		if($this->IsEnabled($type)){
			$alert = isset($this->_alerts[$type]) ? $this->_alerts[$type] : null;
			if($alert){
				// ok, convert alert to a log entry
				$this->Log($type, $alert->code, $alert->mesg, $data);
			}else{
				// in general this shouldn't happen, but it could, so we handle it here :)
				throw new Exception('Alert with code "'.$type.'" has not be registered.');
			}
		}
	}
	
	/**
	 * Register an alert type.
	 * @param array $info Array of [type, code, category, description, message] respectively.
	 */
	public function Register($info){
		if(func_num_args() == 1){
			// handle single item
			list($type, $code, $catg, $desc, $mesg) = $info;
			$this->_alerts[$type] = new WSAL_Alert($type, $code, $catg, $desc, $mesg);
		}else{
			// handle multiple items
			foreach(func_get_args() as $arg)
				$this->Register($arg);
		}
	}
	
	protected $_disabled = null;
	
	/**
	 * Returns whether alert of type $type is enabled or not.
	 * @param integer $type Alert type.
	 * @return boolean True if enabled, false otherwise.
	 */
	public function IsEnabled($type){
		return !in_array($type, $this->GetDisabledAlerts());
	}
	
	/**
	 * Disables a set of alerts by type.
	 * @param int[] $types Alert type codes to be disabled.
	 */
	public function SetDisabledAlerts($types){
		$this->_disabled = array_unique(array_map('intval', $types));
		update_option(self::OPT_DISABLED_TYPES, implode(',', $this->_disabled));
	}
	
	/**
	 * @return int[] Returns an array of disabled alerts' type code.
	 */
	public function GetDisabledAlerts(){
		if(!$this->_disabled){
			$this->_disabled = get_option(self::OPT_DISABLED_TYPES, ',');
			$this->_disabled = explode(',', $this->_disabled);
			$this->_disabled = array_map('intval', $this->_disabled);
		}
		return $this->_disabled;
	}
	
	/**
	 * Converts an Alert into a Log entry (by invoking loggers).
	 * You should not call this method directly.
	 * @param integer $type Alert type.
	 * @param integer $code Alert error level.
	 * @param string $message Alert message.
	 * @param array $data Misc alert data.
	 */
	protected function Log($type, $code, $message, $data = array()){
		$data['Client IP'] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
		$data['UserAgent'] = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
		
		foreach($this->_loggers as $logger)
			$logger->Log($type, $code, $message, $data);
	}
	
	/**
	 * Returns all supported alerts.
	 * @return WSAL_Alert[]
	 */
	public function GetAlerts(){
		return $this->_alerts;
	}
	
	/**
	 * Returns all supported alerts.
	 * @return array
	 */
	public function GetCategorizedAlerts(){
		$result = array();
		foreach($this->_alerts as $alert){
			if(!isset($result[$alert->catg]))
				$result[$alert->catg] = array();
			$result[$alert->catg][] = $alert;
		}
		asort($result);
		return $result;
	}
	
}