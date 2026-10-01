<?php
/**
 * MythWeb Streaming/Download Module (PHP Version)
 */

// Disable output buffering to stream directly to the client
while (ob_get_level()) {
    ob_end_clean();
}

// 1. Sanitize & Validate Inputs
// (Assuming $filename, $basename, $title, $season, $episode, $subtitle are provided)
if (strpos($filename, "\0") !== false || strpos($basename, "\0") !== false) {
    header("HTTP/1.1 400 Bad Request");
    exit("Invalid request.");
}

// Block path traversal attempts (../ or ..\)
if (
    preg_match('/\.\.[\/\\\\]/', $filename) || 
    preg_match('/\.\.[\/\\\\]/', $basename) || 
    preg_match('/\.\.$/', $filename)
) {
    header("HTTP/1.1 403 Forbidden");
    exit("Access denied.");
}

// Sanitize basename
$basename = str_replace(["\r", "\n"], '', $basename);

// Check file existence and size
if (!file_exists($filename) || !is_readable($filename)) {
    header("HTTP/1.1 404 Not Found");
    exit("Can't read $basename.");
}

$size = filesize($filename);
if ($size < 1) {
    header("HTTP/1.1 400 Bad Request");
    exit("$basename is an empty file.");
}

// 2. Determine MIME Type and Extension Suffix
$type = 'text/html';
$suffix = '';

if (preg_match('/\.mpe?g2?$/i', $basename)) {
    $type = 'video/mpeg';
    $suffix = '.mpg';
} elseif (preg_match('/\.ts$/i', $basename)) {
    $type = 'video/mp2t';
    $suffix = '.ts';
} elseif (preg_match('/\.nuv$/i', $basename)) {
    $type = 'video/nuppelvideo';
    $suffix = '.nuv';
} elseif (preg_match('/\.mkv$/i', $basename)) {
    $type = 'video/x-matroska';
    $suffix = '.mkv';
} elseif (preg_match('/\.mp4$/i', $basename)) {
    $type = 'video/mp4';
    $suffix = '.mp4';
} elseif (preg_match('/\.m4v$/i', $basename)) {
    $type = 'video/mp4';
    $suffix = '.m4v';
} elseif (preg_match('/\.avi$/i', $basename)) {
    $type = 'video/x-msvideo';
    $suffix = '.mp4'; // Preserved from original Perl logic
} elseif (preg_match('/\.mov$/i', $basename)) {
    $type = 'video/quicktime';
    $suffix = '.mov';
} elseif (preg_match('/\.wmv$/i', $basename)) {
    $type = 'video/x-ms-wmv';
    $suffix = '.wmv';
} elseif (preg_match('/\.3gp$/i', $basename)) {
    $type = 'video/3gpp';
    $suffix = '.3gp';
} elseif (preg_match('/\.ogv$/i', $basename)) {
    $type = 'video/ogg';
    $suffix = '.ogv';
} elseif (preg_match('/\.webm$/i', $basename)) {
    $type = 'video/webm';
    $suffix = '.webm';
} else {
    header("HTTP/1.1 415 Unsupported Media Type");
    exit("Unknown video type requested: $basename\n");
}

// 3. Format Download Filename
$name = $basename;
if (preg_match('/^\d+_\d+\.\w+$/', $name)) {
    // Decode HTML entities in title/subtitle if they are set
    $cleanTitle = !empty($title) ? html_entity_decode($title, ENT_QUOTES, 'UTF-8') : '';
    $cleanSubtitle = !empty($subtitle) ? html_entity_decode($subtitle, ENT_QUOTES, 'UTF-8') : '';

    if (!empty($cleanTitle) && preg_match('/\w/', $cleanTitle)) {
        $name = $cleanTitle;
        if (!empty($season) && !empty($episode)) {
            $name .= sprintf(" - %dx%02d", $season, $episode);
        }
        if (!empty($cleanSubtitle) && preg_match('/\w/', $cleanSubtitle)) {
            $name .= " - $cleanSubtitle";
        }
    }
    $name .= $suffix;
}

// Strip control characters and quotes from header filename
$name = str_replace(["\r", "\n", '"'], '', $name);

// 4. File Timestamps & Cache Headers (HTTP 304 Handling)
$mtime = filemtime($filename);
$lastModifiedStr = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';

if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
    $checkTime = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
    if ($mtime <= $checkTime) {
        header("HTTP/1.1 304 Not Modified");
        header("Content-Type: $type");
        exit;
    }
}

// 5. Handle HTTP Range Requests (HTTP 206 Partial Content)
$start = 0;
$end = $size - 1;
$totalSize = $size;
$isRange = false;

if (isset($_SERVER['HTTP_RANGE'])) {
    if (preg_match('/bytes=(\d*)-(\d*)/i', $_SERVER['HTTP_RANGE'], $matches)) {
        $start = ($matches[1] !== '') ? (int)$matches[1] : 0;
        $end   = ($matches[2] !== '') ? (int)$matches[2] : ($size - 1);

        if ($end >= $size) {
            $end = $size - 1;
        }

        if ($start <= $end) {
            $size = $end - $start + 1;
            $isRange = true;
        }
    }
}

// 6. Send Response Headers
if ($isRange) {
    header("HTTP/1.1 206 Partial Content");
    header("Content-Range: bytes $start-$end/$totalSize");
} else {
    header("HTTP/1.1 200 OK");
}

header("Content-Type: $type");
header("Content-Length: $size");
header("Accept-Ranges: bytes");
header("Last-Modified: $lastModifiedStr");
header('Content-Disposition: attachment; filename="' . $name . '"');

// Exit early for HEAD requests without streaming content
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'HEAD') {
    exit;
}

// 7. Stream File Content
$fp = fopen($filename, 'rb');
if (!$fp) {
    exit;
}

// Seek to the requested start position
fseek($fp, $start);

$chunkSize = 8192; // 8KB buffer (more performant than 1KB in PHP)
$bytesRemaining = $size;

while (!feof($fp) && $bytesRemaining > 0 && !connection_aborted()) {
    $readLength = min($chunkSize, $bytesRemaining);
    $data = fread($fp, $readLength);

    if ($data === false) {
        break;
    }

    echo $data;
    flush();

    $bytesRemaining -= strlen($data);
}

fclose($fp);
exit;