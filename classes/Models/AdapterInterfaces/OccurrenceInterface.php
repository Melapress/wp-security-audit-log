<?php

interface WSAL_OccurrenceInterface {
	public function GetMeta();
	public function GetNamedMeta($name);
	public function GetFirstNamedMeta($names);
	public static function GetNewestUnique($limit = PHP_INT_MAX);
	public function CheckKnownUsers($args = array());
	public function CheckUnKnownUsers($args = array());
}
