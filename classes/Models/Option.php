<?php

/**
 * Wordpress options are always loaded from the default wordpress database.
 */
class WSAL_Models_Option extends WSAL_Models_ActiveRecord
{

    protected $adapterName = "Option";
    public $id = '';
    public $option_name = '';
    public $option_value = '';
    /**
     * Options are always stored in WPDB. This setting ensures that
     */
    protected $useDefaultAdapter = true;

    public function SetOptionValue($name, $value)
    {
        $option = $this->getAdapter()->GetNamedOption($name);
        $this->id = $option['id'];
        $this->option_name = $name;
        // Serialize if $value is array or object
        $value = maybe_serialize($value);
        $this->option_value = $value;
        $this->Save();
    }
    
    public function GetOptionValue($name, $default = array())
    {
        $this->option_value = $this->getAdapter()->GetNamedOption($name);
        // Unerialize if $value is array or object
        $this->option_value = maybe_unserialize($this->option_value);
        return $this->IsLoaded() ? $this->option_value : $default;
    }

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
}
