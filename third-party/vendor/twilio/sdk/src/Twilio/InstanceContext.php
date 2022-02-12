<?php

namespace WSAL_Vendor\Twilio;

class InstanceContext
{
    protected $version;
    protected $solution = [];
    protected $uri;
    public function __construct(\WSAL_Vendor\Twilio\Version $version)
    {
        $this->version = $version;
    }
    public function __toString() : string
    {
        return '[InstanceContext]';
    }
}
