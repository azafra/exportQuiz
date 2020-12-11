<?php
// This file is part of mod_exportquiz for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/exportquiz/locallib.php');
require_once($CFG->dirroot . '/mod/exportquiz/exportquiz.class.php');
require_once($CFG->dirroot . '/mod/exportquiz/addrandomform.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/question/category_class.php');


// These params are only passed from page request to request while we stay on
// this page otherwise they would go in question_edit_setup.
$scrollpos = optional_param('scrollpos', '', PARAM_INT);

// Patch problem with nested forms and category parameter, otherwise question_edit_setup has problems.
if(array_key_exists('savechanges', $_POST) && $_POST['savechanges']) {
    unset($_POST['category']);
}
if(array_key_exists('exportquizdeleteselected', $_POST) && $_POST['exportquizdeleteselected']) {
    unset($_POST['category']);
}

list($thispageurl, $contexts, $cmid, $cm, $exportquiz, $pagevars) = question_edit_setup('editq', '/mod/exportquiz/edit.php', true);

$defaultcategoryobj = question_make_default_categories($contexts->all());
$defaultcategory = $defaultcategoryobj->id . ',' . $defaultcategoryobj->contextid;

// See if we do bulk grade editing.
$exportquizgradetool = optional_param('gradetool', -1, PARAM_BOOL);
if ($exportquizgradetool > -1) {
    $thispageurl->param('gradetool', $exportquizgradetool);
    set_user_preference('exportquiz_gradetab', $exportquizgradetool);
} else {
    $exportquizgradetool = get_user_preferences('exportquiz_gradetab', 0);
}

// Determine groupid.
$groupnumber    = optional_param('groupnumber', 1, PARAM_INT);
if ($groupnumber === -1 and !empty($SESSION->question_pagevars['groupnumber'])) {
    $groupnumber = $SESSION->question_pagevars['groupnumber'];
}

if ($groupnumber === -1) {
    $groupnumber = 1;
}

$exportquiz->groupnumber = $groupnumber;
$thispageurl->param('groupnumber', $exportquiz->groupnumber);

// Load the exportquiz group and set the groupid in the exportquiz object.
if ($exportquizgroup = exportquiz_get_group($exportquiz, $groupnumber)) {
    $exportquiz->groupid = $exportquizgroup->id;
    $groupquestions = exportquiz_get_group_question_ids($exportquiz);
    $exportquiz->questions = $groupquestions;
} else {
    print_error('invalidgroupnumber', 'exportquiz');
}

$exportquiz->sumgrades = $exportquizgroup->sumgrades;

$exportquizhasattempts = exportquiz_has_scanned_pages($exportquiz->id);
$docscreated = $exportquiz->docscreated;

$PAGE->set_url($thispageurl);

// Get the course object and related bits.
$course = $DB->get_record('course', array('id' => $exportquiz->course), '*', MUST_EXIST);
$exportquizobj = new exportquiz($exportquiz, $cm, $course);
$structure = $exportquizobj->get_structure();

if ($warning = optional_param('warning', '', PARAM_TEXT)) {
    $structure->add_warning(urldecode($warning));
}

// You need mod/exportquiz:manage in addition to question capabilities to access this page.
require_capability('mod/exportquiz:manage', $contexts->lowest());

// Log this visit.
$params = array(
    'courseid' => $course->id,
    'context' => $contexts->lowest(),
    'other' => array(
        'exportquizid' => $exportquiz->id,
    )
);
$event = \mod_exportquiz\event\edit_page_viewed::create($params);
$event->trigger();

// Process commands ============================================================.

// Get the list of question ids had their check-boxes ticked.
$selectedslots = array();
$params = (array) data_submitted();
foreach ($params as $key => $value) {
    if (preg_match('!^s([0-9]+)$!', $key, $matches)) {
        $selectedslots[] = $matches[1];
    }
}

$afteractionurl = new moodle_url($thispageurl);
if ($scrollpos) {
    $afteractionurl->param('scrollpos', $scrollpos);
}

// Get the list of question ids had their check-boxes ticked.
$selectedquestionids = array();
$params = (array) data_submitted();
foreach ($params as $key => $value) {
    if (preg_match('!^s([0-9]+)$!', $key, $matches)) {
        $selectedquestionids[] = $matches[1];
    }
} 

if (optional_param('exportquizdeleteselected', false, PARAM_BOOL) &&
        !empty($selectedquestionids) && confirm_sesskey()) {

    exportquiz_remove_questionlist($exportquiz, $selectedquestionids);
    exportquiz_delete_template_usages($exportquiz);
    $exportquiz->sumgrades = exportquiz_update_sumgrades($exportquiz);
    redirect($afteractionurl);
}

if (optional_param('repaginate', false, PARAM_BOOL) && confirm_sesskey()) {
    // Re-paginate the exportquiz.
    $structure->check_can_be_edited();
    $questionsperpage = optional_param('questionsperpage', $exportquiz->questionsperpage, PARAM_INT);
    exportquiz_repaginate_questions($exportquiz->id, $exportquiz->groupid, $questionsperpage );
    exportquiz_delete_template_usages($exportquiz);
    redirect($afteractionurl);
}

if (($addquestion = optional_param('addquestion', 0, PARAM_INT)) && confirm_sesskey()) {
    // Add a single question to the current exportquiz.
    $structure->check_can_be_edited();
    exportquiz_require_question_use($addquestion);
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    // If the question is already in another group, take the maxmark of that.
    if ($maxmarks = $DB->get_fieldset_select('exportquiz_group_questions', 'maxmark',
            'exportquizid = :exportquizid AND questionid = :questionid', 
            array('exportquizid' => $exportquiz->id, 'questionid' => $addquestion))) {
        exportquiz_add_exportquiz_question($addquestion, $exportquiz, $addonpage, $maxmarks[0]);
    } else {
        exportquiz_add_exportquiz_question($addquestion, $exportquiz, $addonpage);
    }
    exportquiz_delete_template_usages($exportquiz);
    exportquiz_update_sumgrades($exportquiz);
    $thispageurl->param('lastchanged', $addquestion);
    redirect($afteractionurl);
}

if (optional_param('add', false, PARAM_BOOL) && confirm_sesskey()) {
    $structure->check_can_be_edited();
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    // Add selected questions to the current exportquiz.
    $rawdata = (array) data_submitted();
    foreach ($rawdata as $key => $value) { // Parse input for question ids.
        if (preg_match('!^q([0-9]+)$!', $key, $matches)) {
            $key = $matches[1];
            exportquiz_require_question_use($key);
            // If the question is already in another group, take the maxmark of that.
            if ($maxmarks = $DB->get_fieldset_select('exportquiz_group_questions', 'maxmark',
                    'exportquizid = :exportquizid AND questionid = :questionid',
                    array('exportquizid' => $exportquiz->id, 'questionid' => $key))) {
                exportquiz_add_exportquiz_question($key, $exportquiz, $addonpage, $maxmarks[0]);
            } else {
                exportquiz_add_exportquiz_question($key, $exportquiz, $addonpage);
            }
        }
    }
    exportquiz_delete_template_usages($exportquiz);
    exportquiz_update_sumgrades($exportquiz);
    redirect($afteractionurl);
}

if ((optional_param('addrandom', false, PARAM_BOOL)) && confirm_sesskey()) {
    // Add random questions to the exportquiz.
    $structure->check_can_be_edited();
    $recurse = optional_param('recurse', 0, PARAM_BOOL);
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    $categoryid = required_param('categoryid', PARAM_INT);
    $randomcount = required_param('randomcount', PARAM_INT);
    exportquiz_add_random_questions($exportquiz, $addonpage, $categoryid, $randomcount, $recurse);

    exportquiz_delete_template_usages($exportquiz);
    exportquiz_update_sumgrades($exportquiz);
    redirect($afteractionurl);
}

if (optional_param('savechanges', false, PARAM_BOOL) && confirm_sesskey()) {

    // Parameter to copy selected questions to another group.
    $copyselectedtogroup = optional_param('copyselectedtogrouptop', 0, PARAM_INT);

    if ($copyselectedtogroup) {

        if (($selectedquestionids) && ($newgroup = exportquiz_get_group($exportquiz, $copyselectedtogroup))) {
            $fromexportgroup = optional_param('fromexportgroup', 0, PARAM_INT);

            exportquiz_add_questionlist_to_group($selectedquestionids, $exportquiz, $newgroup, $fromexportgroup);

            exportquiz_update_sumgrades($exportquiz, $newgroup->id);
            // Delete the templates, just to be sure.
            exportquiz_delete_template_usages($exportquiz);
        }
        redirect($afteractionurl);
    }

    // If rescaling is required save the new maximum.
    $maxgrade = str_replace(',', '.', optional_param('maxgrade', -1, PARAM_RAW));
    if (!is_numeric( $maxgrade)) {
        $afteractionurl->param('warning', urlencode(get_string('maxgradewarning', 'exportquiz')));
    } else {
        $maxgrade = unformat_float($maxgrade);
        if ($maxgrade >= 0) {
            exportquiz_set_grade($maxgrade, $exportquiz);
//        exportquiz_update_all_final_grades($exportquiz);
            exportquiz_update_grades($exportquiz, 0, true);
        }
    }

    redirect($afteractionurl);
}

$savegrades = optional_param('savegrades', '', PARAM_ALPHA);

if ($savegrades == 'bulksavegrades' && confirm_sesskey()) {
    $rawdata = (array) data_submitted();

    foreach ($rawdata as $key => $value) {
        if (preg_match('!^g([0-9]+)$!', $key, $matches)) {
            if (is_numeric(str_replace(',', '.', $value))) {
                // Parse input for question -> grades.
                $questionid = $matches[1];
                exportquiz_update_question_instance($exportquiz, $questionid, unformat_float($value));
            } else {
                $bulkgradewarning = true;
            }
        }
    }

    // Redmine 983: Upgrade sumgrades for all exportquiz groups.
    if ($groups = $DB->get_records('exportquiz_groups', array('exportquizid' => $exportquiz->id), 'number',
            '*', 0, $exportquiz->numgroups)) {
        foreach ($groups as $group) {
            $sumgrade = exportquiz_update_sumgrades($exportquiz, $group->id);
        }
    }

    exportquiz_update_grades($exportquiz, 0, true);
    redirect($afteractionurl);
}

// Get the question bank view.
$questionbank = new mod_exportquiz\question\bank\custom_view($contexts, $thispageurl, $course, $cm, $exportquiz);
$questionbank->set_exportquiz_has_scanned_pages($docscreated);
$questionbank->process_actions($thispageurl, $cm);

// End of process commands =====================================================.

$PAGE->set_pagelayout('incourse');
$PAGE->set_pagetype('mod-exportquiz-edit');

$output = $PAGE->get_renderer('mod_exportquiz', 'edit');

$PAGE->set_title(get_string('editingexportquizx', 'exportquiz', format_string($exportquiz->name)));
$PAGE->set_heading($course->fullname);
$node = $PAGE->settingsnav->find('mod_exportquiz_edit', navigation_node::TYPE_SETTING);
if ($node) {
    $node->make_active();
}
echo $OUTPUT->header();

// Initialise the JavaScript.
$exportquizeditconfig = new stdClass();
$exportquizeditconfig->url = $thispageurl->out(true, array('qbanktool' => '0'));
$exportquizeditconfig->dialoglisteners = array();
$numberoflisteners = $DB->get_field_sql("
    SELECT COALESCE(MAX(page), 1)
      FROM {exportquiz_group_questions}
     WHERE exportquizid = ?
       AND exportgroupid = ?", array($exportquiz->id, $exportquiz->groupid));

for ($pageiter = 1; $pageiter <= $numberoflisteners; $pageiter++) {
    $exportquizeditconfig->dialoglisteners[] = 'addrandomdialoglaunch_' . $pageiter;
}

$PAGE->requires->data_for_js('exportquiz_edit_config', $exportquizeditconfig);
$PAGE->requires->js('/question/qengine.js');

$currenttab = 'editq';
if ($exportquizgradetool) {
    $mode = 'grade';
} else {
    $mode = 'edit';
}

require_once('tabs.php');

//exportquiz_print_status_bar($exportquiz);

// Questions wrapper start.
if ($mode == 'grade') {
    echo html_writer::start_tag('div', array('class' => 'mod-exportquiz-edit-content edit_grades'));
} else {
    echo html_writer::start_tag('div', array('class' => 'mod-exportquiz-edit-content'));
}

$letterstr = 'ABCDEFGHIJKL';
$groupletters = array();

for ($i = 1; $i <= $exportquiz->numgroups; $i++) {
    $groupletters[$i] = $letterstr[$i - 1];
}

if ($exportquizgradetool) {
    echo $output->edit_grades_page($exportquizobj, $structure, $contexts, $thispageurl, $pagevars, $groupletters);
} else {
    echo $output->edit_page($exportquizobj, $structure, $contexts, $thispageurl, $pagevars, $groupletters);
}

// Questions wrapper end.
echo html_writer::end_tag('div');

echo $OUTPUT->footer();
