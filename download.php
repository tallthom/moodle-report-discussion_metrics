<?php

require_once(dirname(__FILE__) . '/../../config.php');
require_once('reportlib.php');
require_once($CFG->libdir . '/csvlib.class.php');

$forumid = optional_param('forum', 0, PARAM_INT);
$courseid = required_param('course', PARAM_INT);
$type = required_param('type', PARAM_INT);
$groupid = optional_param('group', 0, PARAM_INT);
$grouoingid = optional_param('grouping', 0, PARAM_INT);
$starttime = optional_param('starttime', '', PARAM_RAW);
$endtime = optional_param('endtime', '', PARAM_RAW);
$onlygroupworks = optional_param('onlygroupworks', 0, PARAM_INT);
$stale_reply_days = optional_param('stale_reply_days', 0, PARAM_INT);

$course = $DB->get_record('course', array('id' => $courseid));
require_course_login($course);
$coursecontext = context_course::instance($course->id);

if ($forumid) {
    $forum = $DB->get_record('forum', array('id' => $forumid));
    $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);
    $modcontext = context_module::instance($cm->id);
}
require_capability('report/discussion_metrics:view', $coursecontext, NULL, true, 'noviewdiscussionspermission', 'forum');

$strname = get_string('fullname');
$strfirstname = get_string('firstname');
$strlastname = get_string('lastname');
$strcounrty = get_string('country');
// $strposts = get_string('posts');
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

$groups = array();
if ($groupid) {
    $params['group'] = $groupid;
    $groupfilter = $groupid;;
    $groups[] = groups_get_group($groupid);
    $groupname = groups_get_group_name($groupid);
    $groupmembers = groups_get_members($groupid);
    $grouoingid = '';
} else {
    $groupfilter = 0;
    $groupname = "";
}
if ($grouoingid) {
    $params['grouping'] = $grouoingid;
    $groupingmembers = groups_get_grouping_members($grouoingid);
    $groupinggroups = groups_get_all_groups($courseid, '', $grouoingid);
    if (!$groupid) {
        $groupid = array_keys($groupinggroups);
        $groups = $groupinggroups;
    }
}

//if (isset($fromform->starttime)) {
//    $starttime = $fromform->starttime;
//} elseif ($start) {
//    $starttime = $start;
//} else {
//    $starttime = 0;
//}
//if (isset($fromform->endtime)) {
//    $endtime = $fromform->endtime;
//} elseif ($end) {
//    $endtime = $end;
//} else {
//    $endtime = 0;
//}

if ($forumid) {
    $students = get_users_by_capability($modcontext, 'mod/forum:viewdiscussion');
    if ($groupid && $onlygroupworks) {
        list($wheregroup, $params) = $DB->get_in_or_equal($groupid);
        $params[] = $forumid;
        $select = 'groupid ' . $wheregroup . ' AND forum = ?';
        $discussions = $DB->get_records_select('forum_discussions', $select, $params);
    } else {
        $discussions = $DB->get_records('forum_discussions', array('forum' => $forum->id));
    }
} else {
    $students = get_users_by_capability($coursecontext, 'mod/forum:viewdiscussion');
    if ($groupid && $onlygroupworks) {
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

$csvexport = new \csv_export_writer();
$filename = 'discussion_metrics';
$csvexport->set_filename($filename);
if ($type == 1) {
    $studentdata = new report_discussion_metrics\select\get_student_data($students, $courseid, $forumid, $discussions, $discussionarray, $firstposts, $starttime, $endtime, $stale_reply_days);
    $data = $studentdata->data;
    //    $csvexport->add_data(array($strname, $strgroup, $strcounrty, $strinstituion, 'Discussion', $strposts, $strreplies, $strinternational, $strdomestic, $strself, $strstale, 'Replies to seed', 'Reply Time(s)', '#L1', '#L2', '#L3', '#L4', 'Max depth', 'Average depth', $strwordcount, $strviews, $strmultimedia, '#image', '#video', '#audio', '#link', 'Participants', 'Multinational'));
    $csvexport->add_data(array('Firstname', 'Surname',  $strcounrty, $strinstituion, $strgroup, 'Total discussion joined',  'Total discussion participants', 'Total Nationalities', $strposts, $strreplies, $strstale, $strself, $strdirect, 'E#1', 'E#2', 'E#3', 'E#4+', 'Max E', 'Average E', $strwordcount, $strviews, '#image', '#video', '#audio', '#link'));
    foreach ($data as $row) {
        //        $line = array($row->fullname, $row->group, $row->country, $row->institution, $row->discussion, $row->posts, $row->replies, $row->international_reply, $row->domestic_reply, $row->self_reply, $row->stale_reply,$row->repliestoseed, $row->replytime, $row->l1, $row->l2, $row->l3, $row->l4, $row->maxdepth, $row->avedepth, $row->wordcount, $row->views, $row->multimedia, $row->imagenum, $row->videonum, $row->audionum, $row->linknum, $row->participants, $row->multinationals);
        $line = array($row->firstname, $row->surname, $row->country, $row->institution, $row->group, $row->discussion, $row->participants, $row->multinationals, $row->posts, $row->replies, $row->stale_reply, $row->self_reply, $row->repliestoseed, $row->l1, $row->l2, $row->l3, $row->l4, $row->maxdepth, $row->avedepth, $row->wordcount, $row->views, $row->imagenum, $row->videonum, $row->audionum, $row->linknum);
        $csvexport->add_data($line);
    }
} elseif ($type == 2) { //Goupごと
    $groupdata = new report_discussion_metrics\select\get_group_data($courseid, $forumid, $discussions, $discussionarray, $firstposts, $groups, $starttime, $endtime, $stale_reply_days);
    $data = $groupdata->data;

    //    $csvexport->add_data(array('name', 'users', 'multinationals', 'repliestoseed', 'replies', $strinternational, $strdomestic, $strself, $strstale,'#E1','#E2','#E3','#E4','Max E','Average E', 'repliedusers', 'notrepliedusers', 'wordcount', 'views', 'multimedia',$strimage,$straudio,$strlink,$strvideo));
    $csvexport->add_data(array($strgroup, '# of active members', '# of inactive members', 'Total discussion joined', 'Total discussion participants', $strposts, 'Total Replies', 'Total nationalities', $strstale, $strself, $strdirect, '#E1', '#E2', '#E3', '#E4', 'Max E', 'Average E', $strwordcount, $strviews, $strimage, $strvideo, $straudio, $strlink));
    foreach ($data as $row) {
        //        $line = array($row->name, $row->users, $row->multinationals, $row->repliestoseed, $row->replies, $row->international_reply, $row->domestic_reply, $row->self_reply, $row->stale_reply,$row->l1,$row->l2,$row->l3,$row->l4, $row->maxdepth,$row->avedepth,$row->repliedusers, $row->notrepliedusers, $row->wordcount, $row->views, $row->multimedia,$row->imgnum,$row->audionum,$row->linknum,$row->videonum);
        $line =  array($row->name, $row->repliedusers, $row->notrepliedusers, $row->discussion, $row->users, $row->posts, $row->replies, $row->multinationals, $row->stale_reply, $row->self_reply, $row->repliestoseed, $row->l1, $row->l2, $row->l3, $row->l4, $row->maxdepth, $row->avedepth, $row->wordcount, $row->views, $row->imgnum, $row->videonum, $row->audionum, $row->linknum);
        $csvexport->add_data($line);
    }
} elseif ($type == 3) { //Dialogue(discussion)の集計
    $discussiondata = new report_discussion_metrics\select\get_discussion_data($students, $discussions, $groupid, $starttime, $endtime);
    $data = $discussiondata->data;
    $csvexport->add_data(array('forumname', 'name', 'posts', 'bereplied', 'threads', 'maxdepth', 'l1', 'l2', 'l3', 'l4', 'multimedia', 'replytime', 'density'));
    foreach ($data as $row) {
        $line = array($row->forumname, $row->name, $row->posts, $row->bereplied, $row->threads, $row->maxdepth, $row->l1, $row->l2, $row->l3, $row->l4, $row->multimedia, $row->replytime, $row->density);
        $csvexport->add_data($line);
    }
} elseif ($type == 4) { //DialogueをGroupごと
    $dialoguedata = new report_discussion_metrics\select\get_dialogue_data($courseid, $discussions, $groups, $starttime, $endtime);
    $data = $dialoguedata->data;
    $csvexport->add_data(array('groupname', 'forumname', 'name', 'posts', 'bereplied', $strdirect, 'l1', 'l2', 'l3', 'l4', 'multimedia', 'replytime', 'density'));
    foreach ($data as $row) {
        $line = array($row->groupname, $row->forumname, $row->name, $row->posts, $row->bereplied, $row->threads, $row->l1, $row->l2, $row->l3, $row->l4, $row->multimedia, $row->replytime, $row->density);
        $csvexport->add_data($line);
    }
} elseif ($type == 5) { //Countryごと
    $countrydata = new report_discussion_metrics\select\get_country_data($students, $courseid, $forumid, $discussions, $discussionarray, $firstposts, $starttime, $endtime);
    $data = $countrydata->data;
    $csvexport->add_data(array('country', 'users', $strdirect, 'replies', 'repliedusers', 'notrepliedusers', 'wordcount', 'views', 'multimedia'));
    foreach ($data as $row) {
        $line = array($row->country, $row->users, $row->repliestoseed, $row->replies, $row->repliedusers, $row->notrepliedusers, $row->wordcount, $row->views, $row->multimedia);
        $csvexport->add_data($line);
    }
} elseif ($type == 6) { //DialogueをGroupごと
    $groupcountrydata = new report_discussion_metrics\select\get_group_country_data($students, $courseid, $forumid, $discussions, $discussionarray, $firstposts, $groups, $starttime, $endtime);
    $data = $groupcountrydata->data;
    $csvexport->add_data(array('groupname', 'country', 'users', $strdirect, 'replies', 'repliedusers', 'notrepliedusers', 'wordcount', 'views', 'multimedia'));
    foreach ($data as $group) {
        foreach ($group as $row) {
            $line = array($row->groupname, $row->country, $row->users, $row->repliestoseed, $row->replies, $row->repliedusers, $row->notrepliedusers, $row->wordcount, $row->views, $row->multimedia);
            $csvexport->add_data($line);
        }
    }
}

$csvexport->download_file();
