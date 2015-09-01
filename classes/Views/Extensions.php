<?php

class WSAL_Views_Extensions extends WSAL_AbstractView {
	
	public function GetTitle() {
		return __('WP Security Audit Log Add-Ons', 'wp-security-audit-log');
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
						<p><?php _e('The below add-ons allow you to extend the functionality of WP Security Audit Log plugin and enable you to get more benefits out of the WordPress security audit, such as configurable email alerts, ability to search using free text based searches & filters, and generate user activity reports to meet regulatory compliance requirements.', 'wp-security-audit-log'); ?></p>
					</div>
					
					<div class="activity-block">
						<h2><?php _e('Email Notifications Add-On', 'wp-security-audit-log'); ?></h2>
						<strong><?php _e('Get notified instantly via email when important changes are made on your WordPress!', 'wp-security-audit-log'); ?></strong>
						<p><?php _e('With the Email Notifications Add-On you can easily configure trigger rules so when a specific change happens on your WordPress you are instantly alerted via email. For example you can configure rules to receive an email when existing content is changed, when a new user is created or when someone logs in to WordPress outside normal office hours or from an odd location.', 'wp-security-audit-log'); ?></p>
						<p><a class="button" href="http://www.wpsecurityauditlog.com/extensions/wordpress-email-notifications-add-on/?utm_source=plugin&utm_medium=extensionspage&utm_campaign=notifications" target="_blank"><?php _e('More Information', 'wp-security-audit-log'); ?></a></p>
					</div>
					
					<div class="activity-block">
						<h2><?php _e('External DB Add-On', 'wp-security-audit-log'); ?></h2>
						<strong><?php _e('Save the WordPress Audit Log in an external database.', 'wp-security-audit-log'); ?></strong>
						<p><?php _e('By saving the WordPress Audit Log in an external database you improve the security and performance of your WordPress websites and blogs. You also ensure that your WordPress is compliant to a number of mandatory regulatory compliance requirements business websites need to adhere to.', 'wp-security-audit-log'); ?></p>
						<p><a class="button" href="http://www.wpsecurityauditlog.com/extensions/external-database-for-wp-security-audit-log/?utm_source=plugin&utm_medium=extensionspage&utm_campaign=externaldb" target="_blank"><?php _e('More Information', 'wp-security-audit-log'); ?></a></p>
					</div>
					
					<div class="activity-block">
						<h2><?php _e('Search Add-On', 'wp-security-audit-log'); ?></h2>
						<strong><?php _e('Automatically Search for specific WordPress user and site activity in WordPress Security Audit Log.', 'wp-security-audit-log'); ?></strong>
						<p><?php _e('The Search Add-On enables you to easily find specific WordPress activity in the Audit Log with free-text based searches. Filters can also be used in conjunction with free-text based searches to fine tune the search and find what you are looking for easily.', 'wp-security-audit-log'); ?></p>
						<p><a class="button" href="http://www.wpsecurityauditlog.com/extensions/search-add-on-for-wordpress-security-audit-log/?utm_source=plugin&utm_medium=extensionspage&utm_campaign=search" target="_blank"><?php _e('More Information', 'wp-security-audit-log'); ?></a></p>
					</div>
					
					<div class="activity-block">
						<h2><?php _e('Reports Add-Ons', 'wp-security-audit-log'); ?></h2>
						<strong><?php _e('Generate User, Site and Regulatory Compliance Reports.', 'wp-security-audit-log'); ?></strong>
						<p><?php _e('The Reports Add-On allows you to generate reports and keep track and record of user productivity, and meet any legal and regulatory compliance your business need to adhere to. Unlike other reporting plugins the Reports Add-On does not have any built-in templates that restrict you to specific type of reports, you can generate any type of report using all of the available data.', 'wp-security-audit-log'); ?></p>
						<p><a class="button" href="http://www.wpsecurityauditlog.com/extensions/compliance-reports-add-on-for-wordpress/?utm_source=plugin&utm_medium=extensionspage&utm_campaign=reports" target="_blank"><?php _e('More Information', 'wp-security-audit-log'); ?></a></p>
					</div>
				</div>
			</div>
		</div><?php
	}
	
}