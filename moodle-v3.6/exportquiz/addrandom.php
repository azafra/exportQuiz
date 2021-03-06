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
require_once($CFG->dirroot . '/mod/exportquiz/addrandomform.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/question/category_class.php');

list($thispageurl, $contexts, $cmid, $cm, $exportquiz, $pagevars) =
        question_edit_setup('editq', '/mod/exportquiz/addrandom.php', true);

// These params are only passed from page request to request while we stay on
// this page otherwise they would go in question_edit_setup.
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$addonpage = optional_param('addonpage', 0, PARAM_INT);
$category = optional_param('category', 0, PARAM_INT);
$scrollpos = optional_param('scrollpos', 0, PARAM_INT);
$groupnumber = optional_param('groupnumber', 1, PARAM_INT);

// Get the course object and related bits.
if (!$course = $DB->get_record('course', array('id' => $exportquiz->course))) {
    print_error('invalidcourseid');
}
// You need mod/exportquiz:manage in addition to question capabilities to access this page.
// You also need the moodle/question:useall capability somewhere.
require_capability('mod/exportquiz:manage', $contexts->lowest());
if (!$contexts->having_cap('moodle/question:useall')) {
    print_error('nopermissions', '', '', 'use');
}

if ($groupnumber === -1 and !empty($SESSION->question_pagevars['groupnumber'])) {
    $groupnumber = $SESSION->question_pagevars['groupnumber'];
}

if ($groupnumber === -1) {
    $groupnumber = 1;
}

$exportquiz->groupnumber = $groupnumber;

// Load the exportquiz group and set the groupid in the exportquiz object.
if ($exportquizgroup = exportquiz_get_group($exportquiz, $groupnumber)) {
    $exportquiz->groupid = $exportquizgroup->id;
    //$groupquestions = exportquiz_get_group_question_ids($exportquiz);
    // Clean layout. Remove empty pages if there are no questions in the exportquiz group.
    //$exportquiz->questions = $groupquestions;
} else {
    print_error('invalidgroupnumber', 'exportquiz');
}


if ($returnurl) {
    $returnurl = new moodle_url($returnurl);
} else {
    $returnurl = new moodle_url('/mod/exportquiz/edit.php',
            array('cmid' => $cmid,
                  'groupnumber' => $exportquiz->groupnumber
            )); 
}
if ($scrollpos) {
    $returnurl->param('scrollpos', $scrollpos);
}

$thispageurl->param('groupnumber', $exportquiz->groupnumber);
$PAGE->set_url($thispageurl);

$defaultcategoryobj = question_make_default_categories($contexts->all());
$defaultcategory = $defaultcategoryobj->id . ',' . $defaultcategoryobj->contextid;

$qcobject = new question_category_object(
    $pagevars['cpage'],
    $thispageurl,
    $contexts->having_one_edit_tab_cap('categories'),
    $defaultcategoryobj->id,
    $defaultcategory,
    null,
    $contexts->having_cap('moodle/question:add'));

$mform = new exportquiz_add_random_form(new moodle_url('/mod/exportquiz/addrandom.php'),
                array('contexts' => $contexts,
                      'cat' => $pagevars['cat'],
                      'groupnumber'=> $exportquiz->groupnumber
                ));

if ($mform->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $mform->get_data()) {
    if (!empty($data->existingcategory)) {
        list($categoryid) = explode(',', $data->category);
        $includesubcategories = !empty($data->includesubcategories);
        $returnurl->param('cat', $data->category);

    } else if (!empty($data->newcategory)) {
        list($parentid, $contextid) = explode(',', $data->parent);
        $categoryid = $qcobject->add_category($data->parent, $data->name, '', true);
        $includesubcategories = 0;

        $returnurl->param('cat', $categoryid . ',' . $contextid);
    } else {
        throw new coding_exception(
                'It seems a form was submitted without any button being pressed???');
    }

    exportquiz_add_random_questions($exportquiz, $exportquizgroup, $categoryid, $data->numbertoadd, $includesubcategories);
    exportquiz_delete_template_usages($exportquiz);
    exportquiz_update_sumgrades($exportquiz);
    redirect($returnurl);
}

$mform->set_data(array(
    'addonpage' => $addonpage,
    'returnurl' => $returnurl,
    'cmid' => $cm->id,
    'category' => $category,
));

// Setup $PAGE.
$streditingexportquiz = get_string('editinga', 'moodle', get_string('modulename', 'exportquiz'));
$PAGE->navbar->add($streditingexportquiz);
$PAGE->set_title($streditingexportquiz);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

if (!$exportquizname = $DB->get_field($cm->modname, 'name', array('id' => $cm->instance))) {
            print_error('invalidcoursemodule');
}
$groupletters = 'ABCDEFGHIJKL';
echo $OUTPUT->heading(get_string('addrandomquestiontoexportquiz', 'exportquiz',
        array('name' => $exportquizname, 'group' => $groupletters[$exportquiz->groupnumber - 1])), 2);
$mform->display();
echo $OUTPUT->footer();

