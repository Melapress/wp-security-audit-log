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
	
	public function IsDataInspectorEnabled(){
		return true;
	}
	
	public function GetMaxAllowedAlerts(){
		return 5000;
	}
	
	public function GetDefaultPruningDate(){
		return '1 month';
	}
	
	protected $_pruning = 0;
	
	public function GetPruningDate(){
		if(!$this->_pruning){
			$this->_pruning = get_option(self::OPT_PRFX . 'pruning-date');
			$this->_pruning = strtotime($this->_pruning);
			if(!$this->_pruning)$this->_pruning = strtotime($this->GetDefaultPruningDate());
		}
		return $this->_pruning;
	}
	
	public function SetPruningDate($newvalue){
		if(strtotime($newvalue)){
			update_option(self::OPT_PRFX . 'pruning-date', $newvalue);
			$this->_pruning = null;
		}
	}
	
	public function GetPruningLimit(){
		$val = (int)get_option(self::OPT_PRFX . 'pruning-limit');
		return $val ? $val : $this->GetPruningLimit();
	}
	
	public function SetPruningLimit($newvalue){
		$newvalue = max(min((int)$newvalue, $this->GetMaxAllowedAlerts()), 0);
		update_option(self::OPT_PRFX . 'pruning-limit', $newvalue);
	}
	
	protected $_disabled = null;
	
	public function GetDisabledAlerts(){
		if(!$this->_disabled){
			$this->_disabled = get_option(self::OPT_PRFX . 'disabled-alerts', ',');
			$this->_disabled = explode(',', $this->_disabled);
			$this->_disabled = array_map('intval', $this->_disabled);
		}
		return $this->_disabled;
	}
	
	public function SetDisabledAlerts($types){
		$this->_disabled = array_unique(array_map('intval', $types));
		update_option(self::OPT_PRFX . 'disabled-alerts', implode(',', $this->_disabled));
	}
	
	protected function IsUserOrRole($item){
		// TODO finish user/role check
	}
	
	protected function CheckUsersRoles($usersOrRoles){
		foreach($usersOrRoles as $item)
			if(!$this->IsUserOrRole($item))
				throw new Exception("The identifier \"$item\" is neither a user nor a role.");
	}
	
	protected $_viewers = null;
	
	public function SetAllowedPluginViewers($usersOrRoles){
		$this->CheckUsersRoles($usersOrRoles);
		$this->_viewers = $usersOrRoles;
		update_option(self::OPT_PRFX . 'plugin-viewers', implode(',', $this->_viewers));
	}
	
	public function GetAllowedPluginViewers(){
		if(is_null($this->_viewers)){
			$this->_viewers = explode(',', get_option(self::OPT_PRFX . 'plugin-viewers'));
		}
		return $this->_viewers;
	}
	
	protected $_editors = null;
	
	public function SetAllowedPluginEditors($usersOrRoles){
		$this->CheckUsersRoles($usersOrRoles);
		$this->_editors = $usersOrRoles;
		update_option(self::OPT_PRFX . 'plugin-editors', implode(',', $this->_editors));
	}
	
	public function GetAllowedPluginEditors(){
		if(is_null($this->_editors)){
			$this->_editors = explode(',', get_option(self::OPT_PRFX . 'plugin-editors'));
		}
		return $this->_editors;
	}
	
	public function CurrentUserCan($action){
		return $this->UserCan(get_current_user_id(), $action);
	}
	
	public function UserCan($user, $action){
		if(is_object($user))$user = $user->id;
		// TODO finish auth check
	}
}
