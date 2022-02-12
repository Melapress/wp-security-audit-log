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
namespace WSAL_Vendor\Monolog\Processor;

use WSAL_Vendor\Monolog\Logger;
use WSAL_Vendor\Psr\Log\LogLevel;
/**
 * Injects Git branch and Git commit SHA in all records
 *
 * @author Nick Otter
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @phpstan-import-type Level from \Monolog\Logger
 * @phpstan-import-type LevelName from \Monolog\Logger
 */
class GitProcessor implements \WSAL_Vendor\Monolog\Processor\ProcessorInterface
{
    /** @var int */
    private $level;
    /** @var array{branch: string, commit: string}|array<never>|null */
    private static $cache = null;
    /**
     * @param string|int $level The minimum logging level at which this Processor will be triggered
     *
     * @phpstan-param Level|LevelName|LogLevel::* $level
     */
    public function __construct($level = \WSAL_Vendor\Monolog\Logger::DEBUG)
    {
        $this->level = \WSAL_Vendor\Monolog\Logger::toMonologLevel($level);
    }
    /**
     * {@inheritDoc}
     */
    public function __invoke(array $record) : array
    {
        // return if the level is not high enough
        if ($record['level'] < $this->level) {
            return $record;
        }
        $record['extra']['git'] = self::getGitInfo();
        return $record;
    }
    /**
     * @return array{branch: string, commit: string}|array<never>
     */
    private static function getGitInfo() : array
    {
        if (self::$cache) {
            return self::$cache;
        }
        $branches = `git branch -v --no-abbrev`;
        if ($branches && \preg_match('{^\\* (.+?)\\s+([a-f0-9]{40})(?:\\s|$)}m', $branches, $matches)) {
            return self::$cache = ['branch' => $matches[1], 'commit' => $matches[2]];
        }
        return self::$cache = [];
    }
}
