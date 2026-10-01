<?php
/**
 * Show the current lineup for a specific channel
 *
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  TV
 *
 **/

// Path-based
    if (isset($Path[2]) && $Path[2]) {
        $_REQUEST['chanid'] = $Path[2];
        if (empty($_REQUEST['date']) && isset($Path[3]))
            $_REQUEST['date'] = $Path[3];
    }

// Chanid?
    $chanid = $_REQUEST['chanid'] ?? null;
    $this_channel = Channel::find($chanid);

// New list date?
    if (!empty($_REQUEST['date'])) {
        if (strlen((string)$_REQUEST['date']) == 8)
            $_REQUEST['date'] = unixtime(sprintf('%08d000000', $_REQUEST['date']));
        $_SESSION['list_time'] = $_REQUEST['date'];
    }

// No channel found
    if (empty($_REQUEST['chanid']) || empty($this_channel->chanid)) {
        redirect_browser(root_url.'tv/list');
    }

    $list_time = $_SESSION['list_time'] ?? time();

// Load the programs for today
    $programs = load_all_program_data(mktime(0, 0, 0, date('n', $list_time), date('j', $list_time), date('Y', $list_time)),
                                      mktime(0, 0, 0, date('n', $list_time), date('j', $list_time) + 1, date('Y', $list_time)),
                                      $this_channel->chanid);

// No data?  Assume today.
    if (count($programs) < 1) {
        $_SESSION['list_time'] = time();
        $list_time = $_SESSION['list_time'];
        $programs = load_all_program_data(mktime(0, 0, 0, date('n', $list_time), date('j', $list_time), date('Y', $list_time)),
                                          mktime(0, 0, 0, date('n', $list_time), date('j', $list_time) + 1, date('Y', $list_time)),
                                          $this_channel->chanid);
        if (count($programs) < 1)
            redirect_browser(root_url.'tv/list');
    }

// Load the class for this page
    require_once tmpl_dir.'channel.php';

// Exit
    exit;


/**
 * Prints a <select> of the available channels
 **/
    function channel_select($params = '', $selected='') {
        $channels = Channel::getChannelList();
        echo "<select name=\"chanid\" $params>";
        foreach ($channels as $chanid) {
            $channel = Channel::find($chanid);
        // Not visible?
            if (empty($channel->visible))
                continue;
        // Print the option
            echo '<option value="', $channel->chanid, '"',
                 ' title="', html_entities($channel->name), '"';
        // Selected?
            if (($channel->chanid == $selected) ||
                (isset($_GET['chanid']) && $channel->chanid == $_GET['chanid']))
                echo ' SELECTED';
        // Print the rest of the content
            echo '>';
            if (!empty($_SESSION["prefer_channum"]))
                echo $channel->channum.'&nbsp;&nbsp;('.html_entities($channel->callsign).')';
            else
                echo html_entities($channel->callsign).'&nbsp;&nbsp;('.$channel->channum.')';
            echo '</option>';
        }
        echo '</select>';
    }

/**
 * Prints a <select> of the available date range
 * Reused *almost* verbatim from modules/tv/list.php
 **/
    function date_select($params = '') {
        global $db;
    // Get the available date range
        $min_days = max(-7, $db->query_col('SELECT TO_DAYS(min(starttime)) - TO_DAYS(NOW()) FROM program'));
        $max_days = min(30, $db->query_col('SELECT TO_DAYS(max(starttime)) - TO_DAYS(NOW()) FROM program'));
    // Print out the list
        echo "<select name=\"date\" $params>";
        for ($i=$min_days; $i<=$max_days; $i++) {
            $time = mktime(0,0,0, date('m'), date('d') + $i, date('Y'));
            $date = date('Ymd', $time);
            echo "<option value=\"$date\"";
            if (isset($_SESSION['list_time']) && $date == date('Ymd', $_SESSION['list_time']))
                echo ' SELECTED';
            $fmt = $_SESSION['date_channel_jump2'] ?? '%Y-%m-%d';
            $formatted_date = date($fmt, $time);
            echo '>'.$formatted_date.'</option>';
        }
        echo '</select>';
    }