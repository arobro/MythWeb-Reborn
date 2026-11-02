<?php
/**
 * Display/save mythweb default settings
 *
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  Settings
 *
 **/

// Save?
    if (!empty($_POST['save'])) {
    // Some global mythweb settings
        $_SESSION['prefer_channum']      = setting('WebPrefer_Channum', null, !empty($_POST['prefer_channum']) ? 1 : 0);
        $_SESSION['show_popup_info']     = !empty($_POST['show_popup_info'])     ? 1 : 0;
        $_SESSION['show_channel_icons']  = !empty($_POST['show_channel_icons'])  ? 1 : 0;
        $_SESSION['sortby_channum']      = !empty($_POST['sortby_channum'])      ? 1 : 0;
        $_SESSION['genre_colors']        = !empty($_POST['genre_colors'])        ? 1 : 0;
        $_SESSION['show_video_covers']   = !empty($_POST['show_video_covers'])   ? 1 : 0;
        $_SESSION['cache_engine']        = $_POST['cache_engine'] ?? null;
    }

    if (!empty($_POST['set_current_session_as_default'])) {
        $backup = $_SESSION;
        foreach (['authenticated','user_token','csrf_token','login_nonces','last_activity'] as $k) {
            unset($_SESSION[$k]);
        }
        $session_data = session_encode();
        $_SESSION = $backup;               // restore so the current user stays logged in
        sess_write('default', $session_data);
    } elseif (!empty($_POST['clear_current_default_session'])) {
        sess_destroy('default');
    }

// These settings are limited to MythWeb itself
    $Settings_Hosts = 'MythWeb';