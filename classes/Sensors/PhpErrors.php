<?php

class WSAL_Sensors_PhpErrors extends WSAL_AbstractSensor {
	
	protected $_avoid_error_recursion = false;
	
	protected $_error_types = array(
		0001 => array(1,4,16,64,256,4096),		// errors
		0002 => array(2,32,128,512),			// warnings
		0003 => array(8,1024,2048,8192,16384),	// notices
	);
	
	public function HookEvents() {
		set_error_handler(array($this, 'EventError'), E_ALL);
		set_exception_handler(array($this, 'EventException'));
		register_shutdown_function(array($this, 'EventShutdown'));
	}
	
	public function EventError($errno, $errstr, $errfile = 'unknown', $errline = 0, $errcontext = array()){
		if($this->_avoid_error_recursion)return;
		
		$data = array(
			'Code'    => $errno,
			'Message' => $errstr,
			'File'    => $errfile,
			'Line'    => $errline,
			'Context' => $errcontext,
		);
		
		$type = 0002; // default (middle ground)
		foreach($this->_error_types as $temp => $codes){
			if(in_array($errno, $codes)){
				$type = $temp;
			}
		}
		
		$this->_avoid_error_recursion = true;
		$this->plugin->alerts->Trigger($type, $data);
		$this->_avoid_error_recursion = false;
	}
	
	public function EventException(Exception $ex){
		if($this->_avoid_error_recursion)return;
		
		$data = array(
			'Class'   => get_class($ex),
			'Code'    => $ex->getCode(),
			'Message' => $ex->getMessage(),
			'File'    => $ex->getFile(),
			'Line'    => $ex->getLine(),
			'Trace'   => $ex->getTraceAsString(),
		);
		
		if(method_exists($ex, 'getContext'))
			$data['Context'] = $ex->getContext();
		
		$this->_avoid_error_recursion = true;
		$this->plugin->alerts->Trigger(0004, $data);
		$this->_avoid_error_recursion = false;
	}
	
	public function EventShutdown(){
		if($this->_avoid_error_recursion)return;
		
		if(!!($error = error_get_last())){
			
			$data = array(
				'Code'    => $error['type'],
				'Message' => $error['message'],
				'File'    => $error['file'],
				'Line'    => $error['line'],
			);
			
			$this->_avoid_error_recursion = true;
			$this->plugin->alerts->Trigger(0005, $data);
			$this->_avoid_error_recursion = false;
		}
	}
	
}
