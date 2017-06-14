<?php
/**
 * @package Wsal
 * @subpackage Sensors
 * Database sensor.
 *
 * 5010 Plugin created tables
 * 5011 Plugin modified tables structure
 * 5012 Plugin deleted tables
 * 5013 Theme created tables
 * 5014 Theme modified tables structure
 * 5015 Theme deleted tables
 * 5016 Unknown component created tables
 * 5017 Unknown component modified tables structure
 * 5018 Unknown component deleted tables
 */
class WSAL_Sensors_Database extends WSAL_AbstractSensor
{
    /**
     * Listening to events using WP hooks.
     */
    public function HookEvents()
    {
        add_action('dbdelta_queries', array($this, 'EventDBDeltaQuery'));
        add_action('query', array($this, 'EventDropQuery'));
    }

    /**
     * Checks for drop query.
     * @param WP_Query $query query object
     */
    public function EventDropQuery($query)
    {
        $table_names = array();
        $str = explode(" ", $query);

        if (preg_match("|DROP TABLE ([^ ]*)|", $query)) {
            if (!empty($str[4])) {
                array_push($table_names, $str[4]);
            } else {
                array_push($table_names, $str[2]);
            }
            $actype = basename($_SERVER['SCRIPT_NAME'], '.php');
            $alertOptions = $this->GetActionType($actype);
        }

        if (!empty($table_names)) {
            $event_code = $this->GetEventQueryType($actype, "delete");
            $alertOptions["TableNames"] = implode(",", $table_names);
            $this->plugin->alerts->Trigger($event_code, $alertOptions);
        }
        return $query;
    }
    
    /**
     * Checks DB Delta queries.
     * @param array $queries array of query
     */
    public function EventDBDeltaQuery($queries)
    {
        $typeQueries = array(
            "create" => array(),
            "update" => array(),
            "delete" => array()
        );
        global $wpdb;

        foreach ($queries as $qry) {
            $str = explode(" ", $qry);
            if (preg_match("|CREATE TABLE ([^ ]*)|", $qry)) {
                if ($wpdb->get_var("SHOW TABLES LIKE '" . $str[2] . "'") != $str[2]) {
                    //some plugins keep trying to create tables even when they already exist- would result in too many alerts
                    array_push($typeQueries['create'], $str[2]);
                }
            } else if (preg_match("|ALTER TABLE ([^ ]*)|", $qry)) {
                array_push($typeQueries['update'], $str[2]);
            } else if (preg_match("|DROP TABLE ([^ ]*)|", $qry)) {
                if (!empty($str[4])) {
                    array_push($typeQueries['delete'], $str[4]);
                } else {
                    array_push($typeQueries['delete'], $str[2]);
                }
            }
        }

        if (!empty($typeQueries["create"]) || !empty($typeQueries["update"]) || !empty($typeQueries["delete"])) {
            $actype = basename($_SERVER['SCRIPT_NAME'], '.php');
            $alertOptions = $this->GetActionType($actype);

            foreach ($typeQueries as $queryType => $tableNames) {
                if (!empty($tableNames)) {
                    $event_code = $this->GetEventQueryType($actype, $queryType);
                    $alertOptions["TableNames"] = implode(",", $tableNames);
                    $this->plugin->alerts->Trigger($event_code, $alertOptions);
                }
            }
        }

        return $queries;
    }
    
    /**
     * Get code alert by action and type query.
     * @param string $type_action (plugins, themes or unknown)
     * @param string $type_query (create, update or delete)
     */
    protected function GetEventQueryType($type_action, $type_query)
    {
        switch ($type_action) {
            case 'plugins':
                if ($type_query == 'create') return 5010;
                else if ($type_query == 'update') return 5011;
                else if ($type_query == 'delete') return 5012;
            case 'themes':
                if ($type_query == 'create') return 5013;
                else if ($type_query == 'update') return 5014;
                else if ($type_query == 'delete') return 5015;
            default:
                if ($type_query == 'create') return 5016;
                else if ($type_query == 'update') return 5017;
                else if ($type_query == 'delete') return 5018;
        }
    }

    /**
     * Get info by action type.
     * @param string $actype (plugins, themes or unknown)
     */
    protected function GetActionType($actype)
    {
        $is_themes = $actype == 'themes';
        $is_plugins = $actype == 'plugins';
        //Action Plugin Component
        $alertOptions = array();
        if ($is_plugins) {
            if (isset($_REQUEST['plugin'])) {
                $pluginFile = $_REQUEST['plugin'];
            } else {
                $pluginFile = $_REQUEST['checked'][0];
            }
            $pluginName = basename($pluginFile, '.php');
            $pluginName = str_replace(array('_', '-', '  '), ' ', $pluginName);
            $pluginName = ucwords($pluginName);
            $alertOptions["Plugin"] = (object)array(
                'Name' => $pluginName,
            );
        //Action Theme Component
        } else if ($is_themes) {
            if (isset($_REQUEST['theme'])) {
                $themeName = $_REQUEST['theme'];
            } else {
                $themeName = $_REQUEST['checked'][0];
            }
            $themeName = str_replace(array('_', '-', '  '), ' ', $themeName);
            $themeName = ucwords($themeName);
            $alertOptions["Theme"] = (object)array(
                'Name' => $themeName,
            );
        //Action Unknown Component
        } else {
            $alertOptions["Component"] = "Unknown";
        }

        return $alertOptions;
    }
}
