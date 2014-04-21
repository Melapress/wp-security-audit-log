<?php

class WSAL_Settings {
	/**
	 * @var WpSecurityAuditLog
	 */
	protected $_plugin;
	
	const OPT_PRFX = 'wsal-';
	
	public function __construct(WpSecurityAuditLog $plugin){
		$this->_plugin = $plugin;
	}
	
	/**
	 * @return boolean Whether to enable data inspector or not.
	 */
	public function IsDataInspectorEnabled(){
		return true;
	}
	
	/**
	 * @return boolean Whether dashboard widgets are enabled or not.
	 */
	public function IsWidgetsEnabled(){
		return !get_option(self::OPT_PRFX . 'disable-widgets');
	}
	
	/**
	 * @param boolean $newvalue Whether dashboard widgets are enabled or not.
	 */
	public function SetWidgetsEnabled($newvalue){
		update_option(self::OPT_PRFX . 'disable-widgets', !$newvalue);
	}
	
	/**
	 * @return boolean Whether alerts in audit log view refresh automatically or not.
	 */
	public function IsRefreshAlertsEnabled(){
		return !get_option(self::OPT_PRFX . 'disable-refresh');
	}
	
	/**
	 * @param boolean $newvalue Whether alerts in audit log view refresh automatically or not.
	 */
	public function SetRefreshAlertsEnabled($newvalue){
		update_option(self::OPT_PRFX . 'disable-refresh', !$newvalue);
	}
	
	/**
	 * @return int Maximum number of alerts to show in dashboard widget.
	 */
	public function GetDashboardWidgetMaxAlerts(){
		return 5;
	}
	
	/**
	 * @return int The maximum number of alerts allowable.
	 */
	public function GetMaxAllowedAlerts(){
		return 5000;
	}
	
	/**
	 * @return string The default pruning date.
	 */
	public function GetDefaultPruningDate(){
		return '1 month';
	}
	
	protected $_pruning = 0;
	
	/**
	 * @return string The current pruning date.
	 */
	public function GetPruningDate(){
		if(!$this->_pruning){
			$this->_pruning = get_option(self::OPT_PRFX . 'pruning-date');
			if(!strtotime($this->_pruning))
				$this->_pruning = $this->GetDefaultPruningDate();
		}
		return $this->_pruning;
	}
	
	/**
	 * @param string $newvalue The new pruning date.
	 */
	public function SetPruningDate($newvalue){
		if(strtotime($newvalue)){
			update_option(self::OPT_PRFX . 'pruning-date', $newvalue);
			$this->_pruning = $newvalue;
		}
	}
	
	/**
	 * @return integer Maximum number of alerts to keep.
	 */
	public function GetPruningLimit(){
		$val = (int)get_option(self::OPT_PRFX . 'pruning-limit');
		return $val ? $val : $this->GetMaxAllowedAlerts();
	}
	
	/**
	 * @param integer $newvalue The new maximum number of alerts.
	 */
	public function SetPruningLimit($newvalue){
		$newvalue = max(min((int)$newvalue, $this->GetMaxAllowedAlerts()), 1);
		update_option(self::OPT_PRFX . 'pruning-limit', $newvalue);
	}
	
	protected $_disabled = null;
	
	/**
	 * @return array IDs of disabled alerts.
	 */
	public function GetDisabledAlerts(){
		if(!$this->_disabled){
			$this->_disabled = get_option(self::OPT_PRFX . 'disabled-alerts', ',');
			$this->_disabled = explode(',', $this->_disabled);
			$this->_disabled = array_map('intval', $this->_disabled);
		}
		return $this->_disabled;
	}
	
	/**
	 * @param array $types IDs alerts to disable.
	 */
	public function SetDisabledAlerts($types){
		$this->_disabled = array_unique(array_map('intval', $types));
		update_option(self::OPT_PRFX . 'disabled-alerts', implode(',', $this->_disabled));
	}
	
	protected $_viewers = null;
	
	public function SetAllowedPluginViewers($usersOrRoles){
		$this->_viewers = $usersOrRoles;
		update_option(self::OPT_PRFX . 'plugin-viewers', implode(',', $this->_viewers));
	}
	
	public function GetAllowedPluginViewers(){
		if(is_null($this->_viewers)){
			$this->_viewers = array_unique(array_filter(explode(',', get_option(self::OPT_PRFX . 'plugin-viewers'))));
		}
		return $this->_viewers;
	}
	
	protected $_editors = null;
	
	public function SetAllowedPluginEditors($usersOrRoles){
		$this->_editors = $usersOrRoles;
		update_option(self::OPT_PRFX . 'plugin-editors', implode(',', $this->_editors));
	}
	
	public function GetAllowedPluginEditors(){
		if(is_null($this->_editors)){
			$this->_editors = array_unique(array_filter(explode(',', get_option(self::OPT_PRFX . 'plugin-editors'))));
		}
		return $this->_editors;
	}
	
	protected $_perpage = null;
	
	public function SetViewPerPage($newvalue){
		$this->_perpage = max($newvalue, 1);
		update_option(self::OPT_PRFX . 'items-per-page', $this->_perpage);
	}
	
	public function GetViewPerPage(){
		if(is_null($this->_perpage)){
			$this->_perpage = (int)get_option(self::OPT_PRFX . 'items-per-page', 10);
		}
		return $this->_perpage;
	}
	
	/**
	 * @param string $action Type of action, either 'view' or 'edit'.
	 * @return boolean If user has access or not.
	 */
	public function CurrentUserCan($action){
		return $this->UserCan(wp_get_current_user(), $action);
	}
	
	/**
	 * @param integer|WP_user $user User object to check.
	 * @param string $action Type of action, either 'view' or 'edit'.
	 * @return boolean If user has access or not.
	 */
	public function UserCan($user, $action){
		if(is_int($user))$user = get_userdata($user);
		$allowed = array();
		
		switch($action){
			case 'edit':
				$allowed = $this->GetAllowedPluginEditors();
				break;
			case 'view':
				$allowed = $this->GetAllowedPluginViewers();
				break;
		}
		
		$check = array_merge(
			$user->roles,
			array($user->user_login)
		);
		
		if(is_multisite()){
			$allowed = array_merge($allowed, get_super_admins());
		}else{
			$allowed[] = 'administrator';
		}
		
		foreach($check as $item){
			if(in_array($item, $allowed)){
				return true;
			}
		}
		
		return false;
	}
}
