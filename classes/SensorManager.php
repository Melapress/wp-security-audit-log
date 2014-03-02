<?php

final class WSAL_SensorManager extends WSAL_AbstractSensor {
	
	protected $sensors = array();
	
	public function __construct(WpSecurityAuditLog $plugin){
		parent::__construct($plugin);
		
		foreach(glob(dirname(__FILE__) . '/Sensors/*.php') as $file){
			$class = $plugin->GetClassFileClassName($file);
			$this->sensors[] = new $class($plugin);
		}
	}
	
	public function HookEvents() {
		foreach($this->sensors as $sensor)
			$sensor->HookEvents();
	}
	
}