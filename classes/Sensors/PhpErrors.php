<?php

class WSAL_Sensors_PhpErrors extends WSAL_AbstractSensor {

	public function HookEvents() {
		set_error_handler(array($this, 'EventError'), E_ALL);
		set_exception_handler(array($this, 'EventException'));
	}
	
	protected function PhpCodeToType($errno){
		foreach(array(
			0001 => array(1,4,16,64,256,4096),		// errors
			0002 => array(2,32,128,512),			// warnings
			0003 => array(8,1024,2048,8192,16384),	// notices
		) as $type => $codes)
			if(in_array($errno, $codes))
				return $type;
		return 0002;	// default (middle ground)
	}
	
	public function EventError($errno, $errstr, $errfile = 'unknown', $errline = 0, $errcontext = array()){
		$data = array(
			'Code'    => $errno,
			'Message' => $errstr,
			'File'    => $errfile,
			'Line'    => $errline,
			'Context' => $errcontext,
		);
		
		$this->plugin->alerts->Trigger($this->PhpCodeToType($errno), $data);
	}
	
	public function EventException(Exception $ex){
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
		
		$this->plugin->alerts->Trigger($this->PhpCodeToType($ex->getCode()), $data);
	}
	
}
