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

use WSAL_Vendor\Gelf\PublisherInterface;
use WSAL_Vendor\Monolog\Logger;
use WSAL_Vendor\Monolog\Formatter\GelfMessageFormatter;
use WSAL_Vendor\Monolog\Formatter\FormatterInterface;
/**
 * Handler to send messages to a Graylog2 (http://www.graylog2.org) server
 *
 * @author Matt Lehner <mlehner@gmail.com>
 * @author Benjamin Zikarsky <benjamin@zikarsky.de>
 */
class GelfHandler extends \WSAL_Vendor\Monolog\Handler\AbstractProcessingHandler
{
    /**
     * @var PublisherInterface the publisher object that sends the message to the server
     */
    protected $publisher;
    /**
     * @param PublisherInterface $publisher a gelf publisher object
     */
    public function __construct(\WSAL_Vendor\Gelf\PublisherInterface $publisher, $level = \WSAL_Vendor\Monolog\Logger::DEBUG, bool $bubble = \true)
    {
        parent::__construct($level, $bubble);
        $this->publisher = $publisher;
    }
    /**
     * {@inheritDoc}
     */
    protected function write(array $record) : void
    {
        $this->publisher->publish($record['formatted']);
    }
    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter() : \WSAL_Vendor\Monolog\Formatter\FormatterInterface
    {
        return new \WSAL_Vendor\Monolog\Formatter\GelfMessageFormatter();
    }
}
