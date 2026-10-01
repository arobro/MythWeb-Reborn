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

if (isset($_REQUEST['start']) && $_REQUEST['start'] !== '') {
    $list_starttime = intval($_REQUEST['start']);
} elseif (isset($_REQUEST['time']) && $_REQUEST['time']) {
    $list_starttime = intval($_REQUEST['time']);
} elseif (isset($_REQUEST['daytime']) && isset($_REQUEST['date'])) {
    $list_starttime = parse_epg_timestamp($_REQUEST['date'], $_REQUEST['daytime']);
} elseif (isset($_REQUEST['date']) && isset($_REQUEST['hour'])) {
    $list_starttime = intval($_REQUEST['hour']);
} else {
    $list_starttime = time();
}

$list_starttime -= ($list_starttime % 900);
$_SESSION['list_time'] = $list_starttime;

/**
 * Shared helper to fetch EPG structure
 */
function fetch_epg_data($starttime, $range_hours) {
    $range_hours = max(1, min(24, intval($range_hours)));
    $starttime   = intval($starttime);
    $starttime  -= ($starttime % 3600);
    $endtime     = $starttime + ($range_hours * 3600);
    $Callsigns   = Channel::getCallsignList();
    $epg_data    = array();

    if (!is_array($Callsigns) || empty($Callsigns)) {
        return $epg_data;
    }

    foreach ($Callsigns as $chanid) {
        $channel = Channel::find($chanid);
        if (!$channel || strlen($channel->channum) < 1) continue;

        $programs = load_all_program_data($starttime, $endtime, $channel->chanid);
        $programmes = array();

        $pointer = $starttime;

        if (is_array($programs) || is_object($programs)) {
            $program_list = is_array($programs) ? $programs : iterator_to_array($programs);
            usort($program_list, function($a, $b) {
                return $a->starttime <=> $b->starttime;
            });

            foreach ($program_list as $program) {
                if ($program->starttime >= $endtime || $program->endtime <= $starttime) {
                    continue;
                }

                $start_time = intval($program->starttime);
                $end_time   = intval($program->endtime);

                if ($start_time > $pointer) {
                    $programmes[] = array(
                        'title'           => 'NO DATA',
                        'subTitle'        => '',
                        'category'        => '',
                        'css_class'       => 'no-data-card',
                        'start'           => $pointer,
                        'stop'            => $start_time,
                        'duration'        => ($start_time - $pointer),
                        'hdtv'            => false,
                        'movie_info'      => '',
                        'previouslyshown' => false,
                        'is_nodata'       => true
                    );
                    $pointer = $start_time;
                }

                $movie_info = '';
                if (isset($program->category_type) && (strcasecmp($program->category_type, 'movie') == 0 || strcasecmp($program->category_type, 'film') == 0)) {
                    $parens_arr = array();
                    if (!empty($program->airdate) && $program->airdate > 0) {
                        $parens_arr[] = sprintf('%4d', $program->airdate);
                    }
                    if (!empty($program->rating)) {
                        $parens_arr[] = htmlspecialchars($program->rating, ENT_QUOTES, 'UTF-8');
                    }
                    if (!empty($program->starstring) && strpos($program->title, '★') === false && strpos($program->title, '&#9733;') === false) {
                        $parens_arr[] = $program->starstring;
                    }
                    if (!empty($parens_arr)) {
                        $movie_info = '(' . implode(', ', $parens_arr) . ')';
                    }
                }

                $programmes[] = array(
                    'title'           => strval($program->title ?? ''),
                    'subTitle'        => strval($program->subtitle ?? ''),
                    'category'        => strval($program->category ?? ''),
                    'css_class'       => strval($program->css_class ?? ''),
                    'start'           => $start_time,
                    'stop'            => $end_time,
                    'duration'        => ($end_time - $start_time),
                    'hdtv'            => !empty($program->hdtv),
                    'movie_info'      => $movie_info,
                    'previouslyshown' => !empty($program->previouslyshown),
                    'is_nodata'       => false
                );

                $pointer = max($pointer, $end_time);
            }
        }

        if ($pointer < $endtime) {
            $programmes[] = array(
                'title'           => 'NO DATA',
                'subTitle'        => '',
                'category'        => '',
                'css_class'       => 'no-data-card',
                'start'           => $pointer,
                'stop'            => $endtime,
                'duration'        => ($endtime - $pointer),
                'hdtv'            => false,
                'movie_info'      => '',
                'previouslyshown' => false,
                'is_nodata'       => true
            );
        }

        $epg_data[] = array(
            'id'         => strval($channel->chanid),
            'name'       => !empty($channel->name) ? $channel->name : $channel->callsign,
            'callsign'   => $channel->callsign,
            'channum'    => $channel->channum,
            'icon'       => !empty($channel->icon) ? $channel->icon : '',
            'programmes' => $programmes
        );
    }

    usort($epg_data, function($a, $b) {
        return strnatcasecmp($a['channum'], $b['channum']);
    });

    return $epg_data;
}

if (isset($_REQUEST['ajax']) && $_REQUEST['ajax']) {
    $range = isset($_REQUEST['range']) ? intval($_REQUEST['range']) : 4;
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(fetch_epg_data($list_starttime, $range), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
} else {
    $initial_epg_data = fetch_epg_data($list_starttime, 4);
    require_once tmpl_dir.'list_tmpl.php';
}

function hour_select($params = '') {
    global $list_starttime;
    echo "<select name=\"hour\" $params>";
    for ($h = 0; $h < 24; $h++) {
        $timestamp = mktime($h, 0, 0, (int)date('m', $list_starttime), (int)date('d', $list_starttime), (int)date('Y', $list_starttime));
        echo '<option value="' . $timestamp . '"' . ($h == date('H', $list_starttime) ? ' SELECTED' : '') . '>';
        echo date($_SESSION['time_format2'] ?? 'g:i A', mktime($h, 0, 0));
        echo '</option>';
    }
    echo '</select>';
}

function date_select($params = '') {
    global $db, $list_starttime;

    $min = max(-7, (int) $db->query_col('SELECT DATEDIFF(MIN(starttime), CURDATE()) FROM program'));
    $max = min(30, (int) $db->query_col('SELECT DATEDIFF(MAX(starttime), CURDATE()) FROM program'));
    $curr = date('Ymd', $list_starttime);
    $fmt = $_SESSION['date_listing_jump2'] ?? 'D, M j';

    echo "<select name=\"date\" $params>";
    for ($i = $min; $i <= $max; $i++) {
        $time = mktime((int) date('G', $list_starttime), 0, 0, (int) date('m', $list_starttime), (int) date('d', $list_starttime) + $i, (int) date('Y', $list_starttime));
        $val = date('Ymd', $time);
        $sel = ($val === $curr) ? ' selected' : '';
        echo "<option value=\"$val\"$sel>" . date($fmt, $time) . "</option>";
    }
    echo '</select>';
}

/** Converts a date string (YYYYMMDD) and daytime integer (HHMM) into a Unix timestamp. */
function parse_epg_timestamp($date_input, $daytime_input) {
    $date_str = sprintf('%08d', (int)$date_input);
    $daytime  = (int)$daytime_input;

    $year   = (int)substr($date_str, 0, 4);
    $month  = (int)substr($date_str, 4, 2);
    $day    = (int)substr($date_str, 6, 2);
    $hour   = (int)($daytime / 100);
    $minute = $daytime % 100;

    $dt = (new DateTimeImmutable())
        ->setTimezone(new DateTimeZone(date_default_timezone_get()))
        ->setDate($year, $month, $day)
        ->setTime($hour, $minute, 0);

    return $dt->getTimestamp();
}