<?php

namespace WSAL_Vendor\GuzzleHttp;

use WSAL_Vendor\Psr\Http\Message\MessageInterface;
interface BodySummarizerInterface
{
    /**
     * Returns a summarized message body.
     */
    public function summarize(\WSAL_Vendor\Psr\Http\Message\MessageInterface $message) : ?string;
}
