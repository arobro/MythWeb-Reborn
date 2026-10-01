<?php
/**
 * Display/save mythweb session settings
 *
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  Settings
 *
 **/

// Save?
    if (!empty($_POST['save'])) {
    // Save the template
        if (isset($_POST['tmpl']))
            $_SESSION['tmpl'] = $_POST['tmpl'];

    // Save the skin
        if (isset($_POST['skin']))
            $_SESSION['skin'] = $_POST['skin'];

    // Change language?  Make sure we load the new translation file, too.
        if (!empty($_POST['language']) && $_POST['language'] != ($_SESSION['language'] ?? null)){
            $_SESSION['language'] = $_POST['language'];
	    // Unset the date/time formats in session so translation can fill in the 
	    // language specific defaults
            unset($_SESSION['date_statusbar2']);
            unset($_SESSION['date_scheduled2']);
            unset($_SESSION['date_scheduled_popup2']);
            unset($_SESSION['date_recorded2']);
            unset($_SESSION['date_search2']);
            unset($_SESSION['date_listing_key2']);
            unset($_SESSION['date_listing_jump2']);
            unset($_SESSION['date_channel_jump2']);
            unset($_SESSION['date_job_status2']);
            unset($_SESSION['time_format2']);
            Translate::find()->load_translation();
        }

        $path1 = $Path[1] ?? '';
        $path2 = $Path[2] ?? '';
        redirect_browser(module.'/'.$path1.'/'.$path2);
    }

/**
 * Displays a <select> of the available templates
 **/
    function template_select($name = 'tmpl', $selected = null) {
        echo '<select name="'.html_entities($name).'">';
        foreach (array('default') as $tmpl) {
        // Print the option
            echo '<option value="'.html_entities($tmpl).'"';
            if ($selected == $tmpl)
                echo ' SELECTED';
            echo '>'.html_entities(str_replace('_', ' ', $tmpl)).'</option>';
        }
        echo '</select>';
    }

/**
 * Displays a <select> of the available skins
 **/
    function skin_select($name = 'skin', $selected = null) {
        echo '<select id="'.html_entities($name).'" name="'.html_entities($name).'">';
        foreach (get_sorted_files("skins/") as $skin) {
        // Skip the svn directory
            if (in_array($skin, array('.svn')))
                continue;
        // Ignore non-directories
            if (!is_dir("skins/$skin"))
                continue;
        // Print the option
            echo '<option value="'.html_entities($skin).'"';
            if ($selected == $skin)
                echo ' SELECTED';
            echo '>'.html_entities(str_replace('_', ' ', $skin)).'</option>';
        }
        echo '</select>';
    }

/**
 * Displays a <select> of the available languages
 **/
    function language_select() {
        echo '<select name="language">';
        if (isset(Translate::$Languages) && is_array(Translate::$Languages)) {
            foreach (Translate::$Languages as $lang => $details) {
            // Print the option
                echo '<option value="'.html_entities($lang).'"';
                if (($_SESSION['language'] ?? null) == $lang)
                    echo ' SELECTED';
                echo '>'.$details[0].'</option>';
            }
        }
        echo '</select>';
    }