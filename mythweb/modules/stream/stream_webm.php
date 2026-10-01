<?php
/**
 * MythWeb Streaming/Download module - WebM Transcoder
 *
 * @license     GPL
 * @package     MythWeb
 * @subpackage  Stream
 */

// Round to the nearest even integer
function round_even($in) {
    $n = floor($in);
    return ($n % 2 === 0) ? $n : ceil($in);
}

// Global PID variable for shutdown handlers
$ffmpeg_pid = null;

// Register process cleanup handlers for smooth process killing
if (function_exists('pcntl_signal')) {
    declare(ticks = 1);
    function shutdown_handler() {
        global $ffmpeg_pid;
        if ($ffmpeg_pid && function_exists('posix_kill')) {
            posix_kill($ffmpeg_pid, SIGTERM);
        }
    }
    pcntl_signal(SIGTERM, 'shutdown_handler');
    pcntl_signal(SIGINT, 'shutdown_handler');
}

register_shutdown_function(function() {
    global $ffmpeg_pid;
    if ($ffmpeg_pid && function_exists('posix_kill')) {
        posix_kill($ffmpeg_pid, SIGTERM);
    }
});

// 1. Locate FFmpeg binary on the system
$ffmpeg = '';
$found_ffmpeg = null;
$found_mythffmpeg = null;
$found_darwin = null;

$envPath = getenv('PATH') ?: '/usr/bin:/bin';
$paths = array_unique(array_merge(explode(':', $envPath . ':/usr/local/bin:/usr/bin'), ['.']));

foreach ($paths as $path) {
    if (!$found_ffmpeg && file_exists("$path/ffmpeg")) {
        $found_ffmpeg = "$path/ffmpeg";
    }
    if (!$found_mythffmpeg && file_exists("$path/mythffmpeg")) {
        $found_mythffmpeg = "$path/mythffmpeg";
    }
    if (!$found_darwin && PHP_OS_FAMILY === 'Darwin' && file_exists("$path/ffmpeg.app")) {
        $found_darwin = "$path/ffmpeg.app";
    }
}

$ffmpeg = $found_ffmpeg ?? $found_mythffmpeg ?? $found_darwin ?? '';

if (empty($ffmpeg)) {
    header("HTTP/1.1 500 Internal Server Error");
    exit("ffmpeg executable not found on server.");
}

// 2. Load conversion settings using MythWeb's $db object
$width    = 0;
$vbitrate = 0;
$abitrate = 0;

if (isset($db)) {
    // Width
    $sh = $db->query('SELECT data FROM settings WHERE value = '.$db->escape('WebFLV_w').' AND hostname IS NULL');
    if ($row = $sh->fetch_row()) { $width = $row[0]; }
    $sh->finish();

    // Video Bitrate
    $sh = $db->query('SELECT data FROM settings WHERE value = '.$db->escape('WebFLV_vb').' AND hostname IS NULL');
    if ($row = $sh->fetch_row()) { $vbitrate = $row[0]; }
    $sh->finish();

    // Audio Bitrate
    $sh = $db->query('SELECT data FROM settings WHERE value = '.$db->escape('WebFLV_ab').' AND hostname IS NULL');
    if ($row = $sh->fetch_row()) { $abitrate = $row[0]; }
    $sh->finish();
}

// 3. Auto-detect height based on aspect ratio from recordedmarkup table
$x = null;
$y = null;

if (isset($db) && !empty($chanid) && !empty($starttime)) {
    // Check type 30
    $sh = $db->query('SELECT data FROM recordedmarkup WHERE chanid = '.$db->escape($chanid).' ' .
                     'AND starttime = FROM_UNIXTIME('.$db->escape($starttime).') AND type = 30 ' .
                     'AND data IS NOT NULL ORDER BY mark LIMIT 1');
    if ($row = $sh->fetch_row()) { $x = $row[0]; }
    $sh->finish();

    // Check type 31
    if ($x) {
        $sh = $db->query('SELECT data FROM recordedmarkup WHERE chanid = '.$db->escape($chanid).' ' .
                         'AND starttime = FROM_UNIXTIME('.$db->escape($starttime).') AND type = 31 ' .
                         'AND data IS NOT NULL ORDER BY mark LIMIT 1');
        if ($row = $sh->fetch_row()) { $y = $row[0]; }
        $sh->finish();
    }

    // Standard definition or missing aspect fallback
    if (!$x || !$y || $x <= 720) {
        $sh = $db->query('SELECT recordedmarkup.type, recordedmarkup.data ' .
                         'FROM recordedmarkup ' .
                         'WHERE recordedmarkup.chanid = '.$db->escape($chanid).' ' .
                         'AND recordedmarkup.starttime = FROM_UNIXTIME('.$db->escape($starttime).') ' .
                         'AND recordedmarkup.type IN (10, 11, 12, 13, 14) ' .
                         'GROUP BY recordedmarkup.type ' .
                         'ORDER BY SUM((SELECT IFNULL(rm.mark, recordedmarkup.mark) ' .
                         '   FROM recordedmarkup AS rm ' .
                         '   WHERE rm.chanid = recordedmarkup.chanid ' .
                         '   AND rm.starttime = recordedmarkup.starttime ' .
                         '   AND rm.type IN (10, 11, 12, 13, 14) ' .
                         '   AND rm.mark > recordedmarkup.mark ' .
                         '   ORDER BY rm.mark ASC LIMIT 1) - recordedmarkup.mark) DESC ' .
                         'LIMIT 1');
        if ($aspect = $sh->fetch_assoc()) {
            $aspectType = $aspect['type'] ?? null;
            if ($aspectType == 10) {
                $x = 1; $y = 1;
            } elseif ($aspectType == 11) {
                $x = 4; $y = 3;
            } elseif ($aspectType == 12) {
                $x = 16; $y = 9;
            } elseif ($aspectType == 13) {
                $x = 2.21; $y = 1;
            } elseif ($aspectType == 14) {
                $x = (float)$aspect['data']; $y = 10000;
            } else {
                $x = 4; $y = 3;
            }
        } else {
            $x = 4; $y = 3;
        }
        $sh->finish();
    }
} else {
    $x = 4; $y = 3;
}

// Calculate target resolution
$height   = round_even($width * ($y / $x));
$width    = ($width && $width > 1)       ? (int)$width    : 320;
$height   = ($height && $height > 1)     ? (int)$height   : 240;
$vbitrate = ($vbitrate && $vbitrate > 1) ? (int)$vbitrate : 256;
$abitrate = ($abitrate && $abitrate > 1) ? (int)$abitrate : 64;

// 4. Build FFmpeg command line using MythWeb's shell_escape() function
$ffmpeg_command = shell_escape($ffmpeg)
    . ' -y'
    . ' -i ' . shell_escape($filename)
    . ' -s ' . shell_escape("{$width}x{$height}")
    . ' -g 30'
    . ' -r 24'
    . ' -f webm'
    . ' -acodec libvorbis'
    . ' -vcodec libvpx'
    . ' -b:v ' . shell_escape("{$vbitrate}k")
    . ' -b:a ' . shell_escape("{$abitrate}k")
    . ' -';

// 5. Disable output buffering for live WebM streaming
while (ob_get_level()) {
    ob_end_clean();
}

// 6. Output HTTP Streaming Headers
header('Content-Type: video/webm');
header('Content-Disposition: inline; filename="' . basename($filename) . '.webm"');
header('Cache-Control: no-cache');

// 7. Execute FFmpeg process and stream piped output
$descriptors = [
    0 => ['pipe', 'r'], // stdin
    1 => ['pipe', 'w'], // stdout
    2 => ['file', '/dev/null', 'a'] // stderr
];

$process = proc_open($ffmpeg_command, $descriptors, $pipes);

if (!is_resource($process)) {
    header("HTTP/1.1 500 Internal Server Error");
    exit("Cannot execute ffmpeg command.");
}

// Store process PID
$status = proc_get_status($process);
$ffmpeg_pid = $status['pid'] ?? null;

fclose($pipes[0]); // Close standard input

// Stream binary chunks directly to the web client
while (!feof($pipes[1]) && !connection_aborted()) {
    $buffer = fread($pipes[1], 1024 * 64);
    if ($buffer !== false && $buffer !== '') {
        echo $buffer;
        flush();
    }
}

fclose($pipes[1]);
proc_close($process);
exit;