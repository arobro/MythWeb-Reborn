<?php
/**
 * Inline help items and tooltips using LightTooltip.
 *
 * @license     GPL
 *
 * @package     MythWeb
 *
 **/

// Enable/Disable help messages
if (isset($_REQUEST['show_help'])) {
    if ($_REQUEST['show_help'] == 'yes')
        $_SESSION['show help'] = true;
    elseif ($_REQUEST['show_help'] == 'no')
        $_SESSION['show help'] = false;
}

function show_popup($id, $text, $popup_id = NULL) {
     $clean_id = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
     $clean_text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

     $clean_class = htmlspecialchars(' no-double-box', ENT_QUOTES, 'UTF-8');

	return ' id="' . $clean_id . '" data-tooltip="' . $clean_text . '" class="' . $clean_class . '"';
}