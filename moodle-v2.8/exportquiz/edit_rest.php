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


if (!defined('AJAX_SCRIPT')) {
    define('AJAX_SCRIPT', true);
}

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/exportquiz/locallib.php');
require_once($CFG->dirroot . '/mod/exportquiz/exportquiz.class.php');

// Initialise ALL the incoming parameters here, up front.
$exportquizid     = required_param('exportquizid', PARAM_INT);
$exportgroupid = required_param('exportgroupid', PARAM_INT);
$class      = required_param('class', PARAM_ALPHA);
$field      = optional_param('field', '', PARAM_ALPHA);
$instanceid = optional_param('instanceId', 0, PARAM_INT);
$sectionid  = optional_param('sectionId', 0, PARAM_INT);
$previousid = optional_param('previousid', 0, PARAM_INT);
$value      = optional_param('value', 0, PARAM_INT);
$column     = optional_param('column', 0, PARAM_ALPHA);
$id         = optional_param('id', 0, PARAM_INT);
$summary    = optional_param('summary', '', PARAM_RAW);
$sequence   = optional_param('sequence', '', PARAM_SEQUENCE);
$visible    = optional_param('visible', 0, PARAM_INT);
$pageaction = optional_param('action', '', PARAM_ALPHA); // Used to simulate a DELETE command.
$maxmark    = optional_param('maxmark', '', PARAM_RAW);
$page       = optional_param('page', '', PARAM_INT);
$PAGE->set_url('/mod/exportquiz/edit-rest.php',
        array('exportquizid' => $exportquizid, 'class' => $class));

require_sesskey();

$exportquiz = $DB->get_record('exportquiz', array('id' => $exportquizid), '*', MUST_EXIST);
if ($exportquizgroup = $DB->get_record('exportquiz_groups', array('id' => $exportgroupid))){
    $exportquiz->groupid = $exportquizgroup->id;
} else {
    print_error('invalidgroupnumber', 'exportquiz');
}

$cm = get_coursemodule_from_instance('exportquiz', $exportquiz->id, $exportquiz->course);
$course = $DB->get_record('course', array('id' => $exportquiz->course), '*', MUST_EXIST);
require_login($course, false, $cm);

$exportquizobj = new exportquiz($exportquiz, $cm, $course);
$structure = $exportquizobj->get_structure();
$modcontext = context_module::instance($cm->id);

echo $OUTPUT->header(); // Send headers.

// OK, now let's process the parameters and do stuff
// MDL-10221 the DELETE method is not allowed on some web servers,
// so we simulate it with the action URL param.
$requestmethod = $_SERVER['REQUEST_METHOD'];
if ($pageaction == 'DELETE') {
    $requestmethod = 'DELETE';
}

switch($requestmethod) {
    case 'POST':
    case 'GET': // For debugging.

        switch ($class) {
            case 'section':
                break;

            case 'resource':
                switch ($field) {
                    case 'move':
                        require_capability('mod/exportquiz:manage', $modcontext);
                        exportquiz_delete_template_usages($exportquiz);
                        $structure->move_slot($id, $previousid, $page);
                        echo json_encode(array('visible' => true));
                        break;

                    case 'getmaxmark':
                        require_capability('mod/exportquiz:manage', $modcontext);
                        $slot = $DB->get_record('exportquiz_group_questions', array('id' => $id), '*', MUST_EXIST);
                        echo json_encode(array('instancemaxmark' =>
                                exportquiz_format_question_grade($exportquiz, $slot->maxmark)));
                        break;

                    case 'updatemaxmark':
                        require_capability('mod/exportquiz:manage', $modcontext);
                        $slot = $structure->get_slot_by_id($id);
                        if (!is_numeric(str_replace(',', '.', $maxmark))) {
                            echo json_encode(array('instancemaxmark' => exportquiz_format_question_grade($exportquiz, $slot->maxmark),
                                    'newsummarks' => exportquiz_format_grade($exportquiz, $exportquiz->sumgrades)));
                            break;
                        }
                        if ($structure->update_slot_maxmark($slot, $maxmark)) {
                            // Recalculate the sumgrades for all groups
                            if ($groups = $DB->get_records('exportquiz_groups', array('exportquizid' => $exportquiz->id), 'number',
                                '*', 0, $exportquiz->numgroups)) {
                                foreach ($groups as $group) {
                                   $sumgrade = exportquiz_update_sumgrades($exportquiz, $group->id);
                                }
                            }

                            // Grade has really changed.
                            //$exportquiz->sumgrades = exportquiz_update_sumgrades($exportquiz);
                            exportquiz_update_question_instance($exportquiz, $slot->questionid, unformat_float($maxmark));
                            //exportquiz_update_all_final_grades($exportquiz);
                            exportquiz_update_grades($exportquiz, 0, true);
                        }
                        $newsummarks = $DB->get_field('exportquiz_groups', 'sumgrades', array('id' => $exportquizgroup->id));
                        echo json_encode(array('instancemaxmark' => exportquiz_format_question_grade($exportquiz, $slot->maxmark),
                                'newsummarks' => format_float($newsummarks, $exportquiz->decimalpoints)));
                        break;
                    case 'updatepagebreak':
                        require_capability('mod/exportquiz:manage', $modcontext);
                        exportquiz_delete_template_usages($exportquiz);
                        $slots = $structure->update_page_break($exportquiz, $id, $value);
                        $json = array();
                        foreach ($slots as $slot) {
                            $json[$slot->slot] = array('id' => $slot->id, 'slot' => $slot->slot,
                                                            'page' => $slot->page);
                        }
                        echo json_encode(array('slots' => $json));
                        break;
                }
                break;

            case 'course':
                break;
        }
        break;

    case 'DELETE':
        switch ($class) {
            case 'resource':
                require_capability('mod/exportquiz:manage', $modcontext);
                if (!$slot = $DB->get_record('exportquiz_group_questions',
                        array('exportquizid' => $exportquiz->id, 'id' => $id))) {
                    throw new moodle_exception('AJAX commands.php: Bad slot ID '.$id);
                }
                $structure->remove_slot($exportquiz, $slot->slot);
                exportquiz_delete_template_usages($exportquiz);
                exportquiz_update_sumgrades($exportquiz);
                echo json_encode(array('newsummarks' => exportquiz_format_grade($exportquiz, $exportquiz->sumgrades),
                            'deleted' => true, 'newnumquestions' => $structure->get_question_count()));
                break;
        }
        break;
}
