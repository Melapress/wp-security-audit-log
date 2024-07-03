<?php
/**
 * Addons HTML View in Admin.
 *
 * @package wsal
 * @subpackage views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$utm_params = array(
	'utm_source'   => 'plugin',
	'utm_medium'   => 'button',
	'utm_campaign' => 'wsal',
);

$buy_now_utm_params = $utm_params;

if (property_exists($this, 'hook_suffix')){
switch ( $this->hook_suffix ) {
	case 'wp-activity-log_page_wsal-loginusers':
		$utm_params['utm_content']            = 'sessions';
		$buy_now_utm_params['utm_content']    = 'upgrade+now+loginusers';
		break;
	case 'wp-activity-log_page_wsal-reports':
		$utm_params['utm_content']            = 'reports';
		$buy_now_utm_params['utm_content']    = 'upgrade+now+reports';
		break;
	case 'wp-activity-log_page_wsal-emailnotifications':
		$utm_params['utm_content']            = 'notifications';
		$buy_now_utm_params['utm_content']    = 'upgrade+now+notifications';
		break;
	case 'wp-activity-log_page_wsal-externaldb':
		$utm_params['utm_content']            = 'integrations';
		$buy_now_utm_params['utm_content']    = 'upgrade+now+integrations';
		break;
	case 'wp-activity-log_page_wsal-search':
		$utm_params['utm_content']            = 'search';
		$buy_now_utm_params['utm_content']    = 'upgrade+now+search';
		break;
	default:
		// Fallback for any other hook suffix would go here.
		break;
}
}
// Links.
$more_info = add_query_arg(
	$utm_params,
	'https://melapress.com/wordpress-activity-log/features/'
);

// Buy Now button link.
$buy_now        = add_query_arg(
	$buy_now_utm_params,
	'https://melapress.com/wordpress-activity-log/pricing/'
);
$buy_now_target = ' target="_blank"';

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
				<a href="<?php echo esc_url( $buy_now ); ?>" class="user-gradient-cta"<?php echo esc_attr( $buy_now_target ); ?>><?php esc_html_e( 'Get WP Activity Log Premium', 'wp-security-audit-log' ); ?></a>
				<a href="<?php echo esc_url( $more_info ); ?>" class="user-bordered-cta" target="_blank"><?php esc_html_e( 'See all features', 'wp-security-audit-log' ); ?></a>
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
				<a href="<?php echo esc_url( $buy_now ); ?>" class="user-bordered-cta"<?php echo esc_attr( $buy_now_target ); ?>><?php esc_html_e( 'Get WP Activity Log Premium', 'wp-security-audit-log' ); ?></a>
			</div>
		</div>
	</div>
</div>
