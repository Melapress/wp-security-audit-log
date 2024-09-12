<?php
/**
 * Custom Sensors for Redirection
 * Class file for alert manager.
 *
 * @since 5.1.0
 *
 * @package Wsal
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors;

use WSAL\Controllers\Alert_Manager;
use WSAL\WP_Sensors\Helpers\Redirection_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Plugin_Sensors\Redirection_Sensor' ) ) {
	/**
	 * Custom sensor for Redirection plugin.
	 *
	 * @since 5.1.0
	 */
	class Redirection_Sensor {

		/**
		 * The redirect item - holds object data.
		 *
		 * @var \Red_Item
		 *
		 * @since 5.1.0
		 */
		private static $old_redirection_object = false;

		/**
		 * The redirect items - for bulk actions like delete we have to hold and array with data about the object which are about to be deleted.
		 *
		 * @var array
		 *
		 * @since 5.1.0
		 */
		private static $old_redirection_objects = array();

		/**
		 * Holds the updated id of the redirect item
		 *
		 * @var int
		 *
		 * @since 5.1.0
		 */
		private static $redirect_id = null;

		/**
		 * Sets the ID of the redirection which is about to be updated.
		 *
		 * @param int $updated_id - The ID of the redirection.
		 *
		 * @return void
		 *
		 * @since 5.1.0
		 */
		public static function set_updated_id( int $updated_id ) {
			self::$redirect_id = $updated_id;
		}

		/**
		 * Sets the old (before update) redirection object.
		 *
		 * @param \Red_Item $item - The old redirection object.
		 *
		 * @return void
		 *
		 * @since 5.1.0
		 */
		public static function set_redirect_old_object( \Red_Item $item ) {
			self::$old_redirection_object = $item;
		}

		/**
		 * Sets the old (before update) redirection object into the class array.
		 *
		 * @param int       $id - The ID of the redirection.
		 * @param \Red_Item $item - The old redirection object.
		 *
		 * @return void
		 *
		 * @since 5.1.0
		 */
		public static function add_redirect_old_object( int $id, \Red_Item $item ) {
			self::$old_redirection_objects[ $id ] = $item;
		}

		/**
		 * Sets the old (before update) redirection object into the class array.
		 *
		 * @return array
		 *
		 * @since 5.1.0
		 */
		public static function get_redirect_old_objects(): array {
			return self::$old_redirection_objects;
		}

		/**
		 * Loads all the plugin dependencies early.
		 *
		 * @return void
		 *
		 * @since 5.1.0
		 */
		public static function early_init() {

			if ( Redirection_Helper::is_redirection_active() ) {
				$self = __CLASS__;

				\add_filter(
					'wsal_event_objects',
					array( Redirection_Helper::class, 'wsal_redirection_add_custom_event_objects' ),
					10,
					2
				);

				\add_filter(
					'wsal_event_type_data',
					array( Redirection_Helper::class, 'wsal_redirection_add_custom_event_type' ),
					10,
					2
				);

				/**
				 * Logic here is complicated because Redirection plugin (for whatever reason) doesn't provide the ID of the updated item.
				 * In order to collect that we have to do our own magic or as follows:
				 * - Attach to the REST API
				 * - Call us when the update endpoint is called
				 * - Collect the ID
				 * - Attach to the update hook from the Redirection plugin
				 * - Collect object (current) to compare against
				 */
				\add_action(
					'rest_dispatch_request',
					function ( $first, $request, $route, $handler ) use ( &$self ) {

						if ( ! is_array( $handler['callback'] ) ) {
							return;
						}

						// Redirection REST is called - collecting data - start.
						if ( isset( $handler['callback'] ) &&
						isset( $handler['callback'][0] ) &&
						isset( $handler['callback'][1] ) &&
						is_a( $handler['callback'][0], '\Redirection_Api_Redirect' ) &&
						'route_update' === $handler['callback'][1]
						) {
							$params = $request->get_params();

							$self::set_updated_id( intval( $params['id'], 10 ) );
						}

						if ( isset( $handler['callback'] ) &&
						isset( $handler['callback'][0] ) &&
						isset( $handler['callback'][1] ) &&
						is_a( $handler['callback'][0], '\Redirection_Api_Redirect' ) &&
						'route_bulk' === $handler['callback'][1]
						) {
							$params = $request->get_params();
							$action = sanitize_text_field( $request['bulk'] );

							if ( 'delete' === $action ) {

								foreach ( $params['items'] as $item ) {
									$self::add_redirect_old_object( intval( $item, 10 ), \Red_Item::get_by_id( $item ) );
								}

								\add_action(
									'rest_request_after_callbacks',
									function ( $response, $handler, $request ) use ( &$self ) {
										$alert_id = 10508;

										$params = $request->get_params();
										if ( isset( $params['items'] ) && ! empty( $params['items'] ) ) {
											foreach ( $params['items'] as $redirect_id ) {
												$redirect = self::get_redirect_old_objects()[ $redirect_id ];
												if ( false === $redirect ) {
													return $response;
												}

												$variables = self::set_default_redirection_array_values( $redirect );

												Alert_Manager::trigger_event( $alert_id, $variables );
											}
										}

										return $response;
									},
									PHP_INT_MAX,
									3
								);
							} elseif ( 'disable' === $action ) {

								\add_action(
									'rest_request_after_callbacks',
									function ( $response, $handler, $request ) use ( &$self ) {
										$alert_id = 10503;

										$params = $request->get_params();
										if ( isset( $params['items'] ) && ! empty( $params['items'] ) ) {
											foreach ( $params['items'] as $redirect_id ) {
												$redirect = \Red_Item::get_by_id( intval( $redirect_id, 10 ) );
												if ( false === $redirect ) {
													return $response;
												}

												$variables = self::set_default_redirection_array_values( $redirect );

												Alert_Manager::trigger_event( $alert_id, $variables );
											}
										}

										return $response;
									},
									PHP_INT_MAX,
									3
								);
							} elseif ( 'enable' === $action ) {
								\add_action(
									'rest_request_after_callbacks',
									function ( $response, $handler, $request ) use ( &$self ) {
										$alert_id = 10502;

										$params = $request->get_params();
										if ( isset( $params['items'] ) && ! empty( $params['items'] ) ) {
											foreach ( $params['items'] as $redirect_id ) {
												$redirect = \Red_Item::get_by_id( intval( $redirect_id, 10 ) );
												if ( false === $redirect ) {
													return $response;
												}

												$variables = self::set_default_redirection_array_values( $redirect );

												Alert_Manager::trigger_event( $alert_id, $variables );
											}
										}

										return $response;
									},
									PHP_INT_MAX,
									3
								);
							} elseif ( 'reset' === $action ) {
								\add_action(
									'rest_request_after_callbacks',
									function ( $response, $handler, $request ) use ( &$self ) {
										$alert_id = 10504;

										$params = $request->get_params();
										if ( isset( $params['items'] ) && ! empty( $params['items'] ) ) {
											foreach ( $params['items'] as $redirect_id ) {
												$redirect = \Red_Item::get_by_id( intval( $redirect_id, 10 ) );
												if ( false === $redirect ) {
													return $response;
												}

												$variables = self::set_default_redirection_array_values( $redirect );

												Alert_Manager::trigger_event( $alert_id, $variables );
											}
										}

										return $response;
									},
									PHP_INT_MAX,
									3
								);
							}
						}
						// Redirection REST is called - collecting data - end.

						// Redirection Group REST is called - collecting data - start.

						/*
						That is fot future use (groups part) this version focus is redirection
						if ( isset( $handler['callback'] ) &&
						isset( $handler['callback'][0] ) &&
						isset( $handler['callback'][1] ) &&
						is_a( $handler['callback'][0], '\Redirection_Api_Group' ) &&
						'route_create' === $handler['callback'][1]
						) {
							\add_action(
								'rest_request_after_callbacks',
								function ( $response, $handler, $request ) use ( &$self ) {
									global $wpdb;

									$created_group_id = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(id) FROM {$wpdb->prefix}redirection_groups LIMIT %d", 1 ) );

									$group = \Red_Group::get( $created_group_id );

									$variables = $self::set_default_redirection_group_array_values( $group );

									$alert_id = 10509;
									Alert_Manager::trigger_event( $alert_id, $variables );

									return $response;
								},
								PHP_INT_MAX,
								3
							);
						}
						Groups part end.
						*/
						// Redirection Group REST is called - collecting data - start.

						return $first;
					},
					PHP_INT_MAX,
					4
				);

					\add_action(
						'redirection_update_redirect',
						function ( $data ) use ( &$self ) {

							if ( isset( self::$redirect_id ) ) {
								$self::set_redirect_old_object( \Red_Item::get_by_id( self::$redirect_id ) );
							}

							return $data;
						},
						PHP_INT_MAX,
						1
					);

					/**
					 * Redirection plugin is stupidly organized - when redirection is created or updated it fires this hook. The difference is that when redirection is created, first parameter is int, when it is updated it will be of type \Red_Item
					 */
					\add_action( 'redirection_redirect_updated', array( __CLASS__, 'create_update_redirect' ), 10, 2 );
			}
		}

		/**
		 * Log creation / modification request
		 *
		 * @param int|\Red_Item $obj - Info about the object - the only usage for that is to just separate creation from midification, as this is the way Redirection plugin works.
		 * @param \Red_Item     $data - Object filled with information about the redirection.
		 *
		 * @return \Red_Item
		 *
		 * @since 5.1.0
		 */
		public static function create_update_redirect( $obj, $data ) {

			if ( \is_a( $obj, '\Red_Item' ) ) {
				$alert_id = 10505;

				$variables = self::set_default_redirection_array_values( $data );

				$group = \Red_Group::get( self::$old_redirection_object->get_group_id() );

				$old_variables = array(
					'OldStatus'       => ( self::$old_redirection_object->is_enabled() ) ? \__( 'Activated', 'wp-security-audit-log' ) : \__( 'Deactivated', 'wp-security-audit-log' ),
					'OldSourceURL'    => self::$old_redirection_object->get_match_url(),
					'OldTargetURL'    => self::$old_redirection_object->get_action_data(),
					'GroupTitle'      => $group->get_name(),
					'old_match_data'  => self::$old_redirection_object->get_match_data(),
					'old_regex'       => self::$old_redirection_object->is_regex(),
					'old_action_data' => self::$old_redirection_object->get_action_data(),
					'old_action_type' => self::$old_redirection_object->get_action_type(),
					'old_match_type'  => self::$old_redirection_object->get_match_type(),
					'old_title'       => self::$old_redirection_object->get_title(),
					'old_position'    => self::$old_redirection_object->get_position(),
					'old_group_id'    => self::$old_redirection_object->get_group_id(),
				);

				$variables = \array_merge( $variables, $old_variables );

				Alert_Manager::trigger_event( $alert_id, $variables );
			}
			if ( false !== \filter_var( $obj, FILTER_VALIDATE_INT ) ) {
				$alert_id = 10501;

				$variables = self::set_default_redirection_array_values( $data );

				Alert_Manager::trigger_event( $alert_id, $variables );
			}

			return $data;
		}

		/**
		 * Builds link for the redirection
		 *
		 * @return string
		 *
		 * @since 5.1.0
		 */
		public static function get_redirection_link(): string {
			return \add_query_arg( 'page', 'redirection.php', \network_admin_url( 'tools.php' ) );
		}

		/**
		 * Builds link for the redirection groups
		 *
		 * @return string
		 *
		 * @since 5.1.0
		 */
		public static function get_redirection_group_link(): string {
			return \add_query_arg(
				array(
					'page' => 'redirection.php',
					'sub'  => 'groups',
				),
				\network_admin_url( 'tools.php' )
			);
		}

		/**
		 * Populates Alert array with the defaults for the given redirection
		 *
		 * @param \Red_Item $data - The redirection object.
		 *
		 * @return array
		 *
		 * @since 5.1.0
		 */
		private static function set_default_redirection_array_values( \Red_Item $data ): array {
			$group = \Red_Group::get( $data->get_group_id() );

			$variables = array(
				'Status'      => ( $data->is_enabled() ) ? \__( 'Activated', 'wp-security-audit-log' ) : \__( 'Deactivated', 'wp-security-audit-log' ),
				'SourceURL'   => $data->get_match_url(),
				'TargetURL'   => $data->get_action_data(),
				'ID'          => $data->get_id(),
				'GroupTitle'  => $group->get_name(),
				'match_data'  => $data->get_match_data(),
				'regex'       => $data->is_regex(),
				'action_data' => $data->get_action_data(),
				'action_type' => $data->get_action_type(),
				'match_type'  => $data->get_match_type(),
				'title'       => $data->get_title(),
				'last_access' => $data->get_last_hit() > 0 ? date_i18n( get_option( 'date_format' ), $data->get_last_hit() ) : '-',
				'last_count'  => $data->get_hits(),
				'position'    => $data->get_position(),
				'group_id'    => $data->get_group_id(),
				'EditorLink'  => self::get_redirection_link(),
			);

			return $variables;
		}

		/**
		 * Populates Alert array with the defaults for the given redirection group
		 *
		 * @param \Red_Group $data - The redirection group object.
		 *
		 * @return array
		 *
		 * @since 5.1.0
		 */
		private static function set_default_redirection_group_array_values( \Red_Group $data ): array {
			$module    = \Red_Module::get( $data->get_module_id() );
			$variables = array(
				'Status'      => ( $data->is_enabled() ) ? \__( 'Activated', 'wp-security-audit-log' ) : \__( 'Deactivated', 'wp-security-audit-log' ),
				'ID'          => $data->get_id(),
				'GroupTitle'  => $data->get_name(),
				'ModuleID'    => $data->get_module_id(),
				'ModuleTitle' => $module->get_name(),
				'EditorLink'  => self::get_redirection_group_link(),
			);

			return $variables;
		}
	}
}
