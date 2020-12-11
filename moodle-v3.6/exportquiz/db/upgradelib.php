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
 * Code for upgrading Moodle 1.9.x exportquizzes to Moodle 3.0+
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

global $CFG;
require_once($CFG->dirroot . '/lib/modinfolib.php');
require_once($CFG->dirroot . '/mod/exportquiz/evallib.php');
require_once($CFG->dirroot . '/question/engine/bank.php');
require_once($CFG->dirroot . '/question/engine/upgrade/logger.php');
require_once($CFG->dirroot . '/question/engine/upgrade/behaviourconverters.php');
require_once($CFG->dirroot . '/question/engine/upgrade/upgradelib.php');


/**
 * This class manages upgrading all the question attempts from the old database
 * structure to the new question engine.
 *
 */

class exportquiz_ilog_upgrader {
    /** @var exportquiz_upgrade_question_loader */
    protected $questionloader;
    /** @var question_engine_assumption_logger */
    protected $logger;
    /** @var int used by {@link prevent_timeout()}. */
    protected $dotcounter = 0;
    /** @var progress_bar */
    protected $progressbar = null;
    /** @var boolean */
    protected $doingbackup = false;

    protected $contextid = 0;

    /**
     * Called before starting to upgrade all the attempts at a particular exportquiz.
     * @param int $done the number of exportquizzes processed so far.
     * @param int $outof the total number of exportquizzes to process.
     * @param int $exportquizid the id of the exportquiz that is about to be processed.
     */
    protected function print_progress($done, $outof, $exportquizid) {
        if (is_null($this->progressbar)) {
            $this->progressbar = new progress_bar('oq2ilogupgrade');
            $this->progressbar->create();
        }

        gc_collect_cycles(); // This was really helpful in PHP 5.2. Perhaps remove.
        $a = new stdClass();
        $a->done = $done;
        $a->outof = $outof;
        $a->info = $exportquizid;
        $this->progressbar->update($done, $outof, get_string('upgradingilogs', 'exportquiz', $a));
    }

    protected function prevent_timeout() {
        set_time_limit(300);
        if ($this->doingbackup) {
            return;
        }
        echo '.';
        $this->dotcounter += 1;
        if ($this->dotcounter % 100 == 0) {
            echo '<br /> ' . time() . "\n";
        }
    }

    public function convert_all_exportquiz_attempts() {
        global $DB;

        echo 'starting at ' . time() . "\n";

        $exportquizzes = $DB->get_records('exportquiz', array('needsilogupgrade' => 1));

        if (empty($exportquizzes)) {
            return true;
        }

        $done = 0;
        $outof = count($exportquizzes);

        foreach ($exportquizzes as $exportquiz) {
            $this->print_progress($done, $outof, $exportquiz->id);
            echo ' '. $exportquiz->id;
            $cm = get_coursemodule_from_instance("exportquiz", $exportquiz->id, $exportquiz->course);
            $context = context_module::instance($cm->id);

            $this->contextid = $context->id;
            $this->update_all_files($exportquiz);
            $this->update_all_group_template_usages($exportquiz);
            $this->update_all_results_and_logs($exportquiz);

            rebuild_course_cache($exportquiz->course);

            $done += 1;
        }

        $this->print_progress($outof, $outof, 'All done!');
        echo 'finished at ' . time() . "\n";
        return true;
    }

    public function update_all_files($exportquiz) {
        global $DB, $CFG;

        // First we migrate the image files from the original moodledata directory.
        $dirname = $CFG->dataroot . '/' . $exportquiz->course . '/moddata/exportquiz/' . $exportquiz->id;
        $filenames = get_directory_list($dirname, 'pdfs', false, false);
        $fs = get_file_storage();
        $filerecord = array(
                'contextid' => $this->contextid,      // ID of context.
                'component' => 'mod_exportquiz', // Usually = table name.
                'filearea'  => 'imagefiles',      // Usually = table name.
                'itemid'    => 0,                 // Usually = ID of row in table.
                'filepath'  => '/'                // Any path beginning and ending in.
        ); // Any filename.

        foreach ($filenames as $filename) {
            $filerecord['filename'] = $filename;
            $pathname = $dirname . '/' . $filename;
            if (!$fs->file_exists($this->contextid, 'mod_exportquiz', 'imagefiles', 0, '/', $filename)) {
                if ($newfile = $fs->create_file_from_pathname($filerecord, $pathname)) {
                    unlink($pathname);
                }
            }
        }

        // Now we migrate the PDF files.
        $dirname = $CFG->dataroot . '/' . $exportquiz->course . '/moddata/exportquiz/' . $exportquiz->id . '/pdfs';
        $filenames = get_directory_list($dirname, '', false, false);
        $fs = get_file_storage();
        $filerecord = array(
                'contextid' => $this->contextid,
                'component' => 'mod_exportquiz',
                'filearea'  => 'pdfs',
                'itemid'    => 0,
                'filepath'  => '/'
        ); // Any filename.

        foreach ($filenames as $filename) {
            $filerecord['filename'] = $filename;
            $pathname = $dirname . '/' . $filename;
            if ($newfile = $fs->create_file_from_pathname($filerecord, $pathname)) {
                unlink($pathname);
            }
        }
    }

    public function update_all_group_template_usages($exportquiz) {
        global $DB, $CFG;

        $groups = $DB->get_records('exportquiz_groups', array('exportquizid' => $exportquiz->id),
                                   'number', '*', 0, $exportquiz->numgroups);

        $transaction = $DB->start_delegated_transaction();
        foreach ($groups as $group) {
            if ($attempt = $DB->get_record('exportquiz_attempts', array('exportquiz' => $exportquiz->id,
                    'groupid' => $group->number, 'needsupgradetonewqe' => 0, 'sheet' => 1))) {

                    $DB->set_field('exportquiz_groups', 'templateusageid', $attempt->uniqueid,
                                   array('exportquizid' => $exportquiz->id,
                                         'number' => $attempt->groupid));
            }
        }
        $transaction->allow_commit();
        return true;
    }


    /**
     * Translates old status values to new status and error values of scanned pages
     *
     * @param unknown_type $olderror
     * @return multitype:string
     */
    public function get_status_and_error($olderror) {
        $status = 'ok';
        $error = '';
        switch($olderror) {
            case EXPORTQUIZ_IMPORT_LMS:
                $status = 'ok';
                $error = '';
                break;
            case EXPORTQUIZ_IMPORT_OK:
                $status = 'ok';
                $error = '';
                break;
            case EXPORTQUIZ_IMPORT_CORRECTED:
                $status = 'ok';
                $error = '';
                break;
            case EXPORTQUIZ_IMPORT_DOUBLE:
                $status = 'ok';
                $error = '';
                break;
            case EXPORTQUIZ_IMPORT_ITEM_ERROR:
                $status = 'ok';
                $error = '';
                break;
            case EXPORTQUIZ_IMPORT_DOUBLE_ERROR:
                $status = 'error';
                $error = 'resultexists';
                break;
            case EXPORTQUIZ_IMPORT_USER_ERROR:
                $status = 'error';
                $error = 'nonexistinguser';
                break;
            case EXPORTQUIZ_IMPORT_GROUP_ERROR:
                $status = 'error';
                $error = 'grouperror';
                break;
            case EXPORTQUIZ_IMPORT_FATAL_ERROR:
                $status = 'error';
                $error = 'notadjusted';
                break;
            case EXPORTQUIZ_IMPORT_INSECURE_ERROR:
                $status = 'error';
                $error = 'insecuremarkings';
                break;
            case EXPORTQUIZ_IMPORT_PAGE_ERROR:
                $status = 'error';
                $error = 'pageerror';
                break;
            case EXPORTQUIZ_IMPORT_SINGLE_ERROR:
                $status = 'submitted';
                $error = 'missingpages';
                break;
            case EXPORTQUIZ_IMPORT_DOUBLE_PAGE_ERROR:
                $status = 'error';
                $error = 'doublepage';
                break;
            case EXPORTQUIZ_IMPORT_DIFFERING_PAGE_ERROR:
                $status = 'error';
                $error = 'differentpage';
                break;
            default:
                $status = 'error';
                $error = 'unknown';
                break;
        }
        return array($status, $error);
    }


    /**
     * retrieve the image name from the rawdata
     *
     */
    public function get_pic_name($rawdata) {
        $dataarray = explode(",", $rawdata);
        $last = array_pop($dataarray);
        if (preg_match('/(gif|jpg|jpeg|png|tif|tiff)$/i', $last)) {
            return $last;
        } else {
            return '';
        }
    }

    public function get_user_name($rawdata) {
        $dataarray = explode (",", $rawdata);
        return array_shift($dataarray);
    }

    public function get_group($rawdata) {
        $dataarray = explode (",", $rawdata);
        array_shift($dataarray);
        return array_shift($dataarray);
    }

    public function get_item_data($rawdata) {
        $dataarray = explode (",", $rawdata);
        $pos = count($dataarray) - 1;
        if (preg_match('/(gif|jpg|jpeg|png|tif|tiff)$/i', $dataarray[$pos])) {
            array_pop($dataarray);
        }
        array_shift($dataarray);
        array_shift($dataarray);
        $retwert = implode(",", $dataarray);
        return $retwert;
    }

}

/**
 * This class manages upgrading all the question attempts from the old database
 * structure to the new question engine.
 *
 */
class exportquiz_attempt_upgrader extends question_engine_attempt_upgrader {
    /** @var exportquiz_upgrade_question_loader */
    protected $questionloader;
    /** @var question_engine_assumption_logger */
    protected $logger;
    /** @var int used by {@link prevent_timeout()}. */
    protected $dotcounter = 0;
    /** @var progress_bar */
    protected $progressbar = null;
    /** @var boolean */
    protected $doingbackup = false;

    /**
     * Called before starting to upgrade all the attempts at a particular exportquiz.
     * @param int $done the number of exportquizzes processed so far.
     * @param int $outof the total number of exportquizzes to process.
     * @param int $exportquizid the id of the exportquiz that is about to be processed.
     */
    protected function print_progress($done, $outof, $exportquizid) {
        if (is_null($this->progressbar)) {
            $this->progressbar = new progress_bar('oq2upgrade');
            $this->progressbar->create();
        }

        gc_collect_cycles(); // This was really helpful in PHP 5.2. Perhaps remove.
        $a = new stdClass();
        $a->done = $done;
        $a->outof = $outof;
        $a->info = $exportquizid;
        $this->progressbar->update($done, $outof, get_string('upgradingexportquizattempts', 'exportquiz', $a));
    }

    protected function get_quiz_ids() {
        global $CFG, $DB;

        // Look to see if the admin has set things up to only upgrade certain attempts.
        $partialupgradefile = $CFG->dirroot . '/' . $CFG->admin .
        '/tool/qeupgradehelper/partialupgrade.php';
        $partialupgradefunction = 'tool_qeupgradehelper_get_quizzes_to_upgrade';
        if (is_readable($partialupgradefile)) {
            include_once($partialupgradefile);
            if (function_exists($partialupgradefunction)) {
                $quizids = $partialupgradefunction();

                // Ignore any quiz ids that do not acually exist.
                if (empty($quizids)) {
                    return array();
                }
                list($test, $params) = $DB->get_in_or_equal($quizids);
                return $DB->get_fieldset_sql("
                        SELECT id
                        FROM {exportquiz}
                        WHERE id $test
                        ORDER BY id", $params);
            }
        }

        // Otherwise, upgrade all attempts.
        return $DB->get_fieldset_sql('SELECT id FROM {exportquiz} ORDER BY id');
    }

    public function convert_all_quiz_attempts() {
        global $DB;

        echo 'starting at ' . time() . "\n";
        $quizids = $this->get_quiz_ids();
        if (empty($quizids)) {
            return true;
        }

        $done = 0;
        $outof = count($quizids);
        $this->logger = new question_engine_assumption_logger();

        foreach ($quizids as $quizid) {
            $this->print_progress($done, $outof, $quizid);

            $quiz = $DB->get_record('exportquiz', array('id' => $quizid), '*', MUST_EXIST);
            $this->update_all_attempts_at_quiz($quiz);
            rebuild_course_cache($quiz->course);

            $done += 1;
        }

        $this->print_progress($outof, $outof, 'All done!');
        $this->logger = null;
        echo 'finshed at ' . time() . "\n";
    }

    public function get_attempts_extra_where() {
        return ' AND needsupgradetonewqe = 1';
    }

    public function update_all_attempts_at_quiz($quiz) {
        global $DB;

        // Wipe question loader cache.
        $this->questionloader = new exportquiz_upgrade_question_loader($this->logger);

        $params = array('exportquizid' => $quiz->id);

        // Actually we want all the attempts, also the ones with sheet = 1 for the group template usages.
        $where = 'exportquiz = :exportquizid ' . $this->get_attempts_extra_where();

        $quizattemptsrs = $DB->get_recordset_select('exportquiz_attempts', $where, $params, 'uniqueid');

        $questionsessionsrs = $DB->get_recordset_sql("
                SELECT s.*
                FROM {question_sessions} s
                JOIN {exportquiz_attempts} a ON (s.attemptid = a.uniqueid)
                WHERE $where
                ORDER BY attemptid, questionid
                ", $params);

        $questionsstatesrs = $DB->get_recordset_sql("
                SELECT s.*
                FROM {question_states} s
                JOIN {exportquiz_attempts} a ON (s.attempt = a.uniqueid)
                WHERE $where
                ORDER BY s.attempt, question, seq_number, s.id
                ", $params);

        $datatodo = $quizattemptsrs && $questionsessionsrs && $questionsstatesrs;

        while ($datatodo && $quizattemptsrs->valid()) {
            $attempt = $quizattemptsrs->current();
            $quizattemptsrs->next();

            $transaction = $DB->start_delegated_transaction();
            $this->convert_quiz_attempt($quiz, $attempt, $questionsessionsrs, $questionsstatesrs);
            $transaction->allow_commit();
        }

        $quizattemptsrs->close();
        $questionsessionsrs->close();
        $questionsstatesrs->close();

    }

    protected function convert_quiz_attempt($quiz, $attempt, moodle_recordset $questionsessionsrs,
            moodle_recordset $questionsstatesrs) {
        global $OUTPUT, $DB;

        $qas = array();
        $this->logger->set_current_attempt_id($attempt->id);
        while ($qsession = $this->get_next_question_session($attempt, $questionsessionsrs)) {
            $question = $this->load_question($qsession->questionid, $quiz->id);

            $qstates = $this->get_question_states($attempt, $question, $questionsstatesrs);
            try {
                $qas[$qsession->questionid] = $this->convert_question_attempt(
                        $quiz, $attempt, $question, $qsession, $qstates);
            } catch (Exception $e) {
                echo $OUTPUT->notification($e->getMessage());
            }
        }
        $this->logger->set_current_attempt_id(null);
        $questionorder = array();

        // For exportquizzes we have to take the questionlist from the export group or the attempt.
        $layout = $attempt->layout;
        $groupquestions = explode(',', $layout);

        foreach ($groupquestions as $questionid) {
            if ($questionid == 0) {
                continue;
            }
            if (!array_key_exists($questionid, $qas)) {
                $this->logger->log_assumption("Supplying minimal open state for
                        question {$questionid} in attempt {$attempt->id} at quiz
                        {$attempt->exportquiz}, since the session was missing.", $attempt->id);
                try {
                    $question = $this->load_question($questionid, $quiz->id);
                    $qas[$questionid] = $this->supply_missing_question_attempt(
                            $quiz, $attempt, $question);
                } catch (Exception $e) {
                    echo $OUTPUT->notification($e->getMessage());
                }
            }
        }
        return $this->save_usage('deferredfeedback', $attempt, $qas, $layout);
    }

    public function save_usage($preferredbehaviour, $attempt, $qas, $quizlayout) {
        global $DB, $OUTPUT;
        $missing = array();

        $layout = explode(',', $attempt->layout);
        $questionkeys = array_combine(array_values($layout), array_keys($layout));

        $this->set_quba_preferred_behaviour($attempt->uniqueid, $preferredbehaviour);

        $i = 0;

        foreach (explode(',', $quizlayout) as $questionid) {
            if ($questionid == 0) {
                continue;
            }
            $i++;

            if (!array_key_exists($questionid, $qas)) {
                $missing[] = $questionid;
                $layout[$questionkeys[$questionid]] = $questionid;
                continue;
            }

            $qa = $qas[$questionid];
            $qa->questionusageid = $attempt->uniqueid;
            $qa->slot = $i;
            if (textlib::strlen($qa->questionsummary) > question_bank::MAX_SUMMARY_LENGTH) {
                // It seems some people write very long quesions! MDL-30760.
                $qa->questionsummary = textlib::substr($qa->questionsummary,
                        0, question_bank::MAX_SUMMARY_LENGTH - 3) . '...';
            }
            $this->insert_record('question_attempts', $qa);
            $layout[$questionkeys[$questionid]] = $qa->slot;

            foreach ($qa->steps as $step) {
                $step->questionattemptid = $qa->id;
                $this->insert_record('question_attempt_steps', $step);

                foreach ($step->data as $name => $value) {
                    $datum = new stdClass();
                    $datum->attemptstepid = $step->id;
                    $datum->name = $name;
                    $datum->value = $value;
                    $this->insert_record('question_attempt_step_data', $datum, false);
                }
            }
        }

        $this->set_quiz_attempt_layout($attempt->uniqueid, implode(',', $layout));

        if ($missing) {
            $OUTPUT->notification("Question sessions for questions " .
                    implode(', ', $missing) .
                    " were missing when upgrading question usage {$attempt->uniqueid}.");
        }
    }


    protected function set_quiz_attempt_layout($qubaid, $layout) {
        global $DB;
        $DB->set_field('exportquiz_attempts', 'needsupgradetonewqe', 0, array('uniqueid' => $qubaid));
    }

    protected function delete_quiz_attempt($qubaid) {
        global $DB;
        $DB->delete_records('exportquiz_attempts', array('uniqueid' => $qubaid));
        $DB->delete_records('question_attempts', array('id' => $qubaid));
    }


    protected function get_converter_class_name($question, $quiz, $qsessionid) {
        global $DB;
        if ($question->qtype == 'deleted') {
            $where = '(question = :questionid OR ' . $DB->sql_like('answer', ':randomid') . ') AND event = 7';
            $params = array('questionid' => $question->id, 'randomid' => "random{$question->id}-%");
            if ($DB->record_exists_select('question_states', $where, $params)) {
                $this->logger->log_assumption("Assuming that deleted question {$question->id} was manually graded.");
                return 'qbehaviour_manualgraded_converter';
            }
        } else if ($question->qtype == 'description') {
            return 'qbehaviour_informationitem_converter';
        } else {
            return 'qbehaviour_deferredfeedback_converter';
        }
    }

    public function supply_missing_question_attempt($quiz, $attempt, $question) {
        if ($question->qtype == 'random') {
            throw new coding_exception("Cannot supply a missing qsession for question
            {$question->id} in attempt {$attempt->id}.");
        }

        $converterclass = $this->get_converter_class_name($question, $quiz, 'missing');

        $qbehaviourupdater = new $converterclass($quiz, $attempt, $question,
                null, null, $this->logger, $this);
        $qa = $qbehaviourupdater->supply_missing_qa();
        $qbehaviourupdater->discard();
        return $qa;
    }

    protected function prevent_timeout() {
        set_time_limit(300);
        if ($this->doingbackup) {
            return;
        }
        echo '.';
        $this->dotcounter += 1;
        if ($this->dotcounter % 100 == 0) {
            echo '<br />' . "\n";
        }
    }

    public function convert_question_attempt($quiz, $attempt, $question, $qsession, $qstates) {
        $this->prevent_timeout();
        $quiz->attemptonlast = false;
        $converterclass = $this->get_converter_class_name($question, $quiz, $qsession->id);

        $qbehaviourupdater = new $converterclass($quiz, $attempt, $question, $qsession,
                $qstates, $this->logger, $this);
        $qa = $qbehaviourupdater->get_converted_qa();
        $qbehaviourupdater->discard();
        return $qa;
    }

    protected function decode_random_attempt($qstates, $maxmark) {
        $realquestionid = null;
        foreach ($qstates as $i => $state) {
            if (strpos($state->answer, '-') < 6) {
                // Broken state, skip it.
                $this->logger->log_assumption("Had to skip brokes state {$state->id}
                for question {$state->question}.");
                unset($qstates[$i]);
                continue;
            }
            list($randombit, $realanswer) = explode('-', $state->answer, 2);
            $newquestionid = substr($randombit, 6);
            if ($realquestionid && $realquestionid != $newquestionid) {
                throw new coding_exception("Question session {$this->qsession->id}
                for random question points to two different real questions
                {$realquestionid} and {$newquestionid}.");
            }
            $qstates[$i]->answer = $realanswer;
        }

        if (empty($newquestionid)) {
            // This attempt only had broken states. Set a fake $newquestionid to
            // prevent a null DB error later.
            $newquestionid = 0;
        }

        $newquestion = $this->load_question($newquestionid);
        $newquestion->maxmark = $maxmark;
        return array($newquestion, $qstates);
    }

    public function prepare_to_restore() {
        $this->doingbackup = true; // Prevent printing of dots to stop timeout on upgrade.
        $this->logger = new dummy_question_engine_assumption_logger();
        $this->questionloader = new exportquiz_upgrade_question_loader($this->logger);
    }
}


/**
 * This class deals with loading (and caching) question definitions during the
 * exportquiz upgrade.
 *
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exportquiz_upgrade_question_loader extends question_engine_upgrade_question_loader {

    protected function load_question($questionid, $exportquizid) {
        global $DB;

        if ($exportquizid) {
            $question = $DB->get_record_sql("
                    SELECT q.*, qqi.grade AS maxmark
                    FROM {question} q
                    JOIN {exportquiz_q_instances} qqi ON qqi.question = q.id
                    WHERE q.id = $questionid AND qqi.exportquiz = $exportquizid");
        } else {
            $question = $DB->get_record('question', array('id' => $questionid));
        }

        if (!$question) {
            return null;
        }

        if (empty($question->defaultmark)) {
            if (!empty($question->defaultgrade)) {
                $question->defaultmark = $question->defaultgrade;
            } else {
                $question->defaultmark = 0;
            }
            unset($question->defaultgrade);
        }

        $qtype = question_bank::get_qtype($question->qtype, false);
        if ($qtype->name() === 'missingtype') {
            $this->logger->log_assumption("Dealing with question id {$question->id}
            that is of an unknown type {$question->qtype}.");
            $question->questiontext = '<p>' . get_string('warningmissingtype', 'exportquiz') .
            '</p>' . $question->questiontext;
        }

        $qtype->get_question_options($question);

        return $question;
    }

}

/**
 * Removes all 'double' entries in the exportquiz question instances
 * table. In Moodle 1.9 each group could have their own question
 * instances.  Now we store only one entry per question.
 *
 */
function exportquiz_remove_redundant_q_instances() {
    global $DB;

    $exportquizzes = $DB->get_records('exportquiz', array(), 'id', 'id');
    foreach ($exportquizzes as $exportquiz) {
        $transaction = $DB->start_delegated_transaction();

        $qinstances = $DB->get_records('exportquiz_q_instances', array('exportquiz' => $exportquiz->id), 'groupid');
        // First delete them all.
        $DB->delete_records('exportquiz_q_instances', array('exportquiz' => $exportquiz->id));

        // Now insert one per question.
        foreach ($qinstances as $qinstance) {
            if (!$DB->get_record('exportquiz_q_instances', array('exportquiz' => $qinstance->exportquiz,
                                                                  'question' => $qinstance->question))) {
                $qinstance->groupid = 0;
                $DB->insert_record('exportquiz_q_instances', $qinstance);
            }
        }
        $transaction->allow_commit();
    }
}
