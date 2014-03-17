<?php

class WSAL_Sensors_Uploads extends WSAL_AbstractSensor {

	public function HookEvents() {
		add_action('add_attachment', array($this, 'EventFileUploaded'));
		add_action('delete_attachment', array($this, 'EventFileUploadedDeleted'));
	}
	
	protected $IsFileUploaded = false;
	
	public function EventFileUploaded($attachmentID){
        global $wpdb;
        $data = $wpdb->get_row("SELECT guid FROM $wpdb->posts WHERE ID = " . (int)$attachmentID);
        $this->plugin->alerts->Trigger(2010, array(
			'AttachmentID' => $attachmentID,
			'FileName' => basename($data->guid),
			'FilePath' => dirname($data->guid),
		));
		$this->IsFileUploaded = true;
	}
	
	public function EventFileUploadedDeleted($attachmentID){
		if($this->IsFileUploaded)return;
        global $wpdb;
        $data = $wpdb->get_row("SELECT guid FROM $wpdb->posts WHERE ID = " . (int)$attachmentID);
		$this->plugin->alerts->Trigger(2011, array(
			'AttachmentID' => $attachmentID,
			'FileName' => basename($data->guid),
			'FilePath' => dirname($data->guid),
		));
	}
	
}
