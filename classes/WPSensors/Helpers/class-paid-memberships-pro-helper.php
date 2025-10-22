<?php
/**
 * Paid Memberships Pro Sensor helper
 *
 * @since 5.5.2
 *
 * @package wsal
 * @subpackage wsal-paid-memberships-pro
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors\Helpers;

use WSAL\Helpers\WP_Helper;
use WSAL\PMP_Addon_Member_Edit_Panel\PMP_Addon_Member_Edit_Panel;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Helpers\Paid_Memberships_Pro_Helper' ) ) {

	/**
	 * Helper Sensor class for Paid Memberships Pro.
	 *
	 * @package    wsal
	 * @subpackage sensors-helpers
	 *
	 * @since 5.5.2
	 */
	class Paid_Memberships_Pro_Helper {

		/**
		 * Class cache to store the state of the plugin.
		 *
		 * @var bool
		 *
		 * @since 5.5.2
		 */
		private static $plugin_active = null;

		/**
		 * Class cache to store the state of the plugin for sensors.
		 *
		 * @var bool
		 *
		 * @since 5.5.2
		 */
		private static $plugin_active_for_sensors = null;

		/**
		 * List of plugin event IDs for Paid Memberships Pro
		 *
		 * @var int[]
		 *
		 * @since 5.5.2
		 */
		private static $plugin_events = array( 9501, 9502, 9503, 9504, 9505, 9506, 9507, 9508, 9509 );

		/**
		 * Confirms we can load alerts if plugin is active
		 *
		 * @return bool
		 *
		 * @since 5.5.2
		 */
		public static function load_alerts_for_sensor(): bool {
			if ( null === self::$plugin_active_for_sensors ) {
				self::$plugin_active_for_sensors = WP_Helper::is_plugin_active( 'paid-memberships-pro/paid-memberships-pro.php' );
			}

			return self::$plugin_active_for_sensors;
		}

		/**
		 * Register a custom event object within WSAL.
		 *
		 * @param array $objects array of objects current registered within WSAL.
		 *
		 * @since 5.5.2
		 */
		public static function wsal_paid_memberships_pro_add_custom_event_objects( $objects ) {
			$new_objects = array(
				'pmpro_membership_levels' => esc_html__( 'Paid Memberships Pro - Membership Levels', 'wp-security-audit-log' ),
				'pmpro_members'           => esc_html__( 'Paid Memberships Pro - Members', 'wp-security-audit-log' ),
				'pmpro_subscriptions'     => esc_html__( 'Paid Memberships Pro - Subscriptions', 'wp-security-audit-log' ),
				'pmpro_orders'            => esc_html__( 'Paid Memberships Pro - Orders', 'wp-security-audit-log' ),
			);

			$objects = array_merge( $objects, $new_objects );

			return $objects;
		}

		/**
		 * Check if Paid Memberships Pro is active.
		 *
		 * @return bool
		 *
		 * @since 5.5.2
		 */
		public static function is_pmp_active() {
			if ( null === self::$plugin_active ) {
				if ( \function_exists( 'pmpro_getAllLevels' ) ) {
					self::$plugin_active = true;
				} else {
					self::$plugin_active = false;
				}
			}

			return self::$plugin_active;
		}

		/**
		 * Returns the events associated with Paid Memberships Pro.
		 *
		 * @since 5.5.2
		 */
		public static function get_plugin_events(): array {
			return self::$plugin_events;
		}

		/**
		 * Determine if the 'Disable New Signups' setting for a PMP membership has changed between old and new values, and if so return a string indicating the change.
		 *
		 * @param int    $old_value - The old value obtained from pmpro_getLevel(). Format: [allow_signups] => 0 OR 1.
		 * @param string $new_value - The new value obtained from $_REQUEST. Format: [disable_signups] => yes OR NULL.
		 *
		 * @return string - Returns  as string with the change, if detected, otherwise returns an empty string.
		 *
		 * @since 5.5.2
		 */
		public static function pmp_enable_signup_setting_change( $old_value, $new_value ): string {
			$old_allow_signups_str   = (string) $old_value;
			$new_disable_signups_str = (string) $new_value;

			$change_str = '';

			// Signups: disabled => enabled.
			if ( ( '0' === $old_allow_signups_str || '' === $old_allow_signups_str ) && ( '' === $new_disable_signups_str ) ) {
				$change_str = 'disabled => enabled';
			}

			// Signups: enabled => disabled.
			if ( ( '1' === $old_allow_signups_str ) && ( 'yes' === $new_disable_signups_str ) ) {
				$change_str = 'enabled => disabled';
			}

			return $change_str;
		}

		/**
		 * Format membership changes for display in the event list in wp-admin.
		 *
		 * @param string   $value - Meta value.
		 * @param string   $expression - Meta expression including the surrounding percentage chars.
		 * @param array    $configuration - formatter configuration.
		 * @param int|null $occurrence_id - Occurrence ID. Only present if the event was already written to the database. Default null.
		 *
		 * ! $occurrence_id is not the event id, it's the id of the record in the wsal_occurrences table.
		 *
		 * @return string - The formatted string to be displayed in the event list in wp-admin.
		 *
		 * @since 5.5.2
		 */
		public static function wsal_pmp_format_membership_changes( $value, $expression, $configuration, $occurrence_id ) {
			if ( '%PMPMembershipChanges%' === $expression || '%PMPOrderChanges%' === $expression ) {
				// Remove <strong> tags from start and end.
				$value = preg_replace( '/^<strong>(.*)<\/strong>$/s', '$1', $value );

				// Replace all &lt;br&gt; with proper <br>.
				$value = str_replace( '&lt;br&gt;', '<br>', $value );

				/**
				 * Wrap the value after the first colon in <b>...</b> for each old => new value.
				 * matches[1]: label, matches[2]: 'old => new' value.
				 */
				$value = preg_replace_callback(
					'/<br>\s*([^:]+):\s*([^<]+)(?=<br>|$)/',
					function ( $matches ) {
						return '<br>' . $matches[1] . ': <b>' . $matches[2] . '</b>';
					},
					$value
				);
			}

			return $value;
		}

		/**
		 * Get the gateway names for Paid Memberships Pro orders when we only have free levels available.
		 *
		 * @return string - The gateway name.
		 *
		 * @since 5.5.2
		 */
		public static function get_pmp_free_levels_gateway() {
			return \pmpro_onlyFreeLevels() ? \__( 'Default', 'wp-security-audit-log' ) : \__( 'Testing Only', 'wp-security-audit-log' );
		}

		/**
		 * Build a readable string to indicate a time period. E.g. "3 Months", "1 Year", "Never".
		 *
		 * @param int|string $number - The number for the period label.
		 * @param int|string $period - The period label (e.g. Week, Month, Year). Sometimes this may be 0.

		 * @return string
		 *
		 * @since 5.5.2
		 */
		public static function build_membership_time_period_string( $number, $period ): string {
			$number = (int) $number;

			$periods = array(
				'Hour'  => \_n( 'hour', 'hours', $number, 'wp-security-audit-log' ),
				'Day'   => \_n( 'day', 'days', $number, 'wp-security-audit-log' ),
				'Week'  => \_n( 'week', 'weeks', $number, 'wp-security-audit-log' ),
				'Month' => \_n( 'month', 'months', $number, 'wp-security-audit-log' ),
				'Year'  => \_n( 'year', 'years', $number, 'wp-security-audit-log' ),
			);

			if ( 0 === (int) $number || empty( $period ) || ! isset( $periods[ $period ] ) ) {
				return \__( 'Never', 'wp-security-audit-log' );
			}

			return $number . ' ' . $periods[ $period ];
		}

		/**
		 * Build a readable string for billing cycles of memberships. E.g. "One-time", "3 Months", "1 Year".
		 *
		 * @param int|string   $cycle_number   - The number for the period of the billing cycle.
		 * @param int|string   $cycle_period   - The period label of the billing cycle.
		 * @param bool         $is_recurring   - Whether the membership is recurring or not.
		 * @param float|string $billing_amount - The billing amount for the membership.
		 *
		 * @return string
		 *
		 * @since 5.5.2
		 */
		public static function build_membership_billing_cycle_string( $cycle_number, $cycle_period, $is_recurring, $billing_amount ) {
			$string = \__( 'One-time', 'wp-security-audit-log' );

			if ( null !== $cycle_number && null !== $cycle_period ) {
				if ( true === $is_recurring && $billing_amount > 0 && $cycle_number > 0 ) {
					$string = self::build_membership_time_period_string( $cycle_number, $cycle_period );
				}
			}

			return $string;
		}

		/**
		 * Build a readable string to explain the trial period of a membership.
		 *
		 * @param float|string $trial_amount - the price of the trial.
		 * @param int|string   $trial_limit - the number of payments covered by the trial price.
		 * @param bool         $is_custom_trial - whether the membership has a custom trial or not.
		 *
		 * @return string
		 *
		 * @since 5.5.2
		 */
		public static function build_membership_trial_string( $trial_amount, $trial_limit, $is_custom_trial ) {
			$string = \__( 'Disabled', 'wp-security-audit-log' );

			if ( true === $is_custom_trial ) {
					$price = (float) $trial_amount;

				if ( (float) $trial_amount <= 0 ) {
					$price = \__( 'Free', 'wp-security-audit-log' );
				}

				if ( (int) $trial_limit > 0 ) {
					$string = $price . ' ' . \__( 'for', 'wp-security-audit-log' ) . ' ' . (int) $trial_limit . ' ' . \_n( 'payment', 'payments', (int) $trial_limit, 'wp-security-audit-log' );
				}
			}

			return $string;
		}

		/**
		 * Get the values of a membership level by its ID, and return an array of current saved values.
		 *
		 * @param int|string $membership_id - The membership level ID.
		 *
		 * @return array - Array of current membership values.
		 *
		 * @since 5.5.2
		 */
		public static function extract_current_membership_values( $membership_id ): array {

			$data = array();

			$level_values = \pmpro_getLevel( $membership_id );

			$is_recurring    = \pmpro_isLevelRecurring( $level_values );
			$is_custom_trial = \pmpro_isLevelTrial( $level_values );

			if ( $level_values ) {
				$data = array(
					'name'              => $level_values->name,
					'description'       => $level_values->description,
					'confirmation'      => $level_values->confirmation,
					'initial_payment'   => $level_values->initial_payment,
					'billing_amount'    => $level_values->billing_amount,
					'cycle_period'      => self::build_membership_billing_cycle_string( $level_values->cycle_number, $level_values->cycle_period, $is_recurring, $level_values->billing_amount ),
					'billing_limit'     => $level_values->billing_limit,
					'trial'             => self::build_membership_trial_string( $level_values->trial_amount, $level_values->trial_limit, $is_custom_trial ),
					'expiration_period' => self::build_membership_time_period_string( $level_values->expiration_number, $level_values->expiration_period ),
					'allow_signups'     => $level_values->allow_signups ?? null,
				);
			}

			return $data;
		}

		/**
		 * Compare 2 arrays, returning an array of changed fields with old => new values.
		 *
		 * @param array $old_values - Old values array.
		 * @param array $new_values - New values array.
		 *
		 * @return array - Array of changed fields with old => new values.
		 *
		 * @since 5.5.2
		 */
		public static function get_array_changed_fields( $old_values, $new_values ): array {
			$changed_fields = array();

			// Get the intersection of keys.
			$common_keys = array_intersect( array_keys( $old_values ), array_keys( $new_values ) );

			foreach ( $common_keys as $key ) {
				// Compare as strings for consistency.
				$old = (string) $old_values[ $key ];
				$new = (string) $new_values[ $key ];

				if ( $old !== $new ) {
					$changed_fields[ $key ] = $old . ' => ' . $new;
				}
			}

			return $changed_fields;
		}

		/**
		 * Convert an array of changed PMP fields into a string for display in the main wsal event list.
		 *
		 * @param array $changed_fields_array - An array with a list of recently changed PMP fields.
		 *
		 * @return string - A string listing the changed fields with old => new values.
		 *
		 * @since 5.5.2
		 */
		public static function convert_changed_fields_array_into_string( $changed_fields_array ): string {
			$changed_fields_string = '';

			if ( empty( $changed_fields_array ) || ! is_array( $changed_fields_array ) ) {
				return $changed_fields_string;
			}

			foreach ( $changed_fields_array as $field => $change ) {
				if ( ! empty( $change ) ) {
					$field_label            = ucfirst( str_replace( '_', ' ', $field ) );
					$changed_fields_string .= '<br>' . $field_label . ': ' . $change;
				}
			}

			return $changed_fields_string;
		}

		/**
		 * Compare old and new membership values, returning a string of changed fields with old => new values.
		 *
		 * @param array $old_values - The old values of a Paid Memberships Pro membership level.
		 * @param array $new_values - The new values of a Paid Memberships Pro membership level.
		 *
		 * @return string - A string listing the changed fields with old => new values.
		 *
		 * @since 5.5.2
		 */
		public static function compare_membership_values_change_after_update( $old_values, $new_values ): string {

			$changed_fields = self::get_array_changed_fields( $old_values, $new_values );

			$changed_fields['signups'] = self::pmp_enable_signup_setting_change( $old_values['allow_signups'], $new_values['disable_signups'] );

			$changed_fields_string = self::convert_changed_fields_array_into_string( $changed_fields );

			return $changed_fields_string;
		}

		/**
		 * Get the array of an order by its ID, and return an array of current saved values.
		 *
		 * @param int $order_id - The Paid Memberships Pro order ID.
		 *
		 * @return array - An array of the values found in the database for this order.
		 *
		 * @since 5.5.2
		 */
		public static function get_pmp_order_by_id( $order_id ): array {
			global $wpdb;

			// Direct MOST RECENT and non cached DB query required for accurate before/after change detection.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$old_order = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->base_prefix}pmpro_membership_orders WHERE id = %d",
					$order_id
				)
			);

			if ( ! $old_order ) {
				return array();
			}

			$fallback_gateway = self::get_pmp_free_levels_gateway();

			// We need to covert this which is in string format, to a unix timestamp.
			$old_timestamp_converted = strtotime( $old_order->timestamp );

			$old_order_array = array(
				'user_id'             => $old_order->user_id ?? null,
				'membership_id'       => $old_order->membership_id ?? null,
				'subtotal'            => $old_order->subtotal ?? null,
				'tax'                 => $old_order->tax ?? null,
				'total'               => $old_order->total ?? null,
				'payment_type'        => $old_order->payment_type ?? null,
				'card_type'           => $old_order->cardtype ?? null,
				'account_number'      => $old_order->accountnumber ?? null,
				'expiration_month'    => $old_order->expirationmonth ?? null,
				'expiration_year'     => $old_order->expirationyear ?? null,
				'status'              => $old_order->status ?? null,
				'gateway'             => $old_order->gateway ? $old_order->gateway : $fallback_gateway,
				'gateway_environment' => $old_order->gateway_environment ?? null,
				'order_date'          => $old_order->timestamp ? \date_i18n( \get_option( 'date_format' ) . ' H:i:s', $old_timestamp_converted ) : null,
				'notes'               => $old_order->notes ?? null,
			);

			return $old_order_array;
		}

		/**
		 * Add Edit Member Panel for WP Activity Log.
		 * This panel can be found in wp-admin: Memberships > Members > Edit Member
		 *
		 * @param array $panels currently existing panels.
		 *
		 * @return array Modified panels array with our panel added.
		 *
		 * @since 5.5.2
		 */
		public static function wsal_paid_memberships_pro_include_member_panel( $panels ) {

			// If the class exists, add a panel.
			if ( class_exists( 'WSAL\PMP_Addon_Member_Edit_Panel\PMP_Addon_Member_Edit_Panel' ) && class_exists( 'PMPro_Member_Edit_Panel' ) ) {
				$panels[] = new PMP_Addon_Member_Edit_Panel();
			}

			return $panels;
		}
	}
}
