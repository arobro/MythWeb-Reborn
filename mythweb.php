<?php
/**
 * The main brain script for all of MythWeb.
 *
 * @license     GPL
 *
 * @package     MythWeb
 *
 **/

// Add a custom include path?
    if (!empty($_SERVER['include_path']) && $_SERVER['include_path'] != '.') {
        ini_set('include_path', $_SERVER['include_path'].PATH_SEPARATOR.ini_get('include_path'));
    }

// Path to the MythTV bindings that are now installed by the MythTV package
    ini_set('include_path', '/usr/local/share/mythtv/bindings/php/'.PATH_SEPARATOR.'/usr/share/mythtv/bindings/php/'.PATH_SEPARATOR.ini_get('include_path'));

// Init
    require_once 'includes/init.php';

// Ensure $Path exists as an array for PHP 8+ safe indexing
    if (!isset($Path) || !is_array($Path)) {
        $Path = array();
    }

// Handle Feed requests
    if (   isset($Path[0])
        && ($Path[0] == 'rss' || $Path[0] == 'ical')) {
        unset($Path[0]);
        $Path = array_values($Path);
    }

// Standard module?  Pass along the
    if (isset($Path[0]) && Modules::getModule($Path[0])) {
    // Add the current module directory to our search path, so modules can
    // define includes, etc.
        $mod_path = defined('modules_path') ? modules_path : 'modules';
        ini_set('include_path', ini_get('include_path').PATH_SEPARATOR.$mod_path.'/'.$Path[0]);
    // Load the module handler
        require_once 'handler.php';
    }
    elseif (isset($Path[0]) && $Path[0] == 'dcss') {
        $skin_file = isset($Path[1]) ? $Path[1] : '';
        include_once 'skins/default/'.$skin_file;
    }
    elseif (!empty($Path[0]) && preg_match('/\w/', $Path[0]))
        tailored_error('unknown_module');
    else
        require_once 'modules/welcome.php';