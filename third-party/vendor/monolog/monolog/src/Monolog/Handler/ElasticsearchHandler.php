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

use Throwable;
use RuntimeException;
use WSAL_Vendor\Monolog\Logger;
use WSAL_Vendor\Monolog\Formatter\FormatterInterface;
use WSAL_Vendor\Monolog\Formatter\ElasticsearchFormatter;
use InvalidArgumentException;
use WSAL_Vendor\Elasticsearch\Common\Exceptions\RuntimeException as ElasticsearchRuntimeException;
use WSAL_Vendor\Elasticsearch\Client;
/**
 * Elasticsearch handler
 *
 * @link https://www.elastic.co/guide/en/elasticsearch/client/php-api/current/index.html
 *
 * Simple usage example:
 *
 *    $client = \Elasticsearch\ClientBuilder::create()
 *        ->setHosts($hosts)
 *        ->build();
 *
 *    $options = array(
 *        'index' => 'elastic_index_name',
 *        'type'  => 'elastic_doc_type',
 *    );
 *    $handler = new ElasticsearchHandler($client, $options);
 *    $log = new Logger('application');
 *    $log->pushHandler($handler);
 *
 * @author Avtandil Kikabidze <akalongman@gmail.com>
 */
class ElasticsearchHandler extends \WSAL_Vendor\Monolog\Handler\AbstractProcessingHandler
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
     * @param Client  $client  Elasticsearch Client object
     * @param mixed[] $options Handler configuration
     */
    public function __construct(\WSAL_Vendor\Elasticsearch\Client $client, array $options = [], $level = \WSAL_Vendor\Monolog\Logger::DEBUG, bool $bubble = \true)
    {
        parent::__construct($level, $bubble);
        $this->client = $client;
        $this->options = \array_merge([
            'index' => 'monolog',
            // Elastic index name
            'type' => '_doc',
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
        if ($formatter instanceof \WSAL_Vendor\Monolog\Formatter\ElasticsearchFormatter) {
            return parent::setFormatter($formatter);
        }
        throw new \InvalidArgumentException('ElasticsearchHandler is only compatible with ElasticsearchFormatter');
    }
    /**
     * Getter options
     *
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
        return new \WSAL_Vendor\Monolog\Formatter\ElasticsearchFormatter($this->options['index'], $this->options['type']);
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
     * @param  array[]           $records Records + _index/_type keys
     * @throws \RuntimeException
     */
    protected function bulkSend(array $records) : void
    {
        try {
            $params = ['body' => []];
            foreach ($records as $record) {
                $params['body'][] = ['index' => ['_index' => $record['_index'], '_type' => $record['_type']]];
                unset($record['_index'], $record['_type']);
                $params['body'][] = $record;
            }
            $responses = $this->client->bulk($params);
            if ($responses['errors'] === \true) {
                throw $this->createExceptionFromResponses($responses);
            }
        } catch (\Throwable $e) {
            if (!$this->options['ignore_error']) {
                throw new \RuntimeException('Error sending messages to Elasticsearch', 0, $e);
            }
        }
    }
    /**
     * Creates elasticsearch exception from responses array
     *
     * Only the first error is converted into an exception.
     *
     * @param mixed[] $responses returned by $this->client->bulk()
     */
    protected function createExceptionFromResponses(array $responses) : \WSAL_Vendor\Elasticsearch\Common\Exceptions\RuntimeException
    {
        foreach ($responses['items'] ?? [] as $item) {
            if (isset($item['index']['error'])) {
                return $this->createExceptionFromError($item['index']['error']);
            }
        }
        return new \WSAL_Vendor\Elasticsearch\Common\Exceptions\RuntimeException('Elasticsearch failed to index one or more records.');
    }
    /**
     * Creates elasticsearch exception from error array
     *
     * @param mixed[] $error
     */
    protected function createExceptionFromError(array $error) : \WSAL_Vendor\Elasticsearch\Common\Exceptions\RuntimeException
    {
        $previous = isset($error['caused_by']) ? $this->createExceptionFromError($error['caused_by']) : null;
        return new \WSAL_Vendor\Elasticsearch\Common\Exceptions\RuntimeException($error['type'] . ': ' . $error['reason'], 0, $previous);
    }
}
