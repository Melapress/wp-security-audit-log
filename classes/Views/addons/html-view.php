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
	'utm_medium'   => 'referral',
	'utm_campaign' => 'WSAL',
	'utm_content'  => 'sessions',
);

$buy_now_utm_params = $utm_params;

$trial_link_utm_params = $utm_params;

switch ( $this->hook_suffix ) {
	case 'wp-activity-log_page_wsal-loginusers':
		$utm_params['utm_content'] = 'sessions';
		$buy_now_utm_params['utm_content'] = 'upgrade+now+loginusers';
		$trial_link_utm_params['utm_content'] = 'get+trial+loginusers';
		break;
	case 'wp-activity-log_page_wsal-reports':
		$utm_params['utm_content'] = 'reports';
		$buy_now_utm_params['utm_content'] = 'upgrade+now+reports';
		$trial_link_utm_params['utm_content'] = 'get+trial+reports';
		break;
	case 'wp-activity-log_page_wsal-emailnotifications':
		$utm_params['utm_content'] = 'notifications';
		$buy_now_utm_params['utm_content'] = 'upgrade+now+notifications';
		$trial_link_utm_params['utm_content'] = 'get+trial+notifications';
		break;
	case 'wp-activity-log_page_wsal-externaldb':
		$utm_params['utm_content'] = 'integrations';
		$buy_now_utm_params['utm_content'] = 'upgrade+now+integrations';
		$trial_link_utm_params['utm_content'] = 'get+trial+integrations';
		break;
	case 'wp-activity-log_page_wsal-search':
		$utm_params['utm_content'] = 'search';
		$buy_now_utm_params['utm_content'] = 'upgrade+now+search';
		$trial_link_utm_params['utm_content'] = 'get+trial+search';
		break;
	default:
		// Fallback for any other hook suffix would go here.
		break;
}
// Links.
$more_info = add_query_arg(
	$utm_params,
	'https://wpactivitylog.com/features/'
);

// Buy Now button link.
$buy_now        = add_query_arg(
    $buy_now_utm_params,
    'https://wpactivitylog.com/pricing/'
);
$buy_now_target = ' target="_blank"';

$trial_link = add_query_arg(
    $trial_link_utm_params,
    'https://wpactivitylog.com/trial-premium-edition-plugin/'
);

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
