<?php

abstract class WSAL_Logging_AbstractLogger {
	/**
	 * @var WpSecurityAuditLog
	 */
	protected $plugin;

	public function __construct(WpSecurityAuditLog $plugin){
		$this->plugin = $plugin;
	}
	
	public abstract function Log($type, $code, $message, $data = array());
}