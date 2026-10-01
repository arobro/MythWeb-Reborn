<?php
/**
 * Configure MythTV Channels
 *
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  Settings
 *
 **/

// Save?
    if (!empty($_POST['save'])) {
    // Parse the post variables and save each group of channel info
        if (!empty($_POST['channel']) && is_array($_POST['channel'])) {
            foreach ($_POST['channel'] as $chanid => $data) {

                if (isset($data['delete']) && $data['delete'] == 'true') {
                    $db->query('DELETE FROM channel WHERE chanid=?', $chanid);
                } else {
                    $query_params = array();
                // Not deleting so grab values that can be empty
                    $query = 'UPDATE channel SET xmltvid       = ?,
                                                 freqid        = ?,
                                                 finetune      = ?,
                                                 videofilters  = ?,
                                                 brightness    = ?,
                                                 contrast      = ?,
                                                 colour        = ?,
                                                 hue           = ?,
                                                 recpriority   = ?,
                                                 commmethod    = ?,
                                                 useonairguide = ?,
                                                 visible       = ?';
                    $query_params[] = $data['xmltvid'] ?? null;
                    $query_params[] = $data['freqid'] ?? null;
                    $query_params[] = empty($data['finetune'])      ? 0 : $data['finetune'];
                    $query_params[] = $data['videofilters'] ?? null;
                    $query_params[] = $data['brightness'] ?? null;
                    $query_params[] = $data['contrast'] ?? null;
                    $query_params[] = $data['colour'] ?? null;
                    $query_params[] = $data['hue'] ?? null;
                    $query_params[] = $data['recpriority'] ?? null;
                    $query_params[] = empty($data['commfree'])      ? -1 : -2;
                    $query_params[] = empty($data['useonairguide']) ? 0 : 1;
                    $query_params[] = empty($data['visible'])       ? 0 : 1;
                // next, the fields that need to have a value, so we won't change them if they were emptied
                    if (!empty($data['channum'])) {
                        $query         .= ',channum=?';
                        $query_params[] = $data['channum'];
                    }
                    if (!empty($data['callsign'])) {
                        $query         .= ',callsign=?';
                        $query_params[] = $data['callsign'];
                    }
                    if (!empty($data['name'])) {
                        $query         .= ',name=?';
                        $query_params[] = $data['name'];
                    }
                    $db->query($query.' WHERE chanid=?',
                               $query_params,
                               $chanid
                               );
                }
            }
        }
    // Do a reschedule to refresh scheduled recordings;
        MythBackend::find()->rescheduleRecording();
    }

// Sortby
    if (!empty($_GET['sortby'])) {
        $_SESSION['tv']['set']['chan_sort'] = $_GET['sortby'];
    }
    $chan_sort = $_SESSION['tv']['set']['chan_sort'] ?? '';
    switch ($chan_sort) {
        case 'callsign':
        case 'name':
            $sortby = $chan_sort;
            break;
        case 'channum':
        case 'xmltvid':
        case 'freqid':
            $sortby = $chan_sort.' + 0, '.$chan_sort;
            break;
        case 'sourceid':
            $sortby = $chan_sort.', channum';
            break;
        default:
            $sortby = 'channum + 0, channum';
    }

// Load all of the channel data from the database
    $channels = array();
    $sh = $db->query('SELECT chanid FROM channel ORDER BY '.$sortby);
    while ($row = $sh->fetch_col()) {
        $channels[] = $row;
    }
    $sh->finish();

// These settings affect all of mythtv
    $Settings_Hosts = t('All Hosts');