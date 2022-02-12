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
use WSAL_Vendor\Monolog\ResettableInterface;
use WSAL_Vendor\Monolog\Formatter\FormatterInterface;
use WSAL_Vendor\Psr\Log\LogLevel;
/**
 * Simple handler wrapper that filters records based on a list of levels
 *
 * It can be configured with an exact list of levels to allow, or a min/max level.
 *
 * @author Hennadiy Verkh
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @phpstan-import-type Record from \Monolog\Logger
 * @phpstan-import-type Level from \Monolog\Logger
 * @phpstan-import-type LevelName from \Monolog\Logger
 */
class FilterHandler extends \WSAL_Vendor\Monolog\Handler\Handler implements \WSAL_Vendor\Monolog\Handler\ProcessableHandlerInterface, \WSAL_Vendor\Monolog\ResettableInterface, \WSAL_Vendor\Monolog\Handler\FormattableHandlerInterface
{
    use ProcessableHandlerTrait;
    /**
     * Handler or factory callable($record, $this)
     *
     * @var callable|HandlerInterface
     * @phpstan-var callable(?Record, HandlerInterface): HandlerInterface|HandlerInterface
     */
    protected $handler;
    /**
     * Minimum level for logs that are passed to handler
     *
     * @var int[]
     * @phpstan-var Level[]
     */
    protected $acceptedLevels;
    /**
     * Whether the messages that are handled can bubble up the stack or not
     *
     * @var bool
     */
    protected $bubble;
    /**
     * @psalm-param HandlerInterface|callable(?Record, HandlerInterface): HandlerInterface $handler
     *
     * @param callable|HandlerInterface $handler        Handler or factory callable($record|null, $filterHandler).
     * @param int|array                 $minLevelOrList A list of levels to accept or a minimum level if maxLevel is provided
     * @param int|string                $maxLevel       Maximum level to accept, only used if $minLevelOrList is not an array
     * @param bool                      $bubble         Whether the messages that are handled can bubble up the stack or not
     *
     * @phpstan-param Level|LevelName|LogLevel::*|array<Level|LevelName|LogLevel::*> $minLevelOrList
     * @phpstan-param Level|LevelName|LogLevel::* $maxLevel
     */
    public function __construct($handler, $minLevelOrList = \WSAL_Vendor\Monolog\Logger::DEBUG, $maxLevel = \WSAL_Vendor\Monolog\Logger::EMERGENCY, bool $bubble = \true)
    {
        $this->handler = $handler;
        $this->bubble = $bubble;
        $this->setAcceptedLevels($minLevelOrList, $maxLevel);
        if (!$this->handler instanceof \WSAL_Vendor\Monolog\Handler\HandlerInterface && !\is_callable($this->handler)) {
            throw new \RuntimeException("The given handler (" . \json_encode($this->handler) . ") is not a callable nor a Monolog\\Handler\\HandlerInterface object");
        }
    }
    /**
     * @phpstan-return Level[]
     */
    public function getAcceptedLevels() : array
    {
        return \array_flip($this->acceptedLevels);
    }
    /**
     * @param int|string|array $minLevelOrList A list of levels to accept or a minimum level or level name if maxLevel is provided
     * @param int|string       $maxLevel       Maximum level or level name to accept, only used if $minLevelOrList is not an array
     *
     * @phpstan-param Level|LevelName|LogLevel::*|array<Level|LevelName|LogLevel::*> $minLevelOrList
     * @phpstan-param Level|LevelName|LogLevel::*                                    $maxLevel
     */
    public function setAcceptedLevels($minLevelOrList = \WSAL_Vendor\Monolog\Logger::DEBUG, $maxLevel = \WSAL_Vendor\Monolog\Logger::EMERGENCY) : self
    {
        if (\is_array($minLevelOrList)) {
            $acceptedLevels = \array_map('Monolog\\Logger::toMonologLevel', $minLevelOrList);
        } else {
            $minLevelOrList = \WSAL_Vendor\Monolog\Logger::toMonologLevel($minLevelOrList);
            $maxLevel = \WSAL_Vendor\Monolog\Logger::toMonologLevel($maxLevel);
            $acceptedLevels = \array_values(\array_filter(\WSAL_Vendor\Monolog\Logger::getLevels(), function ($level) use($minLevelOrList, $maxLevel) {
                return $level >= $minLevelOrList && $level <= $maxLevel;
            }));
        }
        $this->acceptedLevels = \array_flip($acceptedLevels);
        return $this;
    }
    /**
     * {@inheritDoc}
     */
    public function isHandling(array $record) : bool
    {
        return isset($this->acceptedLevels[$record['level']]);
    }
    /**
     * {@inheritDoc}
     */
    public function handle(array $record) : bool
    {
        if (!$this->isHandling($record)) {
            return \false;
        }
        if ($this->processors) {
            /** @var Record $record */
            $record = $this->processRecord($record);
        }
        $this->getHandler($record)->handle($record);
        return \false === $this->bubble;
    }
    /**
     * {@inheritDoc}
     */
    public function handleBatch(array $records) : void
    {
        $filtered = [];
        foreach ($records as $record) {
            if ($this->isHandling($record)) {
                $filtered[] = $record;
            }
        }
        if (\count($filtered) > 0) {
            $this->getHandler($filtered[\count($filtered) - 1])->handleBatch($filtered);
        }
    }
    /**
     * Return the nested handler
     *
     * If the handler was provided as a factory callable, this will trigger the handler's instantiation.
     *
     * @return HandlerInterface
     *
     * @phpstan-param Record $record
     */
    public function getHandler(array $record = null)
    {
        if (!$this->handler instanceof \WSAL_Vendor\Monolog\Handler\HandlerInterface) {
            $this->handler = ($this->handler)($record, $this);
            if (!$this->handler instanceof \WSAL_Vendor\Monolog\Handler\HandlerInterface) {
                throw new \RuntimeException("The factory callable should return a HandlerInterface");
            }
        }
        return $this->handler;
    }
    /**
     * {@inheritDoc}
     */
    public function setFormatter(\WSAL_Vendor\Monolog\Formatter\FormatterInterface $formatter) : \WSAL_Vendor\Monolog\Handler\HandlerInterface
    {
        $handler = $this->getHandler();
        if ($handler instanceof \WSAL_Vendor\Monolog\Handler\FormattableHandlerInterface) {
            $handler->setFormatter($formatter);
            return $this;
        }
        throw new \UnexpectedValueException('The nested handler of type ' . \get_class($handler) . ' does not support formatters.');
    }
    /**
     * {@inheritDoc}
     */
    public function getFormatter() : \WSAL_Vendor\Monolog\Formatter\FormatterInterface
    {
        $handler = $this->getHandler();
        if ($handler instanceof \WSAL_Vendor\Monolog\Handler\FormattableHandlerInterface) {
            return $handler->getFormatter();
        }
        throw new \UnexpectedValueException('The nested handler of type ' . \get_class($handler) . ' does not support formatters.');
    }
    public function reset()
    {
        $this->resetProcessors();
        if ($this->getHandler() instanceof \WSAL_Vendor\Monolog\ResettableInterface) {
            $this->getHandler()->reset();
        }
    }
}
