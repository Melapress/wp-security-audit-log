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
	 * List of already logged operation during current request. It is used to prevent duplicate events. Values in the
	 * array are strings in form of "{operation type}_{table name}".
	 *
	 * @var string[]
	 * @since 4.1.5
	 */
	private static $already_logged = [];

	/**
	 * Listening to events using WP hooks.
	 */
	public function HookEvents() {
		add_action( 'dbdelta_queries', array( $this, 'EventDBDeltaQuery' ) );
		add_filter( 'query', array( $this, 'EventDropQuery' ) );
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

		$table_names = array();
		$str         = explode( ' ', $query );
		$query_type  = '';
		if ( preg_match( '|DROP TABLE( IF EXISTS)? ([^ ]*)|', $query ) ) {
			$table_name = empty( $str[4] ) ? $str[2] : $str[4];
			//  only log when the table exists as some plugins try to delete tables even if they don't exist
			if ( $this->is_table_operation_check_enabled($table_name, 'delete')
			     && $this->check_if_table_exists( $table_name ) ) {
				array_push( $table_names, $table_name );
				$query_type = 'delete';
			}
		} elseif ( preg_match( '/CREATE TABLE( IF NOT EXISTS)? ([^ ]*)/i', $query, $matches ) || preg_match( '/CREATE TABLE ([^ ]*)/i', $query, $matches ) ) {
			$table_name = $matches[count($matches) - 1];
			if ( $this->is_table_operation_check_enabled($table_name, 'create')
			     && ! $this->check_if_table_exists($table_name) ) {
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

			// Loop through each item to report event per table.
			foreach ( $table_names as $table_name ) {
				$alert_options               = $this->GetEventOptions( $actor );
				$event_code                  = $this->GetEventCode( $actor, $query_type );
				$db_op_key = $query_type . '_' . $table_name;
				if ( in_array( $db_op_key, self::$already_logged ) ) {
					continue;
				}

				$alert_options['TableNames'] = $table_name;
				$this->plugin->alerts->Trigger( $event_code, $alert_options );
				array_push( self::$already_logged, $db_op_key );
			}
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

					// If this is still empty at this point, lets check recent events.
					if ( empty( $plugin_file ) ) {
						$plugin_name = $this->determine_recently_activated_plugin();
					}

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

		foreach ( $queries as $qry ) {
			$qry = str_replace( '`', '', $qry );
			$str = explode( ' ', $qry );
			if ( preg_match( '/CREATE TABLE( IF NOT EXISTS)? ([^ ]*)/i', $qry, $matches ) ) {
				$table_name = $matches[count($matches) - 1];
				if ( $this->is_table_operation_check_enabled($table_name, 'create')
				     && ! $this->check_if_table_exists( $table_name ) ) {
					/**
					 * Some plugins keep trying to create tables even
					 * when they already exist- would result in too
					 * many alerts.
					 */
					array_push( $query_types['create'], $table_name );
				}
			} elseif ( preg_match( '|ALTER TABLE ([^ ]*)|', $qry ) ) {
				array_push( $query_types['update'], $str[2] );
			} elseif ( preg_match( '|DROP TABLE( IF EXISTS)? ([^ ]*)|', $qry ) ) {
				$table_name = empty( $str[4] ) ? $str[2] : $str[4];
				//  only log when the table exists as some plugins try to delete tables even if they don't exist
				if ( $this->is_table_operation_check_enabled($table_name, 'delete')
				     && $this->check_if_table_exists( $table_name ) ) {
					array_push( $query_types['delete'], $table_name );
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

	/**
	 * Last resort to determine the name of a plugin performing the action.
	 *
	 * @return string Name, taken from recent event.
	 */
	private function determine_recently_activated_plugin() {
		$alert_id = 5001;

		$latest_events = $this->plugin->alerts->get_latest_events( 25 );

		foreach ( $latest_events as $latest_event ) {
			if ( $alert_id === intval( $latest_event->alert_id ) ) {
				$event_meta   = $latest_event ? $latest_event->GetMetaArray() : false;
				$plugin_name = $event_meta['PluginData']->Name;
			}
		}

		if ( $plugin_name ) {
			return $plugin_name;
		}
	}

	/**
	 * Checks if a table exists in the WordPress database by running a SELECT query instead of former solution using
	 * SHOW TABLES. The previous solution has proven to be memory intense in shared hosting environments.
	 *
	 * @param string $table_name Table name.
	 *
	 * @return bool True if the table exists. False otherwise.
	 * @since 4.2.0
	 */
	private function check_if_table_exists( $table_name ) {
		try {
			global $wpdb;
			$db_result = $wpdb->query( "SELECT COUNT(1) FROM {$table_name};" );

			return ( 1 === $db_result );
		} catch (Exception $e) {
			return false;
		}
	}

	/**
	 * Checks if alerts for certain query type are enabled or not.
	 *
	 * This is used to prevent unnecessary table existence checks. These checks should not take place
	 * if a specific alert is not enabled. Unfortunately if the alert is enabled or not is being checked
	 * too late.
	 *
	 * @param string $table_name
	 * @param string $query_type
	 *
	 * @return bool
	 * @see WSAL_AlertManager::_CommitItem()
	 * @since 4.2.0
	 */
	private function is_table_operation_check_enabled( $table_name, $query_type ) {
		$actor     = $this->GetActor( [ $table_name ] );
		$eventCode = $this->GetEventCode( $actor, $query_type );
		return $this->plugin->alerts->IsEnabled( $eventCode );
	}
}
