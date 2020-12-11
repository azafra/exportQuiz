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
 * Define the steps used by the restore_exportquiz_activity_task
 *
 * @package       mod
 * @subpackage    exportquiz
 * @author        Manuel Tejero Martín
 * @copyright     2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @since         Moodle 2.2+
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 **/

defined('MOODLE_INTERNAL') || die();

/**
 * Structure step to restore one exportquiz activity
 *
 * @copyright     2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_exportquiz_activity_structure_step extends restore_questions_activity_structure_step {

    private $currentexportquizresult = null;
    private $currentexportgroup = null;

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $exportquiz = new restore_path_element('exportquiz', '/activity/exportquiz');
        $paths[] = $exportquiz;

        // Scanned pages and their choices and corners.
        $paths[] = new restore_path_element('exportquiz_scannedpage', '/activity/exportquiz/scannedpages/scannedpage');
        $paths[] = new restore_path_element('exportquiz_choice', '/activity/exportquiz/scannedpages/scannedpage/choices/choice');
        $paths[] = new restore_path_element('exportquiz_corner', '/activity/exportquiz/scannedpages/scannedpage/corners/corner');

        // Lists of participants and their scanned pages.
        $paths[] = new restore_path_element('exportquiz_plist',
                 '/activity/exportquiz/plists/plist');
        $paths[] = new restore_path_element('exportquiz_participant',
                 '/activity/exportquiz/plists/plist/participants/participant');
        $paths[] = new restore_path_element('exportquiz_scannedppage',
                 '/activity/exportquiz/scannedppages/scannedppage');
        $paths[] = new restore_path_element('exportquiz_pchoice',
                 '/activity/exportquiz/scannedppages/scannedppage/pchoices/pchoice');

        // Handle exportquiz groups.
        // We need to identify this path to add the question usages.
        $exportquizgroup = new restore_path_element('exportquiz_group',
                '/activity/exportquiz/groups/group');
        $paths[] = $exportquizgroup;

        // Add template question usages for export groups.
        $this->add_question_usages($exportquizgroup, $paths, 'group_');

        $paths[] = new restore_path_element('exportquiz_groupquestion',
                 '/activity/exportquiz/groups/group/groupquestions/groupquestion');

        // We only add the results if userinfo was activated.
        if ($userinfo) {
            $exportquizresult = new restore_path_element('exportquiz_result',
                    '/activity/exportquiz/results/result');
            $paths[] = $exportquizresult;

            // Add the results' question usages.
            $this->add_question_usages($exportquizresult, $paths, 'result_');
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    // Dummy methods for group question usages.
    public function process_group_question_usage($data) {
        $this->restore_question_usage_worker($data, 'group_');
    }

    public function process_group_question_attempt($data) {
        $this->restore_question_attempt_worker($data, 'group_');
    }

    public function process_group_question_attempt_step($data) {
        $this->restore_question_attempt_step_worker($data, 'group_');
    }

    public function process_result_question_usage($data) {
        $this->restore_question_usage_worker($data, 'result_');
    }

    public function process_result_question_attempt($data) {
        $this->restore_question_attempt_worker($data, 'result_');
    }

    public function process_result_question_attempt_step($data) {
        $this->restore_question_attempt_step_worker($data, 'result_');
    }

    // Restore method for the activity.
    protected function process_exportquiz($data) {
        global $CFG, $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timeopen = $this->apply_date_offset($data->timeopen);
        $data->timeclose = $this->apply_date_offset($data->timeclose);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->questions = '';

        // The exportquiz->results can come both in data->results and
        // data->results_number, handle both. MDL-26229.
        if (isset($data->results_number)) {
            $data->results = $data->results_number;
            unset($data->results_number);
        }

        // Insert the exportquiz record.
        $newitemid = $DB->insert_record('exportquiz', $data);
        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    // Restore method for export groups.
    protected function process_exportquiz_group($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->exportquizid = $this->get_new_parentid('exportquiz');

        if (empty($data->templateusageid)) {

            $newitemid = $DB->insert_record('exportquiz_groups', $data);
            // Save exportquiz_group->id mapping, because logs use it.
            $this->set_mapping('exportquiz_group', $oldid, $newitemid, false);
        } else {
            // The data is actually inserted into the database later in inform_new_usage_id.
            $this->currentexportgroup = clone($data);
        }
    }


    // Restore method for exportquiz group questions.
    protected function process_exportquiz_groupquestion($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        // Backward compatibility for old field names prior to Moodle 2.8.5.
        if (isset($data->usageslot) && !isset($data->slot)) {
            $data->slot = $data->usageslot;
        }
        if (isset($data->pagenumber) && !isset($data->page)) {
            $data->page = $data->pagenumber;
        }

        $data->exportquizid = $this->get_new_parentid('exportquiz');
        $data->exportgroupid = $this->get_new_parentid('exportquiz_group');
        $data->questionid = $this->get_mappingid('question', $data->questionid);

        $newitemid = $DB->insert_record('exportquiz_group_questions', $data);
    }

    // Restore method for scanned pages.
    protected function process_exportquiz_scannedpage($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->exportquizid = $this->get_new_parentid('exportquiz');
        $data->resultid = $this->get_mappingid('exportquiz_result', $data->resultid);

        $newitemid = $DB->insert_record('exportquiz_scanned_pages', $data);
        $this->set_mapping('exportquiz_scannedpage', $oldid, $newitemid, true);
    }

    // Restore method for choices on scanned pages.
    protected function process_exportquiz_choice($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->scannedpageid = $this->get_new_parentid('exportquiz_scannedpage');

        $newitemid = $DB->insert_record('exportquiz_choices', $data);
    }

    // Restore method for corners of scanned pages.
    protected function process_exportquiz_corner($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->scannedpageid = $this->get_new_parentid('exportquiz_scannedpage');

        $newitemid = $DB->insert_record('exportquiz_page_corners', $data);
    }

    // Restore method for scanned participants pages.
    protected function process_exportquiz_scannedppage($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->exportquizid = $this->get_new_parentid('exportquiz');

        $newitemid = $DB->insert_record('exportquiz_scanned_p_pages', $data);
        $this->set_mapping('exportquiz_scannedppage', $oldid, $newitemid, true);
    }

    // Restore method for choices on scanned participants pages.
    protected function process_exportquiz_pchoice($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->scannedppageid = $this->get_new_parentid('exportquiz_scannedppage');

        $newitemid = $DB->insert_record('exportquiz_p_choices', $data);
    }

    // Restore method for lists of participants.
    protected function process_exportquiz_plist($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->exportquizid = $this->get_new_parentid('exportquiz');

        $newitemid = $DB->insert_record('exportquiz_p_lists', $data);
        $this->set_mapping('exportquiz_plist', $oldid, $newitemid, true);
    }

    // Restore method for a participant.
    protected function process_exportquiz_participant($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->listid = $this->get_new_parentid('exportquiz_plist');

        $newitemid = $DB->insert_record('exportquiz_participants', $data);
    }

    // Restore method for exportquiz results (attempts).
    protected function process_exportquiz_result($data) {
        global $DB;

        $data = (object) $data;

        $data->exportquizid = $this->get_new_parentid('exportquiz');

        $data->exportgroupid = $this->get_mappingid('exportquiz_group', $data->exportgroupid);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->teacherid = $this->get_mappingid('user', $data->teacherid);
        // The usageid is set in the function below.

        $data->timestart = $this->apply_date_offset($data->timestart);
        $data->timefinish = $this->apply_date_offset($data->timefinish);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // The data is actually inserted into the database later in inform_new_usage_id.
        $this->currentexportquizresult = clone($data);
    }

    // Restore the usage id after it has been created.
    protected function inform_new_usage_id($newusageid) {
        global $DB;

        // We might be dealing with a result.
        $data = $this->currentexportquizresult;
        if ($data) {
            $this->currentexportquizresult = null;
            $oldid = $data->id;
            $data->usageid = $newusageid;

            $newitemid = $DB->insert_record('exportquiz_results', $data);

            // Save exportquiz_result->id mapping, because scanned pages use it.
            $this->set_mapping('exportquiz_result', $oldid, $newitemid, false);
        } else {
            // Or we might be dealing with an exportquiz group.
            $data = $this->currentexportgroup;
            if ($data) {
                $this->currentexportgroup = null;
                $oldid = $data->id;
                $data->templateusageid = $newusageid;

                $newitemid = $DB->insert_record('exportquiz_groups', $data);

                // Save exportquiz_group->id mapping, because exportquiz_results use it.
                $this->set_mapping('exportquiz_group', $oldid, $newitemid, false);
            }
        }
    }

    protected function after_execute() {
        parent::after_execute();
        // Add exportquiz related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_exportquiz', 'intro', null);
        $this->add_related_files('mod_exportquiz', 'imagefiles', null);
        $this->add_related_files('mod_exportquiz', 'pdfs', null);
    }
}
