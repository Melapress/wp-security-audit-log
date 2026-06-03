<?php
/**
 * View: Premium features Page
 *
 * WSAL premium features page.
 *
 * @since 5.1.1
 * @package    wsal
 * @subpackage views
 */

declare(strict_types=1);

namespace WSAL\Views;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Premium_Features class
 */
if ( ! class_exists( '\WSAL\Views\Premium_Features' ) ) {
	/**
	 * Premium features Add-On promo Page.
	 * Used only if the plugin is not activated.
	 *
	 * @package    wsal
	 * @subpackage views
	 */
	class Premium_Features extends \WSAL_AbstractView {

		/**
		 * {@inheritDoc}
		 */
		public function is_title_visible() {
			return false;
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_icon() {
			return 'dashicons-external';
		}

		/**
		 * {@inheritDoc}
		 */
		public function header() {
		}

		/**
		 * {@inheritDoc}
		 */
		public function footer() {
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_title() {
			return esc_html__( 'Premium Features', 'wp-security-audit-log' );
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_name() {

			return esc_html__( 'Premium Features', 'wp-security-audit-log' ) . '<style>
				a[href*="wsal-views-premium-features"] svg{
					fill:rgba(240,246,252,.7) !important;
					display: inline-block;
					position: relative;
					left: 0px;
					top: 3px;
				}
				a[href*="wsal-views-premium-features"]:hover svg,
				a[href*="wsal-views-premium-features"]:focus svg{
					fill:#72aee6 !important;
				}
				.current a[href*="wsal-views-premium-features"] svg,
				.current a[href*="wsal-views-premium-features"]:hover svg,
				.current a[href*="wsal-views-premium-features"]:focus svg{
					fill:#fff !important;
				}
				@media only screen and (max-width: 960px) and (min-width: 782px) {
					a[href*="wsal-views-premium-features"] svg{
						display: none;
					}
				}
				</style> <span><svg xmlns="http://www.w3.org/2000/svg" width="16" height="17"xmlns:v="https://vecta.io/nano"><path d="M12.59 9.004V6.898c0-2.429-1.899-4.398-4.242-4.398S4.106 4.469 4.106 6.898v2.107H3v6.866h10.696V9.004H12.59zM9.298 13.72H7.397v-2.593-.011c.004-.539.429-.977.95-.977s.95.442.95.985v2.595h0zm1.105-4.716H6.292V6.897c0-1.175.922-2.131 2.056-2.131s2.056.956 2.056 2.132v2.107h-.001z"/></svg></span>';
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_weight() {
			return 2;
		}

		/**
		 * {@inheritDoc}
		 */
		public function render() {
			?>
			<style>
				@font-face {
					font-family: 'Quicksand';
					src: url('<?php echo \esc_url( WSAL_BASE_URL ); ?>classes/Free/assets/fonts/Quicksand-VariableFont_wght.woff2') format('woff2');
					font-weight: 100 900; /* This indicates that the variable font supports weights from 100 to 900 */
					font-style: normal;
				}

				/* Styles - START */
				.wsal-premium-overview {
					text-align: center;
					margin-bottom: 40px;
				}

				.wsal-premium-overview h2 {
					font-size: 1.375rem;
					line-height: 1.25;
					margin: 0 auto;
					max-width: 728px;
				}

				.wsal-premium-overview .wsal-cta {
					font-size: 1.125rem;
					margin-top: 16px;
					display: inline-block;
				}

				.wsal-features h1 {
					color: #1A3060;
					font-family: 'Quicksand', sans-serif;
					text-align: center;
					margin: 20px 0;
					font-size: 2.4rem; /* Adjusted for mobile */
				}

				.wsal-features h1 strong {
					background: url("<?php echo \esc_url( WSAL_BASE_URL ); ?>classes/Views/assets/images/highlight.svg") no-repeat;
					background-position: center bottom;
					background-size: 100% 17%;
				}

				.wsal-feature-list {
					color: #1A3060;
					list-style: none;
					padding: 0;
					display: flex;
					flex-wrap: wrap;
					flex-direction: column; /* Stack items vertically by default */
					justify-content: space-between;
				}

				.wsal-feature-list li {
					display: flex;
					align-items: top;
					width: 90%; /* Full width on mobile */
					margin-bottom: .8rem;
					padding: 1.6rem;
					position: relative;
					flex-direction: row;
					text-align: left;
				}

				.wsal-feature-list img {
					margin-right: .8rem;
				}

				.wsal-feature-content {
					flex: 1;
				}

				.wsal-feature-content h2 {
					font-family: 'Quicksand', sans-serif;
					font-size: 1.2rem; /* Adjusted for mobile */
					margin: 0 0 .3rem;
					font-weight: 600;
					color: #1A3060;
				}

				.wsal-feature-content p {
					margin: 0;
					font-size: 1rem;
					line-height: 1.5;
				}

				.wsal-cta {
					text-align: center;
				}

				.wsal-cta-link {
					border-radius: 0.25rem;
					background: #FF8977;
					color: #0000EE;
					font-weight: bold;
					text-decoration: none;
					font-size: 1.2rem;
					padding: 0.675rem 1.3rem .7rem 1.3rem;
					transition: all 0.2s ease-in-out;
					display: inline-block;
					margin: 1rem auto;
				}

				.wsal-cta-link:hover {
					background: #0000EE;
					color: #FF8977;
				}

				/* Tablet and larger screens */
				@media (min-width: 868px) {
					.wsal-features h1 {
						font-size: 2.8rem; /* Larger font size for tablets and above */
					}

					.wsal-feature-list {
						flex-direction: row; /* Arrange items in a row */
					}

					.wsal-feature-list li {
						width: 42%; /* Two columns on larger screens */
					}

					/* Odd items positioned on the right */
					.wsal-feature-list li:nth-child(odd) {
						margin-left: auto;
					}

					/* Even items positioned on the left */
					.wsal-feature-list li:nth-child(even) {
						margin-right: auto;
					}
				}
				/* Styles - END */
			</style>
			<section class="wsal-features">
				<h1><strong><?php echo esc_html__( 'Unlock advanced activity log capabilities', 'wp-security-audit-log' ); ?></strong></h1>	
				<div class="wsal-premium-overview">
					<h2><?php echo esc_html__( 'Get real-time alerts, advanced reporting, user session management, integrations, and more with WP Activity Log Premium.', 'wp-security-audit-log' ); ?></h2>
					<p class="wsal-cta"><a href="<?php echo esc_url( 'https://melapress.com/wordpress-activity-log/pricing/?utm_source=plugin&utm_medium=wsal&utm_campaign=premium-features-top' ); ?>" target="_blank" class="wsal-cta-link"><?php echo esc_html__( 'View Pricing', 'wp-security-audit-log' ); ?></a></p>
				</div>
				<ul class="wsal-feature-list">
					<li>
						<img width="128" height="128" src="<?php echo \esc_url( WSAL_BASE_URL ); ?>classes/Views/assets/images/wsal-instant-alert.svg" alt="<?php echo esc_attr__( 'instant alerts', 'wp-security-audit-log' ); ?>">
						<div class="wsal-feature-content">
							<h2><?php echo esc_html__( 'Get instantly alerted to critical activity', 'wp-security-audit-log' ); ?></h2>
							<div>
								<p><?php echo esc_html__( 'Get instantly notified via email, SMS, or Slack when important user activity or website changes happen, without needing to log in to your website.', 'wp-security-audit-log' ); ?></p>
							</div>
						</div>
					</li>
					<li>
						<img width="128" height="128" src="<?php echo \esc_url( WSAL_BASE_URL ); ?>classes/Views/assets/images/wsal-user-sessions.svg" alt="<?php echo esc_attr__( 'WordPress activity log reports', 'wp-security-audit-log' ); ?>">
						<div class="wsal-feature-content">
							<h2><?php echo esc_html__( 'Stay informed with automated activity reports', 'wp-security-audit-log' ); ?></h2>
							<div>
								<p><?php echo esc_html__( 'Generate configurable activity log and user reports, schedule automatic email delivery, and create white-labelled reports for clients and teams.', 'wp-security-audit-log' ); ?></p>
							</div>
						</div>
					</li>
					<li>
						<img width="128" height="128" src="<?php echo \esc_url( WSAL_BASE_URL ); ?>classes/Views/assets/images/wp-activity-log-user-sessions.svg" alt="<?php echo esc_attr__( 'WP Activity Log user sessions', 'wp-security-audit-log' ); ?>">
						<div class="wsal-feature-content">
							<h2><?php echo esc_html__( 'Prevent account sharing & manage user sessions', 'wp-security-audit-log' ); ?></h2>
							<div>
								<p><?php echo esc_html__( 'See who is logged in to your website in real time, remotely terminate sessions, prevent simultaneous logins, and automatically log out idle users.', 'wp-security-audit-log' ); ?></p>
							</div>
						</div>
					</li>
					<li>
						<img width="128" height="128" src="<?php echo \esc_url( WSAL_BASE_URL ); ?>classes/Views/assets/images/wsal-easy-search.svg" alt="<?php echo esc_attr__( 'easy activity log search', 'wp-security-audit-log' ); ?>">
						<div class="wsal-feature-content">
							<h2><?php echo esc_html__( 'Troubleshoot issues faster', 'wp-security-audit-log' ); ?></h2>
							<div>
								<p><?php echo esc_html__( 'Quickly track down specific activity using advanced filters, saved searches, and detailed activity insights.', 'wp-security-audit-log' ); ?></p>
							</div>
						</div>
					</li>
					<li>
						<img width="128" height="128" src="<?php echo \esc_url( WSAL_BASE_URL ); ?>classes/Views/assets/images/database.svg" alt="<?php echo esc_attr__( 'activity log database', 'wp-security-audit-log' ); ?>">
						<div class="wsal-feature-content">
							<h2><?php echo esc_html__( 'Store and manage activity logs at scale', 'wp-security-audit-log' ); ?></h2>
							<div>
								<p><?php echo esc_html__( 'Store logs in external databases, archive older data automatically, and integrate with services like AWS CloudWatch, Slack, and Loggly.', 'wp-security-audit-log' ); ?></p>
							</div>
						</div>
					</li>
					<li>
						<img width="128" height="128" src="<?php echo \esc_url( WSAL_BASE_URL ); ?>classes/Views/assets/images/premium-support.svg" alt="<?php echo esc_attr__( 'premium support', 'wp-security-audit-log' ); ?>">
						<div class="wsal-feature-content">
							<h2><?php echo esc_html__( 'Get fast, professional support when you need it', 'wp-security-audit-log' ); ?></h2>
							<div>
								<p><?php echo esc_html__( 'Get fast, professional support from a real human with an average response time of under 8 hours.', 'wp-security-audit-log' ); ?></p>
							</div>
						</div>
					</li>
				</ul>
				<p class="wsal-cta"><a href="<?php echo \esc_url( 'https://melapress.com/wordpress-activity-log/pricing/?utm_source=plugin&utm_medium=wsal&utm_campaign=premium-features-page-cta-bottom' ); ?>" target="_blank" class="wsal-cta-link"><?php echo esc_html__( 'Get WP Activity Log Premium', 'wp-security-audit-log' ); ?></a></p>
			</section>
			<?php
		}
	}
}
