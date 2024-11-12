<?php
/**
 * Free version upgrade to premium component
 *
 * @since 5.1.1
 * @package wsal
 */

?>

<div id="wsal-persistant-cta" style="position: absolute; top: 2.4rem;">
<style>
.wsal-persistent-cta {
	margin: .6rem .6rem 0 0;
	background-color: #384A2F;
	border-radius: 7px;
	color: #fff;
	display: flex;
	justify-content: flex-end; /* Align content to the right */
	align-items: center;
	padding: .66rem 2.66rem;
	position: relative;
	overflow: hidden;
	transition: all 0.2s ease-in-out;
}

.wsal-persistent-cta-content {
	max-width: 30%;
	right: 0;
}

.wsal-persistent-cta-title {
	margin: 0;
	font-size: 20px;
	font-weight: bold;
	font-family: Quicksand, sans-serif;
	line-height: 1.44rem;
	text-align: center;
}

.wsal-persistent-cta-text {
	margin: .25rem 0 0;
	font-size: 0.875rem;
	line-height: 1.3125rem;
}

.wsal-persistent-cta::before {
	content: '';
	background-image: url('<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Free/assets/images/features-bg.png'); /* Background image only displayed on desktop */
	background-size: 860px;
	background-repeat: no-repeat;
	background-position: -2rem 52%; /* Moved to the left */
	position: absolute;
	top: 0;
	right: 0;
	bottom: 0;
	left: 0;
	z-index: 0;
}
	
.wsal-persistent-cta-content {
	z-index: 1;
}
	
.wsal-persistent-cta-image {
	display: block;
	margin: 0 auto 1rem;
	width: 20rem;
}
	
.wsal-persistent-cta-link {
	text-align: center;
}

.cta-link {
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

.cta-link:hover {
	background: #0000EE;
	color: #FF8977;
}
	
@media (min-width: 600px)  {
	.wsal-persistent-cta {
		margin-right: 1.2rem;
		margin-bottom: 1.2rem;
	}
}

@media (max-width: 1200px) {
	.wsal-persistent-cta::before {
		display: none;
	}

	.wsal-persistent-cta-content {
		max-width: 100%;
	}
}
/* Styles - END */
</style>

<!-- Copy START -->	
<div class="wsal-persistent-cta">
	<div class="wsal-persistent-cta-content">
		<h2 class="wsal-persistent-cta-title">
			<img src="<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Free/assets/images/wp-activity-log-stacked.svg" alt="WP Activity Log" class="wsal-persistent-cta-image">
		</h2>
		<p class="wsal-persistent-cta-text">
			<?php
			\printf(
			/* translators: This text is prepended by a link to Melapress's website, and appended by a link to Melapress's website. */
				'<strong>%1$s</strong> %2$s',
				\esc_html__( 'Upgrade to Premium and:', 'wp-security-audit-log' ),
				\esc_html__( 'Save time and find specific events in seconds with extensive search filters. Stay informed while on the go with fully configurable email and SMS notifications. Manage user sessions with policies and see who is logged in in real-time. Create ad-hoc and periodic reports with white-labeling and email options. And much more.', 'wp-security-audit-log' )
			);
			?>
		</p>
		<p class="wsal-persistent-cta-link"><a href="https://www.melapress.com/wordpress-activity-log/pricing/?utm_source=plugin&utm_medium=link&utm_campaign=wsal" target="_blank" class="cta-link"><?php echo \esc_html__( 'Get WP Activity Log', 'wp-security-audit-log' ); ?></a></p>
	</div>
</div>
<!-- Copy END -->
<div class="clear"></div>
</div>
