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
namespace WSAL_Vendor\Monolog\Test;

use WSAL_Vendor\Monolog\Logger;
use WSAL_Vendor\Monolog\DateTimeImmutable;
use WSAL_Vendor\Monolog\Formatter\FormatterInterface;
/**
 * Lets you easily generate log records and a dummy formatter for testing purposes
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @phpstan-import-type Record from \Monolog\Logger
 * @phpstan-import-type Level from \Monolog\Logger
 */
class TestCase extends \WSAL_Vendor\PHPUnit\Framework\TestCase
{
    /**
     * @param mixed[] $context
     *
     * @return array Record
     *
     * @phpstan-param  Level $level
     * @phpstan-return Record
     */
    protected function getRecord(int $level = \WSAL_Vendor\Monolog\Logger::WARNING, string $message = 'test', array $context = []) : array
    {
        return ['message' => (string) $message, 'context' => $context, 'level' => $level, 'level_name' => \WSAL_Vendor\Monolog\Logger::getLevelName($level), 'channel' => 'test', 'datetime' => new \WSAL_Vendor\Monolog\DateTimeImmutable(\true), 'extra' => []];
    }
    /**
     * @phpstan-return Record[]
     */
    protected function getMultipleRecords() : array
    {
        return [$this->getRecord(\WSAL_Vendor\Monolog\Logger::DEBUG, 'debug message 1'), $this->getRecord(\WSAL_Vendor\Monolog\Logger::DEBUG, 'debug message 2'), $this->getRecord(\WSAL_Vendor\Monolog\Logger::INFO, 'information'), $this->getRecord(\WSAL_Vendor\Monolog\Logger::WARNING, 'warning'), $this->getRecord(\WSAL_Vendor\Monolog\Logger::ERROR, 'error')];
    }
    protected function getIdentityFormatter() : \WSAL_Vendor\Monolog\Formatter\FormatterInterface
    {
        $formatter = $this->createMock(\WSAL_Vendor\Monolog\Formatter\FormatterInterface::class);
        $formatter->expects($this->any())->method('format')->will($this->returnCallback(function ($record) {
            return $record['message'];
        }));
        return $formatter;
    }
}
