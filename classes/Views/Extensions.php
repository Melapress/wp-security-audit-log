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
					
					<div class="">
						<h2><?php _e('Notifications Extension', 'wp-security-audit-log'); ?></h2>
						<strong><?php _e('Get notified instantly via email when important changes are made on your WordPress!', 'wp-security-audit-log'); ?></strong>
						<p><?php _e('The Notifications extensions allows you to easily configure rules so you receive an email when there is a change on WordPress and such rule is triggered. There is no need for you to manually browse through the Audit Lock viewer anymore to keep an eye for a specific change!', 'wp-security-audit-log'); ?></p>
						<a class="button" href="http://www.wpwhitesecurity.com/plugins-premium-extensions/email-notifications-wordpress/" target="_blank"><?php _e('More Information', 'wp-security-audit-log'); ?></a>
					</div>
				</div>
			</div>
		</div><?php
	}
	
}