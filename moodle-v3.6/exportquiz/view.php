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


require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir  . '/gradelib.php');
require_once($CFG->libdir  . '/completionlib.php');
require_once($CFG->dirroot . '/mod/exportquiz/locallib.php');


$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or.
$q  = optional_param('q', 0, PARAM_INT);  // exportquiz instance ID.
$edit = optional_param('edit', -1, PARAM_BOOL);

if ($id) {
    if (!$cm = get_coursemodule_from_id('exportquiz', $id)) {
        print_error('invalidcoursemodule');
    }
    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('coursemisconf');
    }
    if (!$exportquiz = $DB->get_record('exportquiz', array('id' => $cm->instance))) {
        print_error('invalidcoursemodule');
    }
} else {
    if (!$exportquiz = $DB->get_record('exportquiz', array('id' => $q))) {
        print_error('invalidexportquizid', 'exportquiz');
    }
    if (!$course = $DB->get_record('course', array('id' => $exportquiz->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance('exportquiz', $exportquiz->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
}

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
$coursecontext = context_course::instance($course->id);

// Print the page header.
$PAGE->set_url('/mod/exportquiz/view.php', array('id' => $cm->id));
$PAGE->set_title($exportquiz->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('report');

// Output starts here.
echo $OUTPUT->header();

// Print the page header.
if ($edit != -1 and $PAGE->user_allowed_editing()) {
    $USER->editing = $edit;
}

echo $OUTPUT->heading(format_string($exportquiz->name));

// Print the tabs to switch mode.
if (has_capability('mod/exportquiz:viewreports', $context)) {
    $currenttab = 'info';
    include_once('tabs.php');
}
else
{
	$url = new moodle_url($CFG->wwwroot);
    echo html_writer::link($url, get_string('nopermissions', 'exportquiz'));
}

// If not in all group questions have been output a link to edit.php.
$emptygroups = exportquiz_get_empty_groups($exportquiz);

if (has_capability('mod/exportquiz:manage', $context)) {
    echo '<div class="box generalbox linkbox">';
    if (count($emptygroups) > 0) {
        $url = new moodle_url($CFG->wwwroot . '/mod/exportquiz/edit.php',
                array('cmid' => $cm->id, 'groupnumber' => $emptygroups[0], 'noquestions' => 1));
        echo html_writer::link($url, get_string('emptygroups', 'exportquiz'));
    } else if ($exportquiz->docscreated) {
        echo get_string('pdfscreated', 'exportquiz');
    } else {
        echo get_string('nopdfscreated', 'exportquiz');
	
	$url = new moodle_url($CFG->wwwroot . '/mod/exportquiz/createquiz.php', array('q' => $q));
	echo html_writer::link($url, "<br />".get_string('gotocreate', 'exportquiz'));
    }
    echo '</div>';
}

// Log this request.
$params = array(
    'objectid' => $cm->id,
    'context' => $context
);
$event = \mod_exportquiz\event\course_module_viewed::create($params);
$event->add_record_snapshot('exportquiz', $exportquiz);
$event->trigger();

if (!empty($exportquiz->time)) {
    echo '<div class="exportquizinfo">'.userdate($exportquiz->time).'</div>';
}

if (has_capability('mod/exportquiz:view', $context)) {
    // Print exportquiz description.
    if (trim(strip_tags($exportquiz->intro))) {
        $formatoptions = new stdClass();
        $formatoptions->noclean = true;
        echo $OUTPUT->box(format_text($exportquiz->intro, $exportquiz->introformat, $formatoptions),
                'generalbox', 'intro');
    }
}


// Finish the page.
echo $OUTPUT->footer();
