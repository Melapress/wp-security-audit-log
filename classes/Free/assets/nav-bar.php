<?php
/**
 * Free version navbar component
 *
 * @since 5.1.1
 * @package wsal
 */

?>
<style>
/* Styles - START */
#wsal-navbar {
	top: 46px;
	width: auto;
	height: 72px;
	background-color: #384A2F; /* Updated background color */
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 0 20px;
	box-shadow: 0 2px 5px rgba(0, 0, 0, 0.25);
	z-index: 80;
	margin-left: -20px;
	position: relative; /* Positions under .wpadminbar  */
}


/* Logo Styling */
.wsal-logo-link {
	display: flex;
	align-items: center;
	margin-right: 20px;
}

.wsal-logo {
	width: 40px;
	height: 40px;
	max-width: 40px; /* Overrides extension.css styles! */
}

/* Navigation Styling for Mobile */
#wsal-nav {
	display: flex;
	align-items: center;
	width: 100%;
	justify-content: space-between;
}

#wsal-navbar .wsal-nav-list {
	list-style: none;
	display: flex;
	align-items: center;
	height: 72px;
	padding-top: .5rem;
}

#wsal-navbar ul.wsal-nav-left {
	display: none;
}

#wsal-navbar .wsal-nav-right {
	margin-left: auto; /* Pushes CTA button to the right */
}

/* Navigation Item */
#wsal-navbar .wsal-nav-right {
	margin-left: auto; /* Ensures CTA stays on the right */
}

#wsal-navbar .wsal-nav-list {
	list-style: none;
	display: flex;
	align-items: center;
}

#wsal-navbar .wsal-nav-item {
	margin-right: 20px;
}

#wsal-navbar .wsal-nav-item:last-child {
	margin-right: 0; /* Remove margin for the last item in the left nav */
}


/* CTA Button Styling */
#wsal-navbar .cta-link {
	font-weight: normal;
	border-radius: 0.25rem;
	border: 1px #009344;
	background: linear-gradient(180deg, rgba(248, 231, 28, 0.20) 0%, rgba(56, 74, 47, 0.20) 100%), #009344;
}

#wsal-navbar .cta-link:hover {
	background: rgba(248, 231, 28, 0.4);
}

/* Navigation Link Styling */
#wsal-navbar .wsal-nav-link {
	display: flex;
	align-items: center;
	text-decoration: none;
	color: #fff; /* Updated text color */
	font-size: 16px;
	padding: 0.4375rem 0.75rem 0.5rem 2.1375rem; /* Space for the background icon */
	position: relative;
	transition: all 0.2s ease-in-out;
}

/* Icon Styling */
#wsal-navbar .wsal-nav-link::before {
	content: "";
	position: absolute;
	left: 0.75rem;
	top: 50%;
	transform: translateY(-55%);
	width: 16px;
	height: 16px;
	background-size: contain;
	background-repeat: no-repeat;
}

/* Fix vertical alignment on Firefox */
@-moz-document url-prefix() {
	#wsal-navbar .wsal-nav-link::before {
		top: 55%;
	}
}

/* Active State Styling */
#wsal-navbar .wsal-nav-item.active .wsal-nav-link,
#wsal-navbar .wsal-nav-link:hover {
	background-color: rgba(248, 231, 28, 0.2); /* Transparent yellow shade for active background */
	border-radius: 0.25rem; /* Optional: adds a rounded corner effect */
}

/* Specific Background Images */
#wsal-navbar .cta-link::before {
	background-image: url('<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Free/assets/images/locked-icon.svg');
	opacity: .6;
}

/* Fix vertical alignment on Firefox */
@-moz-document url-prefix() {
	#wsal-navbar .cta-link::before {
		top: 52%;
	}
}

#wsal-navbar .cta-link:hover::before {
	opacity: .9;
}

@-webkit-keyframes buttonGlow {
	from {box-shadow: 0 0 3px #384A2F; }
	50% {box-shadow: 0 0 6px rgba(248, 231, 28, .4); }
	to {box-shadow: 0 0 3px #384A2F; }
}

@keyframes buttonGlow {
	from {box-shadow: 0 0 3px #384A2F; }
	50% {box-shadow: 0 0 6px rgba(248, 231, 28, .4); }
	to {box-shadow: 0 0 3px #384A2F; }
}

#wsal-navbar .cta-link:hover {
	animation-name: buttonGlow;
	animation-duration: 3s;
	animation-iteration-count: infinite;
	-webkit-animation-name: buttonGlow;
	-webkit-animation-duration: 3s;
	-webkit-animation-iteration-count: infinite;
}

@media (min-width: 600px)  {
	#wsal-navbar {
		position: sticky;
		top: 32px;
		width: auto;
	}
}
/* Larger Screen Styles (Tablet & Desktop) */
@media (min-width: 1385px) {
	#wsal-navbar {
		position: sticky;
		width: auto;
	}
	
	#wsal-nav {
		display: flex;
		width: 100%;
		justify-content: space-between;
	}

	#wsal-navbar ul.wsal-nav-left {
		display: flex;
		align-items: center;
	}

	/* Specific Background Images */
	#wsal-navbar .log-viewer-link::before {
		background-image: url('<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Free/assets/images/log-viewer-icon.svg');
	}

	#wsal-navbar .settings-link::before {
		background-image: url('<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Free/assets/images/settings-icon.svg');
	}

	#wsal-navbar .enable-disable-link::before {
		background-image: url('<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Free/assets/images/enable-disable-icon.svg');
	}

	#wsal-navbar .help-contact-link::before {
		background-image: url('<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Free/assets/images/help-contact-icon.svg');
	}
}
/* Styles - END */
</style>
<?php
global $current_screen;

$log_viewer = ( in_array( $current_screen->base, array( 'toplevel_page_wsal-auditlog', 'toplevel_page_wsal-auditlog-network' ), true ) ) ? 'active' : '';
$settings   = ( in_array( $current_screen->base, array( 'wp-activity-log_page_wsal-settings', 'wp-activity-log_page_wsal-settings-network' ), true ) ) ? 'active' : '';
$toggle     = ( in_array( $current_screen->base, array( 'wp-activity-log_page_wsal-togglealerts', 'wp-activity-log_page_wsal-togglealerts-network' ), true ) ) ? 'active' : '';
$help       = ( in_array( $current_screen->base, array( 'wp-activity-log_page_wsal-help', 'wp-activity-log_page_wsal-help-network' ), true ) ) ? 'active' : '';
?>

<nav id="wsal-navbar">
	<a href="https://www.melapress.com/wordpress-activity-log/" target="_blank" class="wsal-logo-link">
		<img src="<?php echo \esc_url( WSAL_BASE_URL ); ?>/classes/Free/assets/images/wp-activity-log-symbol.svg" alt="WP Activity Log" class="wsal-logo">
	</a>
	<div id="wsal-nav" class="nav">
		<ul class="wsal-nav-left wsal-nav-list">
			<li class="wsal-nav-item <?php echo \esc_attr( $log_viewer ); ?>">
				<a href="
				<?php
				echo esc_url(
					\add_query_arg(
						array(
							'page' => 'wsal-auditlog',
						),
						\network_admin_url( 'admin.php' )
					)
				);
				?>
				" class="wsal-nav-link log-viewer-link"><?php echo esc_html__( 'Log viewer', 'wp-security-audit-log' ); ?></a>
			</li>
			<li class="wsal-nav-item <?php echo \esc_attr( $settings ); ?>">
				<a href="
				<?php
				echo esc_url(
					\add_query_arg(
						array(
							'page' => 'wsal-settings',
						),
						\network_admin_url( 'admin.php' )
					)
				);
				?>
				" class="wsal-nav-link settings-link"><?php echo esc_html__( 'Settings', 'wp-security-audit-log' ); ?></a>
			</li>
			<li class="wsal-nav-item <?php echo \esc_attr( $toggle ); ?>">
				<a href="
				<?php
				echo \esc_url(
					\add_query_arg(
						array(
							'page' => 'wsal-togglealerts',
						),
						\network_admin_url( 'admin.php' )
					)
				);
				?>
				" class="wsal-nav-link enable-disable-link"><?php echo esc_html__( 'Enable/Disable Events', 'wp-security-audit-log' ); ?></a>
			</li>
			<li class="wsal-nav-item <?php echo \esc_attr( $help ); ?>">
				<a href="
				<?php
				echo esc_url(
					\add_query_arg(
						array(
							'page' => 'wsal-help',
						),
						\network_admin_url( 'admin.php' )
					)
				);
				?>
				" class="wsal-nav-link help-contact-link"><?php echo esc_html__( 'Help & Contact Us', 'wp-security-audit-log' ); ?></a>
			</li>
		</ul>
		<ul class="wsal-nav-right wsal-nav-list">
			<li class="wsal-nav-item">
				<a href="https://melapress.com/wordpress-activity-log/pricing/?&utm_source=plugin&utm_medium=link&utm_campaign=wsal" target="_blank" class="wsal-nav-link cta-link"><?php echo esc_html__( 'Unlock extra features with WP Activity Log Premium', 'wp-security-audit-log' ); ?></a>
			</li>
		</ul>
	</div>
</nav>
