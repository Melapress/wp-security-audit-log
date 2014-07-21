<?php

class WSAL_Sensors_MetaData extends WSAL_AbstractSensor {

	public function HookEvents() {
		add_action('added_post_meta', array($this,   'EventPostMetaCreated'), 10, 4);
		add_action('update_post_meta', array($this,  'EventPostMetaUpdating'), 10, 3);
		add_action('updated_post_meta', array($this, 'EventPostMetaUpdated'), 10, 4);
		add_action('deleted_post_meta', array($this, 'EventPostMetaDeleted'), 10, 4);
	}
	
	protected function GetEventTypeForPostType($post, $typePost, $typePage, $typeCustom){
		switch($post->post_type){
			case 'page':
				return $typePage;
			case 'post':
				return $typePost;
			default:
				return $typeCustom;
		}
	}
	
	public function EventPostMetaCreated($meta_id, $object_id, $meta_key, $_meta_value){
		$post = get_post($object_id);
		
		switch($post->post_type){
			case 'page':
				$this->plugin->alerts->Trigger(2059, array(
					'PostID' => $object_id,
					'PostTitle' => $post->post_title,
					'MetaID' => $meta_id,
					'MetaKey' => $meta_key,
					'MetaValue' => $_meta_value,
				));
				break;
			case 'post':
				$this->plugin->alerts->Trigger(2053, array(
					'PostID' => $object_id,
					'PostTitle' => $post->post_title,
					'MetaID' => $meta_id,
					'MetaKey' => $meta_key,
					'MetaValue' => $_meta_value,
				));
				break;
			default:
				$this->plugin->alerts->Trigger(2056, array(
					'PostID' => $object_id,
					'PostTitle' => $post->post_title,
					'PostType' => $post->post_type,
					'MetaID' => $meta_id,
					'MetaKey' => $meta_key,
					'MetaValue' => $_meta_value,
				));
				break;
		}
	}
	
	public function EventPostMetaUpdating($meta_id, $object_id, $meta_key){
		$this->old_meta_values[$meta_id] = get_metadata('post', $object_id, $meta_key, true);
	}
	
	public function EventPostMetaUpdated($meta_id, $object_id, $meta_key, $_meta_value){
		$post = get_post($object_id);
		
		if(isset($this->old_meta_values[$meta_id])){
			switch($post->post_type){
				case 'page':
					$this->plugin->alerts->Trigger(2060, array(
						'PostID' => $object_id,
						'PostTitle' => $post->post_title,
						'MetaID' => $meta_id,
						'MetaKey' => $meta_key,
						'MetaValueNew' => $_meta_value,
						'MetaValueOld' => $this->old_meta_values[$meta_id],
					));
					break;
				case 'post':
					$this->plugin->alerts->Trigger(2054, array(
						'PostID' => $object_id,
						'PostTitle' => $post->post_title,
						'MetaID' => $meta_id,
						'MetaKey' => $meta_key,
						'MetaValueNew' => $_meta_value,
						'MetaValueOld' => $this->old_meta_values[$meta_id],
					));
					break;
				default:
					$this->plugin->alerts->Trigger(2057, array(
						'PostID' => $object_id,
						'PostTitle' => $post->post_title,
						'PostType' => $post->post_type,
						'MetaID' => $meta_id,
						'MetaKey' => $meta_key,
						'MetaValueNew' => $_meta_value,
						'MetaValueOld' => $this->old_meta_values[$meta_id],
					));
					break;
			}
			unset($this->old_meta_values[$meta_id]);
		}
	}
	
	public function EventPostMetaDeleted($meta_ids, $object_id, $meta_key, $_meta_value){
		$post = get_post($object_id);
		foreach($meta_ids as $meta_id){
			switch($post->post_type){
				case 'page':
					$this->plugin->alerts->Trigger(2061, array(
						'PostID' => $object_id,
						'PostTitle' => $post->post_title,
						'MetaID' => $meta_id,
						'MetaKey' => $meta_key,
						'MetaValue' => $_meta_value,
					));
					break;
				case 'post':
					$this->plugin->alerts->Trigger(2055, array(
						'PostID' => $object_id,
						'PostTitle' => $post->post_title,
						'MetaID' => $meta_id,
						'MetaKey' => $meta_key,
						'MetaValue' => $_meta_value,
					));
					break;
				default:
					$this->plugin->alerts->Trigger(2058, array(
						'PostID' => $object_id,
						'PostTitle' => $post->post_title,
						'PostType' => $post->post_type,
						'MetaID' => $meta_id,
						'MetaKey' => $meta_key,
						'MetaValue' => $_meta_value,
					));
					break;
			}
		}
	}
}
