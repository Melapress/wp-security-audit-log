<?php

namespace WSAL_Vendor\Twilio;

use WSAL_Vendor\Twilio\Exceptions\DeserializeException;
use WSAL_Vendor\Twilio\Exceptions\RestException;
use WSAL_Vendor\Twilio\Http\Response;
abstract class Page implements \Iterator
{
    protected static $metaKeys = ['end', 'first_page_uri', 'next_page_uri', 'last_page_uri', 'page', 'page_size', 'previous_page_uri', 'total', 'num_pages', 'start', 'uri'];
    protected $version;
    protected $payload;
    protected $solution;
    protected $records;
    public abstract function buildInstance(array $payload);
    public function __construct(\WSAL_Vendor\Twilio\Version $version, \WSAL_Vendor\Twilio\Http\Response $response)
    {
        $payload = $this->processResponse($response);
        $this->version = $version;
        $this->payload = $payload;
        $this->solution = [];
        $this->records = new \ArrayIterator($this->loadPage());
    }
    protected function processResponse(\WSAL_Vendor\Twilio\Http\Response $response)
    {
        if ($response->getStatusCode() !== 200 && !$this->isPagingEol($response->getContent())) {
            $message = '[HTTP ' . $response->getStatusCode() . '] Unable to fetch page';
            $code = $response->getStatusCode();
            $content = $response->getContent();
            $details = [];
            $moreInfo = '';
            if (\is_array($content)) {
                $message .= isset($content['message']) ? ': ' . $content['message'] : '';
                $code = $content['code'] ?? $code;
                $moreInfo = $content['more_info'] ?? '';
                $details = $content['details'] ?? [];
            }
            throw new \WSAL_Vendor\Twilio\Exceptions\RestException($message, $code, $response->getStatusCode(), $moreInfo, $details);
        }
        return $response->getContent();
    }
    protected function isPagingEol(?array $content) : bool
    {
        return $content !== null && \array_key_exists('code', $content) && $content['code'] === 20006;
    }
    protected function hasMeta(string $key) : bool
    {
        return \array_key_exists('meta', $this->payload) && \array_key_exists($key, $this->payload['meta']);
    }
    protected function getMeta(string $key, string $default = null) : ?string
    {
        return $this->hasMeta($key) ? $this->payload['meta'][$key] : $default;
    }
    protected function loadPage() : array
    {
        $key = $this->getMeta('key');
        if ($key) {
            return $this->payload[$key];
        }
        $keys = \array_keys($this->payload);
        $key = \array_diff($keys, self::$metaKeys);
        $key = \array_values($key);
        if (\count($key) === 1) {
            return $this->payload[$key[0]];
        }
        // handle end of results error code
        if ($this->isPagingEol($this->payload)) {
            return [];
        }
        throw new \WSAL_Vendor\Twilio\Exceptions\DeserializeException('Page Records can not be deserialized');
    }
    public function getPreviousPageUrl() : ?string
    {
        if ($this->hasMeta('previous_page_url')) {
            return $this->getMeta('previous_page_url');
        } else {
            if (\array_key_exists('previous_page_uri', $this->payload) && $this->payload['previous_page_uri']) {
                return $this->getVersion()->getDomain()->absoluteUrl($this->payload['previous_page_uri']);
            }
        }
        return null;
    }
    public function getNextPageUrl() : ?string
    {
        if ($this->hasMeta('next_page_url')) {
            return $this->getMeta('next_page_url');
        } else {
            if (\array_key_exists('next_page_uri', $this->payload) && $this->payload['next_page_uri']) {
                return $this->getVersion()->getDomain()->absoluteUrl($this->payload['next_page_uri']);
            }
        }
        return null;
    }
    public function nextPage() : ?\WSAL_Vendor\Twilio\Page
    {
        if (!$this->getNextPageUrl()) {
            return null;
        }
        $response = $this->getVersion()->getDomain()->getClient()->request('GET', $this->getNextPageUrl());
        return new static($this->getVersion(), $response, $this->solution);
    }
    public function previousPage() : ?\WSAL_Vendor\Twilio\Page
    {
        if (!$this->getPreviousPageUrl()) {
            return null;
        }
        $response = $this->getVersion()->getDomain()->getClient()->request('GET', $this->getPreviousPageUrl());
        return new static($this->getVersion(), $response, $this->solution);
    }
    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     */
    public function current()
    {
        return $this->buildInstance($this->records->current());
    }
    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next() : void
    {
        $this->records->next();
    }
    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     */
    public function key()
    {
        return $this->records->key();
    }
    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return bool The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     */
    public function valid() : bool
    {
        return $this->records->valid();
    }
    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     */
    public function rewind() : void
    {
        $this->records->rewind();
    }
    public function getVersion() : \WSAL_Vendor\Twilio\Version
    {
        return $this->version;
    }
    public function __toString() : string
    {
        return '[Page]';
    }
}
