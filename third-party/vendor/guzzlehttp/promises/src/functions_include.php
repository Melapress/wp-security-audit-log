<?php

namespace WSAL_Vendor;

// Don't redefine the functions if included multiple times.
if (!\function_exists('WSAL_Vendor\\GuzzleHttp\\Promise\\promise_for')) {
    require __DIR__ . '/functions.php';
}
