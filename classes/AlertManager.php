<?php

final class WSAL_AlertManager {
	
	protected $_alerts = array();
	
	/**
	 * @var WSAL_AbstractLogger[]
	 */
	protected $_loggers = array();
	
	/**
	 * @var WpSecurityAuditLog
	 */
	protected $plugin;
	
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
			$this->Log(
				$type,
				$this->_alerts[$type]->code,
				$this->_alerts[$type]->mesg,
				$data
			);
		}
	}
	
	/**
	 * Register alert types.
	 * @param array $info Array of [type, code, description, message] respectively.
	 */
	public function Register($info){
		if(func_num_args() == 1){
			// handle single item
			list($type, $code, $desc, $mesg) = $info;
			$this->_alerts[$type] = (object)array(
				'type' => $type,
				'code' => $code,
				'desc' => $desc,
				'mesg' => $mesg,
			);
		}else{
			// handle multiple items
			foreach(func_get_args() as $arg)
				$this->Register($arg);
		}
	}
	
	/**
	 * Returns whether alert of type $type is enabled or not.
	 * @param integer $type Alert type.
	 * @return boolean True if enabled, false otherwise.
	 */
	public function IsEnabled($type){
		return true;
	}
	
	protected function Log($type, $code, $message, $data = array()){
		$data['Client IP'] = $_SERVER['REMOTE_ADDR'];
		$data['UserAgent'] = $_SERVER['HTTP_USER_AGENT'];
		
		foreach($this->_loggers as $logger)
			$logger->Log($type, $code, $message, $data);
	}
	
}