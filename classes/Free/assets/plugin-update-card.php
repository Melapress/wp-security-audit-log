<?php
/**
 * Free version update component
 *
 * @since 5.1.1
 * @package wsal
 */

?>

<style>

/* Styles - START */

/* Melapress brand font 'Quicksand' — There maybe be a preferable way to add this but this seemed the most discrete. */
@font-face {
	font-family: 'Quicksand';
	src: url('<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Free/assets/fonts/Quicksand-VariableFont_wght.woff2') format('woff2');
	font-weight: 100 900; /* This indicates that the variable font supports weights from 100 to 900 */
	font-style: normal;
}


.wsal-plugin-update {
	margin-top: 4rem;
	margin-bottom: 2rem;
	margin-right: .6rem;
	background-color: #384A2F;
	border-radius: 7px;
	color: #fff;
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 2.66rem;
	position: relative;
	overflow: hidden;
	transition: all 0.2s ease-in-out;
}

.wsal-plugin-update-content {
	max-width: 60%;
}

.wsal-plugin-update-title {
	color: #fff;
	margin: 0;
	font-size: 20px;
	font-weight: bold;
	font-family: Quicksand, sans-serif;
	line-height: 1.44rem;
}

.wsal-plugin-update-text {
	margin: .25rem 0 0;
	font-size: 0.875rem;
	line-height: 1.3125rem;
}

.wsal-plugin-update-text a:link {
	color: #FF8977;
}

.wsal-plugin-update-close {
	background-image: url('<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Free/assets/images/close-icon-rev.svg'); /* Path to your close icon */
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

.wsal-plugin-update::before {
	content: '';
	background-image: url('<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Free/assets/images/updated-bg.png'); /* Background image only displayed on desktop */
	background-size: 670px;
	background-repeat: no-repeat;
	background-position: 100% 51%;
	position: absolute;
	top: 0;
	right: 0;
	bottom: 0;
	left: 0;
	z-index: 0;
}

.wsal-plugin-update-content, .wsal-plugin-update-close {
	z-index: 1;
}

@media (min-width: 600px)  {
	.wsal-plugin-update {
		margin-right: 1.2rem;
	}
	.wsal-plugin-update-content {
		max-width: 50%;
	}
}

@media (max-width: 1200px) {
	.wsal-plugin-update::before {
		display: none;
	}

	.wsal-plugin-update-content {
		max-width: 100%;
	}
}

/* Styles - END */
</style>
<!-- Copy START -->
<div class="wsal-plugin-update wsal-notice" data-dismiss-action="wsal_dismiss_upgrade_notice" data-nonce="<?php echo \esc_attr( \wp_create_nonce( 'dismiss_upgrade_notice' ) ); ?>">
	<div class="wsal-plugin-update-content">
		<h2 class="wsal-plugin-update-title"><?php echo esc_html__( 'WP Activity Log has been updated to version ', 'wp-security-audit-log' ) . \esc_attr( WSAL_VERSION ); ?></h2>
		<p class="wsal-plugin-update-text">
			<?php echo \esc_html__( 'You are now running the latest version of WP Activity Log. To see what\'s been included in this update, refer to the plugin\'s release notes and change log where we list all new features, updates, and bug fixes.', 'wp-security-audit-log' ); ?> <a href="https://melapress.com/support/kb/wp-activity-log-plugin-changelog/?utm_source=wp+repo&utm_medium=repo+link&utm_campaign=wordpress_org&utm_content=wsal" target="_blank"><?php echo esc_html__( 'Read the release notes', 'wp-security-audit-log' ); ?></a>
		</p>
	</div>
	<button aria-label="Close button" class="wsal-plugin-update-close wsal-plugin-notice-close"></button>
</div>
<!-- Copy END -->
