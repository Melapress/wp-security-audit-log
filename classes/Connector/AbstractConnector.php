<?php
require_once('ConnectorInterface.php');

abstract class WSAL_Connector_AbstractConnector
{
    protected $connection = null;

    public function __construct($adaptersDirName = null)
    {
        $adaptersBasePath = __DIR__ . '/../Models/Adapters/';
        require_once($adaptersBasePath . 'ActiveRecordInterface.php');
        require_once($adaptersBasePath . 'AdapterInterfaces/MetaInterface.php');
        require_once($adaptersBasePath . 'AdapterInterfaces/OccurrenceInterface.php');
        require_once($adaptersBasePath . 'AdapterInterfaces/QueryInterface.php');
        require_once($adaptersBasePath . 'AdapterInterfaces/OccurrenceQueryInterface.php');

        if (!empty($adaptersDirName)) {
            require_once($adaptersBasePath . $adaptersDirName . '/ActiveRecordAdapter.php');
            require_once($adaptersBasePath . $adaptersDirName . '/MetaAdapter.php');
            require_once($adaptersBasePath . $adaptersDirName . '/OccurrenceAdapter.php');
            require_once($adaptersBasePath . $adaptersDirName . '/QueryAdapter.php');
            require_once($adaptersBasePath . $adaptersDirName . '/OccurrenceQueryAdapter.php');
        }
    }
}
