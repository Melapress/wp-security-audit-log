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
 * 5010 Plugin created tables
 * 5011 Plugin modified tables structure
 * 5012 Plugin deleted tables
 * 5013 Theme created tables
 * 5014 Theme modified tables structure
 * 5015 Theme deleted tables
 * 5016 Unknown component created tables
 * 5017 Unknown component modified tables structure
 * 5018 Unknown component deleted tables
 *
 * @package Wsal
 * @subpackage Sensors
 */
class WSAL_Sensors_Database extends WSAL_AbstractSensor {

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
	 * @param WP_Query $query - Query object.
	 */
	public function EventDropQuery( $query ) {
		global $wpdb;
		$table_names = array();
		$str         = explode( ' ', $query );

		if ( preg_match( '|DROP TABLE ([^ ]*)|', $query ) ) {
			if ( ! empty( $str[4] ) ) {
				array_push( $table_names, $str[4] );
			} else {
				array_push( $table_names, $str[2] );
			}

			$actype        = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ), '.php' ) : false;
			$alert_options = $this->GetActionType( $actype );
			$type_query    = 'delete';
		} elseif ( preg_match( '|CREATE TABLE IF NOT EXISTS ([^ ]*)|', $query ) ) {
			$table_name = str_replace( '`', '', $str[5] );
			if ( $table_name !== $wpdb->get_var( "SHOW TABLES LIKE '" . $table_name . "'" ) ) {
				/**
				 * Some plugins keep trying to create tables even
				 * when they already exist- would result in too
				 * many alerts.
				 */
				array_push( $table_names, $table_name );
				$actype        = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ), '.php' ) : false;
				$alert_options = $this->GetActionType( $actype );
				$type_query    = 'create';
			}
		}

		if ( ! empty( $table_names ) ) {
			$event_code                  = $this->GetEventQueryType( $actype, $type_query );
			$alert_options['TableNames'] = implode( ',', $table_names );
			$this->plugin->alerts->Trigger( $event_code, $alert_options );
		}

		return $query;
	}

	/**
	 * Checks DB Delta queries.
	 *
	 * @param array $queries - Array of query.
	 */
	public function EventDBDeltaQuery( $queries ) {
		$type_queries = array(
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
					array_push( $type_queries['create'], $str[2] );
				}
			} elseif ( preg_match( '|ALTER TABLE ([^ ]*)|', $qry ) ) {
				array_push( $type_queries['update'], $str[2] );
			} elseif ( preg_match( '|DROP TABLE ([^ ]*)|', $qry ) ) {
				if ( ! empty( $str[4] ) ) {
					array_push( $type_queries['delete'], $str[4] );
				} else {
					array_push( $type_queries['delete'], $str[2] );
				}
			}
		}

		if ( ! empty( $type_queries['create'] ) || ! empty( $type_queries['update'] ) || ! empty( $type_queries['delete'] ) ) {
			$actype        = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ), '.php' ) : false;
			$alert_options = $this->GetActionType( $actype );

			foreach ( $type_queries as $query_type => $table_names ) {
				if ( ! empty( $table_names ) ) {
					$event_code                  = $this->GetEventQueryType( $actype, $query_type );
					$alert_options['TableNames'] = implode( ',', $table_names );
					$this->plugin->alerts->Trigger( $event_code, $alert_options );
				}
			}
		}
		return $queries;
	}

	/**
	 * Get code alert by action and type query.
	 *
	 * @param string $type_action - Plugins, themes or unknown.
	 * @param string $type_query - Create, update or delete.
	 */
	protected function GetEventQueryType( $type_action, $type_query ) {
		switch ( $type_action ) {
			case 'plugins':
				if ( 'create' === $type_query ) {
					return 5010;
				} elseif ( 'update' === $type_query ) {
					return 5011;
				} elseif ( 'delete' === $type_query ) {
					return 5012;
				}
				break;

			case 'themes':
				if ( 'create' === $type_query ) {
					return 5013;
				} elseif ( 'update' === $type_query ) {
					return 5014;
				} elseif ( 'delete' === $type_query ) {
					return 5015;
				}
				break;

			default:
				if ( 'create' === $type_query ) {
					return 5016;
				} elseif ( 'update' === $type_query ) {
					return 5017;
				} elseif ( 'delete' === $type_query ) {
					return 5018;
				}
				break;
		}
	}

	/**
	 * Get info by action type.
	 *
	 * @param string $actype - Plugins, themes or unknown.
	 */
	protected function GetActionType( $actype ) {
		// Check the component type (theme or plugin).
		$is_themes  = 'themes' === $actype;
		$is_plugins = 'plugins' === $actype;

		// Action Plugin Component.
		$alert_options = array();
		if ( $is_plugins ) {
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
		} elseif ( $is_themes ) {
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
		} else {
			// Action Unknown Component.
			$alert_options['Component'] = 'Unknown';
		}
		return $alert_options;
	}
}
