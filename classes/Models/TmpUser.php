<?php
/**
 * @package Wsal
 * Model tmp_users
 *
 * Model used for the Temporary WP_users table.
 */
class WSAL_Models_TmpUser extends WSAL_Models_ActiveRecord
{
    public $id = 0;
    public $user_login = '';
    protected $adapterName = "TmpUser";
}
