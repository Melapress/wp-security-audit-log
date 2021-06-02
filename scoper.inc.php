<?php
/**
 * PHP-Scoper configuration file.
 *
 * @package   Google\Site_Kit
 * @copyright 2021 Google LLC
 * @license   https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link      https://sitekit.withgoogle.com
 */

use Isolated\Symfony\Component\Finder\Finder;

return array(
	'prefix'                     => 'WSAL_Vendor',
	'finders'                    => array(

		// General dependencies, except Google API services.
		Finder::create()
		      ->files()
		      ->ignoreVCS( true )
		      ->notName( '/LICENSE|.*\\.md|.*\\.dist|Makefile|composer\\.(json|lock)/' )
		      ->exclude(
			      array(
				      'doc',
				      'test',
				      'test_old',
				      'tests',
				      'Tests',
				      'vendor-bin',
			      )
		      )
		      ->path( '#^guzzlehttp/#' )
		      ->path( '#^mirazmac/#' )
		      ->path( '#^monolog/#' )
		      ->path( '#^mtdowling/#' )
		      ->path( '#^psr/#' )
		      ->path( '#^ralouphie/#' )
		      ->path( '#^symfony/#' )
		      ->path( '#^twilio/#' )
		      ->in( 'vendor' ),
	),
	'files-whitelist'            => array(

		// This dependency is a global function which should remain global.
		'vendor/ralouphie/getallheaders/src/getallheaders.php',
	),
	'patchers'                   => array(
	),
	'whitelist'                  => array(
		'AWS\*'
	),
	'whitelist-global-constants' => false,
	'whitelist-global-classes'   => false,
	'whitelist-global-functions' => false,
);