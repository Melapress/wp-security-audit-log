<?php

class WSAL_Views_Licensing extends WSAL_AbstractView {
	
	public function GetTitle() {
		return __('Licensing', 'wp-security-audit-log');
	}
	
	public function GetIcon() {
		return 'dashicons-cart';
	}
	
	public function GetName() {
		return __('Licensing', 'wp-security-audit-log');
	}
	
	public function GetWeight() {
		return 4;
	}
	
	public function IsAccessible(){
		return !!$this->_plugin->licensing->CountPlugins();
	}
	
	public function Render(){
		?><form id="audit-log-licensing" method="post">
			<input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
			
			<table class="wp-list-table widefat fixed">
				<thead>
					<tr><th>Plugin</th><th>License</th><th></th></tr>
				</thead><tbody>
					<?php $counter = 0; ?>
					<?php foreach($this->_plugin->licensing->plugins as $plugin){ ?>
					<tr class="<?php echo ($counter++ % 2 === 0) ? 'alternate' : ''; ?>">
						<td>
							<a href="<?php echo esc_attr($plugin['PluginData']['PluginURI']); ?>" target="_blank">
								<?php echo esc_html($plugin['PluginData']['Name']); ?> 
							</a><br/><small><b>
								<?php _e('Version', 'wp-security-audit-log'); ?>
								<?php echo esc_html($plugin['PluginData']['Version']); ?>
							</b></small>
						</td><td>
							<input type="text" value="<?php echo esc_attr($plugin['LicenseKey']); ?>" style="width: 400px;"/>
						</td><td>
							<?php if($plugin['LicenseKey']){ ?>
								<tr valign="top">	
									<th scope="row" valign="top">
										<?php _e('Activate License'); ?>
									</th>
									<td>
										<?php if($plugin['LicenseStatus'] == 'valid'){ ?>
											<input type="submit" class="button-secondary" name="edd_license_deactivate" value="<?php _e('Deactivate License'); ?>"/>
										<?php } else { ?>
											<input type="submit" class="button-secondary" name="edd_license_activate" value="<?php _e('Activate License'); ?>"/>
										<?php } ?>
									</td>
								</tr>
							<?php } ?>
						</td>
					</tr>
					<?php } ?>
				</tbody><tfoot>
					<tr><th>Plugin</th><th>License</th><th></th></tr>
				</tfoot>
			</table>
			<?php submit_button(); ?>
		</form><?php
	}

}