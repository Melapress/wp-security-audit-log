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
	
	protected function IsMultisite(){
		return function_exists('is_multisite') && is_multisite();
	}
	
	protected function GetGlobalOption($option, $default = false){
		$fn = $this->IsMultisite() ? 'get_site_option' : 'get_option';
		return $fn($option, $default);
	}
	
	protected function SetGlobalOption($option, $value){
		$fn = $this->IsMultisite() ? 'update_site_option' : 'update_option';
		return $fn($option, $value);
	}
	
	protected function GetLocalOption($option, $default = false){
		$result = get_user_option($option, get_current_user_id());
		return $result === false ? $default : $result;
	}
	
	protected function SetLocalOption($option, $value){
		update_user_option(get_current_user_id(), $option, $value, false);
	}
	
	const OPT_DEV_DATA_INSPECTOR = 'd';
	const OPT_DEV_PHP_ERRORS     = 'p';
	const OPT_DEV_REQUEST_LOG    = 'r';
	const OPT_DEV_SANDBOX_PAGE   = 's';
	
	protected $_devoption = null;
	
	/**
	 * @return array Array of developer options to be enabled by default.
	 */
	public function GetDefaultDevOptions(){
		return array();
	}
	
	/**
	 * Returns whether a developer option is enabled or not.
	 * @param string $option See self::OPT_DEV_* constants.
	 * @return boolean If option is enabled or not.
	 */
	public function IsDevOptionEnabled($option){
		if(is_null($this->_devoption)){
			$this->_devoption = $this->GetGlobalOption(
				self::OPT_PRFX . 'dev-options',
				implode(',', $this->GetDefaultDevOptions())
			);
			$this->_devoption = explode(',', $this->_devoption);
		}
		return in_array($option, $this->_devoption);
	}
	
	/**
	 * Sets whether a developer option is enabled or not.
	 * @param string $option See self::OPT_DEV_* constants.
	 * @param boolean $enabled If option should be enabled or not.
	 */
	public function SetDevOptionEnabled($option, $enabled){
		// make sure options have been loaded
		$this->IsDevOptionEnabled('');
		// remove option if it exists
		while(($p = array_search($option, $this->_devoption)) !== false)
			unset($this->_devoption[$p]);
		// add option if callee wants it enabled
		if($enabled)
			$this->_devoption[] = $option;
		// commit option
		$this->SetGlobalOption(
			self::OPT_PRFX . 'dev-options',
			implode(',', $this->_devoption)
		);
	}
	
	/**
	 * Remove all enabled developer options.
	 */
	public function ClearDevOptions(){
		$this->_devoption = array();
		$this->SetGlobalOption(self::OPT_PRFX . 'dev-options', '');
	}
	
	/**
	 * @return boolean Whether to enable data inspector or not.
	 */
	public function IsDataInspectorEnabled(){
		return $this->IsDevOptionEnabled(self::OPT_DEV_DATA_INSPECTOR);
	}
	
	/**
	 * @return boolean Whether to PHP error logging or not.
	 */
	public function IsPhpErrorLoggingEnabled(){
		return $this->IsDevOptionEnabled(self::OPT_DEV_PHP_ERRORS);
	}
	
	/**
	 * @return boolean Whether to log requests to file or not.
	 */
	public function IsRequestLoggingEnabled(){
		return $this->IsDevOptionEnabled(self::OPT_DEV_REQUEST_LOG);
	}
	
	/**
	 * @return boolean Whether PHP sandbox page is enabled or not.
	 */
	public function IsSandboxPageEnabled(){
		return $this->IsDevOptionEnabled(self::OPT_DEV_SANDBOX_PAGE);
	}
	
	/**
	 * @return boolean Whether dashboard widgets are enabled or not.
	 */
	public function IsWidgetsEnabled(){
		return !$this->GetGlobalOption(self::OPT_PRFX . 'disable-widgets');
	}
	
	/**
	 * @param boolean $newvalue Whether dashboard widgets are enabled or not.
	 */
	public function SetWidgetsEnabled($newvalue){
		$this->SetGlobalOption(self::OPT_PRFX . 'disable-widgets', !$newvalue);
	}
	
	/**
	 * @return boolean Whether alerts in audit log view refresh automatically or not.
	 */
	public function IsRefreshAlertsEnabled(){
		return !$this->GetGlobalOption(self::OPT_PRFX . 'disable-refresh');
	}
	
	/**
	 * @param boolean $newvalue Whether alerts in audit log view refresh automatically or not.
	 */
	public function SetRefreshAlertsEnabled($newvalue){
		$this->SetGlobalOption(self::OPT_PRFX . 'disable-refresh', !$newvalue);
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
			$this->_pruning = $this->GetGlobalOption(self::OPT_PRFX . 'pruning-date');
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
			$this->SetGlobalOption(self::OPT_PRFX . 'pruning-date', $newvalue);
			$this->_pruning = $newvalue;
		}
	}
	
	/**
	 * @return integer Maximum number of alerts to keep.
	 */
	public function GetPruningLimit(){
		$val = (int)$this->GetGlobalOption(self::OPT_PRFX . 'pruning-limit');
		return $val ? $val : $this->GetMaxAllowedAlerts();
	}
	
	/**
	 * @param integer $newvalue The new maximum number of alerts.
	 */
	public function SetPruningLimit($newvalue){
		$newvalue = max(min((int)$newvalue, $this->GetMaxAllowedAlerts()), 1);
		$this->SetGlobalOption(self::OPT_PRFX . 'pruning-limit', $newvalue);
	}
	
	protected $_disabled = null;
	
	public function GetDefaultDisabledAlerts(){
		return array(); //array(0000, 0003, 0005);
	}
	
	/**
	 * @return array IDs of disabled alerts.
	 */
	public function GetDisabledAlerts(){
		if(!$this->_disabled){
			$this->_disabled = implode(',', $this->GetDefaultDisabledAlerts());
			$this->_disabled = $this->GetGlobalOption(self::OPT_PRFX . 'disabled-alerts', $this->_disabled);
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
		$this->SetGlobalOption(self::OPT_PRFX . 'disabled-alerts', implode(',', $this->_disabled));
	}
	
	protected $_viewers = null;
	
	public function SetAllowedPluginViewers($usersOrRoles){
		$this->_viewers = $usersOrRoles;
		$this->SetGlobalOption(self::OPT_PRFX . 'plugin-viewers', implode(',', $this->_viewers));
	}
	
	public function GetAllowedPluginViewers(){
		if(is_null($this->_viewers)){
			$this->_viewers = array_unique(array_filter(explode(',', $this->GetGlobalOption(self::OPT_PRFX . 'plugin-viewers'))));
		}
		return $this->_viewers;
	}
	
	protected $_editors = null;
	
	public function SetAllowedPluginEditors($usersOrRoles){
		$this->_editors = $usersOrRoles;
		$this->SetGlobalOption(self::OPT_PRFX . 'plugin-editors', implode(',', $this->_editors));
	}
	
	public function GetAllowedPluginEditors(){
		if(is_null($this->_editors)){
			$this->_editors = array_unique(array_filter(explode(',', $this->GetGlobalOption(self::OPT_PRFX . 'plugin-editors'))));
		}
		return $this->_editors;
	}
	
	protected $_perpage = null;
	
	public function SetViewPerPage($newvalue){
		$this->_perpage = max($newvalue, 1);
		$this->SetGlobalOption(self::OPT_PRFX . 'items-per-page', $this->_perpage);
	}
	
	public function GetViewPerPage(){
		if(is_null($this->_perpage)){
			$this->_perpage = (int)$this->GetGlobalOption(self::OPT_PRFX . 'items-per-page', 10);
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
	 * @return string[] List of superadmin usernames.
	 */
	protected function GetSuperAdmins(){
		return $this->IsMultisite() ? get_super_admins() : array();
	}
	
	/**
	 * @return string[] List of admin usernames.
	 */
	protected function GetAdmins(){
		if($this->IsMultisite()){
			// see: https://gist.github.com/1508426/65785a15b8638d43a9905effb59e4d97319ef8f8
			global $wpdb;
			$cap = get_current_blog_id();
			$cap = ($cap < 2) ? 'wp_capabilities' : "wp_{$cap}_capabilities";
			$sql = "SELECT DISTINCT $wpdb->users.user_login"
				. " FROM $wpdb->users"
				. " INNER JOIN $wpdb->usermeta ON ($wpdb->users.ID = $wpdb->usermeta.user_id )"
				. " WHERE $wpdb->usermeta.meta_key = '$cap'"
				. " AND CAST($wpdb->usermeta.meta_value AS CHAR) LIKE  '%\"administrator\"%'";
			return $wpdb->get_col($sql);
		}else{
			$result = array();
			$query = 'role=administrator&fields[]=user_login';
			foreach (get_users($query) as $user) $result[] = $user->user_login;
			return $result;
		}
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
			case 'view':
				$allowed = $this->GetAllowedPluginViewers();
				$allowed = array_merge($allowed, $this->GetAllowedPluginEditors());
				$allowed = array_merge($allowed, $this->GetSuperAdmins());
				$allowed = array_merge($allowed, $this->GetAdmins());
				break;
			case 'edit':
				$allowed = $this->GetAllowedPluginEditors();
				$allowed = array_merge($allowed, $this->IsMultisite() ?
						$this->GetSuperAdmins() : $this->GetAdmins()
					);
				break;
			default:
				throw new Exception('Unknown action "'.$action.'".');
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
