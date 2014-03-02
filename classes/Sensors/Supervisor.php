<?php

class WSAL_Sensors_Supervisor extends WSAL_Sensors_AbstractSensor {
	
	protected $sensors = array();
	
	public function __construct(WpSecurityAuditLog $plugin){
		parent::__construct($plugin);
		
		foreach(glob(dirname(__FILE__) . '/*.php') as $file){
			$class = $plugin->GetClassFileClassName($file);
			if(strpos($class, 'Abstract') === false && $class != __CLASS__)
				$this->sensors = new $class($plugin);
		}
	}
	
}