<?php
/**
 * Atarim addon.
 *
 * @package    wsal
 * @subpackage atarim
 * @copyright  2026 Melapress
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3 or higher
 * @link       https://wordpress.org/plugins/wp-security-audit-log/
 *
 * @since 5.7.0
 */

namespace WSAL\Atarim;

use WSAL\Helpers\WP_Helper;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

if ( ! class_exists( '\WSAL\Atarim\Atarim_Addon' ) ) {

	/**
	 * Attributes the events triggered by the Atarim AI agent to the agent.
	 *
	 * @package WSAL\Atarim
	 *
	 * @since 5.7.0
	 */
	class Atarim_Addon {

		/**
		 * Name of the Atarim AI agent, stored in the AIAgent metadata of the events it triggers.
		 *
		 * @var string
		 *
		 * @since 5.7.0
		 */
		public const AGENT_NAME = 'Atarim AI';

		/**
		 * Start of the REST routes Atarim's servers send their requests to.
		 *
		 * @var string
		 *
		 * @since 5.7.0
		 */
		private const ATARIM_REST_ROUTE_PREFIX = '/atarim/';

		/**
		 * Option where Atarim stores the token its servers send in the X-Atarim-Token header.
		 *
		 * @var string
		 *
		 * @since 5.7.0
		 */
		private const ATARIM_TOKEN_OPTION = 'avc_atarim_secret_token';

		/**
		 * Atarim plugin basename.
		 *
		 * @var string
		 *
		 * @since 5.7.0
		 */
		private const ATARIM_PLUGIN_BASENAME = 'atarim-visual-collaboration/atarim-visual-collaboration.php';

		/**
		 * Whether the current request comes from the Atarim AI agent.
		 *
		 * @var bool
		 *
		 * @since 5.7.0
		 */
		private static $is_agent_request = false;

		/**
		 * Inits the class and sets the hooks.
		 *
		 * @return void
		 *
		 * @since 5.7.0
		 */
		public static function init() {
			// Apply the agent attribution after MainWP has prepared the child site's user output.
			\add_filter( 'wsal_get_user_image', array( __CLASS__, 'get_user_image' ), 20, 2 );
			\add_filter( 'wsal_get_user_html', array( __CLASS__, 'get_user_html' ), 20, 2 );

			if ( ! WP_Helper::is_plugin_active( self::ATARIM_PLUGIN_BASENAME ) ) {
				return;
			}

			\add_filter( 'rest_dispatch_request', array( __CLASS__, 'detect_agent_request' ), 10, 3 );
			\add_filter( 'wsal_event_data_before_log', array( __CLASS__, 'add_agent_to_event_data' ) );
		}

		/**
		 * Flags the current request as an Atarim AI agent request.
		 *
		 * @param mixed            $dispatch_result - Dispatch result, returned unchanged.
		 * @param \WP_REST_Request $request - Current REST request.
		 * @param string           $route - Route matched for the request.
		 *
		 * @return mixed $dispatch_result - Unchanged dispatch result.
		 *
		 * @since 5.7.0
		 */
		public static function detect_agent_request( $dispatch_result, $request, $route ) {
			if ( 0 !== strpos( $route, self::ATARIM_REST_ROUTE_PREFIX ) ) {
				return $dispatch_result;
			}

			$request_token = \sanitize_text_field( (string) $request->get_header( 'x_atarim_token' ) );

			if ( '' === $request_token ) {
				return $dispatch_result;
			}

			$atarim_token = (string) \get_option( self::ATARIM_TOKEN_OPTION, '' );

			// Some Atarim routes also accept logged-in users, so only a valid token proves the request comes from Atarim.
			if ( '' === $atarim_token || ! hash_equals( $atarim_token, $request_token ) ) {
				return $dispatch_result;
			}

			self::$is_agent_request = true;

			return $dispatch_result;
		}

		/**
		 * Adds the Atarim AI agent to the metadata of events triggered by its requests.
		 *
		 * @param array $event_data - Event data.
		 *
		 * @return array $event_data - Event data.
		 *
		 * @since 5.7.0
		 */
		public static function add_agent_to_event_data( $event_data ) {
			if ( self::$is_agent_request ) {
				$event_data['AIAgent'] = self::AGENT_NAME;
			}

			return $event_data;
		}

		/**
		 * Checks whether the event was triggered by the Atarim AI agent.
		 *
		 * @param array $item - Event data from the event log.
		 *
		 * @return bool - Whether the event is attributed to the Atarim AI agent.
		 *
		 * @since 5.7.0
		 */
		private static function is_agent_event( $item ): bool {
			// Fall back to the metadata written by Atarim's own integration.
			return self::AGENT_NAME === ( $item['meta_values']['AIAgent'] ?? $item['meta_values']['Agent'] ?? '' );
		}

		/**
		 * Shows the Atarim icon as the avatar of events triggered by the Atarim AI agent.
		 *
		 * @param string $image - Avatar HTML.
		 * @param array  $item - Event data from the event log.
		 *
		 * @return string $image - Avatar HTML.
		 *
		 * @since 5.7.0
		 */
		public static function get_user_image( $image, $item ) {
			if ( ! self::is_agent_event( $item ) ) {
				return $image;
			}

			$icon_url = WSAL_BASE_URL . 'img/icons/atarim-ai.png';

			return '<img src="' . \esc_url( $icon_url ) . '" alt="" class="avatar avatar-32" width="32" height="32" />';
		}

		/**
		 * Shows events triggered by the Atarim AI agent as "Atarim AI", with the WordPress user it acted as.
		 *
		 * @param string $user_html - User HTML.
		 * @param array  $item - Event data from the event log.
		 *
		 * @return string $user_html - User HTML.
		 *
		 * @since 5.7.0
		 */
		public static function get_user_html( $user_html, $item ) {
			if ( ! self::is_agent_event( $item ) ) {
				return $user_html;
			}

			// Atarim's servers send some requests without a WordPress user, in that case just use AGENT_NAME property.
			if ( 0 === (int) ( $item['user_id'] ?? 0 ) ) {
				return \esc_html( self::AGENT_NAME );
			}

			return sprintf(
				'%1$s<br />%2$s',
				\esc_html( self::AGENT_NAME ),
				sprintf(
					/* translators: %s: WordPress user the AI agent acted as. */
					\esc_html__( '(via %s)', 'wp-security-audit-log' ),
					$user_html
				)
			);
		}
	}
}
