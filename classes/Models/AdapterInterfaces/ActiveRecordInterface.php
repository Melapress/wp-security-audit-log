<?php

interface WSAL_ActiveRecordInterface {
	
	public function IsInstalled();
	public function Install();
	public function Uninstall();
	public function Load($cond = '%d', $args = array(1));
	public function Save($activeRecord);
	public function Delete($activeRecord);
	public static function LoadMulti($cond, $args = array());
	public static function LoadAndCallForEach($callback, $cond = '%d', $args = array(1));
	public static function Count($cond = '%d', $args = array(1));
	public static function LoadMultiQuery($query, $args = array());
	public static function InstallAll();
	public static function UninstallAll();

}
