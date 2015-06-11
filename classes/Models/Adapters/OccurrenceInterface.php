<?php

interface WSAL_Adapters_OccurrenceInterface
{
    public function GetMeta();
    public function GetNamedMeta($name);
    public function GetFirstNamedMeta($names);
    public static function GetNewestUnique($limit = PHP_INT_MAX);
    public function findExistingOccurences($ipAddress, $username, $alertId, $siteId, $startTime, $endTime);
    public function CheckUnKnownUsers($args = array());
}
