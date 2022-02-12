<?php

namespace WSAL_Vendor\GuzzleHttp\Handler;

use WSAL_Vendor\Psr\Http\Message\RequestInterface;
interface CurlFactoryInterface
{
    /**
     * Creates a cURL handle resource.
     *
     * @param RequestInterface $request Request
     * @param array            $options Transfer options
     *
     * @throws \RuntimeException when an option cannot be applied
     */
    public function create(\WSAL_Vendor\Psr\Http\Message\RequestInterface $request, array $options) : \WSAL_Vendor\GuzzleHttp\Handler\EasyHandle;
    /**
     * Release an easy handle, allowing it to be reused or closed.
     *
     * This function must call unset on the easy handle's "handle" property.
     */
    public function release(\WSAL_Vendor\GuzzleHttp\Handler\EasyHandle $easy) : void;
}
