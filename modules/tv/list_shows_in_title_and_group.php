<?php
/**
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  TV
 **/

    if (!isset($_REQUEST['group']) || strtolower($_REQUEST['group']) == 'all')
        $group = 'All';
    else
        $group = $_REQUEST['group'];

    $title_param = isset($_REQUEST['title']) ? $_REQUEST['title'] : '';

    $SubTitles = array();
    $sh = $db->query('SELECT IF( IFNULL(recorded.subtitle, "") = "",
                                 recorded.title,
                                 recorded.subtitle) AS subtitle,
                             recorded.chanid,
                             UNIX_TIMESTAMP(recorded.starttime)
                        FROM recorded
                       WHERE recorded.recgroup LIKE ?
                         AND recorded.title       = ?
                    ORDER BY recorded.originalairdate ASC
                    ',
                    ($group == 'All' ? '%' : $group),
                    $title_param
                    );
    while ($subtitles = $sh->fetch_row())
        $SubTitles[] = $subtitles;

    $Page_Previous_Location = 'tv/list_titles_in_group?group='.htmlspecialchars($group, ENT_QUOTES, 'UTF-8');
    $Page_Previous_Location_Name = $group;
    $Page_Title_Short = $title_param;

// Load the class for this page
    require_once tmpl_dir.'list_shows_in_title_and_group.php';