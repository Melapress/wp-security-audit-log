<?php

final class WSAL_SensorManager extends WSAL_AbstractSensor {
	
	/**
	 * @var WSAL_AbstractSensor[] 
	 */
	protected $sensors = array();
	
	public function __construct(WpSecurityAuditLog $plugin){
		parent::__construct($plugin);
		
		foreach(glob(dirname(__FILE__) . '/Sensors/*.php') as $file)
			$this->AddFromFile ($file);
	}
	
	public function HookEvents() {
		foreach($this->sensors as $sensor)
			$sensor->HookEvents();
	}
	
	public function GetSensors(){
		return $this->sensors;
	}
	
	/**
	 * Add new sensor from file inside autoloader path.
	 * @param string $file Path to file.
	 */
	public function AddFromFile($file){
		$this->AddFromClass($this->plugin->GetClassFileClassName($file));
	}
	
	/**
	 * Add new sensor given class name.
	 * @param string $class Class name.
	 */
	public function AddFromClass($class){
		$this->AddInstance(new $class($this->plugin));
	}
	
	/**
	 * Add newly created sensor to list.
	 * @param WSAL_AbstractSensor $sensor The new sensor.
	 */
	public function AddInstance(WSAL_AbstractSensor $sensor){
		$this->sensors[] = $sensor;
	}
	
}