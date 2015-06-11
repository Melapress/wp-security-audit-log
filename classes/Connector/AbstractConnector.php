<?php
require_once('ConnectorInterface.php');

abstract class WSAL_Connector_AbstractConnector
{
    protected $connection = null;
    protected $adaptersBasePath = null;
    protected $adaptersDirName = null;

    public function __construct($adaptersDirName = null)
    {
        $this->adaptersBasePath =  __DIR__ . '/../Models/Adapters/';

        require_once($this->adaptersBasePath . 'ActiveRecordInterface.php');
        require_once($this->adaptersBasePath . 'MetaInterface.php');
        require_once($this->adaptersBasePath . 'OccurrenceInterface.php');
        require_once($this->adaptersBasePath . 'QueryInterface.php');
        require_once($this->adaptersBasePath . 'OccurrenceQueryInterface.php');

        if (!empty($adaptersDirName)) {
            $this->adaptersDirName = $adaptersDirName;
            require_once($this->getAdaptersDirectory() . '/ActiveRecordAdapter.php');
            require_once($this->getAdaptersDirectory() . '/MetaAdapter.php');
            require_once($this->getAdaptersDirectory() . '/OccurrenceAdapter.php');
            require_once($this->getAdaptersDirectory() . '/QueryAdapter.php');
            require_once($this->getAdaptersDirectory() . '/OccurrenceQueryAdapter.php');
        }
    }

    public function getAdaptersDirectory()
    {
        if (!empty($this->adaptersBasePath) && !empty($this->adaptersDirName)) {
            return $this->adaptersBasePath . $this->adaptersDirName;
        } else {
            return false;
        }
    }
}
