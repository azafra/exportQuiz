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
 * Standard plugin entry points of the exportquiz statistics report.
 *
 * @package       mod
 * @subpackage    exportquiz
 * @author        Jose Manuel Ventura Martínez
 * @copyright     2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @since         Moodle 2.5
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Serve questiontext files in the question text when they are displayed in this report.
 *
 * @package  mod_exportquiz
 * @category files
 * @param stdClass $context the context
 * @param int $questionid the question id
 * @param array $args remaining file args
 * @param bool $forcedownload
 * @param array $options additional options affecting the file serving
 */
function exportquiz_statistics_questiontext_preview_pluginfile($context, $questionid, $args, $forcedownload,
                                                                array $options = array()) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/exportquiz/locallib.php');

    list($context, $course, $cm) = get_context_info_array($context->id);
    require_login($course, false, $cm);

    // Assume only trusted people can see this report. There is no real way to
    // validate questionid, becuase of the complexity of random quetsions.
    require_capability('exportquiz/statistics:view', $context);

    question_send_questiontext_file($questionid, $args, $forcedownload, $options);
}

/**
 * exportquiz statistics report cron code. Deletes cached data more than a certain age.
 */
function exportquiz_statistics_cron() {
    global $DB;

    $expiretime = time() - 5 * HOURSECS;
    $todelete = $DB->get_records_select_menu('exportquiz_statistics',
            'timemodified < ?', array($expiretime), '', 'id, 1');

    if (!$todelete) {
        return true;
    }

    list($todeletesql, $todeleteparams) = $DB->get_in_or_equal(array_keys($todelete));

    $DB->delete_records_select('exportquiz_q_statistics',
            'exportquizstatisticsid ' . $todeletesql, $todeleteparams);

    $DB->delete_records_select('exportquiz_q_response_stats',
            'exportquizstatisticsid ' . $todeletesql, $todeleteparams);

    $DB->delete_records_select('exportquiz_statistics',
            'id ' . $todeletesql, $todeleteparams);

    return true;
}
