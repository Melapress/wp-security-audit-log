<?php
/**
 * Licensing helper for WP Activity Log.
 *
 * @package    wsal
 * @subpackage licensing
 *
 * @since 5.6.8
 */

declare(strict_types=1);

namespace WSAL\Licensing;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Licensing\Licensing_Helper' ) ) {

	/**
	 * Helper methods for licensing that work only on the values they receive.
	 *
	 * They read and store nothing, so licensing classes and tests can use them freely.
	 *
	 * @since 5.6.8
	 */
	class Licensing_Helper {

		/**
		 * Error codes of store requests that got no answer, or one that could not be read.
		 *
		 * @var string[]
		 *
		 * @since 5.6.8
		 */
		private const UNANSWERED_ERROR_CODES = array(
			'store_unreachable',
			'store_invalid_response',
		);

		/**
		 * Returns the expiry time of a license.
		 *
		 * @param array $license_data - License data, with the expiry date in 'expires'.
		 *
		 * @return int|false $expires_at - Expiry time as a Unix timestamp, or false for lifetime licenses and missing or unreadable dates.
		 *
		 * @since 5.6.8
		 */
		public static function get_expiry_timestamp( array $license_data ) {
			$expires = (string) ( $license_data['expires'] ?? '' );

			if ( '' === $expires || 'lifetime' === $expires ) {
				return false;
			}

			return strtotime( $expires );
		}

		/**
		 * Checks whether the premium settings of a network must be read-only.
		 *
		 * An expired license, or a valid license that does not cover every site, keeps premium features
		 * running but makes their settings read-only. Every other status leaves premium code off.
		 *
		 * @param bool   $has_license_key - Whether a license key is stored.
		 * @param string $status          - License status reported by the store.
		 * @param bool   $is_over_limit   - Whether the network has more sites than the license covers.
		 *
		 * @return bool $is_frozen - True when premium settings must be read-only.
		 *
		 * @since 5.6.8
		 */
		public static function is_premium_frozen_multisite( bool $has_license_key, string $status, bool $is_over_limit ): bool {
			if ( ! $has_license_key ) {
				return false;
			}

			if ( 'expired' === $status ) {
				return true;
			}

			return 'valid' === $status && $is_over_limit;
		}

		/**
		 * Returns the URLs of the sites with an active activation.
		 *
		 * @param array $activations - Activation entries keyed by site URL.
		 *
		 * @return string[] $site_urls - Site URLs.
		 *
		 * @since 5.6.8
		 */
		public static function get_active_site_urls_multisite( array $activations ): array {
			$site_urls = array();

			foreach ( $activations as $site_url => $activation ) {
				if ( 'active' === ( $activation['status'] ?? '' ) ) {
					$site_urls[] = (string) $site_url;
				}
			}

			return $site_urls;
		}

		/**
		 * Returns the URLs of the network sites without an active activation.
		 *
		 * @param string[] $sites       - Site URLs of the network.
		 * @param array    $activations - Activation entries keyed by site URL.
		 *
		 * @return string[] $site_urls - Site URLs without an active activation, empty when every site is activated.
		 *
		 * @since 5.6.8
		 */
		public static function get_unlicensed_site_urls_multisite( array $sites, array $activations ): array {
			$site_urls = array();

			foreach ( $sites as $site_url ) {
				if ( 'active' !== ( $activations[ $site_url ]['status'] ?? '' ) ) {
					$site_urls[] = $site_url;
				}
			}

			return $site_urls;
		}

		/**
		 * Finds the stored activation URL of a site by its domain and path.
		 *
		 * The letter case of the domain and a trailing slash in the path are ignored.
		 *
		 * @param array  $activations - Activation entries keyed by site URL.
		 * @param string $domain      - Site domain.
		 * @param string $path        - Site path.
		 *
		 * @return string $site_url - Matching activation URL, or an empty string when none matches.
		 *
		 * @since 5.6.8
		 */
		public static function find_activation_url_multisite( array $activations, string $domain, string $path ): string {
			$wanted = strtolower( $domain ) . \untrailingslashit( $path );

			foreach ( array_keys( $activations ) as $site_url ) {
				$host      = (string) \wp_parse_url( (string) $site_url, PHP_URL_HOST );
				$site_path = (string) \wp_parse_url( (string) $site_url, PHP_URL_PATH );

				if ( strtolower( $host ) . \untrailingslashit( $site_path ) === $wanted ) {
					return (string) $site_url;
				}
			}

			return '';
		}

		/**
		 * Returns the sites a batch still has to activate.
		 *
		 * A site needs an activation when it has no active one, and is skipped when the batch already tried it.
		 *
		 * @param string[] $sites       - Site URLs of the network.
		 * @param array    $activations - Activation entries keyed by site URL.
		 * @param string   $batch_id    - ID of the batch.
		 *
		 * @return string[] $site_urls - Site URLs to activate.
		 *
		 * @since 5.6.8
		 */
		public static function get_sites_to_activate_multisite( array $sites, array $activations, string $batch_id ): array {
			$site_urls = array();

			foreach ( $sites as $site_url ) {
				$status         = $activations[ $site_url ]['status'] ?? '';
				$tried_in_batch = $activations[ $site_url ]['batch'] ?? '';

				if ( 'active' === $status || $batch_id === $tried_in_batch ) {
					continue;
				}

				$site_urls[] = $site_url;
			}

			return $site_urls;
		}

		/**
		 * Builds the stored activation entry for an activation result.
		 *
		 * @param bool|\WP_Error $result   - Result of EDD_Provider::try_activate_license().
		 * @param string         $batch_id - ID of the batch that tried the site, empty outside a batch.
		 *
		 * @return array $entry - Activation entry with 'status', 'batch', and 'activated_at', or 'code' and 'error'.
		 *
		 * @since 5.6.8
		 */
		public static function get_activation_entry_multisite( $result, string $batch_id = '' ): array {
			if ( true === $result ) {
				return array(
					'status'       => 'active',
					'batch'        => $batch_id,
					'activated_at' => time(),
				);
			}

			return array(
				'status' => 'failed',
				'batch'  => $batch_id,
				'code'   => \is_wp_error( $result ) ? $result->get_error_code() : '',
				'error'  => \is_wp_error( $result ) ? $result->get_error_message() : \__( 'Unknown error', 'wp-security-audit-log' ),
			);
		}

		/**
		 * Returns the sites whose last activation attempt got no answer from the store.
		 *
		 * The store may have activated these sites although its answer never arrived. Activating a site
		 * the store already counts uses no free activation, so they can be tried again at any time.
		 *
		 * @param array $activations - Activation entries keyed by site URL.
		 *
		 * @return string[] $site_urls - Site URLs.
		 *
		 * @since 5.6.8
		 */
		public static function get_unanswered_site_urls_multisite( array $activations ): array {
			$site_urls = array();

			foreach ( $activations as $site_url => $activation ) {
				if ( 'failed' === ( $activation['status'] ?? '' ) && in_array( $activation['code'] ?? '', self::UNANSWERED_ERROR_CODES, true ) ) {
					$site_urls[] = (string) $site_url;
				}
			}

			return $site_urls;
		}

		/**
		 * Returns the number of available activations from a store answer.
		 *
		 * @param array $license_data - License data returned by the store.
		 *
		 * @return int|false $activations - Available activations, PHP_INT_MAX for unlimited, or false when unknown.
		 *
		 * @since 5.6.8
		 */
		public static function get_available_activations( array $license_data ) {
			if ( isset( $license_data['activations_left'] ) ) {
				if ( 'unlimited' === $license_data['activations_left'] ) {
					return PHP_INT_MAX;
				}

				return (int) $license_data['activations_left'];
			}

			// A license limit of 0 means unlimited.
			if ( isset( $license_data['license_limit'] ) && 0 === (int) $license_data['license_limit'] ) {
				return PHP_INT_MAX;
			}

			return false;
		}

		/**
		 * Returns the message shown when a network has more sites than the license has activations available.
		 *
		 * @param string $plugin_name           - Plugin name shown to the user.
		 * @param int    $site_count            - Number of sites of the network.
		 * @param int    $available_activations - Activations the license has available.
		 *
		 * @return string $message - Plain text message, escaped where it is printed.
		 *
		 * @since 5.6.8
		 */
		public static function get_insufficient_activations_message_multisite( string $plugin_name, int $site_count, int $available_activations ): string {
			return sprintf(
				/* translators: 1: number of sites of the network, 2: plugin name, 3: available license activations */
				\_n( 'This network has %1$d sites, but your %2$s license only has %3$d activation available. Upgrade your license or remove sites from the network, then try again.', 'This network has %1$d sites, but your %2$s license only has %3$d activations available. Upgrade your license or remove sites from the network, then try again.', $available_activations, 'wp-security-audit-log' ),
				$site_count,
				$plugin_name,
				$available_activations
			);
		}

		/**
		 * Swaps the render and save callbacks of premium settings tabs, so they show the read-only notice and save nothing.
		 *
		 * @param array    $tabs          - Settings tabs keyed by tab ID, each with 'render' and 'save' callbacks.
		 * @param string[] $tab_ids       - IDs of the premium tabs.
		 * @param callable $render_notice - Callback that prints the read-only notice.
		 *
		 * @return array $tabs - Settings tabs, with the premium tabs marked 'is_frozen'.
		 *
		 * @since 5.6.8
		 */
		public static function make_tabs_read_only( array $tabs, array $tab_ids, callable $render_notice ): array {
			foreach ( $tab_ids as $tab_id ) {
				if ( ! isset( $tabs[ $tab_id ] ) ) {
					continue;
				}

				$tabs[ $tab_id ]['render']    = $render_notice;
				$tabs[ $tab_id ]['save']      = '__return_null';
				$tabs[ $tab_id ]['is_frozen'] = true;
			}

			return $tabs;
		}
	}
}
