<?php
/**
 * @package Wsal
 *
 * Abstract ActiveRecord model is the generic model for any kind
 * of adapter.
 */
abstract class WSAL_Models_ActiveRecord
{
    const STATE_UNKNOWN  = 'unknown';
    const STATE_CREATED  = 'created';
    const STATE_UPDATED  = 'updated';
    const STATE_DELETED  = 'deleted';
    const STATE_LOADED   = 'loaded';

    /**
     * @var $connector Data connector;
     */
    protected $connector;

    protected $id = false;

    protected $adapterName = null;

    protected $useDefaultAdapter = false;

    protected $_state = self::STATE_UNKNOWN;

    protected static $_cache = array();
    
    /**
     * @return array Returns this records' fields.
     */
    public function GetFields()
    {
        if (!isset($this->_column_cache)) {
            $this->_column_cache = array();
            foreach (array_keys(get_object_vars($this)) as $col) {
                if (trim($col) && $col[0] != '_') {
                    $this->_column_cache[] = $col;
                }
            }
        }
        return $this->_column_cache;
    }

    /**
     * Sets the id.
     * @param integer $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Gets the id.
     * @return integer $id.
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @throws Exception requires adapterName
     */
    public function __construct($data = null)
    {
        if (!$this->adapterName) {
            throw new Exception('Class "' . __CLASS__ . '" requires "adapterName" to be set.');
        }
        if (!is_null($data)) {
            $this->LoadData($data);
            $this->_state = self::STATE_LOADED;
        }
    }

    /**
     * Gets the connector.
     * @return WSAL_Connector_ConnectorInterface
     */
    protected function getConnector()
    {
        if (!empty($this->connector)) {
            return $this->connector;
        }
        if ($this->useDefaultAdapter) {
            $this->connector = WSAL_Connector_ConnectorFactory::GetDefaultConnector();
        } else {
            $this->connector = WSAL_Connector_ConnectorFactory::GetConnector();
        }
        return $this->connector;
    }

    /**
     * Gets an adapter for the specified model
     * based on the adapter name.
     * @see WSAL_Connector_ConnectorInterface::getAdapter()
     */
    public function getAdapter()
    {
        return $this->getConnector()->getAdapter($this->adapterName);
    }
    
    /**
     * Load record from DB.
     * @see WSAL_Adapters_MySQL_ActiveRecord::Load()
     * @param string $cond (Optional) Load condition.
     * @param array $args (Optional) Load condition arguments.
     */
    public function Load($cond = '%d', $args = array(1))
    {
        $this->_state = self::STATE_UNKNOWN;

        $data = $this->getAdapter()->Load($cond, $args);
        if (!is_null($data)) {
            $this->LoadData($data);
            $this->_state = self::STATE_LOADED;
        }
    }

    /**
     * Load object data from variable.
     * @param array|object $data Data array or object.
     */
    public function LoadData($data)
    {
        $copy = get_class($this);
        $copy = new $copy;
        foreach ((array)$data as $key => $val) {
            if (isset($copy->$key)) {
                switch (true) {
                    case $this->is_ip_address($val):
                        $this->$key = (string)$val;
                        break;
                    case is_array($copy->$key):
                    case is_object($copy->$key):
                        $jsonDecodedVal = WSAL_Helpers_DataHelper::JsonDecode($val);
                        $this->$key = ($jsonDecodedVal == null) ? $val : $jsonDecodedVal;
                        break;
                    case is_int($copy->$key):
                        $this->$key = (int)$val;
                        break;
                    case is_float($copy->$key):
                        $this->$key = (float)$val;
                        break;
                    case is_bool($copy->$key):
                        $this->$key = (bool)$val;
                        break;
                    case is_string($copy->$key):
                        $this->$key = (string)$val;
                        break;
                    default:
                        throw new Exception('Unsupported type "'.gettype($copy->$key).'"');
                }
            }
        }
        return $this;
    }

    /**
     * Save this active record
     * @see WSAL_Adapters_MySQL_ActiveRecord::Save()
     * @return integer|boolean Either the number of modified/inserted rows or false on failure.
     */
    public function Save()
    {
        $this->_state = self::STATE_UNKNOWN;

        // use today's date if not set up
        if (is_null($this->created_on)) {
            $this->created_on = $this->GetMicrotime();
        }
        $updateId = $this->getId();
        $result = $this->getAdapter()->Save($this);

        if ($result !== false) {
            $this->_state = (!empty($updateId))?self::STATE_UPDATED:self::STATE_CREATED;
        }
        return $result;
    }

    /**
     * Deletes this active record.
     * @see WSAL_Adapters_MySQL_ActiveRecord::Delete()
     * @return int|boolean Either the amount of deleted rows or False on error.
     */
    public function Delete()
    {
        $this->_state = self::STATE_UNKNOWN;
        $result = $this->getAdapter()->Delete($this);
        if ($result !== false) {
            $this->_state = self::STATE_DELETED;
        }
        
        return $result;
    }

    /**
     * Count records that matching a condition.
     * @see WSAL_Adapters_MySQL_ActiveRecord::Count()
     * @return int count
     */
    public function Count($cond = '%d', $args = array(1))
    {
        $result = $this->getAdapter()->Count($cond, $args);
        return $result;
    }
    
    /**
     * Check state loaded.
     * @return bool
     */
    public function IsLoaded()
    {
        return $this->_state == self::STATE_LOADED;
    }
    
    /**
     * Check state saved.
     * @return bool
     */
    public function IsSaved()
    {
        return $this->_state == self::STATE_CREATED
            || $this->_state == self::STATE_UPDATED;
    }
    
    /**
     * Check state created.
     * @return bool
     */
    public function IsCreated()
    {
        return $this->_state == self::STATE_CREATED;
    }
    
    /**
     * Check state updated.
     * @return bool
     */
    public function IsUpdated()
    {
        return $this->_state == self::STATE_UPDATED;
    }

    /**
     * Check if the Record structure is created.
     * @see WSAL_Adapters_MySQL_ActiveRecord::IsInstalled()
     * @return bool
     */
    public function IsInstalled()
    {
        return $this->getAdapter()->IsInstalled();
    }

    /**
     * Install the Record structure.
     * @see WSAL_Adapters_MySQL_ActiveRecord::Install()
     */
    public function Install()
    {
        return $this->getAdapter()->Install();
    }
    
    /**
     * Check state deleted.
     * @return bool
     */
    public function IsDeleted()
    {
        return $this->_state == self::STATE_DELETED;
    }
    
    /**
     * Load ActiveRecord from DB or cache.
     * @param string $target ActiveRecord class name.
     * @param string $query Load condition.
     * @param array $args Arguments used in condition.
     * @return WSAL_Models_ActiveRecord
     */
    protected static function CacheLoad($target, $query, $args)
    {
        $index = $target . '::' . vsprintf($query, $args);
        if (!isset(self::$_cache[$index])) {
            self::$_cache[$index] = new $target();
            self::$_cache[$index]->Load($query, $args);
        }
        return self::$_cache[$index];
    }
    
    /**
     * Remove ActiveRecord cache.
     * @param string $target ActiveRecord class name.
     * @param string $query Load condition.
     * @param array $args Arguments used in condition.
     */
    protected static function CacheRemove($target, $query, $args)
    {
        $index = $target . '::' . sprintf($query, $args);
        if (!isset(self::$_cache[$index])) {
            unset(self::$_cache[$index]);
        }
    }
    
    /**
     * Clear the cache.
     */
    protected static function CacheClear()
    {
        self::$_cache = array();
    }
    
    /**
     * Function used in WSAL reporting extension.
     * @see WSAL_Adapters_MySQL_ActiveRecord::GetReporting()
     * @param int $_siteId site ID
     * @param int $_userId user ID
     * @param string $_roleName user role
     * @param int $_alertCode alert code
     * @param timestamp $_startTimestamp from created_on
     * @param timestamp $_endTimestamp to created_on
     * @return array Report results
     */
    public function GetReporting($_siteId, $_userId, $_roleName, $_alertCode, $_startTimestamp, $_endTimestamp)
    {
        return $this->getAdapter()->GetReporting($_siteId, $_userId, $_roleName, $_alertCode, $_startTimestamp, $_endTimestamp);
    }

    /**
     * Check if the float is IPv4 instead.
     * @see WSAL_Models_ActiveRecord::LoadData()
     * @param float $ip_address number to check
     * @return bool result validation
     */
    private function is_ip_address($ip_address)
    {
        if (filter_var($ip_address, FILTER_VALIDATE_IP) !== false) {
            return true;
        }
        return false;
    }
}
