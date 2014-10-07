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
				<div class="inside">
					<div class="activity-block">
						<h2><?php _e('Plugin Support', 'wp-security-audit-log'); ?></h2>
						<p>
							<?php _e('Have you encountered or noticed any issues while using WP Security Audit Log plugin?', 'wp-security-audit-log'); ?>
							<?php _e('Or you want to report something to us? Click any of the options below to post on the plugin\'s forum or contact our support directly.', 'wp-security-audit-log'); ?>
						</p><p>
							<a class="button" href="http://wordpress.org/support/plugin/wp-security-audit-log" target="_blank"><?php _e('Free Support Forum', 'wp-security-audit-log'); ?></a>
							&nbsp;&nbsp;&nbsp;&nbsp;
							<a class="button" href="mailto:plugins@wpwhitesecurity.com" target="_blank"><?php _e('Free Support Email', 'wp-security-audit-log'); ?></a>
						</p>
					</div>

					<div class="activity-block">
						<h2><?php _e('Plugin Documentation', 'wp-security-audit-log'); ?></h2>
						<p>
							<?php _e('For more detailed information about WP Security Audit Log you can visit the official plugin page.', 'wp-security-audit-log'); ?>
							<?php _e('You can also visit the official list of WordPress Security Alerts for more information about all of the activity you can monitor with WP Security Audit Log.', 'wp-security-audit-log'); ?>
						</p><p>
							<a class="button" href="http://www.wpwhitesecurity.com/wordpress-security-plugins/wp-security-audit-log/?utm_source=wpsalabt&utm_medium=txtlink&utm_campaign=wpsal" target="_blank"><?php _e('Official Plugin Page', 'wp-security-audit-log'); ?></a>
							&nbsp;&nbsp;&nbsp;&nbsp;
							<a class="button" href="http://www.wpwhitesecurity.com/wordpress-security-plugins/wp-security-audit-log/security-audit-alerts-logs/?utm_source=wpsalabt&utm_medium=txtlink&utm_campaign=wpsal" target="_blank"><?php _e('List of WordPress Security Alerts', 'wp-security-audit-log'); ?></a>
						</p>
					</div>

					<div class="activity-block">
						<h2><?php _e('Need Help Securing WordPress?', 'wp-security-audit-log'); ?></h2>
						<p>
							<?php _e('Is your WordPress website hackable?', 'wp-security-audit-log'); ?>
							<?php _e('If you are not sure contact our WordPress security professionals to audit your WordPress or to simply secure your WordPress website.', 'wp-security-audit-log'); ?>
							<?php _e('Click on any of the below service buttons for more information.', 'wp-security-audit-log'); ?>
						</p><p>
							<a class="button" href="http://www.wpwhitesecurity.com/wordpress-security-services/wordpress-security-hardening/?utm_source=wpsalabt&utm_medium=txtlink&utm_campaign=wpsal" target="_blank"><?php _e('WordPress Security Hardening', 'wp-security-audit-log'); ?></a>
							&nbsp;&nbsp;&nbsp;&nbsp;
							<a class="button" href="http://www.wpwhitesecurity.com/wordpress-security-services/wordpress-security-audit/?utm_source=wpsalabt&utm_medium=txtlink&utm_campaign=wpsal" target="_blank"><?php _e('WordPress Security Audit', 'wp-security-audit-log'); ?></a>
						</p>
					</div>

					<div class="">
						<h2><?php _e('WordPress Security Readings', 'wp-security-audit-log'); ?></h2>
						<p>
							<?php _e('New to WordPress security?', 'wp-security-audit-log'); ?>
							<?php _e('Do not know from where to start or which is the best services for you?', 'wp-security-audit-log'); ?>
							<?php _e('Visit our WordPress security blog or the WordPress Security category directly for more information and a number of tips and tricks about WordPress security.', 'wp-security-audit-log'); ?>
						</p>
						<a class="button" href="http://www.wpwhitesecurity.com/blog/?utm_source=wpsalabt&utm_medium=txtlink&utm_campaign=wpsal" target="_blank"><?php _e('WP White Security Blog', 'wp-security-audit-log'); ?></a>
						&nbsp;&nbsp;&nbsp;&nbsp;
						<a class="button" href="http://www.wpwhitesecurity.com/wordpress-security/?utm_source=wpsalabt&utm_medium=txtlink&utm_campaign=wpsal" target="_blank"><?php _e('WordPress Security Category', 'wp-security-audit-log'); ?></a>
					</div>
				</div>
			</div>

			<div style="position: absolute; right: 70px; width: 180px; top: 10px;">
				<div class="postbox">
					<h3 class="hndl"><span><?php _e('WP Password Policy Manager', 'wp-security-audit-log'); ?></span></h3>
					<div class="inside">
						<p>
							<?php _e('Easily configure WordPress password policies and ensure users use strong passwords with our plugin WP Password Policy Manager.', 'wp-security-audit-log'); ?>
						</p>
						<a class="button button-primary" href="http://wordpress.org/plugins/wp-password-policy-manager/" target="_blank"><?php _e('Download', 'wp-security-audit-log'); ?></a>
					</div>
				</div>
				<div class="postbox">
					<h3 class="hndl"><span><?php _e('WP Security Audit Log in your Language!', 'wp-security-audit-log'); ?></span></h3>
					<div class="inside">
						<?php _e('If you are interested in translating our plugin please drop us an email on', 'wp-security-audit-log'); ?>
						<a href="mailto:plugins@wpwhitesecurity.com">plugins@wpwhitesecurity.com</a>.
					</div>
				</div>
			</div>
			
		</div><?php
	}
	
}