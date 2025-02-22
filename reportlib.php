<?php
require_once("$CFG->libdir/formslib.php");

class report_form extends moodleform
{
    //Add elements to form
    public function definition()
    {
        global $CFG, $DB, $COURSE;
        $mform = $this->_form;

        $mform->addElement('header', 'filter', get_string('reportfilter', 'report_discussion_metrics'));

        $strcountry = get_string('country');
        $strgroup = get_string('group');
        $type = array('1' => 'Individual', '2' => $strgroup, '3' => 'Discussion', '4' => 'Discussion/' . $strgroup, '5' => $strcountry, '6' => $strcountry . '/' . $strgroup);
        $mform->addElement('select', 'type', "Type", $type);

        $forumdata = $DB->get_records('forum', array('course' => $COURSE->id));
        foreach ($forumdata as $forum) {
            $forums[$forum->id] = $forum->name;
        }
        $forums = array('0' => get_string('all')) + $forums;
        $mform->addElement('select', 'forum', get_string('forum', 'forum'), $forums);


        $allgroups = groups_get_all_groups($COURSE->id);
        if (count($allgroups)) {
            $groupoptions = array('0' => get_string('allgroups'));
            foreach ($allgroups as $group) {
                $groupoptions[$group->id] = $group->name;
            }
            $mform->addElement('select', 'group', get_string('group'), $groupoptions);
        }

        //Add @20210510
        $groupings = groups_get_all_groupings($COURSE->id);
        if (count($groupings)) {
            $groupingoptions = array('0' => get_string('all'));
            foreach ($groupings as $grouping) {
                $groupingoptions[$grouping->id] = $grouping->name;
            }
            $mform->addElement('select', 'grouping', get_string('groupings', 'group'), $groupingoptions);
        }

        //Add @20210405
        $mform->addElement('checkbox', 'onlygroupworks', get_string('onlygroupworks', 'report_discussion_metrics'));
        $mform->setType('onlygroupworks', PARAM_INT);
        $mform->setDefault('onlygroupworks', 0);

        $mform->addElement('hidden', 'id', $COURSE->id);
        $mform->setType('id', PARAM_RAW);

        // Open and close dates.
        $mform->addElement('date_time_selector', 'starttime', get_string('reportstart', 'report_discussion_metrics'), array('optional' => true, 'startyear' => 2000, 'stopyear' => date("Y"), 'step' => 5));

        $mform->addElement('date_time_selector', 'endtime', get_string('reportend', 'report_discussion_metrics'), array('optional' => true, 'startyear' => 2000, 'stopyear' => date("Y"), 'step' => 5));
        /*
        $mform->addElement('header','normalization', "Normalization");
        $mform->setExpanded('normalization', false);
        $mform->addElement('checkbox', 'usernormlization', "Use normlization");
        
        $attributes=array('size'=>'5');
        $mform->addElement('text', 'depth', "Depth", $attributes);
        $mform->setType('depth',PARAM_INT);
        $mform->addRule('depth', get_string('error'), 'numeric');
        */

        $perpage = array('0' => 'All', '10' => '10', '20' => '20', '30' => '30', '50' => '50', '100' => '100');
        $mform->addElement('select', 'pagesize', "Reports per page", $perpage);

        //Seedを含むか TODO
        //$mform->addElement('checkbox','containseed','Contains seed post');
        $stale_days = array();
        for ($i = 1; $i <= 28; $i++) {
            if ($i <= 14) {
                $stale_days[$i] = $i;
            }
            if ($i == 21) {
                $stale_days[$i] = $i;
            }
            if ($i == 28) {
                $stale_days[$i] = $i;
            }
        }
        $mform->addElement('select', 'stale_reply_days', get_string('stale_days', 'report_discussion_metrics'), $stale_days);
        $mform->hideIf('stale_reply_days', 'type', 'eq', 3);
        $mform->hideIf('stale_reply_days', 'type', 'eq', 4);
        $mform->hideIf('stale_reply_days', 'type', 'eq', 5);
        $mform->hideIf('stale_reply_days', 'type', 'eq', 6);

        $mform->closeHeaderBefore('changefilter');
        $mform->addElement('submit', 'changefilter', get_string('showreport', 'report_discussion_metrics'));
        $mform->addElement('button', 'download', get_string('download'),array('class'=>'download' ,'style'=>'background-color:#0f6fc5; color:#fff;border-color:#0a4e8a'));

    }
}

function forum_report_sort($sortby)
{
    return function ($a, $b) use ($sortby) {
        foreach ($sortby as $key => $order) {
            if (strpos($key, "name") !== FALSE) {
                if ($order == 4) {
                    $cmp = strcmp($a->$key, $b->$key);
                } else {
                    $cmp = strcmp($b->$key, $a->$key);
                }
            } else {
                if ($order == 4) {
                    return ($a->$key < $b->$key) ? -1 : 1;
                } else {
                    return ($a->$key > $b->$key) ? -1 : 1;
                }
            }
            break;
        }
        return $cmp;
    };
}

function get_mulutimedia_num($text)
{
    global $CFG, $PAGE;

    if (!is_string($text) or empty($text)) {
        // non string data can not be filtered anyway
        return 0;
    }

    if (stripos($text, '</a>') === false && stripos($text, '</video>') === false && stripos($text, '</audio>') === false && (stripos($text, '<img') === false)) {
        // Performance shortcut - if there are no </a>, </video> or </audio> tags, nothing can match.
        return 0;
    }

    // Looking for tags.
    $matches = preg_split('/(<[^>]*>)/i', $text, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
    $count = new stdClass;
    $count->num = 0;
    $count->img = 0;
    $count->video = 0;
    $count->audio = 0;
    $count->link = 0;
    if (!$matches) {
        return 0;
    } else {
        // Regex to find media extensions in an <a> tag.
        $embedmarkers = core_media_manager::instance()->get_embeddable_markers();
        $re = '~<a\s[^>]*href="([^"]*(?:' .  $embedmarkers . ')[^"]*)"[^>]*>([^>]*)</a>~is';
        $tagname = '';
        foreach ($matches as $idx => $tag) {
            if (preg_match('/<(a|img|video|audio)\s[^>]*/', $tag, $tagmatches)) {
                $tagname = strtolower($tagmatches[1]);
                if ($tagname === "a" && preg_match($re, $tag)) {
                    $count->num++;
                    $count->link++;
                } else {
                    if ($tagname == "img") {
                        $count->img++;
                    } elseif ($tagname == "video") {
                        $count->video++;
                    } elseif ($tagname == "audio") {
                        $count->audio++;
                    }
                    $count->num++;
                }
            }
        }
    }
    return $count;
}

function second2days($seconds)
{

    $days = floor($seconds / 86400);
    $hours = floor($seconds / 3600) % 24;
    $minutes = floor(($seconds / 60) % 60);
    $seconds = $seconds % 60;

    $dhms = sprintf("%ddays %02d:%02d:%02d", $days, $hours, $minutes, $seconds);

    return $dhms;
}

function discussion_metrics_format_time($totalsecs, $str = null)
{

    $totalsecs = abs($totalsecs);

    if (!$str) {
        // Create the str structure the slow way.
        $str = new stdClass();
        $str->day   = get_string('day');
        $str->days  = get_string('days');
        $str->hour  = get_string('hour');
        $str->hours = get_string('hours');
        $str->min   = get_string('min');
        $str->mins  = get_string('mins');
        $str->sec   = get_string('sec');
        $str->secs  = get_string('secs');
        $str->year  = get_string('year');
        $str->years = get_string('years');
    }

    $years     = floor($totalsecs / YEARSECS);
    $remainder = $totalsecs - ($years * YEARSECS);
    $days      = floor($remainder / DAYSECS);
    $remainder = $totalsecs - ($days * DAYSECS);
    $hours     = floor($remainder / HOURSECS);
    $remainder = $remainder - ($hours * HOURSECS);
    $mins      = floor($remainder / MINSECS);
    $secs      = $remainder - ($mins * MINSECS);

    $ss = ($secs == 1)  ? $str->sec  : $str->secs;
    $sm = ($mins == 1)  ? $str->min  : $str->mins;
    $sh = ($hours == 1) ? $str->hour : $str->hours;
    $sd = ($days == 1)  ? $str->day  : $str->days;
    $sy = ($years == 1)  ? $str->year  : $str->years;

    $oyears = '';
    $odays = '';
    $ohours = '';
    $omins = '';
    $osecs = '';

    if ($years) {
        $oyears  = $years . ' ' . $sy;
    }
    if ($days) {
        $odays  = $days . ' ' . $sd;
    }
    if ($hours) {
        $ohours = $hours . ' ' . $sh;
    }
    if ($mins) {
        $omins  = $mins . ' ' . $sm;
    }
    if ($secs) {
        $osecs  = $secs . ' ' . $ss;
    }

    if ($years) {
        return trim($oyears . ' ' . $odays . ' ' . $ohours . ' ' . $omins . ' ' . $osecs);
    }
    if ($days) {
        return trim($odays . ' ' . $ohours . ' ' . $omins . ' ' . $osecs);
    }
    if ($hours) {
        return trim($ohours . ' ' . $omins . ' ' . $osecs);
    }
    if ($mins) {
        return trim($omins . ' ' . $osecs);
    }
    if ($secs) {
        return $osecs;
    }
    return "-";
}
