<?php

interface Connector
{
	public static function GetAdapter($class_name);
	public static function GetConnection();
}


/*
$connection = ConnectorFactory::getConnection();
$connection->getAdapter("Meta")->create(array("test" => "test"));
*/
