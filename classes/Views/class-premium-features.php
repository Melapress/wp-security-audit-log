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
					src: url('<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Free/assets/fonts/Quicksand-VariableFont_wght.woff2') format('woff2');
					font-weight: 100 900; /* This indicates that the variable font supports weights from 100 to 900 */
					font-style: normal;
				}

				/* Styles - START */
				.wsal-features h1 {
					color: #1A3060;
					font-family: 'Quicksand', sans-serif;
					text-align: center;
					margin: 20px 0;
					font-size: 2.4rem; /* Adjusted for mobile */
				}

				.wsal-features h1 strong {
					background: url("<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Views/assets/images/highlight.svg") no-repeat;
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
						font-size: 3.4rem; /* Larger font size for tablets and above */
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
				<h1><strong><?php echo esc_html__( 'Premium features', 'wp-security-audit-log' ); ?></strong></h1>	
				<ul class="wsal-feature-list">
					<li>
						<img width="128" height="128" src="<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Views/assets/images/wsal-instant-alert.svg" alt="instant alerts">
						<div class="wsal-feature-content">
							<h2><?php echo esc_html__( 'Receive instant SMS &#38; email alerts', 'wp-security-audit-log' ); ?></h2>
							<div>
								<p><?php echo esc_html__( 'Know what is happening on your websites without having to login! Get instantly alerted of any user activities or site changes via SMS messages and emails. You can start right away with the built-in notifications or even create your own notification triggers.', 'wp-security-audit-log' ); ?></p>
							</div>
						</div>
					</li>
					<li>
						<img width="128" height="128" src="<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Views/assets/images/wsal-user-sessions.svg" alt="WordPress activity log reports">
						<div class="wsal-feature-content">
							<h2><?php echo esc_html__( 'Generate activity log reports', 'wp-security-audit-log' ); ?></h2>
							<div>
								<p><?php echo esc_html__( 'Generate user and system reports from the activity log including the update log. Reports are fully configurable and include White Labelling options. You can also schedule daily, weekly, monthly, or quarterly reports to be sent automatically to your inbox.', 'wp-security-audit-log' ); ?></p>
							</div>
						</div>
					</li>
					<li>
						<img width="128" height="128" src="<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Views/assets/images/wp-activity-log-user-sessions.svg" alt="WP Activity Log user sessions">
						<div class="wsal-feature-content">
							<h2><?php echo esc_html__( 'Manage users sessions in real-time', 'wp-security-audit-log' ); ?></h2>
							<div>
								<p><?php echo esc_html__( 'Use the real-time user activity monitor to remotely terminate sessions, block simultaneous same-user sessions, and automatically terminate idle sessions; with email notifications so that you can truly manage your WordPress from anywhere.', 'wp-security-audit-log' ); ?></p>
							</div>
						</div>
					</li>
					<li>
						<img width="128" height="128" src="<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Views/assets/images/wsal-easy-search.svg" alt="easy activity log search">
						<div class="wsal-feature-content">
							<h2><?php echo esc_html__( 'Easily track down specific activity', 'wp-security-audit-log' ); ?></h2>
							<div>
								<p><?php echo esc_html__( 'Use the extensive built-in filters to fine tune the log viewer search results and easily track down specific WordPress system and user changes. Filter configurations can also be saved for future use to find what you are looking for within seconds.', 'wp-security-audit-log' ); ?></p>
							</div>
						</div>
					</li>
					<li><img width="128" height="128" src="<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Views/assets/images/database.svg" alt="activity log database">
						<div class="wsal-feature-content">
							<h2><?php echo esc_html__( 'Store the logs in an external database', 'wp-security-audit-log' ); ?></h2>
							<div>
								<p><?php echo esc_html__( 'Improve the security posture of your website and store the activity log in an external database. By doing so you also safeguard the integrity of the logs in an unfortunate case of a website hack.', 'wp-security-audit-log' ); ?></p>
							</div>
						</div>
					</li>
					<li>
						<img width="128" height="128" src="<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Views/assets/images/manage-the-logs.svg" alt="manage logs activity">
						<div class="wsal-feature-content">
							<h2><?php echo esc_html__( 'Archive old activity log data', 'wp-security-audit-log' ); ?></h2>
							<div>
								<p><?php echo esc_html__( 'Configure the plugin to automatically archive log data that is older than a specific period to another database. Keep the log organized, easier to search, compact, and blazing fast.', 'wp-security-audit-log' ); ?></p>
							</div>
						</div>
					</li>
					<li><img width="128" height="128" src="<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Views/assets/images/integration-with-log-managment.svg" alt="integration with log management">
						<div class="wsal-feature-content">
							<h2><?php echo esc_html__( 'Logs &#38; business systems integration', 'wp-security-audit-log' ); ?></h2>
							<div>
								<p><?php echo esc_html__( 'Mirror the activity log in real time to your central logs management system so you do not have to log in to the websites and see what is happening from one central place. WP Activity Log supports AWS CloudWatch, Loggly, Slack, and others.', 'wp-security-audit-log' ); ?></p>
							</div>
						</div>
					</li>
					<li>
						<img width="128" height="128" src="<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Views/assets/images/mirror-the-log-files.svg" alt="mirror the log files">
						<div class="wsal-feature-content">
							<h2><?php echo esc_html__( 'Mirror the activity log to log files', 'wp-security-audit-log' ); ?></h2>
							<div>
								<p><?php echo esc_html__( 'Mirror the activity logs to a log file as a backup, while making it easier to import the activity log to your custom logs management system. Configure the plugin to write the activity log to a log file so the logs can be read and parsed by your system.', 'wp-security-audit-log' ); ?></p>
							</div>
						</div>
					</li>
					<li>
						<img width="128" height="128" src="<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Views/assets/images/wsal-send-activity-log.svg" alt="wsal send activity log">
						<div class="wsal-feature-content">
							<h2><?php echo esc_html__( 'Send activity logs directly to third party systems', 'wp-security-audit-log' ); ?></h2>
							<div>
								<p><?php echo esc_html__( 'When you send the activity log to a log file or a third party logs management system you can configure the plugin to not write any activity log data to the database, avoiding redundant data.', 'wp-security-audit-log' ); ?></p>
							</div>
						</div>
					</li>
					<li>
						<img width="128" height="128" src="<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Views/assets/images/plugin-setting-configuration.svg" alt="plugin setting configuration">
						<div class="wsal-feature-content">
							<h2><?php echo esc_html__( 'Export &#38; import plugin settings configuration', 'wp-security-audit-log' ); ?></h2>
							<div>
								<p><?php echo esc_html__( 'Export the plugin settings configuration to keep a backup, or to import the same plugin configuration on other websites, allowing you to have a base configuration and easily propagate it to other websites.', 'wp-security-audit-log' ); ?></p>
							</div>
						</div>
					</li>
					<li>
						<img width="128" height="128" src="<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Views/assets/images/wsal-specific-activity.svg" alt="wsal specific activity">
						<div class="wsal-feature-content">
							<h2><?php echo esc_html__( 'Delete specific activity log data', 'wp-security-audit-log' ); ?></h2>
							<div>
								<p><?php echo esc_html__( 'Need to delete data for a specific user, IP address, or another object? Use the activity log data manager to delete specific data that you do not want in your log.', 'wp-security-audit-log' ); ?></p>
							</div>
						</div>
					</li>
					<li>
						<img width="128" height="128" src="<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Views/assets/images/premium-support.svg" alt="premium support">
						<div class="wsal-feature-content">
							<h2><?php echo esc_html__( 'Premium support', 'wp-security-audit-log' ); ?></h2>
							<div>
								<p><?php echo esc_html__( 'Get professional email support within just a few hours from people who care. Our knowledgeable support team is proud of our 8-hour average response time.', 'wp-security-audit-log' ); ?></p>
							</div>
						</div>
					</li>
				</ul>
				<p class="wsal-cta"><a href="https://www.melapress.com/wordpress-activity-log/pricing/?&utm_source=plugin&utm_medium=link&utm_campaign=wsal" target="_blank" class="wsal-cta-link">Get WP Activity Log</a></p>
			</section>
			<?php
		}
	}
}
