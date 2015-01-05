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
						<p><?php _e('The below extensions allow you to extend the functionality of WP Security Audit Log plugin thus enabling you to get more benefits out of the WordPress security audit, such as configurable WordPress email alerts, WordPress security alerts search and user activity reports.', 'wp-security-audit-log'); ?></p>
					</div>
					
					<div class="activity-block">
						<h2><?php _e('WordPress Email Notifications Extension', 'wp-security-audit-log'); ?></h2>
						<strong><?php _e('Get notified instantly via email when important changes are made on your WordPress!', 'wp-security-audit-log'); ?></strong>
						<p><?php _e('With the Notifications Extension you can easily configure monitoring rules so when a specific change happens on your WordPress you are alerted via email. For example you can configure rules to receive an email when existing content is changed, when a new user is created or when someone logs in to WordPress outside normal office hours or from an odd location.', 'wp-security-audit-log'); ?></p>
						<p><a class="button" href="http://www.wpwhitesecurity.com/plugins-premium-extensions/email-notifications-wordpress/?utm_source=plugin&utm_medium=extensionspage&utm_campaign=notifications" target="_blank"><?php _e('More Information', 'wp-security-audit-log'); ?></a></p>
					</div>
					
					<div class="activity-block">
						<h2><?php _e('Security Alerts Search Extension', 'wp-security-audit-log'); ?></h2>
						<strong><?php _e('Automatically Search for specific WordPress user and site activity in WordPress Security Audit Log.', 'wp-security-audit-log'); ?></strong>
						<p><?php _e('The Search Extension enables you to easily find specific WordPress activity in the Audit Log with free text based searches. Filters can also be used in conjunction with free text based searches to further narrow down and have more accurate search results.', 'wp-security-audit-log'); ?></p>
						<p><a class="button" href="http://www.wpwhitesecurity.com/plugins-premium-extensions/search-filtering-extension/?utm_source=plugin&utm_medium=extensionspage&utm_campaign=search" target="_blank"><?php _e('More Information', 'wp-security-audit-log'); ?></a></p>
					</div>
					
					<div class="activity-block">
						<h2><?php _e('Reporting Extension', 'wp-security-audit-log'); ?></h2>
						<strong><?php _e('Generate User, Site and Other Types of Reports from the Audit Log.', 'wp-security-audit-log'); ?></strong>
						<p><?php _e('The Reporting Extension allows you to generate reports to keep track and record of the productivity, and to meet any legal and regulatory compliance your business need to adhere to. Unlike other reporting plugins WSAL Reporting Extension does not have any built-in templates that restrict you to specific type of reports, you can generate any type of report using all of the available data.', 'wp-security-audit-log'); ?></p>
						<p><a class="button" href="http://www.wpwhitesecurity.com/plugins-premium-extensions/wordpress-reports/?utm_source=plugin&utm_medium=extensionspage&utm_campaign=reports" target="_blank"><?php _e('More Information', 'wp-security-audit-log'); ?></a></p>
					</div>
				</div>
			</div>
		</div><?php
	}
	
}