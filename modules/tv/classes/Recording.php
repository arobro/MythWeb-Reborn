<?php
/**
 * The Recording object, and a couple of related subroutines.
 *
 * @license     GPL
 *
 * @package     MythWeb
 *
 **/

// Make sure the recording schedule type data gets loaded
    require_once 'includes/recording_schedules.php';

//
//  Recordings class
//
#[\AllowDynamicProperties]
class Recording {
    public $recordid;
    public $type;
    public $chanid;
    public $starttime;
    public $endtime;
    public $title;
    public $subtitle;
    public $description;
    public $season;
    public $episode;
    public $category;
    public $profile;
    public $recgroup;
    public $storagegroup;
    public $recpriority;
    public $autoexpire;
    public $maxepisodes;
    public $maxnewest;
    public $dupin;
    public $dupmethod;
    public $startoffset;
    public $endoffset;
    public $progstart;
    public $progend;
    public $seriesid;
    public $programid;
    public $inetref;
    public $texttype;

    public $basename;

    public $channel;

    public $will_record    = false;
    public $record_daily   = false;
    public $record_weekly  = false;
    public $record_once    = false;
    public $record_always  = false;
    public $record_findone = false;

    public $css_class;         // css class, based on category and/or category_type

    public function __construct($recording_data) {

    // SQL data
        if (is_array($recording_data) && isset($recording_data['recordid'])) {
            $this->recordid    = $recording_data['recordid'];
            $this->type        = $recording_data['type'];
            $this->chanid      = $recording_data['chanid'];
            $this->starttime   = isset($recording_data['starttime_unix']) ? $recording_data['starttime_unix'] : null;
            $this->endtime     = isset($recording_data['endtime_unix']) ? $recording_data['endtime_unix'] : null;
            $this->title       = isset($recording_data['title']) ? $recording_data['title'] : null;
            $this->subtitle    = isset($recording_data['subtitle']) ? $recording_data['subtitle'] : null;
            $this->description = isset($recording_data['description']) ? $recording_data['description'] : null;
            $this->season      = isset($recording_data['season']) ? $recording_data['season'] : null;
            $this->episode     = isset($recording_data['episode']) ? $recording_data['episode'] : null;
            $this->category    = isset($recording_data['category']) ? $recording_data['category'] : null;
            $this->profile     = isset($recording_data['profile']) ? $recording_data['profile'] : null;
            $this->recgroup    = isset($recording_data['recgroup']) ? $recording_data['recgroup'] : null;
            $this->storagegroup = isset($recording_data['storagegroup']) ? $recording_data['storagegroup'] : null;
            $this->recpriority = isset($recording_data['recpriority']) ? $recording_data['recpriority'] : null;
            $this->autoexpire  = isset($recording_data['autoexpire']) ? $recording_data['autoexpire'] : null;
            $this->maxepisodes = isset($recording_data['maxepisodes']) ? $recording_data['maxepisodes'] : null;
            $this->maxnewest   = isset($recording_data['maxnewest']) ? $recording_data['maxnewest'] : null;
            $this->dupin       = isset($recording_data['dupin']) ? $recording_data['dupin'] : null;
            $this->dupmethod   = isset($recording_data['dupmethod']) ? $recording_data['dupmethod'] : null;
            $this->startoffset = isset($recording_data['startoffset']) ? $recording_data['startoffset'] : null;
            $this->endoffset   = isset($recording_data['endoffset']) ? $recording_data['endoffset'] : null;
            $this->seriesid    = isset($recording_data['seriesid']) ? $recording_data['seriesid'] : null;
            $this->programid   = isset($recording_data['programid']) ? $recording_data['programid'] : null;
            $this->inetref     = isset($recording_data['inetref']) ? $recording_data['inetref'] : null;
            $this->progstart   = isset($recording_data['progstart']) ? $recording_data['progstart'] : null;
            $this->progend     = isset($recording_data['progend']) ? $recording_data['progend'] : null;
            $this->basename    = isset($recording_data['basename']) ? $recording_data['basename'] : null;
        }
    // Recording object data
        else {
            $tmp = is_object($recording_data) ? get_object_vars($recording_data) : null;
            if (is_array($tmp) && (count($tmp) > 0)) {
                foreach ($tmp as $key => $value) {
                    $this->$key = $value;
                }
            }
        }

    // We get various recording-related information, too
        switch ($this->type) {
            case 1: $this->record_once    = true;  break;
            case 2: $this->record_daily   = true;  break;
            case 4: $this->record_always  = true;  break;
            case 5: $this->record_weekly  = true;  break;
            case 6: $this->record_findone = true;  break;
        }

    // Add a generic "will record" variable, too
        $this->will_record = ($this->record_daily
                              || $this->record_weekly
                              || $this->record_once
                              || $this->record_findone
                              || $this->record_always ) ? true : false;
    // Turn type int a word
        if (isset($this->type) && isset($GLOBALS['RecTypes'][$this->type]))
            $this->texttype = $GLOBALS['RecTypes'][$this->type];
    // Do we have a chanid?  Load some info about it
        if ($this->chanid && !isset($this->channel))
            $this->channel = Channel::find($this->chanid);

    // Find out which css category this recording falls into
        if ($this->chanid != '')
            $this->css_class = category_class($this);
    }

}