<?php
// Attempt to force some caching, will make secondary views so much faster.
    header('Cache-Control: max-age=7200, public');
    header('Pragma: ');
    header('Expires: '.date('D, d M Y H:i:s e', time() + 7200));
    header('Content-Type: application/json');

    $starttime = $_REQUEST['starttime'] ?? null;
    $chanid    = $_REQUEST['chanid'] ?? null;

    $program = load_one_program($starttime, $chanid, false);

    echo json_encode(array( 'id'               => 'program-'.$chanid.'-'.$starttime,
                             'info'             => $program ? $program->details_list() : null
                           ));