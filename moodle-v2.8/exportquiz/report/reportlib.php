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

/**
 * Helper functions for exportquiz reports
 *
 * @package       mod
 * @subpackage    exportquiz
 * @author        Jose Manuel Ventura Martínez
 * @copyright     2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @since         Moodle 2.2+
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/exportquiz/lib.php');
require_once($CFG->libdir . '/filelib.php');

define('EXPORTQUIZ_REPORT_DEFAULT_PAGE_SIZE', 30);
define('EXPORTQUIZ_REPORT_DEFAULT_GRADING_PAGE_SIZE', 10);

define('EXPORTQUIZ_REPORT_ATTEMPTS_ALL', 0);
define('EXPORTQUIZ_REPORT_ATTEMPTS_STUDENTS_WITH_NO', 1);
define('EXPORTQUIZ_REPORT_ATTEMPTS_STUDENTS_WITH', 2);
define('EXPORTQUIZ_REPORT_ATTEMPTS_ALL_STUDENTS', 3);

/**
 * Returns an array of reports to which the current user has access to.
 * @return array reports are ordered as they should be for display in tabs.
 */
function exportquiz_report_list($context) {
    global $DB;
    static $reportlist = null;
    if (!is_null($reportlist)) {
        return $reportlist;
    }

    $reports = $DB->get_records('exportquiz_reports', null, 'displayorder DESC', 'name, capability');
    $reportdirs = get_plugin_list('exportquiz');

    // Order the reports tab in descending order of displayorder.
    $reportcaps = array();
    foreach ($reports as $key => $report) {
        if (array_key_exists($report->name, $reportdirs)) {
            $reportcaps[$report->name] = $report->capability;
        }
    }

    // Add any other reports, which are on disc but not in the DB, on the end.
    foreach ($reportdirs as $reportname => $notused) {
        if (!isset($reportcaps[$reportname])) {
            $reportcaps[$reportname] = null;
        }
    }
    $reportlist = array();
    foreach ($reportcaps as $name => $capability) {
        if (empty($capability)) {
            $capability = 'mod/exportquiz:viewreports';
        }
        if (has_capability($capability, $context)) {
            $reportlist[] = $name;
        }
    }

    return $reportlist;
}

function exportquiz_report_unindex($datum) {
    if (!$datum) {
        return $datum;
    }
    $datumunkeyed = array();
    foreach ($datum as $value) {
        if (is_array($value)) {
            $datumunkeyed = array_merge($datumunkeyed, exportquiz_report_unindex($value));
        } else {
            $datumunkeyed[] = $value;
        }
    }
    return $datumunkeyed;
}

/**
 * Get the slots of real questions (not descriptions) in this exportquiz, in order.
 * @param object $exportquiz the exportquiz.
 * @return array of slot => $question object with fields
 *      ->slot, ->id, ->maxmark, ->number, ->length.
 */
function exportquiz_report_get_significant_questions($exportquiz) {
    global $DB;

    $questionids = $exportquiz->questions;
    if (empty($questionids)) {
        return array();
    }

    list($usql, $params) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED, 'qid');
    
    $params['exportquizid'] = $exportquiz->id;
    $groupsql = '';
    if ($exportquiz->groupid) {
        $groupsql = ' AND oqg.exportgroupid = :exportgroupid ';
        $params['exportgroupid'] = $exportquiz->groupid;
    }

    $rawquestions = $DB->get_records_sql("SELECT oqg.id as oqgid, q.id as questionid, q.length, oqg.maxmark
                                            FROM {question} q
                                            JOIN {exportquiz_group_questions} oqg ON oqg.questionid = q.id
                                           WHERE q.id $usql
                                             AND q.qtype <> 'description'
                                             AND oqg.exportquizid = :exportquizid
                                                 $groupsql
                                             AND q.length > 0", $params);
    // Make sure we have unique questionids. Not sure if DISTINCT in query captures all contingencies.
    $questions = array();
    foreach ($rawquestions as $rawquestion) {
        if (!array_key_exists($rawquestion->questionid, $questions)) {
            $question = new stdClass();
            $question->id = $rawquestion->questionid;
            $question->length = $rawquestion->length;
            $question->maxmark = $rawquestion->maxmark;
            $questions[$question->id] = $question;
        }
    }
    
    $number = 1;
    foreach ($questionids as $key => $id) {
        if (!array_key_exists($id, $questions)) {
            continue;
        }
        $questions[$id]->number = $number;
        $number += $questions[$id]->length;
    }

    return $questions;
}

/**
 * Format a number as a percentage out of $exportquiz->sumgrades
 * 
 * @param number $rawgrade the mark to format.
 * @param object $exportquiz the exportquiz settings
 * @param bool $round whether to round the results ot $exportquiz->decimalpoints.
 */
function exportquiz_report_scale_summarks_as_percentage($rawmark, $exportquiz, $round = true) {
    if ($exportquiz->sumgrades <= 0) {
        return '';
    }
    if (!is_numeric($rawmark)) {
        return $rawmark;
    }

    $mark = 100 * $rawmark / $exportquiz->sumgrades;
    if ($round) {
        $mark = exportquiz_format_grade($exportquiz, $mark);
    }
    return $mark . '%';
}

/**
 * Format a number as a percentage out of $exportquiz->sumgrades
 * 
 * @param number $rawgrade the mark to format.
 * @param object $exportquiz the exportquiz settings
 * @param bool $round whether to round the results ot $exportquiz->decimalpoints.
 */
function exportquiz_report_scale_grade($rawmark, $exportquiz, $round = true) {
    if ($exportquiz->sumgrades <= 0) {
        return '';
    }
    if (!is_numeric($rawmark)) {
        return $rawmark;
    }

    $mark = $rawmark / $exportquiz->sumgrades * $exportquiz->grade;
    if ($round) {
        $mark = exportquiz_format_grade($exportquiz, $mark);
    }
    return $mark;
}


/**
 * Create a filename for use when downloading data from a exportquiz report. It is
 * expected that this will be passed to flexible_table::is_downloading, which
 * cleans the filename of bad characters and adds the file extension.
 * @param string $report the type of report.
 * @param string $courseshortname the course shortname.
 * @param string $exportquizname the exportquiz name.
 * @return string the filename.
 */
function exportquiz_report_download_filename($report, $courseshortname, $exportquizname) {
    return $courseshortname . '-' . format_string($exportquizname, true) . '-' . $report;
}

/**
 * Get the default report for the current user.
 * @param object $context the exportquiz context.
 */
function exportquiz_report_default_report($context) {
    $reports = exportquiz_report_list($context);
    return reset($reports);
}

/**
 * Generate a message saying that this exportquiz has no questions, with a button to
 * go to the edit page, if the user has the right capability.
 * @param object $exportquiz the exportquiz settings.
 * @param object $cm the course_module object.
 * @param object $context the exportquiz context.
 * @return string HTML to output.
 */
function exportquiz_no_questions_message($exportquiz, $cm, $context) {
    global $OUTPUT;

    $output = '';
    $output .= $OUTPUT->notification(get_string('noquestions', 'exportquiz'));
    if (has_capability('mod/exportquiz:manage', $context)) {
        $output .= $OUTPUT->single_button(new moodle_url('/mod/exportquiz/edit.php',
        array('cmid' => $cm->id)), get_string('editexportquiz', 'exportquiz'), 'get');
    }

    return $output;
}
