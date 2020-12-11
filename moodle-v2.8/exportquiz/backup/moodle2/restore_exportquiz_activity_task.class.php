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
 * Define the restore_exportquiz_activity_task
 *
 * @package       mod
 * @subpackage    exportquiz
 * @author        Juergen Zimmer <zimmerj7@univie.ac.at>
 * @copyright     2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @since         Moodle 2.2+
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 **/


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/exportquiz/backup/moodle2/restore_exportquiz_stepslib.php');


/**
 * exportquiz restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 *
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_exportquiz_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // exportquiz only has one structure step.
        $this->add_step(new restore_exportquiz_activity_structure_step('exportquiz_structure', 'exportquiz.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    public static function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('exportquiz', array('intro'), 'exportquiz');
        $contents[] = new restore_decode_content('exportquiz', array('pdfintro'), 'exportquiz');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    public static function define_decode_rules() {
        $rules = array();

        $rules[] = new restore_decode_rule('EXPORTQUIZVIEWBYID',
                '/mod/exportquiz/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('EXPORTQUIZVIEWBYQ',
                '/mod/exportquiz/view.php?q=$1', 'exportquiz');
        $rules[] = new restore_decode_rule('EXPORTQUIZINDEX',
                '/mod/exportquiz/index.php?id=$1', 'course');

        return $rules;

    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * exportquiz logs. It must return one array
     * of {@link restore_log_rule} objects
     */
    public static function define_restore_log_rules() {
        $rules = array();

        $rules[] = new restore_log_rule('exportquiz', 'add',
                'view.php?id={course_module}', '{exportquiz}');
        $rules[] = new restore_log_rule('exportquiz', 'update',
                'view.php?id={course_module}', '{exportquiz}');
        $rules[] = new restore_log_rule('exportquiz', 'view',
                'view.php?id={course_module}', '{exportquiz}');
        $rules[] = new restore_log_rule('exportquiz', 'review',
                'review.php?id={course_module}&resultid={exportquiz_result_id}', '{exportquiz}');
        $rules[] = new restore_log_rule('exportquiz', 'report',
                'report.php?id={course_module}', '{exportquiz}');
        $rules[] = new restore_log_rule('exportquiz', 'editquestions',
                'view.php?id={course_module}', '{exportquiz}');
        $rules[] = new restore_log_rule('exportquiz', 'delete result',
                'report.php?id={course_module}', '{exportquiz}');
        $rules[] = new restore_log_rule('exportquiz', 'uncheck_participant',
                'participants.php?id={course_module}', '{exportquiz}');
        $rules[] = new restore_log_rule('exportquiz', 'check_participant',
                'participants.php?id={course_module}', '{exportquiz}');
        $rules[] = new restore_log_rule('exportquiz', 'addcategory',
                'view.php?id={course_module}', '{question_category}');
        $rules[] = new restore_log_rule('exportquiz', 'view summary',
                'summary.php?result={exportquiz_attempt_id}', '{exportquiz}');
        $rules[] = new restore_log_rule('exportquiz', 'manualgrade',
                'comment.php?resultid={exportquiz_attempt_id}&question={question}', '{exportquiz}');
        $rules[] = new restore_log_rule('exportquiz', 'manualgrading',
                'report.php?mode=grading&q={exportquiz}', '{exportquiz}');
        $rules[] = new restore_log_rule('exportquiz', 'preview',
                'view.php?id={course_module}', '{exportquiz}');

        // All the ones calling to review.php have two rules to handle both old and new urls
        // in any case they are always converted to new urls on restore.
        // TODO: In Moodle 2.x (x >= 5) kill the old rules.
        // Note we are using the 'exportquiz_attempt_id' mapping because that is the
        // one containing the exportquiz_attempt->ids old an new for exportquiz-attempt.
        $rules[] = new restore_log_rule('exportquiz', 'attempt',
                'review.php?id={course_module}&resultid={exportquiz_attempt}', '{exportquiz}',
                null, null, 'review.php?attempt={exportquiz_attempt}');
        // Old an new for exportquiz-submit.
        $rules[] = new restore_log_rule('exportquiz', 'submit',
                'review.php?id={course_module}&attempt={exportquiz_attempt_id}', '{exportquiz}',
                null, null, 'review.php?attempt={exportquiz_attempt_id}');
        $rules[] = new restore_log_rule('exportquiz', 'submit',
                'review.php?attempt={exportquiz_attempt_id}', '{exportquiz}');
        // Old an new for exportquiz-review.
        // Old an new for exportquiz-start attempt.
        $rules[] = new restore_log_rule('exportquiz', 'start attempt',
                'review.php?id={course_module}&attempt={exportquiz_attempt_id}', '{exportquiz}',
                null, null, 'review.php?attempt={exportquiz_attempt_id}');
        $rules[] = new restore_log_rule('exportquiz', 'start attempt',
                'review.php?attempt={exportquiz_attempt_id}', '{exportquiz}');
        // Old an new for exportquiz-close attempt.
        $rules[] = new restore_log_rule('exportquiz', 'close attempt',
                'review.php?id={course_module}&attempt={exportquiz_attempt_id}', '{exportquiz}',
                null, null, 'review.php?attempt={exportquiz_attempt_id}');
        $rules[] = new restore_log_rule('exportquiz', 'close attempt',
                'review.php?attempt={exportquiz_attempt_id}', '{exportquiz}');
        // Old an new for exportquiz-continue attempt.
        $rules[] = new restore_log_rule('exportquiz', 'continue attempt',
                'review.php?id={course_module}&attempt={exportquiz_attempt_id}', '{exportquiz}',
                null, null, 'review.php?attempt={exportquiz_attempt_id}');
        $rules[] = new restore_log_rule('exportquiz', 'continue attempt',
                'review.php?attempt={exportquiz_attempt_id}', '{exportquiz}');
        // Old an new for exportquiz-continue attempt.
        $rules[] = new restore_log_rule('exportquiz', 'continue attemp',
                'review.php?id={course_module}&attempt={exportquiz_attempt_id}', '{exportquiz}',
                null, 'continue attempt', 'review.php?attempt={exportquiz_attempt_id}');
        $rules[] = new restore_log_rule('exportquiz', 'continue attemp',
                'review.php?attempt={exportquiz_attempt_id}', '{exportquiz}',
                null, 'continue attempt');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * course logs. It must return one array
     * of {@link restore_log_rule} objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     */
    public static function define_restore_log_rules_for_course() {
        $rules = array();

        $rules[] = new restore_log_rule('exportquiz', 'view all', 'index.php?id={course}', null);

        return $rules;
    }
}
