<?php

abstract class WSAL_Sensors_AbstractSensor {
	/**
	 * @var WpSecurityAuditLog
	 */
	protected $plugin;

	public function __construct(WpSecurityAuditLog $plugin){
		$this->plugin = $plugin;
	}
}