<?php
/**
 * Create a ical for the upcoming recordings
 *
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  TV
 *
 **/

    $calendar = new vcalendar();
    $calendar->setConfig($_SERVER['SERVER_SIGNATURE'], $_SERVER['HTTP_HOST']);
    $calendar->setProperty( 'method', 'PUBLISH' );

    foreach ($all_shows as $show) {
        $event = new vevent();
        $event->setProperty('dtstart',      array( 'year'  => date('Y', (int) $show->starttime),
                                                   'month' => date('m', (int) $show->starttime),
                                                   'day'   => date('d', (int) $show->starttime),
                                                   'hour'  => date('H', (int) $show->starttime),
                                                   'min'   => date('i', (int) $show->starttime),
                                                   'sec'   => date('s', (int) $show->starttime) ));
        $event->setProperty('dtend',        array( 'year'  => date('Y', (int) $show->endtime),
                                                   'month' => date('m', (int) $show->endtime),
                                                   'day'   => date('d', (int) $show->endtime),
                                                   'hour'  => date('H', (int) $show->endtime),
                                                   'min'   => date('i', (int) $show->endtime),
                                                   'sec'   => date('s', (int) $show->endtime) ));
        $event->setProperty('summary',      $show->title.(!empty($show->subtitle) ? ' - '.$show->subtitle : ''));
        $event->setProperty('description',  ($show->description ?? '')."\n\n".preg_replace('/([A-Z]+)/',' $1', $show->recstatus ?? ''));
        $event->setProperty('location', $show->channel->callsign ?? '');
        $calendar->setComponent($event);
    }

    $calendar->returnCalendar();