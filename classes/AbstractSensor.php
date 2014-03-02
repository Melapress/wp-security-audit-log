<?php

abstract class WSAL_AbstractSensor {
	/**
	 * @var WpSecurityAuditLog
	 */
	protected $plugin;

	public function __construct(WpSecurityAuditLog $plugin){
		$this->plugin = $plugin;
	}
	
	abstract function HookEvents();
}