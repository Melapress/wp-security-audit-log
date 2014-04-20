<?php

class WSAL_Views_Settings extends WSAL_AbstractView {
	
	public function HasPluginShortcutLink(){
		return true;
	}
	
	public function GetTitle() {
		return 'Settings';
	}
	
	public function GetIcon() {
		return 'dashicons-admin-generic';
	}
	
	public function GetName() {
		return 'Settings';
	}
	
	public function GetWeight() {
		return 2;
	}
	
	protected function Save(){
		$this->_plugin->settings->SetPruningDate($_REQUEST['PruningDate']);
		$this->_plugin->settings->SetPruningLimit($_REQUEST['PruningLimit']);
	}
	
	public function Render(){
		if(!$this->_plugin->settings->CurrentUserCan('edit')){
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		if(isset($_POST['submit'])){
			try {
				$this->Save();
				?><div class="updated"><p><?php _e('Settings have been saved.'); ?></p></div><?php
			}catch(Exception $ex){
				?><div class="error"><p><?php _e('Error: '); ?><?php echo $ex->getMessage(); ?></p></div><?php
			}
		}
		?><form id="audit-log-settings" method="post">
			<input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
			
			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row"><label><?php _e('Security Alerts Pruning'); ?></label></th>
						<td>
							<?php $text = __('(eg: 1 month)'); ?>
							<input type="radio" id="delete1" style="margin-top: 2px;"/>
							<label for="delete1"><?php echo __('Delete alerts older than'); ?></label>
							<input type="text" name="PruningDate" placeholder="<?php echo $text; ?>"
								   value="<?php echo esc_attr($this->_plugin->settings->GetPruningDate()); ?>"/>
							<span> <?php echo $text; ?></span>
						</td>
					</tr>
					<tr>
						<th></th>
						<td>
							<?php $max = $this->_plugin->settings->GetMaxAllowedAlerts(); ?>
							<?php $text = sprintf(__('(1 to %d alerts)'), $max); ?>
							<input type="radio" id="delete2" class="radioInput" style="margin-top: 2px;"/>
							<label for="delete2"><?php echo __('Keep up to'); ?></label>
							<input type="text" name="PruningLimit" placeholder="<?php echo $text;?>"
								   value="<?php echo esc_attr($this->_plugin->settings->GetPruningLimit()); ?>"/>
							<span><?php echo $text; ?></span>
							<p class="description" style="margin-top: 5px !important;"><?php
								echo sprintf(__('By default we keep up to %d WordPress Security Events.'), $max);
							?></p>
						</td>
					</tr>
				</tbody>
			</table>
			
			<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes"></p>
		</form><?php
	}
	
}