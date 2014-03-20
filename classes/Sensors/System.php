<?php

class WSAL_Sensors_System extends WSAL_AbstractSensor {

	public function HookEvents() {
		add_action('add_attachment', array($this, 'EventFileUploaded'));
		add_action('delete_attachment', array($this, 'EventFileUploadedDeleted'));
	}
	
	protected $IsFileUploaded = false;
	
	public function EventWordpressUpgrade($attachmentID){
//        global $wpdb;
//        $data = $wpdb->get_row("SELECT guid FROM $wpdb->posts WHERE ID = " . (int)$attachmentID);
//        $this->plugin->alerts->Trigger(2010, array(
//			'AttachmentID' => $attachmentID,
//			'FileName' => basename($data->guid),
//			'FilePath' => dirname($data->guid),
//		));
//		$this->IsFileUploaded = true;
	}
	
	public function EventUpdatedSettings(){
		if(isset($_POST)){
			if(!empty($_POST['admin_email']) && current_user_can()){ // TODO !!!
				$from = get_option('admin_email');
				$to = trim($_POST['admin_email']);
				if($from !== $to){
					$this->plugin->alerts->Trigger(6003, array(
						'OldEmail' => $from,
						'NewEmail' => $to,
						'CurrentUserID' => wp_get_current_user()->ID,
					));
				}
			}
			if(!empty($_POST['option_page']) && $_POST['option_page'] == 'general' && current_user_can()){ // TODO !!!
				
			}
		}
	}
	
}
