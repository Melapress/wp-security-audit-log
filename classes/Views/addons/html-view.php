<?php
/**
 * Addons HTML View in Admin.
 *
 * @package wsal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$utm_params = array(
	'utm_source'   => 'plugin',
	'utm_medium'   => 'referral',
	'utm_campaign' => 'WSAL',
);
switch ( $this->hook_suffix ) {
	case 'audit-log_page_wsal-loginusers':
		$utm_params['utm_content'] = 'users+sessions';
		break;
	case 'audit-log_page_wsal-reports':
		$utm_params['utm_content'] = 'reports';
		break;
	case 'audit-log_page_wsal-emailnotifications':
		$utm_params['utm_content'] = 'notifications';
		break;
	case 'audit-log_page_wsal-externaldb':
		$utm_params['utm_content'] = 'db+integrations';
		break;
	case 'audit-log_page_wsal-search':
		$utm_params['utm_content'] = 'search';
		break;
    default:
	    //  fallback for any other hook suffices would go here
	    break;
}
// Links.
$more_info = add_query_arg(
	$utm_params,
	'https://wpactivitylog.com/premium-features/'
);

// Trial link arguments.
$trial_args = array(
	'page'          => 'wsal-auditlog-pricing',
	'billing_cycle' => 'annual',
	'trial'         => 'true',
);

// Buy Now button link.
$buy_now        = add_query_arg( 'page', 'wsal-auditlog-pricing', admin_url( 'admin.php' ) );
$buy_now_target = '';
$trial_link     = add_query_arg( $trial_args, admin_url( 'admin.php' ) );

// If user is not super admin and website is multisite then change the URL.
if ( $this->_plugin->IsMultisite() && ! is_super_admin() ) {
	$buy_now        = 'https://wpactivitylog.com/pricing/';
	$trial_link     = 'https://wpactivitylog.com/pricing/';
	$buy_now_target = ' target="_blank"';
} elseif ( $this->_plugin->IsMultisite() && is_super_admin() ) {
	$buy_now    = add_query_arg( 'page', 'wsal-auditlog-pricing', network_admin_url( 'admin.php' ) );
	$trial_link = add_query_arg( $trial_args, network_admin_url( 'admin.php' ) );
} elseif ( ! $this->_plugin->IsMultisite() && ! current_user_can( 'manage_options' ) ) {
	$buy_now        = 'https://wpactivitylog.com/pricing/';
	$trial_link     = 'https://wpactivitylog.com/pricing/';
	$buy_now_target = ' target="_blank"';
}
?>

<div class="user-login-row">
	<div class="container">
		<div class="title-wrap">
			<h3><?php echo esc_html( $title ); ?></h3>
		</div>
		<div class="user-login-content-wrap">
			<div class="user-login-content">
				<div class="user-content-right">
					<div class="img-wrap">
						<img src="<?php echo esc_url( $addon_img ); ?>" alt="">
					</div>
				</div>
				<div class="user-content-left">
					<p><?php echo esc_html( $description ); ?></p>
					<ul class="premium-list">
						<?php foreach ( $premium_list as $item ) : ?>
							<li><?php echo esc_html( $item ); ?></li>
						<?php endforeach; ?>
					</ul>
					<?php if ( $subtext ) : ?>
						<p><?php echo esc_html( $subtext ); ?></p>
					<?php endif; ?>
				</div>
			</div>
			<div class="user-login-cta">
				<a href="<?php echo esc_url( $buy_now ); ?>" class="user-gradient-cta"<?php echo esc_attr( $buy_now_target ); ?>><?php esc_html_e( 'Upgrade to Premium', 'wp-security-audit-log' ); ?></a>
				<a href="<?php echo esc_url( $more_info ); ?>" class="user-bordered-cta" target="_blank"><?php esc_html_e( 'More Information', 'wp-security-audit-log' ); ?></a>
			</div>
		</div>
	</div>
</div>
<div class="user-login-row">
	<div class="container">
		<div class="title-wrap">
			<h3><?php esc_html_e( 'Screenshots', 'wp-security-audit-log' ); ?></h3>
		</div>
		<div class="user-login-content-wrap">
			<?php foreach ( $screenshots as $screenshot ) : ?>
				<div class="user-login-content user-screenshots">
					<div class="user-content-right">
						<a class="lightbox img-wrap" href="<?php echo esc_url( $screenshot['img'] ); ?>" title="<?php echo esc_attr( $screenshot['desc'] ); ?>">
							<img src="<?php echo esc_url( $screenshot['img'] ); ?>" alt="">
						</a>
					</div>
					<div class="user-content-left">
						<p><?php echo esc_html( $screenshot['desc'] ); ?></p>
					</div>
				</div>
			<?php endforeach; ?>
			<div class="user-login-cta">
				<a href="<?php echo esc_url( $buy_now ); ?>" class="user-gradient-cta"<?php echo esc_attr( $buy_now_target ); ?>><?php esc_html_e( 'Upgrade to Premium', 'wp-security-audit-log' ); ?></a>
				<a href="<?php echo esc_url( $trial_link ); ?>" class="user-bordered-cta"<?php echo esc_attr( $buy_now_target ); ?>><?php esc_html_e( 'Start Free 14-Day Premium Trial', 'wp-security-audit-log' ); ?></a>
			</div>
		</div>
	</div>
</div>
