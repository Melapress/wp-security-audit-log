<?php
/**
 * Custom sensor for Paid Memberships Pro
 *
 * Class file for alert manager.
 *
 * @since 5.5.2
 *
 * @package wsal
 * @subpackage wsal-paid-memberships-pro
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors;

use WSAL\Controllers\Alert_Manager;
use WSAL\WP_Sensors\Helpers\Paid_Memberships_Pro_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Plugin_Sensors\Paid_Memberships_Pro_Sensor' ) ) {
	/**
	 * Custom sensor for Paid Memberships Pro
	 *
	 * @since 5.5.2
	 */
	class Paid_Memberships_Pro_Sensor {
		/**
		 * Init sensors
		 *
		 * @since 5.5.2
		 */
		public static function init() {

			if ( Paid_Memberships_Pro_Helper::is_pmp_active() ) {

				/**
				 * Members/Users events.
				 */
				add_action( 'pmpro_after_change_membership_level', array( __CLASS__, 'pmp_membership_assigned_to_user' ), 10, 2 );
				add_action( 'pmpro_after_change_membership_level', array( __CLASS__, 'pmp_membership_removed_from_user' ), 10, 3 );

				/**
				 * Changes to membership levels.
				 */
				add_action( 'pmpro_save_membership_level', array( __CLASS__, 'pmp_created_membership_level_event' ), 10 );
				add_action( 'pmpro_delete_membership_level', array( __CLASS__, 'pmp_deleted_membership_level_event' ), 10 );


			}
		}

		/**
		 * Loads all the plugin dependencies early.
		 *
		 * @return void
		 *
		 * @since 5.5.2
		 */
		public static function early_init() {
			if ( Paid_Memberships_Pro_Helper::is_pmp_active() ) {
				\add_filter(
					'wsal_event_objects',
					array( Paid_Memberships_Pro_Helper::class, 'wsal_paid_memberships_pro_add_custom_event_objects' ),
					10,
					2
				);

				if ( Paid_Memberships_Pro_Helper::is_pmp_active() ) {
					\add_filter(
						'wsal_format_custom_meta',
						array( Paid_Memberships_Pro_Helper::class, 'wsal_pmp_format_membership_changes' ),
						10,
						4
					);
				}


			}
		}

		/**
		 * Trigger event when a membership level is created.
		 *
		 * @param int $save_id The membership level ID that is saved/created.
		 *
		 * @since 5.5.2
		 */
		public static function pmp_created_membership_level_event( $save_id ) {

			if ( ! isset( $_REQUEST['pmpro_membershiplevels_nonce'] ) ) {
				return;
			}

			$nonce       = \sanitize_text_field( \wp_unslash( $_REQUEST['pmpro_membershiplevels_nonce'] ) );
			$valid_nonce = \wp_verify_nonce( $nonce, 'save_membershiplevel' );

			// Return early if nonce is not valid.
			if ( ! $valid_nonce ) {
				return;
			}

			/**
			 * Only trigger this for event for level creation.
			 * ! Note: $_REQUEST['saveid'] never matches $save_id upon membership level creation.
			 */
			$request_saveid = isset( $_REQUEST['saveid'] ) ? \sanitize_text_field( \wp_unslash( $_REQUEST['saveid'] ) ) : 0;

			// If we have a $request_saveid in the request and it is greater than 0, then this is an update and not a create, so we return early.
			if ( (int) $request_saveid > 0 ) {
				return;
			}

			$level = \pmpro_getLevel( $save_id );

			$variables = array(
				'MembershipID'       => (int) $save_id,
				'LevelName'          => \esc_html( $level->name ),
				'MembershipCostText' => \esc_html( \wp_strip_all_tags( pmpro_getLevelCost( $level, false, true ) ) ),
				'ExpirationText'     => ! empty( $level->expiration_number ) ? $level->expiration_number . ' ' . \pmpro_translate_billing_period( $level->expiration_period, $level->expiration_number ) : \esc_html__( 'Never', 'wp-security-audit-log' ),
				'ViewLink'           => \esc_url( \admin_url( 'admin.php?page=pmpro-membershiplevels&edit=' . $save_id ) ),
			);

			Alert_Manager::trigger_event( 9501, $variables );
		}

		/**
		 * Trigger event when a membership level is deleted.
		 *
		 * @param int $level_id The membership level ID that was deleted.
		 *
		 * @return void
		 *
		 * @since 5.5.2
		 */
		public static function pmp_deleted_membership_level_event( $level_id ) {
			$level = \pmpro_getLevel( $level_id );

			$variables = array(
				'MembershipID' => ! empty( $level_id ) ? (int) $level_id : \esc_html__( 'Not provided', 'wp-security-audit-log' ),
				'LevelName'    => ! empty( $level->name ) ? \esc_html( $level->name ) : \esc_html__( 'Not provided', 'wp-security-audit-log' ),
			);

			Alert_Manager::trigger_event( 9502, $variables );
		}


		/**
		 * Member received a membership level.
		 *
		 * @param int $level_id - The membership level ID from Paid Memberships Pro.
		 * @param int $user_id - The WordPress user ID.
		 *
		 * @since 5.5.2
		 */
		public static function pmp_membership_assigned_to_user( $level_id, $user_id ) {

			// If this is zero this is a cancellation, not an assignment, so in this case we return early.
			if ( 0 === (int) $level_id ) {
				return;
			}

			$level = \pmpro_getLevel( $level_id );
			$user  = \get_user_by( 'ID', $user_id );

			$variables = array(
				'ID'         => ! empty( $cancel_level_id ) ? (int) $cancel_level_id : 0,
				'LevelName'  => \esc_html( $level->name ),
				'LevelId'    => (int) $level->id,
				'UserID'     => (int) $user_id,
				'UserName'   => \esc_html( $user->user_login ),
				'Email'      => \esc_html( $user->user_email ),
				'FirstName'  => ! empty( \get_user_meta( $user_id, 'first_name', true ) ) ? \esc_html( \get_user_meta( $user_id, 'first_name', true ) ) : \esc_html__( 'Not provided', 'wp-security-audit-log' ),
				'LastName'   => ! empty( \get_user_meta( $user_id, 'last_name', true ) ) ? \esc_html( \get_user_meta( $user_id, 'last_name', true ) ) : \esc_html__( 'Not provided', 'wp-security-audit-log' ),
				'Role'       => \esc_html( implode( ', ', $user->roles ) ),
				'ViewMember' => \esc_url( \admin_url( '?page=pmpro-member&user_id=' . $user_id ) ),
			);

			Alert_Manager::trigger_event( 9504, $variables );
		}

		/**
		 * Trigger event when a membership level is removed/canceled from a user.
		 *
		 * @param int $save_id - Save ID is from $_REQUEST and helps to determine if this is a removal or an assignment.
		 * @param int $user_id - The affected WordPress user ID.
		 * @param int $cancel_level_id - The level ID of the membership that was removed from this user.
		 *
		 * @since 5.5.2
		 */
		public static function pmp_membership_removed_from_user( $save_id, $user_id, $cancel_level_id ) {

			// Save id is === 0 only on cancellations, so return early if this is not equal to 0.
			if ( 0 !== $save_id ) {
				return;
			}

			$level = \pmpro_getLevel( $cancel_level_id );
			$user  = \get_user_by( 'ID', $user_id );

			$variables = array(
				'LevelName'  => \esc_html( $level->name ),
				'LevelID'    => (int) $cancel_level_id,
				'UserID'     => (int) $user_id,
				'UserName'   => \esc_html( $user->user_login ),
				'Email'      => \esc_html( $user->user_email ),
				'FirstName'  => ! empty( \get_user_meta( $user_id, 'first_name', true ) ) ? \esc_html( \get_user_meta( $user_id, 'first_name', true ) ) : \esc_html__( 'Not provided', 'wp-security-audit-log' ),
				'LastName'   => ! empty( \get_user_meta( $user_id, 'last_name', true ) ) ? \esc_html( \get_user_meta( $user_id, 'last_name', true ) ) : \esc_html__( 'Not provided', 'wp-security-audit-log' ),
				'Role'       => \esc_html( implode( ', ', $user->roles ) ),
				'ViewMember' => \esc_url( \admin_url( '?page=pmpro-member&user_id=' . $user_id ) ),
			);

			Alert_Manager::trigger_event( 9505, $variables );
		}

	}
}
