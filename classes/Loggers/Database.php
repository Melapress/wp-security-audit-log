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
        if ($type != 9999) {
            $this->AlertInject($occ);
        }
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
        $count = $this->CheckPromoToShow();
        if ($count && $occurrence->getId() != 0) {
            if (($occurrence->getId() % $count) == 0) {
                $promoToSend = $this->GetPromoAlert();
                if (!empty($promoToSend)) {
                    $link = '<a href="'.$promoToSend['link'].'" target="_blank">'.$promoToSend['name'].'</a>';
                    $this->Log(9999, array(
                        'ClientIP' => '127.0.0.1',
                        'Username' => 'Plugin',
                        'PromoMessage' => sprintf($promoToSend['message'], $link),
                        'PromoName' => $promoToSend['name']
                    ));
                }
            }
        }
    }

    private function GetPromoAlert()
    {
        $lastPromoSentId = $this->plugin->GetGlobalOption('promo-send-id');
        $lastPromoSentId = empty($lastPromoSentId) ? 0 : $lastPromoSentId;
        $promoToSend = null;
        $aPromoAlerts = $this->GetActivePromoText();
        if (!empty($aPromoAlerts)) {
            $promoToSend = isset($aPromoAlerts[$lastPromoSentId]) ? $aPromoAlerts[$lastPromoSentId] : $aPromoAlerts[0];

            if ($lastPromoSentId < count($aPromoAlerts)-1) {
                $lastPromoSentId++;
            } else {
                $lastPromoSentId = 0;
            }
            $this->plugin->SetGlobalOption('promo-send-id', $lastPromoSentId);
        }
        return $promoToSend;
    }

    private function GetActivePromoText()
    {
        $aPromoAlerts = array();
        for ($i = 1; $i <= 2; $i++) {
            // Generic Premium Update
            if ($i == 1) {
                $msg = 'Add email alerts, generate compliance reports and add the search functionality to your WordPress audit log with the <strong>%s</strong>.';
            } else {
                $msg = 'Buy all the WP Security Audit Log premium add-ons as bundle and <strong>benefit from a 60&percnt; discount</strong>. <strong>All %s</strong> for 1 website only cost $89.';
            }
            $aPromoAlerts[] = array(
                'name' => 'Premium Add-Ons',
                'message' => '<strong>60&percnt; OFF On All Premium Add-Ons and Support Bundle</strong><br>'. $msg,
                'link' => 'http://www.wpsecurityauditlog.com/plugin-extensions/?utm_source=auditviewer&utm_medium=allpromoalert&utm_campaign=plugin'
            );
            // Email Add-On
            if (!class_exists('WSAL_NP_Plugin')) {
                if ($i == 1) {
                    $msg = 'Get notified instantly via email of important changes and actions on your WordPress with the <strong>%s</strong>.';
                } else {
                    $msg = 'Receive an email when a user changes a password, when someone logs in during odd hours or from an unusual location with the <strong>%s</strong>';
                }
                $aPromoAlerts[] = array(
                    'name' => 'Email Notifications Add-on',
                    'message' => '<strong>Email Notifications for WordPress</strong><br>'. $msg,
                    'link' => 'http://www.wpsecurityauditlog.com/extensions/wordpress-email-notifications-add-on/?utm_source=auditviewer&utm_medium=emailpromoalert&utm_campaign=plugin'
                );
            }
            // Search Add-On
            if (!class_exists('WSAL_SearchExtension')) {
                if ($i == 1) {
                    $msg = 'Easily find a specific change or action in the WordPress audit log with the <strong>%s</strong>.';
                } else {
                    $msg = 'Add the Search functionality to the WordPress audit log to find a specific change or action easily within seconds. Use the <strong>%s</strong>';
                }
                $aPromoAlerts[] = array(
                    'name' => 'Search & Filters Add-on',
                    'message' => '<strong>Search and Filtering for WordPress Audit Log</strong><br>'. $msg,
                    'link' => 'http://www.wpsecurityauditlog.com/extensions/search-add-on-for-wordpress-security-audit-log/?utm_source=auditviewer&utm_medium=searchpromoalert&utm_campaign=plugin'
                );
            }
            // Reports Add-On
            if (!class_exists('WSAL_Rep_Plugin')) {
                if ($i == 1) {
                    $msg = 'Generate WordPress reports for management and to meet regulatory compliance requirements your business needs to adhere to with the <strong>%s</strong>.';
                } else {
                    $msg = 'Generate WordPress reports to ensure users’ productivity and meet legal and regulatory compliance requirements with the <strong>%s</strong>';
                }
                $aPromoAlerts[] = array(
                    'name' => 'Reports Add-on',
                    'message' => '<strong>WordPress Reports Add-On</strong><br>'. $msg,
                    'link' => 'http://www.wpsecurityauditlog.com/extensions/compliance-reports-add-on-for-wordpress/?utm_source=auditviewer&utm_medium=reportspromoalert&utm_campaign=plugin'
                );
            }
            // External DB Add-On
            if (!class_exists('WSAL_Ext_Plugin')) {
                if ($i == 1) {
                    $msg = 'Store the WordPress audit log in an external database to boost the performance and security of your WordPress. <strong>%s</strong>.';
                } else {
                    $msg = 'Meet regulatory compliance requirements your business needs to adhere to. <strong>%s</strong>';
                }
                $aPromoAlerts[] = array(
                    'name' => 'External DB Add-on',
                    'message' => '<strong>External Database for WordPress Audit Log</strong><br>'. $msg,
                    'link' => 'http://www.wpsecurityauditlog.com/extensions/external-database-for-wp-security-audit-log/?utm_source=auditviewer&utm_medium=extdbpromoalert&utm_campaign=plugin'
                );
            }
            if (count($aPromoAlerts) == 1) {
                unset($aPromoAlerts[0]);
            }
        }
        if (count($aPromoAlerts) >= 1) {
            return $aPromoAlerts;
        } else {
            return null;
        }
    }

    private function CheckPromoToShow()
    {
        $promoToShow = null;
        if (!class_exists('WSAL_NP_Plugin')) {
            $promoToShow[] = true;
        }
        if (!class_exists('WSAL_SearchExtension')) {
            $promoToShow[] = true;
        }
        if (!class_exists('WSAL_Rep_Plugin')) {
            $promoToShow[] = true;
        }
        if (!class_exists('WSAL_Ext_Plugin')) {
            $promoToShow[] = true;
        }

        if (empty($promoToShow)) {
            return null;
        }
        return (count($promoToShow) == 4) ? 100 : 175;
    }
}
