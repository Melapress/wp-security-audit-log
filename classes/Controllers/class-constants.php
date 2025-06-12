<?php
/**
 * Controller: Constants.
 *
 * Constants class file.
 *
 * @since     4.5
 *
 * @package   wsal
 * @subpackage controllers
 */

declare(strict_types=1);

namespace WSAL\Controllers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Controllers\Constants' ) ) {
	/**
	 * Provides logging functionality for the comments.
	 *
	 * @since 4.5.0
	 */
	class Constants {
		public const WSAL_SEVERITIES = array(
			0   => 'E_UNKNOWN',
			500 => 'WSAL_CRITICAL',
			400 => 'WSAL_HIGH',
			300 => 'WSAL_MEDIUM',
			250 => 'WSAL_LOW',
			200 => 'WSAL_INFORMATIONAL',
		);

		/**
		 * Same array as above, but names are keys - for speeding up the checks
		 */
		public const WSAL_SEVERITIES_NAMES = array(
			'E_UNKNOWN'          => 0,
			'WSAL_CRITICAL'      => 500,
			'WSAL_HIGH'          => 400,
			'WSAL_MEDIUM'        => 300,
			'WSAL_LOW'           => 250,
			'WSAL_INFORMATIONAL' => 200,
		);

		/**
		 * All the severities
		 *
		 * @var array
		 *
		 * @since 5.1.1
		 */
		public static $severities = array();

		/**
		 * All the severities with codes as keys
		 *
		 * @var array
		 *
		 * @since 5.2.1
		 */
		public static $severities_codes = array();

		/**
		 * Holds the array with all the severities for the plugin.
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		private static $wsal_constants = array();

		/**
		 * Holds the array with all the default built in links.
		 *
		 * @var array
		 *
		 * @since 5.3.0
		 */
		private static $wsal_built_links = array();

		/**
		 * If the search is unsuccessful - that is needed because of the legacy behavior of the plugin. Currently we can not get rid of that functionality - so that is the reason for that flag.
		 *
		 * @var bool
		 *
		 * @since 4.5.0
		 */
		private static $not_found = false;

		/**
		 * Inits the class variables.
		 *
		 * @return void
		 *
		 * @since 4.5.0
		 */
		public static function init() {
			self::$not_found = false;

			// Declaring the global legacy constants.
			defined( 'E_CRITICAL' ) || define( 'E_CRITICAL', 'E_CRITICAL' );
			defined( 'E_WARNING' ) || define( 'E_WARNING', 'E_WARNING' );
			defined( 'E_NOTICE' ) || define( 'E_NOTICE', 'E_NOTICE' );
			defined( 'E_UNKNOWN' ) || define( 'E_UNKNOWN', 'E_UNKNOWN' );
			defined( 'WSAL_CRITICAL' ) || define( 'WSAL_CRITICAL', 'WSAL_CRITICAL' );
			defined( 'WSAL_HIGH' ) || define( 'WSAL_HIGH', 'WSAL_HIGH' );
			defined( 'WSAL_MEDIUM' ) || define( 'WSAL_MEDIUM', 'WSAL_MEDIUM' );
			defined( 'WSAL_LOW' ) || define( 'WSAL_LOW', 'WSAL_LOW' );
			defined( 'WSAL_INFORMATIONAL' ) || define( 'WSAL_INFORMATIONAL', 'WSAL_INFORMATIONAL' );

			self::$wsal_constants = array(
				array(
					'name'        => 'E_CRITICAL',
					'css'         => 'wsal_critical',
					'value'       => 500,
					'text'        => __( 'Critical', 'wp-security-audit-log' ),
					'description' => '',
				),
				array(
					'name'        => 'E_WARNING',
					'css'         => 'wsal_medium',
					'value'       => 300,
					'text'        => __( 'Medium', 'wp-security-audit-log' ),
					'description' => '',
				),
				array(
					'name'        => 'E_NOTICE',
					'css'         => 'wsal_informational',
					'value'       => 100,
					'text'        => __( 'Notification', 'wp-security-audit-log' ),
					'description' => '',
				),
				array(
					'name'        => 'E_UNKNOWN',
					'css'         => 'e_unknown',
					'value'       => 0,
					'text'        => __( 'Unknown', 'wp-security-audit-log' ),
					'description' => __( 'Unknown error code.', 'wp-security-audit-log' ),
				),
				500 => array(
					'name'        => 'WSAL_CRITICAL',
					'css'         => 'wsal_critical',
					'value'       => 500,
					'text'        => __( 'Critical', 'wp-security-audit-log' ),
					'description' => esc_html__( 'Critical severity events.', 'wp-security-audit-log' ),
				),
				400 => array(
					'name'        => 'WSAL_HIGH',
					'css'         => 'wsal_high',
					'value'       => 400,
					'text'        => __( 'High', 'wp-security-audit-log' ),
					'description' => esc_html__( 'High severity events.', 'wp-security-audit-log' ),
				),
				300 => array(
					'name'        => 'WSAL_MEDIUM',
					'css'         => 'wsal_medium',
					'value'       => 300,
					'text'        => __( 'Medium', 'wp-security-audit-log' ),
					'description' => esc_html__( 'Medium severity events.', 'wp-security-audit-log' ),
				),
				250 => array(
					'name'        => 'WSAL_LOW',
					'css'         => 'wsal_low',
					'value'       => 250,
					'text'        => __( 'Low', 'wp-security-audit-log' ),
					'description' => esc_html__( 'Low severity events.', 'wp-security-audit-log' ),
				),
				200 => array(
					'name'        => 'WSAL_INFORMATIONAL',
					'css'         => 'wsal_informational',
					'value'       => 200,
					'text'        => __( 'Informational', 'wp-security-audit-log' ),
					'description' => esc_html__( 'Informational events.', 'wp-security-audit-log' ),
				),
			);
		}

		/**
		 * Adds severity to the class.
		 *
		 * @param string $name        - The name of the constant.
		 * @param mixed  $value       - The value of the constant.
		 * @param string $description - The description of the constant.
		 *
		 * @return void
		 *
		 * @since 4.5.0
		 *
		 * @throws \Exception - If the constant is already defined.
		 */
		public static function add_constant( $name, $value, $description = '' ) {
			if ( empty( self::$wsal_constants ) ) {
				self::init();
			}

			foreach ( self::$wsal_constants as $constant ) {
				if ( $constant['name'] === $name ) {
					throw new \Exception( 'Constant already defined with a different value.' );
				}
			}

			self::$wsal_constants[] = array(
				'name'        => $name,
				'value'       => constant( $name ),
				'description' => $description,
			);
		}

		/**
		 * Checks the defined constants and returns its Name.
		 *
		 * @param int $code - The code to check for constants.
		 *
		 * @return string
		 *
		 * @since 4.5.0
		 */
		public static function get_constant_name( $code ) {
			if ( isset( self::WSAL_SEVERITIES[ $code ] ) ) {
				return self::WSAL_SEVERITIES[ $code ];
			}

			return 'E_UNKNOWN';
		}

		/**
		 * Returns the constant code by given name.
		 *
		 * @param string $constant - The name of the constant.
		 *
		 * @return int
		 *
		 * @since 4.5.0
		 */
		public static function get_constant_code( $constant ) {
			if ( isset( self::WSAL_SEVERITIES_NAMES[ $constant ] ) ) {
				return self::WSAL_SEVERITIES_NAMES[ $constant ];
			}

			return -1;
		}

		/**
		 * Checks the constants and returns the value of the given one (could be number or string).
		 *
		 * @param string $constant_name - The name od the constant to search for.
		 *
		 * @return mixed
		 *
		 * @since 4.5.0
		 */
		public static function get_constant_value( string $constant_name ) {
			self::$not_found = false;

			if ( empty( self::$wsal_constants ) ) {
				self::init();
			}

			foreach ( self::$wsal_constants as $constant ) {
				if ( $constant['name'] === $constant_name ) {
					if ( isset( $constant['value'] ) ) {
						return $constant['value'];
					}
				}
			}
			self::$not_found = true;

			return 'Unknown';
		}

		/**
		 * Returns the WSAL constants array
		 *
		 * @param boolean $remove_old - Should we remove old constants from the array.
		 *
		 * @return array
		 *
		 * @since 4.5.0
		 * @since 4.6.0 - $remove_old is added
		 */
		public static function get_wsal_constants( bool $remove_old = false ): array {
			if ( empty( self::$wsal_constants ) ) {
				self::init();
			}

			if ( $remove_old ) {
				return array_slice( self::$wsal_constants, 4 );
			}

			return self::$wsal_constants;
		}

		/**
		 * Checks the constants and returns the description of the given one.
		 *
		 * @param string $constant_name - The name of the constant to check.
		 *
		 * @since 4.5.0
		 */
		public static function get_constant_description( string $constant_name ): string {
			self::$not_found = false;

			if ( empty( self::$wsal_constants ) ) {
				self::init();
			}

			foreach ( self::$wsal_constants as $constant ) {
				if ( $constant['name'] === $constant_name ) {
					if ( isset( $constant['description'] ) ) {
						return $constant['description'];
					}
				}
			}
			self::$not_found = true;

			return 'Unknown error code.';
		}

		/**
		 * Returns the class flag after the last operation.
		 *
		 * @return bool
		 *
		 * @since 4.5.0
		 */
		public static function is_found() {
			return ! self::$not_found;
		}

		/**
		 * Returns the severity array by the given code. The code could be either value or name (value is checked first)
		 *
		 * @param int $code - The code to search for.
		 *
		 * @return array
		 *
		 * @since 4.5.0
		 */
		public static function get_severity_by_code( $code ) {
			self::$not_found = false;

			if ( empty( self::$wsal_constants ) ) {
				self::init();
			}

			foreach ( self::$wsal_constants as $constant ) {
				if ( $constant['value'] === $code ) {
					return $constant;
				}
				if ( $constant['name'] === $code ) {
					return $constant;
				}
			}
			self::$not_found = true;

			return array(
				'name'        => 'E_UNKNOWN',
				'css'         => 'e_unknown',
				'value'       => 0,
				'text'        => __( 'Unknown', 'wp-security-audit-log' ),
				'description' => __( 'Unknown error code.', 'wp-security-audit-log' ),
			);
		}

		/**
		 * Fast search for code in the severities constants. It uses key to search for and if that fails it fall back into the row by row search.
		 *
		 * @param int|string $code - The code to search for.
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function fast_severity_by_code( $code ): array {
			if ( empty( self::$wsal_constants ) ) {
				self::init();
			}

			if ( isset( self::$wsal_constants[ $code ] ) ) {
				return self::$wsal_constants[ $code ];
			} else {
				return self::get_severity_by_code( $code );
			}
		}

		/**
		 * Returns the code name (human readable) by code number.
		 *
		 * @param int $code - The code number to get the human readable name.
		 *
		 * @return string
		 *
		 * @since 5.0.0
		 */
		public static function get_severity_name_by_code( $code ) {
			return self::fast_severity_by_code( (int) $code )['text'];
		}

		/**
		 * Returns array with all the severities.
		 * Thats is legacy code - should think of something better.
		 *
		 * @return array
		 *
		 * @since 4.3.2
		 */
		public static function get_severities() {
			if ( empty( self::$severities ) ) {
				self::$severities = array(
					'WSAL_CRITICAL'      => __( 'Critical', 'wp-security-audit-log' ),
					'WSAL_HIGH'          => __( 'High', 'wp-security-audit-log' ),
					'WSAL_MEDIUM'        => __( 'Medium', 'wp-security-audit-log' ),
					'WSAL_LOW'           => __( 'Low', 'wp-security-audit-log' ),
					'WSAL_INFORMATIONAL' => __( 'Info', 'wp-security-audit-log' ),
				);
			}

			return self::$severities;
		}

		/**
		 * Returns array with all the severities.
		 * Thats is legacy code - should think of something better.
		 *
		 * @return array
		 *
		 * @since 5.2.1
		 */
		public static function get_severities_with_codes() {
			if ( empty( self::$severities ) ) {
				self::get_severities();
			}
			if ( empty( self::$severities_codes ) ) {
				$temp_names = \array_keys( self::WSAL_SEVERITIES );
				\array_shift( $temp_names );

				self::$severities_codes = \array_combine( ( $temp_names ), \array_values( self::get_severities() ) );

			}

			return self::$severities_codes;
		}


		/**
		 * Builds a configuration object of links suitable for the events definition.
		 *
		 * @param string[] $link_aliases Link aliases.
		 *
		 * @return array
		 *
		 * @since 5.3.0
		 */
		public static function wsaldefaults_build_links( $link_aliases = array() ) {
			$result = array();

			if ( empty( self::$wsal_built_links ) ) {
				self::$wsal_built_links['CategoryLink']   = array( esc_html__( 'View category', 'wp-security-audit-log' ) => '%CategoryLink%' );
				self::$wsal_built_links['cat_link']       = array( esc_html__( 'View category', 'wp-security-audit-log' ) => '%cat_link%' );
				self::$wsal_built_links['ProductCatLink'] = array( esc_html__( 'View category', 'wp-security-audit-log' ) => '%ProductCatLink%' );

				self::$wsal_built_links['ContactSupport'] = array( esc_html__( 'Contact Support', 'wp-security-audit-log' ) => 'https://melapress.com/contact/' );

				self::$wsal_built_links['CommentLink'] = array(
					\esc_html__( 'Comment', 'wp-security-audit-log' ) => array(
						// Before 4.2.1 the CommentLink meta would contain the full HTML markup for the link, now it
						// contains only the URL.
						'url'   => '%CommentLink%',
						'label' => '%CommentDate%',
					),
				);

				self::$wsal_built_links['EditorLinkPage'] = array( esc_html__( 'View page in the editor', 'wp-security-audit-log' ) => '%EditorLinkPage%' );

				self::$wsal_built_links['EditorLinkPost'] = array( esc_html__( 'View the post in editor', 'wp-security-audit-log' ) => '%EditorLinkPost%' );

				self::$wsal_built_links['EditorLinkOrder'] = array( esc_html__( 'View the order', 'wp-security-audit-log' ) => '%EditorLinkOrder%' );

				self::$wsal_built_links['EditUserLink'] = array( esc_html__( 'User profile page', 'wp-security-audit-log' ) => '%EditUserLink%' );

				self::$wsal_built_links['LinkFile'] = array( esc_html__( 'Open the log file', 'wp-security-audit-log' ) => '%LinkFile%' );

				self::$wsal_built_links['MenuUrl'] = array( esc_html__( 'View menu', 'wp-security-audit-log' ) => '%MenuUrl%' );

				self::$wsal_built_links['PostUrl'] = array( esc_html__( 'URL', 'wp-security-audit-log' ) => '%PostUrl%' );

				self::$wsal_built_links['AttachmentUrl'] = array( esc_html__( 'View attachment page', 'wp-security-audit-log' ) => '%AttachmentUrl%' );

				self::$wsal_built_links['PostUrlIfPlublished'] = array( esc_html__( 'URL', 'wp-security-audit-log' ) => '%PostUrlIfPlublished%' );

				self::$wsal_built_links['PostUrlIfPublished'] = array( esc_html__( 'URL', 'wp-security-audit-log' ) => '%PostUrlIfPlublished%' );

				self::$wsal_built_links['RevisionLink'] = array( esc_html__( 'View the content changes', 'wp-security-audit-log' ) => '%RevisionLink%' );

				self::$wsal_built_links['TagLink'] = array( esc_html__( 'View tag', 'wp-security-audit-log' ) => '%RevisionLink%' );

				/*
				* All these links are formatted using WSAL_AlertFormatter (including any label) because they
				* contain non-trivial HTML markup that includes custom JS. We assume these will only be rendered
				* in the log viewer in WP admin UI.
				*/
				self::$wsal_built_links['LogFileText'] = array( '%LogFileText%' );
				self::$wsal_built_links['MetaLink']    = array( '%MetaLink%' );

			}

			if ( ! empty( $link_aliases ) ) {
				foreach ( $link_aliases as $link_alias ) {
					if ( array_key_exists( $link_alias, self::$wsal_built_links ) ) {
						$result = array_merge( $result, self::$wsal_built_links[ $link_alias ] );
					}
				}
			}

			return $result;
		}
	}
}
