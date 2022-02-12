<?php

namespace WSAL_Vendor;

// Don't redefine the functions if included multiple times.
if (!\function_exists('WSAL_Vendor\\GuzzleHttp\\Psr7\\str')) {
    require __DIR__ . '/functions.php';
}
