<?php

interface Connector
{
	public static function GetAdapter($class_name);
	public static function GetConnection();
}
