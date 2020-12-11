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



define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/exportquiz/locallib.php');
require_once($CFG->dirroot . '/question/editlib.php');

list($thispageurl, $contexts, $cmid, $cm, $exportquiz, $pagevars) =
        question_edit_setup('editq', '/mod/exportquiz/edit.php', true);

// Get the course object and related bits.
$course = $DB->get_record('course', array('id' => $exportquiz->course), '*', MUST_EXIST);
require_capability('mod/exportquiz:manage', $contexts->lowest());

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

// Create exportquiz question bank view.
$questionbank = new mod_exportquiz\question\bank\custom_view($contexts, $thispageurl, $course, $cm, $exportquiz);
$questionbank->set_exportquiz_has_scanned_pages(exportquiz_has_scanned_pages($exportquiz->id));

// Output.
$output = $PAGE->get_renderer('mod_exportquiz', 'edit');
$contents = $output->question_bank_contents($questionbank, $pagevars);
echo json_encode(array(
    'status'   => 'OK',
    'contents' => $contents,
));
