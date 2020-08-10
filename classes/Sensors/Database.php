<?php
/**
 * Sensor: Database
 *
 * Database sensors class file.
 *
 * @since 1.0.0
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database sensor.
 *
 * 5010 Plugin created table
 * 5011 Plugin modified table structure
 * 5012 Plugin deleted table
 * 5013 Theme created tables
 * 5014 Theme modified tables structure
 * 5015 Theme deleted tables
 * 5016 Unknown component created tables
 * 5017 Unknown component modified tables structure
 * 5018 Unknown component deleted tables
 * 5022 WordPress created tables
 * 5023 WordPress modified tables structure
 * 5024 WordPress deleted tables
 *
 * @package Wsal
 * @subpackage Sensors
 */
class WSAL_Sensors_Database extends WSAL_AbstractSensor {

	/**
	 * Local cache for basename of current script. It used used to improve performance
	 * of determining the actor of current action.
	 *
	 * @var string|bool
	 */
	private $script_basename = null;

	/**
	 * If true, database events are being logged. This is used by the plugin's update process to temporarily disable
	 * the database sensor to prevent errors (events are registered after the upgrade process is run).
	 *
	 * @var bool
	 * @since 4.1.3
	 */
	public static $enabled = true;

	/**
	 * Listening to events using WP hooks.
	 */
	public function HookEvents() {
		if ( $this->plugin->IsInstalled() ) {
			add_action( 'dbdelta_queries', array( $this, 'EventDBDeltaQuery' ) );
			add_filter( 'query', array( $this, 'EventDropQuery' ) );
		}
	}

	/**
	 * Checks for drop query.
	 *
	 * @param string $query - Database query string.
	 *
	 * @return string
	 */
	public function EventDropQuery( $query ) {
		if ( ! self::$enabled ) {
			return $query;
		}
		global $wpdb;
		$table_names = array();
		$str         = explode( ' ', $query );
		$query_type  = '';
		if ( preg_match( '|DROP TABLE ([^ ]*)|', $query ) ) {
			if ( ! empty( $str[4] ) ) {
				array_push( $table_names, $str[4] );
			} else {
				array_push( $table_names, $str[2] );
			}
			$query_type = 'delete';
		} elseif ( preg_match( '|CREATE TABLE IF NOT EXISTS ([^ ]*)|', $query ) ) {
			$table_name = str_replace( '`', '', $str[5] );
			if ( $table_name !== $wpdb->get_var( "SHOW TABLES LIKE '" . $table_name . "'" ) ) {
				/**
				 * Some plugins keep trying to create tables even
				 * when they already exist - would result in too
				 * many alerts.
				 */
				array_push( $table_names, $table_name );
				$query_type = 'create';
			}
		}

		$this->MaybeTriggerEvent( $query_type, $table_names );

		return $query;
	}

	/**
	 * Triggers an event if the list of tables is not empty. It also checks if
	 * the event should be logged for events originated by WordPress.
	 *
	 * @param string $query_type
	 * @param string[] $table_names
	 */
	private function MaybeTriggerEvent( $query_type, $table_names ) {
		if ( ! empty( $table_names ) ) {
			$actor = $this->GetActor( $table_names );
			if ( 'wordpress' === $actor && ! $this->plugin->settings()->IsWPBackend() ) {
				//  event is not fired if the monitoring of background events is disabled
				return;
			}

			$alert_options               = $this->GetEventOptions( $actor );
			$event_code                  = $this->GetEventCode( $actor, $query_type );
			$alert_options['TableNames'] = implode( ',', $table_names );
			$this->plugin->alerts->Trigger( $event_code, $alert_options );
		}
	}

	/**
	 * Determine the actor of database change.
	 *
	 * @param string[] $table_names Names of the tables that are being changed.
	 *
	 * @return bool|string Theme, plugin or false if unknown.
	 */
	private function GetActor( $table_names ) {
		//  default actor (treated as an unknown component)
		$result = false;

		//  use current script name to determine if the actor is theme or a plugin

		if ( is_null( $this->script_basename ) ) {
			$this->script_basename = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ), '.php' ) : false;
		}

		$result = $this->script_basename;

		//  check table names for default WordPress table names (including network tables)
		if ( $this->ContainsWordPressTable( $table_names ) ) {
			$result = 'wordpress';
		}

		return $result;
	}

	private function ContainsWordPressTable( $tables ) {
		if ( ! empty( $tables ) ) {
			global $wpdb;
			$prefix        = preg_quote( $wpdb->prefix );
			$site_regex    = '/\b' . $prefix . '(\d+_)?(commentmeta|comments|links|options|postmeta|posts|terms|termmeta|term_relationships|term_relationships|term_taxonomy|usermeta|users)\b/';
			$network_regex = '/\b' . $prefix . '(blogs|blog_versions|registration_log|signups|site|sitemeta|users|usermeta)\b/';

			foreach ( $tables as $table ) {
				if ( preg_match( $site_regex, $table ) || preg_match( $network_regex, $table ) ) {
					//  stop as soon as the first WordPress table is found
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get event options by actor.
	 *
	 * @param string $actor - Plugins, themes, WordPress or unknown.
	 *
	 * @return array
	 */
	protected function GetEventOptions( $actor ) {
		// Check the actor
		$alert_options = array();
		switch ( $actor ) {
			case 'plugins':
				// Action Plugin Component.
				$plugin_file = '';
				// @codingStandardsIgnoreStart
				if ( isset( $_GET['plugin'] ) ) {
					$plugin_file = sanitize_text_field( wp_unslash( $_GET['plugin'] ) );
				} elseif ( isset( $_GET['checked'] ) ) {
					$plugin_file = sanitize_text_field( wp_unslash( $_GET['checked'][0] ) );
				}
				// @codingStandardsIgnoreEnd

				// Get plugin data.
				$plugins = get_plugins();
				if ( isset( $plugins[ $plugin_file ] ) ) {
					$plugin = $plugins[ $plugin_file ];

					// Set alert options.
					$alert_options['Plugin'] = (object) array(
						'Name'      => $plugin['Name'],
						'PluginURI' => $plugin['PluginURI'],
						'Version'   => $plugin['Version'],
					);
				} else {
					$plugin_name             = basename( $plugin_file, '.php' );
					$plugin_name             = str_replace( array( '_', '-', '  ' ), ' ', $plugin_name );
					$plugin_name             = ucwords( $plugin_name );
					$alert_options['Plugin'] = (object) array( 'Name' => $plugin_name );
				}
				break;
			case 'themes':
				// Action Theme Component.
				$theme_name = '';

				// @codingStandardsIgnoreStart
				if ( isset( $_GET['theme'] ) ) {
					$theme_name = sanitize_text_field( wp_unslash( $_GET['theme'] ) );
				} elseif ( isset( $_GET['checked'] ) ) {
					$theme_name = sanitize_text_field( wp_unslash( $_GET['checked'][0] ) );
				}
				// @codingStandardsIgnoreEnd

				$theme_name             = str_replace( array( '_', '-', '  ' ), ' ', $theme_name );
				$theme_name             = ucwords( $theme_name );
				$alert_options['Theme'] = (object) array( 'Name' => $theme_name );
				break;

			case 'wordpress':
				$alert_options['Component'] = 'WordPress';
				break;

			default:
				// Action Unknown Component.
				$alert_options['Component'] = 'Unknown';

		}

		return $alert_options;
	}

	/**
	 * Get alert code by actor and query type.
	 *
	 * @param string $actor - Plugins, themes, WordPress or unknown.
	 * @param string $query_type - Create, update or delete.
	 *
	 * @return int Event code.
	 */
	protected function GetEventCode( $actor, $query_type ) {
		switch ( $actor ) {
			case 'plugins':
				if ( 'create' === $query_type ) {
					return 5010;
				} elseif ( 'update' === $query_type ) {
					return 5011;
				} elseif ( 'delete' === $query_type ) {
					return 5012;
				}
				break;

			case 'themes':
				if ( 'create' === $query_type ) {
					return 5013;
				} elseif ( 'update' === $query_type ) {
					return 5014;
				} elseif ( 'delete' === $query_type ) {
					return 5015;
				}
				break;

			case 'wordpress':
				if ( 'create' === $query_type ) {
					return 5022;
				} elseif ( 'update' === $query_type ) {
					return 5023;
				} elseif ( 'delete' === $query_type ) {
					return 5024;
				}
				break;
			default:
				if ( 'create' === $query_type ) {
					return 5016;
				} elseif ( 'update' === $query_type ) {
					return 5017;
				} elseif ( 'delete' === $query_type ) {
					return 5018;
				}
				break;
		}
	}

	/**
	 * Checks DB Delta queries.
	 *
	 * @param array $queries - Array of queries.
	 *
	 * @return array
	 */
	public function EventDBDeltaQuery( $queries ) {
		if ( ! self::$enabled ) {
			return $queries;
		}

		$query_types = array(
			'create' => array(),
			'update' => array(),
			'delete' => array(),
		);

		global $wpdb;
		foreach ( $queries as $qry ) {
			$qry = str_replace( '`', '', $qry );
			$str = explode( ' ', $qry );
			if ( preg_match( '|CREATE TABLE ([^ ]*)|', $qry ) ) {
				if ( $str[2] !== $wpdb->get_var( "SHOW TABLES LIKE '" . $str[2] . "'" ) ) {
					/**
					 * Some plugins keep trying to create tables even
					 * when they already exist- would result in too
					 * many alerts.
					 */
					array_push( $query_types['create'], $str[2] );
				}
			} elseif ( preg_match( '|ALTER TABLE ([^ ]*)|', $qry ) ) {
				array_push( $query_types['update'], $str[2] );
			} elseif ( preg_match( '|DROP TABLE ([^ ]*)|', $qry ) ) {
				if ( ! empty( $str[4] ) ) {
					array_push( $query_types['delete'], $str[4] );
				} else {
					array_push( $query_types['delete'], $str[2] );
				}
			}
		}

		if ( ! empty( $query_types['create'] ) || ! empty( $query_types['update'] ) || ! empty( $query_types['delete'] ) ) {
			foreach ( $query_types as $query_type => $table_names ) {
				$this->MaybeTriggerEvent( $query_type, $table_names );
			}
		}

		return $queries;
	}
}
