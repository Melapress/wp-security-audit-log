<?php

class WSAL_Views_Search extends WSAL_AbstractView {
    
    public function GetTitle()
    {
        return __('Search Add-On', 'wp-security-audit-log');
    }
    
    public function GetIcon()
    {
        return 'dashicons-external';
    }
    
    public function GetName()
    {
        return __('Search', 'wp-security-audit-log');
    }
    
    public function GetWeight()
    {
        return 9;
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
                <?php _e('Do free-text based searches for specific activity in the WordPress audit trail. You can also use the built-in filters to fine-tune your searches.', 'wp-security-audit-log'); ?>
            </p>
            <?php $url = 'https://www.wpsecurityauditlog.com/extensions/search-add-on-for-wordpress-security-audit-log/?utm_source=plugin&utm_medium=searchpage&utm_campaign=search'; ?>
            <p>
                <a class="button-primary" href="<?php echo esc_attr($url); ?>" target="_blank"><?php _e('Learn More', 'wp-security-audit-log'); ?></a>
            </p>
            <p>
                <span class="description">
                    Save more than 70% when you purchase this add-on as part of the <strong>All Add-On</strong> bundle.
                </span>
            </p>
            <?php $url = 'https://www.wpsecurityauditlog.com/extensions/all-add-ons-60-off/?utm_source=plugin&utm_medium=searchpage&utm_campaign=search'; ?>
            <p>
                <a class="button-primary" href="<?php echo esc_attr($url); ?>" target="_blank"><?php _e('Learn More', 'wp-security-audit-log'); ?></a>
            </p>
        </div>
        <?php
    }
}
