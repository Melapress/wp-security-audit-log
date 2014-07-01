<?php

class WSAL_Views_Settings extends WSAL_AbstractView {
	
	public function __construct(WpSecurityAuditLog $plugin) {
		parent::__construct($plugin);
		add_action('wp_ajax_AjaxCheckSecurityToken', array($this, 'AjaxCheckSecurityToken'));
	}
	
	public function HasPluginShortcutLink(){
		return true;
	}
	
	public function GetTitle() {
		return __('Settings', 'wp-security-audit-log');
	}
	
	public function GetIcon() {
		return 'dashicons-admin-generic';
	}
	
	public function GetName() {
		return __('Settings', 'wp-security-audit-log');
	}
	
	public function GetWeight() {
		return 3;
	}
	
	protected function GetTokenType($token){
		$users = array();
		foreach(get_users('blog_id=0&fields[]=user_login') as $obj)
			$users[] = $obj->user_login;
		$roles = array_keys(get_editable_roles());
		
		if(in_array($token, $users))return 'user';
		if(in_array($token, $roles))return 'role';
		return 'other';
	}
	
	protected function Save(){
		$this->_plugin->settings->SetPruningDateEnabled(isset($_REQUEST['PruneByDate']));
		$this->_plugin->settings->SetPruningDate($_REQUEST['PruningDate']);
		$this->_plugin->settings->SetPruningLimitEnabled(isset($_REQUEST['PruneByLimit']));
		$this->_plugin->settings->SetPruningLimit($_REQUEST['PruningLimit']);
		$this->_plugin->settings->SetWidgetsEnabled($_REQUEST['EnableDashboardWidgets']);
		$this->_plugin->settings->SetAllowedPluginViewers(isset($_REQUEST['Viewers']) ? $_REQUEST['Viewers'] : array());
		$this->_plugin->settings->SetAllowedPluginEditors(isset($_REQUEST['Editors']) ? $_REQUEST['Editors'] : array());
		$this->_plugin->settings->SetRefreshAlertsEnabled($_REQUEST['EnableAuditViewRefresh']);
		$this->_plugin->settings->SetIncognito(isset($_REQUEST['Incognito']));
		$this->_plugin->settings->ClearDevOptions();
		if(isset($_REQUEST['DevOptions']))
			foreach($_REQUEST['DevOptions'] as $opt)
				$this->_plugin->settings->SetDevOptionEnabled($opt, true);
	}
	
	public function AjaxCheckSecurityToken(){
		if(!$this->_plugin->settings->CurrentUserCan('view'))
			die('Access Denied.');
		if(!isset($_REQUEST['token']))
			die('Token parameter expected.');
		die($this->GetTokenType($_REQUEST['token']));
	}
	
	public function Render(){
		if(!$this->_plugin->settings->CurrentUserCan('edit')){
			wp_die( __( 'You do not have sufficient permissions to access this page.' , 'wp-security-audit-log') );
		}
		if(isset($_POST['submit'])){
			try {
				$this->Save();
				?><div class="updated"><p><?php _e('Settings have been saved.', 'wp-security-audit-log'); ?></p></div><?php
			}catch(Exception $ex){
				?><div class="error"><p><?php _e('Error: ', 'wp-security-audit-log'); ?><?php echo $ex->getMessage(); ?></p></div><?php
			}
		}
		?><form id="audit-log-settings" method="post">
			<input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
			<input type="hidden" id="ajaxurl" value="<?php echo esc_attr(admin_url('admin-ajax.php')); ?>" />
			
			<table class="form-table">
				<tbody>
					<tr>
						<th><label for="delete1"><?php _e('Security Alerts Pruning', 'wp-security-audit-log'); ?></label></th>
						<td>
							<fieldset>
								<?php $text = __('(eg: 1 month)', 'wp-security-audit-log'); ?>
								<?php $nbld = $this->_plugin->settings->IsPruningDateEnabled(); ?>
								<label for="delete1">
									<input type="checkbox" id="delete1" name="PruneByDate" value="1" <?php if($nbld)echo 'checked="checked"'; ?>
										   onchange="jQuery('#PruningDate').attr('readonly', !checked);"/>
									<?php echo __('Delete alerts older than', 'wp-security-audit-log'); ?>
								</label>
								<input type="text" id="PruningDate" name="PruningDate" placeholder="<?php echo $text; ?>" <?php if(!$nbld)echo 'readonly="readonly"'; ?>
									   value="<?php echo esc_attr($this->_plugin->settings->GetPruningDate()); ?>"/>
								<span> <?php echo $text; ?></span>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th></th>
						<td>
							<fieldset>
								<?php $text = __('(eg: 80)', 'wp-security-audit-log'); ?>
								<?php $nbld = $this->_plugin->settings->IsPruningLimitEnabled(); ?>
								<label for="delete2">
									<input type="checkbox" id="delete2" name="PruneByLimit" value="1" <?php if($nbld)echo 'checked="checked"'; ?>
										   onchange="jQuery('#PruningLimit').attr('readonly', !checked);"/>
									<?php echo __('Keep up to', 'wp-security-audit-log'); ?>
								</label>
								<input type="text" id="PruningLimit" name="PruningLimit" placeholder="<?php echo $text;?>" <?php if(!$nbld)echo 'readonly="readonly"'; ?>
									   value="<?php echo esc_attr($this->_plugin->settings->GetPruningLimit()); ?>"/>
								<?php echo __('alerts', 'wp-security-audit-log'); ?>
								<span><?php echo $text; ?></span>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th><label for="dwoption_on"><?php _e('Alerts Dashboard Widget', 'wp-security-audit-log'); ?></label></th>
						<td>
							<fieldset>
								<?php $dwe = $this->_plugin->settings->IsWidgetsEnabled(); ?>
								<label for="dwoption_on">
									<input type="radio" name="EnableDashboardWidgets" id="dwoption_on" style="margin-top: 2px;" <?php if($dwe)echo 'checked="checked"'; ?> value="1">
									<span><?php _e('On', 'wp-security-audit-log'); ?></span>
								</label>
								<br/>
								<label for="dwoption_off">
									<input type="radio" name="EnableDashboardWidgets" id="dwoption_off" style="margin-top: 2px;" <?php if(!$dwe)echo 'checked="checked"'; ?> value="0">
									<span><?php _e('Off', 'wp-security-audit-log'); ?></span>
								</label>
								<br/>
								<p class="description"><?php
									echo sprintf(
											__('Display a dashboard widget with the latest %d security alerts.', 'wp-security-audit-log'),
											$this->_plugin->settings->GetDashboardWidgetMaxAlerts()
										);
								?></p>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th><label for="ViewerQueryBox"><?php _e('Can View Alerts', 'wp-security-audit-log'); ?></label></th>
						<td>
							<fieldset>
								<input type="text" id="ViewerQueryBox" style="float: left; display: block; width: 250px;">
								<input type="button" id="ViewerQueryAdd" style="float: left; display: block;" class="button-primary" value="Add">
								<br style="clear: both;"/>
								<p class="description"><?php
									_e('Users and Roles in this list can view the security alerts', 'wp-security-audit-log');
								?></p>
								<div id="ViewerList"><?php
									foreach($this->_plugin->settings->GetAllowedPluginViewers() as $item){
										?><span class="sectoken-<?php echo $this->GetTokenType($item); ?>">
											<input type="hidden" name="Viewers[]" value="<?php echo esc_attr($item); ?>"/>
											<?php echo esc_html($item); ?>
											<a href="javascript:;" title="Remove">&times;</a>
										</span><?php
									}
								?></div>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th><label for="EditorQueryBox"><?php _e('Can Manage Plugin', 'wp-security-audit-log'); ?></label></th>
						<td>
							<fieldset>
								<input type="text" id="EditorQueryBox" style="float: left; display: block; width: 250px;">
								<input type="button" id="EditorQueryAdd" style="float: left; display: block;" class="button-primary" value="Add">
								<br style="clear: both;"/>
								<p class="description"><?php
									_e('Users and Roles in this list can manage the plugin settings', 'wp-security-audit-log');
								?></p>
								<div id="EditorList"><?php
									foreach($this->_plugin->settings->GetAllowedPluginEditors() as $item){
										?><span class="sectoken-<?php echo $this->GetTokenType($item); ?>">
											<input type="hidden" name="Editors[]" value="<?php echo esc_attr($item); ?>"/>
											<?php echo esc_html($item); ?>
											<a href="javascript:;" title="Remove">&times;</a>
										</span><?php
									}
								?></div>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th><label for="aroption_on"><?php _e('Refresh Audit View', 'wp-security-audit-log'); ?></label></th>
						<td>
							<fieldset>
								<?php $are = $this->_plugin->settings->IsRefreshAlertsEnabled(); ?>
								<label for="aroption_on">
									<input type="radio" name="EnableAuditViewRefresh" id="aroption_on" style="margin-top: 2px;" <?php if($are)echo 'checked="checked"'; ?> value="1">
									<span><?php _e('Automatic', 'wp-security-audit-log'); ?></span>
								</label>
								<span class="description"> &mdash; <?php _e('Refresh Audit View as soon as there are new events.', 'wp-security-audit-log'); ?></span>
								<br/>
								<label for="aroption_off">
									<input type="radio" name="EnableAuditViewRefresh" id="aroption_off" style="margin-top: 2px;" <?php if(!$are)echo 'checked="checked"'; ?> value="0">
									<span><?php _e('Manual', 'wp-security-audit-log'); ?></span>
								</label>
								<span class="description"> &mdash; <?php _e('Refresh Audit View only when page is reloaded.', 'wp-security-audit-log'); ?></span>
								<br/>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th><label><?php _e('Developer Options', 'wp-security-audit-log'); ?></label></th>
						<td>
							<fieldset><?php
								foreach(array(
									WSAL_Settings::OPT_DEV_DATA_INSPECTOR => array('Data Inspector', 'View data logged for each triggered alert.'),
									WSAL_Settings::OPT_DEV_PHP_ERRORS     => array('PHP Errors', 'Enables sensor for alerts generated from PHP.'),
									WSAL_Settings::OPT_DEV_REQUEST_LOG    => array('Request Log', 'Enables logging request to file.'),
									WSAL_Settings::OPT_DEV_SANDBOX_PAGE   => array('Sandbox', 'Enables sandbox for testing PHP code.'),
								) as $opt => $info){
									?><label for="devoption_<?php echo $opt; ?>">
										<input type="checkbox" name="DevOptions[]" id="devoption_<?php echo $opt; ?>" <?php
											if($this->_plugin->settings->IsDevOptionEnabled($opt))echo 'checked="checked"'; ?> value="<?php echo $opt; ?>">
										<span><?php _e($info[0], 'wp-security-audit-log'); ?></span>
										<?php if(isset($info[1]) && $info[1]){ ?>
											<span class="description"> &mdash; <?php _e($info[1], 'wp-security-audit-log'); ?></span>
										<?php }
									?></label><br/><?php
								}
							?></fieldset>
						</td>
					</tr>
					
					<tr>
						<th><label for="Incognito"><?php _e('Hide Plugin from Plugins Page', 'wp-security-audit-log'); ?></label></th>
						<td>
							<fieldset>
								<label for="Incognito">
									<input type="checkbox" name="Incognito" value="1" id="Incognito"<?php
										if($this->_plugin->settings->IsIncognito())echo ' checked="checked"'; ?>/>
									<?php _e('Hide', 'wp-security-audit-log'); ?>
								</label>
							</fieldset>
						</td>
					</tr>
				</tbody>
			</table>
			
			<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes"></p>
		</form><?php
	}
	
	public function Header(){
		wp_enqueue_style(
			'settings',
			$this->_plugin->GetBaseUrl() . '/css/settings.css',
			array(),
			filemtime($this->_plugin->GetBaseDir() . '/css/settings.css')
		);
	}
	
	public function Footer() {
		wp_enqueue_script(
			'settings',
			$this->_plugin->GetBaseUrl() . '/js/settings.js',
			array(),
			filemtime($this->_plugin->GetBaseDir() . '/js/settings.js')
		);
	}
	
}
