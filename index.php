<?php

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir . '/tablelib.php');
require_once('reportlib.php');

$startnow = optional_param('startnow', 0, PARAM_INT);
$forumid = optional_param('forum', 0, PARAM_INT);
$courseid = required_param('id', PARAM_INT);
$groupid = optional_param('group', 0, PARAM_INT);
$groupingid = optional_param('grouping', 0, PARAM_INT);
$type = optional_param('type', 0, PARAM_INT);
$countryid = optional_param('country', '', PARAM_RAW);
$start = optional_param('start', '', PARAM_RAW);
$end = optional_param('end', '', PARAM_RAW);
$tsort = optional_param('tsort', 0, PARAM_RAW);
$treset = optional_param('treset', 0, PARAM_RAW);
$page = optional_param('page', 0, PARAM_RAW);
$pagesize = optional_param('pagesize', 0, PARAM_RAW);
$onlygroupworks = optional_param('onlygroupworks', 0, PARAM_INT);
$stale_reply_days = optional_param('stale_reply_days', 7, PARAM_INT);

if (strpos($tsort, 'firstname') !== FALSE  || strpos($tsort, 'lastname') !== FALSE) {
    //$orderbyname = $tsort;
    $tdir = optional_param('tdir', 0, PARAM_INT);
    $ascdesc = ($tdir == 4) ? 'ASC' : 'DESC';
    $orderbyname = $tsort . ' ' . $ascdesc;
} else {
    $orderbyname = '';
}

$params['id'] = $courseid;
$course = $DB->get_record('course', array('id' => $courseid));

require_course_login($course);
$coursecontext = context_course::instance($course->id);

require_capability('report/discussion_metrics:view', $coursecontext, NULL, true, 'noviewdiscussionspermission', 'forum');

$event = \report_discussion_metrics\event\report_viewed::create(array('context' => $coursecontext));
$event->trigger();

if ($forumid) {
    $params['forum'] = $forumid;
    $forum = $DB->get_record('forum', array('id' => $forumid));
    $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);
    $modcontext = context_module::instance($cm->id);
    $PAGE->set_title("$course->shortname: $forum->name");
    $PAGE->navbar->add($forum->name);
}

$countries = get_string_manager()->get_list_of_countries();

$mform = new report_form();
$fromform = $mform->get_data();
$paramstr = '?course=' . $course->id . '&forum=' . $forumid . '&group=' . $groupid . '&grouping=' . $groupingid;

$groups = array();
if ($groupid) {
    $params['group'] = $groupid;
    $groupfilter = $groupid;
    $groups[] = groups_get_group($groupid);
    $groupname = groups_get_group_name($groupid);
    $groupmembers = groups_get_members($groupid);
    /*
}elseif(isset($fromform->group)){
    $groupfilter = $fromform->group;
    $paramstr .= '&group='.$groupfilter;
    $params['group'] = $groupfilter;
    echo $groupfilter
    $groupname = groups_get_all_groups($course->id)[$groupfilter]->name;
*/
    $groupingid = '';
} else {
    $groupfilter = 0;
    $groupname = "";
}
if ($groupingid) {
    $params['grouping'] = $groupingid;
    $groupingmembers = groups_get_grouping_members($groupingid);
    $groupinggroups = groups_get_all_groups($courseid, '', $groupingid);
    if (!$groupid) {
        $groupid = array_keys($groupinggroups);
        $groups = $groupinggroups;
    }
}
if ($countryid) {
    $params['country'] = $countryid;
    $countryfilter = $countryid;
    $paramstr .= '&country=' . $countryfilter;
} elseif (isset($fromform->country)) {
    $countryfilter = $fromform->country;
    $paramstr .= '&country=' . $countryfilter;
    $params['country'] = $countryfilter;
} else {
    $countryfilter = 0;
}
if (isset($fromform->starttime)) {
    $starttime = $fromform->starttime;
    $params['start'] = $starttime;
    $paramstr .= '&start=' . $starttime;
} elseif ($start) {
    $starttime = $start;
    $paramstr .= '&start=' . $starttime;
    $params['start'] = $starttime;
} else {
    $starttime = 0;
}
if (isset($fromform->endtime)) {
    $endtime = $fromform->endtime;
    $params['end'] = $endtime;
    $paramstr .= '&end=' . $endtime;
} elseif ($end) {
    $endtime = $end;
    $paramstr .= '&end=' . $endtime;
    $params['end'] = $endtime;
} else {
    $endtime = 0;
}
if (isset($type)) {
    $paramstr .= '&type=' . $type;
    $params['type'] = $type;
}
if (isset($pagesize)) {
    $paramstr .= '&pagesize=' . $pagesize;
    $params['pagesize'] = $pagesize;
}
if (isset($stale_reply_days)) {
    $paramstr .= '&stale_reply_days=' . $stale_reply_days;
    $params['stale_reply_days'] = $stale_reply_days;
}
if (isset($page)) {
    $paramstr .= '&page=' . $page;
    $params['page'] = $page;
}
if (isset($onlygroupworks)) {
    $paramstr .= '&onlygroupworks=' . $onlygroupworks;
    $params['onlygroupworks'] = $onlygroupworks;
}
$mform->set_data($params);

$PAGE->set_pagelayout('incourse');
$PAGE->set_url($CFG->wwwroot . '/report/discussion_metrics/index.php', $params);
$PAGE->navbar->add('discussion_metrics');
$PAGE->set_heading($course->fullname);
$PAGE->requires->js_call_amd('report_discussion_metrics/script', 'init');
echo $OUTPUT->header();
$mform->display();
echo html_writer::tag('input', '', array('type' => 'hidden', 'id' => 'courseid1', 'value' => $courseid));

$strname = get_string('fullname');
$strfirstname = get_string('firstname');
$strlastname = get_string('lastname');
$strcounrty = get_string('country');
//$strposts = get_string('posts');
$strposts = 'Total discussion posts started';
$strviews = get_string('views', 'report_discussion_metrics');
$strreplies = get_string('replies', 'report_discussion_metrics');
$strwordcount = get_string('wordcount', 'report_discussion_metrics');
$strfp = get_string('firstpost', 'report_discussion_metrics');
$strlp = get_string('lastpost', 'report_discussion_metrics');
$strsr = get_string('sendreminder', 'report_discussion_metrics');
$strcl = get_string('completereport');
$strinstituion = get_string('institution');
//$strgroup = get_string('group');
$strgroup = 'Group Name';
$strmultimedia = get_string('multimedia', 'report_discussion_metrics');
$strinternational = get_string('international', 'report_discussion_metrics');
$strdomestic = get_string('domestic', 'report_discussion_metrics');
$strself = get_string('self', 'report_discussion_metrics');
$strstale = get_string('stale', 'report_discussion_metrics');
$straudio = get_string('audio', 'report_discussion_metrics');
$strlink = get_string('link', 'report_discussion_metrics');
$strvideo = get_string('video', 'report_discussion_metrics');
$strimage = get_string('image', 'report_discussion_metrics');
$strdirect = get_string('direct_reply', 'report_discussion_metrics');

if ($type || $tsort || $treset || $page) {
    echo html_writer::empty_tag('br');
    // echo '<a href="download.php"><button class="btn btn-primary download">' . get_string('download') . '</button></a><br><br>';
    // $downloadbutton = '<button class="btn btn-primary ">' . get_string('download') . '</button>';
    // echo html_writer::empty_tag('br');
    // echo html_writer::empty_tag('br');
    echo html_writer::start_tag('div', array('id' => 'discssionmetrixreport'));
    if ($forumid) { //Add onlugroupaction @20210405
        $students = get_users_by_capability($modcontext, 'mod/forum:viewdiscussion', '', $orderbyname);
        if ($groupid && $onlygroupworks) {
            list($wheregroup, $params) = $DB->get_in_or_equal($groupid);
            $params[] = $forumid;
            $select = 'groupid ' . $wheregroup . ' AND forum = ?';
            $discussions = $DB->get_records_select('forum_discussions', $select, $params);
            //$discussions = $DB->get_records('forum_discussions',array('forum'=>$forum->id,'groupid'=>$groupid));
        } else {
            $discussions = $DB->get_records('forum_discussions', array('forum' => $forum->id));
        }
    } else {
        //get_enrolled_users(context $context, $withcapability = '', $groupid = 0, $userfields = 'u.*', $orderby = '', $limitfrom = 0, $limitnum = 0)に変えること
        //投稿が終わった後に学生からviewを剥奪することがある？考え中。
        //$students = get_enrolled_users($coursecontext);
        //var_dump($students);
        $students = get_users_by_capability($coursecontext, 'mod/forum:viewdiscussion', '', $orderbyname);
        if ($groupid && $onlygroupworks) { //Add onlugroupaction @20210405
            list($wheregroup, $params) = $DB->get_in_or_equal($groupid);
            $params[] = $courseid;
            $select = 'groupid ' . $wheregroup . ' AND course = ?';
            $discussions = $DB->get_records_select('forum_discussions', $select, $params);
        } else {
            $discussions = $DB->get_records('forum_discussions', array('course' => $course->id));
        }
    }
    if ($groupid) {
        if (!isset($groupinggroups)) {
            $students = array_intersect_key($students, $groupmembers);
        } else {
            $students = array_intersect_key($students, $groupingmembers);
        }
    }
    $firstposts = array();
    $discussionarray = '(';
    foreach ($discussions as $discussion) {
        $discussionarray .= $discussion->id . ',';
        $firstposts[] = $discussion->firstpost;
    }
    $discussionarray .= '0)';
    $table = new flexible_table('forum_report_table');
    $table->define_baseurl($PAGE->url);
    $table->sortable(true);
    $table->collapsible(true);
    $table->set_attribute('class', 'admintable generaltable');
    $table->set_attribute('id', 'discussionmetrixreporttable');
    if ($type == 1) {
        $studentdata = new report_discussion_metrics\select\get_student_data($students, $courseid, $forumid, $discussions, $discussionarray, $firstposts, $starttime, $endtime, $stale_reply_days);
        $data = $studentdata->data;
        $table->define_columns(array('firstname', 'lastname', 'country', 'institution', 'group', 'discussion', 'participants', 'multinational', 'posts', 'replies', 'stale_reply', 'self_reply', 'repliestoseed', 'l1', 'l2', 'l3', 'l4', 'maxdepth', 'avedepth', 'wordcount', 'views', 'imagenum', 'videonum', 'audionum', 'linknum'));
        $table->define_headers(array('Firstname', 'Surname',  $strcounrty, $strinstituion, $strgroup, 'Total discussion joined',  'Total discussion participants', 'Total Nationalities', $strposts, $strreplies, $strstale, $strself, $strdirect, 'E#1', 'E#2', 'E#3', 'E#4+', 'Max E', 'Average E', $strwordcount, $strviews, '#image', '#video', '#audio', '#link'));
        $table->setup();
        $sortby = $table->get_sort_columns();
        if ($sortby) {
            $orderby = array_keys($sortby)[0];
            $ascdesc = ($sortby[$orderby] == 4) ? 'ASC' : 'DESC';
            if (strpos($orderby, 'name') !== FALSE) {
                $orderbyname = $orderby . ' ' . $ascdesc;
            } else {
                $orderbyname = '';
            }
        } else {
            $orderbyname = '';
        }
        if ($sortby && !$orderbyname) {
            usort($data, forum_report_sort($sortby));
        }
        if ($pagesize) {
            $table->pagesize($pagesize, count($data));
            $data = array_slice($data, $page * $pagesize, $pagesize);
        }
        foreach ($data as $row) {
            $trdata = array($row->firstname, $row->surname, $row->country, $row->institution, $row->group, $row->discussion, $row->participants, $row->multinationals, $row->posts, $row->replies, $row->stale_reply, $row->self_reply, $row->repliestoseed, $row->l1, $row->l2, $row->l3, $row->l4, $row->maxdepth, $row->avedepth, $row->wordcount, $row->views, $row->imagenum, $row->videonum, $row->audionum, $row->linknum);
            $table->add_data($trdata);
        }
    } elseif ($type == 2) { //Goupごと

        $groupdata = new report_discussion_metrics\select\get_group_data($courseid, $forumid, $discussions, $discussionarray, $firstposts, $groups, $starttime, $endtime, $stale_reply_days);
        $data = $groupdata->data;
        $table->define_columns(array('name', 'repliedusers', 'notrepliedusers', 'discussion', 'users', 'posts', 'replies', 'multinationals', 'stale_reply', 'self_reply', 'r2ndpost', 'l1', 'l2', 'l3', 'l4', 'maxe', 'avge', 'wordcount', 'views', 'image', 'video', 'audio', 'link'));
        $table->define_headers(array($strgroup, '# of active members', '# of inactive members', 'Total discussion joined', 'Total discussion participants', $strposts, 'Total Replies', 'Total nationalities', $strstale, $strself, $strdirect, '#E1', '#E2', '#E3', '#E4', 'Max E', 'Average E', $strwordcount, $strviews, $strimage, $strvideo, $straudio, $strlink));
        $table->setup();
        $sortby = $table->get_sort_columns();
        if ($sortby && !$orderbyname) {
            usort($data, forum_report_sort($sortby));
        }
        if ($pagesize) {
            $table->pagesize($pagesize, count($data));
            $data = array_slice($data, $page * $pagesize, $pagesize);
        }
        foreach ($data as $row) {
            $trdata = array($row->name, $row->repliedusers, $row->notrepliedusers, $row->discussion, $row->users, $row->posts, $row->replies, $row->multinationals, $row->stale_reply, $row->self_reply, $row->repliestoseed, $row->l1, $row->l2, $row->l3, $row->l4, $row->maxdepth, $row->avedepth, $row->wordcount, $row->views, $row->imgnum, $row->videonum, $row->audionum, $row->linknum);
            $table->add_data($trdata);
        }
    } elseif ($type == 3) { //Dialogue(discussion)の集計
        //$discussiondata = new report_discussion_metrics\select\get_discussion_data($students,$courseid,$forumid,$groupfilter,$starttime,$endtime);
        $discussiondata = new report_discussion_metrics\select\get_discussion_data($students, $discussions, $groupid, $starttime, $endtime);
        $data = $discussiondata->data;
        $table->define_columns(array('forumname', 'name', 'posts', 'bereplied', 'international_reply', 'domestic_reply', 'self_reply', 'threads', 'maxdepth', 'l1', 'l2', 'l3', 'l4', 'multimedia', 'replytime', 'density'));
        $table->define_headers(array("Forum", 'Discussion', '#posts', '#been replied to', $strinternational, $strdomestic, $strself, '#threads', 'Max depth', '#L1', '#L2', '#L3', '#L4', '#multimedia', 'Reply time', 'Density'));
        $table->setup();
        $sortby = $table->get_sort_columns();
        if ($sortby && !$orderbyname) {
            usort($data, forum_report_sort($sortby));
        }
        if ($pagesize) {
            $table->pagesize($pagesize, count($data));
            $data = array_slice($data, $page * $pagesize, $pagesize);
        }
        foreach ($data as $row) {
            $trdata = array($row->forumname, $row->name, $row->posts, $row->bereplied, '', '', '', $row->threads, $row->maxdepth, $row->l1, $row->l2, $row->l3, $row->l4, $row->multimedia, $row->replytime, $row->density);
            $table->add_data($trdata);
        }
    } elseif ($type == 4) { //DialogueをGroupごと
        //$dialoguedata = new report_discussion_metrics\select\get_dialogue_data($courseid,$forumid,$groupfilter,$starttime,$endtime);
        $dialoguedata = new report_discussion_metrics\select\get_dialogue_data($courseid, $discussions, $groups, $starttime, $endtime);
        $data = $dialoguedata->data;
        $table->define_columns(array('groupname', 'forumname', 'name', 'posts', 'bereplied', 'threads', 'l1', 'l2', 'l3', 'l4', 'multimedia', 'replytime', 'density'));
        $table->define_headers(array('Group', "Forum", 'Discussion', '#post', '#been replied to', $strdirect, '#L1', '#L2', '#L3', '#L4', '#multimedia', 'Reply time', 'Density'));
        $table->setup();
        $sortby = $table->get_sort_columns();
        if ($sortby && !$orderbyname) {
            usort($data, forum_report_sort($sortby));
        }
        if ($sortby && !$orderbyname) {
            usort($data, forum_report_sort($sortby));
        }
        if ($pagesize) {
            $table->pagesize($pagesize, count($data));
            $data = array_slice($data, $page * $pagesize, $pagesize);
        }
        foreach ($data as $row) {
            $trdata = array($row->groupname, $row->forumname, $row->name, $row->posts, $row->bereplied, $row->threads, $row->l1, $row->l2, $row->l3, $row->l4, $row->multimedia, $row->replytime, $row->density);
            $table->add_data($trdata);
        }
    } elseif ($type == 5) { //Countryごと
        $countrydata = new report_discussion_metrics\select\get_country_data($students, $courseid, $forumid, $discussions, $discussionarray, $firstposts, $starttime, $endtime);
        $data = $countrydata->data;
        $table->define_columns(array('country', 'users', 'repliestoseed', 'replies', 'repliedusers', 'notrepliedusers', 'wordcount', 'views', 'multimedia'));
        $table->define_headers(array($strcounrty, '#member', $strdirect, '#replies', '#replied user', '#not replied user', $strwordcount, $strviews, $strmultimedia));
        $table->setup();
        $sortby = $table->get_sort_columns();
        if ($sortby && !$orderbyname) {
            usort($data, forum_report_sort($sortby));
        }
        if ($pagesize) {
            $table->pagesize($pagesize, count($data));
            $data = array_slice($data, $page * $pagesize, $pagesize);
        }
        foreach ($data as $row) {
            $trdata = array($row->country, $row->users, $row->repliestoseed, $row->replies, $row->repliedusers, $row->notrepliedusers, $row->wordcount, $row->views, $row->multimedia);
            $table->add_data($trdata);
        }
    } elseif ($type == 6) { //CountryをGroupごと
        $groupcountrydata = new report_discussion_metrics\select\get_group_country_data($students, $courseid, $forumid, $discussions, $discussionarray, $firstposts, $groups, $starttime, $endtime);
        $data = $groupcountrydata->data;
        $table->define_columns(array('groupname', 'country', 'users', 'repliestoseed', 'replies', 'repliedusers', 'notrepliedusers', 'wordcount', 'views', 'multimedia'));
        $table->define_headers(array($strgroup, $strcounrty, '#member', $strdirect, '#replies', '#replied user', '#not replied user', $strwordcount, $strviews, $strmultimedia));
        $table->setup();
        $sortby = $table->get_sort_columns();
        if ($sortby && !$orderbyname) {
            usort($data, forum_report_sort($sortby));
        }
        if ($pagesize) {
            $table->pagesize($pagesize, count($data));
            $data = array_slice($data, $page * $pagesize, $pagesize);
        }
        foreach ($data as $group) {
            foreach ($group as $row) {
                $trdata = array($row->groupname, $row->country, $row->users, $row->repliestoseed, $row->replies, $row->repliedusers, $row->notrepliedusers, $row->wordcount, $row->views, $row->multimedia);
                $table->add_data($trdata);
            }
        }
    }

    echo '<input type="hidden" name="course" id="courseid" value="' . $courseid . '">';
    if ($forumid) {
        echo '<input type="hidden" name="forum" id="forumid" value="' . $forumid . '">';
    }
    $table->finish_output();
    html_writer::end_tag('div');
}
echo $OUTPUT->footer();
