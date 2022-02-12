<?php

namespace WSAL_Vendor\Twilio\Http;

use WSAL_Vendor\GuzzleHttp\ClientInterface;
use WSAL_Vendor\GuzzleHttp\Exception\BadResponseException;
use WSAL_Vendor\GuzzleHttp\Psr7\Query;
use WSAL_Vendor\GuzzleHttp\Psr7\Request;
use WSAL_Vendor\Twilio\Exceptions\HttpException;
final class GuzzleClient implements \WSAL_Vendor\Twilio\Http\Client
{
    /**
     * @var ClientInterface
     */
    private $client;
    public function __construct(\WSAL_Vendor\GuzzleHttp\ClientInterface $client)
    {
        $this->client = $client;
    }
    public function request(string $method, string $url, array $params = [], array $data = [], array $headers = [], string $user = null, string $password = null, int $timeout = null) : \WSAL_Vendor\Twilio\Http\Response
    {
        try {
            $body = \WSAL_Vendor\GuzzleHttp\Psr7\Query::build($data, \PHP_QUERY_RFC1738);
            $options = ['timeout' => $timeout, 'auth' => [$user, $password], 'body' => $body];
            if ($params) {
                $options['query'] = $params;
            }
            $response = $this->client->send(new \WSAL_Vendor\GuzzleHttp\Psr7\Request($method, $url, $headers), $options);
        } catch (\WSAL_Vendor\GuzzleHttp\Exception\BadResponseException $exception) {
            $response = $exception->getResponse();
        } catch (\Exception $exception) {
            throw new \WSAL_Vendor\Twilio\Exceptions\HttpException('Unable to complete the HTTP request', 0, $exception);
        }
        // Casting the body (stream) to a string performs a rewind, ensuring we return the entire response.
        // See https://stackoverflow.com/a/30549372/86696
        return new \WSAL_Vendor\Twilio\Http\Response($response->getStatusCode(), (string) $response->getBody(), $response->getHeaders());
    }
}
