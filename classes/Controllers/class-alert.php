<?php
/**
 * Controller: Alert Manager.
 *
 * Alert manager class file.
 *
 * @since     4.6
 *
 * @package   wsal
 * @subpackage controllers
 * @author Stoil Dobrev <sdobreff@gmail.com>
 */

declare(strict_types=1);

namespace WSAL\Controllers;

use WSAL\Helpers\Validator;
use WSAL\Helpers\Classes_Helper;
use WSAL\Helpers\Settings_Helper;
use WSAL\Controllers\Alert_Manager;
use WSAL\Helpers\Formatters\Alert_Formatter;
use WSAL\Helpers\Formatters\Formatter_Factory;
use WSAL\WP_Sensors\WP_Content_Sensor;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Controllers\Alert' ) ) {
	/**
	 * Provides logging functionality for the comments.
	 *
	 * @since 4.6.0
	 */
	class Alert {

		/**
		 * Holds array with all the deactivated alerts.
		 * When monitored plugin is deactivated, all alerts are removed from the alerts array. But here we hold all of these deactivated alerts, so we can still show proper message generated from the plugin.
		 *
		 * @var array
		 *
		 * @since 4.6.0
		 */
		private static $deactivated_alerts = null;

		/**
		 * Returns the alert array by given alert_id
		 *
		 * @param integer $alert_id - The alert_id to get the alert data for.
		 *
		 * @return array|bool
		 *
		 * @since 4.6.0
		 */
		public static function get_alert( $alert_id = 0 ) {
			if ( isset( Alert_Manager::get_alerts()[ $alert_id ] ) ) {

				return Alert_Manager::get_alerts()[ $alert_id ];
			}

			// Lets check deactivated as well.
			if ( isset( self::get_deactivated_alerts_array()[ $alert_id ] ) ) {

				return self::get_deactivated_alerts_array()[ $alert_id ];
			}

			return false;
		}

		/**
		 * Returns the alert message
		 *
		 * @param integer $alert_id - The alert_id to retrieve the message for.
		 *
		 * @return string
		 *
		 * @since 4.6.0
		 */
		public static function get_original_alert_message( $alert_id = 0 ): string {
			if ( isset( Alert_Manager::get_alerts()[ $alert_id ] ) ) {

				return Alert_Manager::get_alerts()[ $alert_id ]['message'];
			}

			if ( isset( self::get_deactivated_alerts_array()[ $alert_id ] ) ) {

				return self::get_deactivated_alerts_array()[ $alert_id ][3];
			}

			return esc_html__( 'Alert message not found.', 'wp-security-audit-log' );
		}

		/**
		 * Collects and returns all of the deactivated alerts (coming from deactivated plugins)
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		public static function get_deactivated_alerts_array(): array {
			if ( null === self::$deactivated_alerts ) {
				// Nothing in the registered alerts - lets try ones that are activated only if the representative plugin is installed and activated.
				$unchecked_alerts = array();
				$custom_alerts    = Classes_Helper::get_classes_by_namespace( '\WSAL\WP_Sensors\Alerts' );
				foreach ( $custom_alerts as $alerts ) {
					if ( method_exists( $alerts, 'get_alerts_array' ) ) {
						$unchecked_alerts += call_user_func_array( array( $alerts, 'get_alerts_array' ), array() );
					}
				}
				self::$deactivated_alerts = $unchecked_alerts;
			}
			return self::$deactivated_alerts;
		}

		/**
		 * Formats the alert message
		 *
		 * @param array       $meta_data - Array of meta data.
		 * @param string|null $message -  The message to be formatted.
		 * @param integer     $alert_id - The alert id to get message for.
		 * @param integer     $occurrence_id - The occurrence id to get message for.
		 * @param string      $context - In which context the message should be formatted.
		 *
		 * @return string|bool
		 *
		 * @since 4.6.0
		 */
		public static function get_message( $meta_data, $message = null, $alert_id = 0, $occurrence_id = 0, $context = 'default' ) {
			$active_alert = isset( Alert_Manager::get_alerts()[ $alert_id ] );
			if ( $active_alert || isset( self::get_deactivated_alerts_array()[ $alert_id ] ) ) {
				if ( $active_alert ) {
					$alert = Alert_Manager::get_alerts()[ $alert_id ];
				} else {
					$alert               = self::get_deactivated_alerts_array()[ $alert_id ];
					$alert['code']       = $alert[0];
					$alert['severity']   = $alert[1];
					$alert['desc']       = $alert[3];
					$alert['message']    = $alert[3];
					$alert['metadata']   = $alert[4];
					$alert['object']     = $alert[6];
					$alert['event_type'] = $alert[7];
				}

				$message = is_null( $message ) ? $alert['message'] : $message;

				if ( ! $context ) {
					$context = 'default';
				}
				// Get the alert formatter for given context.
				$configuration = Formatter_Factory::get_configuration( $context );

				// Tokenize message with regex.
				$message_parts = preg_split( '/(%.*?%)/', (string) $message, - 1, PREG_SPLIT_DELIM_CAPTURE );
				if ( ! is_array( $message_parts ) ) {
					// Use the message as is.
					$result = (string) $message;
				} elseif ( ! empty( $message_parts ) ) {
					// Handle tokenized message.
					foreach ( $message_parts as $i => $token ) {
						if ( strlen( $token ) === 0 ) {
							continue;
						}
						// Handle escaped percent sign.
						if ( '%%' === $token ) {
							$message_parts[ $i ] = '%';
						} elseif ( substr( $token, 0, 1 ) === '%' && substr( $token, - 1, 1 ) === '%' ) {
							// Handle complex expressions.
							$message_parts[ $i ] = self::get_meta_expression_value( substr( $token, 1, - 1 ), $meta_data );
							$message_parts[ $i ] = Alert_Formatter::format_meta_expression(
								$token,
								$message_parts[ $i ],
								$configuration,
								$occurrence_id,
								$meta_data
							);
						}
					}

					// Compact message.
					$result = implode( '', $message_parts );
				}

				// Process message to make sure it any HTML tags are handled correctly.
				$result = Alert_Formatter::process_html_tags_in_message( $result, $configuration );

				$end_of_line = $configuration['end_of_line'];

				// Process metadata and links introduced as part of alert definition in version 4.2.1.
				if ( $configuration['supports_metadata'] ) {
					$metadata_result = self::get_formatted_metadata( $configuration, $meta_data, $occurrence_id, $alert );
					if ( ! empty( $metadata_result ) ) {
						if ( ! empty( $result ) ) {
							$result .= $end_of_line;
						}
						$result .= $metadata_result;
					}
				}

				if ( $configuration['supports_hyperlinks'] ) {
					$hyperlinks_result = self::get_formatted_hyperlinks( $configuration, $meta_data, $occurrence_id, $alert );
					if ( ! empty( $hyperlinks_result ) ) {
						if ( ! empty( $result ) ) {
							$result .= $end_of_line;
						}
						$result .= $hyperlinks_result;
					}
				}

				return $result;
			}

			return false;
		}

		/**
		 * Retrieves a value for a particular meta variable expression.
		 *
		 * @param string $expr Expression, eg: User->Name looks for a Name property for meta named User.
		 * @param array  $meta_data (Optional) Meta data relevant to expression.
		 *
		 * @return mixed The value nearest to the expression.
		 *
		 * @since 4.6.2
		 */
		protected static function get_meta_expression_value( $expr, $meta_data = array() ) {
			$expr = preg_replace( '/%/', '', $expr );
			if ( 'IPAddress' === $expr ) {
				if ( array_key_exists( 'IPAddress', $meta_data ) ) {
					if ( is_array( $meta_data['IPAddress'] ) ) {
						return implode( ', ', $meta_data['IPAddress'] );
					} else {
						return implode( ', ', array( $meta_data['IPAddress'] ) );
					}
				}

				return null;
			}

			// TODO: Handle function calls (and methods?).
			$expr = explode( '->', $expr );
			$meta = array_shift( $expr );
			$meta = isset( $meta_data[ $meta ] ) ? $meta_data[ $meta ] : null;
			foreach ( $expr as $part ) {
				if ( is_scalar( $meta ) || is_null( $meta ) ) {
					return $meta; // This isn't 100% correct.
				}
				$meta = is_array( $meta ) && array_key_exists( $part, $meta ) ? $meta[ $part ] : ( isset( $meta->$part ) ? $meta->$part : 'NULL' );
			}

			return is_scalar( $meta ) ? (string) $meta : var_export( $meta, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		}

		/**
		 * Retrieves formatted meta data item (label and data).
		 *
		 * @param array $configuration  - Alert message configuration rules.
		 * @param array $meta_data Meta data.
		 * @param int   $occurrence_id Occurrence ID.
		 * @param array $alert - The array with all the alert details.
		 *
		 * @return string
		 * @since 4.2.1
		 */
		public static function get_formatted_metadata( $configuration, $meta_data, $occurrence_id, $alert ) {
			$result            = '';
			$metadata_as_array = self::get_metadata_as_array( $configuration, $meta_data, $occurrence_id, $alert );
			if ( ! empty( $metadata_as_array ) ) {

				$meta_result_parts = array();
				foreach ( $metadata_as_array as $meta_label => $meta_expression ) {
					if ( ! empty( $meta_expression ) ) {
						array_push( $meta_result_parts, $meta_label . ': ' . $meta_expression );
					}
				}

				if ( ! empty( $meta_result_parts ) ) {
					$result .= implode( $configuration['end_of_line'], $meta_result_parts );
				}
			}
			return $result;
		}

		/**
		 * Retrieves metadata as an associative array.
		 *
		 * @param array $configuration  - Alert message configuration rules.
		 * @param array $meta_data Meta data.
		 * @param int   $occurrence_id Occurrence ID.
		 * @param array $alert - The array with all the alert details.
		 *
		 * @return array
		 * @since 4.2.1
		 */
		public static function get_metadata_as_array( $configuration, $meta_data, $occurrence_id, $alert ) {
			$result = array();
			if ( ! empty( $alert['metadata'] ) ) {
				foreach ( $alert['metadata'] as $meta_label => $meta_token ) {
					if ( strlen( $meta_token ) === 0 ) {
						continue;
					}

					// Pure alert meta lookup based on meta token.
					$meta_expression = self::get_meta_expression_value( $meta_token, $meta_data );

					// Additional alert meta processing - handles derived or decorated alert data.
					$meta_expression = Alert_Formatter::format_meta_expression( $meta_token, $meta_expression, $configuration, $occurrence_id );

					if ( ! empty( $meta_expression ) ) {
						$result[ $meta_label ] = $meta_expression;
					}
				}
			}

			return $result;
		}

		/**
		 * Get formatter hyperlinks.
		 *
		 * @param array $configuration  - Alert message configuration rules.
		 * @param array $meta_data     Meta data.
		 * @param int   $occurrence_id Occurrence ID.
		 * @param array $alert - The array with all the alert details.
		 *
		 * @return string
		 * @since 4.2.1
		 *
		 * @since 5.5.0 - Added target value to the Alert_Formatter::format_link call.
		 */
		public static function get_formatted_hyperlinks( $configuration, $meta_data, $occurrence_id, $alert ) {
			$result              = '';
			$hyperlinks_as_array = self::get_hyperlinks_as_array( $configuration, $meta_data, $occurrence_id, $alert );
			if ( ! empty( $hyperlinks_as_array ) ) {
				$links_result_parts = array();
				foreach ( $hyperlinks_as_array as  $link_data ) {
					$link_label       = $link_data['label'];
					$link_url         = $link_data['url'];
					$needs_formatting = $link_data['needs_formatting'];
					$formatted_link   = $needs_formatting ? Alert_Formatter::format_link( $configuration, $link_url, $link_label, '', '_blank' ) : $link_url;
					array_push( $links_result_parts, $formatted_link );
				}

				if ( ! empty( $links_result_parts ) ) {
					$result .= implode( $configuration['end_of_line'], $links_result_parts );
				}
			}

			if ( isset( $alert['links'] ) && ! empty( $alert['links'] ) && \is_array( $alert['links'] ) && \in_array( '%PostUrl%', $alert['links'] ) && Settings_Helper::get_url_parameters() ) {
				if ( isset( $meta_data['PostUrl'] ) ) {
					$return = $meta_data['PostUrl'];

					$return = htmlspecialchars_decode( $return, ENT_QUOTES );

					$processed_url = \wp_parse_url( $return );

					$result = \str_replace( array( 'http://%PostUrl%', 'https://%PostUrl%' ), $meta_data['PostUrl'], $result );

					if ( $processed_url && isset( $processed_url['query'] ) ) {
						$params = array();
						parse_str( $processed_url['query'], $params );

						if ( ! empty( $params ) ) {

							$return_temp = esc_html__( 'Query params:', 'wp-security-audit-log' );
							$return      = '';
							foreach ( $params as $key => $value ) {
								$return .= $key . '=' . $value . ', ';
							}
							$return = rtrim( $return, ', ' );
							$return = $return_temp . ' ' . $configuration['highlight_start_tag'] . $return . $configuration['highlight_end_tag'] . $configuration['end_of_line'];

							$result = $return . \str_replace( array( 'http://%PostUrl%', 'https://%PostUrl%' ), $meta_data['PostUrl'], $result );
						}
					}
				}
			}

			return $result;
		}

		/**
		 * Retrieves hyperlinks as an array.
		 *
		 * @param array $configuration  - Alert message configuration rules.
		 * @param array $meta_data                            Meta data.
		 * @param int   $occurrence_id                        Occurrence ID.
		 * @param array $alert - The array with all the alert details.
		 * @param bool  $exclude_links_not_needing_formatting If true, links that don't need formatting will
		 *                                                    be excluded. For example special links that
		 *                                                    contain onclick attribute already from the meta
		 *                                                    formatter.
		 *
		 * @return array
		 * @since 4.2.1
		 */
		public static function get_hyperlinks_as_array( $configuration, $meta_data, $occurrence_id, $alert, $exclude_links_not_needing_formatting = false ) {
			$result = array();
			if ( ! empty( $alert['links'] ) ) {
				foreach ( $alert['links'] as $link_label => $link_data ) {

					$link_title = '';
					$link_url   = '';
					if ( is_string( $link_data ) ) {
						if ( strlen( $link_data ) === 0 ) {
							continue;
						}

						if ( '%RevisionLink%' === $link_data && 2065 === (int) $alert['code'] ) {

							// Check $revision_link to avoid PHP warnings.
							$revision_link = $meta_data['RevisionLink'] ?? '';

							$link_url   = self::get_post_revision_link( $meta_data['PostID'], $revision_link );
							$link_title = self::get_revision_link_title();
							$link_label = self::get_revision_link_title();
						} else {
							$link_url   = $link_data;
							$link_title = $link_data;
						}
					} else {
						$link_url   = $link_data['url'];
						$link_title = $link_data['label'];
					}

					/**
					 * Link url can be:
					 * - an actual URL
					 * - placeholder for an existing metadata field that contains a URL (or the full HTML A tag markup)
					 * -- before 4.2.1 the CommentLink meta would contain the full HTML markup for the link, now it contains only the URL
					 * - other placeholder for a dynamic or JS infused link that will be processed by the meta formatter.
					 */
					$needs_formatting = true;
					if ( ! Validator::is_valid_url( $link_url ) ) {

						$meta_expression = self::get_meta_expression_value( $link_url, $meta_data );
						$meta_expression = Alert_Formatter::format_meta_expression( $link_url, $meta_expression, $configuration, $occurrence_id, $meta_data, false );
						if ( ! empty( $meta_expression ) ) {
							if ( Validator::is_valid_url( $meta_expression ) ) {

								$link_url = $meta_expression;
							} elseif ( preg_match( '/onclick=/', $meta_expression ) ) {
								$link_url         = $meta_expression;
								$needs_formatting = false;
							} else {

								preg_match( '/href=["\']https?:\/\/([^"\']+)["\']/', $meta_expression, $url_matches );
								if ( count( $url_matches ) === 2 ) {
									$link_url = $url_matches[1];
								}
							}
						} else {
							$link_url = '';
						}
					}

					if ( $exclude_links_not_needing_formatting && ! $needs_formatting ) {
						continue;
					}

					if ( ! empty( $link_url ) ) {
						$result[ $link_label ] = array(
							'url'              => $link_url,
							'needs_formatting' => $needs_formatting,
							'title'            => $link_title,
							'label'            => $link_label,
						);
					}
				}

				unset( $link_label );
			}

			return $result;
		}

		private static function get_revision_link_title(): string {
			if ( \defined( 'WP_POST_REVISIONS' ) && ! WP_POST_REVISIONS ) {
				return esc_html__( 'Revisions are not enabled. Enable revisions to view the content changes. Read more.', 'wp-security-audit-log' );
			} else {
				return esc_html__( 'View the content changes', 'wp-security-audit-log' );
			}
		}

		/**
		 * Builds the Post revision link
		 *
		 * @param int    $post_id - The Post ID to get the link for.
		 * @param string $url - The Post URL.
		 *
		 * @return string
		 *
		 * @since 5.4.0
		 */
		private static function get_post_revision_link( $post_id, $url ): string {
			if ( \defined( 'WP_POST_REVISIONS' ) && ! WP_POST_REVISIONS ) {
				return 'https://melapress.com/wordpress-revisions-posts-pages/#utm_source=plugin&utm_medium=link&utm_campaign=wsal';
			} elseif ( '' !== $url ) {
				return (string) WP_Content_Sensor::get_post_revision( $post_id );
			} else {
				return '';
			}
		}
	}
}
