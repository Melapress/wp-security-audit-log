<?php

class WSAL_DB_Occurrence extends WSAL_DB_ActiveRecord {
	protected $_table = 'wsal_occurrences';
	protected $_idkey = 'id';
	
	public $id = 0;
	public $site_id = 0;
	public $alert_id = 0;
	public $created_on = 0.0;
	public $is_read = false;
	public $is_migrated = false;
	
	protected function GetTableOptions(){
		return parent::GetTableOptions() . ',' . PHP_EOL
				. '    KEY site_alert_created (site_id,alert_id,created_on)';
	}
	
	protected $_meta;
	
	/**
	 * Returns all meta data related to this event.
	 * @return WSAL_DB_Meta[]
	 */
	public function GetMeta(){
		if(!isset($this->_meta)){
			$this->_meta = WSAL_DB_Meta::LoadMulti('occurrence_id = %d', array($this->id));
		}
		return $this->_meta;
	}
	
	/**
	 * Loads a meta item given its name.
	 * @param string $name Meta name.
	 * @return WSAL_DB_Meta The meta item, be sure to checked if it was loaded successfully.
	 */
	public function GetNamedMeta($name){
		$meta = new WSAL_DB_Meta();
		$meta->Load('occurrence_id = %d AND name = %s', array($this->id, $name));
		return $meta;
	}
	
	/**
	 * Returns the first meta value from a given set of names. Useful when you have a mix of items that could provide a particular detail.
	 * @param array $names List of meta names.
	 * @return WSAL_DB_Meta The first meta item that exists.
	 */
	public function GetFirstNamedMeta($names){
		$meta = new WSAL_DB_Meta();
		$query = '(' . str_repeat('name = %s OR ', count($names)).'0)';
		$query = 'occurrence_id = %d AND ' . $query . ' ORDER BY name DESC LIMIT 1';
		array_unshift($names, $this->id); // prepend args with occurrence id
		$meta->Load($query, $names);
		return $meta->IsLoaded() ? $meta : null;
	}
	
	/**
	 * Returns the alert related to this occurrence.
	 * @return WSAL_Alert
	 */
	public function GetAlert(){
		return WpSecurityAuditLog::GetInstance()->alerts->GetAlert($this->alert_id);
	}
	
	/**
	 * Returns the value of a meta item.
	 * @param string $name Name of meta item.
	 * @param mixed $default Default value returned when meta does not exist.
	 * @return mixed The value, if meta item does not exist $default returned.
	 */
	public function GetMetaValue($name, $default = array()){
		$meta = $this->GetNamedMeta($name);
		return $meta->IsLoaded() ? $meta->value : $default;
	}
	
	/**
	 * Set the value of a meta item (creates or updates meta item).
	 * @param string $name Meta name.
	 * @param mixed $value Meta value.
	 */
	public function SetMetaValue($name, $value){
		$meta = $this->GetNamedMeta($name);
		$meta->occurrence_id = $this->id;
		$meta->name = $name;
		$meta->value = $value;
		$meta->Save();
	}
	
	/**
	 * Returns a key-value pair of meta data.
	 * @return array
	 */
	public function GetMetaArray(){
		$result = array();
		foreach($this->GetMeta() as $meta)
			$result[$meta->name] = $meta->value;
		return $result;
	}
	
	/**
	 * Creates or updates all meta data passed as an array of meta-key/meta-value pairs.
	 * @param array $data New meta data.
	 */
	public function SetMeta($data){
		foreach((array)$data as $key => $val)
			$this->SetMetaValue($key, $val);
	}
	
	/**
	 * @param callable|null $metaFormatter (Optional) Meta formatter callback.
	 * @return string Full-formatted message.
	 */
	public function GetMessage($metaFormatter = null){
		if(!isset($this->_cachedmessage)){
			// get correct message entry
			if($this->is_migrated){
				$this->_cachedmessage = $this->GetMetaValue('MigratedMesg', false);
			}
			if(!$this->is_migrated || !$this->_cachedmessage){
				$this->_cachedmessage = $this->GetAlert()->mesg;
			}
			// fill variables in message
			$this->_cachedmessage = $this->GetAlert()->GetMessage($this->GetMetaArray(), $metaFormatter, $this->_cachedmessage);
		}
		return $this->_cachedmessage;
	}
	
	/**
	 * Returns newest unique occurrences.
	 * @param integer $limit Maximum limit.
	 * @return WSAL_DB_Occurrence[]
	 */
	public static function GetNewestUnique($limit = PHP_INT_MAX){
		$temp = new self();
		return self::LoadMultiQuery('
			SELECT *, COUNT(alert_id) as count
			FROM (
				SELECT *
				FROM ' . $temp->GetTable() . '
				ORDER BY created_on DESC
			) AS temp_table
			GROUP BY alert_id
			LIMIT %d
		', array($limit));
	}
	
	/**
	 * Delete occurrence as well as associated meta data.
	 * @return boolean True on success, false on failure.
	 */
	public function Delete(){
		foreach($this->GetMeta() as $meta)$meta->Delete();
		return parent::Delete();
	}
	
	/**
	 * @return string User's username.
	 */
	public function GetUsername(){
		$meta = $this->GetFirstNamedMeta(array('Username', 'CurrentUserID'));
		if($meta){
			switch(true){
				case $meta->name == 'Username':
					return $meta->value;
				case $meta->name == 'CurrentUserID':
					return ($data = get_userdata($meta->value)) ? $data->user_login : null;
			}
		}
		return null;
	}
	
	/**
	 * @return string IP address of request.
	 */
	public function GetSourceIP(){
		return $this->GetMetaValue('ClientIP', '');
	}
	
	/**
	 * @return string IP address of request (from proxies etc).
	 */
	public function GetOtherIPs(){
		$result = array();
		$data = (array)$this->GetMetaValue('OtherIPs', array());
		foreach ($data as $ips) foreach($ips as $ip) $result[] = $ip;
		return array_unique($result);
	}
	
	/**
	 * @return array Array of user roles.
	 */
	public function GetUserRoles(){
		return $this->GetMetaValue('CurrentUserRoles', array());
	}
	
	/**
	 * @return float Number of seconds (and microseconds as fraction) since unix Day 0.
	 * @todo This needs some caching.
	 */
	protected function GetMicrotime(){
		return microtime(true);// + get_option('gmt_offset') * HOUR_IN_SECONDS;
	}
	
	public function Save(){
		// use today's date if not set up
		if(is_null($this->created_on))
			$this->created_on = $this->GetMicrotime();
		
		return parent::Save();
	}
}
