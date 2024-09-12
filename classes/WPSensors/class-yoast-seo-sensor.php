<?php
/**
 * Sensor: Yoast SEO
 *
 * Yoast SEO sensor file.
 *
 * @package Wsal
 * @since 3.2.0
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors;

use WSAL\Controllers\Alert_Manager;
use WSAL\WP_Sensors\Helpers\Yoast_SEO_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Support for Yoast SEO Plugin.
 *
 * @package Wsal
 * @subpackage Sensors
 */
if ( ! class_exists( '\WSAL\WP_Sensors\Yoast_SEO_Sensor' ) ) {

	/**
	 * The main sensor class.
	 */
	class Yoast_SEO_Sensor {

		/**
		 * Post ID.
		 *
		 * @var int
		 */
		private static $post_id = 0;

		/**
		 * Post Object.
		 *
		 * @var WP_Post
		 */
		private static $post;

		/**
		 * Possible scheme types and labels.
		 *
		 * @var array
		 */
		private static $schema_labels = array(
			'Article'                  => 'Article',
			'BlogPosting'              => 'Blog Post',
			'SocialMediaPosting'       => 'Social Media Posting',
			'NewsArticle'              => 'News Article',
			'AdvertiserContentArticle' => 'Advertiser Content Article',
			'SatiricalArticle'         => 'Satirical Article',
			'ScholarlyArticle'         => 'Scholarly Article',
			'TechArticle'              => 'Tech Article',
			'Report'                   => 'Report',
			'WebPage'                  => 'Web Page',
			'ItemPage'                 => 'Item Page',
			'AboutPage'                => 'About Page',
			'FAQPage'                  => 'FAQ Page',
			'QAPage'                   => 'QA Page',
			'ProfilePage'              => 'Profile Page',
			'ContactPage'              => 'Contact Page',
			'MedicalWebPage'           => 'Medical Web Page',
			'CollectionPage'           => 'Collection Page',
			'CheckoutPage'             => 'Checkout Page',
			'RealEstateListing'        => 'Real Estate Listing',
			'SearchResultsPage'        => 'Search Results Page',
			'_yoast_wpseo_bctitle'     => '',
		);

		/**
		 * Listening to events using hooks.
		 */
		public static function init() {
			if ( Yoast_SEO_Helper::is_wpseo_active() ) {
				// If user can edit post then hook this function.
				if ( current_user_can( 'edit_posts' ) ) {
					add_action( 'admin_init', array( __CLASS__, 'event_admin_init' ) );
				}

				// Yoast SEO option alerts.
				add_action( 'updated_option', array( __CLASS__, 'yoast_options_trigger' ), 10, 3 );

				// Yoast SEO Site option alerts.
				add_action( 'update_site_option', array( __CLASS__, 'yoast_site_options_trigger' ), 10, 3 );
			}
		}

		/**
		 * Loads all the plugin dependencies early.
		 *
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function early_init() {
			add_filter(
				'wsal_event_objects',
				array( '\WSAL\WP_Sensors\Helpers\Yoast_SEO_Helper', 'wsal_yoast_seo_extension_add_custom_event_objects' )
			);

			if ( Yoast_SEO_Helper::is_wpseo_active() ) {
				// Yoast SEO blog option default change alerts.
				add_action(
					'add_option_wpseo',
					array( __CLASS__, 'yoast_blog_options_trigger' ),
					10,
					2
				);

				add_filter(
					'wsal_togglealerts_sub_category_events',
					array( '\WSAL\WP_Sensors\Helpers\Yoast_SEO_Helper', 'wsal_yoast_seo_extension_togglealerts_sub_category_events' )
				);

				add_filter(
					'wsal_togglealerts_sub_category_titles',
					array( '\WSAL\WP_Sensors\Helpers\Yoast_SEO_Helper', 'wsal_yoast_seo_extension_togglealerts_sub_category_titles' ),
					10,
					2
				);

				add_filter(
					'wsal_togglealerts_obsolete_events',
					array( '\WSAL\WP_Sensors\Helpers\Yoast_SEO_Helper', 'wsal_yoast_seo_extension_togglealerts_obsolete_events' )
				);
			}
		}

		/**
		 * Method: Admin Init Event.
		 */
		public static function event_admin_init() {
			// Check for settings change.
			self::check_seo_data_change();
		}

		/**
		 * Method: Get Post SEO Data.
		 *
		 * @param string $key – Meta Key.
		 * @return mixed
		 */
		private static function get_post_seo_data( $key = '' ) {
			// If empty key then return false.
			if ( empty( $key ) ) {
				return false;
			}

			return \WPSEO_Meta::get_value( $key, self::$post_id );
		}

		/**
		 * Get editor link.
		 *
		 * @param stdClass $post_id - Post id.
		 * @return array $editor_link - Name and value link.
		 */
		private static function get_editor_link( $post_id ) {
			$value       = get_edit_post_link( $post_id );
			$editor_link = array(
				'name'  => 'EditorLinkPost',
				'value' => $value,
			);
			return $editor_link;
		}

		/**
		 * Method: Detect Post SEO Data Change.
		 */
		private static function check_seo_data_change() {
			// Set filter input args.
			$filter_input_args = array(
				'post_ID'                          => FILTER_VALIDATE_INT,
				'yoast_wpseo_is_cornerstone'       => FILTER_VALIDATE_BOOLEAN,
				'yoast_wpseo_meta-robots-noindex'  => FILTER_VALIDATE_INT,
				'yoast_wpseo_meta-robots-nofollow' => FILTER_VALIDATE_INT,
				'yoast_wpseo_canonical'            => FILTER_VALIDATE_URL,
			);

			// Filter POST global array.
			$post_array = filter_input_array( INPUT_POST, $filter_input_args );

			if ( isset( $_POST['_wpnonce'] ) ) {
				$post_array['_wpnonce'] = \sanitize_text_field( \wp_unslash( $_POST['_wpnonce'] ) );
			}

			if ( isset( $_POST['action'] ) ) {
				$post_array['action'] = \sanitize_text_field( \wp_unslash( $_POST['action'] ) );
			}

			if ( isset( $_POST['yoast_wpseo_title'] ) ) {
				$post_array['yoast_wpseo_title'] = \sanitize_text_field( \wp_unslash( $_POST['yoast_wpseo_title'] ) );
			}

			if ( isset( $_POST['yoast_wpseo_metadesc'] ) ) {
				$post_array['yoast_wpseo_metadesc'] = \sanitize_text_field( \wp_unslash( $_POST['yoast_wpseo_metadesc'] ) );
			}

			if ( isset( $_POST['yoast_wpseo_focuskw'] ) ) {
				$post_array['yoast_wpseo_focuskw'] = \sanitize_text_field( \wp_unslash( $_POST['yoast_wpseo_focuskw'] ) );
			}

			if ( isset( $_POST['yoast_wpseo_meta-robots-adv'] ) ) {
				$post_array['yoast_wpseo_meta-robots-adv'] = \sanitize_text_field( \wp_unslash( $_POST['yoast_wpseo_meta-robots-adv'] ) );
			}

			if ( isset( $_POST['yoast_wpseo_schema_page_type'] ) ) {
				$post_array['yoast_wpseo_schema_page_type'] = \sanitize_text_field( \wp_unslash( $_POST['yoast_wpseo_schema_page_type'] ) );
			}

			if ( isset( $_POST['yoast_wpseo_schema_page_type'] ) ) {
				$post_array['yoast_wpseo_schema_page_type'] = \sanitize_text_field( \wp_unslash( $_POST['yoast_wpseo_schema_page_type'] ) );
			}

			if ( isset( $_POST['yoast_wpseo_schema_article_type'] ) ) {
				$post_array['yoast_wpseo_schema_article_type'] = \sanitize_text_field( \wp_unslash( $_POST['yoast_wpseo_schema_article_type'] ) );
			}

			if ( isset( $_POST['yoast_wpseo_bctitle'] ) ) {
				$post_array['yoast_wpseo_bctitle'] = \sanitize_text_field( \wp_unslash( $_POST['yoast_wpseo_bctitle'] ) );
			}

			if ( isset( $post_array['post_ID'] )
			&& 'editpost' === $post_array['action']
			&& isset( $post_array['_wpnonce'] )
			&& wp_verify_nonce( $post_array['_wpnonce'], 'update-post_' . $post_array['post_ID'] ) ) {
				self::$post_id = intval( $post_array['post_ID'] );
				self::$post    = get_post( self::$post_id );

				// Check SEO data changes and alert if changed.
				if ( isset( $post_array['yoast_wpseo_title'] ) ) {
					self::check_title_change( $post_array['yoast_wpseo_title'] ); // Title.
				}
				if ( isset( $post_array['yoast_wpseo_metadesc'] ) ) {
					self::check_desc_change( $post_array['yoast_wpseo_metadesc'] ); // Meta description.
				}
				if ( isset( $post_array['yoast_wpseo_meta-robots-noindex'] ) ) {
					self::check_robots_index_change( $post_array['yoast_wpseo_meta-robots-noindex'] ); // Meta Robots Index.
				}
				if ( isset( $post_array['yoast_wpseo_meta-robots-nofollow'] ) ) {
					self::check_robots_follow_change( $post_array['yoast_wpseo_meta-robots-nofollow'] ); // Meta Robots Follow.
				}
				if ( isset( $post_array['yoast_wpseo_meta-robots-adv'] ) ) {
					self::check_robots_advanced_change( $post_array['yoast_wpseo_meta-robots-adv'] ); // Meta Robots Advanced.
				}
				if ( isset( $post_array['yoast_wpseo_canonical'] ) ) {
					self::check_canonical_url_change( $post_array['yoast_wpseo_canonical'] ); // Canonical URL.
				}
				if ( isset( $post_array['yoast_wpseo_focuskw'] ) ) {
					self::check_focus_keys_change( $post_array['yoast_wpseo_focuskw'] ); // Focus keywords.
				}
				if ( isset( $post_array['yoast_wpseo_is_cornerstone'] ) ) {
					self::check_cornerstone_change( $post_array['yoast_wpseo_is_cornerstone'] ); // Cornerstone.
				}
				if ( isset( $post_array['yoast_wpseo_schema_page_type'] ) ) {
					self::check_schema_change( $post_array['yoast_wpseo_schema_page_type'], 'page_type' );
				}
				if ( isset( $post_array['yoast_wpseo_schema_article_type'] ) ) {
					self::check_schema_change( $post_array['yoast_wpseo_schema_article_type'], 'article_type' );
				}
				if ( isset( $post_array['yoast_wpseo_bctitle'] ) ) {
					self::check_breadcrumb_change( $post_array['yoast_wpseo_bctitle'] );
				}
			}
		}

		/**
		 * Method: Check SEO Title Change.
		 *
		 * @param string $title – Changed SEO Title.
		 */
		private static function check_title_change( $title ) {
			// Get old title value.

			$old_title = (string) self::get_post_seo_data( 'title' );
			$title     = (string) $title;

			// If old and new values are empty then don't log the alert.
			if ( empty( $old_title ) && empty( $title ) ) {
				return;
			}

			// Remove whitespaces at the ends of the titles.
			$old_title = trim( $old_title );
			$title     = trim( $title );

			// If title is changed then log alert.
			if ( $old_title !== $title ) {

				// Ensure default value is not passed as NULL.
				if ( ! empty( $old_title ) && empty( $title ) ) {
					if ( strpos( $old_title, '%%title%% %%page%% %%sep%% %%sitename%%' ) !== false ) {
						$title = '%%title%% %%page%% %%sep%% %%sitename%%';
					}
				}
				if ( empty( $old_title ) && ! empty( $title ) ) {
					if ( strpos( $title, '%%title%% %%page%% %%sep%% %%sitename%%' ) !== false ) {
						$old_title = '%%title%% %%page%% %%sep%% %%sitename%%';
					}
				}

				$editor_link = self::get_editor_link( self::$post_id );
				Alert_Manager::trigger_event(
					8801,
					array(
						'PostID'             => self::$post->ID,
						'PostType'           => self::$post->post_type,
						'PostTitle'          => self::$post->post_title,
						'PostStatus'         => self::$post->post_status,
						'PostDate'           => self::$post->post_date,
						'PostUrl'            => get_permalink( self::$post->ID ),
						'OldSEOTitle'        => $old_title,
						'NewSEOTitle'        => $title,
						$editor_link['name'] => $editor_link['value'],
					)
				);
			}
		}

		/**
		 * Method: Check SEO Meta Description Change.
		 *
		 * @param string $desc – Changed SEO Meta Description.
		 */
		private static function check_desc_change( $desc ) {
			// Get old desc value.
			$old_desc = esc_html( self::get_post_seo_data( 'metadesc' ) );
			$desc     = esc_html( $desc );

			// If old and new values are empty then don't log the alert.
			if ( empty( $old_desc ) && empty( $desc ) ) {
				return;
			}

			// If desc is changed then log alert.
			if ( trim( $old_desc ) !== trim( $desc ) ) {

				// Replace NULL with a nicer string.
				if ( empty( $old_desc ) ) {
					$old_desc = esc_html__( 'Not provided', 'wp-security-audit-log' );
				}

				$editor_link = self::get_editor_link( self::$post_id );
				Alert_Manager::trigger_event(
					8802,
					array(
						'PostID'             => self::$post->ID,
						'PostType'           => self::$post->post_type,
						'PostTitle'          => self::$post->post_title,
						'PostStatus'         => self::$post->post_status,
						'PostDate'           => self::$post->post_date,
						'PostUrl'            => get_permalink( self::$post->ID ),
						'old_desc'           => $old_desc,
						'new_desc'           => $desc,
						$editor_link['name'] => $editor_link['value'],
					)
				);
			}
		}

		/**
		 * Method: Check Meta Robots Index Change.
		 *
		 * @param string $index – Changed Meta Robots Index.
		 */
		private static function check_robots_index_change( $index ) {
			// Get old title value.
			$old_index = (int) self::get_post_seo_data( 'meta-robots-noindex' );

			if ( 1 === $old_index ) {
				$old_index = 'No';
			} else {
				$old_index = 'Yes';
			}

			if ( 1 === $index ) {
				$index = 'No';
			} else {
				$index = 'Yes';
			}

			// If setting is changed then log alert.
			if ( $old_index !== $index ) {
				$editor_link = self::get_editor_link( self::$post_id );
				Alert_Manager::trigger_event(
					8803,
					array(
						'PostID'             => self::$post->ID,
						'PostType'           => self::$post->post_type,
						'PostTitle'          => self::$post->post_title,
						'PostStatus'         => self::$post->post_status,
						'PostDate'           => self::$post->post_date,
						'PostUrl'            => get_permalink( self::$post->ID ),
						'OldStatus'          => $old_index,
						'NewStatus'          => $index,
						$editor_link['name'] => $editor_link['value'],
					)
				);
			}
		}

		/**
		 * Method: Check Meta Robots Follow Change.
		 *
		 * @param string $follow – Changed Meta Robots Follow.
		 */
		private static function check_robots_follow_change( $follow ) {
			// Get old title value.
			$old_follow = (int) self::get_post_seo_data( 'meta-robots-nofollow' );

			if ( 1 === $old_follow ) {
				$old_follow = 'disabled';
			} else {
				$old_follow = 'enabled';
			}

			if ( 1 === $follow ) {
				$follow = 'disabled';
			} else {
				$follow = 'enabled';
			}

			// If setting is changed then log alert.
			if ( $old_follow !== $follow ) {
				$editor_link = self::get_editor_link( self::$post_id );
				Alert_Manager::trigger_event(
					8804,
					array(
						'PostID'             => self::$post->ID,
						'PostType'           => self::$post->post_type,
						'PostTitle'          => self::$post->post_title,
						'PostStatus'         => self::$post->post_status,
						'PostDate'           => self::$post->post_date,
						'PostUrl'            => get_permalink( self::$post->ID ),
						'EventType'          => $follow,
						$editor_link['name'] => $editor_link['value'],
					)
				);
			}
		}

		/**
		 * Method: Check Meta Robots Advanced Change.
		 *
		 * @param array $advanced – Advanced array.
		 */
		private static function check_robots_advanced_change( $advanced ) {
			// Convert to string.
			if ( is_array( $advanced ) ) {
				$advanced = implode( ',', $advanced );
			}

			// Get old title value.
			$old_adv = self::get_post_seo_data( 'meta-robots-adv' );

			// If old and new values are empty then don't log the alert.
			if ( empty( $old_adv ) && ( empty( $advanced ) || '-' === $advanced ) ) {
				return;
			}

			// If setting is changed then log alert.
			if ( $old_adv !== $advanced ) {
				$editor_link = self::get_editor_link( self::$post_id );
				Alert_Manager::trigger_event(
					8805,
					array(
						'PostID'             => self::$post->ID,
						'PostType'           => self::$post->post_type,
						'PostTitle'          => self::$post->post_title,
						'PostStatus'         => self::$post->post_status,
						'PostDate'           => self::$post->post_date,
						'PostUrl'            => get_permalink( self::$post->ID ),
						'OldStatus'          => $old_adv,
						'NewStatus'          => $advanced,
						$editor_link['name'] => $editor_link['value'],
					)
				);
			}
		}

		/**
		 * Method: Check Canonical URL Change.
		 *
		 * @param string $canonical_url – Changed Canonical URL.
		 */
		private static function check_canonical_url_change( $canonical_url ) {
			// Get old title value.
			$old_url = self::get_post_seo_data( 'canonical' );

			// Check to see if both change value are empty.
			if ( empty( $old_url ) && empty( $canonical_url ) ) {
				return; // Return if both are empty.
			}

			// If title is changed then log alert.
			if ( $old_url !== $canonical_url ) {
				$editor_link = self::get_editor_link( self::$post_id );
				Alert_Manager::trigger_event(
					8806,
					array(
						'PostID'             => self::$post->ID,
						'PostType'           => self::$post->post_type,
						'PostTitle'          => self::$post->post_title,
						'PostStatus'         => self::$post->post_status,
						'PostDate'           => self::$post->post_date,
						'PostUrl'            => get_permalink( self::$post->ID ),
						'OldCanonicalUrl'    => $old_url,
						'NewCanonicalUrl'    => $canonical_url,
						$editor_link['name'] => $editor_link['value'],
					)
				);
			}
		}

		/**
		 * Method: Check Focus Keywords Change.
		 *
		 * @param string $focus_keys – Changed Focus Keywords.
		 */
		private static function check_focus_keys_change( $focus_keys ) {
			// Get old title value.
			$old_focus_keys = self::get_post_seo_data( 'focuskw' );

			// If old and new values are empty then don't log the alert.
			if ( empty( $old_focus_keys ) && empty( $focus_keys ) ) {
				return;
			}

			// If title is changed then log alert.
			if ( $old_focus_keys !== $focus_keys ) {

				// Replace NULL with a nicer string.
				if ( empty( $old_focus_keys ) ) {
					$old_focus_keys = esc_html__( 'Not provided', 'wp-security-audit-log' );
				}

				$editor_link = self::get_editor_link( self::$post_id );
				Alert_Manager::trigger_event(
					8807,
					array(
						'PostID'             => self::$post->ID,
						'PostType'           => self::$post->post_type,
						'PostTitle'          => self::$post->post_title,
						'PostStatus'         => self::$post->post_status,
						'PostDate'           => self::$post->post_date,
						'PostUrl'            => get_permalink( self::$post->ID ),
						'old_keywords'       => $old_focus_keys,
						'new_keywords'       => $focus_keys,
						$editor_link['name'] => $editor_link['value'],
					)
				);
			}
		}

		/**
		 * Method: Check Cornerstone Change.
		 *
		 * @param string $cornerstone – Changed Cornerstone.
		 */
		private static function check_cornerstone_change( $cornerstone ) {
			// Get old title value.
			$old_cornerstone = (int) self::get_post_seo_data( 'is_cornerstone' );
			$cornerstone     = (int) $cornerstone;

			if ( 1 === $cornerstone ) {
				$alert_status = 'enabled';
			} else {
				$alert_status = 'disabled';
			}

			// If setting is changed then log alert.
			if ( $old_cornerstone !== $cornerstone ) {
				$editor_link = self::get_editor_link( self::$post_id );
				Alert_Manager::trigger_event(
					8808,
					array(
						'PostID'             => self::$post->ID,
						'PostType'           => self::$post->post_type,
						'PostTitle'          => self::$post->post_title,
						'PostStatus'         => self::$post->post_status,
						'PostDate'           => self::$post->post_date,
						'PostUrl'            => get_permalink( self::$post->ID ),
						'EventType'          => $alert_status,
						$editor_link['name'] => $editor_link['value'],
					)
				);
			}
		}


		/**
		 * Method: Check Breadcrumb Change.
		 *
		 * @param string $breadcrumb – Changed Breadcrumb.
		 */
		private static function check_breadcrumb_change( $breadcrumb ) {
			// Get old title value.
			$old_breadcrumb = ( ! self::get_post_seo_data( 'bctitle' ) ) ? '' : self::get_post_seo_data( 'bctitle' );

			// If setting is changed then log alert.
			if ( $old_breadcrumb !== $breadcrumb ) {
				$editor_link = self::get_editor_link( self::$post_id );
				Alert_Manager::trigger_event(
					8850,
					array(
						'PostID'             => self::$post->ID,
						'PostType'           => self::$post->post_type,
						'PostTitle'          => self::$post->post_title,
						'PostStatus'         => self::$post->post_status,
						'PostDate'           => self::$post->post_date,
						'PostUrl'            => get_permalink( self::$post->ID ),
						'old_breadcrumb'     => $old_breadcrumb,
						'new_breadcrumb'     => $breadcrumb,
						$editor_link['name'] => $editor_link['value'],
					)
				);
			}
		}

		/**
		 * Method: Check Schema Change.
		 *
		 * @param string $schema – Changed Schema.
		 * @param string $type   – Page type.
		 */
		private static function check_schema_change( $schema, $type = 'page_type' ) {

			// Get old title value.
			$old_schema = ( 'page_type' === $type ) ? self::get_post_seo_data( 'schema_page_type' ) : self::get_post_seo_data( 'schema_article_type' );

			// If setting is changed then log alert.
			if ( $old_schema !== $schema ) {

				$event_code = ( 'page_type' === $type ) ? 8851 : 8852;

				$editor_link = self::get_editor_link( self::$post_id );
				Alert_Manager::trigger_event(
					$event_code,
					array(
						'PostID'             => self::$post->ID,
						'PostType'           => self::$post->post_type,
						'PostTitle'          => self::$post->post_title,
						'PostStatus'         => self::$post->post_status,
						'PostDate'           => self::$post->post_date,
						'PostUrl'            => get_permalink( self::$post->ID ),
						'old_type'           => ( $old_schema ) ? self::$schema_labels[ $old_schema ] : esc_html__( 'Default', 'wp-security-audit-log' ),
						'new_type'           => ( $schema ) ? self::$schema_labels[ $schema ] : esc_html__( 'Default', 'wp-security-audit-log' ),
						$editor_link['name'] => $editor_link['value'],
					)
				);
			}
		}

		/**
		 * Method: Yoast default blog options change trigger.
		 * Notes:
		 *    - To accomplish that, Yoast is taking the site, removes option (wpseo_ms) and sets the new one
		 *
		 *    @see Yoast-Network-Admin::handle_restore_site_request
		 *    - wp functions used do not triggering events @see WPSEO_Options::reset_ms_blog :
		 *    - delete_blog_option, update_blog_option
		 * Logic used here is - if add_option_wpseo is triggered (this method is called only then), and global $_POST is set with valid 'site_id' value and 'ms_defaults_set' (in $value parameter) == true - we know which site has been preset with the default options
		 *
		 * @param string $option – Option name.
		 * @param mixed  $value – Option old value.
		 */
		public static function yoast_blog_options_trigger( $option, $value ) {
			$site_id = ( isset( $_POST['wpseo_ms'] ) && ! empty( $_POST['wpseo_ms']['site_id'] ) ) ? (int) $_POST['wpseo_ms']['site_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( $site_id && isset( $value['ms_defaults_set'] ) && true === $value['ms_defaults_set'] ) {
				self::yoast_setting_change_alert( 'site-default-options-change', $site_id, '' );
			}
		}

		/**
		 * Method: Yoast SEO options trigger.
		 *
		 * @param string $option – Option name.
		 * @param mixed  $old_value – Option old value.
		 * @param mixed  $new_value – Option new value.
		 */
		public static function yoast_site_options_trigger( $option, $old_value, $new_value ) {

			if ( 'wpseo_ms' === $option ) {

				$prefix_network = 'network-';
				$prefix_yoast   = 'allow_';

				$event_names = array(
					'keyword_analysis_active', // SEO analysis.
					'content_analysis_active', // Readability analysis.
					'enable_cornerstone_content', // Cornerstone Content.
					'enable_text_link_counter', // Text Link Counter.
					'enable_xml_sitemap', // XML Sitemaps.
					'enable_admin_bar_menu', // Admin bar menu.
					'disableadvanced_meta', // Advanced settings for authors.
					'tracking', // Usage tracking.
					'enable_headless_rest_endpoints', // REST enpoint.
					'enable_enhanced_slack_sharing', // Slack sharing.
				);

				foreach ( $event_names as $event_name ) {
					$yoast_name = $prefix_yoast . $event_name; // Yoast event names - starting with active-[YoastEventName].
					$wsal_name  = $prefix_network . $event_name; // internal use name - network-[YoastEventName].

					if ( isset( $old_value[ $yoast_name ] ) && isset( $new_value[ $yoast_name ] ) ) {
						if ( $old_value[ $yoast_name ] !== $new_value[ $yoast_name ] ) {

							$suffix = '-inactive';
							if ( ! $new_value[ $yoast_name ] ) {
								$suffix = '-active';
							}

							self::yoast_setting_switch_alert( $wsal_name . $suffix, $new_value[ $yoast_name ] );
						}
					}
				}
				if ( $old_value['access'] !== $new_value['access'] ) {
					self::yoast_setting_change_alert( 'site-access-change', $old_value['access'], $new_value['access'] );
				}
				// We are aware the variables are being fed in backwards here, however this appears to be the only way.
				// to get a reliable outcome. Issue https://github.com/WPWhiteSecurity/activity-log-yoast-seo/issues/63.
				if ( $old_value['defaultblog'] !== $new_value['defaultblog'] ) {
					self::yoast_setting_change_alert( 'site-default-seo-inherit-change', $new_value['defaultblog'], $old_value['defaultblog'] );
				}
			}
		}

		/**
		 * Method: Yoast SEO options trigger.
		 *
		 * @param string $option – Option name.
		 * @param mixed  $old_value – Option old value.
		 * @param mixed  $new_value – Option new value.
		 */
		public static function yoast_options_trigger( $option, $old_value, $new_value ) {

			// Detect the SEO option.
			if ( 'wpseo_titles' === $option || 'wpseo' === $option || 'wpseo_social' === $option ) {
				// WPSEO Title Alerts.
				if ( 'wpseo_titles' === $option ) {
					// Redirect attachment URLs to the attachment itself.
					if ( $old_value['disable-attachment'] !== $new_value['disable-attachment'] ) {
						self::yoast_setting_change_alert( 'disable-attachment', $old_value['disable-attachment'], $new_value['disable-attachment'] );
					}
					// Title Separator.
					if ( $old_value['separator'] !== $new_value['separator'] ) {
						self::yoast_setting_change_alert( 'separator', $old_value['separator'], $new_value['separator'] );
					}

					// Homepage Title.
					if ( $old_value['title-home-wpseo'] !== $new_value['title-home-wpseo'] ) {
						self::yoast_setting_change_alert( 'title-home-wpseo', $old_value['title-home-wpseo'], $new_value['title-home-wpseo'] );
					}

					// Homepage Meta Description.
					if ( $old_value['metadesc-home-wpseo'] !== $new_value['metadesc-home-wpseo'] ) {
						self::yoast_setting_change_alert( 'metadesc-home-wpseo', $old_value['metadesc-home-wpseo'], $new_value['metadesc-home-wpseo'] );
					}

					// Company or Person.
					if ( $old_value['company_or_person'] !== $new_value['company_or_person'] ) {
						self::yoast_setting_change_alert( 'company_or_person', $old_value['company_or_person'], $new_value['company_or_person'] );
					}

					// Author Archives.
					if ( $old_value['disable-author'] !== $new_value['disable-author'] ) {
						self::yoast_setting_switch_alert( 'disable-author', $new_value['disable-author'] );
					}

					if ( $old_value['noindex-author-wpseo'] !== $new_value['noindex-author-wpseo'] ) {
						self::yoast_setting_switch_alert( 'noindex-author-wpseo', $new_value['noindex-author-wpseo'] );
					}

					if ( $old_value['title-author-wpseo'] !== $new_value['title-author-wpseo'] ) {
						self::yoast_setting_change_alert( 'title-author-wpseo', $old_value['title-author-wpseo'], $new_value['title-author-wpseo'] );
					}

					if ( $old_value['metadesc-author-wpseo'] !== $new_value['metadesc-author-wpseo'] ) {
						self::yoast_setting_change_alert( 'metadesc-author-wpseo', $old_value['metadesc-author-wpseo'], $new_value['metadesc-author-wpseo'] );
					}

					// Date Archives.
					if ( $old_value['disable-date'] !== $new_value['disable-date'] ) {
						self::yoast_setting_switch_alert( 'disable-date', $new_value['disable-date'] );
					}

					if ( $old_value['noindex-archive-wpseo'] !== $new_value['noindex-archive-wpseo'] ) {
						self::yoast_setting_switch_alert( 'noindex-archive-wpseo', $new_value['noindex-archive-wpseo'] );
					}

					if ( $old_value['title-archive-wpseo'] !== $new_value['title-archive-wpseo'] ) {
						self::yoast_setting_change_alert( 'title-archive-wpseo', $old_value['title-archive-wpseo'], $new_value['title-archive-wpseo'] );
					}

					if ( $old_value['metadesc-archive-wpseo'] !== $new_value['metadesc-archive-wpseo'] ) {
						self::yoast_setting_change_alert( 'metadesc-archive-wpseo', $old_value['metadesc-archive-wpseo'], $new_value['metadesc-archive-wpseo'] );
					}

					$schemas = array(
						'schema-page-type-post',
						'schema-article-type-post',
						'schema-page-type-page',
						'schema-article-type-page',
						'schema-page-type-attachment',
						'schema-article-type-attachment',
					);

					foreach ( $schemas as $schema ) {
						if ( $old_value[ $schema ] !== $new_value[ $schema ] ) {
							self::yoast_setting_change_alert( $schema, $old_value[ $schema ], $new_value[ $schema ] );
						}
					}

					// Get public post types.
					$post_types = get_post_types( array( 'public' => true ) );

					// For each post type check show, title, and description changes.
					foreach ( $post_types as $type ) {
						if ( isset( $old_value[ "noindex-$type" ] ) ) {
							// Show Post Type in search results.
							if ( $old_value[ "noindex-$type" ] !== $new_value[ "noindex-$type" ] ) {
								self::yoast_setting_switch_alert( "noindex-$type", $new_value[ "noindex-$type" ] );
							}

							// Post Type Title Template.
							if ( $old_value[ "title-$type" ] !== $new_value[ "title-$type" ] ) {
								self::yoast_setting_change_alert( "title-$type", $old_value[ "title-$type" ], $new_value[ "title-$type" ] );
							}

							// Post Type Meta Description Template.
							if ( $old_value[ "metadesc-$type" ] !== $new_value[ "metadesc-$type" ] ) {
								self::yoast_setting_change_alert( "metadesc-$type", $old_value[ "metadesc-$type" ], $new_value[ "metadesc-$type" ] );
							}

							// Show Meta box.
							if ( $old_value[ "display-metabox-pt-$type" ] !== $new_value[ "display-metabox-pt-$type" ] ) {
								self::yoast_setting_switch_alert( "display-metabox-pt-$type", $new_value[ "display-metabox-pt-$type" ] );
							}
						}
					}

					// Get taxonomy types.
					$taxonomy_types = get_taxonomies( array( 'public' => true ) );

					// Lets check each and see if anything has been changes.
					foreach ( $taxonomy_types as $type ) {
						if ( isset( $old_value[ "noindex-tax-$type" ] ) ) {
							// Show Post Type in search results.
							if ( $old_value[ "noindex-tax-$type" ] !== $new_value[ "noindex-tax-$type" ] ) {
								self::yoast_setting_switch_alert( "noindex-tax-$type", $new_value[ "noindex-tax-$type" ] );
							}

							// Post Type Title Template.
							if ( $old_value[ "title-tax-$type" ] !== $new_value[ "title-tax-$type" ] ) {
								self::yoast_setting_change_alert( "title-tax-$type", $old_value[ "title-tax-$type" ], $new_value[ "title-tax-$type" ] );
							}

							// Post Type Meta Description Template.
							if ( $old_value[ "metadesc-tax-$type" ] !== $new_value[ "metadesc-tax-$type" ] ) {
								self::yoast_setting_change_alert( "metadesc-tax-$type", $old_value[ "metadesc-tax-$type" ], $new_value[ "metadesc-tax-$type" ] );
							}

							// Show Meta box.
							if ( $old_value[ "display-metabox-tax-$type" ] !== $new_value[ "display-metabox-tax-$type" ] ) {
								self::yoast_setting_switch_alert( "display-metabox-tax-$type", $new_value[ "display-metabox-tax-$type" ] );
							}
						}
					}
				}

				// Webmaster URL alerts.
				if ( 'wpseo' === $option ) {
					// SEO analysis.
					if ( isset( $old_value['keyword_analysis_active'] ) && isset( $new_value['keyword_analysis_active'] ) ) {
						if ( $old_value['keyword_analysis_active'] !== $new_value['keyword_analysis_active'] ) {
							self::yoast_setting_switch_alert( 'keyword_analysis_active', $new_value['keyword_analysis_active'] );
						}
					}

					// Readability analysis.
					if ( isset( $old_value['content_analysis_active'] ) && isset( $new_value['content_analysis_active'] ) ) {
						if ( $old_value['content_analysis_active'] !== $new_value['content_analysis_active'] ) {
							self::yoast_setting_switch_alert( 'content_analysis_active', $new_value['content_analysis_active'] );
						}
					}

					// Cornerstone Content.
					if ( isset( $old_value['enable_cornerstone_content'] ) && isset( $new_value['enable_cornerstone_content'] ) ) {
						if ( $old_value['enable_cornerstone_content'] !== $new_value['enable_cornerstone_content'] ) {
							self::yoast_setting_switch_alert( 'enable_cornerstone_content', $new_value['enable_cornerstone_content'] );
						}
					}

					// Text Link Counter.
					if ( isset( $old_value['enable_text_link_counter'] ) && isset( $new_value['enable_text_link_counter'] ) ) {
						if ( $old_value['enable_text_link_counter'] !== $new_value['enable_text_link_counter'] ) {
							self::yoast_setting_switch_alert( 'enable_text_link_counter', $new_value['enable_text_link_counter'] );
						}
					}

					// XML Sitemaps.
					if ( isset( $old_value['enable_xml_sitemap'] ) && isset( $new_value['enable_xml_sitemap'] ) ) {
						if ( $old_value['enable_xml_sitemap'] !== $new_value['enable_xml_sitemap'] ) {
							self::yoast_setting_switch_alert( 'enable_xml_sitemap', $new_value['enable_xml_sitemap'] );
						}
					}

					/**
					 * Ryte integration.
					 *
					 * NOTE: Reenamed in yoast plugin v13.2.
					 *
					 * @see: https://github.com/Yoast/wordpress-seo/pull/14123
					 */
					$integrations = array(
						'semrush_integration_active',
						'zapier_integration_active',
						'algolia_integration_active',
						'wincher_integration_active',
						'ryte_indexability',
					);

					foreach ( $integrations as $integration ) {
						if ( \key_exists( $integration, $old_value ) && \key_exists( $integration, $new_value ) ) {

							if ( $old_value[ $integration ] !== $new_value[ $integration ] ) {
								self::yoast_setting_switch_alert( $integration, $new_value[ $integration ] );
							}
						}
					}

					// Admin bar menu.
					if ( isset( $old_value['enable_admin_bar_menu'] ) && isset( $new_value['enable_admin_bar_menu'] ) ) {
						if ( $old_value['enable_admin_bar_menu'] !== $new_value['enable_admin_bar_menu'] ) {
							self::yoast_setting_switch_alert( 'enable_admin_bar_menu', $new_value['enable_admin_bar_menu'] );
						}
					}

					// Advanced settings for authors.
					if ( isset( $old_value['disableadvanced_meta'] ) && isset( $new_value['disableadvanced_meta'] ) ) {
						if ( $old_value['disableadvanced_meta'] !== $new_value['disableadvanced_meta'] ) {
							self::yoast_setting_switch_alert( 'disableadvanced_meta', $new_value['disableadvanced_meta'] );
						}
					}

					// Usage tracking.
					if ( isset( $old_value['tracking'] ) && isset( $new_value['tracking'] ) ) {
						if ( $old_value['tracking'] !== $new_value['tracking'] ) {
							self::yoast_setting_switch_alert( 'tracking', $new_value['tracking'] );
						}
					}

					// REST enpoint.
					if ( isset( $old_value['enable_headless_rest_endpoints'] ) && isset( $new_value['enable_headless_rest_endpoints'] ) ) {
						if ( $old_value['enable_headless_rest_endpoints'] !== $new_value['enable_headless_rest_endpoints'] ) {
							self::yoast_setting_switch_alert( 'enable_headless_rest_endpoints', $new_value['enable_headless_rest_endpoints'] );
						}
					}
					$search_engines = array(
						'baiduverify',
						'googleverify',
						'msverify',
						'yandexverify',
					);

					foreach ( $search_engines as $search_engine ) {
						if ( $old_value[ $search_engine ] !== $new_value[ $search_engine ] ) {
							self::yoast_setting_change_alert( $search_engine, $old_value[ $search_engine ], $new_value[ $search_engine ] );
						}
					}
				}

				// Social profile alerts.
				if ( 'wpseo_social' === $option ) {
					self::yoast_social_profile_setting_change_alert( $old_value, $new_value );
				}
			}

			if ( 'wpseo-premium-redirects-export-plain' === $option ) {
				self::yoast_redirects_change_alert( $option, $old_value, $new_value, 'plain' );
			} elseif ( 'wpseo-premium-redirects-export-regex' === $option ) {
				self::yoast_redirects_change_alert( $option, $old_value, $new_value, 'regex' );
			} elseif ( 'wpseo_redirect' === $option ) {
				self::yoast_redirects_system_change_alert( $option, $old_value, $new_value );
			}
		}

		/**
		 * Trigger an alert for redirect changes.
		 *
		 * @param string $option - Current option being changes.
		 * @param array  $old_value - Old value.
		 * @param array  $new_value - new value.
		 * @return void
		 */
		private static function yoast_redirects_system_change_alert( $option, $old_value, $new_value ) {
			$alert_code = 8858;
			$alert_args = array(
				'new_method' => ( 'off' === $new_value['disable_php_redirect'] ) ? esc_html__( 'PHP', 'wp-security-audit-log' ) : esc_html__( 'Web server', 'wp-security-audit-log' ),
				'old_method' => ( 'off' === $old_value['disable_php_redirect'] ) ? esc_html__( 'PHP', 'wp-security-audit-log' ) : esc_html__( 'Web server', 'wp-security-audit-log' ),
			);
			Alert_Manager::trigger_event( $alert_code, $alert_args );
		}

		/**
		 * Monitor and alert for changes related to Yoast redirects (Premium only)
		 *
		 * @param string $option - Option being changed.
		 * @param array  $old_value - Old value.
		 * @param array  $new_value - New Value.
		 * @param string $redirect_type - Redirection type.
		 *
		 * @return void
		 */
		private static function yoast_redirects_change_alert( $option, $old_value, $new_value, $redirect_type = 'plain' ) {
			$alert_args = null;
			$alert_code = null;
			$is_regex   = ( 'regex' === $redirect_type ) ? true : false;

			if ( count( $old_value ) !== count( $new_value ) ) {

				$compare_added_items = array_diff_assoc(
					array_map( 'serialize', $new_value ),
					array_map( 'serialize', $old_value )
				);
				$added_items         = array_map( 'unserialize', $compare_added_items );

				$compare_removed_items = array_diff_assoc(
					array_map( 'serialize', $old_value ),
					array_map( 'serialize', $new_value )
				);
				$removed_items         = array_map( 'unserialize', $compare_removed_items );

				if ( ! empty( $added_items ) ) {
					$alert_code                  = 8855;
					$alert_args['old_url']       = key( $added_items );
					$added_items                 = end( $added_items );
					$alert_args['new_url']       = ( isset( $added_items['url'] ) && ! empty( $added_items['url'] ) ) ? $added_items['url'] : esc_html__( 'Not applicable', 'wp-security-audit-log' );
					$alert_args['redirect_code'] = $added_items['type'];
					$alert_args['redirect_type'] = ( $is_regex ) ? 'regex' : 'plain';
				}

				if ( ! empty( $removed_items ) ) {
					$alert_code                  = 8857;
					$alert_args['old_url']       = key( $removed_items );
					$removed_items               = end( $removed_items );
					$alert_args['new_url']       = ( isset( $removed_items['url'] ) && ! empty( $added_items['url'] ) ) ? $removed_items['url'] : esc_html__( 'Not applicable', 'wp-security-audit-log' );
					$alert_args['redirect_code'] = $removed_items['type'];
					$alert_args['redirect_type'] = ( $is_regex ) ? 'regex' : 'plain';
				}
			}

			if ( count( $old_value ) === count( $new_value ) ) {
				$compare_modified_items = array_diff_assoc(
					array_map( 'serialize', $new_value ),
					array_map( 'serialize', $old_value )
				);
				$modified_items         = array_map( 'unserialize', $compare_modified_items );

				$compare_removed_items = array_diff_assoc(
					array_map( 'serialize', $old_value ),
					array_map( 'serialize', $new_value )
				);
				$removed_items         = array_map( 'unserialize', $compare_removed_items );

				if ( ! empty( $modified_items ) ) {
					$alert_code                      = 8856;
					$alert_args['redirect_type']     = ( $is_regex ) ? 'regex' : 'plain';
					$alert_args['old_url']           = ( key( $removed_items ) !== key( $modified_items ) ) ? key( $removed_items ) : esc_html__( 'Not applicable', 'wp-security-audit-log' );
					$alert_args['new_old_url']       = ( key( $modified_items ) !== key( $removed_items ) ) ? key( $modified_items ) : esc_html__( 'Not applicable', 'wp-security-audit-log' );
					$modified_items                  = end( $modified_items );
					$removed_items                   = end( $removed_items );
					$alert_args['old_new_url']       = ( isset( $removed_items['url'] ) && ! empty( $removed_items['url'] ) && $modified_items['url'] !== $removed_items['url'] ) ? $removed_items['url'] : esc_html__( 'Not applicable', 'wp-security-audit-log' );
					$alert_args['new_new_url']       = ( isset( $modified_items['url'] ) && ! empty( $added_items['url'] ) && $modified_items['url'] !== $removed_items['url'] ) ? $modified_items['url'] : esc_html__( 'Not applicable', 'wp-security-audit-log' );
					$alert_args['new_redirect_code'] = ( $removed_items['type'] !== $modified_items['type'] ) ? $modified_items['type'] : esc_html__( 'Not applicable', 'wp-security-audit-log' );
					$alert_args['old_redirect_code'] = ( $modified_items['type'] !== $removed_items['type'] ) ? $removed_items['type'] : esc_html__( 'Not applicable', 'wp-security-audit-log' );
				}
			}

			if ( ! empty( $alert_code ) ) {
				Alert_Manager::trigger_event( $alert_code, $alert_args );
			}
		}

		/**
		 * Method: Trigger Yoast Setting Change Alerts.
		 *
		 * @param string $key – Setting key.
		 * @param string $old_value – Old setting value.
		 * @param string $new_value – New setting value.
		 */
		private static function yoast_setting_change_alert( $key, $old_value, $new_value ) {
			// Return if key is empty.
			if ( empty( $key ) ) {
				return;
			}

			// Return if both old and new values are empty.
			if ( empty( $old_value ) && empty( $new_value ) ) {
				return;
			}

			// Alert arguments.
			$alert_args = array(
				'old' => $old_value, // Old value.
				'new' => $new_value, // New value.
			);

			// Find title-* in the key.
			if ( false !== strpos( $key, 'title-' ) ) {
				// Confirm if this is a taxonomy or not.
				if ( false !== strpos( $key, 'title-tax-' ) ) {
					$seo_post_type = self::create_tidy_name( $key );

					// Set alert meta data.
					$alert_args['SEOPostType'] = $seo_post_type;
				} elseif ( false !== strpos( $key, 'title-author-' ) || false !== strpos( $key, 'title-archive-' ) ) {
					$seo_post_type = self::create_tidy_name( $key );

					// Set alert meta data.
					$alert_args['archive_type'] = $seo_post_type;
				} else {
					$seo_post_type  = self::create_tidy_name( $key );
					$seo_post_type .= 's';

					// Set alert meta data.
					$alert_args['SEOPostType'] = $seo_post_type;
				}
			}

			// Find metadesc-* in the key.
			if ( false !== strpos( $key, 'metadesc-' ) ) {
				// Confirm if this is a taxonomy or not.
				if ( false !== strpos( $key, 'metadesc-tax-' ) ) {
					$seo_post_type = self::create_tidy_name( $key );
					// Set alert meta data.
					$alert_args['SEOPostType'] = $seo_post_type;
				} elseif ( false !== strpos( $key, 'metadesc-author-' ) || false !== strpos( $key, 'metadesc-archive-' ) ) {
					$seo_post_type  = self::create_tidy_name( $key );
					$seo_post_type .= 's';
					// Set alert meta data.
					$alert_args['archive_type'] = $seo_post_type;
				} else {
					$seo_post_type  = self::create_tidy_name( $key );
					$seo_post_type .= 's';

					// Set alert meta data.
					$alert_args['SEOPostType'] = $seo_post_type;
				}
			}

			// Set alert code to null initially.
			$alert_code = null;

			// Detect alert code for setting.
			switch ( $key ) {
				case 'separator':
					$alert_code = 8809;
					if ( class_exists( '\WPSEO_Option_Titles' ) ) {
						$titles               = \WPSEO_Option_Titles::get_instance();
						$available_seperators = $titles->get_separator_options();
						$alert_args['old']    = $available_seperators[ $alert_args['old'] ];
						$alert_args['new']    = $available_seperators[ $alert_args['new'] ];
					}
					break;

				case 'metadesc-archive-wpseo':
				case 'metadesc-author-wpseo':
					$alert_code = 8836;
					break;

				case 'company_or_person':
					$alert_code        = 8812;
					$alert_args['old'] = ucwords( $alert_args['old'] );
					$alert_args['new'] = ucwords( $alert_args['new'] );
					break;

				case strpos( $key, 'title-archive-' ):
				case strpos( $key, 'title-author-' ):
					$alert_code = 8835;
					break;

				case strpos( $key, 'title-tax-' ):
					$alert_code = 8831;
					break;

				case strpos( $key, 'title-' ):
					$alert_code = 8814;
					break;

				case strpos( $key, 'metadesc-tax-' ):
					$alert_code        = 8832;
					$alert_args['old'] = ( ! empty( $alert_args['old'] ) ) ? $alert_args['old'] : esc_html__( 'Not provided', 'wp-security-audit-log' );
					$alert_args['new'] = ( ! empty( $alert_args['new'] ) ) ? $alert_args['new'] : esc_html__( 'Not provided', 'wp-security-audit-log' );
					break;

				case strpos( $key, 'metadesc-' ):
					$alert_code        = 8822;
					$alert_args['old'] = ( ! empty( $alert_args['old'] ) ) ? $alert_args['old'] : esc_html__( 'Not provided', 'wp-security-audit-log' );
					$alert_args['new'] = ( ! empty( $alert_args['new'] ) ) ? $alert_args['new'] : esc_html__( 'Not provided', 'wp-security-audit-log' );
					break;

				case 'disable-attachment':
					$alert_code              = 8826;
					$alert_args['EventType'] = $new_value ? 'enabled' : 'disabled';
					break;

				case 'site-access-change':
					$alert_code        = 8838;
					$alert_args['old'] = ucwords( $alert_args['old'] );
					$alert_args['new'] = ucwords( $alert_args['new'] );
					break;

				case 'site-default-seo-inherit-change':
					$alert_code        = 8839;
					$alert_args['old'] = ( ! empty( $alert_args['old'] ) ) ? get_blog_details( $alert_args['old'] )->blogname : esc_html__( 'None', 'wp-security-audit-log' );
					$alert_args['new'] = ( ! empty( $alert_args['new'] ) ) ? get_blog_details( $alert_args['new'] )->blogname : esc_html__( 'None', 'wp-security-audit-log' );
					break;

				case 'site-default-options-change':
					$alert_code        = 8840;
					$alert_args['old'] = get_blog_details( $alert_args['old'] )->blogname . ' / ' . $alert_args['old'];
					$alert_args['new'] = '';
					break;

				case 'baiduverify':
				case 'googleverify':
				case 'msverify':
				case 'yandexverify':
					$alert_code                       = 8841;
					$alert_args['search_engine_type'] = ucwords( str_replace( 'verify', '', $key ) );

					if ( empty( $alert_args['old'] ) && ! empty( $alert_args['new'] ) ) {
						$event_type = 'added';
					} elseif ( empty( $alert_args['new'] ) && ! empty( $alert_args['old'] ) ) {
						$event_type = 'removed';
					} else {
						$event_type = 'modified';
					}

					$alert_args['EventType'] = $event_type;
					$alert_args['old']       = ( empty( $alert_args['old'] ) ) ? esc_html__( 'Not provided', 'wp-security-audit-log' ) : $alert_args['old'];
					$alert_args['new']       = ( empty( $alert_args['new'] ) ) ? esc_html__( 'Not provided', 'wp-security-audit-log' ) : $alert_args['new'];
					break;

				case 'schema-page-type-post':
				case 'schema-page-type-page':
				case 'schema-page-type-attachment':
					$alert_code                = 8853;
					$alert_args['SEOPostType'] = ucwords( str_replace( 'schema-page-type-', '', $key ) );
					$alert_args['old_type']    = ( $alert_args['old'] ) ? self::$schema_labels[ $alert_args['old'] ] : esc_html__( 'Default', 'wp-security-audit-log' );
					$alert_args['new_type']    = ( $alert_args['new'] ) ? self::$schema_labels[ $alert_args['new'] ] : esc_html__( 'Default', 'wp-security-audit-log' );
					break;

				case 'schema-article-type-page':
				case 'schema-article-type-post':
				case 'schema-article-type-attachment':
					$alert_code                = 8854;
					$alert_args['SEOPostType'] = ucwords( str_replace( 'schema-article-type-', '', $key ) );
					$alert_args['old_type']    = ( $alert_args['old'] ) ? self::$schema_labels[ $alert_args['old'] ] : esc_html__( 'Default', 'wp-security-audit-log' );
					$alert_args['new_type']    = ( $alert_args['new'] ) ? self::$schema_labels[ $alert_args['new'] ] : esc_html__( 'Default', 'wp-security-audit-log' );
					break;

				default:
					break;
			}

			// Trigger the alert.
			if ( ! empty( $alert_code ) ) {
				Alert_Manager::trigger_event( $alert_code, $alert_args );
			}
		}

		/**
		 * Method: Trigger Yoast Enable/Disable Setting Alerts.
		 *
		 * @param string $key ��� Setting index to alert.
		 * @param mixed  $new_value – Setting new value.
		 */
		private static function yoast_setting_switch_alert( $key, $new_value ) {
			// If key is empty, then return.
			if ( empty( $key ) ) {
				return;
			}

			// Check and set status.
			$status = (int) $new_value;

			// Alert arguments.
			$alert_args = array();

			// Find noindex-* in the key.
			if ( false !== strpos( $key, 'noindex-' ) ) {
				// Check if its a taxonomy setting.
				if ( false !== strpos( $key, 'noindex-tax-' ) ) {
					$seo_post_type = self::create_tidy_name( $key );
					// Set alert meta data.
					$alert_args['SEOPostType'] = $seo_post_type;
					$status                    = 1 === $status ? 0 : 1;
				} else {
					$seo_post_type = self::create_tidy_name( $key );

					// Set alert meta data.
					$alert_args['SEOPostType'] = $seo_post_type;
					$status                    = 1 === $status ? 0 : 1;
				}
			}

			// Find display-metabox-pt-* in the key.
			if ( false !== strpos( $key, 'display-metabox-tax-' ) ) {
				$seo_post_type = self::create_tidy_name( $key );

				// Set alert meta data.
				$alert_args['SEOPostType'] = $seo_post_type;
			} else {
				$seo_post_type = self::create_tidy_name( $key );

				// Set alert meta data.
				$alert_args['SEOPostType'] = $seo_post_type;
			}

			$alert_args['EventType'] = 1 === $status ? 'enabled' : 'disabled';

			// Find network-* in the key.
			if ( false !== strpos( $key, 'network-' ) ) {
				$event_key = substr( $key, strpos( $key, '-' ) + 1 );

				$event_key = substr( $event_key, 0, strrpos( $event_key, '-' ) );

				switch ( $event_key ) {
					default:
					case 'keyword_analysis_active':
						$feature_name = esc_html__( 'SEO Analysis', 'wp-security-audit-log' );
						break;
					case 'content_analysis_active':
						$feature_name = esc_html__( 'Readability Analysis', 'wp-security-audit-log' );
						break;
					case 'enable_cornerstone_content':
						$feature_name = esc_html__( 'Cornerstone content', 'wp-security-audit-log' );
						break;
					case 'enable_text_link_counter':
						$feature_name = esc_html__( 'Text link counter', 'wp-security-audit-log' );
						break;
					case 'enable_xml_sitemap':
						$feature_name = esc_html__( 'XML sitemap', 'wp-security-audit-log' );
						break;
					case 'enable_admin_bar_menu':
						$feature_name = esc_html__( 'Admin bar menu', 'wp-security-audit-log' );
						break;
					case 'disableadvanced_meta':
						$feature_name = esc_html__( 'Security: advanced or schema settings for authors', 'wp-security-audit-log' );
						break;
					case 'tracking':
						$feature_name = esc_html__( 'Usage tracking', 'wp-security-audit-log' );
						break;
					case 'enable_headless_rest_endpoints':
						$feature_name = esc_html__( 'REST API: Head endpoint', 'wp-security-audit-log' );
						break;
					case 'enable_enhanced_slack_sharing':
						$feature_name = esc_html__( 'Slack sharing', 'wp-security-audit-log' );
						break;
				}

				// Set alert meta data.
				$alert_args['feature_name'] = $feature_name;
				$alert_args['EventType']    = 1 === $status ? 'disabled' : 'enabled';
			}

			// Set alert code to NULL initially.
			$alert_code = null;

			// Add switch case to set the alert code.
			switch ( $key ) {
				case 'noindex-author-wpseo':
				case 'noindex-archive-wpseo':
					$alert_code   = 8834;
					$archive_type = self::create_tidy_name( $key );
					// If this is the "date archive" setting, update archive type to something more descriptive.
					if ( 'Archive' === $archive_type ) {
						$archive_type = esc_html__( 'Date', 'wp-security-audit-log' );
					}
					// Set alert meta data.
					$alert_args['archive_type'] = $archive_type;
					break;

				case strpos( $key, 'noindex-tax-' ):
					$alert_code = 8830;
					break;

				case strpos( $key, 'noindex-' ):
					$alert_code = 8813;
					break;

				case 'keyword_analysis_active':
					$alert_code = 8815;
					break;

				case 'content_analysis_active':
					$alert_code = 8816;
					break;

				case 'enable_cornerstone_content':
					$alert_code = 8817;
					break;

				case 'enable_text_link_counter':
					$alert_code = 8818;
					break;

				case 'enable_xml_sitemap':
					$alert_code = 8819;
					break;

				case ( false !== strpos( $key, 'network-' ) && false !== strpos( $key, '-inactive' ) ):
					$alert_code = 8842;
					break;

				case ( false !== strpos( $key, 'network-' ) && false !== strpos( $key, '-active' ) ):
					$alert_code = 8843;
					break;

				// renamed to ryte_integration. see: https://github.com/Yoast/wordpress-seo/pull/14123.
				case 'onpage_indexability':
				case 'ryte_indexability':
					$alert_code         = 8820;
					$alert_args['type'] = ucfirst( str_replace( '_indexability', '', $key ) );
					break;

				case 'semrush_integration_active':
				case 'zapier_integration_active':
				case 'algolia_integration_active':
				case 'wincher_integration_active':
					$alert_code         = 8820;
					$alert_args['type'] = ucfirst( str_replace( '_integration_active', '', $key ) );
					break;

				case 'enable_admin_bar_menu':
					$alert_code = 8821;
					break;

				case strpos( $key, 'display-metabox-pt-' ):
					$alert_code = 8824;
					break;

				case strpos( $key, 'display-metabox-tax-' ):
					$alert_code = 8837;
					// Avoid false reporting for post_format metabox.
					if ( 'display-metabox-tax-post_format' === $key ) {
						$alert_code = null;
					}
					break;

				case strpos( $key, 'disableadvanced_meta' ):
					$alert_code = 8825;
					break;

				case strpos( $key, 'tracking' ):
					$alert_code = 8827;
					break;

				case strpos( $key, 'enable_headless_rest_endpoints' ):
					$alert_code = 8828;
					break;

				case 'disable-author':
				case 'disable-date':
					$alert_code   = 8833;
					$archive_type = str_replace( 'disable-', '', $key );
					$archive_type = ucfirst( $archive_type );
					// Set alert meta data.
					$alert_args['archive_type'] = $archive_type;
					// Reverse logic for enabled/disabled.
					$alert_args['EventType'] = $new_value ? 'disabled' : 'enabled';
					break;

				default:
					break;
			}

			// Trigger the alert.
			if ( ! empty( $alert_code ) ) {
				Alert_Manager::trigger_event( $alert_code, $alert_args );
			}
		}

		/**
		 * Method: Trigger Yoast Social profile settings alerts.
		 *
		 * @param mixed $old_value – Setting old value.
		 * @param mixed $new_value – Setting new value.
		 */
		private static function yoast_social_profile_setting_change_alert( $old_value, $new_value ) {

			// Array of keys we want to look for.
			$profiles_to_monitor = array(
				'facebook_site',
				'instagram_url',
				'linkedin_url',
				'pinterest_url',
				'twitter_site',
				'youtube_url',
				'wikipedia_url',
			);

			foreach ( $old_value as $social_profile => $value ) {

				if ( in_array( $social_profile, $profiles_to_monitor, true ) && $old_value[ $social_profile ] !== $new_value[ $social_profile ] ) {
					$alert_code = 8829;
					$event_type = self::determine_social_event_type( $old_value[ $social_profile ], $new_value[ $social_profile ] );
					$alert_args = array(
						'social_profile' => ucwords( substr( $social_profile, 0, strpos( $social_profile, '_' ) ) ),
						'old_url'        => empty( $old_value[ $social_profile ] ) ? ' ' : $old_value[ $social_profile ], // The empty string is intentional.
						'new_url'        => empty( $new_value[ $social_profile ] ) ? ' ' : $new_value[ $social_profile ], // The empty string is intentional.
						'EventType'      => $event_type,
					);
					Alert_Manager::trigger_event( $alert_code, $alert_args );
				}
			}

			// Facebook social settings.
			if ( $new_value['opengraph'] !== $old_value['opengraph'] ) {
				$alert_code = 8844;
				$alert_args = array(
					'EventType' => ( ! $new_value['opengraph'] ) ? 'disabled' : 'enabled',
				);
				Alert_Manager::trigger_event( $alert_code, $alert_args );
			}

			if ( $new_value['og_default_image'] !== $old_value['og_default_image'] ) {
				$alert_code = 8845;
				$alert_args = array(
					'image_name' => ( empty( $new_value['og_default_image_id'] ) ) ? esc_html__( 'None supplied', 'wsal-yoast' ) : wp_basename( $new_value['og_default_image'] ),
					'image_path' => ( empty( $new_value['og_default_image'] ) ) ? esc_html__( 'None supplied', 'wsal-yoast' ) : dirname( $new_value['og_default_image'] ),
					'old_image'  => ( empty( $old_value['og_default_image'] ) ) ? esc_html__( 'None supplied', 'wsal-yoast' ) : wp_basename( $old_value['og_default_image'] ),
					'old_path'   => ( empty( $old_value['og_default_image'] ) ) ? esc_html__( 'None supplied', 'wsal-yoast' ) : dirname( $old_value['og_default_image'] ),
				);
				Alert_Manager::trigger_event( $alert_code, $alert_args );
			}

			if ( $new_value['twitter'] !== $old_value['twitter'] ) {
				$alert_code = 8846;
				$alert_args = array(
					'EventType' => ( ! $new_value['twitter'] ) ? 'disabled' : 'enabled',
				);
				Alert_Manager::trigger_event( $alert_code, $alert_args );
			}

			if ( $new_value['twitter_card_type'] !== $old_value['twitter_card_type'] ) {
				$alert_code = 8847;
				$alert_args = array(
					'new_setting' => $new_value['twitter_card_type'],
					'old_setting' => $old_value['twitter_card_type'],
				);
				Alert_Manager::trigger_event( $alert_code, $alert_args );
			}

			if ( $new_value['pinterestverify'] !== $old_value['pinterestverify'] ) {
				$alert_code = 8848;
				$alert_args = array(
					'new_value' => ( empty( $new_value['pinterestverify'] ) ) ? esc_html__( 'None supplied', 'wsal-yoast' ) : $new_value['pinterestverify'],
					'old_value' => ( empty( $old_value['pinterestverify'] ) ) ? esc_html__( 'None supplied', 'wsal-yoast' ) : $old_value['pinterestverify'],
				);
				Alert_Manager::trigger_event( $alert_code, $alert_args );
			}
		}

		/**
		 * Helper function to check if a profile was added, removed or modified.
		 *
		 * @param  string $old_value Old profile value.
		 * @param  string $new_value New profile value.
		 * @return string            Our determination of whats happened
		 */
		private static function determine_social_event_type( $old_value, $new_value ) {
			if ( ! empty( $old_value ) && empty( $new_value ) ) {
				return 'removed';
			} elseif ( empty( $old_value ) && ! empty( $new_value ) ) {
				return 'added';
			} else {
				return 'modified';
			}
		}

		/**
		 * Helper function to strip a string of any unwanted compontent.
		 *
		 * @param  string $text_to_strip String we want to work on.
		 * @return string $tidied_text   The actual string we want.
		 */
		private static function create_tidy_name( $text_to_strip ) {
			$tidied_text = null;

			// Array of string we want to look for.
			$strings_to_remove = array(
				'title-',
				'tax-',
				'-wpseo',
				'metadesc-',
				'noindex-',
				'display-',
				'metabox-',
				'pt-',
				'disable-',
			);

			if ( ! empty( $text_to_strip ) ) {
				$tidied_text = str_replace( $strings_to_remove, '', $text_to_strip );
				$tidied_text = ucfirst( $tidied_text );

				// If this is the "date archive" setting, update archive type to something more descriptive.
				if ( 'Archive' === $tidied_text ) {
					$tidied_text = esc_html__( 'Date', 'wp-security-audit-log' );
				}

				// If left unchanged, the alert reads "Categorys". The 's' is missing as its added later.
				if ( 'Category' === $tidied_text ) {
					$tidied_text = esc_html__( 'Categories', 'wp-security-audit-log' );
				}

				return $tidied_text;
			}
		}
	}
}
