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



defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/question/engine/questionusage.php');

// These are the old error codes from the Moodle 1.9 module. We still need them for migration.
define("EXPORTQUIZ_IMPORT_LMS", "1");
define("EXPORTQUIZ_IMPORT_OK", "0");
define("EXPORTQUIZ_IMPORT_CORRECTED", "1");
define("EXPORTQUIZ_IMPORT_DOUBLE", "2");
define("EXPORTQUIZ_IMPORT_ITEM_ERROR", "3");
define("EXPORTQUIZ_IMPORT_DOUBLE_ERROR", "11");
define("EXPORTQUIZ_IMPORT_USER_ERROR", "12");
define("EXPORTQUIZ_IMPORT_GROUP_ERROR", "13");
define("EXPORTQUIZ_IMPORT_FATAL_ERROR", "14");
define("EXPORTQUIZ_IMPORT_INSECURE_ERROR", "15");
define("EXPORTQUIZ_IMPORT_PAGE_ERROR", "16");
define("EXPORTQUIZ_IMPORT_SINGLE_ERROR", "17"); // This is not really an error.
// It occures, when multipage answer sheets are scanned.
define("EXPORTQUIZ_IMPORT_DOUBLE_PAGE_ERROR", "18"); // New error for double pages (e.g. page 2 occurs twice for as student).
define("EXPORTQUIZ_IMPORT_DIFFERING_PAGE_ERROR", "19"); // New error for double pages that have different results (rawdata).

// Codes for lists of participants.
define("EXPORTQUIZ_PART_FATAL_ERROR", "21");   // Over 20 indicates, it is a participants error.
define("EXPORTQUIZ_PART_INSECURE_ERROR", "22");
define("EXPORTQUIZ_PART_USER_ERROR", "23");
define("EXPORTQUIZ_PART_LIST_ERROR", "24");
define("EXPORTQUIZ_IMPORT_NUMUSERS", "50");

define('EXPORTQUIZ_GROUP_LETTERS', "ABCDEFGHIJKL");  // Letters for naming exportquiz groups.

define('EXPORTQUIZ_PDF_FORMAT', 0);   // PDF file format for question sheets.
define('EXPORTQUIZ_DOCX_FORMAT', 1);  // DOCX file format for question sheets.
define('EXPORTQUIZ_ODT_FORMAT', 2);	  // ODT file format for question sheets

define('NUMBERS_PER_PAGE', 30);        // Number of students on participants list.
define('OQ_IMAGE_WIDTH', 860);         // Width of correction form.

class exportquiz_question_usage_by_activity extends question_usage_by_activity {

    public function get_clone($qinstances) {
        // The new quba doesn't have to be cloned, so we can use the parent class.
        $newquba = question_engine::make_questions_usage_by_activity($this->owningcomponent, $this->context);
        $newquba->set_preferred_behaviour('immediatefeedback');

        foreach ($this->get_slots() as $slot) {
            $slotquestion = $this->get_question($slot);
            $attempt = $this->get_question_attempt($slot);

            // We have to check for the type because we might have old migrated templates
            // that could contain description questions.
            if ($slotquestion->get_type_name() == 'multichoice' || $slotquestion->get_type_name() == 'multichoiceset') {
                $order = $slotquestion->get_order($attempt);  // Order of the answers.
                $order = implode(',', $order);
                $newslot = $newquba->add_question($slotquestion, $qinstances[$slotquestion->id]->maxmark);
                $qa = $newquba->get_question_attempt($newslot);
                $qa->start('immediatefeedback', 1, array('_order' => $order));
            }
        }
        question_engine::save_questions_usage_by_activity($newquba);
        return $newquba;
    }

    /**
     * Create a question_usage_by_activity from records loaded from the database.
     *
     * For internal use only.
     *
     * @param Iterator $records Raw records loaded from the database.
     * @param int $questionattemptid The id of the question_attempt to extract.
     * @return question_usage_by_activity The newly constructed usage.
     */
    public static function load_from_records($records, $qubaid) {
        $record = $records->current();
        while ($record->qubaid != $qubaid) {
            $records->next();
            if (!$records->valid()) {
                throw new coding_exception("Question usage $qubaid not found in the database.");
            }
            $record = $records->current();
        }

        $quba = new exportquiz_question_usage_by_activity($record->component,
                context::instance_by_id($record->contextid));
        $quba->set_id_from_database($record->qubaid);
        $quba->set_preferred_behaviour($record->preferredbehaviour);

        $quba->observer = new question_engine_unit_of_work($quba);

        while ($record && $record->qubaid == $qubaid && !is_null($record->slot)) {
            $quba->questionattempts[$record->slot] =
            question_attempt::load_from_records($records,
                    $record->questionattemptid, $quba->observer,
                    $quba->get_preferred_behaviour());
            if ($records->valid()) {
                $record = $records->current();
            } else {
                $record = false;
            }
        }

        return $quba;
    }
}

function exportquiz_make_questions_usage_by_activity($component, $context) {
    return new exportquiz_question_usage_by_activity($component, $context);
}

/**
 * Load a {@link question_usage_by_activity} from the database, including
 * all its {@link question_attempt}s and all their steps.
 * @param int $qubaid the id of the usage to load.
 * @param question_usage_by_activity the usage that was loaded.
 */
function exportquiz_load_questions_usage_by_activity($qubaid) {
    global $DB;

    $records = $DB->get_recordset_sql("
            SELECT quba.id AS qubaid,
                   quba.contextid,
                   quba.component,
                   quba.preferredbehaviour,
                   qa.id AS questionattemptid,
                   qa.questionusageid,
                   qa.slot,
                   qa.behaviour,
                   qa.questionid,
                   qa.variant,
                   qa.maxmark,
                   qa.minfraction,
                   qa.maxfraction,
                   qa.flagged,
                   qa.questionsummary,
                   qa.rightanswer,
                   qa.responsesummary,
                   qa.timemodified,
                   qas.id AS attemptstepid,
                   qas.sequencenumber,
                   qas.state,
                   qas.fraction,
                   qas.timecreated,
                   qas.userid,
                   qasd.name,
                   qasd.value
              FROM {question_usages}            quba
         LEFT JOIN {question_attempts}          qa   ON qa.questionusageid    = quba.id
         LEFT JOIN {question_attempt_steps}     qas  ON qas.questionattemptid = qa.id
         LEFT JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid    = qas.id
            WHERE  quba.id = :qubaid
          ORDER BY qa.slot,
                   qas.sequencenumber
            ", array('qubaid' => $qubaid));

    if (!$records->valid()) {
        throw new coding_exception('Failed to load questions_usage_by_activity ' . $qubaid);
    }

    $quba = exportquiz_question_usage_by_activity::load_from_records($records, $qubaid);
    $records->close();

    return $quba;
}

/**
 *
 * @param int $exportquiz
 * @param int $groupid
 * @return string
 */
function exportquiz_get_group_question_ids($exportquiz, $groupid = 0) {
    global $DB;

    if (!$groupid) {
        $groupid = $exportquiz->groupid;
    }

    // This query only makes sense if it is restricted to a export group.
    if (!$groupid) {
        return '';
    }

    $sql = "SELECT questionid
              FROM {exportquiz_group_questions}
             WHERE exportquizid = :exportquizid
               AND exportgroupid = :exportgroupid
          ORDER BY slot ASC ";
    
    $params = array('exportquizid' => $exportquiz->id, 'exportgroupid' => $groupid);
    $questionids = $DB->get_fieldset_sql($sql, $params);

    return $questionids;
}


/**
 *
 * @param mixed $exportquiz The exportquiz
 * @return array returns an array of export group numbers
 */
function exportquiz_get_empty_groups($exportquiz) {
    global $DB;

    $emptygroups = array();

    if ($groups = $DB->get_records('exportquiz_groups',
                                   array('exportquizid' => $exportquiz->id), 'number', '*', 0, $exportquiz->numgroups)) {
        foreach ($groups as $group) {
            $questions = exportquiz_get_group_question_ids($exportquiz, $group->id);
            if (count($questions) < 1) {
                $emptygroups[] = $group->number;
            }
        }
    }
    return $emptygroups;
}


/**
 * Get the slot for a question with a particular id.
 * @param object $exportquiz the exportquiz settings.
 * @param int $questionid the of a question in the exportquiz.
 * @return int the corresponding slot. Null if the question is not in the exportquiz.
 */
function exportquiz_get_slot_for_question($exportquiz, $group, $questionid) {
    $questionids = exportquiz_get_group_question_ids($exportquiz, $group->id);
    foreach ($questionids as $key => $id) {
        if ($id == $questionid) {
            return $key + 1;
        }
    }
    return null;
}

/**
 * Verify that the question exists, and the user has permission to use it.
 * Does not return. Throws an exception if the question cannot be used.
 * @param int $questionid The id of the question.
 */
function exportquiz_require_question_use($questionid) {
    global $DB;
    $question = $DB->get_record('question', array('id' => $questionid), '*', MUST_EXIST);
    question_require_capability_on($question, 'use');
}

/**
 * Add a question to a exportquiz
 *
 * Adds a question to a exportquiz by updating $exportquiz as well as the
 * exportquiz and exportquiz_slots tables. It also adds a page break if required.
 * @param int $questionid The id of the question to be added
 * @param object $exportquiz The extended exportquiz object as used by edit.php
 *      This is updated by this function
 * @param int $page Which page in exportquiz to add the question on. If 0 (default),
 *      add at the end
 * @param float $maxmark The maximum mark to set for this question. (Optional,
 *      defaults to question.defaultmark.
 * @return bool false if the question was already in the exportquiz
 */
function exportquiz_add_exportquiz_question($questionid, $exportquiz, $page = 0, $maxmark = null) {
    global $DB;
    
    if (exportquiz_has_scanned_pages($exportquiz->id)) {
        return false;
    }
    
    $slots = $DB->get_records('exportquiz_group_questions',
            array('exportquizid' => $exportquiz->id, 'exportgroupid' => $exportquiz->groupid),
            'slot', 'questionid, slot, page, id');
    if (array_key_exists($questionid, $slots)) {
        return false;
    }

    $trans = $DB->start_delegated_transaction();

    $maxpage = 1;
    $numonlastpage = 0;
    foreach ($slots as $slot) {
        if ($slot->page > $maxpage) {
            $maxpage = $slot->page;
            $numonlastpage = 1;
        } else {
            $numonlastpage += 1;
        }
    }

    // Add the new question instance.
    $slot = new stdClass();
    $slot->exportquizid = $exportquiz->id;
    $slot->exportgroupid = $exportquiz->groupid;
    $slot->questionid = $questionid;

    if ($maxmark !== null) {
        $slot->maxmark = $maxmark;
    } else {
        $slot->maxmark = $DB->get_field('question', 'defaultmark', array('id' => $questionid));
    }

    if (is_int($page) && $page >= 1) {
        // Adding on a given page.
        $lastslotbefore = 0;
        foreach (array_reverse($slots) as $otherslot) {
            if ($otherslot->page > $page) {
                // Increase the slot number of the other slot.
                $DB->set_field('exportquiz_group_questions', 'slot', $otherslot->slot + 1, array('id' => $otherslot->id));
            } else {
                $lastslotbefore = $otherslot->slot;
                break;
            }
        }
        $slot->slot = $lastslotbefore + 1;
        $slot->page = min($page, $maxpage + 1);

    } else {
        $lastslot = end($slots);
        if ($lastslot) {
            $slot->slot = $lastslot->slot + 1;
        } else {
            $slot->slot = 1;
        }
        if ($exportquiz->questionsperpage && $numonlastpage >= $exportquiz->questionsperpage) {
            $slot->page = $maxpage + 1;
        } else {
            $slot->page = $maxpage;
        }
    }

    $DB->insert_record('exportquiz_group_questions', $slot);
    $trans->allow_commit();
}


/**
 * Save the questions of an exportquiz in the database.
 * @param object $exportquiz the exportquiz object.
 * @param array $questionids an array of question IDs.
 * @return .
 */
// function exportquiz_save_questions($exportquiz, $questionids = null) {
//     global $DB;

//     if (empty($questionids)) {
//         $questionids = explode(',', $exportquiz->questions);
//     }

//     // Delete all export group questions.
//     $DB->delete_records('exportquiz_group_questions', array('exportquizid' => $exportquiz->id,
//             'exportgroupid' => $exportquiz->groupid));

//     // Then insert them from scratch.
//     $position = 1;
//     foreach ($questionids as $qid) {
//         $data = new stdClass();
//         $data->exportquizid = $exportquiz->id;
//         $data->exportgroupid = $exportquiz->groupid;
//         $data->questionid = $qid;
//         $data->slot = $position;
//         $data->position = $position++;

//         $DB->insert_record('exportquiz_group_questions', $data);
//     }
// }

/**
 * returns the maximum number of questions in a set of export groups
 *
 * @param unknown_type $exportquiz
 * @param unknown_type $groups
 * @return Ambigous <number, unknown>
 */
function exportquiz_get_maxquestions($exportquiz, $groups) {
    global $DB;

    $maxquestions = 0;
    foreach ($groups as $group) {

        $questionids = exportquiz_get_group_question_ids($exportquiz, $group->id);

        list($qsql, $params) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);

        $numquestions = $DB->count_records_sql("SELECT COUNT(id) FROM {question} WHERE qtype <> 'description' AND id $qsql",
                                               $params);
        if ($numquestions > $maxquestions) {
            $maxquestions = $numquestions;
        }
    }
    return $maxquestions;
}

/**
 * returns the maximum number of answers in the group questions of an exportquiz
 * @param unknown_type $exportquiz
 * @return number
 */
function exportquiz_get_maxanswers($exportquiz, $groups = array()) {
    global $CFG, $DB;

    $groupids = array();
    foreach ($groups as $group) {
        $groupids[] = $group->id;
    }

    $sql = "SELECT DISTINCT(questionid)
              FROM {exportquiz_group_questions}
             WHERE exportquizid = :exportquizid
               AND questionid > 0";

    if (!empty($groupids)) {
        list($gsql, $params) = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED);
        $sql .= " AND exportgroupid " . $gsql;
    } else {
        $params = array();
    }

    $params['exportquizid'] = $exportquiz->id;

    $questionids = $DB->get_records_sql($sql, $params);
    $questionlist = array_keys($questionids);

    $counts = array();
    if (!empty($questionlist)) {
        foreach ($questionlist as $questionid) {
            $sql = "SELECT COUNT(id)
                      FROM {question_answers} qa
                     WHERE qa.question = :questionid
                    ";
            $params = array('questionid' => $questionid);
            $counts[] = $DB->count_records_sql($sql, $params);
        }
        return max($counts);
    } else {
        return 0;
    }
}


/**
 * Repaginate the questions in a exportquiz
 * @param int $exportquizid the id of the exportquiz to repaginate.
 * @param int $slotsperpage number of items to put on each page. 0 means unlimited.
 */
function exportquiz_repaginate_questions($exportquizid, $exportgroupid, $slotsperpage) {
    global $DB;
    $trans = $DB->start_delegated_transaction();

    $slots = $DB->get_records('exportquiz_group_questions',
            array('exportquizid' => $exportquizid, 'exportgroupid' => $exportgroupid),
            'slot');

    $currentpage = 1;
    $slotsonthispage = 0;
    foreach ($slots as $slot) {
        if ($slotsonthispage && $slotsonthispage == $slotsperpage) {
            $currentpage += 1;
            $slotsonthispage = 0;
        }
        if ($slot->page != $currentpage) {
            $DB->set_field('exportquiz_group_questions', 'page', $currentpage,
                    array('id' => $slot->id));
        }
        $slotsonthispage += 1;
    }

    $trans->allow_commit();
}

/**
 * Re-paginates the exportquiz layout
 *
 * @return string         The new layout string
 * @param string $layout  The string representing the exportquiz layout.
 * @param integer $perpage The number of questions per page
 * @param boolean $shuffle Should the questions be reordered randomly?
 */
function exportquiz_shuffle_questions($questionids) {
    srand((float)microtime() * 1000000); // For php < 4.2.
    shuffle($questionids);
    return $questionids;
}

/**
 * returns true if there are scanned pages for an export quiz.
 * @param int $exportquizid
 */
function exportquiz_has_scanned_pages($exportquizid) {
    global $CFG, $DB;

    $sql = "SELECT COUNT(id)
              FROM {exportquiz_scanned_pages}
             WHERE exportquizid = :exportquizid";
    $params = array('exportquizid' => $exportquizid);
    return $DB->count_records_sql($sql, $params) > 0;
}


/**
 * Save new maxgrade to a question instance
 *
 * Saves changes to the question grades in the exportquiz_group_questions table.
 * The grades of the questions in the group template qubas are also updated.
 * This function does not update 'sumgrades' in the exportquiz table.
 *
 * @param int $exportquiz  The exportquiz to update / add the instances for.
 * @param int $questionid  The id of the question
 * @param int grade    The maximal grade for the question
 */
function exportquiz_update_question_instance($exportquiz, $questionid, $grade) {
    global $DB;

    // First change the maxmark of the question in all export quiz groups.
    $groupquestionids = $DB->get_fieldset_select('exportquiz_group_questions', 'id',
                    'exportquizid = :exportquizid AND questionid = :questionid',
                    array('exportquizid' => $exportquiz->id, 'questionid' => $questionid));
    
    foreach ($groupquestionids as $groupquestionid) {
        $DB->set_field('exportquiz_group_questions', 'maxmark', $grade, array('id' => $groupquestionid));
    }

    $groups = $DB->get_records('exportquiz_groups', array('exportquizid' => $exportquiz->id), 'number', '*', 0,
                $exportquiz->numgroups);

    // Now change the maxmark of the question instance in the template question usages of the exportquiz groups.
    foreach ($groups as $group) {

        if ($group->templateusageid) {
            $templateusage = question_engine::load_questions_usage_by_activity($group->templateusageid);
            $slots = $templateusage->get_slots();

            $slot = 0;
            foreach ($slots as $thisslot) {
                if ($templateusage->get_question($thisslot)->id == $questionid) {
                    $slot = $thisslot;
                    break;
                }
            }
            if ($slot) {
                // Update the grade in the template usage.
                question_engine::set_max_mark_in_attempts(new qubaid_list(array($group->templateusageid)), $slot, $grade);
            }
        }
    }

    // Now do the same for the qubas of the results of the export quiz.
    if ($results = $DB->get_records('exportquiz_results', array('exportquizid' => $exportquiz->id))) {
        foreach ($results as $result) {
            if ($result->usageid > 0) {
                $quba = question_engine::load_questions_usage_by_activity($result->usageid);
                $slots = $quba->get_slots();
                
                $slot = 0;
                foreach ($slots as $thisslot) {
                    if ($quba->get_question($thisslot)->id == $questionid) {
                        $slot = $thisslot;
                        break;
                    }
                }
                if ($slot) {
                    question_engine::set_max_mark_in_attempts(new qubaid_list(array($result->usageid)), $slot, $grade);

                    // Now set the new sumgrades also in the export quiz result.
                    $newquba = question_engine::load_questions_usage_by_activity($result->usageid);
                    $DB->set_field('exportquiz_results', 'sumgrades',  $newquba->get_total_mark(),
                        array('id' => $result->id));
                }
            }
        }
    }
}


class result_qubaids_for_exportquiz extends qubaid_join {
    public function __construct($exportquizid, $exportgroupid, $includepreviews = true, $onlyfinished = false) {
        $where = 'quiza.exportquizid = :exportquizid AND quiza.exportgroupid = :exportgroupid';
        if (!$includepreviews) {
            $where .= ' AND preview = 0';
        }
        if ($onlyfinished) {
            $where .= ' AND timefinish <> 0';
        }

        parent::__construct('{exportquiz_results} quiza', 'quiza.usageid', $where,
                array('exportquizid' => $exportquizid, 'exportgroupid' => $exportgroupid));
    }
}

/**
 * The exportquiz grade is the maximum that student's results are marked out of. When it
 * changes, the corresponding data in exportquiz_grades and exportquiz_feedback needs to be
 * rescaled. After calling this function, you probably need to call
 * exportquiz_update_all_attempt_sumgrades, exportquiz_update_all_final_grades and
 * exportquiz_update_grades.
 *
 * @param float $newgrade the new maximum grade for the exportquiz.
 * @param object $exportquiz the exportquiz we are updating. Passed by reference so its
 *      grade field can be updated too.
 * @return bool indicating success or failure.
 */
function exportquiz_set_grade($newgrade, $exportquiz) {
    global $DB;
    // This is potentially expensive, so only do it if necessary.
    if (abs($exportquiz->grade - $newgrade) < 1e-7) {
        // Nothing to do.
        return true;
    }

    // Use a transaction, so that on those databases that support it, this is safer.
    $transaction = $DB->start_delegated_transaction();

    // Update the exportquiz table.
    $DB->set_field('exportquiz', 'grade', $newgrade, array('id' => $exportquiz->id));

    $exportquiz->grade = $newgrade;

    // Update grade item and send all grades to gradebook.
    exportquiz_grade_item_update($exportquiz);
    exportquiz_update_grades($exportquiz);

    $transaction->allow_commit();
    return true;
}


/**
 * Returns info about the JS module used by exportquizzes.
 *
 * @return multitype:string multitype:string  multitype:multitype:string
 */
function exportquiz_get_js_module() {
    global $PAGE;
    return array(
            'name' => 'mod_exportquiz',
            'fullpath' => '/mod/exportquiz/module.js',
            'requires' => array('base', 'dom', 'event-delegate', 'event-key',
                    'core_question_engine'),
            'strings' => array(
                    array('timesup', 'exportquiz'),
                    array('functiondisabledbysecuremode', 'exportquiz'),
                    array('flagged', 'question'),
            ),
    );
}

// Other exportquiz functions.

/**
 * @param object $exportquiz the exportquiz.
 * @param int $cmid the course_module object for this exportquiz.
 * @param object $question the question.
 * @param string $returnurl url to return to after action is done.
 * @return string html for a number of icons linked to action pages for a
 * question - preview and edit / view icons depending on user capabilities.
 */
function exportquiz_question_action_icons($exportquiz, $cmid, $question, $returnurl) {
    $html = exportquiz_question_preview_button($exportquiz, $question);
    if (!$exportquiz->docscreated) {
        $html .= ' ' .  exportquiz_question_edit_button($cmid, $question, $returnurl);
    }
    return $html;
}



/**
 * Splits the little formula from $exportquizconfig->useridentification
 * into prefix, postfix, digits and field and stores them in config variables.
 */
function exportquiz_load_useridentification() {
    global $CFG, $DB;

    $exportquizconfig = get_config('exportquiz');

    $errorstr = "Incorrect formula for user identification. Please contact your system administrator to change the settings.";
    $start = strpos($exportquizconfig->useridentification, '[');
    $end = strpos($exportquizconfig->useridentification, ']');
    $digits = substr($exportquizconfig->useridentification, $start + 1, $end - $start - 1);
    if (!is_numeric($digits) or $digits > 9) {
        print_error($errorstr, 'exportquiz');
    }
    $prefix = substr($exportquizconfig->useridentification, 0, $start);
    $postfix = substr($exportquizconfig->useridentification, $end + 1,
                      strpos($exportquizconfig->useridentification, '=') - $end - 1);
    $field = substr($exportquizconfig->useridentification, strpos($exportquizconfig->useridentification, '=') + 1);

    set_config('ID_digits', $digits, 'exportquiz');
    set_config('ID_prefix', $prefix, 'exportquiz');
    set_config('ID_postfix', $postfix, 'exportquiz');
    set_config('ID_field', $field, 'exportquiz');
}


/**
 * Creates an array of maximum grades for an exportquiz
 *
 * The grades are extracted for the exportquiz_question_instances table.
 * @param object $exportquiz The exportquiz settings.
 * @return array of grades indexed by question id. These are the maximum
 *      possible grades that students can achieve for each of the questions.
 */
function exportquiz_get_all_question_grades($exportquiz) {
    global $CFG, $DB;

    $questionlist = $exportquiz->questions;
    if (empty($questionlist)) {
        return array();
    }

    $wheresql = '';
    $params = array();
    if (!empty($questionlist)) {
        list($usql, $questionparams) = $DB->get_in_or_equal($questionlist, SQL_PARAMS_NAMED, 'qid');
        $wheresql = " AND questionid $usql ";
        $params = array_merge($params, $questionparams);
    }
    $params['exportquizid'] = $exportquiz->id;
    
    $instances = $DB->get_records_sql("
            SELECT questionid, maxmark
              FROM {exportquiz_group_questions}
             WHERE exportquizid = :exportquizid 
                   $wheresql", $params);

    $grades = array();
    foreach ($questionlist as $qid) {
        if (isset($instances[$qid])) {
            $grades[$qid] = $instances[$qid]->maxmark;
        } else {
            $grades[$qid] = 1;
        }
    }

    return $grades;
}


/**
 * Returns the number of pages in a exportquiz layout
 *
 * @param string $layout The string representing the exportquiz layout. Always ends in ,0
 * @return int The number of pages in the exportquiz.
 */
function exportquiz_number_of_pages($layout) {
    return substr_count(',' . $layout, ',0');
}

/**
 * Counts the multichoice question in a questionusage.
 *
 * @param question_usage_by_activity $questionusage
 * @return number
 */
function exportquiz_count_multichoice_questions(question_usage_by_activity $questionusage) {
    $count = 0;
    $slots = $questionusage->get_slots();
    foreach ($slots as $slot) {
        $question = $questionusage->get_question($slot);
        if ($question->qtype->name() == 'multichoice' || $question->qtype->name() == 'multichoiceset') {
            $count++;
        }
    }
    return $count;
}

/**
 * Returns the sumgrades for a given exportquiz group.
 *
 * @param object $exportquiz object that must contain the groupid field.
 * @return Ambigous <mixed, boolean>
 */
function exportquiz_get_group_sumgrades($exportquiz) {
    global $DB;

    $sql = 'SELECT COALESCE((SELECT SUM(maxmark)
              FROM {exportquiz_group_questions} ogq
             WHERE ogq.exportquizid = :exportquizid
               AND ogq.exportgroupid = :groupid) , 0)';

    $params = array('exportquizid' => $exportquiz->id,
            'groupid' => $exportquiz->groupid);

    $sumgrades = $DB->get_field_sql($sql, $params);
    return $sumgrades;
}

/**
 * Update the sumgrades field of the exportquiz. This needs to be called whenever
 * the grading structure of the exportquiz is changed. For example if a question is
 * added or removed, or a question weight is changed.
 *
 * @param object $exportquiz a exportquiz.
 */
function exportquiz_update_sumgrades($exportquiz, $exportgroupid = null) {
    global $DB;

    $groupid = 0; 
    if (isset($exportquiz->groupid)) {
        $groupid = $exportquiz->groupid;
    }
    if (!empty($exportgroupid)) {
        $groupid = $exportgroupid;
    }
    $sql = 'UPDATE {exportquiz_groups}
               SET sumgrades = COALESCE((
                   SELECT SUM(maxmark)
                     FROM {exportquiz_group_questions} ogq
                    WHERE ogq.exportquizid = :exportquizid1
                      AND ogq.exportgroupid = :groupid1
                      ), 0)
             WHERE exportquizid = :exportquizid2
               AND id = :groupid2';

    $params = array('exportquizid1' => $exportquiz->id,
            'exportquizid2' => $exportquiz->id,
            'groupid1' => $groupid,
            'groupid2' => $groupid);
    $DB->execute($sql, $params);

    $sumgrades = $DB->get_field('exportquiz_groups', 'sumgrades', array('id' => $groupid));

    return $sumgrades;
}

/**
 * Convert the raw grade stored in $attempt into a grade out of the maximum
 * grade for this exportquiz.
 *
 * @param float $rawgrade the unadjusted grade, fof example $attempt->sumgrades
 * @param object $exportquiz the exportquiz object. Only the fields grade, sumgrades and decimalpoints are used.
 * @param bool|string $format whether to format the results for display
 *      or 'question' to format a question grade (different number of decimal places.
 * @return float|string the rescaled grade, or null/the lang string 'notyetgraded'
 *      if the $grade is null.
 */
function exportquiz_rescale_grade($rawgrade, $exportquiz, $group, $format = true) {
    if (is_null($rawgrade)) {
        $grade = null;
    } else if ($group->sumgrades >= 0.000005) {
        $grade = $rawgrade / $group->sumgrades * $exportquiz->grade;
    } else {
        $grade = 0;
    }
    if ($format === 'question') {
        $grade = exportquiz_format_question_grade($exportquiz, $grade);
    } else if ($format) {
        $grade = exportquiz_format_grade($exportquiz, $grade);
    }
    return $grade;
}


/**
 * Extends first object with member data of the second
 *
 * @param unknown_type $first
 * @param unknown_type $second
 */
function exportquiz_extend_object (&$first, &$second) {

    foreach ($second as $key => $value) {
        if (empty($first->$key)) {
            $first->$key = $value;
        }
    }

}

/**
 * Returns the group object for a given exportquiz and group number (1,2,3...). Adds a
 * new group if the group does not exist.
 *
 * @param unknown_type $exportquiz
 * @param unknown_type $groupnumber
 * @return Ambigous <mixed, boolean, unknown>
 */
function exportquiz_get_group($exportquiz, $groupnumber) {
    global $DB;

    if (!$exportquizgroup = $DB->get_record('exportquiz_groups',
                                              array('exportquizid' => $exportquiz->id, 'number' => $groupnumber))) {
        if ($groupnumber > 0 && $groupnumber <= $exportquiz->numgroups) {
            $exportquizgroup = exportquiz_add_group( $exportquiz->id, $groupnumber);
        }
    }
    return $exportquizgroup;
}

/**
 * Adds a new group with a given group number to a given exportquiz.
 *
 * @param object $exportquiz the data that came from the form.
 * @param int groupnumber The number of the group to add.
 * @return mixed the id of the new instance on success,
 *          false or a string error message on failure.
 */
function exportquiz_add_group($exportquizid, $groupnumber) {
    GLOBAL $DB;

    $exportquizgroup = new StdClass();
    $exportquizgroup->exportquizid = $exportquizid;
    $exportquizgroup->number = $groupnumber;

    // Note: numberofpages and templateusageid will be filled later.

    // Try to store it in the database.
    if (!$exportquizgroup->id = $DB->insert_record('exportquiz_groups', $exportquizgroup)) {
        return false;
    }

    return $exportquizgroup;
}


/**
 * An extension of question_display_options that includes the extra options used
 * by the exportquiz.
 *
 * @copyright     2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_exportquiz_display_options extends question_display_options {
    /**
     * @var boolean if this is false, then the student is not allowed to review
     * anything about the result.
     */
    public $responses = true;

    /**
     * @var boolean if this is false, then the student cannot see the scanned answer forms
     */
    public $sheetfeedback = false;

    /**
     * @var boolean if this is false, then the student cannot see any markings in the scanned answer forms.
     */
    public $gradedsheetfeedback = false;

    /**
     * Set up the various options from the exportquiz settings, and a time constant.
     * @param object $exportquiz the exportquiz settings.
     * @param int $one of the {@link DURING}, {@link IMMEDIATELY_AFTER},
     * {@link LATER_WHILE_OPEN} or {@link AFTER_CLOSE} constants.
     * @return mod_exportquiz_display_options set up appropriately.
     */
    public static function make_from_exportquiz($exportquiz) {
        $options = new self();

        $options->attempt = self::extract($exportquiz->review, EXPORTQUIZ_REVIEW_ATTEMPT);
        $options->marks = self::extract($exportquiz->review, EXPORTQUIZ_REVIEW_MARKS) ?
            question_display_options::MARK_AND_MAX : question_display_options::HIDDEN;
        $options->correctness = self::extract($exportquiz->review, EXPORTQUIZ_REVIEW_CORRECTNESS);
        $options->feedback = self::extract($exportquiz->review, EXPORTQUIZ_REVIEW_SPECIFICFEEDBACK);
        $options->generalfeedback = self::extract($exportquiz->review, EXPORTQUIZ_REVIEW_GENERALFEEDBACK);
        $options->rightanswer = self::extract($exportquiz->review, EXPORTQUIZ_REVIEW_RIGHTANSWER);
        $options->sheetfeedback = self::extract($exportquiz->review, EXPORTQUIZ_REVIEW_SHEET);
        $options->gradedsheetfeedback = self::extract($exportquiz->review, EXPORTQUIZ_REVIEW_GRADEDSHEET);

        $options->numpartscorrect = $options->feedback;

        if (property_exists($exportquiz, 'decimalpoints')) {
            $options->markdp = $exportquiz->decimalpoints;
        }

        // We never want to see any flags.
        $options->flags = question_display_options::HIDDEN;

        return $options;
    }

    protected static function extract($bitmask, $bit, $whenset = self::VISIBLE, $whennotset = self::HIDDEN) {
        if ($bitmask & $bit) {
            return $whenset;
        } else {
            return $whennotset;
        }
    }
}


/**
 * The appropriate mod_exportquiz_display_options object for this result at this
 * exportquiz right now.
 *
 * @param object $exportquiz the exportquiz instance.
 * @param object $result the result in question.
 * @param $context the exportquiz context.
 *
 * @return mod_exportquiz_display_options
 */
function exportquiz_get_review_options($exportquiz, $result, $context) {

    $options = mod_exportquiz_display_options::make_from_exportquiz($exportquiz);

    $options->readonly = true;

    if (!empty($result->id)) {
        $options->questionreviewlink = new moodle_url('/mod/exportquiz/reviewquestion.php',
                array('resultid' => $result->id));
    }

    if (!is_null($context) &&
            has_capability('mod/exportquiz:viewreports', $context) &&
            has_capability('moodle/grade:viewhidden', $context)) {

        // The teacher should be shown everything.
        $options->attempt = question_display_options::VISIBLE;
        $options->marks = question_display_options::MARK_AND_MAX;
        $options->correctness = question_display_options::VISIBLE;
        $options->feedback = question_display_options::VISIBLE;
        $options->rightanswer = question_display_options::VISIBLE;
        $options->generalfeedback = question_display_options::VISIBLE;
        $options->sheetfeedback = question_display_options::VISIBLE;
        $options->gradedsheetfeedback = question_display_options::VISIBLE;

        // Show a link to the comment box only for closed attempts.
        if (!empty($result->id) && $result->timefinish &&
                !is_null($context) && has_capability('mod/exportquiz:grade', $context)) {
            $options->manualcomment = question_display_options::VISIBLE;
            $options->manualcommentlink = new moodle_url('/mod/exportquiz/comment.php',
                    array('resultid' => $result->id));
        }
    }
    return $options;
}


/**
 * Combines the review options from a number of different exportquiz attempts.
 * Returns an array of two ojects, so the suggested way of calling this
 * funciton is:
 * list($someoptions, $alloptions) = exportquiz_get_combined_reviewoptions(...)
 *
 * @param object $exportquiz the exportquiz instance.
 * @param array $attempts an array of attempt objects.
 * @param $context the roles and permissions context,
 *          normally the context for the exportquiz module instance.
 *
 * @return array of two options objects, one showing which options are true for
 *          at least one of the attempts, the other showing which options are true
 *          for all attempts.
 */
function exportquiz_get_combined_reviewoptions($exportquiz) {
    $fields = array('feedback', 'generalfeedback', 'rightanswer');
    $someoptions = new stdClass();
    $alloptions = new stdClass();
    foreach ($fields as $field) {
        $someoptions->$field = false;
        $alloptions->$field = true;
    }
    $someoptions->marks = question_display_options::HIDDEN;
    $alloptions->marks = question_display_options::MARK_AND_MAX;

    $attemptoptions = mod_exportquiz_display_options::make_from_exportquiz($exportquiz);
    foreach ($fields as $field) {
        $someoptions->$field = $someoptions->$field || $attemptoptions->$field;
        $alloptions->$field = $alloptions->$field && $attemptoptions->$field;
    }
    $someoptions->marks = max($someoptions->marks, $attemptoptions->marks);
    $alloptions->marks = min($alloptions->marks, $attemptoptions->marks);

    return array($someoptions, $alloptions);
}
    
/**
 * Creates HTML code for a question edit button, used by editlib.php
 *
 * @param int $cmid the course_module.id for this exportquiz.
 * @param object $question the question.
 * @param string $returnurl url to return to after action is done.
 * @param string $contentbeforeicon some HTML content to be added inside the link, before the icon.
 * @return the HTML for an edit icon, view icon, or nothing for a question
 *      (depending on permissions).
 */
function exportquiz_question_edit_button($cmid, $question, $returnurl, $contentaftericon = '') {
    global $CFG, $OUTPUT;

    // Minor efficiency saving. Only get strings once, even if there are a lot of icons on one page.
    static $stredit = null;
    static $strview = null;
    if ($stredit === null) {
        $stredit = get_string('edit');
        $strview = get_string('view');
    }

    // What sort of icon should we show?
    $action = '';
    if (!empty($question->id) &&
            (question_has_capability_on($question, 'edit', $question->category) ||
                    question_has_capability_on($question, 'move', $question->category))
    ) {
        $action = $stredit;
        $icon = '/t/edit';
    } else if (!empty($question->id) &&
            question_has_capability_on($question, 'view', $question->category)) {
        $action = $strview;
        $icon = '/i/info';
    }

    // Build the icon.
    if ($action) {
        if ($returnurl instanceof moodle_url) {
            $returnurl = str_replace($CFG->wwwroot, '', $returnurl->out(false));
        }
        $questionparams = array('returnurl' => $returnurl, 'cmid' => $cmid, 'id' => $question->id);
        $questionurl = new moodle_url("$CFG->wwwroot/question/question.php", $questionparams);
        return '<a title="' . $action . '" href="' . $questionurl->out() . '"><img src="' .
                $OUTPUT->pix_url($icon) . '" alt="' . $action . '" />' . $contentaftericon .
                '</a>';
    } else {
        return $contentaftericon;
    }
}

/**
 * Creates HTML code for a question preview button.
 *
 * @param object $exportquiz the exportquiz settings
 * @param object $question the question
 * @param bool $label if true, show the preview question label after the icon
 * @return the HTML for a preview question icon.
 */
function exportquiz_question_preview_button($exportquiz, $question, $label = false) {
    global $CFG, $OUTPUT;
    if (property_exists($question, 'category') &&
            !question_has_capability_on($question, 'use', $question->category)) {
        return '';
    }

    $url = exportquiz_question_preview_url($exportquiz, $question);

    // Do we want a label?
    $strpreviewlabel = '';
    if ($label) {
        $strpreviewlabel = get_string('preview', 'exportquiz');
    }

    // Build the icon.
    $strpreviewquestion = get_string('previewquestion', 'exportquiz');
    $image = $OUTPUT->pix_icon('t/preview', $strpreviewquestion);

    $action = new popup_action('click', $url, 'questionpreview',
            question_preview_popup_params());

    return $OUTPUT->action_link($url, $image, $action, array('title' => $strpreviewquestion));
}

/**
 * @param object $exportquiz the exportquiz settings
 * @param object $question the question
 * @return moodle_url to preview this question with the options from this exportquiz.
 */
function exportquiz_question_preview_url($exportquiz, $question) {
    // Get the appropriate display options.
    $displayoptions = mod_exportquiz_display_options::make_from_exportquiz($exportquiz);

    $maxmark = null;
    if (isset($question->maxmark)) {
        $maxmark = $question->maxmark;
    }

    // Work out the correct preview URL.
    return question_preview_url($question->id, null,
            $maxmark, $displayoptions);
}


/**
 * Retrieves a template question usage for an export group. Creates a new template if there is none.
 * While creating question usage it shuffles the group questions if shuffleanswers is created.
 *
 * @param object $exportquiz
 * @param object $group
 * @param object $context
 * @return question_usage_by_activity
 */
function exportquiz_get_group_template_usage($exportquiz, $group, $context) {
    global $CFG, $DB;

    if (!empty($group->templateusageid) && $group->templateusageid > 0) {
        $templateusage = question_engine::load_questions_usage_by_activity($group->templateusageid);
    } else {

        $questionids = exportquiz_get_group_question_ids($exportquiz, $group->id);

        if ($exportquiz->shufflequestions) {
            $exportquiz->groupid = $group->id;

            $questionids = exportquiz_shuffle_questions($questionids);
        }

        // We have to use our own class s.t. we can use the clone function to create results.
        $templateusage = exportquiz_make_questions_usage_by_activity('mod_exportquiz', $context);
        $templateusage->set_preferred_behaviour('immediatefeedback');

        if (!$questionids) {
            print_error(get_string('noquestionsfound', 'exportquiz'), 'view.php?q='.$exportquiz->id);
        }

        // Gets database raw data for the questions.
        $questiondata = question_load_questions($questionids);

        // Get the question instances for initial markmarks.
        $sql = "SELECT questionid, maxmark
                  FROM {exportquiz_group_questions}
                 WHERE exportquizid = :exportquizid
                   AND exportgroupid = :exportgroupid ";

        $groupquestions = $DB->get_records_sql($sql,
                array('exportquizid' => $exportquiz->id, 'exportgroupid' => $group->id));

        foreach ($questionids as $questionid) {
            if ($questionid) {
                // Convert the raw data of multichoice questions to a real question definition object.
                if (!$exportquiz->shuffleanswers) {
                    $questiondata[$questionid]->options->shuffleanswers = false;
                }
                $question = question_bank::make_question($questiondata[$questionid]);

                // We only add multichoice questions which are needed for grading.
                if ($question->get_type_name() == 'multichoice' || $question->get_type_name() == 'multichoiceset') {
                    $templateusage->add_question($question, $groupquestions[$question->id]->maxmark);
                }
            }
        }

        // Create attempts for all questions (fixes order of the answers if shuffleanswers is active).
        $templateusage->start_all_questions();

        // Save the template question usage to the DB.
        question_engine::save_questions_usage_by_activity($templateusage);

        // Save the templateusage-ID in the exportquiz_groups table.
        $group->templateusageid = $templateusage->get_id();
        $DB->set_field('exportquiz_groups', 'templateusageid', $group->templateusageid, array('id' => $group->id));
    } // End else.
    return $templateusage;
}


/**
 * Deletes the PDF forms of an exportquiz.
 *
 * @param object $exportquiz
 */
function exportquiz_delete_pdf_forms($exportquiz) {
    global $DB;

    $fs = get_file_storage();
    
    // If the exportquiz has just been created then there is no cmid.
    if (isset($exportquiz->cmid)) {    
        $context = context_module::instance($exportquiz->cmid);

        // Delete PDF documents.
        $files = $fs->get_area_files($context->id, 'mod_exportquiz', 'pdfs');
        foreach ($files as $file) {
            $file->delete();
        }
    }

    // Set exportquiz->docscreated to 0.
    $exportquiz->docscreated = 0;
    $DB->set_field('exportquiz', 'docscreated', 0, array('id' => $exportquiz->id));
    return $exportquiz;
}

/**
 * Deletes the question usages by activity for an exportquiz. This function must not be
 * called if the export quiz has attempts or scanned pages
 *
 * @param object $exportquiz
 */
function exportquiz_delete_template_usages($exportquiz, $deletefiles = true) {
    global $CFG, $DB, $OUTPUT;

    if ($groups = $DB->get_records('exportquiz_groups',
                                   array('exportquizid' => $exportquiz->id), 'number', '*', 0, $exportquiz->numgroups)) {
        foreach ($groups as $group) {
            if ($group->templateusageid) {
                question_engine::delete_questions_usage_by_activity($group->templateusageid);
                $group->templateusageid = 0;
                $DB->set_field('exportquiz_groups', 'templateusageid', 0, array('id' => $group->id));
            }
        }
    }

    // Also delete the PDF forms if they have been created.
    if ($deletefiles) {
        return exportquiz_delete_pdf_forms($exportquiz);
    } else {
        return $exportquiz;
    }
}


function exportquiz_delete_template_usages_for_group($exportquiz, $groupid ,$deletefiles = true) {
    global $CFG, $DB, $OUTPUT;

    if ($groups = $DB->get_records('exportquiz_groups',
                                   array('exportquizid' => $exportquiz->id, 'id' => $groupid), 'number', '*', 0, 1)) {
        foreach ($groups as $group) {
            if ($group->templateusageid) {
                question_engine::delete_questions_usage_by_activity($group->templateusageid);
                $group->templateusageid = 0;
                $DB->set_field('exportquiz_groups', 'templateusageid', 0, array('id' => $group->id));
            }
        }
    }

    // Also delete the PDF forms if they have been created.
    if ($deletefiles) {
        return exportquiz_delete_pdf_forms($exportquiz);
    } else {
        return $exportquiz;
    }
}

/**
 * Prints a preview for a question in an exportquiz to Stdout.
 *
 * @param object $question
 * @param array $choiceorder
 * @param int $number
 * @param object $context
 */
function exportquiz_print_question_preview($question, $choiceorder, $number, $context, $page) {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/filter/mathjaxloader/filter.php' );

    $letterstr = 'abcdefghijklmnopqrstuvwxyz';

    echo '<div id="q' . $question->id . '" class="preview">
            <div class="question">
              <span class="number">';

    if ($question->qtype != 'description') {
        echo $number.')&nbsp;&nbsp;';
    }
    echo '    </span>';

    $text = question_rewrite_question_preview_urls($question->questiontext, $question->id,
            $question->contextid, 'question', 'questiontext', $question->id,
            $context->id, 'exportquiz');

    // Remove leading paragraph tags because the cause a line break after the question number.
    $text = preg_replace('!^<p>!i', '', $text);

    // Filter only for tex formulas.
    $texfilter = null;
    $mathjaxfilter = null;
    $filters = filter_get_active_in_context($context);

    if (array_key_exists('mathjaxloader', $filters)) {
        $mathjaxfilter = new filter_mathjaxloader($context, array());
        $mathjaxfilter->setup($page, $context);
    }
    if (array_key_exists('tex', $filters)) {
        $texfilter = new filter_tex($context, array());
    }
    if ($mathjaxfilter) {
        $text = $mathjaxfilter->filter($text);
        if ($question->qtype != 'description') {
            foreach ($choiceorder as $key => $answer) {
                $question->options->answers[$answer]->answer = $mathjaxfilter->filter($question->options->answers[$answer]->answer);
            }
        }
    } else if ($texfilter) {
        $text = $texfilter->filter($text);
        if ($question->qtype != 'description') {
            foreach ($choiceorder as $key => $answer) {
                $question->options->answers[$answer]->answer = $texfilter->filter($question->options->answers[$answer]->answer);
            }
        }
    }

    echo '<b>'.$text.'</b>';

    echo '  </div>';
    if ($question->qtype != 'description') {
        echo '  <div class="grade">';
        echo '(' . get_string('marks', 'quiz') . ': ' . ($question->maxmark + 0) . ')';
        echo '  </div>';

		if($question->qtype == 'multichoice' || $question->qtype == 'multichoiceset')
		{
		    foreach ($choiceorder as $key => $answer)
			{
		        $answertext = $question->options->answers[$answer]->answer;

		        // Remove all HTML comments (typically from MS Office).
		        $answertext = preg_replace("/<!--.*?--\s*>/ms", "", $answertext);
		        // Remove all paragraph tags because they mess up the layout.
		        $answertext = preg_replace("/<p[^>]*>/ms", "", $answertext);
		        $answertext = preg_replace("/<\/p[^>]*>/ms", "", $answertext);

		        echo "<div class=\"answer\">$letterstr[$key])&nbsp;&nbsp;";
		        echo $answertext;
		        echo "</div>";
		    }
		}
		else if($question->qtype == 'truefalse')
		{
			$answers = $DB->get_records('question_answers', array('question' => $question->id), 'id ASC');
					
			$i = 0;
			
			foreach ($answers as $key => $answer)
			{
				$singleanswers[$i]['answer'] = $question->options->answers[$key]->answer;
				$singleanswers[$i]['fraction'] = $question->options->answers[$key]->fraction;
				
				$i++;
			}
			
			echo "<div class=\"answer\">$letterstr[0])&nbsp;&nbsp;";
	        echo $singleanswers[0]['answer'];
	        echo "</div>";

			echo "<div class=\"answer\">$letterstr[1])&nbsp;&nbsp;";
	        echo $singleanswers[1]['answer'];
	        echo "</div>";
		}
		else if($question->qtype == 'numerical' || $question->qtype == 'shortanswer')
		{
			echo "<div class=\"answer\">";
	        echo get_string('answer', 'exportquiz').":&nbsp;&nbsp;____________";
	        echo "</div>";
		}
		else if($question->qtype == 'match')
		{
			$questiondata = $DB->get_records('qtype_match_subquestions',
                array('questionid' => $question->id), 'id ASC');
			echo "<div class=\"answer\">";
			
			//Imprimo las posibles respuestas (pueden ser más respuestas que preguntas)
			$i=0;
			foreach($questiondata as $question)
			{
				echo "<b>".$letterstr[$i].") ".$question->answertext."</b>&nbsp;&nbsp;&nbsp;&nbsp;";
				$i++;
			}

			echo "</div>";

			//Imprimo las preguntas que se han de emparejar
			$i=0;
			foreach($questiondata as $question)
			{
				if($question->questiontext != '')
				{
					$answertext = $question->questiontext;

				    // Remove all HTML comments (typically from MS Office).
				    $answertext = preg_replace("/<!--.*?--\s*>/ms", "", $answertext);
				    // Remove all paragraph tags because they mess up the layout.
				    $answertext = preg_replace("/<p[^>]*>/ms", "", $answertext);
				    $answertext = preg_replace("/<\/p[^>]*>/ms", "", $answertext);
					$answertext = preg_replace('/<br>/', '', nl2br($answertext), 1);
				
					echo "<div class=\"answer\">";
				    echo $answertext.":&nbsp;&nbsp;____________";
				    echo "</div>";
					$i++;
				}
			}
		}
    }
    echo "</div>";
}


/**
 * Creates a textual representation of a question for display.
 *
 * @param object $question A question object from the database questions table
 * @param bool $showicon If true, show the question's icon with the question. False by default.
 * @param bool $showquestiontext If true (default), show question text after question name.
 *       If false, show only question name.
 * @param bool $return If true (default), return the output. If false, print it.
 */
function exportquiz_question_tostring($question, $showicon = false,
        $showquestiontext = true, $return = true, $shorttitle = false) {
    global $COURSE;

    $result = '';

    $formatoptions = new stdClass();
    $formatoptions->noclean = true;
    $formatoptions->para = false;

    $questiontext = strip_tags(question_utils::to_plain_text($question->questiontext, $question->questiontextformat,
                                                             array('noclean' => true, 'para' => false)));
    $questiontitle = strip_tags(format_text($question->name, $question->questiontextformat, $formatoptions, $COURSE->id));

    $result .= '<span class="questionname" title="' . $questiontitle . '">';
    if ($shorttitle && strlen($questiontitle) > 25) {
        $questiontitle = shorten_text($questiontitle, 25, false, '...');
    }

    if ($showicon) {
        $result .= print_question_icon($question, true);
        echo ' ';
    }

    if ($shorttitle) {
        $result .= $questiontitle;
    } else {
        $result .= shorten_text(format_string($question->name), 200) . '</span>';
    }

    if ($showquestiontext) {
        $result .= '<span class="questiontext" title="' . $questiontext . '">';

        $questiontext = shorten_text($questiontext, 200);

        if (!empty($questiontext)) {
            $result .= $questiontext;
        } else {
            $result .= '<span class="error">';
            $result .= get_string('questiontextisempty', 'exportquiz');
            $result .= '</span>';
        }
        $result .= '</span>';
    }
    if ($return) {
        return $result;
    } else {
        echo $result;
    }
}
/**
 * Add a question to a exportquiz group
 *
 * Adds a question to a exportquiz by updating $exportquiz as well as the
 * exportquiz and exportquiz_question_instances tables. It also adds a page break
 * if required.
 * @param int $id The id of the question to be added
 * @param object $exportquiz The extended exportquiz object as used by edit.php
 *      This is updated by this function
 * @param int $page Which page in exportquiz to add the question on. If 0 (default),
 *      add at the end
 * @return bool false if the question was already in the exportquiz
 */
function exportquiz_add_questionlist_to_group($questionids, $exportquiz, $exportgroup, $fromexportgroup = null, $maxmarks = null) {
    global $DB;

    if (exportquiz_has_scanned_pages($exportquiz->id)) {
        return false;
    }

    // Don't add the same question twice.
    foreach ($questionids as $questionid) {
        $slots = $DB->get_records('exportquiz_group_questions',
                array('exportquizid' => $exportquiz->id, 'exportgroupid' => $exportgroup->id),
                'slot', 'questionid, slot, page, id');

        if (array_key_exists($questionid, $slots)) {
            continue;
        }
        
        $trans = $DB->start_delegated_transaction();
        // If the question is already in another group, take the maxmark of that.
        $maxmark = null;
        if ($fromexportgroup && $oldmaxmark = $DB->get_field('exportquiz_group_questions', 'maxmark',
                    array('exportquizid' => $exportquiz->id,
                          'exportgroupid' => $fromexportgroup,
                          'questionid' => $questionid))) {
            $maxmark = $oldmaxmark;
        } else if ($maxmarks && array_key_exists($questionid, $maxmarks)) {
            $maxmark = $maxmarks[$questionid];
        }

        $maxpage = 1;
        $numonlastpage = 0;
        foreach ($slots as $slot) {
            if ($slot->page > $maxpage) {
                $maxpage = $slot->page;
                $numonlastpage = 1;
            } else {
                $numonlastpage += 1;
            }
        }
        
        // Add the new question instance.
        $slot = new stdClass();
        $slot->exportquizid = $exportquiz->id;
        $slot->exportgroupid = $exportgroup->id;
        $slot->questionid = $questionid;
        
        if ($maxmark !== null) {
            $slot->maxmark = $maxmark;
        } else {
            $slot->maxmark = $DB->get_field('question', 'defaultmark', array('id' => $questionid));
        }
        

        $lastslot = end($slots);
        if ($lastslot) {
            $slot->slot = $lastslot->slot + 1;
        } else {
            $slot->slot = 1;
        }
        $slot->page = 0;

        
        if (!$slot->page) {
            if ($exportquiz->questionsperpage && $numonlastpage >= $exportquiz->questionsperpage) {
                $slot->page = $maxpage + 1;
            } else {
                $slot->page = $maxpage;
            }
        }
        $DB->insert_record('exportquiz_group_questions', $slot);
        $trans->allow_commit();
    }
}

/**
 * Randomly add a number of multichoice questions to an exportquiz group.
 * 
 * @param unknown_type $exportquiz
 * @param unknown_type $addonpage
 * @param unknown_type $categoryid
 * @param unknown_type $number
 * @param unknown_type $includesubcategories
 */
function exportquiz_add_random_questions($exportquiz, $exportgroup, $categoryid, $number, $recurse) {
    global $DB;

    $category = $DB->get_record('question_categories', array('id' => $categoryid));
    if (!$category) {
        print_error('invalidcategoryid', 'error');
    }

    $catcontext = context::instance_by_id($category->contextid);
    require_capability('moodle/question:useall', $catcontext);

    if ($recurse) {
        $categoryids = question_categorylist($category->id);
    } else {
        $categoryids = array($category->id);
    }

    list($qcsql, $qcparams) = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'qc');

    // Find all questions in the selected categories that are not in the export group yet.
    $sql = "SELECT id
              FROM {question} q
             WHERE q.category $qcsql
               AND q.parent = 0
               AND q.hidden = 0
               AND q.qtype IN ('multichoice', 'multichoiceset', 'description', 'truefalse', 'numerical', 'match', 'shortanswer')
               AND NOT EXISTS (SELECT 1 
                                 FROM {exportquiz_group_questions} ogq
                                WHERE ogq.questionid = q.id
                                  AND ogq.exportquizid = :exportquizid
                                  AND ogq.exportgroupid = :exportgroupid)";
    
    $qcparams['exportquizid'] = $exportquiz->id;
    $qcparams['exportgroupid'] = $exportgroup->id;
    
    $questionids = $DB->get_fieldset_sql($sql, $qcparams);
    srand(microtime() * 1000000);
    shuffle($questionids);
    
    $chosenids = array();
    while (($questionid = array_shift($questionids)) && $number > 0) {
        $chosenids[] = $questionid;
        $number -= 1;
    }
    
    $maxmarks = array();
    if ($chosenids) {
        // Get the old maxmarks in case questions are already in other exportquiz groups.
        list($qsql, $params) = $DB->get_in_or_equal($chosenids, SQL_PARAMS_NAMED);
    
        $sql = "SELECT id, questionid, maxmark
                  FROM {exportquiz_group_questions}
                 WHERE exportquizid = :exportquizid
                   AND questionid $qsql";
        $params['exportquizid'] = $exportquiz->id;
    
        if ($slots = $DB->get_records_sql($sql, $params)) {
            foreach ($slots as $slot) {
                if (!array_key_exists($slot->questionid, $maxmarks)) {
                    $maxmarks[$slot->questionid] = $slot->maxmark;
                }
            }
        }
    }

    exportquiz_add_questionlist_to_group($chosenids, $exportquiz, $exportgroup, null, $maxmarks);
}

/**
 * 
 * @param unknown $exportquiz
 * @param unknown $questionids
 */
function exportquiz_remove_questionlist($exportquiz, $questionids) {
    global $DB;
    
    // Go through the question IDs and remove them if they exist.
    // We do a DB commit after each question ID to make things simpler. 
    foreach ($questionids as $questionid) {
        // Retrieve the slots indexed by id
        $slots = $DB->get_records('exportquiz_group_questions',
                array('exportquizid' => $exportquiz->id, 'exportgroupid' => $exportquiz->groupid),
                'slot');

        // Build an array with slots indexed by questionid and indexed by slot number.
        $questionslots = array();
        $slotsinorder = array();
        foreach ($slots as $slot) {
            $questionslots[$slot->questionid] = $slot;
            $slotsinorder[$slot->slot] = $slot;
        }

        if (!array_key_exists($questionid, $questionslots)) {
            continue;
        }   

        $slot = $questionslots[$questionid];

        $nextslot = null;
        $prevslot = null;
        if (array_key_exists($slot->slot + 1, $slotsinorder)) {
            $nextslot = $slotsinorder[$slot->slot + 1];
        }
        if (array_key_exists($slot->slot - 1, $slotsinorder)) {
            $prevslot = $slotsinorder[$slot->slot - 1];
        }
        $lastslot = end($slotsinorder);

        $trans = $DB->start_delegated_transaction();        

        // Reduce the page numbers of the following slots if there is no previous slot
        // or the page number of the previous slot is smaller than the page number of the current slot. 
        $removepage = false;
        if ($nextslot && $nextslot->page > $slot->page) {
            if (!$prevslot || $prevslot->page < $slot->page) {
                $removepage = true;
            }
        }

        // Delete the slot.
        $DB->delete_records('exportquiz_group_questions',
                array('exportquizid' => $exportquiz->id, 'exportgroupid' => $exportquiz->groupid,
                      'id' => $slot->id));

        // Reduce the slot number in the following slots if there are any.
        // Also reduce the page number if necessary.
        if ($nextslot) {
            for ($curslotnr = $nextslot->slot ; $curslotnr <= $lastslot->slot; $curslotnr++) {
                if ($slotsinorder[$curslotnr]) {
                    if ($removepage) {
                        $slotsinorder[$curslotnr]->page = $slotsinorder[$curslotnr]->page - 1;
                    }
                    // Reduce the slot number by one.
                    $slotsinorder[$curslotnr]->slot = $slotsinorder[$curslotnr]->slot - 1;
                    $DB->update_record('exportquiz_group_questions', $slotsinorder[$curslotnr]);
                }
            }
        }                    

        $trans->allow_commit();
   }
}
