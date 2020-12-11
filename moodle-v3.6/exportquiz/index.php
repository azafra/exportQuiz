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


require_once("../../config.php");
require_once("locallib.php");

$id = required_param('id', PARAM_INT);

$PAGE->set_url('/mod/exportquiz/index.php', array('id' => $id));
$PAGE->set_pagelayout('incourse');

if (!$course = $DB->get_record('course', array('id' => $id))) {
    print_error('invalidcourseid');
}

$coursecontext = context_course::instance($id);
require_login($course);
$PAGE->set_pagelayout('incourse');

// Log this request.
$params = array(
        'context' => $coursecontext
);
$event = \mod_exportquiz\event\course_module_instance_list_viewed::create($params);
$event->trigger();

// Print the header.
$strexportquizzes = get_string("modulenameplural", "exportquiz");
$streditquestions = '';
$editqcontexts = new question_edit_contexts($coursecontext);
if ($editqcontexts->have_one_edit_tab_cap('questions')) {
    $streditquestions =
            "<form target=\"_parent\" method=\"get\" action=\"$CFG->wwwroot/question/edit.php\">
               <div>
               <input type=\"hidden\" name=\"courseid\" value=\"$course->id\" />
               <input type=\"submit\" value=\"".get_string("editquestions", "exportquiz")."\" />
               </div>
             </form>";
}

$PAGE->navbar->add($strexportquizzes);
$PAGE->set_title($strexportquizzes);
$PAGE->set_button($streditquestions);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

// Get all the appropriate data.
if (!$exportquizzes = get_all_instances_in_course('exportquiz', $course)) {
    notice(get_string('thereareno', 'moodle', $strexportquizzes), "../../course/view.php?id=$course->id");
    echo $OUTPUT->footer();
    die;
}

$isteacher = has_capability('mod/exportquiz:viewreports', $coursecontext);

// Check if we need the closing date header.
$showclosingheader = false;
$showfeedback = false;
$therearesome = false;
foreach ($exportquizzes as $exportquiz) {
    if ($exportquiz->timeclose != 0 ) {
        $showclosingheader = true;
    }
    if ($exportquiz->visible || $isteacher) {
        $therearesome = true;
    }
}

if (!$therearesome) {
    notice(get_string('thereareno', 'moodle', $strexportquizzes), "../../course/view.php?id=$course->id");
    echo $OUTPUT->footer();
    die;
}

// Configure table for displaying the list of instances.
$headings = array(get_string('name'));
$align = array('left');

if ($showclosingheader) {
    array_push($headings, get_string('exportquizcloses', 'exportquiz'));
    array_push($align, 'left');
}

array_unshift($headings, get_string('sectionname', 'format_'.$course->format));
array_unshift($align, 'center');

$showing = '';

if (has_capability('mod/exportquiz:viewreports', $coursecontext)) {
    array_push($headings, get_string('results', 'exportquiz'));
    array_push($align, 'left');
    $showing = 'stats';

} else if (has_capability('mod/exportquiz:attempt', $coursecontext)) {
    array_push($headings, get_string('grade', 'exportquiz'));
    array_push($align, 'left');
    if ($showfeedback) {
        array_push($headings, get_string('feedback', 'exportquiz'));
        array_push($align, 'left');
    }
    $showing = 'grades';
}

$table = new html_table();
$table->head = $headings;
$table->align = $align;

// Populate the table with the list of instances.
$currentsection = '';
foreach ($exportquizzes as $exportquiz) {
    $cm = get_coursemodule_from_instance('exportquiz', $exportquiz->id);
    $context = context_module::instance($cm->id);
    $data = array();

    $grades = array();
    if ($showing == 'grades') {
        if ($gradearray = exportquiz_get_user_grades($exportquiz, $USER->id)) {
            $grades[$exportquiz->id] = $gradearray[$USER->id]['rawgrade'];
        } else {
            $grades[$exportquiz->id] = null;
        }
    }

    // Section number if necessary.
    $strsection = '';
    if ($exportquiz->section != $currentsection) {
        if ($exportquiz->section) {
            $strsection = $exportquiz->section;
            $strsection = get_section_name($course, $exportquiz->section);
        }
        if ($currentsection) {
            $learningtable->data[] = 'hr';
        }
        $currentsection = $exportquiz->section;
    }
    $data[] = $strsection;

    // Link to the instance.
    $class = '';
    if (!$exportquiz->visible) {
        $class = ' class="dimmed"';
    }
    $data[] = "<a$class href=\"view.php?id=$exportquiz->coursemodule\">" .
            format_string($exportquiz->name, true) . '</a>';

    // Close date.
    if ($exportquiz->timeclose) {
        $data[] = userdate($exportquiz->timeclose);
    } else if ($showclosingheader) {
        $data[] = '';
    }

    if ($showing == 'stats') {
        // The $exportquiz objects returned by get_all_instances_in_course have the necessary $cm
        // fields set to make the following call work.
        $data[] = exportquiz_attempt_summary_link_to_reports($exportquiz, $cm, $context);

    } else if ($showing == 'grades') {
        // Grade and feedback.
        list($someoptions, $alloptions) = exportquiz_get_combined_reviewoptions($exportquiz);

        $grade = '';
        $feedback = '';
        if ($exportquiz->grade && array_key_exists($exportquiz->id, $grades)) {
            if ($alloptions->marks >= question_display_options::MARK_AND_MAX) {
                $a = new stdClass();
                $a->grade = exportquiz_format_grade($exportquiz, $grades[$exportquiz->id]);
                $a->maxgrade = exportquiz_format_grade($exportquiz, $exportquiz->grade);
                $grade = get_string('outofshort', 'exportquiz', $a);
            }
        }
        $data[] = $grade;
        if ($showfeedback) {
            $data[] = $feedback;
        }
    }

    $table->data[] = $data;
} // End of loop over exportquiz instances.

// Display the table.
echo html_writer::table($table);

// Finish the page.
echo $OUTPUT->footer();
