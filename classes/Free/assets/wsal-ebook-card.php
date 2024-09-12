<?php
/**
 * Free version ebook component
 *
 * @since 5.1.1
 * @package wsal
 */

?>

<style>


/* Styles - START */

/* Melapress brand font 'Quicksand' */
@font-face {
	font-family: 'Quicksand';
	src: url('<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Free/assets/fonts/Quicksand-VariableFont_wght.woff2') format('woff2');
	font-weight: 100 900;
	font-style: normal;
}

.wsal-ebook {
	margin-top: 4rem; /* Add this and it should fix the problem */
	background-color: #D9E4FD;
	border-radius: 7px;
	color: #1A3060;
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-right: .6rem;
	padding: 2.66rem 2.66rem 5rem 12rem;
	position: relative;
	overflow: hidden;
	transition: all 0.2s ease-in-out;
}

.wsal-ebook-title {
	color: #1A3060;
	margin: 0 0 .3rem;
	font-size: 20px;
	font-weight: bold;
	font-family: Quicksand, sans-serif;
	line-height: 1.44rem;
}

.wsal-ebook-text {
	margin: .25rem 0 1rem;
	font-size: 0.875rem;
	line-height: 1.3125rem;
}

.wsal-ebook-cta-link {
	border-radius: 0.25rem;
	background:#FF8977;
	color: #0000EE;
	font-weight: 800;
	text-decoration: none;
	font-size: 0.875rem;
	padding: 0.4375rem 0.75rem 0.5rem 0.75rem;
	transition: all 0.2s ease-in-out;
	position: relative;
}

.wsal-ebook-cta-link:hover {
	background:#0000EE;
	color: #FF8977;
}

.wsal-ebook-close {
	background-image: url('<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Free/assets/images/close-icon.svg'); /* Path to your close icon */
	background-size: cover;
	width: 18px;
	height: 18px;
	border: none;
	cursor: pointer;
	position: absolute;
	top: 20px;
	right: 20px;
	background-color: transparent;
	display: inline-block;
}

.wsal-ebook {
	background-image: url('<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Free/assets/images/wsal-ebook.png'); /* Background image added */
	background-size: 187px 185px;
	background-repeat: no-repeat;
	background-position: 50px 51%; /* Adjusted for left side */
}

.wsal-ebook-content, .wsal-ebook-close {
	z-index: 1;
}

@media (min-width: 600px)  {
	.wsal-ebook {
		margin-right: 1.2rem;
	}
}

@media (min-width: 1200px) {
	.wsal-ebook {
		padding: 2.66rem;
	}
	
	.wsal-ebook::before {
		content: '';
		background-image: url('<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Free/assets/images/icon-bg.png'); /* Background image only displayed on desktop */
		background-size: contain;
		background-repeat: no-repeat;
		background-position: 100% 51%;
		position: absolute;
		top: 0;
		right: 0;
		bottom: 0;
		left: 0;
		z-index: 0;
	}
	
	.wsal-ebook-content {
		max-width: 40%;
		padding-left: 10rem;
	}
}

@media (min-width: 1800px) {
	.wsal-ebook {
		padding: 3.66rem;
	}
	
	.wsal-ebook-content {
		max-width: 30%;
	}
}

/* Logo Styling */
.wsal-ebook-logo-link {
	position: absolute;
	bottom: 14px;
	right: 20px;
	display: inline-block;
}

.wsal-ebook-logo-link img {
	width: 160px;
	height: 31px;
}

/* Styles - END */

</style>
<!-- Copy START -->	
<div class="wsal-ebook wsal-notice" data-dismiss-action="wsal_dismiss_ebook_notice" data-nonce="<?php echo \esc_attr( \wp_create_nonce( 'dismiss_ebook_notice' ) ); ?>">
	<div class="wsal-ebook-content">
		<h2 class="wsal-ebook-title"><?php echo esc_html__( 'Get your FREE copy of The Ultimate Guide to WordPress Oversight eBook.', 'wp-security-audit-log' ); ?></h2>
		<p class="wsal-ebook-text">
		<?php echo esc_html__( 'Learn how to leverage ', 'wp-security-audit-log' ); ?><strong>WP Activity Log</strong><?php echo esc_html__( ' and master WordPress oversight to supercharge the administration and security of your websites.', 'wp-security-audit-log' ); ?>
		</p>
		<a href="https://melapress.com/ebook-wordpress-oversight/?user-referral=log-ebook-plugin" target="_blank" class="wsal-ebook-cta-link"><?php echo esc_html__( 'Download your free copy today', 'wp-security-audit-log' ); ?></a>
		<a href="https://www.melapress.com/ebook-wordpress-oversight/?user-referral=log-ebook-plugin" target="_blank" class="wsal-ebook-logo-link"><img src="<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Free/assets/images/melapress.svg" width="160" height="31" alt="Melapress"></a>
	</div>
	<button aria-label="Close button" class="wsal-ebook-close wsal-plugin-notice-close"></button>
</div>
<!-- Copy END -->
