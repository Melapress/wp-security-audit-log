<?php
/**
 * Entity: Occurrences.
 *
 * User Sessions class.
 *
 * @package wsal
 */

declare(strict_types=1);

namespace WSAL\Entities;

use WSAL\Controllers\Alert;
use WSAL\Controllers\Connection;
use WSAL\Helpers\Settings_Helper;
use WSAL\Helpers\DateTime_Formatter_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Entities\Occurrences_Entity' ) ) {

	/**
	 * Responsible for the events occurrences.
	 */
	class Occurrences_Entity extends Abstract_Entity {

		/**
		 * Contains the table name.
		 *
		 * @var string
		 */
		protected static $table = 'wsal_occurrences';

		/**
		 * List of migrated metadata fields.
		 *
		 * @var string[]
		 */
		public static $migrated_meta = array(
			'ClientIP'         => 'client_ip',
			'Severity'         => 'severity',
			'Object'           => 'object',
			'EventType'        => 'event_type',
			'UserAgent'        => 'user_agent',
			'CurrentUserRoles' => 'user_roles',
			'Username'         => 'username',
			'CurrentUserID'    => 'user_id',
			'SessionID'        => 'session_id',
			'PostStatus'       => 'post_status',
			'PostType'         => 'post_type',
			'PostID'           => 'post_id',
		);

		/**
		 * Keeps the info about the columns of the table - name, type.
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		protected static $fields = array(
			'id'          => 'int',
			'site_id'     => 'int',
			'alert_id'    => 'int',
			'created_on'  => 'float',
			'client_ip'   => 'string',
			'severity'    => 'string',
			'object'      => 'string',
			'event_type'  => 'string',
			'user_agent'  => 'string',
			'user_roles'  => 'string',
			'username'    => 'string',
			'user_id'     => 'int',
			'session_id'  => 'string',
			'post_status' => 'string',
			'post_type'   => 'string',
			'post_id'     => 'int',
		);

		/**
		 * Holds all the default values for the columns.
		 *
		 * @var array
		 *
		 * @since 4.6.0
		 */
		protected static $fields_values = array(
			'id'          => 0,
			'site_id'     => 0,
			'alert_id'    => 0,
			'created_on'  => 0.0,
			'client_ip'   => '',
			'severity'    => '',
			'object'      => '',
			'event_type'  => '',
			'user_agent'  => '',
			'user_roles'  => '',
			'username'    => null,
			'user_id'     => null,
			'session_id'  => '',
			'post_status' => '',
			'post_type'   => '',
			'post_id'     => 0,
		);

		/**
		 * Builds an upgrade query for the occurrence table.
		 *
		 * @return string
		 */
		public static function get_upgrade_query() {
			return 'ALTER TABLE `' . self::get_table_name() . '`'
			. ' DROP COLUMN is_read, '
			. ' DROP COLUMN is_migrated, '
			. " ADD client_ip VARCHAR(255) NOT NULL DEFAULT '',"
			. ' ADD severity BIGINT NOT NULL DEFAULT 0,'
			. " ADD object VARCHAR(255) NOT NULL DEFAULT '',"
			. " ADD event_type VARCHAR(255) NOT NULL DEFAULT '',"
			. " ADD user_agent VARCHAR(255) NOT NULL DEFAULT '',"
			. " ADD user_roles VARCHAR(255) NOT NULL DEFAULT '',"
			. ' ADD username VARCHAR(255) NULL,'
			. ' ADD user_id BIGINT NULL ,'
			. " ADD session_id VARCHAR(255) NOT NULL DEFAULT '',"
			. " ADD post_status VARCHAR(255) NOT NULL DEFAULT '',"
			. " ADD post_type VARCHAR(255) NOT NULL DEFAULT '',"
			. ' ADD post_id BIGINT NOT NULL DEFAULT 0;';
		}

		/**
		 * Creates table functionality
		 *
		 * @param \wpdb $connection - \wpdn connection to be used for name extraction.
		 *
		 * @return bool
		 *
		 * @since 4.4.2.1
		 * @since 4.6.0 - Added $connection parameter
		 */
		public static function create_table( $connection = null ): bool {
			$collate = self::get_connection()->get_charset_collate();
			if ( null !== $connection ) {
				if ( $connection instanceof \wpdb ) {
					$collate = $connection->get_charset_collate();
				}
			}
			$table_name    = self::get_table_name( $connection );
			$wp_entity_sql = '
				CREATE TABLE `' . $table_name . '` (
					`id` bigint NOT NULL AUTO_INCREMENT,
					`site_id` bigint NOT NULL,
					`alert_id` bigint NOT NULL,
					`created_on` double NOT NULL,
					`client_ip` varchar(255) NOT NULL,
					`severity` varchar(255) NOT NULL,
					`object` varchar(255) NOT NULL,
					`event_type` varchar(255) NOT NULL,
					`user_agent` varchar(255) NOT NULL,
					`user_roles` varchar(255) NOT NULL,
					`username` varchar(255) DEFAULT NULL,
					`user_id` bigint DEFAULT NULL,
					`session_id` varchar(255) NOT NULL,
					`post_status` varchar(255) NOT NULL,
					`post_type` varchar(255) NOT NULL,
					`post_id` bigint NOT NULL,
				PRIMARY KEY (`id`),
				KEY `site_alert_created` (`site_id`,`alert_id`,`created_on`),
				KEY `created_on` (`created_on`)
				)
			  ' . $collate . ';';

			return self::maybe_create_table( $table_name, $wp_entity_sql, $connection );
		}

		/**
		 * Returns the column name for a given table
		 *
		 * @return array
		 *
		 * @since 4.5.0
		 */
		public static function get_column_names(): array {
			return array(
				'id'          => 'bigint',
				'site_id'     => 'bigint',
				'alert_id'    => 'bigint',
				'created_on'  => 'double',
				'client_ip'   => 'varchar(255)',
				'severity'    => 'varchar(255)',
				'object'      => 'varchar(255)',
				'event_type'  => 'varchar(255)',
				'user_agent'  => 'varchar(255)',
				'user_roles'  => 'varchar(255)',
				'username'    => 'varchar(255)',
				'user_id'     => 'bigint',
				'session_id'  => 'varchar(255)',
				'post_status' => 'varchar(255)',
				'post_type'   => 'varchar(255)',
				'post_id'     => 'bigint',
			);
		}

		/**
		 * Responsible for storing the information in both occurrences table and metadata table.
		 * That one is optimized for DB performance
		 *
		 * @param array $data - The data to be stored.
		 * @param int   $type - The event ID.
		 * @param float $date - Formatted to UNIX timestamp date.
		 * @param int   $site_id - The site ID to store data for.
		 *
		 * @return void
		 *
		 * @since 4.5.0
		 */
		public static function store_record( $data, $type, $date, $site_id ) {
			$data_to_store = array();
			foreach ( (array) $data as $name => $value ) {
				if ( '0' === $value || ! empty( $value ) ) {
					if ( isset( self::$migrated_meta[ $name ] ) ) {
						if ( 'CurrentUserRoles' === $name ) {
							$value = maybe_unserialize( $value );
							if ( is_array( $value ) && ! empty( $value ) ) {
								$data_to_store[ self::$migrated_meta[ $name ] ] = implode( ',', $value );
							}
						} else {
							$data_to_store[ self::$migrated_meta[ $name ] ] = $value;
						}

						unset( $data[ $name ] );
					}
				}
			}

			if ( ! empty( $data_to_store ) ) {
				$data_to_store['created_on'] = $date;
				$data_to_store['alert_id']   = $type;
				$data_to_store['site_id']    = ! is_null( $site_id ) ? $site_id : ( function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 0 );

				$occurrences_id = self::save( $data_to_store );

				if ( 0 !== $occurrences_id && ! empty( $data ) ) {
					$sqls = '';
					foreach ( (array) $data as $name => $value ) {
						$meta_insert = array(
							'occurrence_id' => $occurrences_id,
							'name'          => $name,
							'value'         => maybe_serialize( $value ),
						);

						$data_prepared = Metadata_Entity::prepare_data( $meta_insert );

						$fields  = '`' . implode( '`, `', array_keys( $data_prepared[0] ) ) . '`';
						$formats = implode( ', ', $data_prepared[1] );

						$sql = "($formats),";

						$sqls .= self::get_connection()->prepare( $sql, $data_prepared[0] );
					}

					if ( ! empty( $sqls ) ) {
						$sqls = 'INSERT INTO `' . Metadata_Entity::get_table_name() . "` ($fields) VALUES " . rtrim( $sqls, ',' );

						self::get_connection()->suppress_errors( true );
						self::get_connection()->query( $sqls );

						if ( '' !== self::get_connection()->last_error ) {
							if ( 1146 === Metadata_Entity::get_last_sql_error( self::get_connection() ) ) {
								if ( Metadata_Entity::create_table() ) {
									self::get_connection()->query( $sqls );
								}
							}
						}
						self::get_connection()->suppress_errors( false );
					}
				}
			}
		}

		/**
		 * Sets an index (if not there already)
		 *
		 * @param \wpdb $connection - \wpdb connection to be used for name extraction.
		 *
		 * @return void
		 *
		 * @since 4.5.1
		 * @since 4.6.0 - Added connection parameter
		 */
		public static function create_indexes( $connection = null ) {
			$index_exists  = false;
			$db_connection = self::get_connection();
			if ( null !== $connection ) {
				if ( $connection instanceof \wpdb ) {
					$db_connection = $connection;
				}
			}
			// check if an index exists.
			if ( $db_connection->query( 'SELECT COUNT(1) IndexIsThere FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema=DATABASE() AND table_name="' . self::get_table_name( $connection ) . '" AND index_name="created_on"' ) ) {
				// query succeeded, does index exist?
				$index_exists = ( isset( $db_connection->last_result[0]->IndexIsThere ) ) ? $db_connection->last_result[0]->IndexIsThere : false;
			}
			// if no index exists then make one.
			if ( ! $index_exists ) {
				$db_connection->query( 'CREATE INDEX created_on ON ' . self::get_table_name( $connection ) . ' (created_on)' );
			}
		}

		/**
		 * Gets alert message.
		 *
		 * @param array  $item    - Occurrence meta array.
		 * @param string $context Message context.
		 *
		 * @return string Full-formatted message.
		 * @see WSAL_Alert::get_message()
		 */
		public static function get_alert_message( $item = null, $context = 'default' ) {
			// $alert          = Alert_Manager::get_alert(
			// $item['alert_id'],
			// (object) array(
			// 'mesg' => esc_html__( 'Alert message not found.', 'wp-security-audit-log' ),
			// 'desc' => esc_html__( 'Alert description not found.', 'wp-security-audit-log' ),
			// )
			// );
			// $cached_message = $alert->mesg;

			// Fill variables in message.
			$meta_array = $item['meta_values'];
			// $alert_object = $alert;

			$message = Alert::get_message( $meta_array, null, $item['alert_id'], $item['id'], $context );
			if ( false === $message ) {
				$cached_message = Alert::get_original_alert_message( $item['alert_id'] );
			}
			if ( false !== $message ) {
				$cached_message = $message;
			} else {

				$cached_message = isset( $cached_message ) ? $cached_message : sprintf(
				/* Translators: 1: html that opens a link, 2: html that closes a link. */
					__( 'This type of activity / change is no longer monitored. You can create your own custom event IDs to keep a log of such change. Read more about custom events %1$shere%2$s.', 'wp-security-audit-log' ),
					'<a href="https://melapress.com/support/kb/create-custom-events-wordpress-activity-log/" rel="noopener noreferrer" target="_blank">',
					'</a>'
				);
			}
			return $cached_message;
		}

		/**
		 * Retrieves occurrences that have metadata that needs to be migrated to the occurrences table. This relates to the
		 * database schema change done in version 4.4.0.
		 *
		 * @param int $limit Limits the number of results.
		 *
		 * @return WSAL_Models_Occurrence[]
		 *
		 * @since 4.6.0
		 */
		public static function get_all_with_meta_to_migrate( $limit ) {

			$meta_keys = array_map(
				function ( $value ) {
					return '"' . $value . '"';
				},
				array_keys( self::$migrated_meta )
			);

			return self::load_multi_query(
				'SELECT o.* FROM `' . self::get_table_name() . '` o '
				. ' INNER JOIN `' . Metadata_Entity::get_table_name() . '` m '
				. ' ON m.occurrence_id = o.id '
				. ' WHERE m.name IN (' . implode( ',', $meta_keys ) . ') '
				. ' GROUP BY o.id '
				. ' ORDER BY created_on DESC '
				. ' LIMIT 0, %d;',
				array( $limit )
			);
		}

		/**
		 * Returns the value of a meta item.
		 *
		 * @param array  $data_collected - The array with all of the items, must contain at least the id of the occurrence.
		 * @param string $name    - Name of meta item.
		 * @param mixed  $default - Default value returned when meta does not exist.
		 *
		 * @return mixed The value, if meta item does not exist $default returned.
		 * @see WSAL_Adapters_MySQL_Occurrence::get_named_meta()
		 *
		 * @throws \Exception - if the id is not part of the $data_collected array.
		 *
		 * @since 4.6.0
		 */
		public static function get_meta_value( array $data_collected, $name, $default = array() ) {
			$result = $default;

			if ( ! isset( $data_collected['id'] ) ) {
				throw new \Exception( 'The id of the Occurrence is not present in the array', 1 );
			}

			// Check if the meta is part of the occurrences table.
			if ( in_array( $name, array_keys( self::$migrated_meta ), true ) ) {
				$property_name = self::$migrated_meta[ $name ];
				if ( isset( $data_collected[ $property_name ] ) ) {
					$result = $data_collected[ $property_name ];
				}
			} else {
				// Get meta adapter.
				$meta = Metadata_Entity::load_by_name_and_occurrence_id( $name, $data_collected['id'] );
				if ( is_null( $meta ) || ! array_key_exists( 'value', $meta ) ) {
					return $default;
				}

				$result = $meta['value'];
			}

			$result = maybe_unserialize( $result );
			if ( 'CurrentUserRoles' === $name && is_string( $result ) ) {
				$result = preg_replace( '/[\[\]"]/', '', $result );
				$result = explode( ',', $result );
			}

			return $result;
		}

		/**
		 * Works out the grouping data entities for given statistical report type.
		 *
		 * @param int    $statistics_report_type Statistical report type.
		 * @param string $grouping_period        Period to use for data grouping.
		 *
		 * @return string[]|null
		 * @since 4.4.0
		 */
		public static function get_grouping( $statistics_report_type, $grouping_period ) {
			$grouping = null;
			if ( ! is_null( $statistics_report_type ) ) {
				$grouping = array( 'site' );
				if ( ! is_null( $grouping_period ) ) {
					array_push( $grouping, $grouping_period );
				}

				switch ( $statistics_report_type ) {
					case \WSAL_Rep_Common::DIFFERENT_IP:
						array_push( $grouping, 'users' );
						array_push( $grouping, 'ips' );
						break;
					case \WSAL_Rep_Common::ALL_IPS:
						array_push( $grouping, 'ips' );
						break;
					case \WSAL_Rep_Common::LOGIN_ALL:
					case \WSAL_Rep_Common::LOGIN_BY_USER:
					case \WSAL_Rep_Common::LOGIN_BY_ROLE:
					case \WSAL_Rep_Common::PUBLISHED_ALL:
					case \WSAL_Rep_Common::PUBLISHED_BY_USER:
					case \WSAL_Rep_Common::PUBLISHED_BY_ROLE:
					case \WSAL_Rep_Common::ALL_USERS:
					case \WSAL_Rep_Common::VIEWS_BY_POST:
					case \WSAL_Rep_Common::PROFILE_CHANGES_ALL:
					case \WSAL_Rep_Common::PROFILE_CHANGES_BY_USER:
					case \WSAL_Rep_Common::PROFILE_CHANGES_BY_ROLE:
						array_push( $grouping, 'users' );
						break;

					case \WSAL_Rep_Common::PASSWORD_CHANGES:
						array_push( $grouping, 'users' );
						array_push( $grouping, 'events' );
						break;

					case \WSAL_Rep_Common::VIEWS_ALL:
						array_push( $grouping, 'posts' );
						break;

					case \WSAL_Rep_Common::VIEWS_BY_USER:
					case \WSAL_Rep_Common::VIEWS_BY_ROLE:
						array_push( $grouping, 'users' );
						array_push( $grouping, 'posts' );
						break;

				}
			}

			return $grouping;
		}

		/**
		 * Retrieves report data for generic (alerts based) report. Function used in WSAL reporting extension.
		 *
		 * @param WSAL_ReportArgs $report_args            Report arguments.
		 * @param int             $next_date              (Optional) Created on >.
		 * @param int             $limit                  (Optional) Limit.
		 * @param int             $statistics_report_type Statistics report type.
		 * @param string          $grouping_period        Period to use for data grouping.
		 *
		 * @return array Report results
		 */
		public static function get_report_data( $report_args, $next_date = null, $limit = 0, $statistics_report_type = null, $grouping_period = null ) {

			// Figure out the grouping statement and the columns' selection.
			$grouping = self::get_grouping( $statistics_report_type, $grouping_period );

			// The user grouping based on an additional meta field is only applicable to the password changes' statistical
			// report at the moment.
			$use_meta_field_for_user_grouping = \WSAL_Rep_Common::PASSWORD_CHANGES === $statistics_report_type;

			// Build the SQL query and runs it.
			$query = self::build_reporting_query( $report_args, false, $grouping, $next_date, $limit, $use_meta_field_for_user_grouping, $statistics_report_type );

			// Statistical reports expect data as array, regular reports use objects.
			$result_format = is_null( $statistics_report_type ) ? OBJECT : ARRAY_A;

			// Perform additional query needed for new role counts.
			if ( \WSAL_Rep_Common::NEW_USERS === $statistics_report_type ) {
				$occurrences = self::additional_new_user_query( $grouping, $next_date, $limit, $result_format );
			}

			$results = self::get_connection()->get_results( $query, $result_format );

			// Append role counts to results.
			if ( \WSAL_Rep_Common::NEW_USERS === $statistics_report_type && isset( $occurrences ) && ! empty( $occurrences ) ) {
				foreach ( $results as $result_key => $result_value ) {
					$role_counts                           = $occurrences[ $result_value['period'] ]['roles_counts'];
					$results[ $result_key ]['role_counts'] = $role_counts;
				}
			}

			if ( ! empty( $results ) ) {
				$last_item = end( $results );
				if ( is_object( $last_item ) && property_exists( $last_item, 'created_on' ) ) {
					$results['lastDate'] = $last_item->created_on;
				} elseif ( is_array( $last_item ) && array_key_exists( 'created_on', $last_item ) ) {
					$results['lastDate'] = $last_item['created_on'];
				}
			}

			return $results;
		}

		/**
		 * Builds an SQL query for the main report.
		 *
		 * @param WSAL_ReportArgs $report_args Report arguments.
		 * @param bool            $count_only  If true, the resulting query will only provide a count of matching entries
		 *                                     is
		 *                                     returned.
		 * @param array           $grouping    Grouping criteria. Drives the selected columns as well as the GROUP BY
		 *                                     statement. Only null value prevents grouping. Empty array means to group by
		 *                                     time period only.
		 * @param int             $next_date   (Optional) Created on >.
		 * @param int             $limit       (Optional) Limit.
		 * @param bool            $use_meta_field_for_user_grouping - Do we need to use meta field for grouping the users table?.
		 * @param int             $statistics_report_type Statistics report type.
		 * @param \wpdb           $connection - \wpdb connection to be used for name extraction.
		 *
		 * @return string
		 */
		private static function build_reporting_query( $report_args, $count_only, $grouping = null, $next_date = null, $limit = 0, $use_meta_field_for_user_grouping = false, $statistics_report_type = null, $connection = null ) {
			if ( null !== $connection ) {
				if ( $connection instanceof \wpdb ) {
					$_wpdb = $connection;
				}
			} else {
				$_wpdb = self::get_connection();
			}

			$table_occ = self::get_table_name( $_wpdb );

			$join_meta_table_for_user_grouping = false;
			if ( $count_only ) {
				$select_fields = array( 'COUNT(1) as count' );
				$group_by      = array( 'occ.id' );
			} elseif ( is_null( $grouping ) ) {
				$select_fields = array(
					'occ.id',
					'occ.alert_id',
					'occ.site_id',
					'occ.created_on',
					"replace( replace( replace( occ.user_roles, '[', ''), ']', ''), '\\'', '') AS roles",
					'occ.client_ip AS ip',
					'occ.user_agent AS ua',
					'COALESCE( occ.username, occ.user_id ) as user_id',
					'occ.object',
					'occ.event_type',
					'occ.post_id',
					'occ.post_type',
					'occ.post_status',
				);
			} else {
				$select_fields = array( 'occ.created_on' );
				$group_by      = array();
				foreach ( $grouping as $grouping_item ) {
					switch ( $grouping_item ) {
						case 'site':
							array_push( $select_fields, 'site_id' );
							array_push( $group_by, 'site_id' );
							break;
						case 'users':
							if ( $use_meta_field_for_user_grouping ) {
								array_push( $select_fields, 'COALESCE( m.value, occ.user_id, occ.username ) as user' );
								$join_meta_table_for_user_grouping = true;
							} else {
								// array_push( $select_fields, 'COALESCE( occ.user_id, occ.username ) as user' ); //.
								array_push( $select_fields, 'IF ( occ.user_id>0, occ.user_id, occ.username ) as user' );
							}

							if ( in_array( $statistics_report_type, range( 70, 72 ), true ) ) {
								array_push( $select_fields, 'GROUP_CONCAT(occ.alert_id) as events' );
							}

							array_push( $group_by, 'user' );
							break;
						case 'posts':
							array_push( $select_fields, 'post_id' );
							array_push( $group_by, 'post_id' );
							break;
						case 'events':
							array_push( $select_fields, 'alert_id' );
							array_push( $group_by, 'alert_id' );
							break;
						case 'day':
							array_push( $select_fields, 'DATE_FORMAT( FROM_UNIXTIME( occ.created_on ), "%Y-%m-%d" ) AS period' );
							array_push( $group_by, 'period' );
							break;
						case 'week':
							array_push( $select_fields, 'DATE_FORMAT( FROM_UNIXTIME( occ.created_on ), "%Y-%u" ) AS period' );
							array_push( $group_by, 'period' );
							break;
						case 'month':
							array_push( $select_fields, 'DATE_FORMAT( FROM_UNIXTIME( occ.created_on ), "%Y-%m" ) AS period' );
							array_push( $group_by, 'period' );
							break;
					}
				}

				array_push( $select_fields, 'COUNT(*) as count' );
			}

			$sql = 'SELECT ' . implode( ',', $select_fields ) . ' FROM ' . $table_occ . ' AS occ ';
			if ( $join_meta_table_for_user_grouping ) {
				$sql .= ' LEFT JOIN ' . Metadata_Entity::get_table_name( $_wpdb ) . ' AS m ON ( m.occurrence_id = occ.id AND m.name = "TargetUserId" ) ';
			}

			$sql .= self::build_where_statement( $report_args );

			if ( ! empty( $next_date ) ) {
				$sql .= ' AND occ.created_on < ' . $next_date;
			}

			if ( isset( $group_by ) && ! empty( $group_by ) ) {
				$sql          .= ' GROUP BY ' . implode( ',', $group_by );
				$orderby_parts = array_map(
					function ( $item ) {
						return 'period' === $item ? $item . ' DESC ' : $item . ' ASC ';
					},
					$group_by
				);

				$sql .= ' ORDER BY ' . implode( ',', $orderby_parts );
			} else {
				$sql .= ' ORDER BY created_on DESC ';
			}

			if ( ! empty( $limit ) ) {
				$sql .= " LIMIT {$limit}";
			}

			return $sql;
		}

		/**
		 * Generates SQL where statement based on given report args.
		 *
		 * @param WSAL_ReportArgs $report_args Report arguments.
		 *
		 * @return string
		 */
		private static function build_where_statement( $report_args ) {
			$_site_id                = null;
			$sites_negate_expression = '';
			if ( $report_args->site__in ) {
				$_site_id = self::format_array_for_query( $report_args->site__in );
			} elseif ( $report_args->site__not_in ) {
				$_site_id                = self::format_array_for_query( $report_args->site__not_in );
				$sites_negate_expression = 'NOT';
			}

			$_user_id                = null;
			$users_negate_expression = '';
			if ( $report_args->user__in ) {
				$_user_id = self::format_array_for_query( $report_args->user__in );
			} elseif ( $report_args->user__not_in ) {
				$_user_id                = self::format_array_for_query( $report_args->user__not_in );
				$users_negate_expression = 'NOT';
			}

			$user_names = self::get_user_names( $_user_id );

			$_role_name              = null;
			$roles_negate_expression = '';
			if ( $report_args->role__in ) {
				$_role_name = self::format_array_for_query_regex( $report_args->role__in );
			} elseif ( $report_args->role__not_in ) {
				$_role_name              = self::format_array_for_query_regex( $report_args->role__not_in );
				$roles_negate_expression = 'NOT';
			}

			$_alert_code                  = null;
			$alert_code_negate_expression = '';
			if ( $report_args->code__in ) {
				$_alert_code = self::format_array_for_query( $report_args->code__in );
			} elseif ( $report_args->code__not_in ) {
				$_alert_code                  = self::format_array_for_query( $report_args->code__not_in );
				$alert_code_negate_expression = 'NOT';
			}

			$_post_ids                  = null;
			$post_ids_negate_expression = '';
			if ( $report_args->post__in ) {
				$_post_ids = self::format_array_for_query( $report_args->post__in );
			} elseif ( $report_args->post__not_in ) {
				$_post_ids                  = self::format_array_for_query( $report_args->post__not_in );
				$post_ids_negate_expression = 'NOT';
			}

			$_post_types                  = null;
			$post_types_negate_expression = '';
			if ( $report_args->post_type__in ) {
				$_post_types = self::format_array_for_query( $report_args->post_type__in );
			} elseif ( $report_args->post_type__not_in ) {
				$_post_types                  = self::format_array_for_query( $report_args->post_type__not_in );
				$post_types_negate_expression = 'NOT';
			}

			$_post_statuses                  = null;
			$post_statuses_negate_expression = '';
			if ( $report_args->post_status__in ) {
				$_post_statuses = self::format_array_for_query( $report_args->post_status__in );
			} elseif ( $report_args->post_status__not_in ) {
				$_post_statuses                  = self::format_array_for_query( $report_args->post_status__not_in );
				$post_statuses_negate_expression = 'NOT';
			}

			$_ip_addresses                  = null;
			$ip_addresses_negate_expression = '';
			if ( $report_args->ip__in ) {
				$_ip_addresses = self::format_array_for_query( $report_args->ip__in );
			} elseif ( $report_args->ip__not_in ) {
				$_ip_addresses                  = self::format_array_for_query( $report_args->ip__not_in );
				$ip_addresses_negate_expression = 'NOT';
			}

			$_severities                  = null;
			$severities_negate_expression = '';
			if ( $report_args->severities__in ) {
				$_severities = self::format_array_for_query( $report_args->severities__in );
			} elseif ( $report_args->severities__not_in ) {
				$_severities                  = self::format_array_for_query( $report_args->severities__not_in );
				$severities_negate_expression = 'NOT';
			}

			$_objects                  = null;
			$objects_negate_expression = '';
			if ( $report_args->object__in ) {
				$_objects = self::format_array_for_query( $report_args->object__in );
			} elseif ( $report_args->object__not_in ) {
				$_objects                  = self::format_array_for_query( $report_args->object__not_in );
				$objects_negate_expression = 'NOT';
			}

			$_event_types                  = null;
			$event_types_negate_expression = '';
			if ( $report_args->type__in ) {
				$_event_types = self::format_array_for_query( $report_args->type__in );
			} elseif ( $report_args->type__not_in ) {
				$_event_types                  = self::format_array_for_query( $report_args->type__not_in );
				$event_types_negate_expression = 'NOT';
			}

			$_start_timestamp = null;
			if ( $report_args->start_date ) {
				$start_datetime   = \DateTime::createFromFormat( 'Y-m-d H:i:s', $report_args->start_date . ' 00:00:00' );
				$_start_timestamp = $start_datetime->format( 'U' ) + ( DateTime_Formatter_Helper::get_time_zone_offset() ) * -1;
			}

			$_end_timestamp = null;
			if ( $report_args->end_date ) {
				$end_datetime   = \DateTime::createFromFormat( 'Y-m-d H:i:s', $report_args->end_date . ' 23:59:59' );
				$_end_timestamp = $end_datetime->format( 'U' ) + ( DateTime_Formatter_Helper::get_time_zone_offset() ) * -1;
			}

			$users_condition_parts = array();
			if ( ! is_null( $_user_id ) ) {
				array_push( $users_condition_parts, " {$users_negate_expression} find_in_set( occ.user_id, $_user_id ) > 0 " );
			}

			if ( ! is_null( $user_names ) ) {
				array_push( $users_condition_parts, " {$users_negate_expression} replace( occ.username, '\"', '' ) IN ( $user_names ) " );
			}

			$where_statement = ' WHERE 1 = 1 ';

			if ( ! empty( $users_condition_parts ) ) {
				$where_statement .= ' AND ( ' . implode( 'OR', $users_condition_parts ) . ' ) ';
			}

			if ( ! is_null( $_site_id ) ) {
				$where_statement .= " AND {$sites_negate_expression} find_in_set( occ.site_id, {$_site_id} ) > 0 ";
			}

			if ( ! is_null( $_role_name ) ) {
				$where_statement .= " AND user_roles {$roles_negate_expression} REGEXP {$_role_name} ";
			}

			if ( ! is_null( $_ip_addresses ) ) {
				$where_statement .= " AND {$ip_addresses_negate_expression} find_in_set( occ.client_ip, {$_ip_addresses} ) > 0 ";
			}

			if ( ! is_null( $_severities ) ) {
				$where_statement .= " AND {$severities_negate_expression} find_in_set( occ.severity, {$_severities} ) > 0 ";
			}

			if ( ! is_null( $_objects ) ) {
				$where_statement .= " AND {$objects_negate_expression} find_in_set( occ.object, {$_objects} ) > 0 ";
			}

			if ( ! is_null( $_event_types ) ) {
				$where_statement .= " AND {$event_types_negate_expression} find_in_set( occ.event_type, {$_event_types} ) > 0 ";
			}

			if ( ! is_null( $_alert_code ) ) {
				$where_statement .= " AND {$alert_code_negate_expression} find_in_set( occ.alert_id, {$_alert_code} ) > 0 ";
			}

			if ( ! is_null( $_post_ids ) ) {
				$where_statement .= " AND {$post_ids_negate_expression} find_in_set( occ.post_id, {$_post_ids} ) > 0 ";
			}

			if ( ! is_null( $_post_statuses ) ) {
				$where_statement .= " AND {$post_statuses_negate_expression} find_in_set( occ.post_status, {$_post_statuses} ) > 0 ";
			}

			if ( ! is_null( $_post_types ) ) {
				$where_statement .= " AND {$post_types_negate_expression} find_in_set( occ.post_type, {$_post_types} ) > 0 ";
			}

			if ( ! is_null( $_start_timestamp ) ) {
				$where_statement .= " AND occ.created_on >= {$_start_timestamp} ";
			}

			if ( ! is_null( $_end_timestamp ) ) {
				$where_statement .= " AND occ.created_on <= {$_end_timestamp} ";
			}

			return $where_statement;
		}

		/**
		 * Formats array for use in SQL query.
		 *
		 * @param array $data Data to format.
		 *
		 * @return string
		 * @since 4.3.2
		 */
		protected static function format_array_for_query( $data ) {
			return "'" . implode( ',', $data ) . "'";
		}

		/**
		 * Get Users user_login.
		 *
		 * @param int $user_id - User ID.
		 *
		 * @return string comma separated users login
		 */
		private static function get_user_names( $user_id ) {
			global $wpdb;

			$user_names = null;
			if ( ! empty( $user_id ) && 'null' !== $user_id && ! is_null( $user_id ) ) {
				$sql = 'SELECT user_login FROM ' . $wpdb->users . ' WHERE find_in_set(ID, @userId) > 0';
				$wpdb->query( "SET @userId = $user_id" ); // phpcs:ignore
				$result      = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore
				$users_array = array();
				foreach ( $result as $item ) {
					$users_array[] = '"' . $item['user_login'] . '"';
				}
				$user_names = implode( ', ', $users_array );
			}

			return $user_names;
		}

		/**
		 * Formats data as SQL query regex.
		 *
		 * @param array $data Data to format.
		 *
		 * @return string
		 * @since 4.3.2
		 */
		protected static function format_array_for_query_regex( $data ) {
			$result = array();
			foreach ( $data as $item ) {
				array_push( $result, esc_sql( preg_quote( $item ) ) ); // phpcs:ignore
			}

			return "'" . implode( '|', $result ) . "'";
		}

		/**
		 * Determine the roles for newly created users, which is then appended to the report result.
		 *
		 * @param array  $grouping Period to use for data grouping.
		 * @param int    $next_date Created on >.
		 * @param int    $limit Limit.
		 * @param string $result_format Required format.
		 * @return array
		 */
		private static function additional_new_user_query( $grouping, $next_date, $limit, $result_format ) {
			$table_occ        = self::get_table_name();
			$table_meta       = Metadata_Entity::get_table_name();
			$occurrences      = array();
			$select_fields    = array(
				'site_id',
				'id',
			);
			$group_by_columns = array(
				'site_id',
			);
			foreach ( $grouping as $grouping_item ) {
				switch ( $grouping_item ) {
					case 'day':
						array_push( $select_fields, 'DATE_FORMAT( FROM_UNIXTIME( created_on ), "%Y-%m-%d" ) AS period' );
						array_unshift( $group_by_columns, 'period' );
						break;
					case 'week':
						array_push( $select_fields, 'DATE_FORMAT( FROM_UNIXTIME( created_on ), "%Y-%u" ) AS period' );
						array_unshift( $group_by_columns, 'period' );
						break;
					case 'month':
						array_push( $select_fields, 'DATE_FORMAT( FROM_UNIXTIME( created_on ), "%Y-%m" ) AS period' );
						array_unshift( $group_by_columns, 'period' );
						break;
				}
			}

			$user_query       = 'SELECT ' . implode( ',', $select_fields ) . ' FROM ' . $table_occ . ' AS occ WHERE find_in_set( occ.alert_id, "4000,4001" ) > 0 ';
			$occurrence_query = 'SELECT occ.id FROM ' . $table_occ . ' AS occ WHERE find_in_set( occ.alert_id, "4000,4001" ) > 0 ';

			if ( ! empty( $next_date ) ) {
				$user_query .= ' AND ' . $table_occ . '.created_on < ' . $next_date;
			}

			$user_query .= ' ORDER BY created_on DESC ';

			if ( ! empty( $limit ) ) {
				$user_query .= " LIMIT {$limit}";
			}

			// Get occurrences so we can reference the data.
			$user_results = self::get_connection()->get_results( $user_query, $result_format );

			// Get a list of registered roles for columns.
			$known_roles = get_editable_roles();

			// Strip any values, these will be replaced below.
			$known_roles_array = array_fill_keys( array_keys( $known_roles ), ' ' );

			foreach ( $user_results as $key => $item ) {
				$occurrences[ $item['period'] ]['roles_arr'] = empty( $occurrences[ $item['period'] ]['roles_arr'] ) ? array() : $occurrences[ $item['period'] ]['roles_arr'];
				$lookup_id                                   = $item['id'];
				// Locate role from possible meta tables rows.
				$roles     = self::get_connection()->get_results( 'SELECT value FROM ' . $table_meta . ' metatable WHERE occurrence_id ="' . $lookup_id . '" AND ( name = "NewUserData" OR name = "NewUserID" )', ARRAY_A );
				$roles_obj = isset( $roles[0]['value'] ) ? maybe_unserialize( $roles[0]['value'] ) : false;
				if ( isset( $roles->Roles ) ) {
					$item['roles'] = $roles->Roles;
				} else {
					$user          = get_userdata( intval( $roles_obj ) );
					$item['roles'] = $user->roles[0];
				}
				array_push( $occurrences[ $item['period'] ]['roles_arr'], $item['roles'] );
				$occurrences[ $item['period'] ][ $key ]         = $item;
				$occurrences[ $item['period'] ]['roles_counts'] = array_merge( $known_roles_array, array_count_values( $occurrences[ $item['period'] ]['roles_arr'] ) );
			}

			return $occurrences;
		}

		/**
		 * Function used in WSAL reporting extension.
		 * Check if criteria are matching in the DB.
		 *
		 * @param WSAL_ReportArgs $report_args - Query conditions.
		 * @param \wpdb           $connection - \wpdb connection to be used for name extraction.
		 *
		 * @return int Count of distinct values.
		 *
		 * @since 4.6.0
		 */
		public static function check_match_report_criteria( $report_args, $connection = null ) {
			if ( null !== $connection ) {
				if ( $connection instanceof \wpdb ) {
					$_wpdb = $connection;
				}
			} else {
				$_wpdb = self::get_connection();
			}
			$query = self::build_reporting_query( $report_args, true, \null, \null, 0, false, \null, $_wpdb );

			return (int) $_wpdb->get_var( $query );
		}

		/**
		 * Retrieves report data for IP address based reports. Function is used in WSAL reporting extension.
		 *
		 * @param WSAL_ReportArgs $report_args            Report arguments.
		 * @param int             $limit                  (Optional) Limit.
		 * @param int             $statistics_report_type Statistics report type.
		 * @param string          $grouping_period        Period to use for data grouping.
		 *
		 * @return array Raw report results as objects. Content depends on the report type.
		 */
		public static function get_ip_address_report_data( $report_args, $limit = 0, $statistics_report_type = null, $grouping_period = null ) {
			global $wpdb;
			$_wpdb = self::get_connection();

			// Tables.
			$table_occ = self::get_table_name();

			$table_users = $wpdb->users;

			// Figure out the grouping statement and the columns' selection.
			$grouping = self::get_grouping( $statistics_report_type, $grouping_period );

			// Figure out the selected columns and group by statement.
			$group_by_columns = array(
				'site_id',
			);

			if ( in_array( 'users', $grouping, true ) ) {
				array_push( $group_by_columns, 'username' );
			}

			if ( in_array( 'ips', $grouping, true ) ) {
				array_push( $group_by_columns, 'client_ip' );
			}

			if ( in_array( 'events', $grouping, true ) ) {
				array_push( $group_by_columns, 'alert_id' );
			}

			$select_fields = $group_by_columns;
			foreach ( $grouping as $grouping_item ) {
				switch ( $grouping_item ) {
					case 'day':
						array_unshift( $select_fields, 'DATE_FORMAT( FROM_UNIXTIME( created_on ), "%Y-%m-%d" ) AS period' );
						array_unshift( $group_by_columns, 'period' );
						break;
					case 'week':
						array_unshift( $select_fields, 'DATE_FORMAT( FROM_UNIXTIME( created_on ), "%Y-%u" ) AS period' );
						array_unshift( $group_by_columns, 'period' );
						break;
					case 'month':
						array_unshift( $select_fields, 'DATE_FORMAT( FROM_UNIXTIME( created_on ), "%Y-%m" ) AS period' );
						array_unshift( $group_by_columns, 'period' );
						break;
				}
			}

			$where_statement = self::build_where_statement( $report_args );

			$sql = '
			SELECT ' . implode( ',', $select_fields ) . " FROM (
			    SELECT occ.created_on, occ.site_id, occ.username, occ.client_ip 
				FROM $table_occ AS occ
				{$where_statement}
				HAVING username IS NOT NULL AND username NOT IN ( 'Unregistered user', 'Plugins', 'Plugin')
			UNION ALL
				SELECT occ.created_on, occ.site_id, u.user_login as username, occ.client_ip 
				FROM $table_occ AS occ
				JOIN $table_users AS u ON u.ID = occ.user_id  
				{$where_statement}
			HAVING username IS NOT NULL AND username NOT IN ( 'Unregistered user', 'Plugins', 'Plugin')
        ) ip_logins
		GROUP BY " . implode( ',', $group_by_columns );

			$orderby_parts = array_map(
				function ( $item ) {
					return 'period' === $item ? $item . ' DESC ' : $item . ' ASC ';
				},
				$group_by_columns
			);

			$sql .= ' ORDER BY ' . implode( ',', $orderby_parts );

			if ( ! empty( $limit ) ) {
				$sql .= " LIMIT {$limit}";
			}

			$results = $_wpdb->get_results( $sql, ARRAY_A );
			if ( is_array( $results ) && ! empty( $results ) ) {
				return $results;
			}

			return array();
		}

		/**
		 * Saves the given data into the table
		 * The data should be in following format:
		 * field name => value
		 *
		 * It checks the given data array against the table fields and determines the types based on that, it stores the values in the table then.
		 *
		 * @param array $data - The data to be saved (check above about the format).
		 *
		 * @return int
		 *
		 * @since 4.6.0
		 */
		public static function save( $data ) {

			// Use today's date if not set up.
			if ( ! isset( $data['created_on'] ) ) {
				$data['created_on'] = microtime( true );

			}

			return parent::save( $data );
		}

		/**
		 * Returns a key-value pair of metadata.
		 *
		 * @param int   $occurrence_id - The ID of the occurrence to extract data from.
		 * @param array $record_data -  Array with the already prepared event data.
		 * @param \wpdb $connection - \wpdb connection to be used for name extraction.
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		public static function get_meta_array( int $occurrence_id, array $record_data = array(), $connection = null ) {
			$result = array();

			if ( null !== $connection ) {
				if ( $connection instanceof \wpdb ) {
					$_wpdb = $connection;
				}
			} else {
				$_wpdb = self::get_connection();
			}

			$metas = Metadata_Entity::load_array( 'occurrence_id = %d', array( $occurrence_id ), $_wpdb );
			foreach ( $metas as $meta ) {
				$result[ $meta['name'] ] = maybe_unserialize( $meta['value'] );
			}

			if ( empty( $record_data ) ) {
				$record_data = self::load_array( 'id = %d', array( $occurrence_id ) );
				$record_data = \reset( $record_data );
			}

			if ( isset( $record_data ) && ! empty( $record_data ) ) {
				foreach ( self::$migrated_meta as $meta_key => $column_name ) {
					$result[ $meta_key ] = $record_data[ $column_name ];
				}
			}

			return $result;
		}

		/**
		 * Returns a key-value pair of metadata.
		 *
		 * @param array $events -  Array with all the events to extract meta data for.
		 * @param \wpdb $connection - \wpdb connection to be used for name extraction.
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		public static function get_multi_meta_array( array &$events, $connection = null ) {

			$event_ids = array();

			foreach ( $events as &$event ) {
				if ( ! isset( $event['id'] ) ) {
					$events = array();
					return $events;
				}
				$event_ids[] = (int) $event['id'];
			}
			unset( $event );

			$events = \array_combine( $event_ids, $events );

			if ( null !== $connection ) {
				if ( $connection instanceof \wpdb ) {
					$_wpdb = $connection;
				}
			} else {
				$_wpdb = self::get_connection();
			}

			$metas = Metadata_Entity::load_by_occurrences_ids( $event_ids, $_wpdb );

			foreach ( $metas as $meta ) {
				$events[ $meta['occurrence_id'] ]['meta_values'][ $meta['name'] ] = maybe_unserialize( $meta['value'] );
			}

			foreach ( $events as &$event ) {
				foreach ( self::$migrated_meta as $meta_key => $column_name ) {
					$event['meta_values'][ $meta_key ] = $event[ $column_name ];
				}
			}
			unset( $event );

			return $events;
		}

		/**
		 * Get distinct values of IPs.
		 *
		 * @param int $limit - (Optional) Limit.
		 *
		 * @return array - Distinct values of IPs.
		 *
		 * @since 4.6.0
		 */
		public static function get_matching_ips( $limit = null ) {
			$_wpdb = self::get_connection();
			$sql   = 'SELECT DISTINCT client_ip FROM ' . self::get_table_name();
			if ( ! is_null( $limit ) ) {
				$sql .= ' LIMIT ' . $limit;
			}
			$ips    = $_wpdb->get_col( $sql );
			$result = array();
			foreach ( $ips as $ip ) {
				if ( 0 === strlen( trim( (string) $ip ) ) ) {
					continue;
				}
				array_push( $result, $ip );
			}

			$_wpdb = null;

			return array_unique( $result );
		}

		/**
		 * Collects and prepares all the metadata for the given array of the events.
		 *
		 * @param array $results - Array with all the data with events collected.
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		public static function prepare_with_meta_data( array &$results ): array {
			$prepared_array = array();

			$table_name      = self::get_table_name();
			$meta_table_name = Metadata_Entity::get_table_name();

			if ( is_array( reset( $results[0] ) ) || ( is_array( $results[0] ) && ! empty( $results[0] ) && ! isset( $results[0][0] ) ) ) {
				foreach ( $results as $result_row ) {
					if ( ! isset( $prepared_array[ $result_row[ $table_name . 'id' ] ] ) ) {
						foreach ( array_keys( self::$fields ) as $field ) {
							$prepared_array[ $result_row[ $table_name . 'id' ] ][ $field ] = $result_row[ $table_name . $field ];

							$prepared_array[ $result_row[ $table_name . 'id' ] ][ $field ] = self::cast_to_correct_type( $field, $prepared_array[ $result_row[ $table_name . 'id' ] ][ $field ] );
						}

						foreach ( self::$migrated_meta as $name => $new_name ) {
							$prepared_array[ $result_row[ $table_name . 'id' ] ]['meta_values'][ $name ] = \maybe_unserialize( $result_row[ $table_name . $new_name ] );
						}
					}

					$prepared_array[ $result_row[ $table_name . 'id' ] ]['meta_values'][ $result_row[ $meta_table_name . 'name' ] ] = \maybe_unserialize( $result_row[ $meta_table_name . 'value' ] );
				}
			}

			return $prepared_array;
		}
	}
}
