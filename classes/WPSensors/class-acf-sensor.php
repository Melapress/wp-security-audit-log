<?php
/**
 * Sensor: ACF meta
 *
 * ACF Meta sensor class file.
 *
 * @since     5.0.0
 * @package   wsal
 * @subpackage sensors
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors;

use WSAL\Controllers\Alert_Manager;
use WSAL\WP_Sensors\Helpers\ACF_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\ACF_Sensor' ) ) {

	/**
	 * Sensor for events specific to Advanced Custom Fields plugin.
	 *
	 * @package    wsal
	 * @subpackage sensors
	 * @since      5.0.0
	 */
	class ACF_Sensor {

		/**
		 * Old post object.
		 *
		 * @var object
		 *
		 * @since 5.0.0
		 */
		private static $old_post;

		/**
		 * Array with the old post terms.
		 *
		 * @var array
		 *
		 * @since 5.0.0
		 */
		private static $old_post_terms;

		/**
		 * Inits the main hooks
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function init() {
			add_action( 'pre_post_update', array( __CLASS__, 'get_before_post_edit_data' ), 10, 2 );
			add_action( 'save_post', array( __CLASS__, 'acf_save_post' ), 10, 3 );
			add_action( 'delete_post', array( __CLASS__, 'event_post_deleted' ), 10, 1 );
			add_action( 'create_term', array( __CLASS__, 'event_category_creation' ), 10, 4 );
			add_filter( 'wp_update_term_data', array( __CLASS__, 'event_update_term_data' ), 10, 4 );
			add_action( 'pre_delete_term', array( __CLASS__, 'check_taxonomy_term_deletion' ), 10, 2 );
			add_action( 'set_object_terms', array( __CLASS__, 'post_terms_changed' ), 10, 4 );
		}

		/**
		 * Loads all the plugin dependencies early.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function early_init() {
			/**
			 * Add our filters.
			 */
			add_filter(
				'wsal_event_objects',
				array( '\WSAL\WP_Sensors\Helpers\ACF_Helper', 'add_custom_event_objects' )
			);
			if ( ACF_Helper::is_acf_active() ) {
				add_filter(
					'wsal_ignored_custom_post_types',
					array( '\WSAL\WP_Sensors\Helpers\ACF_Helper', 'add_custom_ignored_cpt' )
				);
			}
		}

		/**
		 * Record events for ACF changes.
		 *
		 * @param  int     $post_id - ID of post being saved.
		 * @param  WP_Post $post - Post object.
		 * @param  bool    $update - Is a post update or not.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function acf_save_post( $post_id, $post, $update ) {
			if ( 'acf-post-type' === $post->post_type || 'acf-taxonomy' === $post->post_type ) {
				$editor_link = array(
					'name'  => 'EditorLinkPost',
					'value' => get_edit_post_link( $post_id ),
				);

				if ( ! $update ) { // phpcs:ignore
					// If we wanted to try an event when an item is duplicated, now would be a good time.

				} else {
					$old_content_arr = (array) maybe_unserialize( self::$old_post->post_content );
					$new_content_arr = (array) maybe_unserialize( $post->post_content );

					if ( isset( self::$old_post->post_status ) && 'draft' !== self::$old_post->post_status && 'auto-draft' !== self::$old_post->post_status ) {
						if ( self::$old_post->post_status !== $post->post_status ) {
							if ( 'trash' === $post->post_status ) {
								$event      = ( 'acf-post-type' === $post->post_type ) ? 10007 : 10017;
								$event_data = array(
									'PostTypeTitle'      => $post->post_title,
									'PostID'             => $post->ID,
									'PostStatus'         => $post->post_status,
									'EditorLink' => $editor_link['value'],
								);
								Alert_Manager::trigger_event( $event, $event_data );
							} elseif ( 'trash' === self::$old_post->post_status && 'publish' === $post->post_status ) {
								$event      = ( 'acf-post-type' === $post->post_type ) ? 10008 : 10018;
								$event_data = array(
									'PostTypeTitle'      => $post->post_title,
									'PostID'             => $post->ID,
									'PostStatus'         => $post->post_status,
									'EditorLink'         => $editor_link['value'],
									'PostUrl'            => get_permalink( $post->ID ),
								);
								Alert_Manager::trigger_event( $event, $event_data );
							} else {
								$event      = ( 'acf-post-type' === $post->post_type ) ? 10001 : 10011;
								$event_data = array(
									'PostTypeTitle'      => $post->post_title,
									'PostID'             => $post->ID,
									'PostStatus'         => $post->post_status,
									'EventType'          => ( 'acf-disabled' === $post->post_status ) ? 'deactivated' : 'activated',
									'EditorLink' => $editor_link['value'],
								);
								Alert_Manager::trigger_event( $event, $event_data );
							}
						}
						if ( isset( self::$old_post->post_title ) ) {
							if ( self::$old_post->post_title !== $post->post_title ) {
								$event      = ( 'acf-post-type' === $post->post_type ) ? 10002 : 10012;
								$event_data = array(
									'OldPostTypeTitle'   => self::$old_post->post_title,
									'PostTypeTitle'      => $post->post_title,
									'PostID'             => $post->ID,
									'PostStatus'         => $post->post_status,
									'EditorLink' => $editor_link['value'],
								);
								Alert_Manager::trigger_event( $event, $event_data );
							}
						}
					} elseif ( ! Alert_Manager::was_triggered_recently( 10000 ) && ! Alert_Manager::was_triggered_recently( 10006 ) && ! Alert_Manager::was_triggered_recently( 10010 ) ) { 
							$event      = ( 'acf-post-type' === $post->post_type ) ? 10000 : 10010;
							$event_data = array(
								'PostTypeTitle'      => $post->post_title,
								'PostID'             => $post->ID,
								'PostStatus'         => 'published',
								'EditorLink' => $editor_link['value'],
							);
							Alert_Manager::trigger_event( $event, $event_data );
					}

					if ( isset( $old_content_arr['labels'] ) ) {
						if ( $old_content_arr['labels']['singular_name'] !== $new_content_arr['labels']['singular_name'] ) {
							$event      = ( 'acf-post-type' === $post->post_type ) ? 10003 : 10013;
							$event_data = array(
								'PostTypeTitle'      => $post->post_title,
								'PostID'             => $post->ID,
								'PostStatus'         => $post->post_status,
								'old_label'          => $old_content_arr['labels']['singular_name'],
								'new_label'          => $new_content_arr['labels']['singular_name'],
								'EditorLink' => $editor_link['value'],
							);
							Alert_Manager::trigger_event( $event, $event_data );
						}
					}

					if ( isset( $old_content_arr['post_type'] ) ) {
						if ( $old_content_arr['post_type'] !== $new_content_arr['post_type'] ) {
							$event      = 10004;
							$event_data = array(
								'PostTypeTitle'      => $post->post_title,
								'PostID'             => $post->ID,
								'PostStatus'         => $post->post_status,
								'old_key'            => $old_content_arr['post_type'],
								'new_key'            => $new_content_arr['post_type'],
								'EditorLink' => $editor_link['value'],
							);
							Alert_Manager::trigger_event( $event, $event_data );
						}
					}
					
					if ( isset( $old_content_arr['taxonomy'] ) ) {
						if ( $old_content_arr['taxonomy'] !== $new_content_arr['taxonomy'] ) {
							$event      = 10014;
							$event_data = array(
								'PostTypeTitle'      => $post->post_title,
								'PostID'             => $post->ID,
								'PostStatus'         => $post->post_status,
								'old_key'            => $old_content_arr[ 'taxonomy' ],
								'new_key'            => $new_content_arr[ 'taxonomy' ],
								'EditorLink' => $editor_link['value'],
							);
							Alert_Manager::trigger_event( $event, $event_data );
						}
					}
					if ( isset( $old_content_arr['taxonomies'] ) ) {
						if ( $old_content_arr['taxonomies'] !== $new_content_arr['taxonomies'] ) {
							$event      = 10015;
							$event_data = array(
								'PostTypeTitle'      => $post->post_title,
								'PostID'             => $post->ID,
								'PostStatus'         => $post->post_status,
								'old_tax'            => ( ! empty( $old_content_arr['taxonomies'] ) ) ? implode( ', ', $old_content_arr['taxonomies'] ) : esc_html__( 'Not provided', 'wp-security-audit-log' ),
								'new_tax'            => ( ! empty( $new_content_arr['taxonomies'] ) ) ? implode( ', ', $new_content_arr['taxonomies'] ) : esc_html__( 'Not provided', 'wp-security-audit-log' ),
								'EditorLink' => $editor_link['value'],
							);
							Alert_Manager::trigger_event( $event, $event_data );
						}
					}

					if ( isset( $old_content_arr['object_type'] ) ) {
						if ( $old_content_arr['object_type'] !== $new_content_arr['object_type'] ) {
							$event      = 10015;
							$event_data = array(
								'PostTypeTitle'      => $post->post_title,
								'PostID'             => $post->ID,
								'PostStatus'         => $post->post_status,
								'old_tax'            => ( ! empty( $old_content_arr['object_type'] ) ) ? implode( ', ', $old_content_arr['object_type'] ) : esc_html__( 'Not provided', 'wp-security-audit-log' ),
								'new_tax'            => ( ! empty( $new_content_arr['object_type'] ) ) ? implode( ', ', $new_content_arr['object_type'] ) : esc_html__( 'Not provided', 'wp-security-audit-log' ),
								'EditorLink' => $editor_link['value'],
							);
							Alert_Manager::trigger_event( $event, $event_data );
						}
					}

					
				}
			}
		}

		/**
		 * Post permanently deleted.
		 *
		 * @param integer $post_id - Post ID.
		 *
		 * @since 5.0.0
		 */
		public static function event_post_deleted( $post_id ) {
			// Exclude CPTs from external plugins.
			$post = get_post( $post_id );

			if ( 'acf-taxonomy' === $post->post_type || 'acf-post-type' === $post->post_type ) {
				$event      = ( 'acf-post-type' === $post->post_type ) ? 10009 : 10019;				
				$event_data = array(
					'PostTypeTitle' => $post->post_title,
					'PostID'        => $post->ID,
					'PostStatus'    => $post->post_status,
				);
				Alert_Manager::trigger_event( $event, $event_data );
			}
		}

		/**
		 * Create link to terms.
		 *
		 * @param int    $tag_id - ID if edited item.
		 * @param string $taxonomy - Taxonomy slug.
		 *
		 * @return string|null Returns link if it can, otherwise null.
		 *
		 * @since 5.0.0
		 */
		private static function get_taxonomy_edit_link( $tag_id, $taxonomy = 'post_tag' ) {
			$tag_args = array(
				'taxonomy' => $taxonomy,
				'tag_ID'   => $tag_id,
			);
			return ! empty( $tag_id ) ? add_query_arg( $tag_args, \network_admin_url( 'term.php' ) ) : null;
		}

		/**
		 * A new term was created.
		 *
		 * @param  int    $term_id - New term ID.
		 * @param  int    $tt_id - Parent taxonomy ID.
		 * @param  string $taxonomy - Taxonomy slug.
		 * @param  array  $args - Addition args.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function event_category_creation( $term_id, $tt_id, $taxonomy, $args ) {
			$is_acf_term = self::check_if_acf_term( $taxonomy );

			if ( ! empty( $is_acf_term ) ) {
				$link = self::get_taxonomy_edit_link( $term_id, $taxonomy );
				Alert_Manager::trigger_event(
					10020,
					array(
						'TaxonomyTerm' => $args['name'],
						'slug'         => $args['slug'],
						'EditorLink'   => $link,
					)
				);
			}
		}

		/**
		 * Triggered when term data is updated.
		 *
		 * @param array  $data     Term data to be updated.
		 * @param int    $term_id  Term ID.
		 * @param string $taxonomy Taxonomy slug.
		 * @param array  $args     Arguments passed to wp_update_term().
		 *
		 * @return array $data     We are only observing, return original data.
		 *
		 * @since 5.0.0
		 */
		public static function event_update_term_data( $data, $term_id, $taxonomy, $args ) {
			$new_name = isset( $data['name'] ) ? $data['name'] : false;
			$new_slug = isset( $data['slug'] ) ? $data['slug'] : false;

			$is_acf_term = self::check_if_acf_term( $taxonomy );

			// Get old data.
			$term     = get_term( $term_id, $taxonomy );
			$old_name = $term->name;
			$old_slug = $term->slug;

			if ( ! empty( $is_acf_term ) ) {
				$link = self::get_taxonomy_edit_link( $term_id, $taxonomy );
				if ( $old_name !== $new_name ) {
					Alert_Manager::trigger_event(
						10021,
						array(
							'old_name'   => $old_name,
							'new_name'   => $new_name,
							'EditorLink' => $link,
							'slug'       => $new_slug,
						)
					);
				}
				if ( $old_slug !== $new_slug ) {
					Alert_Manager::trigger_event(
						10023,
						array(
							'TaxonomyTerm' => $term->name,
							'old_slug'     => $old_slug,
							'new_slug'     => $new_slug,
							'EditorLink'   => $link,
						)
					);
				}
			}

			return $data; // Return data for the filter.
		}

		/**
		 * Taxonomy Terms Deleted Events.
		 *
		 * @param integer $term_id  - Term ID.
		 * @param string  $taxonomy - Taxonomy Name.
		 *
		 * @since 5.0.0
		 */
		public static function check_taxonomy_term_deletion( $term_id, $taxonomy ) {
			$is_acf_term = self::check_if_acf_term( $taxonomy );
			if ( ! empty( $is_acf_term ) ) {
				$term = get_term( $term_id, $taxonomy );
				Alert_Manager::trigger_event(
					10022,
					array(
						'TermName' => $term->name,
						'slug'     => $term->slug,
					)
				);
			}
		}

		/**
		 * Check if post terms changed via Gutenberg.
		 *
		 * @param int    $post_id  - Post ID.
		 * @param array  $terms    - Array of terms.
		 * @param array  $tt_ids   - Array of taxonomy term ids.
		 * @param string $taxonomy - Taxonomy slug.
		 *
		 * @since 5.0.0
		 */
		public static function post_terms_changed( $post_id, $terms, $tt_ids, $taxonomy ) {
			$post = get_post( $post_id );

			if ( is_wp_error( $post ) ) {
				return;
			}

			if ( null === $post ) {
				return;
			}

			if ( 'auto-draft' === $post->post_status ) {
				return;
			}

			// Support for Admin Columns Pro plugin and its add-on.
			if ( isset( $_POST['_ajax_nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_ajax_nonce'] ) ), 'ac-ajax' ) ) {
				return;
			}

			if ( isset( $_POST['action'] ) && 'acp_editing_single_request' === sanitize_text_field( wp_unslash( $_POST['action'] ) ) ) {
				return;
			}
			$is_acf_term = self::check_if_acf_term( $taxonomy );

			if ( ! empty( $is_acf_term ) ) {
				self::check_terms_change( self::$old_post_terms, self::get_post_terms( $post_id ), $post, $taxonomy );
			}
		}

		/**
		 * Check for changes to a posts terms.
		 *
		 * @param  array    $old_tags - Previous temrs list.
		 * @param  array    $new_tags - Incoming terms.
		 * @param  \WP_Post $post - Post.
		 * @param  string   $taxonomy - Taxonomy slug.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		private static function check_terms_change( $old_tags, $new_tags, $post, $taxonomy ) {
			$extracted_old_tags = array();
			$extracted_new_tags = array();
			if ( isset( $old_tags ) && \is_array( $old_tags ) && isset( $old_tags[ $taxonomy ] ) ) {
				$extracted_old_tags = $old_tags[ $taxonomy ];
			}
			if ( isset( $new_tags ) && \is_array( $new_tags ) && isset( $new_tags[ $taxonomy ] ) ) {
				$extracted_new_tags = $new_tags[ $taxonomy ];
			}

			$intersection = array_intersect_assoc( array_map( 'serialize', $extracted_old_tags ), array_map( 'serialize', $extracted_new_tags ) );
			if ( count( $intersection ) === count( $extracted_old_tags ) && count( $extracted_old_tags ) === count( $extracted_new_tags ) ) {
				// No change, let's leave.
				return;
			}

			self::report_terms_change_event( $extracted_old_tags, $extracted_new_tags, $post, $taxonomy );
		}

		/**
		 * Report changes to ACF terms.
		 *
		 * @param  array    $old_tags - Previous temrs list.
		 * @param  array    $new_tags - Incoming terms.
		 * @param  \WP_Post $post - Post.
		 * @param  string   $taxonomy - Taxonomy slug.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		private static function report_terms_change_event( $old_tags, $new_tags, $post, $taxonomy ) {
			if ( $old_tags === $new_tags ) {
				return;
			}

			$editor_link = array(
				'name'  => 'EditorLink',
				'value' => get_edit_post_link( $post->ID ),
			);

			Alert_Manager::trigger_event(
				10024,
				array(
					'PostID'             => $post->ID,
					'PostTitle'          => $post->post_title,
					'old_terms'          => ! empty( $old_tags ) ? implode( ', ', self::create_term_list( $old_tags ) ) : esc_html__( 'none', 'wp-security-audit-log' ),
					'new_terms'          => ! empty( $new_tags ) ? implode( ', ', self::create_term_list( $new_tags ) ) : esc_html__( 'none', 'wp-security-audit-log' ),
					'EditorLink' => $editor_link['value'],
				)
			);
		}

		/**
		 * Create neat list from term array.
		 *
		 * @param  array $terms - Incoming terms to tidy.
		 * @return array $return_arr - Neat list.
		 *
		 * @since 5.0.0
		 */
		private static function create_term_list( $terms ) {
			$return_arr = array();
			foreach ( $terms as $item ) {
				$return_arr[] = $item['name'];
			}
			return $return_arr;
		}

		/**
		 * Get a copy of a posts meta data prior to update for later comparison.
		 *
		 * @param int $post_id - Post ID.
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function get_before_post_edit_data( $post_id ) {
			$post_id = absint( $post_id ); // Making sure that the post id is integer.
			$post    = get_post( $post_id ); // Get post.

			// If post exists.
			if ( ! empty( $post ) && $post instanceof \WP_Post ) {
				$access_array         = array();
				self::$old_post       = $post;
				self::$old_post_terms = self::get_post_terms( $post_id );
			}
		}

		/**
		 * Check if a given taxomony is one that was created via ACF.
		 *
		 * @param string $taxonomy - Taxonomy slug to look for.
		 * @return bool|array $acf_tax_search - If found, return item, otherwise false.
		 *
		 * @since 5.0.0
		 */
		public static function check_if_acf_term( $taxonomy ) {
			global $wpdb;
			$acf_tax_search = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE post_content LIKE %s AND post_name LIKE 'taxonomy%'", '%' . $taxonomy . '%' ), ARRAY_A ); // phpcs:ignore
			return empty( $acf_tax_search ) ? false : $acf_tax_search;
		}

		/**
		 * Get ACF taxomonies for a post, if applicable
		 *
		 * @param  int $post_id - Post ID.
		 * @return array $found_terms - Applicable terms.
		 *
		 * @since 5.0.0
		 */
		private static function get_post_terms( $post_id ) {
			$post_taxonomies = get_post_taxonomies( $post_id );
			$found_terms     = array();
			foreach ( $post_taxonomies as $taxonomy ) {
				$check = self::check_if_acf_term( $taxonomy );
				if ( ! empty( $check ) ) {
					$possible_terms = wp_get_post_terms( $post_id, $taxonomy );
					if ( ! empty( $possible_terms ) ) {
						$found_terms[ $taxonomy ] = json_decode( wp_json_encode( $possible_terms ), true );
					}
				}
			}
			return $found_terms;
		}
	}
}
