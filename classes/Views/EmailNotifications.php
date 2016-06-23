<?php

class WSAL_Views_EmailNotifications extends WSAL_AbstractView {
    
    public function GetTitle()
    {
        return __('Email Notifications Add-On', 'wp-security-audit-log');
    }
    
    public function GetIcon()
    {
        return 'dashicons-external';
    }
    
    public function GetName()
    {
        return __('Email Notifications', 'wp-security-audit-log');
    }
    
    public function GetWeight()
    {
        return 7;
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
                <?php _e('This premium add-on allows you to configure email alerts so you are notified instantly when important changes happen on your WordPress.', 'wp-security-audit-log'); ?>
            </p>
            <?php $url = 'https://www.wpsecurityauditlog.com/extensions/wordpress-email-notifications-add-on/?utm_source=plugin&utm_medium=emailpage&utm_campaign=notifications'; ?>
            <p>
                <a class="button-primary" href="<?php echo esc_attr($url); ?>" target="_blank"><?php _e('Learn More', 'wp-security-audit-log'); ?></a>
            </p>
            <p>
                <span class="description">
                    Save more than 70% when you purchase this add-on as part of the <strong>All Add-On</strong> bundle. 
                </span>
            </p>
            <?php $url = 'https://www.wpsecurityauditlog.com/extensions/all-add-ons-60-off/?utm_source=plugin&utm_medium=emailpage&utm_campaign=notifications'; ?>
            <p>
                <a class="button-primary" href="<?php echo esc_attr($url); ?>" target="_blank"><?php _e('Learn More', 'wp-security-audit-log'); ?></a>
            </p>
        </div>
        <?php
    }
}
