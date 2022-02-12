<?php

declare (strict_types=1);
/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace WSAL_Vendor\Monolog\Handler;

use WSAL_Vendor\Monolog\Formatter\FormatterInterface;
use WSAL_Vendor\Monolog\Formatter\NormalizerFormatter;
use WSAL_Vendor\Monolog\Logger;
/**
 * Handler sending logs to Zend Monitor
 *
 * @author  Christian Bergau <cbergau86@gmail.com>
 * @author  Jason Davis <happydude@jasondavis.net>
 *
 * @phpstan-import-type FormattedRecord from AbstractProcessingHandler
 */
class ZendMonitorHandler extends \WSAL_Vendor\Monolog\Handler\AbstractProcessingHandler
{
    /**
     * Monolog level / ZendMonitor Custom Event priority map
     *
     * @var array<int, int>
     */
    protected $levelMap = [];
    /**
     * @throws MissingExtensionException
     */
    public function __construct($level = \WSAL_Vendor\Monolog\Logger::DEBUG, bool $bubble = \true)
    {
        if (!\function_exists('WSAL_Vendor\\zend_monitor_custom_event')) {
            throw new \WSAL_Vendor\Monolog\Handler\MissingExtensionException('You must have Zend Server installed with Zend Monitor enabled in order to use this handler');
        }
        //zend monitor constants are not defined if zend monitor is not enabled.
        $this->levelMap = [\WSAL_Vendor\Monolog\Logger::DEBUG => \WSAL_Vendor\ZEND_MONITOR_EVENT_SEVERITY_INFO, \WSAL_Vendor\Monolog\Logger::INFO => \WSAL_Vendor\ZEND_MONITOR_EVENT_SEVERITY_INFO, \WSAL_Vendor\Monolog\Logger::NOTICE => \WSAL_Vendor\ZEND_MONITOR_EVENT_SEVERITY_INFO, \WSAL_Vendor\Monolog\Logger::WARNING => \WSAL_Vendor\ZEND_MONITOR_EVENT_SEVERITY_WARNING, \WSAL_Vendor\Monolog\Logger::ERROR => \WSAL_Vendor\ZEND_MONITOR_EVENT_SEVERITY_ERROR, \WSAL_Vendor\Monolog\Logger::CRITICAL => \WSAL_Vendor\ZEND_MONITOR_EVENT_SEVERITY_ERROR, \WSAL_Vendor\Monolog\Logger::ALERT => \WSAL_Vendor\ZEND_MONITOR_EVENT_SEVERITY_ERROR, \WSAL_Vendor\Monolog\Logger::EMERGENCY => \WSAL_Vendor\ZEND_MONITOR_EVENT_SEVERITY_ERROR];
        parent::__construct($level, $bubble);
    }
    /**
     * {@inheritDoc}
     */
    protected function write(array $record) : void
    {
        $this->writeZendMonitorCustomEvent(\WSAL_Vendor\Monolog\Logger::getLevelName($record['level']), $record['message'], $record['formatted'], $this->levelMap[$record['level']]);
    }
    /**
     * Write to Zend Monitor Events
     * @param string $type      Text displayed in "Class Name (custom)" field
     * @param string $message   Text displayed in "Error String"
     * @param array  $formatted Displayed in Custom Variables tab
     * @param int    $severity  Set the event severity level (-1,0,1)
     *
     * @phpstan-param FormattedRecord $formatted
     */
    protected function writeZendMonitorCustomEvent(string $type, string $message, array $formatted, int $severity) : void
    {
        zend_monitor_custom_event($type, $message, $formatted, $severity);
    }
    /**
     * {@inheritDoc}
     */
    public function getDefaultFormatter() : \WSAL_Vendor\Monolog\Formatter\FormatterInterface
    {
        return new \WSAL_Vendor\Monolog\Formatter\NormalizerFormatter();
    }
    /**
     * @return array<int, int>
     */
    public function getLevelMap() : array
    {
        return $this->levelMap;
    }
}
