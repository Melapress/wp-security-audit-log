<?php
/**
 * WSAL Freemius SDK
 *
 * Freemius SDK initialization file for WSAL.
 *
 * @since 2.7.0
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( file_exists( dirname( __FILE__ ) . '/freemius/start.php' ) ) {
	/**
	 * Freemius SDK
	 *
	 * Create a helper function for easy SDK access.
	 *
	 * @return array
	 * @author Ashar Irfan
	 * @since  2.7.0
	 */
	function wsal_freemius() {
		global $wsal_freemius;

		if ( ! isset( $wsal_freemius ) ) {
			// Include Freemius SDK.
			require_once dirname( __FILE__ ) . '/freemius/start.php';

			$wsal_freemius = fs_dynamic_init( array(
				'id'                  => '94',
				'slug'                => 'wp-security-audit-log',
				'type'                => 'plugin',
				'public_key'          => 'pk_d602740d3088272d75906045af9fa',
				'is_premium'          => true,
				// If your plugin is a serviceware, set this option to false.
				'has_premium_version' => true,
				'has_addons'          => false,
				'has_paid_plans'      => true,
				'menu'                => array(
					'slug'           => 'wsal-auditlog',
					'support'        => false,
				),
				// Set the SDK to work in a sandbox mode (for development & testing).
				// IMPORTANT: MAKE SURE TO REMOVE SECRET KEY BEFORE DEPLOYMENT.
				'secret_key'          => 'sk_k}ImB7U^0NFo3fqZa{Zd62NoT!QeX',
			) );
		}

		return $wsal_freemius;
	}

	// Init Freemius.
	wsal_freemius();

	// Signal that SDK was initiated.
	do_action( 'wsal_freemius_loaded' );
}
