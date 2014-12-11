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
	 * Create new AlertManager instance.
	 * @param WpSecurityAuditLog $plugin
	 */
	public function __construct(WpSecurityAuditLog $plugin){
		$this->plugin = $plugin;
		foreach(glob(dirname(__FILE__) . '/Loggers/*.php') as $file)
			$this->AddFromFile($file);
		
		add_action('shutdown', array($this, '_CommitPipeline'));
	}
	
	/**
	 * Add new logger from file inside autoloader path.
	 * @param string $file Path to file.
	 */
	public function AddFromFile($file){
		$this->AddFromClass($this->plugin->GetClassFileClassName($file));
	}
	
	/**
	 * Add new logger given class name.
	 * @param string $class Class name.
	 */
	public function AddFromClass($class){
		$this->AddInstance(new $class($this->plugin));
	}
	
	/**
	 * Add newly created logger to list.
	 * @param WSAL_AbstractLogger $logger The new logger.
	 */
	public function AddInstance(WSAL_AbstractLogger $logger){
		$this->_loggers[] = $logger;
	}
	
	/**
	 * Remove logger by class name.
	 * @param string $class The class name.
	 */
	public function RemoveByClass($class){
		foreach($this->_loggers as $i => $inst)
			if(get_class($inst) == $class)
				unset($this->_loggers[$i]);
	}
	
	/**
	 * Contains a list of alerts to trigger.
	 * @var array
	 */
	protected $_pipeline = array();
	
	/**
	 * Contains an array of alerts that have been triggered for this request.
	 * @var int[]
	 */
	protected $_triggered_types = array();
	
	/**
	 * Trigger an alert.
	 * @param integer $type Alert type.
	 * @param array $data Alert data.
	 */
	public function Trigger($type, $data = array(), $delayed = false){
		if($delayed){
			$this->TriggerIf($type, $data, null);
		}else{
			$this->_CommitItem($type, $data, null);
		}
	}
	
	/**
	 * Trigger only if a condition is met at the end of request.
	 * @param integer $type Alert type ID.
	 * @param array $data Alert data.
	 * @param callable $cond A future condition callback (receives an object of type WSAL_AlertManager as parameter).
	 */
	public function TriggerIf($type, $data, $cond = null){
		$this->_pipeline[] = array(
			'type' => $type,
			'data' => $data,
			'cond' => $cond,
		);
	}
	
	/**
	 * @internal Commit an alert now.
	 */
	protected function _CommitItem($type, $data, $cond, $_retry = true){
		if(!$cond || !!call_user_func($cond, $this)){
			if($this->IsEnabled($type)){
				if(isset($this->_alerts[$type])){
					// ok, convert alert to a log entry
					$this->_triggered_types[] = $type;
					$this->Log($type, $data);
				}elseif($_retry){
					// this is the last attempt at loading alerts from default file
					$this->plugin->LoadDefaults();
					return $this->_CommitItem($type, $data, $cond, false);
				}else{
					// in general this shouldn't happen, but it could, so we handle it here :)
					throw new Exception('Alert with code "' . $type . '" has not be registered.');
				}
			}
		}
	}
	
	/**
	 * @internal Runs over triggered alerts in pipeline and passes them to loggers.
	 */
	public function _CommitPipeline(){
		foreach($this->_pipeline as $item)
			$this->_CommitItem($item['type'], $item['data'], $item['cond']);
	}
	
	/**
	 * @param integer $type Alert type ID.
	 * @return boolean True if at the end of request an alert of this type will be triggered.
	 */
	public function WillTrigger($type){
		foreach($this->_pipeline as $item)
			if($item['type'] == $type)
				return true;
		return false;
	}
	
	/**
	 * @param int $type Alert type ID.
	 * @return boolean True if an alert has been or will be triggered in this request, false otherwise.
	 */
	public function WillOrHasTriggered($type){
		return in_array($type, $this->_triggered_types)
				|| $this->WillTrigger($type);
	}
	
	/**
	 * Register an alert type.
	 * @param array $info Array of [type, code, category, description, message] respectively.
	 */
	public function Register($info){
		if(func_num_args() == 1){
			// handle single item
			list($type, $code, $catg, $desc, $mesg) = $info;
			if(isset($this->_alerts[$type]))
				throw new Exception("Alert $type already registered with Alert Manager.");
			$this->_alerts[$type] = new WSAL_Alert($type, $code, $catg, $desc, $mesg);
		}else{
			// handle multiple items
			foreach(func_get_args() as $arg)
				$this->Register($arg);
		}
	}
	
	/**
	 * Register a whole group of items.
	 * @param array $groups An array with group name as the index and an array of group items as the value.
	 * Item values is an array of [type, code, description, message] respectively.
	 */
	public function RegisterGroup($groups){
		foreach($groups as $name => $group){
			foreach($group as $item){
				list($type, $code, $desc, $mesg) = $item;
				$this->Register(array($type, $code, $name, $desc, $mesg));
			}
		}
	}
	
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
		$this->plugin->settings->SetDisabledAlerts($types);
	}
	
	/**
	 * @return int[] Returns an array of disabled alerts' type code.
	 */
	public function GetDisabledAlerts(){
		return $this->plugin->settings->GetDisabledAlerts();
	}
	
	/**
	 * @return WSAL_AbstractLogger[] Returns an array of loaded loggers.
	 */
	public function GetLoggers(){
		return $this->_loggers;
	}
	
	/**
	 * Converts an Alert into a Log entry (by invoking loggers).
	 * You should not call this method directly.
	 * @param integer $type Alert type.
	 * @param array $data Misc alert data.
	 */
	protected function Log($type, $data = array()){
		if(!isset($data['ClientIP']))
			$data['ClientIP'] = $this->plugin->settings->GetMainClientIP();
		if(!isset($data['OtherIPs']) && $this->plugin->settings->IsMainIPFromProxy())
			$data['OtherIPs'] = $this->plugin->settings->GetClientIPs();
		if(!isset($data['UserAgent']))
			$data['UserAgent'] = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
		if(!isset($data['Username']) && !isset($data['CurrentUserID']))
			$data['CurrentUserID'] = function_exists('get_current_user_id') ? get_current_user_id() : 0;
		if(!isset($data['CurrentUserRoles']) && function_exists('is_user_logged_in') && is_user_logged_in())
			$data['CurrentUserRoles'] = $this->plugin->settings->GetCurrentUserRoles();
		
		//if(isset($_SERVER['REMOTE_HOST']) && $_SERVER['REMOTE_HOST'] != $data['ClientIP'])
		//	$data['ClientHost'] = $_SERVER['REMOTE_HOST'];
		
		//$data['OtherIPs'] = $_SERVER['REMOTE_HOST'];
		
		foreach($this->_loggers as $logger)
			$logger->Log($type, $data);
	}
	
	/**
	 * Return alert given alert type.
	 * @param integer $type Alert type.
	 * @param mixed $default Returned if alert is not found.
	 * @return WSAL_Alert
	 */
	public function GetAlert($type, $default = null){
		foreach($this->_alerts as $alert)
			if($alert->type == $type)
				return $alert;
		return $default;
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
		ksort($result);
		return $result;
	}
	
}