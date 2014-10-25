<?php

class WSAL_Views_Extensions extends WSAL_AbstractView {
	
	public function GetTitle() {
		return __('WP Security Audit Log Functionality Extensions', 'wp-security-audit-log');
	}
	
	public function GetIcon() {
		return 'admin-plugins';
	}
	
	public function GetName() {
		return __('Extensions', 'wp-security-audit-log');
	}
	
	public function GetWeight() {
		return 3.5;
	}
	
	public function Render(){
		?><div class="metabox-holder" style="position: relative;">
		
			<div class="postbox" style="margin-right: 270px;">
				<div class="inside">
					<div class="activity-block">
						<h2><?php _e('Extend the functionality of your WP Security Audit Log plugin', 'wp-security-audit-log'); ?></h2>
						<p><?php _e('Below is a list of extensions that allow you to extend the functionality of WP Security Audit Log plugin for a much better auditing and monitoring experience.', 'wp-security-audit-log'); ?></p>
					</div>
					
					<div class="activity-block">
						<h2><?php _e('Notifications Extension', 'wp-security-audit-log'); ?></h2>
						<strong><?php _e('Get notified instantly via email when important changes are made on your WordPress!', 'wp-security-audit-log'); ?></strong>
						<p><?php _e('The Notifications Extension allows you to easily configure rules to receive an email when there is a change on WordPress. You do not need manually browse through the Audit Lock viewer anymore when looking for a specific change and the good thing is that you will be instantly alerted when it happens!', 'wp-security-audit-log'); ?></p>
						<p><a class="button" href="http://www.wpwhitesecurity.com/plugins-premium-extensions/email-notifications-wordpress/?utm_source=plugin&utm_medium=extensionspage&utm_campaign=notifications" target="_blank"><?php _e('More Information', 'wp-security-audit-log'); ?></a></p>
					</div>
					
					<div class="">
						<h2><?php _e('Search and Filtering Extension', 'wp-security-audit-log'); ?></h2>
						<strong><?php _e('Search for specific alerts by using a free text search or filters.', 'wp-security-audit-log'); ?></strong>
						<p><?php _e('The Search and Filtering Extension enables you to easily search for a specific WordPress security alert through the Audit Log viewer. You can use a free text based search, i.e. by simply entering a term for which to look for. Alternatively you can use the filtering rules which allow you to filter WordPress security alerts by date, username, Alert ID and more.', 'wp-security-audit-log'); ?></p>
					</div>
				</div>
			</div>
		</div><?php
	}
	
}