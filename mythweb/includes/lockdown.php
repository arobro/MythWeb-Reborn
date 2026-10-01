<?php
/**
 * To attempt to curve the massive amounts of (unintentionally) open mythweb installs, we are attempting to
 * protect the users by having a automatic lockdown if we detect a 'bot' or if it's specifically requested by a user
 *
 * You can disable this feature with the apache env var of MYTHWEB_LOCKDOWN_DISABLE being set to true
 *
 * @license     GPL
 *
 * @package     MythWeb
 *
 **/

    $lockdown_disabled = isset($_SERVER['MYTHWEB_LOCKDOWN_DISABLE']) && (bool)$_SERVER['MYTHWEB_LOCKDOWN_DISABLE'];
    $user_agent        = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

    if (!$lockdown_disabled) {
        $bot_keywords = ['bot', 'spider', 'crawler', 'search', 'yahoo'];
        $is_bot       = false;

        foreach ($bot_keywords as $keyword) {
            if (str_contains($user_agent, $keyword)) {
                $is_bot = true;
                break;
            }
        }

        if ($is_bot || isset($_GET['TRIGGER_MYTHWEB_LOCKDOWN'])) {
            touch('lockdown');
        }
    }

    if (!$lockdown_disabled && file_exists('lockdown')) {
        tailored_error('lockdown');
    }