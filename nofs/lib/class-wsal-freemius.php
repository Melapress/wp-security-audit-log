<?php
/**
 * Mock for freemius class. It emulates the functionality of the Freemius class - nothing special here, except there are probably missing methods which have to be implemented as well.
 *
 * @package wsal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WSAL_Freemius' ) ) {
	/**
	 * WSAL disabled Freemius class.
	 */
	class WSAL_Freemius {

		/**
		 * Single instance of the class.
		 *
		 * @var WSAL_Freemius
		 */
		private static $instance;

		/**
		 * WSAL_Freemius single instance.
		 *
		 * @return WSAL_Freemius
		 */
		public static function get_instance() {
			if ( ! self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Check if user is a trial or have feature enabled license.
		 *
		 * @return bool
		 */
		public function can_use_premium_code() {
			return false;
		}

		/**
		 * All code wrapped in this statement will be only included in the premium code.
		 *
		 * @param string $plan  Plan name.
		 * @param bool   $exact If true, looks for exact plan. If false, also check "higher" plans.
		 *
		 * @return bool
		 */
		public function is_plan__premium_only( $plan = '' ) {
			return false;
		}

		public function is_plan_or_trial__premium_only( $plan = '' ) {
			return false;
		}

		public function is__premium_only() {
			return false;
		}

		public function is_premium() {
			return false;
		}

		public function is_registered() {
			return false;
		}

		public function has_api_connectivity() {
			return false;
		}

		public function has_active_valid_license() {
			return false;
		}

		public function is_not_paying() {
			return true;
		}

		public function is_free_plan() {
		return true;
			return true;
		}

		public function is_anonymous() {
			return true;
		}

		public function get_id() {
			return false;
		}

		public function skip_connection() {
			return false;
		}
	}
}
