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
/**
 * @author Robert Kaufmann III <rok3@rok3.me>
 */
class LogEntriesHandler extends \WSAL_Vendor\Monolog\Handler\SocketHandler
{
    /**
     * @var string
     */
    protected $logToken;
    /**
     * @param string     $token  Log token supplied by LogEntries
     * @param bool       $useSSL Whether or not SSL encryption should be used.
     * @param string     $host   Custom hostname to send the data to if needed
     *
     * @throws MissingExtensionException If SSL encryption is set to true and OpenSSL is missing
     */
    public function __construct(string $token, bool $useSSL = \true, $level = \WSAL_Vendor\Monolog\Logger::DEBUG, bool $bubble = \true, string $host = 'data.logentries.com')
    {
        if ($useSSL && !\extension_loaded('openssl')) {
            throw new \WSAL_Vendor\Monolog\Handler\MissingExtensionException('The OpenSSL PHP plugin is required to use SSL encrypted connection for LogEntriesHandler');
        }
        $endpoint = $useSSL ? 'ssl://' . $host . ':443' : $host . ':80';
        parent::__construct($endpoint, $level, $bubble);
        $this->logToken = $token;
    }
    /**
     * {@inheritDoc}
     */
    protected function generateDataStream(array $record) : string
    {
        return $this->logToken . ' ' . $record['formatted'];
    }
}
