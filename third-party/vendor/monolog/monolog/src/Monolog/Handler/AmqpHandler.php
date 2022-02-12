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
use WSAL_Vendor\Monolog\Formatter\FormatterInterface;
use WSAL_Vendor\Monolog\Formatter\JsonFormatter;
use WSAL_Vendor\PhpAmqpLib\Message\AMQPMessage;
use WSAL_Vendor\PhpAmqpLib\Channel\AMQPChannel;
use AMQPExchange;
/**
 * @phpstan-import-type Record from \Monolog\Logger
 */
class AmqpHandler extends \WSAL_Vendor\Monolog\Handler\AbstractProcessingHandler
{
    /**
     * @var AMQPExchange|AMQPChannel $exchange
     */
    protected $exchange;
    /**
     * @var string
     */
    protected $exchangeName;
    /**
     * @param AMQPExchange|AMQPChannel $exchange     AMQPExchange (php AMQP ext) or PHP AMQP lib channel, ready for use
     * @param string|null              $exchangeName Optional exchange name, for AMQPChannel (PhpAmqpLib) only
     */
    public function __construct($exchange, ?string $exchangeName = null, $level = \WSAL_Vendor\Monolog\Logger::DEBUG, bool $bubble = \true)
    {
        if ($exchange instanceof \WSAL_Vendor\PhpAmqpLib\Channel\AMQPChannel) {
            $this->exchangeName = (string) $exchangeName;
        } elseif (!$exchange instanceof \AMQPExchange) {
            throw new \InvalidArgumentException('PhpAmqpLib\\Channel\\AMQPChannel or AMQPExchange instance required');
        } elseif ($exchangeName) {
            @\trigger_error('The $exchangeName parameter can only be passed when using PhpAmqpLib, if using an AMQPExchange instance configure it beforehand', \E_USER_DEPRECATED);
        }
        $this->exchange = $exchange;
        parent::__construct($level, $bubble);
    }
    /**
     * {@inheritDoc}
     */
    protected function write(array $record) : void
    {
        $data = $record["formatted"];
        $routingKey = $this->getRoutingKey($record);
        if ($this->exchange instanceof \AMQPExchange) {
            $this->exchange->publish($data, $routingKey, 0, ['delivery_mode' => 2, 'content_type' => 'application/json']);
        } else {
            $this->exchange->basic_publish($this->createAmqpMessage($data), $this->exchangeName, $routingKey);
        }
    }
    /**
     * {@inheritDoc}
     */
    public function handleBatch(array $records) : void
    {
        if ($this->exchange instanceof \AMQPExchange) {
            parent::handleBatch($records);
            return;
        }
        foreach ($records as $record) {
            if (!$this->isHandling($record)) {
                continue;
            }
            /** @var Record $record */
            $record = $this->processRecord($record);
            $data = $this->getFormatter()->format($record);
            $this->exchange->batch_basic_publish($this->createAmqpMessage($data), $this->exchangeName, $this->getRoutingKey($record));
        }
        $this->exchange->publish_batch();
    }
    /**
     * Gets the routing key for the AMQP exchange
     *
     * @phpstan-param Record $record
     */
    protected function getRoutingKey(array $record) : string
    {
        $routingKey = \sprintf('%s.%s', $record['level_name'], $record['channel']);
        return \strtolower($routingKey);
    }
    private function createAmqpMessage(string $data) : \WSAL_Vendor\PhpAmqpLib\Message\AMQPMessage
    {
        return new \WSAL_Vendor\PhpAmqpLib\Message\AMQPMessage($data, ['delivery_mode' => 2, 'content_type' => 'application/json']);
    }
    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter() : \WSAL_Vendor\Monolog\Formatter\FormatterInterface
    {
        return new \WSAL_Vendor\Monolog\Formatter\JsonFormatter(\WSAL_Vendor\Monolog\Formatter\JsonFormatter::BATCH_MODE_JSON, \false);
    }
}
