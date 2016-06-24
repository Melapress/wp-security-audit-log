<?php

class WSAL_Views_LogInUsers extends WSAL_AbstractView {
    
    public function GetTitle()
    {
        return __('User Sessions Management Add-On', 'wp-security-audit-log');
    }
    
    public function GetIcon()
    {
        return 'dashicons-external';
    }
    
    public function GetName()
    {
        return __('Logged In Users', 'wp-security-audit-log');
    }
    
    public function GetWeight()
    {
        return 8;
    }

    public function Header() {
        wp_enqueue_style(
            'extensions',
            $this->_plugin->GetBaseUrl() . '/css/extensions.css',
            array(),
            filemtime($this->_plugin->GetBaseDir() . '/css/extensions.css')
        );
    }
    
    public function Render()
    {
        ?>
        <div class="wrap-advertising-page">
            <p>
                <?php _e('This premium add-on allows you to see who is logged in to your WordPress, block multiple same-user WordPress sessions and more.', 'wp-security-audit-log'); ?>
            </p>
            <?php $url = 'https://www.wpsecurityauditlog.com/extensions/wordpress-users-logins-management/?utm_source=plugin&utm_medium=loginspage&utm_campaign=logins'; ?>
            <p>
                <a class="button-primary" href="<?php echo esc_attr($url); ?>" target="_blank"><?php _e('Learn More', 'wp-security-audit-log'); ?></a>
            </p>
            <p>
                <span class="description">
                    Save more than 70% when you purchase this add-on as part of the <strong>All Add-On</strong> bundle.
                </span>
            </p>
            <?php $url = 'https://www.wpsecurityauditlog.com/extensions/all-add-ons-60-off/?utm_source=plugin&utm_medium=loginspage&utm_campaign=logins'; ?>
            <p>
                <a class="button-primary" href="<?php echo esc_attr($url); ?>" target="_blank"><?php _e('Learn More', 'wp-security-audit-log'); ?></a>
            </p>
        </div>
        <?php
    }
}
