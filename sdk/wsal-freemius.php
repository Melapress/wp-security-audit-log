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
			define( 'WP_FS__PRODUCT_94_MULTISITE', true );
			// Include Freemius SDK.
			require_once dirname( __FILE__ ) . '/freemius/start.php';

			// Check anonymous mode.
			$is_anonymous = ( true === get_site_option( 'wpsal_anonymous_mode', true ) );
			$is_premium   = false;
			$is_anonymous = $is_premium ? false : $is_anonymous;

			$wsal_freemius = fs_dynamic_init( array(
				'id'             => '94',
				'slug'           => 'wp-security-audit-log',
				'type'           => 'plugin',
				'public_key'     => 'pk_d602740d3088272d75906045af9fa',
				'is_premium'     => $is_premium,
				'has_addons'     => false,
				'has_paid_plans' => true,
				'menu'           => array(
					'slug'    => 'wsal-auditlog',
					'support' => false,
					'network' => true,
				),
				'anonymous_mode' => $is_anonymous,
				'live'           => true,
			) );
		}

		return $wsal_freemius;
	}

	// Init Freemius.
	wsal_freemius();

	// Signal that SDK was initiated.
	do_action( 'wsal_freemius_loaded' );
}
