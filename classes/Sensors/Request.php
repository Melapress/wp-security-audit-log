<?php

class WSAL_Sensors_Request extends WSAL_AbstractSensor {
	public function HookEvents() {
		add_action('shutdown', array($this, 'EventShutdown'));
	}
	
	protected function FileAppend($file, $data){
		$f = fopen($file, 'a');
		fwrite($f, $data);
		fclose($f);
	}
	
	public function EventShutdown(){
		$file = 'C:\Users\Christian\Desktop\request.log';
		if(file_exists($file)){
			$line = '['.date('Y-m-d H:i:s').'] '
				. $_SERVER['REQUEST_METHOD'] . ' '
				. $_SERVER['REQUEST_URI'] . ' '
				. (!empty($_POST) ? str_pad(PHP_EOL, 24) . json_encode($_POST) : '')
				. (!empty(self::$envvars) ? str_pad(PHP_EOL, 24) . json_encode(self::$envvars) : '')
				. PHP_EOL;
			$this->FileAppend($file, $line);
		}
	}
	
	protected static $envvars = array();
	
	public static function SetVar($name, $value){
		self::$envvars[$name] = $value;
	}
	
	public static function SetVars($data){
		foreach($data as $name => $value)self::SetVar($name, $value);
	}
}