<?php

namespace WSAL_Vendor\Twilio;

class InstanceResource
{
    protected $version;
    protected $context;
    protected $properties = [];
    protected $solution = [];
    public function __construct(\WSAL_Vendor\Twilio\Version $version)
    {
        $this->version = $version;
    }
    public function toArray() : array
    {
        return $this->properties;
    }
    public function __toString() : string
    {
        return '[InstanceResource]';
    }
    public function __isset($name) : bool
    {
        return \array_key_exists($name, $this->properties);
    }
}
