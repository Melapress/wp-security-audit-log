<?php

class WSAL_Views_Settings extends WSAL_AbstractView {
	
	public function __construct(WpSecurityAuditLog $plugin) {
		parent::__construct($plugin);
		if (is_admin()) {
			add_action('wp_ajax_AjaxCheckSecurityToken', array($this, 'AjaxCheckSecurityToken'));
			add_action('wp_ajax_AjaxRunCleanup', array($this, 'AjaxRunCleanup'));
		}
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
		check_admin_referer('wsal-settings');
		$this->_plugin->settings->SetPruningDateEnabled($_REQUEST['PruneBy'] == 'date');
		$this->_plugin->settings->SetPruningDate($_REQUEST['PruningDate']);
		$this->_plugin->settings->SetPruningLimitEnabled($_REQUEST['PruneBy'] == 'limit');
		$this->_plugin->settings->SetPruningLimit($_REQUEST['PruningLimit']);
		$this->_plugin->settings->SetWidgetsEnabled($_REQUEST['EnableDashboardWidgets']);
		$this->_plugin->settings->SetAllowedPluginViewers(isset($_REQUEST['Viewers']) ? $_REQUEST['Viewers'] : array());
		$this->_plugin->settings->SetAllowedPluginEditors(isset($_REQUEST['Editors']) ? $_REQUEST['Editors'] : array());
		$this->_plugin->settings->SetRestrictAdmins(isset($_REQUEST['RestrictAdmins']));
		$this->_plugin->settings->SetRefreshAlertsEnabled($_REQUEST['EnableAuditViewRefresh']);
		$this->_plugin->settings->SetMainIPFromProxy($_REQUEST['EnableProxyIpCapture']);
		$this->_plugin->settings->SetInternalIPsFiltering($_REQUEST['EnableIpFiltering']);
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
	
	public function AjaxRunCleanup(){
		if(!$this->_plugin->settings->CurrentUserCan('view'))
			die('Access Denied.');
		$this->_plugin->CleanUp();
		wp_redirect($this->GetUrl());
		exit;
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
			<?php wp_nonce_field('wsal-settings'); ?>
			
			<div id="audit-log-adverts">
				<a href="http://www.wpwhitesecurity.com/plugins-premium-extensions/email-notifications-wordpress/?utm_source=plugin&utm_medium=settingspage&utm_campaign=notifications">
					<img src="<?php echo $this->_plugin->GetBaseUrl(); ?>/img/notifications_250x150.gif" width="250" height="150" alt=""/>
				</a>
				<a href="http://www.wpwhitesecurity.com/plugins-premium-extensions/search-filtering-extension/?utm_source=plugin&utm_medium=settingspage&utm_campaign=search">
					<img src="<?php echo $this->_plugin->GetBaseUrl(); ?>/img/search_250x150.gif" width="250" height="150" alt=""/>
				</a>
				<a href="http://www.wpwhitesecurity.com/plugins-premium-extensions/wordpress-reports-extension/?utm_source=plugin&utm_medium=settingspage&utm_campaign=reports">
					<img src="<?php echo $this->_plugin->GetBaseUrl(); ?>/img/reporting_250x150.gif" width="250" height="150" alt=""/>
				</a>
			</div>
			
			<table class="form-table">
				<tbody>
					<tr>
						<th><label for="delete1"><?php _e('Security Alerts Pruning', 'wp-security-audit-log'); ?></label></th>
						<td>
							<fieldset>
								<?php $text = __('(eg: 1 month)', 'wp-security-audit-log'); ?>
								<?php $nbld = !($this->_plugin->settings->IsPruningDateEnabled() || $this->_plugin->settings->IsPruningLimitEnabled()); ?>
								<label for="delete0">
									<input type="radio" id="delete0" name="PruneBy" value="" <?php if($nbld)echo 'checked="checked"'; ?>/>
									<?php echo __('None', 'wp-security-audit-log'); ?>
								</label>
							</fieldset>
							<fieldset>
								<?php $text = __('(eg: 1 month)', 'wp-security-audit-log'); ?>
								<?php $nbld = $this->_plugin->settings->IsPruningDateEnabled(); ?>
								<label for="delete1">
									<input type="radio" id="delete1" name="PruneBy" value="date" <?php if($nbld)echo 'checked="checked"'; ?>/>
									<?php echo __('Delete alerts older than', 'wp-security-audit-log'); ?>
								</label>
								<input type="text" id="PruningDate" name="PruningDate" placeholder="<?php echo $text; ?>"
									   value="<?php echo esc_attr($this->_plugin->settings->GetPruningDate()); ?>"
									   onfocus="jQuery('#delete1').attr('checked', true);"/>
								<span> <?php echo $text; ?></span>
							</fieldset>
							<fieldset>
								<?php $text = __('(eg: 80)', 'wp-security-audit-log'); ?>
								<?php $nbld = $this->_plugin->settings->IsPruningLimitEnabled(); ?>
								<label for="delete2">
									<input type="radio" id="delete2" name="PruneBy" value="limit" <?php if($nbld)echo 'checked="checked"'; ?>/>
									<?php echo __('Keep up to', 'wp-security-audit-log'); ?>
								</label>
								<input type="text" id="PruningLimit" name="PruningLimit" placeholder="<?php echo $text;?>"
									   value="<?php echo esc_attr($this->_plugin->settings->GetPruningLimit()); ?>"
									   onfocus="jQuery('#delete2').attr('checked', true);"/>
								<?php echo __('alerts', 'wp-security-audit-log'); ?>
								<span><?php echo $text; ?></span>
							</fieldset>
							<p class="description"><?php
								echo __('Next Scheduled Cleanup is in ', 'wp-security-audit-log');
								echo human_time_diff(current_time('timestamp'), $next = wp_next_scheduled('wsal_cleanup'));
								echo '<!-- ' . date('dMy H:i:s', $next) . ' --> ';
								echo sprintf(
										__('(or %s)', 'wp-security-audit-log'),
										'<a href="' . admin_url('admin-ajax.php?action=AjaxRunCleanup') . '">' . __('Run Manually', 'wp-security-audit-log') . '</a>'
									);
							?></p>
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
						<th><label for="pioption_on"><?php _e('Reverse Proxy / Firewall Options', 'wp-security-audit-log'); ?></label></th>
						<td>
							<fieldset>
								<label for="EnableProxyIpCapture">
									<input type="checkbox" name="EnableProxyIpCapture" value="1" id="EnableProxyIpCapture"<?php
										if($this->_plugin->settings->IsMainIPFromProxy())echo ' checked="checked"';
									?>/> <?php _e('WordPress running behind firewall or proxy', 'wp-security-audit-log'); ?><br/>
									<span class="description"><?php _e('Enable this option if your WordPress is running behind a firewall or reverse proxy. When this option is enabled the plugin will retrieve the user\'s IP address from the proxy header.', 'wp-security-audit-log'); ?></span>
								</label>
								<br/>
								<label for="EnableIpFiltering">
									<input type="checkbox" name="EnableIpFiltering" value="1" id="EnableIpFiltering"<?php
										if($this->_plugin->settings->IsInternalIPsFiltered())echo ' checked="checked"';
									?>/> <?php _e('Filter Internal IP Addresses', 'wp-security-audit-log'); ?><br/>
									<span class="description"><?php _e('Enable this option to filter internal IP addresses from the proxy headers.', 'wp-security-audit-log'); ?></span>
								</label>	
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
						<th><label for="RestrictAdmins"><?php _e('Restrict Plugin Access', 'wp-security-audit-log'); ?></label></th>
						<td>
							<fieldset>
								<input type="hidden" id="RestrictAdminsDefaultUser" value="<?php echo esc_attr(wp_get_current_user()->user_login); ?>"/>
								<label for="RestrictAdmins">
									<?php $ira = $this->_plugin->settings->IsRestrictAdmins(); ?>
									<input type="checkbox" name="RestrictAdmins" id="RestrictAdmins"<?php if($ira)echo ' checked="checked"'; ?>/>
									<span class="description">
										<?php _e('By default all the administrators on this WordPress have access to manage this plugin.<br/>By enabling this option only the users specified in the two options above and your username will have access to view alerts and manage this plugin.', 'wp-security-audit-log'); ?>
									</span>
								</label>
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
							<fieldset>
								<?php $any = $this->_plugin->settings->IsAnyDevOptionEnabled(); ?>
								<a href="javascript:;" style="<?php if($any)echo 'display: none;'; ?>"
								   onclick="jQuery(this).hide().next().show();">Show Developer Options</a>
								<div style="<?php if(!$any)echo 'display: none;'; ?>">
									<p style="border-left: 3px solid #FFD000; padding: 2px 8px; margin-left: 6px; margin-bottom: 16px;"><?php
										_e('Only enable these options on testing, staging and development websites. Enabling any of the settings below on LIVE websites may cause unintended side-effects including degraded performance.', 'wp-security-audit-log');
									?></p><?php
									foreach(array(
										WSAL_Settings::OPT_DEV_DATA_INSPECTOR => array(
											__('Data Inspector', 'wp-security-audit-log'),
											__('View data logged for each triggered alert.', 'wp-security-audit-log')
										),
										WSAL_Settings::OPT_DEV_PHP_ERRORS     => array(
											__('PHP Errors', 'wp-security-audit-log'),
											__('Enables sensor for alerts generated from PHP.', 'wp-security-audit-log')
										),
										WSAL_Settings::OPT_DEV_REQUEST_LOG    => array(
											__('Request Log', 'wp-security-audit-log'),
											__('Enables logging request to file.', 'wp-security-audit-log')
										),
										WSAL_Settings::OPT_DEV_BACKTRACE_LOG  => array(
											__('Backtrace', 'wp-security-audit-log'),
											__('Log full backtrace for PHP-generated alerts.', 'wp-security-audit-log')
										),
									) as $opt => $info){
										?><label for="devoption_<?php echo $opt; ?>">
											<input type="checkbox" name="DevOptions[]" id="devoption_<?php echo $opt; ?>" <?php
												if($this->_plugin->settings->IsDevOptionEnabled($opt))echo 'checked="checked"'; ?> value="<?php echo $opt; ?>">
											<span><?php echo $info[0]; ?></span>
											<?php if(isset($info[1]) && $info[1]){ ?>
												<span class="description"> &mdash; <?php echo $info[1]; ?></span>
											<?php }
										?></label><br/><?php
									}
								?></div>
							</fieldset>
						</td>
					</tr>
					
					<tr>
						<th><label for="Incognito"><?php _e('Hide Plugin from Plugins Page', 'wp-security-audit-log'); ?></label></th>
						<td>
							<fieldset>
								<label for="Incognito">
									<input type="checkbox" name="Incognito" value="1" id="Incognito"<?php
										if($this->_plugin->settings->IsIncognito())echo ' checked="checked"';
									?>/> <?php _e('Hide', 'wp-security-audit-log'); ?>
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
