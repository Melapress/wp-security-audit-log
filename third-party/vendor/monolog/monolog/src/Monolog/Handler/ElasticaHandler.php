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

use WSAL_Vendor\Elastica\Document;
use WSAL_Vendor\Monolog\Formatter\FormatterInterface;
use WSAL_Vendor\Monolog\Formatter\ElasticaFormatter;
use WSAL_Vendor\Monolog\Logger;
use WSAL_Vendor\Elastica\Client;
use WSAL_Vendor\Elastica\Exception\ExceptionInterface;
/**
 * Elastic Search handler
 *
 * Usage example:
 *
 *    $client = new \Elastica\Client();
 *    $options = array(
 *        'index' => 'elastic_index_name',
 *        'type' => 'elastic_doc_type', Types have been removed in Elastica 7
 *    );
 *    $handler = new ElasticaHandler($client, $options);
 *    $log = new Logger('application');
 *    $log->pushHandler($handler);
 *
 * @author Jelle Vink <jelle.vink@gmail.com>
 */
class ElasticaHandler extends \WSAL_Vendor\Monolog\Handler\AbstractProcessingHandler
{
    /**
     * @var Client
     */
    protected $client;
    /**
     * @var mixed[] Handler config options
     */
    protected $options = [];
    /**
     * @param Client  $client  Elastica Client object
     * @param mixed[] $options Handler configuration
     */
    public function __construct(\WSAL_Vendor\Elastica\Client $client, array $options = [], $level = \WSAL_Vendor\Monolog\Logger::DEBUG, bool $bubble = \true)
    {
        parent::__construct($level, $bubble);
        $this->client = $client;
        $this->options = \array_merge([
            'index' => 'monolog',
            // Elastic index name
            'type' => 'record',
            // Elastic document type
            'ignore_error' => \false,
        ], $options);
    }
    /**
     * {@inheritDoc}
     */
    protected function write(array $record) : void
    {
        $this->bulkSend([$record['formatted']]);
    }
    /**
     * {@inheritDoc}
     */
    public function setFormatter(\WSAL_Vendor\Monolog\Formatter\FormatterInterface $formatter) : \WSAL_Vendor\Monolog\Handler\HandlerInterface
    {
        if ($formatter instanceof \WSAL_Vendor\Monolog\Formatter\ElasticaFormatter) {
            return parent::setFormatter($formatter);
        }
        throw new \InvalidArgumentException('ElasticaHandler is only compatible with ElasticaFormatter');
    }
    /**
     * @return mixed[]
     */
    public function getOptions() : array
    {
        return $this->options;
    }
    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter() : \WSAL_Vendor\Monolog\Formatter\FormatterInterface
    {
        return new \WSAL_Vendor\Monolog\Formatter\ElasticaFormatter($this->options['index'], $this->options['type']);
    }
    /**
     * {@inheritDoc}
     */
    public function handleBatch(array $records) : void
    {
        $documents = $this->getFormatter()->formatBatch($records);
        $this->bulkSend($documents);
    }
    /**
     * Use Elasticsearch bulk API to send list of documents
     *
     * @param Document[] $documents
     *
     * @throws \RuntimeException
     */
    protected function bulkSend(array $documents) : void
    {
        try {
            $this->client->addDocuments($documents);
        } catch (\WSAL_Vendor\Elastica\Exception\ExceptionInterface $e) {
            if (!$this->options['ignore_error']) {
                throw new \RuntimeException("Error sending messages to Elasticsearch", 0, $e);
            }
        }
    }
}
