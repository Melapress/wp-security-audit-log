<?php
/**
 * @package Wsal
 * Wordpress options are always loaded from the default wordpress database.
 *
 * Option Model gets and sets the options of the wsal_options table in the database.
 */
class WSAL_Models_Option extends WSAL_Models_ActiveRecord
{
    public $id = '';
    public $option_name = '';
    public $option_value = '';
    protected $adapterName = "Option";

    /**
     * Options are always stored in WPDB. This setting ensures that
     */
    protected $useDefaultAdapter = true;

    /**
     * Sets Option record.
     * @param string $name option name
     * @param mixed $value option value
     */
    public function SetOptionValue($name, $value)
    {
        $option = $this->getAdapter()->GetNamedOption($name);
        $this->id = $option['id'];
        $this->option_name = $name;
        // Serialize if $value is array or object
        $value = maybe_serialize($value);
        $this->option_value = $value;
        return $this->Save();
    }
    
    /**
     * Gets Option record.
     * @param string $name option name
     * @param mixed $default (Optional) default value
     * @return mixed option value
     */
    public function GetOptionValue($name, $default = array())
    {
        $option = $this->getAdapter()->GetNamedOption($name);
        $this->option_value = (!empty($option)) ? $option['option_value'] : null;
        if (!empty($this->option_value)) {
            $this->_state = self::STATE_LOADED;
        }
        // Unerialize if $value is array or object
        $this->option_value = maybe_unserialize($this->option_value);
        return $this->IsLoaded() ? $this->option_value : $default;
    }

    /**
     * Save Option record.
     * @see WSAL_Adapters_MySQL_ActiveRecord::Save()
     * @return integer|boolean Either the number of modified/inserted rows or false on failure.
     */
    public function Save()
    {
        $this->_state = self::STATE_UNKNOWN;

        $updateId = $this->getId();
        $result = $this->getAdapter()->Save($this);

        if ($result !== false) {
            $this->_state = (!empty($updateId))?self::STATE_UPDATED:self::STATE_CREATED;
        }
        return $result;
    }

    /**
     * Get options by prefix (notifications stored in json format).
     * @see WSAL_Adapters_MySQL_Option::GetNotificationsSetting()
     * @param string $opt_prefix prefix
     * @return array|null options
     */
    public function GetNotificationsSetting($opt_prefix)
    {
        return $this->getAdapter()->GetNotificationsSetting($opt_prefix);
    }

    /**
     * Get option by id (notifications stored in json format).
     * @see WSAL_Adapters_MySQL_Option::GetNotification()
     * @param int $id option ID
     * @return string|null option
     */
    public function GetNotification($id)
    {
        return $this->LoadData(
            $this->getAdapter()->GetNotification($id)
        );
    }

    /**
     * Delete option by name.
     * @see WSAL_Adapters_MySQL_Option::DeleteByName()
     * @param string $name option_name
     * @return boolean
     */
    public function DeleteByName($name)
    {
        return $this->getAdapter()->DeleteByName($name);
    }

    /**
     * Delete options start with prefix.
     * @see WSAL_Adapters_MySQL_Option::DeleteByPrefix()
     * @param string $opt_prefix prefix
     * @return boolean
     */
    public function DeleteByPrefix($opt_prefix)
    {
        return $this->getAdapter()->DeleteByPrefix($opt_prefix);
    }

    /**
     * Number of options start with prefix.
     * @see WSAL_Adapters_MySQL_Option::CountNotifications()
     * @param string $opt_prefix prefix
     * @return integer Indicates the number of items.
     */
    public function CountNotifications($opt_prefix)
    {
        return $this->getAdapter()->CountNotifications($opt_prefix);
    }
}
