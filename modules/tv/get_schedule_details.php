<?php
// Attempt to force some caching, will make secondary views so much faster.
    $starttime = intval($_REQUEST['starttime'] ?? 0);
    $recordid  = $_REQUEST['recordid'] ?? null;

    header('Cache-Control: max-age='.($starttime - time() + 604800));
    header('Pragma: ');
    header('Expires: '.date('D, d M Y H:i:s e', $starttime + 604800));
    header('Content-Type: application/json');

    $schedule = $recordid ? Schedule::find($recordid) : null;

    echo json_encode(array( 'id'               => 'schedule-'.$recordid,
                             'info'             => $schedule ? $schedule->details_list() : null
                           ));