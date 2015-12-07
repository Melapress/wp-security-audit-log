<?php

class WSAL_Loggers_Database extends WSAL_AbstractLogger
{

    public function __construct(WpSecurityAuditLog $plugin)
    {
        parent::__construct($plugin);
        $plugin->AddCleanupHook(array($this, 'CleanUp'));
    }

    public function Log($type, $data = array(), $date = null, $siteid = null, $migrated = false)
    { 
        // is this a php alert, and if so, are we logging such alerts?
        if ($type < 0010 && !$this->plugin->settings->IsPhpErrorLoggingEnabled()) return;

        // create new occurrence
        $occ = new WSAL_Models_Occurrence();
        $occ->is_migrated = $migrated;
        $occ->created_on = $date;
        $occ->alert_id = $type;
        $occ->site_id = !is_null($siteid) ? $siteid
            : (function_exists('get_current_blog_id') ? get_current_blog_id() : 0);
        $occ->Save();

        // set up meta data
        $occ->SetMeta($data);
        // Inject for promoting the paid add-ons
        $this->AlertInject($occ);
    }

    public function CleanUp()
    {
        $now = current_time('timestamp');
        $max_sdate = $this->plugin->settings->GetPruningDate();
        $max_count = $this->plugin->settings->GetPruningLimit();
        $is_date_e = $this->plugin->settings->IsPruningDateEnabled();
        $is_limt_e = $this->plugin->settings->IsPruningLimitEnabled();

        if (!$is_date_e && !$is_limt_e) {
            return;
        } // pruning disabled
        $occ = new WSAL_Models_Occurrence();
        $cnt_items = $occ->Count();

        // Check if there is something to delete
        if ($is_limt_e && ($cnt_items < $max_count)) {
            return;
        }

        $max_stamp = $now - (strtotime($max_sdate) - $now);
        $max_items = (int)max(($cnt_items - $max_count) + 1, 0);

        $query = new WSAL_Models_OccurrenceQuery();
        $query->addOrderBy("created_on", false);
        // TO DO Fixing data
        if ($is_date_e) $query->addCondition('created_on <= %s', intval($max_stamp));
        if ($is_limt_e) $query->setLimit($max_items);

        if (($max_items-1) == 0) return; // nothing to delete

        $result = $query->getAdapter()->GetSqlDelete($query);
        $deletedCount = $query->getAdapter()->Delete($query);

        if ($deletedCount == 0) return; // nothing to delete
        // keep track of what we're doing
        $this->plugin->alerts->Trigger(0003, array(
                'Message' => 'Running system cleanup.',
                'Query SQL' => $result['sql'],
                'Query Args' => $result['args'],
            ), true);

        // notify system
        do_action('wsal_prune', $deletedCount, vsprintf($result['sql'], $result['args']));
    }

    private function AlertInject($occurrence)
    {
        if (($occurrence->getId() % 2) == 0) {
            $promoToSend = $this->GetPromoAlert();
            // WIP, to do: add message and link
            $link = '<a href="'.$promoToSend['link'].'" target="_blank">'.$promoToSend['name'].'</a>';
            $this->log(9999, array(
                'ClientIP' => '127.0.0.1',
                'Username' => 'Plugin',
                'PromoMessage' => sprintf($promoToSend['message1'], $link).'<br>'.sprintf($promoToSend['message2'], $link),
                'PromoName' => $promoToSend['name']
            ));
        }
    }

    private function GetPromoAlert()
    {
        $lastPromoSentId = $this->plugin->GetGlobalOption('promo-send-id');
        $lastPromoSentId = empty($lastPromoSentId) ? 0 : $lastPromoSentId;
        $promoToSend = null;
        $aPromoAlert = array();
        if (!class_exists('WSAL_NP_Plugin')) {
            $aPromoAlert[] = array(
                'name' => 'Email Notifications Add-on',
                'message1' => 'Get notified instantly via email of important changes and actions on your WordPress. <strong>%s</strong>.',
                'message2' => 'Would you like to receive an email when a user changes his password, or when someone logs in during odd hours or from an unusual location? <strong>Get the %s.</strong>',
                'link' => 'http://www.wpsecurityauditlog.com/extensions/wordpress-email-notifications-add-on/?utm_source=promoalert&utm_medium=auditviewer&utm_campaign=emailnotifications'
            );
        }
        if (!class_exists('WSAL_SearchExtension')) {
            $aPromoAlert[] = array(
                'name' => 'Search & Filters Add-on',
                'message1' => 'Automatically find a specific change or action in the WordPress audit log with the <strong>%s</strong>.',
                'message2' => 'Add the Search functionality to the WordPress audit log to find a specific change or action automatically. <strong>Use the %s</strong>.',
                'link' => 'http://www.wpsecurityauditlog.com/extensions/search-add-on-for-wordpress-security-audit-log/?utm_source=promoalert&utm_medium=auditviewer&utm_campaign=search'
            );
        }
        if (!class_exists('WSAL_Rep_Plugin')) {
            $aPromoAlert[] = array(
                'name' => 'Reports Add-on',
                'message1' => 'Generate WordPress reports for management purposes and to meet regulatory compliance requirements your business needs to adhere to with the <strong>%s</strong>.',
                'message2' => 'Generate WordPress reports to monitor users’ productivity and meet legal and regulatory compliance requirements with the <strong>%s</strong>.',
                'link' => 'http://www.wpsecurityauditlog.com/extensions/compliance-reports-add-on-for-wordpress/?utm_source=promoalert&utm_medium=auditviewer&utm_campaign=reports'
            );
        }
        if (!class_exists('WSAL_Ext_Plugin')) {
            $aPromoAlert[] = array(
                'name' => 'External DB Add-on',
                'message1' => 'Store the WordPress audit log in an external database to boost both the performance and security of your WordPress. <strong>Install the %s</strong>.',
                'message2' => 'Meet regulatory compliance requirements your business needs to adhere to. <strong>Store the WordPress audit log on an external database</strong>.',
                'link' => 'http://www.wpsecurityauditlog.com/extensions/external-database-for-wp-security-audit-log/?utm_source=promoalert&utm_medium=auditviewer&utm_campaign=externaldb'
            );
        }
        if (!empty($aPromoAlert)) {
            $aPromoAlert[] = array(
                'name' => 'Add-Ons',
                'message1' => 'Add email alerts, generate reports and add the search functionality to your WordPress audit log. <strong>%s</strong>.',
                'message2' => 'Buy all the WP Security Audit Log add-ons as bundle and <strong>benefit from a 60&percnt; discount</strong>. All <strong>%s</strong> for 1 website only cost $99',
                'link' => 'http://www.wpsecurityauditlog.com/plugin-extensions/?utm_source=promoalert&utm_medium=auditviewer&utm_campaign=alladdons'
            );

            $promoToSend = $aPromoAlert[$lastPromoSentId];

            if ($lastPromoSentId < count($aPromoAlert)-1) {
                $lastPromoSentId++;
            } else {
                $lastPromoSentId = 0;
            }
            $this->plugin->SetGlobalOption('promo-send-id', $lastPromoSentId);
        }
        return $promoToSend;
    }

}
