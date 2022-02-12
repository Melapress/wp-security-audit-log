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

use WSAL_Vendor\Monolog\Logger;
use WSAL_Vendor\Monolog\Formatter\NormalizerFormatter;
use WSAL_Vendor\Monolog\Formatter\FormatterInterface;
use WSAL_Vendor\Doctrine\CouchDB\CouchDBClient;
/**
 * CouchDB handler for Doctrine CouchDB ODM
 *
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
class DoctrineCouchDBHandler extends \WSAL_Vendor\Monolog\Handler\AbstractProcessingHandler
{
    /** @var CouchDBClient */
    private $client;
    public function __construct(\WSAL_Vendor\Doctrine\CouchDB\CouchDBClient $client, $level = \WSAL_Vendor\Monolog\Logger::DEBUG, bool $bubble = \true)
    {
        $this->client = $client;
        parent::__construct($level, $bubble);
    }
    /**
     * {@inheritDoc}
     */
    protected function write(array $record) : void
    {
        $this->client->postDocument($record['formatted']);
    }
    protected function getDefaultFormatter() : \WSAL_Vendor\Monolog\Formatter\FormatterInterface
    {
        return new \WSAL_Vendor\Monolog\Formatter\NormalizerFormatter();
    }
}
