<?php
/**
 * @package Wsal
 * @subpackage Sensors
 * Login/Logout sensor.
 *
 * 1000 User logged in
 * 1001 User logged out
 * 1002 Login failed
 * 1003 Login failed / non existing user
 * 1004 Login blocked
 * 4003 User has changed his or her password
 */
class WSAL_Sensors_LogInOut extends WSAL_AbstractSensor
{
    /**
     * Transient name.
     * WordPress will prefix the name with "_transient_" or "_transient_timeout_" in the options table.
     */
    const TRANSIENT_FAILEDLOGINS = 'wsal-failedlogins-known';
    const TRANSIENT_FAILEDLOGINS_UNKNOWN = 'wsal-failedlogins-unknown';

    /**
     * @var WP_User current user object
     */
    protected $_current_user = null;

    /**
     * Listening to events using WP hooks.
     */
    public function HookEvents()
    {
        add_action('wp_login', array($this, 'EventLogin'), 10, 2);
        add_action('wp_logout', array($this, 'EventLogout'));
        add_action('password_reset', array($this, 'EventPasswordReset'), 10, 2);
        add_action('wp_login_failed', array($this, 'EventLoginFailure'));
        add_action('clear_auth_cookie', array($this, 'GetCurrentUser'), 10);
        add_filter('wp_login_blocked', array($this, 'EventLoginBlocked'), 10, 1);

        // Directory for logged in users log files.
        $user_upload_dir    = wp_upload_dir();
        $user_upload_path   = trailingslashit( $user_upload_dir['basedir'] . '/wp-security-audit-log/failed-logins/' );
        if ( ! $this->CheckDirectory( $user_upload_path ) ) {
            wp_mkdir_p( $user_upload_path );
        }
    }

    /**
     * Sets current user.
     */
    public function GetCurrentUser()
    {
        $this->_current_user = wp_get_current_user();
    }

    /**
     * Event Login.
     */
    public function EventLogin($user_login, $user = null)
    {
        if (empty($user)) {
            $user = get_user_by('login', $user_login);
        }
        $userRoles = $this->plugin->settings->GetCurrentUserRoles($user->roles);
        if ($this->plugin->settings->IsLoginSuperAdmin($user_login)) {
            $userRoles[] = 'superadmin';
        }
        $this->plugin->alerts->Trigger(1000, array(
            'Username' => $user_login,
            'CurrentUserRoles' => $userRoles,
        ), true);
    }

    /**
     * Event Logout.
     */
    public function EventLogout()
    {
        if ($this->_current_user->ID != 0) {
            $this->plugin->alerts->Trigger(1001, array(
                'CurrentUserID' => $this->_current_user->ID,
                'CurrentUserRoles' => $this->plugin->settings->GetCurrentUserRoles($this->_current_user->roles),
            ), true);
        }
    }

    /**
     * Login failure limit count.
     *
     * @return int
     */
    protected function GetLoginFailureLogLimit() {
        return $this->plugin->settings->get_failed_login_limit();
    }

    /**
     * Non-existing Login failure limit count.
     *
     * @return int
     */
    protected function GetVisitorLoginFailureLogLimit() {
        return $this->plugin->settings->get_visitor_failed_login_limit();
    }

    /**
     * Expiration of the transient saved in the WP database.
     * @return integer Time until expiration in seconds from now
     */
    protected function GetLoginFailureExpiration()
    {
        return 12 * 60 * 60;
    }

    /**
     * Check failure limit.
     * @param string $ip IP address
     * @param integer $site_id blog ID
     * @param WP_User $user user object
     * @return boolean passed limit true|false
     */
    protected function IsPastLoginFailureLimit($ip, $site_id, $user)
    {
        $get_fn = $this->IsMultisite() ? 'get_site_transient' : 'get_transient';
        if ( $user ) {
            if ( -1 === (int) $this->GetLoginFailureLogLimit() ) {
                return false;
            } else {
                $dataKnown = $get_fn(self::TRANSIENT_FAILEDLOGINS);
                return ($dataKnown !== false) && isset($dataKnown[$site_id.":".$user->ID.":".$ip]) && ($dataKnown[$site_id.":".$user->ID.":".$ip] >= $this->GetLoginFailureLogLimit());
            }
        } else {
            if ( -1 === (int) $this->GetVisitorLoginFailureLogLimit() ) {
                return false;
            } else {
                $dataUnknown = $get_fn(self::TRANSIENT_FAILEDLOGINS_UNKNOWN);
                return ($dataUnknown !== false) && isset($dataUnknown[$site_id.":".$ip]) && ($dataUnknown[$site_id.":".$ip] >= $this->GetVisitorLoginFailureLogLimit());
            }
        }
    }

    /**
     * Increment failure limit.
     * @param string $ip IP address
     * @param integer $site_id blog ID
     * @param WP_User $user user object
     */
    protected function IncrementLoginFailure($ip, $site_id, $user)
    {
        $get_fn = $this->IsMultisite() ? 'get_site_transient' : 'get_transient';
        $set_fn = $this->IsMultisite() ? 'set_site_transient' : 'set_transient';
        if ($user) {
            $dataKnown = $get_fn(self::TRANSIENT_FAILEDLOGINS);
            if (!$dataKnown) {
                $dataKnown = array();
            }
            if (!isset($dataKnown[$site_id.":".$user->ID.":".$ip])) {
                $dataKnown[$site_id.":".$user->ID.":".$ip] = 1;
            }
            $dataKnown[$site_id.":".$user->ID.":".$ip]++;
            $set_fn(self::TRANSIENT_FAILEDLOGINS, $dataKnown, $this->GetLoginFailureExpiration());
        } else {
            $dataUnknown = $get_fn(self::TRANSIENT_FAILEDLOGINS_UNKNOWN);
            if (!$dataUnknown) {
                $dataUnknown = array();
            }
            if (!isset($dataUnknown[$site_id.":".$ip])) {
                $dataUnknown[$site_id.":".$ip] = 1;
            }
            $dataUnknown[$site_id.":".$ip]++;
            $set_fn(self::TRANSIENT_FAILEDLOGINS_UNKNOWN, $dataUnknown, $this->GetLoginFailureExpiration());
        }
    }

    /**
     * Event Login failure.
     * @param string $username username
     */
    public function EventLoginFailure($username)
    {
        list($y, $m, $d) = explode('-', date('Y-m-d'));

        $ip = $this->plugin->settings->GetMainClientIP();

        $username = array_key_exists('log', $_POST) ? $_POST["log"] : $username;
        $newAlertCode = 1003;
        $user = get_user_by('login', $username);
        $site_id = (function_exists('get_current_blog_id') ? get_current_blog_id() : 0);
        if ($user) {
            $newAlertCode = 1002;
            $userRoles = $this->plugin->settings->GetCurrentUserRoles($user->roles);
            if ($this->plugin->settings->IsLoginSuperAdmin($username)) {
                $userRoles[] = 'superadmin';
            }
        }

        // Check if the alert is disabled from the "Enable/Disable Alerts" section
        if (!$this->plugin->alerts->IsEnabled($newAlertCode)) {
            return;
        }

        if ($this->IsPastLoginFailureLimit($ip, $site_id, $user)) {
            return;
        }

        $objOcc = new WSAL_Models_Occurrence();

        if ($newAlertCode == 1002) {
            if (!$this->plugin->alerts->CheckEnableUserRoles($username, $userRoles)) {
                return;
            }
            $occ = $objOcc->CheckKnownUsers(
                array(
                    $ip,
                    $username,
                    1002,
                    $site_id,
                    mktime(0, 0, 0, $m, $d, $y),
                    mktime(0, 0, 0, $m, $d + 1, $y) - 1
                )
            );
            $occ = count($occ) ? $occ[0] : null;

            if (!empty($occ)) {
                // update existing record exists user
                $this->IncrementLoginFailure($ip, $site_id, $user);
                $new = $occ->GetMetaValue('Attempts', 0) + 1;

                if ( -1 !== (int) $this->GetLoginFailureLogLimit()
                    && $new > $this->GetLoginFailureLogLimit() ) {
                    $new = $this->GetLoginFailureLogLimit() . '+';
                }

                $occ->UpdateMetaValue('Attempts', $new);
                $occ->UpdateMetaValue('Username', $username);

                //$occ->SetMetaValue('CurrentUserRoles', $userRoles);
                $occ->created_on = null;
                $occ->Save();
            } else {
                // create a new record exists user
                $this->plugin->alerts->Trigger($newAlertCode, array(
                    'Attempts' => 1,
                    'Username' => $username,
                    'CurrentUserRoles' => $userRoles
                ));
            }
        } else {
            $occUnknown = $objOcc->CheckUnKnownUsers(
                array(
                    $ip,
                    1003,
                    $site_id,
                    mktime(0, 0, 0, $m, $d, $y),
                    mktime(0, 0, 0, $m, $d + 1, $y) - 1
                )
            );

            $occUnknown = count($occUnknown) ? $occUnknown[0] : null;
            if (!empty($occUnknown)) {
                // update existing record not exists user
                $this->IncrementLoginFailure($ip, $site_id, false);
                $new = $occUnknown->GetMetaValue('Attempts', 0) + 1;

                if ( 'on' === $this->plugin->GetGlobalOption( 'log-visitor-failed-login' ) ) {
                    $link_file = $this->WriteLog( $new, $username );
                }

                if ( -1 !== (int) $this->GetVisitorLoginFailureLogLimit()
                    && $new > $this->GetVisitorLoginFailureLogLimit() ) {
                    $new = $this->GetVisitorLoginFailureLogLimit() . '+';
                }

                $occUnknown->UpdateMetaValue('Attempts', $new);
                if ( ! empty( $link_file ) && 'on' === $this->plugin->GetGlobalOption( 'log-visitor-failed-login' ) ) {
                    $occUnknown->UpdateMetaValue( 'LogFileLink', $link_file );
                } else {
                    $link_file = site_url() . '/wp-admin/admin.php?page=wsal-togglealerts#tab-users-profiles---activity';
                    $occUnknown->UpdateMetaValue( 'LogFileLink', $link_file );
                }
                $occUnknown->created_on = null;
                $occUnknown->Save();
            } else {
                $link_file = site_url() . '/wp-admin/admin.php?page=wsal-togglealerts#tab-users-profiles---activity';
                $log_file_text = ' in a log file';
                if ( 'on' === $this->plugin->GetGlobalOption( 'log-visitor-failed-login' ) ) {
                    $link_file = $this->WriteLog( 1, $username );
                    $log_file_text = ' with the usernames used during these failed login attempts';
                }

                // Create a new record not exists user.
                $this->plugin->alerts->Trigger( $newAlertCode, array(
                    'Attempts' => 1,
                    'LogFileLink' => $link_file,
                    'LogFileText' => $log_file_text,
                ) );
            }
        }
    }

    /**
     * Event changed password.
     * @param WP_User $user user object
     */
    public function EventPasswordReset($user, $new_pass)
    {
        if (!empty($user)) {
            $userRoles = $this->plugin->settings->GetCurrentUserRoles($user->roles);
            $this->plugin->alerts->Trigger(4003, array(
                'Username' => $user->user_login,
                'CurrentUserRoles' => $userRoles
            ), true);
        }
    }

    /**
     * Event login blocked.
     * @param string $username username
     */
    public function EventLoginBlocked($username)
    {
        $user = get_user_by('login', $username);
        $userRoles = $this->plugin->settings->GetCurrentUserRoles($user->roles);

        if ($this->plugin->settings->IsLoginSuperAdmin($username)) {
            $userRoles[] = 'superadmin';
        }
        $this->plugin->alerts->Trigger(1004, array(
            'Username' => $username,
            'CurrentUserRoles' => $userRoles
        ), true);
    }

    /**
     * Write log file.
     *
     * @param int    $attempts - Number of attempt.
     * @param string $username - Username.
     * @author Ashar Irfan
     * @since  2.6.9
     */
    private function WriteLog( $attempts, $username = '' ) {
        $name_file = null;

        // Create/Append to the log file.
        $data = 'Attempts: ' . $attempts . ' — Username: ' . $username;

        $upload_dir = wp_upload_dir();
        $uploads_dir_path = trailingslashit( $upload_dir['basedir'] ) . 'wp-security-audit-log/failed-logins/';
        $uploads_url = trailingslashit( $upload_dir['baseurl'] ) . 'wp-security-audit-log/failed-logins/';

        // Check directory.
        if ( $this->CheckDirectory( $uploads_dir_path ) ) {
            $filename = 'failed_logins_usernames_' . date( 'Ymd' ) . '.log';
            $fp = $uploads_dir_path . $filename;
            $name_file = $uploads_url . $filename;
            if ( ! $file = fopen( $fp, 'a' ) ) {
                $i = 1;
                $file_opened = false;
                do {
                    $fp2 = substr( $fp, 0, -4 ) . '_' . $i . '.log';
                    if ( ! file_exists( $fp2 ) ) {
                        if ( $file = fopen( $fp2, 'a' ) ) {
                            $file_opened = true;
                            $name_file = $uploads_url . substr( $name_file, 0, -4 ) . '_' . $i . '.log';
                        }
                    } else {
                        $latest_filename = $this->GetLastModified( $uploads_dir_path, $filename );
                        $fp_last = $uploads_dir_path . $latest_filename;
                        if ( $file = fopen( $fp_last, 'a' ) ) {
                            $file_opened = true;
                            $name_file = $uploads_url . $latest_filename;
                        }
                    }
                    $i++;
                } while ( ! $file_opened );
            }
            fwrite( $file, sprintf( "%s\n", $data ) );
            fclose( $file );
        }

        return $name_file;
    }
}
