<?php

/**
 * Wordpress options are always loaded from the default wordpress database.
 */
class WSAL_Models_Option extends WSAL_Models_ActiveRecord
{

    protected $adapterName = "Option";
    public $option_name = '';
    public $option_value = '';
    /**
     * Options are always stored in WPDB. This setting ensures that
     */
    public $useDefaultAdapter = true;

    public function SetOptionValue($name, $value)
    {
        $this->getAdapter()->GetNamedOption($name);
        $this->option_name = $name;
        // Serialize if $value is array or object
        $value = maybe_serialize($value);
        $this->option_value = $value;
        $this->getAdapter()->Save($this);
    }
    
    public function GetOptionValue($name, $default = array())
    {
        $this->option_value = $this->getAdapter()->GetNamedOption($name);
        // Unerialize if $value is array or object
        $this->option_value = maybe_unserialize($this->option_value);
        return $this->IsLoaded() ? $this->option_value : $default;
    }
}
