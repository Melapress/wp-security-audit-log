<?php
/**
 * @package Wsal
 *
 * Abstract class used in the Logger.
 * @see Loggers/Database.php
 */
abstract class WSAL_AbstractLogger
{
    /**
     * @var WpSecurityAuditLog
     */
    protected $plugin;

    public function __construct(WpSecurityAuditLog $plugin)
    {
        $this->plugin = $plugin;
    }
    
    /**
     * Log alert abstract.
     * @param integer $type alert code
     * @param array $data Metadata
     * @param integer $date (Optional) created_on
     * @param integer $siteid (Optional) site_id
     * @param bool $migrated (Optional) is_migrated
     */
    public abstract function Log($type, $data = array(), $date = null, $siteid = null, $migrated = false);
}
