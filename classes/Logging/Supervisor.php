<?php

final class WSAL_Logging_Supervisor extends WSAL_Logging_AbstractLogger {
	
	protected $loggers = array();
	
	public function __construct(WpSecurityAuditLog $plugin){
		parent::__construct($plugin);
		
		foreach(glob(dirname(__FILE__) . '/*.php') as $file){
			$class = $plugin->GetClassFileClassName($file);
			if(strpos($class, 'Abstract') === false && $class != __CLASS__)
				$this->loggers[] = new $class($plugin);
		}
	}
	
	public function Log($type, $code, $message, $data = array()){
		$data['Client IP'] = $_SERVER['REMOTE_ADDR'];
		$data['UserAgent'] = $_SERVER['HTTP_USER_AGENT'];
		
		foreach($this->loggers as $logger)
			$logger->Log($type, $code, $message, $data);
	}
	
}