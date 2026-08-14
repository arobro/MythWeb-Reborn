<?php
/**
 * Run a command on a frontend
 *
 *
 * @package     MythWeb
 * @subpackage  Remote
 *
 **/

    $frontend = isset($Path[2]) ? $Frontends[$Path[2]] : null;

    if (!isset($frontend)) {
        header('HTTP/1.0 404 Not Found', true, 404);
        die(t('Unknown frontend'));
    }

    $action   = isset($Path[3]) ? $Path[3] : null;
    $parms    = array_slice($Path, 4);

    switch ($action) {
        case 'SendKey':
            if (isset($parms[0]) && !$frontend->send_key($parms[0]))
                header('HTTP/1.0 400 Bad Request', true, 400);
            break;

        case 'PlayProgram':
            if (isset($parms[0], $parms[1]) && !$frontend->play_program($parms[0], $parms[1]))
                header('HTTP/1.0 400 Bad Request', true, 400);
            break;

        case 'JumpTo':
            if (isset($parms[0]) && !$frontend->send_jump($parms[0]))
                header('HTTP/1.0 400 Bad Request', true, 400);
            break;

        case 'GetLocation':
            echo json_encode($frontend->query_location());
            break;

        case 'GetJumppoints':
            echo json_encode($frontend->get_jump_points());
            break;

        case 'Query':
            if (isset($parms[0]))
                echo json_encode($frontend->query($parms[0]));
            break;

        default:
            header('HTTP/1.0 501 Not Implemented', true, 501);
            die(t('Unknown action'));
    }