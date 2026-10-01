<?php
/**
 * Handles tokens when cookie based authentication cannot be used. ASX for VLC etc
 *
 * @license     GPL
 *
 * @package     MythWeb
 *
 **/
 
if (str_contains($_SERVER['REQUEST_URI'], '.')) {
	http_response_code(403);
	die("Only stream raw allowed");
}

// Load the generic utilities so we have access to stuff like DEBUG()
require_once 'includes/utils.php';

// Load the error trapping and display routines
require_once 'includes/errors.php';
require_once 'includes/errordisplay.php';

// Define some common stuff
require_once 'includes/defines.php';

// Setup the database
require_once 'includes/database.php';

function isValidUserToken(?string $token): bool {
    global $db;

    // 1. Basic Validation: Must be non-empty, a string, and exactly 64 valid hex characters (32 bytes)
    if (empty($token) || !is_string($token) || strlen($token) !== 64 || !ctype_xdigit($token)) {
        return false;
    }

    // 2. Ensure database instance is available
    if (!is_object($db)) {
        return false;
    }

    // 3. Query database to check across all users
    try {
        $count = (int)$db->query_col(
            "SELECT COUNT(*) FROM mythweb_users WHERE token = ?", 
            [$token]
        );

        return $count > 0;
    } catch (Exception $e) {
        return false;
    }
}

$userToken = $_GET['token'] ?? null;

// Only continue if the server token exists and matches the provided token
if (empty($userToken) || !isValidUserToken($userToken)) {
    http_response_code(403);
    die("Access denied: Invalid or missing stream token.");
}

// Call stream handler
require_once 'modules/stream/handler.php';