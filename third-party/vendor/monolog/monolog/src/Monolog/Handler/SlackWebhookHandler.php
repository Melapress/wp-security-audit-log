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

use WSAL_Vendor\Monolog\Formatter\FormatterInterface;
use WSAL_Vendor\Monolog\Logger;
use WSAL_Vendor\Monolog\Utils;
use WSAL_Vendor\Monolog\Handler\Slack\SlackRecord;
/**
 * Sends notifications through Slack Webhooks
 *
 * @author Haralan Dobrev <hkdobrev@gmail.com>
 * @see    https://api.slack.com/incoming-webhooks
 */
class SlackWebhookHandler extends \WSAL_Vendor\Monolog\Handler\AbstractProcessingHandler
{
    /**
     * Slack Webhook token
     * @var string
     */
    private $webhookUrl;
    /**
     * Instance of the SlackRecord util class preparing data for Slack API.
     * @var SlackRecord
     */
    private $slackRecord;
    /**
     * @param string      $webhookUrl             Slack Webhook URL
     * @param string|null $channel                Slack channel (encoded ID or name)
     * @param string|null $username               Name of a bot
     * @param bool        $useAttachment          Whether the message should be added to Slack as attachment (plain text otherwise)
     * @param string|null $iconEmoji              The emoji name to use (or null)
     * @param bool        $useShortAttachment     Whether the the context/extra messages added to Slack as attachments are in a short style
     * @param bool        $includeContextAndExtra Whether the attachment should include context and extra data
     * @param string[]    $excludeFields          Dot separated list of fields to exclude from slack message. E.g. ['context.field1', 'extra.field2']
     */
    public function __construct(string $webhookUrl, ?string $channel = null, ?string $username = null, bool $useAttachment = \true, ?string $iconEmoji = null, bool $useShortAttachment = \false, bool $includeContextAndExtra = \false, $level = \WSAL_Vendor\Monolog\Logger::CRITICAL, bool $bubble = \true, array $excludeFields = array())
    {
        parent::__construct($level, $bubble);
        $this->webhookUrl = $webhookUrl;
        $this->slackRecord = new \WSAL_Vendor\Monolog\Handler\Slack\SlackRecord($channel, $username, $useAttachment, $iconEmoji, $useShortAttachment, $includeContextAndExtra, $excludeFields);
    }
    public function getSlackRecord() : \WSAL_Vendor\Monolog\Handler\Slack\SlackRecord
    {
        return $this->slackRecord;
    }
    public function getWebhookUrl() : string
    {
        return $this->webhookUrl;
    }
    /**
     * {@inheritDoc}
     */
    protected function write(array $record) : void
    {
        $postData = $this->slackRecord->getSlackData($record);
        $postString = \WSAL_Vendor\Monolog\Utils::jsonEncode($postData);
        $ch = \curl_init();
        $options = array(\CURLOPT_URL => $this->webhookUrl, \CURLOPT_POST => \true, \CURLOPT_RETURNTRANSFER => \true, \CURLOPT_HTTPHEADER => array('Content-type: application/json'), \CURLOPT_POSTFIELDS => $postString);
        if (\defined('CURLOPT_SAFE_UPLOAD')) {
            $options[\CURLOPT_SAFE_UPLOAD] = \true;
        }
        \curl_setopt_array($ch, $options);
        \WSAL_Vendor\Monolog\Handler\Curl\Util::execute($ch);
    }
    public function setFormatter(\WSAL_Vendor\Monolog\Formatter\FormatterInterface $formatter) : \WSAL_Vendor\Monolog\Handler\HandlerInterface
    {
        parent::setFormatter($formatter);
        $this->slackRecord->setFormatter($formatter);
        return $this;
    }
    public function getFormatter() : \WSAL_Vendor\Monolog\Formatter\FormatterInterface
    {
        $formatter = parent::getFormatter();
        $this->slackRecord->setFormatter($formatter);
        return $formatter;
    }
}
