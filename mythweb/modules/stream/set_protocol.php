<?php
/**
 * Display/save mythweb session settings
 *
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  Stream
 *
 **/

// Save?
    if (!empty($_POST['save'])) {
    // Save the protocol choice
        $_SESSION['stream']['force_http']                = !empty($_REQUEST['force_http']);
        $_SESSION['stream']['include_user_and_password'] = !empty($_REQUEST['include_user_and_password']);
        $_SESSION['stream']['force_http_port']           = isset($_REQUEST['force_http_port']) ? intVal($_REQUEST['force_http_port']) : 0;
        if ($_SESSION['stream']['force_http_port'] < 1)
            $_SESSION['stream']['force_http_port'] = '';
    }