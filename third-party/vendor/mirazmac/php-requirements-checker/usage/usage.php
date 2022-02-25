<?php

namespace WSAL_Vendor;

require '../vendor/autoload.php';
use WSAL_Vendor\MirazMac\Requirements\Checker;
$checker = new Checker();
// Define requirements
// Make sure the PHP version is equal to or greater than 5.6
// Pass preferred php.ini values as an array
// Note the usage of boolean instead of On/1/Off/0
// Ensures allow_url_fopen is On
$checker->requirePhpVersion('>=5.6')->requirePhpExtensions(['pdo', 'mbstring'])->requireFunctions(['random_bytes'])->requireFile('../composer.json', Checker::CHECK_FILE_EXISTS)->requireDirectory('../src', Checker::CHECK_IS_READABLE)->requireIniValues(['allow_url_fopen' => \true, 'short_open_tag' => \true, 'memory_limit' => '>=64M']);
// Runs the check and returns parsed requirements as an array
// Contains parsed requirements with state of the current values and their comparison result
$output = $checker->check();
// Should be called after running check() to see if requirements has met or not
$satisfied = $checker->isSatisfied();
if ($satisfied) {
    echo "Requirements are met.";
} else {
    echo \join(', ', $checker->getErrors());
}
