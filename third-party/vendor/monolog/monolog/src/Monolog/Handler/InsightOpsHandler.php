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
 * Inspired on LogEntriesHandler.
 *
 * @author Robert Kaufmann III <rok3@rok3.me>
 * @author Gabriel Machado <gabriel.ms1@hotmail.com>
 */
class InsightOpsHandler extends \WSAL_Vendor\Monolog\Handler\SocketHandler
{
    /**
     * @var string
     */
    protected $logToken;
    /**
     * @param string     $token  Log token supplied by InsightOps
     * @param string     $region Region where InsightOps account is hosted. Could be 'us' or 'eu'.
     * @param bool       $useSSL Whether or not SSL encryption should be used
     *
     * @throws MissingExtensionException If SSL encryption is set to true and OpenSSL is missing
     */
    public function __construct(string $token, string $region = 'us', bool $useSSL = \true, $level = \WSAL_Vendor\Monolog\Logger::DEBUG, bool $bubble = \true)
    {
        if ($useSSL && !\extension_loaded('openssl')) {
            throw new \WSAL_Vendor\Monolog\Handler\MissingExtensionException('The OpenSSL PHP plugin is required to use SSL encrypted connection for InsightOpsHandler');
        }
        $endpoint = $useSSL ? 'ssl://' . $region . '.data.logs.insight.rapid7.com:443' : $region . '.data.logs.insight.rapid7.com:80';
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
