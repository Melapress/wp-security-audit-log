<?php
/**
 * @package Wsal
 *
 * Interface used by the Query.
 */
interface WSAL_Adapters_QueryInterface
{
    public function Execute($query);
    public function Count($query);
    public function Delete($query);
}
