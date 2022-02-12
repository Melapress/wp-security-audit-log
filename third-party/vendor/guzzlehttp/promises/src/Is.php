<?php

namespace WSAL_Vendor\GuzzleHttp\Promise;

final class Is
{
    /**
     * Returns true if a promise is pending.
     *
     * @return bool
     */
    public static function pending(\WSAL_Vendor\GuzzleHttp\Promise\PromiseInterface $promise)
    {
        return $promise->getState() === \WSAL_Vendor\GuzzleHttp\Promise\PromiseInterface::PENDING;
    }
    /**
     * Returns true if a promise is fulfilled or rejected.
     *
     * @return bool
     */
    public static function settled(\WSAL_Vendor\GuzzleHttp\Promise\PromiseInterface $promise)
    {
        return $promise->getState() !== \WSAL_Vendor\GuzzleHttp\Promise\PromiseInterface::PENDING;
    }
    /**
     * Returns true if a promise is fulfilled.
     *
     * @return bool
     */
    public static function fulfilled(\WSAL_Vendor\GuzzleHttp\Promise\PromiseInterface $promise)
    {
        return $promise->getState() === \WSAL_Vendor\GuzzleHttp\Promise\PromiseInterface::FULFILLED;
    }
    /**
     * Returns true if a promise is rejected.
     *
     * @return bool
     */
    public static function rejected(\WSAL_Vendor\GuzzleHttp\Promise\PromiseInterface $promise)
    {
        return $promise->getState() === \WSAL_Vendor\GuzzleHttp\Promise\PromiseInterface::REJECTED;
    }
}
