<?php
/**
 * This file is part of MythWeb, a php-based interface for MythTV.
 * See README and LICENSE for details.
 *
 * This is the default viewing mode, and shows the current program listings.
 *
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  TV
 *
 **/

// Were we passed a timestamp?  This is going to be the most common occurrence
    if (isset($_REQUEST['time']) && $_REQUEST['time'])
        $list_starttime = intval($_REQUEST['time']);
    elseif(isset($_REQUEST['daytime']) && isset($_REQUEST['date']))
        $list_starttime = unixtime(sprintf('%08d%04d00', $_REQUEST['date'], $_REQUEST['daytime']));
// Did we get passed a date (and probably an hour, too)?
    elseif(isset($_REQUEST['date']) && isset($_REQUEST['hour']))
        $list_starttime = unixtime(sprintf('%08d%02d0000', $_REQUEST['date'], $_REQUEST['hour']));
// Default value - just use the current time
    else
        $list_starttime = time();

// Round *back* to the nearest timeslot size
    $list_starttime -= $list_starttime % (timeslot_size * timeslot_blocks);

// Setup the time slots
    $list_endtime = $list_starttime;
    $Timeslots = array();
    for ($i = 0; $i < num_time_slots; $i++) {
        $Timeslots[]  = $list_endtime;
        $list_endtime += timeslot_size; // skip to the next timeslot
    }

// Set a session variable so other sections know how to get back to this particular page
    $_SESSION['list_time'] = $list_starttime;

// Are we an ajax request?
    if (isset($_REQUEST['ajax']) && $_REQUEST['ajax'])
        require_once tmpl_dir.'list_data.php';
    else
        require_once tmpl_dir.'list.php';

/**
 * Prints a <select> of the available hour range
 **/
    function hour_select($params = '') {
        global $list_starttime;
        echo "<select name=\"hour\" $params>";
        for ($h=0;$h<24;$h++) {
            $timestamp = mktime($h,0,0, (int)date('m', $list_starttime), (int)date('d', $list_starttime), (int)date('Y', $list_starttime));
            echo '<option value="'.$timestamp.'"';
            if ($h == date('H', $list_starttime))
                echo ' SELECTED';
            
            // Format time safely for PHP 8.1+ without breaking legacy custom session formats
			$time_formatted = date(
				$_SESSION['time_format2'] ?? 'g:i A', 
				mktime((int)$h, 0, 0)
			);

            echo '>'.$time_formatted.'</option>';
        }
        echo '</select>';
    }

/**
 * Prints a <select> of the available date range
 **/
    function date_select($params = '') {
        global $db;
        global $list_starttime;
    // Get the available date range
        $min_days = max(-7, (int)$db->query_col('SELECT TO_DAYS(min(starttime)) - TO_DAYS(NOW()) FROM program'));
        $max_days = min(30, (int)$db->query_col('SELECT TO_DAYS(max(starttime)) - TO_DAYS(NOW()) FROM program'));
    // Print out the list
        echo "<select name=\"date\" $params>";
        for ($i=$min_days; $i<=$max_days; $i++) {
            $time = mktime((int)date('G', $list_starttime),0,0, (int)date('m'), (int)date('d') + $i, (int)date('Y'));
            echo "<option value=\"$time\"";
            if ( date('Ymd', $time) == date('Ymd', $list_starttime))
                echo ' SELECTED';

			$date_formatted = date($_SESSION['date_listing_jump2'] ?? 'D, M j', $time);

            echo '>'.$date_formatted.'</option>';
        }
        echo '</select>';
    }