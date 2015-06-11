<?php

class WSAL_Adapters_MySQL_Ocurrence extends WSAL_Adapters_MySQL_ActiveRecord implements WSAL_Adapters_OccurrenceInterface {

    protected $_table = 'wsal_occurrences';
    protected $_idkey = 'id';
    protected $_meta;

    public function __construct($conn) {
        parent::__construct($conn);
    }
    
    protected function GetTableOptions(){
        return parent::GetTableOptions() . ',' . PHP_EOL
                . '    KEY site_alert_created (site_id,alert_id,created_on)';
    }
    
    /**
     * Returns all meta data related to this event.
     * @return WSAL_Meta[]
     */
    public function GetMeta(){
        if(!isset($this->_meta)){
            $this->_meta = WSAL_Adapters_MySQL_Meta::LoadMulti('occurrence_id = %d', array($this->id));
        }
        return $this->_meta;
    }

    /**
     * Loads a meta item given its name.
     * @param string $name Meta name.
     * @return WSAL_Meta The meta item, be sure to checked if it was loaded successfully.
     */
    public function GetNamedMeta($name){
        $meta = new WSAL_Adapters_MySQL_Meta();
        $meta->Load('occurrence_id = %d AND name = %s', array($this->id, $name));
        return $meta;
    }
    
    /**
     * Returns the first meta value from a given set of names. Useful when you have a mix of items that could provide a particular detail.
     * @param array $names List of meta names.
     * @return WSAL_Meta The first meta item that exists.
     */
    public function GetFirstNamedMeta($names){
        $meta = new WSAL_Adapters_MySQL_Meta();
        $query = '(' . str_repeat('name = %s OR ', count($names)).'0)';
        $query = 'occurrence_id = %d AND ' . $query . ' ORDER BY name DESC LIMIT 1';
        array_unshift($names, $this->id); // prepend args with occurrence id
        $meta->Load($query, $names);
        return $meta->IsLoaded() ? $meta : null;
    }
    
    /**
     * Returns newest unique occurrences.
     * @param integer $limit Maximum limit.
     * @return WSAL_Occurrence[]
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
     * Gets occurences of the same type by IP and Username within specified time frame
     * @param string $ipAddress
     * @param string $username
     * @param int $alertId Alert type we are lookign for
     * @param int $siteId
     * @param $startTime mktime
     * @param $endTime mktime
     */
    public function findExistingOccurences($ipAddress, $username, $alertId, $siteId, $startTime, $endTime)
    {
        $tt2 = new WSAL_Adapters_MySQL_Meta();
        return self::LoadMultiQuery(
            'SELECT occurrence.* FROM `' . $this->GetTable() . '` occurrence 
            INNER JOIN `' . $tt2->GetTable() . '` ipMeta on ipMeta.occurrence_id = occurrence.id
            and ipMeta.name = "ClientIP"
            and ipMeta.value = %s
            INNER JOIN `' . $tt2->GetTable() . '` usernameMeta on usernameMeta.occurrence_id = occurrence.id
            and usernameMeta.name = "Username"
            and usernameMeta.value = %s
            WHERE occurrence.alert_id = %d AND occurrence.site_id = %d
            AND (created_on BETWEEN %d AND %d)
            GROUP BY occurrence.id',
            array(
                json_encode($ipAddress),
                json_encode($username),
                $alertId,
                $siteId,
                $startTime,
                $endTime
            )
        );
    }

    public function CheckUnKnownUsers($args = array()) {
        $tt2 = new WSAL_Adapters_MySQL_Meta();
        return self::LoadMultiQuery('
            SELECT occurrence.* FROM `' . $this->GetTable() . '` occurrence 
            INNER JOIN `' . $tt2->GetTable() . '` ipMeta on ipMeta.occurrence_id = occurrence.id 
            and ipMeta.name = "ClientIP" and ipMeta.value = %s 
            WHERE occurrence.alert_id = %d AND occurrence.site_id = %d
            AND (created_on BETWEEN %d AND %d)
            GROUP BY occurrence.id
        ', $args);
    }
    
    
}
