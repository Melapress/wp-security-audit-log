<?php

class WSAL_Views_Help extends WSAL_AbstractView {
	
	public function GetTitle() {
		return __('Help', 'wp-security-audit-log');
	}
	
	public function GetIcon() {
		return 'dashicons-sos';
	}
	
	public function GetName() {
		return __('Help', 'wp-security-audit-log');
	}
	
	public function GetWeight() {
		return 5;
	}
	
	public function Render(){
		?><div class="metabox-holder" style="position: relative;">
		
			<div class="postbox" style="margin-right: 270px;">
				<h3 class="hndl"><span>Help</span></h3>
				<div class="inside">
					<div class="activity-block">
						<h2>Plugin Support</h2>
						<p>
							Have you encountered or noticed any issues while using WP Security Audit Log plugin?
							Or you want to report something to us? Click any of the options below to post on the plugin's forum or contact our support directly.
						</p><p>
							<a class="button" href="http://wordpress.org/support/plugin/wp-security-audit-log" target="_blank">Free Support Forum</a>
							&nbsp;&nbsp;&nbsp;&nbsp;
							<a class="button" href="mailto:plugins@wpwhitesecurity.com" target="_blank">Free Support Email</a>
						</p>
					</div>

					<div class="activity-block">
						<h2>Plugin Documentation</h2>
						<p>
							For more detailed information about WP Security Audit Log you can visit the official plugin page.
							You can also visit the official list of WordPress Security Alerts for more information about all of the activity you can monitor with WP Security Audit Log.
						</p><p>
							<a class="button" href="http://www.wpwhitesecurity.com/wordpress-security-plugins/wp-security-audit-log/?utm_source=wpsalabt&utm_medium=txtlink&utm_campaign=wpsal" target="_blank">Official Plugin Page</a>
							&nbsp;&nbsp;&nbsp;&nbsp;
							<a class="button" href="http://www.wpwhitesecurity.com/wordpress-security-plugins/wp-security-audit-log/security-audit-alerts-logs/?utm_source=wpsalabt&utm_medium=txtlink&utm_campaign=wpsal" target="_blank">List of WordPress Security Alerts</a>
						</p>
					</div>

					<div class="activity-block">
						<h2>Need Help Securing WordPress?</h2>
						<p>
							Is your WordPress website hackable?
							If you are not sure contact our WordPress security professionals to audit your WordPress or to simply secure your WordPress website.
							Click on any of the below service buttons for more information.
						</p><p>
							<a class="button" href="http://www.wpwhitesecurity.com/wordpress-security-services/wordpress-security-hardening/?utm_source=wpsalabt&utm_medium=txtlink&utm_campaign=wpsal" target="_blank">WordPress Security Hardening</a>
							&nbsp;&nbsp;&nbsp;&nbsp;
							<a class="button" href="http://www.wpwhitesecurity.com/wordpress-security-services/wordpress-security-audit/?utm_source=wpsalabt&utm_medium=txtlink&utm_campaign=wpsal" target="_blank">WordPress Security Audit</a>
						</p>
					</div>

					<div class="">
						<h2>WordPress Security Readings</h2>
						<p>
							New to WordPress security?
							Do not know from where to start or which is the best services for you?
							Visit our WordPress security blog or the WordPress Security category directly for more information and a number of tips and tricks about WordPress security.
						</p>
						<a class="button" href="http://www.wpwhitesecurity.com/blog/?utm_source=wpsalabt&utm_medium=txtlink&utm_campaign=wpsal" target="_blank">WP White Security Blog</a>
						&nbsp;&nbsp;&nbsp;&nbsp;
						<a class="button" href="http://www.wpwhitesecurity.com/wordpress-security/?utm_source=wpsalabt&utm_medium=txtlink&utm_campaign=wpsal" target="_blank">WordPress Security Category</a>
					</div>
				</div>
			</div>

			<div style="position: absolute; right: 70px; width: 180px; top: 10px;">
				<div class="postbox">
					<h3 class="hndl"><span>WP Password Policy Manager</span></h3>
					<div class="inside">
						<p>
							Easily configure WordPress password policies and ensure users use strong passwords with our plugin WP Password Policy Manager.
						</p>
						<a class="button button-primary" href="http://wordpress.org/plugins/wp-password-policy-manager/" target="_blank">Download</a>
					</div>
				</div>
				<div class="postbox">
					<h3 class="hndl"><span>WP Security Audit Log in your Language!</span></h3>
					<div class="inside">
						If you are interested in translating our plugin please drop us an email on
						<a href="mailto:plugins@wpwhitesecurity.com">plugins@wpwhitesecurity.com</a>.
					</div>
				</div>
			</div>
			
		</div><?php
	}
	
}