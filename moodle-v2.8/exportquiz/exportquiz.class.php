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

require_once($CFG->libdir . '/accesslib.php');
require_once($CFG->dirroot . '/mod/exportquiz/classes/structure.php');
require_once($CFG->dirroot . '/mod/exportquiz/accessmanager.php');

use mod_exportquiz\structure;

class exportquiz {
    // Fields initialised in the constructor.
    protected $course;
    protected $cm;
    protected $exportquiz;
    protected $context;

    // Fields set later if that data is needed.
    protected $questions = null;
    protected $accessmanager = null;
    protected $ispreviewuser = null;

    // Constructor =============================================================
    /**
     * Constructor, assuming we already have the necessary data loaded.
     *
     * @param object $exportquiz the row from the exportquiz table.
     * @param object $cm the course_module object for this exportquiz.
     * @param object $course the row from the course table for the course we belong to.
     * @param bool $getcontext intended for testing - stops the constructor getting the context.
     */
    public function __construct($exportquiz, $cm, $course, $getcontext = true) {
        $this->exportquiz = $exportquiz;
        $this->cm = $cm;
        $this->exportquiz->cmid = $this->cm->id;
        $this->course = $course;
        if ($getcontext && !empty($cm->id)) {
            $this->context = context_module::instance($cm->id);
        }
    }

    /**
     * Static function to create a new exportquiz object for a specific user.
     *
     * @param int $exportquizid the the exportquiz id.
     * @param int $userid the the userid.
     * @return exportquiz the new exportquiz object
     */
    public static function create($exportquizid, $exportgroupid, $userid = null) {
        global $DB;

        $exportquiz = exportquiz_access_manager::load_exportquiz_and_settings($exportquizid);
        $exportquiz->groupid = $exportgroupid;
        $course = $DB->get_record('course', array('id' => $exportquiz->course), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('exportquiz', $exportquiz->id, $course->id, false, MUST_EXIST);

        // Update exportquiz with override information.
        if ($userid) {
            $exportquiz = exportquiz_update_effective_access($exportquiz, $userid);
        }

        return new exportquiz($exportquiz, $cm, $course);
    }

    /**
     * Create a {@link exportquiz_attempt} for an attempt at this exportquiz.
     * @param object $attemptdata row from the exportquiz_attempts table.
     * @return exportquiz_attempt the new exportquiz_attempt object.
     */
//     public function create_attempt_object($attemptdata) {
//         return new exportquiz_attempt($attemptdata, $this->exportquiz, $this->cm, $this->course);
//     }

    // Functions for loading more data =========================================

    /**
     * Load just basic information about all the questions in this exportquiz.
     */
    public function preload_questions() {
        $this->questions = question_preload_questions(null,
                'slot.maxmark, slot.id AS slotid, slot.slot, slot.page',
                '{exportquiz_group_questions} slot ON slot.exportquizid = :exportquizid
                  AND slot.exportgroupid = :exportgroupid 
                  AND q.id = slot.questionid',
                array('exportquizid' => $this->exportquiz->id,
                      'exportgroupid' => $this->exportquiz->groupid),
                 'slot.slot');
    }

    /**
     * Fully load some or all of the questions for this exportquiz. You must call
     * {@link preload_questions()} first.
     *
     * @param array $questionids question ids of the questions to load. null for all.
     */
    public function load_questions($questionids = null) {
        if ($this->questions === null) {
            throw new coding_exception('You must call preload_questions before calling load_questions.');
        }
        if (is_null($questionids)) {
            $questionids = array_keys($this->questions);
        }
        $questionstoprocess = array();
        foreach ($questionids as $id) {
            if (array_key_exists($id, $this->questions)) {
                $questionstoprocess[$id] = $this->questions[$id];
            }
        }
        get_question_options($questionstoprocess);
    }

    /**
     * Get an instance of the {@link \mod_exportquiz\structure} class for this exportquiz.
     * @return \mod_exportquiz\structure describes the questions in the exportquiz.
     */
    public function get_structure() {
        return \mod_exportquiz\structure::create_for_exportquiz($this);
    }

    // Simple getters ==========================================================
    /** @return int the course id. */
    public function get_courseid() {
        return $this->course->id;
    }

    /** @return object the row of the course table. */
    public function get_course() {
        return $this->course;
    }

    /** @return int the exportquiz id. */
    public function get_exportquizid() {
        return $this->exportquiz->id;
    }

    /** @return int the exportquiz group id. */
    public function get_exportgroupid() {
        return $this->exportquiz->groupid;
    }

    /** @return object the row of the exportquiz table. */
    public function get_exportquiz() {
        return $this->exportquiz;
    }

    /** @return string the name of this exportquiz. */
    public function get_exportquiz_name() {
        return $this->exportquiz->name;
    }

    /** @return int the exportquiz navigation method. */
    public function get_navigation_method() {
        return $this->exportquiz->navmethod;
    }

    /** @return int the number of attempts allowed at this exportquiz (0 = infinite). */
    public function get_num_attempts_allowed() {
        return $this->exportquiz->attempts;
    }

    /** @return int the course_module id. */
    public function get_cmid() {
        return $this->cm->id;
    }

    /** @return object the course_module object. */
    public function get_cm() {
        return $this->cm;
    }

    /** @return object the module context for this exportquiz. */
    public function get_context() {
        return $this->context;
    }

    /**
     * @return bool wether the current user is someone who previews the exportquiz,
     * rather than attempting it.
     */
    public function is_preview_user() {
        if (is_null($this->ispreviewuser)) {
            $this->ispreviewuser = has_capability('mod/exportquiz:preview', $this->context);
        }
        return $this->ispreviewuser;
    }

    /**
     * @return whether any questions have been added to this exportquiz.
     */
    public function has_questions() {
        if ($this->questions === null) {
            $this->preload_questions();
        }
        return !empty($this->questions);
    }

    /**
     * @param int $id the question id.
     * @return object the question object with that id.
     */
    public function get_question($id) {
        return $this->questions[$id];
    }

    /**
     * @param array $questionids question ids of the questions to load. null for all.
     */
    public function get_questions($questionids = null) {
        if (is_null($questionids)) {
            $questionids = array_keys($this->questions);
        }
        $questions = array();
        foreach ($questionids as $id) {
            if (!array_key_exists($id, $this->questions)) {
                throw new moodle_exception('cannotstartmissingquestion', 'exportquiz', $this->view_url());
            }
            $questions[$id] = $this->questions[$id];
            $this->ensure_question_loaded($id);
        }
        return $questions;
    }

    /**
     * @param int $timenow the current time as a unix timestamp.
     * @return exportquiz_access_manager and instance of the exportquiz_access_manager class
     *      for this exportquiz at this time.
     */
    public function get_access_manager($timenow) {
        if (is_null($this->accessmanager)) {
            $this->accessmanager = new exportquiz_access_manager($this, $timenow,
                    has_capability('mod/exportquiz:ignoretimelimits', $this->context, null, false));
        }
        return $this->accessmanager;
    }

    /**
     * Wrapper round the has_capability funciton that automatically passes in the exportquiz context.
     */
    public function has_capability($capability, $userid = null, $doanything = true) {
        return has_capability($capability, $this->context, $userid, $doanything);
    }

    /**
     * Wrapper round the require_capability funciton that automatically passes in the exportquiz context.
     */
    public function require_capability($capability, $userid = null, $doanything = true) {
        return require_capability($capability, $this->context, $userid, $doanything);
    }

    // URLs related to this attempt ============================================
    /**
     * @return string the URL of this exportquiz's view page.
     */
    public function view_url() {
        global $CFG;
        return $CFG->wwwroot . '/mod/exportquiz/view.php?id=' . $this->cm->id;
    }

    /**
     * @return string the URL of this exportquiz's edit page.
     */
    public function edit_url() {
        global $CFG;
        return $CFG->wwwroot . '/mod/exportquiz/edit.php?cmid=' . $this->cm->id;
    }

    /**
     * @param int $attemptid the id of an attempt.
     * @param int $page optional page number to go to in the attempt.
     * @return string the URL of that attempt.
     */
    public function attempt_url($attemptid, $page = 0) {
        global $CFG;
        $url = $CFG->wwwroot . '/mod/exportquiz/attempt.php?attempt=' . $attemptid;
        if ($page) {
            $url .= '&page=' . $page;
        }
        return $url;
    }

    /**
     * @return string the URL of this exportquiz's edit page. Needs to be POSTed to with a cmid parameter.
     */
    public function start_attempt_url($page = 0) {
        $params = array('cmid' => $this->cm->id, 'sesskey' => sesskey());
        if ($page) {
            $params['page'] = $page;
        }
        return new moodle_url('/mod/exportquiz/startattempt.php', $params);
    }

    /**
     * @param int $attemptid the id of an attempt.
     * @return string the URL of the review of that attempt.
     */
    public function review_url($attemptid) {
        return new moodle_url('/mod/exportquiz/review.php', array('attempt' => $attemptid));
    }

    /**
     * @param int $attemptid the id of an attempt.
     * @return string the URL of the review of that attempt.
     */
    public function summary_url($attemptid) {
        return new moodle_url('/mod/exportquiz/summary.php', array('attempt' => $attemptid));
    }

    // Bits of content =========================================================

    /**
     * @param bool $unfinished whether there is currently an unfinished attempt active.
     * @return string if the exportquiz policies merit it, return a warning string to
     *      be displayed in a javascript alert on the start attempt button.
     */
    public function confirm_start_attempt_message($unfinished) {
        if ($unfinished) {
            return '';
        }

        if ($this->exportquiz->timelimit && $this->exportquiz->attempts) {
            return get_string('confirmstartattempttimelimit', 'exportquiz', $this->exportquiz->attempts);
        } else if ($this->exportquiz->timelimit) {
            return get_string('confirmstarttimelimit', 'exportquiz');
        } else if ($this->exportquiz->attempts) {
            return get_string('confirmstartattemptlimit', 'exportquiz', $this->exportquiz->attempts);
        }

        return '';
    }

    /**
     * If $reviewoptions->attempt is false, meaning that students can't review this
     * attempt at the moment, return an appropriate string explaining why.
     *
     * @param int $when One of the mod_exportquiz_display_options::DURING,
     *      IMMEDIATELY_AFTER, LATER_WHILE_OPEN or AFTER_CLOSE constants.
     * @param bool $short if true, return a shorter string.
     * @return string an appropraite message.
     */
    public function cannot_review_message($when, $short = false) {

        if ($short) {
            $langstrsuffix = 'short';
            $dateformat = get_string('strftimedatetimeshort', 'langconfig');
        } else {
            $langstrsuffix = '';
            $dateformat = '';
        }

        if ($when == mod_exportquiz_display_options::DURING ||
                $when == mod_exportquiz_display_options::IMMEDIATELY_AFTER) {
            return '';
        } else if ($when == mod_exportquiz_display_options::LATER_WHILE_OPEN && $this->exportquiz->timeclose &&
                $this->exportquiz->reviewattempt & mod_exportquiz_display_options::AFTER_CLOSE) {
            return get_string('noreviewuntil' . $langstrsuffix, 'exportquiz',
                    userdate($this->exportquiz->timeclose, $dateformat));
        } else {
            return get_string('noreview' . $langstrsuffix, 'exportquiz');
        }
    }

    /**
     * @param string $title the name of this particular exportquiz page.
     * @return array the data that needs to be sent to print_header_simple as the $navigation
     * parameter.
     */
    public function navigation($title) {
        global $PAGE;
        $PAGE->navbar->add($title);
        return '';
    }

    // Private methods =========================================================
    /**
     * Check that the definition of a particular question is loaded, and if not throw an exception.
     * @param $id a questionid.
     */
    protected function ensure_question_loaded($id) {
        if (isset($this->questions[$id]->_partiallyloaded)) {
            throw new moodle_exportquiz_exception($this, 'questionnotloaded', $id);
        }
    }
}
