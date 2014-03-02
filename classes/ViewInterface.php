<?php

interface WSAL_ViewInterface {
	
	/**
	 * Receives plugin instance from view manager.
	 * @param WpSecurityAuditLog $plugin
	 */
	public function SetPlugin(WpSecurityAuditLog $plugin);
	
	/**
	 * @return string Return page name (for menu etc).
	 */
	public function GetName();
	
	/**
	 * @return string Return page title.
	 */
	public function GetTitle();
	
	/**
	 * @return string Page icon name.
	 */
	public function GetIcon();
	
	/**
	 * @return int Menu weight, the higher this is, the lower it goes.
	 */
	public function GetWeight();
	
	/**
	 * Renders and outputs the view directly.
	 */
	public function Render();
	
}