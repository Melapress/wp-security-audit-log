<?php

abstract class WSAL_AbstractView {
	
	/**
	 * @var WpSecurityAuditLog 
	 */
	protected $_plugin;
	
	protected $_wpversion;
	
	/**
	 * @param WpSecurityAuditLog $plugin
	 */
	public function __construct(WpSecurityAuditLog $plugin){
		$this->_plugin = $plugin;
		
		// get and store wordpress version
		global $wp_version;
		if(!isset($wp_version))
			$wp_version = get_bloginfo('version');
		$this->_wpversion = floatval($wp_version);
	}
	
	/**
	 * @return string Return page name (for menu etc).
	 */
	abstract public function GetName();
	
	/**
	 * @return string Return page title.
	 */
	abstract public function GetTitle();
	
	/**
	 * @return string Page icon name.
	 */
	abstract public function GetIcon();
	
	/**
	 * @return int Menu weight, the higher this is, the lower it goes.
	 */
	abstract public function GetWeight();
	
	/**
	 * Renders and outputs the view directly.
	 */
	abstract public function Render();
	
	/**
	 * @return boolean Whether page should appear in menu or not.
	 */
	public function IsVisible(){ return true; }
	
	/**
	 * Used for rendering stuff into head tag.
	 */
	public function Header(){}
	
	/**
	 * Used for rendering stuff in page fotoer.
	 */
	public function Footer(){}
	
	/**
	 * @return string Safe view menu name.
	 */
	public function GetSafeViewName(){
		return 'wsal-' . strtolower(
				preg_replace('/[^A-Za-z0-9\-]/', '-', $this->GetName())
			);
	}
	
	/**
	 * Override this and make it return true to create a shortcut link in plugin page to the view.
	 * @return boolean
	 */
	public function HasPluginShortcutLink(){
		return false;
	}
	
}