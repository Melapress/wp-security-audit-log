<?php

class WSAL_Settings {
	/**
	 * @var WpSecurityAuditLog
	 */
	protected $_plugin;

	public function __construct(WpSecurityAuditLog $plugin){
		$this->_plugin = $plugin;
	}
	
	// <editor-fold desc="Developer Options">

	const OPT_DEV_DATA_INSPECTOR = 'd';
	const OPT_DEV_PHP_ERRORS     = 'p';
	const OPT_DEV_REQUEST_LOG    = 'r';
	const OPT_DEV_BACKTRACE_LOG  = 'b';

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
			$this->_devoption = $this->_plugin->GetGlobalOption(
				'dev-options',
				implode(',', $this->GetDefaultDevOptions())
			);
			$this->_devoption = explode(',', $this->_devoption);
		}
		return in_array($option, $this->_devoption);
	}

	/**
	 * @return boolean Whether any developer option has been enabled or not.
	 */
	public function IsAnyDevOptionEnabled(){
		return !!$this->_plugin->GetGlobalOption('dev-options', null);
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
		$this->_plugin->SetGlobalOption(
			'dev-options',
			implode(',', $this->_devoption)
		);
	}

	/**
	 * Remove all enabled developer options.
	 */
	public function ClearDevOptions(){
		$this->_devoption = array();
		$this->_plugin->SetGlobalOption('dev-options', '');
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
	 * @return boolean Whether to store debug backtrace for PHP alerts or not.
	 */
	public function IsBacktraceLoggingEnabled(){
		return $this->IsDevOptionEnabled(self::OPT_DEV_BACKTRACE_LOG);
	}
	
	// </editor-fold>

	/**
	 * @return boolean Whether dashboard widgets are enabled or not.
	 */
	public function IsWidgetsEnabled(){
		return !$this->_plugin->GetGlobalOption('disable-widgets');
	}

	/**
	 * @param boolean $newvalue Whether dashboard widgets are enabled or not.
	 */
	public function SetWidgetsEnabled($newvalue){
		$this->_plugin->SetGlobalOption('disable-widgets', !$newvalue);
	}

	/**
	 * @return boolean Whether alerts in audit log view refresh automatically or not.
	 */
	public function IsRefreshAlertsEnabled(){
		return !$this->_plugin->GetGlobalOption('disable-refresh');
	}

	/**
	 * @param boolean $newvalue Whether alerts in audit log view refresh automatically or not.
	 */
	public function SetRefreshAlertsEnabled($newvalue){
		$this->_plugin->SetGlobalOption('disable-refresh', !$newvalue);
	}

	/**
	 * @return int Maximum number of alerts to show in dashboard widget.
	 */
	public function GetDashboardWidgetMaxAlerts(){
		return 5;
	}
	
	// <editor-fold desc="Pruning Settings">

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
			$this->_pruning = $this->_plugin->GetGlobalOption('pruning-date');
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
			$this->_plugin->SetGlobalOption('pruning-date', $newvalue);
			$this->_pruning = $newvalue;
		}
	}

	/**
	 * @return integer Maximum number of alerts to keep.
	 */
	public function GetPruningLimit(){
		$val = (int)$this->_plugin->GetGlobalOption('pruning-limit');
		return $val ? $val : $this->GetMaxAllowedAlerts();
	}

	/**
	 * @param integer $newvalue The new maximum number of alerts.
	 */
	public function SetPruningLimit($newvalue){
		$newvalue = max(/*min(*/(int)$newvalue/*, $this->GetMaxAllowedAlerts())*/, 1);
		$this->_plugin->SetGlobalOption('pruning-limit', $newvalue);
	}

	public function SetPruningDateEnabled($enabled){
		$this->_plugin->SetGlobalOption('pruning-date-e', $enabled);
	}

	public function SetPruningLimitEnabled($enabled){
		$this->_plugin->SetGlobalOption('pruning-limit-e', $enabled);
	}

	public function IsPruningDateEnabled(){
		return $this->_plugin->GetGlobalOption('pruning-date-e', true);
	}

	public function IsPruningLimitEnabled(){
		return $this->_plugin->GetGlobalOption('pruning-limit-e', true);
	}

	public function IsRestrictAdmins(){
		return $this->_plugin->GetGlobalOption('restrict-admins', false);
	}

	public function SetRestrictAdmins($enable){
		$this->_plugin->SetGlobalOption('restrict-admins', (bool)$enable);
	}
	
	// </editor-fold>

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
			$this->_disabled = $this->_plugin->GetGlobalOption('disabled-alerts', $this->_disabled);
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
		$this->_plugin->SetGlobalOption('disabled-alerts', implode(',', $this->_disabled));
	}

	public function IsIncognito(){
		return $this->_plugin->GetGlobalOption('hide-plugin');
	}

	public function SetIncognito($enabled){
		return $this->_plugin->SetGlobalOption('hide-plugin', $enabled);
	}
	
	// <editor-fold desc="Access Control">
	
	protected $_viewers = null;

	public function SetAllowedPluginViewers($usersOrRoles){
		$this->_viewers = $usersOrRoles;
		$this->_plugin->SetGlobalOption('plugin-viewers', implode(',', $this->_viewers));
	}

	public function GetAllowedPluginViewers(){
		if(is_null($this->_viewers)){
			$this->_viewers = array_unique(array_filter(explode(',', $this->_plugin->GetGlobalOption('plugin-viewers'))));
		}
		return $this->_viewers;
	}

	protected $_editors = null;

	public function SetAllowedPluginEditors($usersOrRoles){
		$this->_editors = $usersOrRoles;
		$this->_plugin->SetGlobalOption('plugin-editors', implode(',', $this->_editors));
	}

	public function GetAllowedPluginEditors(){
		if(is_null($this->_editors)){
			$this->_editors = array_unique(array_filter(explode(',', $this->_plugin->GetGlobalOption('plugin-editors'))));
		}
		return $this->_editors;
	}

	protected $_perpage = null;

	public function SetViewPerPage($newvalue){
		$this->_perpage = max($newvalue, 1);
		$this->_plugin->SetGlobalOption('items-per-page', $this->_perpage);
	}

	public function GetViewPerPage(){
		if(is_null($this->_perpage)){
			$this->_perpage = (int)$this->_plugin->GetGlobalOption('items-per-page', 10);
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
		return $this->_plugin->IsMultisite() ? get_super_admins() : array();
	}

	/**
	 * @return string[] List of admin usernames.
	 */
	protected function GetAdmins(){
		if($this->_plugin->IsMultisite()){
			// see: https://gist.github.com/1508426/65785a15b8638d43a9905effb59e4d97319ef8f8
			global $wpdb;
			$cap = $wpdb->prefix."capabilities";
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
	 * Returns access tokens for a particular action.
	 * @param string $action Type of action.
	 * @return string[] List of tokens (usernames, roles etc).
	 */
	public function GetAccessTokens($action){
		$allowed = array();

		switch($action){
			case 'view':
				$allowed = $this->GetAllowedPluginViewers();
				$allowed = array_merge($allowed, $this->GetAllowedPluginEditors());
				if (!$this->IsRestrictAdmins()) {
					$allowed = array_merge($allowed, $this->GetSuperAdmins());
					$allowed = array_merge($allowed, $this->GetAdmins());
				}
				break;
			case 'edit':
				$allowed = $this->GetAllowedPluginEditors();
				if (!$this->IsRestrictAdmins()) {
					$allowed = array_merge($allowed, $this->_plugin->IsMultisite() ?
							$this->GetSuperAdmins() : $this->GetAdmins()
						);
				}
				break;
			default:
				throw new Exception('Unknown action "'.$action.'".');
		}

		if (!$this->IsRestrictAdmins()) {
			if(is_multisite()){
				$allowed = array_merge($allowed, get_super_admins());
			}else{
				$allowed[] = 'administrator';
			}
		}

		return array_unique($allowed);
	}

	/**
	 * @param integer|WP_user $user User object to check.
	 * @param string $action Type of action, either 'view' or 'edit'.
	 * @return boolean If user has access or not.
	 */
	public function UserCan($user, $action){
		if(is_int($user))$user = get_userdata($user);

		$allowed = $this->GetAccessTokens($action);

		$check = array_merge(
			$user->roles,
			array($user->user_login)
		);

		foreach($check as $item){
			if(in_array($item, $allowed)){
				return true;
			}
		}
		return false;
	}

	public function GetCurrentUserRoles($baseRoles = null){
		if ($baseRoles == null) $baseRoles = wp_get_current_user()->roles;
		if (function_exists('is_super_admin') && is_super_admin()) $baseRoles[] = 'superadmin';
		return $baseRoles;
	}
	
	// </editor-fold>
	
	// <editor-fold desc="Licensing">

	public function GetLicenses(){
		return $this->_plugin->GetGlobalOption('licenses');
	}

	public function GetLicense($name){
		$data = $this->GetLicenses();
		$name = sanitize_key(basename($name));
		return isset($data[$name]) ? $data[$name] : array();
	}

	public function SetLicenses($data){
		$this->_plugin->SetGlobalOption('licenses', $data);
	}

	public function GetLicenseKey($name){
		$data = $this->GetLicense($name);
		return isset($data['key']) ? $data['key'] : '';
	}

	public function GetLicenseStatus($name){
		$data = $this->GetLicense($name);
		return isset($data['sts']) ? $data['sts'] : '';
	}

	public function GetLicenseErrors($name){
		$data = $this->GetLicense($name);
		return isset($data['err']) ? $data['err'] : '';
	}

	public function SetLicenseKey($name, $key){
		$data = $this->GetLicenses();
		if (!isset($data[$name])) $data[$name] = array();
		$data[$name]['key'] = $key;
		$this->SetLicenses($data);
	}

	public function SetLicenseStatus($name, $status){
		$data = $this->GetLicenses();
		if (!isset($data[$name])) $data[$name] = array();
		$data[$name]['sts'] = $status;
		$this->SetLicenses($data);
	}

	public function SetLicenseErrors($name, $errors){
		$data = $this->GetLicenses();
		if (!isset($data[$name])) $data[$name] = array();
		$data[$name]['err'] = $errors;
		$this->SetLicenses($data);
	}

	public function ClearLicenses(){
		$this->SetLicenses(array());
	}
	
	// </editor-fold>
	
	// <editor-fold desc="Client IP Retrieval">
	
	public function IsMainIPFromProxy(){
		return $this->_plugin->GetGlobalOption('use-proxy-ip');
	}

	public function SetMainIPFromProxy($enabled){
		return $this->_plugin->SetGlobalOption('use-proxy-ip', $enabled);
	}
	
	public function IsInternalIPsFiltered(){
		return $this->_plugin->GetGlobalOption('filter-internal-ip');
	}

	public function SetInternalIPsFiltering($enabled){
		return $this->_plugin->SetGlobalOption('filter-internal-ip', $enabled);
	}
	
	public function GetMainClientIP(){
		$result = null;
		if ($this->IsMainIPFromProxy()) {
			// TODO the algorithm below just gets the first IP in the list...we might want to make this more intelligent somehow
			$result = $this->GetClientIPs();
			$result = reset($result);
			$result = isset($result[0]) ? $result[0] : null;
		}elseif(isset($_SERVER['REMOTE_ADDR'])){
			$result = $this->NormalizeIP($_SERVER['REMOTE_ADDR']);
			if (!$this->ValidateIP($result)) $result = null;
		}
		return $result;
	}
	
	public function GetClientIPs(){
		$ips = array();
		foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key) {
			if (isset($_SERVER[$key])) {
				$ips[$key] = array();
				foreach (explode(',', $_SERVER[$key]) as $ip)
					if ($this->ValidateIP($ip = $this->NormalizeIP($ip)))
						$ips[$key][] = $ip;
			}
		}
		return $ips;
	}
	
	protected function NormalizeIP($ip){
		$ip = trim($ip);
		if(strpos($ip, ':') !== false && strpos($ip, '[') === false){
			// IPv4 with a port (eg: 11.22.33.44:80)
			$ip = explode(':', $ip);
			$ip = $ip[0];
		}else{
			// IPv6 with a port (eg: [::1]:80)
			$ip = explode(']', $ip);
			$ip = ltrim($ip[0], '[');
		}
		return $ip;
	}
	
	protected function ValidateIP($ip){
		$opts = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;
		if ($this->IsInternalIPsFiltered()) $opts = $opts | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
		return filter_var($ip, FILTER_VALIDATE_IP, $opts);
	}
	
	// </editor-fold>
}
