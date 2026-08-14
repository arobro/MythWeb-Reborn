<?php
/**
 * Display/save MythWeb session settings
 *
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  Settings
 *
 **/

// Save?
    if (!empty($_POST['save'])) {
    // Save the date formats
        if (isset($_POST['date_statusbar2']))       $_SESSION['date_statusbar2']       = $_POST['date_statusbar2'];
        if (isset($_POST['date_scheduled2']))       $_SESSION['date_scheduled2']       = $_POST['date_scheduled2'];
        if (isset($_POST['date_scheduled_popup2'])) $_SESSION['date_scheduled_popup2'] = $_POST['date_scheduled_popup2'];
        if (isset($_POST['date_recorded2']))        $_SESSION['date_recorded2']        = $_POST['date_recorded2'];
        if (isset($_POST['date_search2']))          $_SESSION['date_search2']          = $_POST['date_search2'];
        if (isset($_POST['date_listing_key2']))     $_SESSION['date_listing_key2']     = $_POST['date_listing_key2'];
        if (isset($_POST['date_listing_jump2']))    $_SESSION['date_listing_jump2']    = $_POST['date_listing_jump2'];
        if (isset($_POST['date_channel_jump2']))    $_SESSION['date_channel_jump2']    = $_POST['date_channel_jump2'];
        if (isset($_POST['time_format2']))          $_SESSION['time_format2']          = $_POST['time_format2'];
    // Recorded Programs
        $_SESSION['recorded_pixmaps']   = !empty($_POST['recorded_pixmaps']);
        if (isset($_POST['file_url_override']))  $_SESSION['file_url_override']  = trim(preg_replace('#^file://#', '', $_POST['file_url_override']));
    // Guide Settings
        $_SESSION['guide_favonly']    = !empty($_POST['guide_favonly']);
        $_SESSION['timeslot_size']    = max(5, intval($_POST['timeslot_size'] ?? 0)) * 60;
        $_SESSION['num_time_slots']   = max(3, intval($_POST['num_time_slots'] ?? 0));
        $_SESSION['timeslot_blocks']  = max(1, intval($_POST['timeslot_blocks'] ?? 0));
        $_SESSION['timeslotbar_skip'] = max(1, intval($_POST['timeslotbar_skip'] ?? 0));
        $_SESSION['max_stars']        = max(3, intval($_POST['max_stars'] ?? 0));
        $_SESSION['star_character']   = $_POST['star_character'] ?? '';
        $_SESSION['recorded_paging']  = $_POST['recorded_paging'] ?? '';
    }