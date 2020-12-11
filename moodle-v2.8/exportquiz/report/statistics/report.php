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
 * exportquiz statistics report class.
 *
 * @package   exportquiz_statistics
 * @author    Jose Manuel Ventura Martínez
 * @copyright 2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/exportquiz/report/statistics/statistics_form.php');
require_once($CFG->dirroot . '/mod/exportquiz/report/statistics/statistics_table.php');
require_once($CFG->dirroot . '/mod/exportquiz/report/statistics/statistics_question_table.php');
require_once($CFG->dirroot . '/mod/exportquiz/report/statistics/statistics_question_answer_table.php');
require_once($CFG->dirroot . '/mod/exportquiz/report/statistics/qstats.php');
require_once($CFG->dirroot . '/mod/exportquiz/report/statistics/responseanalysis.php');

/**
 * The exportquiz statistics report provides summary information about each question in
 * a exportquiz, compared to the whole exportquiz. It also provides a drill-down to more
 * detailed information about each question.
 *
 * @copyright     2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exportquiz_statistics_report extends exportquiz_default_report {
    /** @var integer Time after which statistics are automatically recomputed. */
    const TIME_TO_CACHE_STATS = 900; // 15 minutes.

    /** @var object instance of table class used for main questions stats table. */
    protected $table;

    /**
     * Display the report.
     */
    public function display($exportquiz, $cm, $course) {
        global $CFG, $DB, $OUTPUT, $PAGE;

        $this->context = context_module::instance($cm->id);

        // Work out the display options.
        $download = optional_param('download', '', PARAM_ALPHA);
        $everything = optional_param('everything', 0, PARAM_BOOL);
        $recalculate = optional_param('recalculate', 0, PARAM_BOOL);
        // A qid paramter indicates we should display the detailed analysis of a question and subquestions.
        $qid = optional_param('qid', 0, PARAM_INT);
        $questionid = optional_param('questionid', 0, PARAM_INT);
        // Determine statistics mode.
        $statmode = optional_param('statmode', 'statsoverview', PARAM_ALPHA);

        $pageoptions = array();
        $pageoptions['id'] = $cm->id;
        $pageoptions['mode'] = 'statistics';
        $pageoptions['statmode'] = $statmode;

        // When showing big tables add the JavaScript for the double scrollbar.
        if ($statmode == 'questionstats' || $statmode == 'questionandanswerstats') {
            $module = array(
                    'name'      => 'mod_exportquiz_statistics',
                    'fullpath'  => '/mod/exportquiz/report/statistics/doublescroll.js',
                    'requires'  => array(),
                    'strings'   => array(),
                    'async'     => false,
            );

            $PAGE->requires->jquery();
            $PAGE->requires->jquery_plugin('ui');
            $PAGE->requires->jquery_plugin('doubleScroll', 'mod_exportquiz');
            $PAGE->requires->js_init_call('exportquiz_statistics_init_doublescroll', null, false, $module);
        }

        if (!$groups = $DB->get_records('exportquiz_groups',
                array('exportquizid' => $exportquiz->id), 'number', '*', 0, $exportquiz->numgroups)) {
            print_error('nogroups', 'exportquiz', $CFG->wwwroot . '/course/view.php?id=' .
                $COURSE->id, $scannedpage->exportquizid);
        }

        // Determine groupid.
        $groupnumber = optional_param('exportgroup', -1, PARAM_INT);
        if ($groupnumber === -1 and !empty($SESSION->question_pagevars['groupnumber'])) {
            $groupnumber = $SESSION->question_pagevars['groupnumber'];
        }

        if ($groupnumber > 0) {
            $pageoptions['exportgroup'] = $groupnumber;
            $exportquiz->groupnumber = $groupnumber;
            $exportquiz->sumgrades = $DB->get_field('exportquiz_groups', 'sumgrades',
                    array('exportquizid' => $exportquiz->id, 'number' => $groupnumber));

            if ($exportgroup = exportquiz_get_group($exportquiz, $groupnumber)) {
                $exportquiz->groupid = $exportgroup->id;
                $groupquestions = exportquiz_get_group_question_ids($exportquiz);
                $exportquiz->questions = $groupquestions;
            } else {
                print_error('invalidgroupnumber', 'exportquiz');
            }
        } else {
            $exportquiz->groupid = 0;
            // The user wants to evaluate results from all exportquiz groups.
            // Compare the sumgrades of all exportquiz groups. First we put all sumgrades in an array.
            $sumgrades = array();
            foreach ($groups as $group) {
                $sumgrades[] = round($group->sumgrades, $exportquiz->decimalpoints);
            }
            // Now we remove duplicates.
            $sumgrades = array_unique($sumgrades);

            if (count($sumgrades) > 1) {
                // If the groups have different sumgrades, we can't pick one.
                $exportquiz->sumgrades = -1;
            } else if (count($sumgrades) == 1) {
                // If the groups all have the same sumgrades, we pick the first one.
                $exportquiz->sumgrades = $sumgrades[0];
            } else {
                // Pathological, there are no sumgrades, i.e. no groups...
                $exportquiz->sumgrades = 0;
            }

            // If no group has been chosen we simply take the questions from the question instances.
            $sql = "SELECT DISTINCT(questionid)
                      FROM {exportquiz_group_questions}
                     WHERE exportquizid = :exportquizid";
            
            $questionids = $DB->get_fieldset_sql($sql, array('exportquizid' => $exportquiz->id));
            $exportquiz->questions = $questionids;
        }

        // We warn the user if the different exportquiz groups have different sets of questions.
        $differentquestions = false;
        if ($exportquiz->groupid == 0 && count($groups) > 1 &&
                $this->groups_have_different_questions($exportquiz, $groups)) {
            $differentquestions = true;
        }

        $reporturl = new moodle_url('/mod/exportquiz/report.php', $pageoptions);

        $useallattempts = 0;

        // Find out current groups mode.
        $currentgroup = $this->get_current_group($cm, $course, $this->context);
        $nostudentsingroup = false; // True if a group is selected and there is no one in it.
        if (empty($currentgroup)) {
            $currentgroup = 0;
            $groupstudents = array();

        } else if ($currentgroup == self::NO_GROUPS_ALLOWED) {
            $groupstudents = array();
            $nostudentsingroup = true;

        } else {
            // All users who can attempt exportquizzes and who are in the currently selected group.
            $groupstudents = get_users_by_capability($this->context,
                    array('mod/exportquiz:reviewmyattempts', 'mod/exportquiz:attempt'),
                    '', '', '', '', $currentgroup, '', false);
            if (!$groupstudents) {
                $nostudentsingroup = true;
            }
        }

        // If recalculate was requested, handle that.
        if ($recalculate && confirm_sesskey()) {
            $this->clear_cached_data($exportquiz->id, $currentgroup, $useallattempts, $exportquiz->groupid);
            redirect($reporturl);
        }

        // Set up the main table.
        if ($statmode == 'statsoverview' || $statmode == 'questionstats') {
            $this->table = new exportquiz_statistics_table();
        } else {
            $this->table = new exportquiz_question_answer_statistics_table();
        }
        if ($everything) {
            $report = get_string('completestatsfilename', 'exportquiz_statistics');
        } else {
            $report = get_string('questionstatsfilename', 'exportquiz_statistics');
        }
        $courseshortname = format_string($course->shortname, true,
                array('context' => context_course::instance($course->id)));
        $filename = exportquiz_report_download_filename($report, $courseshortname, $exportquiz->name);
        $this->table->is_downloading($download, $filename,
                get_string('exportquizstructureanalysis', 'exportquiz_statistics'));

        // Load the questions.
        // NOTE: function is hacked to deliver question array with question IDs as keys, not the slot as before.
        $questions = exportquiz_report_get_significant_questions($exportquiz);

        $questionids = array_keys($questions);
        $fullquestions = question_load_questions($questionids);

        foreach ($questions as $quid => $question) {
            $q = $fullquestions[$quid];
            $q->maxmark = $question->maxmark;
            $q->number = $question->number;
            $questions[$quid] = $q;
        }

        // Get the data to be displayed.
        list($exportquizstats, $questions, $subquestions, $s) =
                $this->get_exportquiz_and_questions_stats($exportquiz, $currentgroup,
                        $nostudentsingroup, $useallattempts, $groupstudents, $questions);

        $exportquizinfo = $this->get_formatted_exportquiz_info_data($course, $cm, $exportquiz, $exportquizstats);

        // Set up the table, if there is data.
        if ($s) {
            $this->table->statistics_setup($exportquiz, $cm->id, $reporturl, $s);
        }
        // Print the page header stuff (if not downloading.
        if (!$this->table->is_downloading()) {
            $this->print_header_and_tabs($cm, $course, $exportquiz, $statmode, 'statistics');

            // Options for the help text popup_action.
            $options = array('width' => 650,
                    'height' => 400,
                    'resizable' => false,
                    'top' => 0,
                    'left' => 0,
                    'menubar' => false,
                    'location' => false,
                    'scrollbars' => true,
                    'toolbar' => false,
                    'status' => false,
                    'directories' => false,
                    'fullscreen' => false,
                    'dependent' => false);

            $helpfilename = 'statistics_help_';
            if (current_language() == 'de') {
                $helpfilename .= 'de.html';
            } else {
                $helpfilename .= 'en.html';
            }
            $url = new moodle_url($CFG->wwwroot . '/mod/exportquiz/report/statistics/help/' . $helpfilename);
            $pixicon = new pix_icon('help', get_string('statisticshelp', 'exportquiz_statistics'));
            $helpaction = $OUTPUT->action_icon($url, $pixicon, new popup_action('click', $url, 'help123', $options));

            echo $OUTPUT->box_start('linkbox');
            echo $OUTPUT->heading(format_string($exportquiz->name));
            echo $OUTPUT->heading(get_string($statmode . 'header', 'exportquiz_statistics') . $helpaction);
            echo $OUTPUT->box_end();

            if (!$questionid) {
                $this->print_exportquiz_group_selector($cm, $groups, $groupnumber, $pageoptions);
                if ($statmode == 'statsoverview') {
                    if ($exportquiz->sumgrades == -1 || $differentquestions) {
                        echo $OUTPUT->box_start();
                        echo $OUTPUT->notification(get_string('remarks', 'exportquiz_statistics') . ':', 'notifynote');
                    }
                    if ($exportquiz->sumgrades == -1) {
                        echo $OUTPUT->notification('- ' . get_string('differentsumgrades', 'exportquiz_statistics',
                                implode(', ', $sumgrades)), 'notifynote');
                    }
                    if ($differentquestions) {
                        echo $OUTPUT->notification('- ' . get_string('differentquestions', 'exportquiz_statistics',
                                implode(', ', $sumgrades)), 'notifynote');
                    }
                    if ($exportquiz->sumgrades == -1 || $differentquestions) {
                        echo $OUTPUT->box_end();
                    }
                }
            }

            if (groups_get_activity_groupmode($cm)) {
                groups_print_activity_menu($cm, $reporturl->out());
                if ($currentgroup && !$groupstudents) {
                    echo $OUTPUT->notification(get_string('nostudentsingroup', 'exportquiz_statistics'));
                }
            }

            if (!$exportquiz->questions) {
                echo exportquiz_no_questions_message($exportquiz, $cm, $this->context);
            } else if (!$this->table->is_downloading() && $s == 0) {
                echo $OUTPUT->box_start('linkbox');
                echo $OUTPUT->notification(get_string('noattempts', 'exportquiz'), 'notifyproblem');
                echo $OUTPUT->box_end();
                echo '<br/>';
            }
        }

        if ($everything) { // Implies is downloading.
            // Overall report, then the analysis of each question.
            if ($statmode == 'statsoverview') {
                $this->download_exportquiz_info_table($exportquizinfo);
            } else if ($statmode == 'questionstats') {

                if ($s) {
                    $this->output_exportquiz_structure_analysis_table($s, $questions, $subquestions);

                    if ($this->table->is_downloading() == 'xhtml') {
                        $this->output_statistics_graph($exportquizstats->id, $s);
                    }

                    foreach ($questions as $question) {
                        if (question_bank::get_qtype(
                                $question->qtype, false)->can_analyse_responses()) {
                            $this->output_individual_question_response_analysis(
                                    $question, $reporturl, $exportquizstats);

                        } else if (!empty($question->_stats->subquestions)) {
                            $subitemstodisplay = explode(',', $question->_stats->subquestions);
                            foreach ($subitemstodisplay as $subitemid) {
                                $this->output_individual_question_response_analysis(
                                        $subquestions[$subitemid], $reporturl, $exportquizstats);
                            }
                        }
                    }
                }
            } else if ($statmode == 'questionandanswerstats') {
                if ($s) {
                    $this->output_exportquiz_structure_analysis_table($s, $questions, $subquestions);

                    if ($this->table->is_downloading() == 'xhtml') {
                        $this->output_statistics_graph($exportquizstats->id, $s);
                    }

                    foreach ($questions as $question) {
                        if (question_bank::get_qtype(
                                $question->qtype, false)->can_analyse_responses()) {
                            $this->output_individual_question_response_analysis(
                                    $question, $reporturl, $exportquizstats);

                        } else if (!empty($question->_stats->subquestions)) {
                            $subitemstodisplay = explode(',', $question->_stats->subquestions);
                            foreach ($subitemstodisplay as $subitemid) {
                                $this->output_individual_question_response_analysis(
                                        $subquestions[$subitemid], $reporturl, $exportquizstats);
                            }
                        }
                    }
                }
            }

            $this->table->export_class_instance()->finish_document();

        } else if ($questionid) {
            // Report on an individual question indexed by position.
            if (!isset($questions[$questionid])) {
                print_error('questiondoesnotexist', 'question');
            }

            $this->output_individual_question_data($exportquiz, $questions[$questionid]);
            $this->output_individual_question_response_analysis(
                    $questions[$questionid], $reporturl, $exportquizstats);

            // Back to overview link.
            echo $OUTPUT->box('<a href="' . $reporturl->out() . '">' .
                    get_string('backtoquestionsandanswers', 'exportquiz_statistics') . '</a>',
                    'backtomainstats boxaligncenter backlinkbox generalbox boxwidthnormal mdl-align');

        } else if ($qid) {
            // Report on an individual sub-question indexed questionid.
            if (!isset($subquestions[$qid])) {
                print_error('questiondoesnotexist', 'question');
            }

            $this->output_individual_question_data($exportquiz, $subquestions[$qid]);
            $this->output_individual_question_response_analysis(
                    $subquestions[$qid], $reporturl, $exportquizstats);

            // Back to overview link.
            echo $OUTPUT->box('<a href="' . $reporturl->out() . '">' .
                    get_string('backtoquestionsandanswers', 'exportquiz_statistics') . '</a>',
                    'boxaligncenter backlinkbox generalbox boxwidthnormal mdl-align');

        } else if ($this->table->is_downloading()) {
            // Downloading overview report.
            $this->download_exportquiz_info_table($exportquizinfo);
            if ($statmode == 'questionstats') {
                $this->output_exportquiz_structure_analysis_table($s, $questions, $subquestions);
            } else if ($statmode == 'questionandanswerstats') {
                $this->output_exportquiz_question_answer_table($s, $questions, $subquestions, $exportquizstats);
            }
            $this->table->finish_output();

        } else {
            // On-screen display of overview report.
            echo $this->output_caching_info($exportquizstats, $exportquiz->id, $currentgroup,
                    $groupstudents, $useallattempts, $reporturl, $exportquiz->groupid);

            if ($statmode == 'statsoverview') {
                echo $this->everything_download_options();
                echo '<br/><center>';
                echo $this->output_exportquiz_info_table($exportquizinfo);
                echo '</center>';
            } else if ($statmode == 'questionstats') {
                if ($s) {
                    echo '<br/>';
                    $this->output_exportquiz_structure_analysis_table($s, $questions, $subquestions);
                }
            } else if ($statmode == 'questionandanswerstats') {
                if ($s) {
                    echo '<br/>';
                    $this->output_exportquiz_question_answer_table($s, $questions, $subquestions, $exportquizstats);
                }
            }
        }
    }


    /**
     * Checks whether the different exportquiz groups have different sets of questions (order is irrelevant).
     *
     * @param unknown_type $exportquiz
     * @param unknown_type $groups
     * @return boolean
     */
    private function groups_have_different_questions($exportquiz, $groups) {
        $agroup = array_pop($groups);
        $aquestions = exportquiz_get_group_question_ids($exportquiz, $agroup->id);

        // Compare all other groups to the first one.
        foreach ($groups as $bgroup) {
            $bquestions = exportquiz_get_group_question_ids($exportquiz, $bgroup->id);
            // Check which questions are in group A but not in group B.
            $diff1 = array_diff($aquestions, $bquestions);
            // Check which questions are in group B but not in group A.
            $diff2 = array_diff($bquestions, $aquestions);
            // Return true if there are any differences.
            if (!empty($diff1) || !empty($diff2)) {
                return true;
            }
        }
        return false;
    }

    /**
     *
     * @param unknown_type $cm The course module, needed to construct the base URL
     * @param unknown_type $groups The group objects as read from the database
     * @param unknown_type $groupnumber The currently chosen group number
     */
    private function print_exportquiz_group_selector($cm, $groups, $groupnumber, $pageoptions) {
        global $CFG, $OUTPUT;

        $options = array();
        $letterstr = 'ABCDEFGH';
        $prefix = get_string('statisticsforgroup', 'exportquiz_statistics');
        foreach ($groups as $group) {
            $options[$group->number] = $prefix . ' ' . $letterstr[$group->number - 1];
        }
        $urlparams = array('id' => $cm->id, 'mode' => 'statistics', 'statmode' => $pageoptions['statmode']);
        if (key_exists('exportgroup', $pageoptions)) {
            $urlparams['exportgroup'] = $pageoptions['exportgroup'];
        }

        $url = new moodle_url($CFG->wwwroot . '/mod/exportquiz/report.php', $urlparams);
        echo $OUTPUT->single_select($url, 'exportgroup', $options, $groupnumber,
                array(0 => get_string('allgroups', 'exportquiz_statistics')));
    }


    /**
     * Display the statistical and introductory information about a question.
     * Only called when not downloading.
     * @param object $exportquiz the exportquiz settings.
     * @param object $question the question to report on.
     * @param moodle_url $reporturl the URL to resisplay this report.
     * @param object $exportquizstats Holds the exportquiz statistics.
     */
    protected function output_individual_question_data($exportquiz, $question) {
        global $OUTPUT;

        // On-screen display. Show a summary of the question's place in the exportquiz,
        // and the question statistics.
        $datumfromtable = $this->table->format_row($question);

        echo '<strong>';
        echo $question->name . '&nbsp;&nbsp;&nbsp;' . $datumfromtable['actions'] . '&nbsp;&nbsp;&nbsp;';
        echo '</strong>';
        echo $datumfromtable['icon'] . '&nbsp;' .
                question_bank::get_qtype($question->qtype, false)->menu_name() . '&nbsp;' .
                $datumfromtable['icon'] . '<br/>';
        echo $this->render_question_text_plain($question);

        // Set up the question statistics table.
        $questionstatstable = new html_table();
        $questionstatstable->id = 'questionstatstable';
        $questionstatstable->align = array('left', 'right');
        $questionstatstable->attributes['class'] = 'generaltable titlesleft';

        unset($datumfromtable['number']);
        unset($datumfromtable['icon']);
        $actions = $datumfromtable['actions'];
        unset($datumfromtable['actions']);
        unset($datumfromtable['name']);
        unset($datumfromtable['response']);
        unset($datumfromtable['frequency']);
        unset($datumfromtable['count']);
        unset($datumfromtable['fraction']);

        $labels = array(
            's' => get_string('attempts', 'exportquiz_statistics'),
            'facility' => get_string('facility', 'exportquiz_statistics'),
            'sd' => get_string('standarddeviationq', 'exportquiz_statistics'),
            'random_guess_score' => get_string('random_guess_score', 'exportquiz_statistics'),
            'intended_weight' => get_string('intended_weight', 'exportquiz_statistics'),
            'effective_weight' => get_string('effective_weight', 'exportquiz_statistics'),
            'discrimination_index' => get_string('discrimination_index', 'exportquiz_statistics'),
            'discriminative_efficiency' => get_string('discriminative_efficiency', 'exportquiz_statistics'),
            'correct' => get_string('correct', 'exportquiz_statistics'),
            'partially' => get_string('partially', 'exportquiz_statistics'),
            'wrong' => get_string('wrong', 'exportquiz_statistics'),
        );
        foreach ($datumfromtable as $item => $value) {
            $questionstatstable->data[] = array($labels[$item], $value);
        }

        // Display the various bits.
        echo '<br/>';
        echo '<center>';
        echo html_writer::table($questionstatstable);
        echo '</center>';
    }

    /**
     * @param object $question question data.
     * @return string HTML of question text, ready for display.
     */
    protected function render_question_text($question) {
        global $OUTPUT;

        $text = question_rewrite_question_preview_urls($question->questiontext, $question->id,
                $question->contextid, 'question', 'questiontext', $question->id,
                $this->context->id, 'quiz_statistics');

        return $OUTPUT->box(format_text($text, $question->questiontextformat,
                array('noclean' => true, 'para' => false, 'overflowdiv' => true)),
                'questiontext boxaligncenter generalbox boxwidthnormal mdl-align');
    }

    /**
     * @param object $question question data.
     * @return string HTML of question text, ready for display.
     */
    protected function render_question_text_plain($question, $showimages = true) {
        global $OUTPUT;

        if ($showimages) {
            $text = question_rewrite_question_preview_urls($question->questiontext, $question->id,
                    $question->contextid, 'question', 'questiontext', $question->id,
                    $this->context->id, 'quiz_statistics');
        } else {
            $text = $question->questiontext;
        }
        $questiontext = question_utils::to_plain_text($text, $question->questiontextformat,
                array('noclean' => true, 'para' => false, 'overflowdiv' => true));
        return '&nbsp;&nbsp;&nbsp;' . $questiontext;
    }


    /**
     * Display the response analysis for a question.
     * @param object $question the question to report on.
     * @param moodle_url $reporturl the URL to resisplay this report.
     * @param object $exportquizstats Holds the exportquiz statistics.
     */
    protected function output_individual_question_response_analysis($question,
            $reporturl, $exportquizstats) {
        global $OUTPUT;

        if (!question_bank::get_qtype($question->qtype, false)->can_analyse_responses()) {
            return;
        }

        $qtable = new exportquiz_statistics_question_table($question->id);
        $qtable->set_attribute('id', 'statisticsquestiontable');

        $exportclass = $this->table->export_class_instance();
        $qtable->export_class_instance($exportclass);
        if (!$this->table->is_downloading()) {
            // Output an appropriate title.
            echo $OUTPUT->heading(get_string('analysisofresponses', 'exportquiz_statistics'));
            echo $this->render_question_text_plain($question, false);
            echo '<br/>';

        } else {
            // Work out an appropriate title.
            $questiontabletitle = '"' . $question->name . '"';
            if (!empty($question->number)) {
                $questiontabletitle = '(' . $question->number . ') ' . $questiontabletitle;
            }
            if ($this->table->is_downloading() == 'xhtml') {
                $questiontabletitle = get_string('analysisofresponsesfor',
                        'exportquiz_statistics', $questiontabletitle);
            }

            // Set up the table.
            $exportclass->start_table($questiontabletitle);

            if ($this->table->is_downloading() == 'xhtml') {
                echo $this->render_question_text($question);
            }
        }

        $responesstats = new exportquiz_statistics_response_analyser($question);
        $responesstats->load_cached($exportquizstats->id);

        $qtable->question_setup($reporturl, $question, $responesstats);
        if ($this->table->is_downloading()) {
            $exportclass->output_headers($qtable->headers);
        }
        $letterstr = 'abcdefghijklmnopqrstuvwxyz';
        $counter = 0;
        foreach ($responesstats->responseclasses as $partid => $partclasses) {
            $rowdata = new stdClass();
            foreach ($partclasses as $responseclassid => $responseclass) {
                $rowdata->responseclass = $responseclass->responseclass;

                $responsesdata = $responesstats->responses[$partid][$responseclassid];
                if (empty($responsesdata)) {
                    if ($responseclass->responseclass != get_string('noresponse', 'question')) {
                        $rowdata->part = $letterstr[$counter++] . ')';
                    } else {
                        $rowdata->part = '';
                    }

                    if (!array_key_exists('responseclass', $qtable->columns)) {
                        $rowdata->response = $responseclass->responseclass;
                    } else {
                        $rowdata->response = '';
                    }
                    $rowdata->fraction = $responseclass->fraction;
                    $rowdata->count = 0;
                    $classname = '';
                    if ($rowdata->fraction > 0) {
                        $classname = 'greenrow';
                    } else if ($rowdata->fraction < 0) {
                        $classname = 'redrow';
                    }
                    $qtable->add_data_keyed($qtable->format_row($rowdata), $classname);
                    continue;
                }

                foreach ($responsesdata as $response => $data) {
                    if ($response != get_string('noresponse', 'question')) {
                        $rowdata->part = $letterstr[$counter++] . ')';
                    } else {
                        $rowdata->part = '';
                    }
                    $rowdata->response = $response;
                    $rowdata->fraction = $data->fraction;
                    $rowdata->count = $data->count;
                    $classname = '';
                    if ($rowdata->fraction > 0) {
                        $classname = 'greenrow';
                    } else if ($rowdata->fraction < 0) {
                        $classname = 'redrow';
                    }
                    $qtable->add_data_keyed($qtable->format_row($rowdata), $classname);
                    break;
                }
            }
        }

        $qtable->finish_output(!$this->table->is_downloading());
    }

    /**
     * Output the table that lists all the questions in the exportquiz with their statistics.
     * @param int $s number of attempts.
     * @param array $questions the questions in the exportquiz.
     * @param array $subquestions the subquestions of any random questions.
     */
    protected function output_exportquiz_structure_analysis_table($s, $questions, $subquestions) {
        if (!$s) {
            return;
        }

        foreach ($questions as $question) {
            // Output the data for this question.
            $this->table->add_data_keyed($this->table->format_row($question));

            if (empty($question->_stats->subquestions)) {
                continue;
            }

            // And its subquestions, if it has any.
            $subitemstodisplay = explode(',', $question->_stats->subquestions);
            foreach ($subitemstodisplay as $subitemid) {
                $subquestions[$subitemid]->maxmark = $question->maxmark;
                $this->table->add_data_keyed($this->table->format_row($subquestions[$subitemid]));
            }
        }
        $this->table->finish_output(!$this->table->is_downloading());
    }

    protected function get_formatted_exportquiz_info_data($course, $cm, $exportquiz, $exportquizstats) {
        // You can edit this array to control which statistics are displayed.
        $todisplay = array( // Comment in 'firstattemptscount' => 'number'.
                    'allattemptscount' => 'number',
                    'maxgrade' => 'number_format',
                    'bestgrade' => 'scale_to_maxgrade',
                    'worstgrade' => 'scale_to_maxgrade',
                    'allattemptsavg' => 'scale_to_maxgrade',
                    'median' => 'scale_to_maxgrade',
                    'standarddeviation' => 'scale_to_maxgrade', // The 'summarks_as_percentage'.
                    'skewness' => 'number_format',
                    'kurtosis' => 'number_format',
                    'cic' => 'percent_to_number_format',
                    'errorratio' => 'number_format_percent',
                    'standarderror' => 'scale_to_maxgrade');

        if ($exportquiz->sumgrades > 0) {
            $exportquizstats->sumgrades = $exportquiz->sumgrades;
        } else if ($exportquiz->sumgrades == -1) {
            $exportquizstats->sumgrades = '';
            $exportquizstats->bestgrade = '';
            $exportquizstats->worstgrade = '';
            $exportquizstats->allattemptsavg = '';
            $exportquizstats->median = '';
            $exportquizstats->standarddeviation = '';
        }
        $exportquizstats->maxgrade = $exportquiz->grade;

        // General information about the exportquiz.
        $exportquizinfo = array();
        $exportquizinfo[get_string('exportquizname', 'exportquiz_statistics')] = format_string($exportquiz->name);

        if ($cm->idnumber) {
            $exportquizinfo[get_string('idnumbermod')] = $cm->idnumber;
        }
        if ($exportquiz->timeopen) {
            $exportquizinfo[get_string('reviewopens', 'exportquiz')] = userdate($exportquiz->timeopen);
        }
        if ($exportquiz->timeclose) {
            $exportquizinfo[get_string('reviewcloses', 'exportquiz')] = userdate($exportquiz->timeclose);
        }
        if ($exportquiz->timeopen && $exportquiz->timeclose) {
            $exportquizinfo[get_string('duration', 'exportquiz_statistics')] =
                    format_time($exportquiz->timeclose - $exportquiz->timeopen);
        }
        // The statistics.
        foreach ($todisplay as $property => $format) {
            if (!isset($exportquizstats->$property) || empty($format)) {
                continue;
            }
            $value = $exportquizstats->$property;

            switch ($format) {
                case 'summarks_as_percentage':
                    $formattedvalue = exportquiz_report_scale_summarks_as_percentage($value, $exportquiz);
                    break;
                case 'scale_to_maxgrade':
                    $formattedvalue = exportquiz_report_scale_grade($value, $exportquiz);
                    break;
                case 'number_format_percent':
                    $formattedvalue = exportquiz_format_grade($exportquiz, $value) . '%';
                    break;
                case 'number_format':
                    // 2 extra decimal places, since not a percentage,
                    // and we want the same number of sig figs.???
                    $formattedvalue = format_float($value, $exportquiz->decimalpoints);
                    break;
                case 'percent_to_number_format':
                    $formattedvalue = format_float($value / 100.00, $exportquiz->decimalpoints);
                    break;
                case 'number':
                    $formattedvalue = $value + 0;
                    break;
                default:
                    $formattedvalue = $value;
            }

            $exportquizinfo[get_string($property, 'exportquiz_statistics',
                    $this->using_attempts_string(!empty($exportquizstats->allattempts)))] =
                    $formattedvalue;
        }

        return $exportquizinfo;
    }

    /**
     * Output the table that lists all the questions in the exportquiz with their statistics.
     * @param int $s number of attempts.
     * @param array $questions the questions in the exportquiz.
     * @param array $subquestions the subquestions of any random questions.
     */
    protected function output_exportquiz_question_answer_table($s, $questions, $subquestions, $exportquizstats) {
        if (!$s) {
            return;
        }

        foreach ($questions as $question) {
            // Output the data for this question.
            $question->actions = 'actions';
            $this->table->add_data_keyed($this->table->format_row($question));
            $this->output_question_answers($question, $exportquizstats);
        }
        $this->table->finish_output(!$this->table->is_downloading());
    }

    /**
     * Output a question and its answers in one table in a sequence of rows.
     *
     * @param object $question
     */
    protected function output_question_answers($question, $exportquizstats) {

        $exportclass = $this->table->export_class_instance();
        $responesstats = new exportquiz_statistics_response_analyser($question);
        $responesstats->load_cached($exportquizstats->id);
        $this->table->set_questiondata($question);

        $letterstr = 'abcdefghijklmnopqrstuvwxyz';
        $counter = 0;
        $counter2 = 0;

        foreach ($responesstats->responseclasses as $partid => $partclasses) {
            $rowdata = new stdclass();
            $partcounter = 0;
            foreach ($partclasses as $responseclassid => $responseclass) {
                $rowdata->responseclass = $responseclass->responseclass;
                $responsesdata = $responesstats->responses[$partid][$responseclassid];

                if (empty($responsesdata)) {
                    $rowdata->part = $letterstr[$counter++] . ')';
                    $rowdata->response = $responseclass->responseclass;
                    $rowdata->response = str_ireplace(array('<br />', '<br/>', '<br>', "\r\n"),
                            array('', '', '', ''), $rowdata->response);
                    $rowdata->fraction = $responseclass->fraction;
                    $rowdata->count = 0;
                    $classname = '';
                    if ($rowdata->fraction > 0) {
                        $classname = 'greenrow';
                    } else if ($rowdata->fraction < 0) {
                        $classname = 'redrow';
                    }
                    if ($counter2 == 0 && $partcounter == 0) {
                        if ($this->table->is_downloading()) {
                            $rowdata->name = format_text(strip_tags($question->questiontext), FORMAT_PLAIN);
                            $rowdata->name = str_ireplace(array('<br />', '<br/>', '<br>', "\r\n"),
                                    array('', '', '', ''), $rowdata->name);
                        } else {
                            $rowdata->name = format_text(html_to_text($question->questiontext));
                        }
                    } else {
                        $rowdata->name = '';
                    }

                    $rowdata->s = '';
                    $rowdata->facility = '';
                    $rowdata->sd = '';
                    $rowdata->intended_weight = '';
                    $rowdata->effective_weight = '';
                    $rowdata->discrimination_index = '';
                    $this->table->add_data_keyed($this->table->format_row($rowdata), $classname);
                    $partcounter++;
                    continue;
                } else {
                    foreach ($responsesdata as $response => $data) {
                        $rowdata->response = $response;
                        $rowdata->response = str_ireplace(array('<br />', '<br/>', '<br>', "\r\n"),
                                array('', '', '', ''), $rowdata->response);
                        $rowdata->fraction = $data->fraction;
                        $rowdata->count = $data->count;
                        $rowdata->part = $letterstr[$counter++] . ')';

                        $classname = '';
                        if ($rowdata->fraction > 0) {
                            $classname = 'greenrow';
                        } else if ($rowdata->fraction < 0) {
                            $classname = 'redrow';
                        }

                        if ($counter2 == 0 && $partcounter == 0) {
                            if ($this->table->is_downloading()) {
                                $rowdata->name = format_text(strip_tags($question->questiontext), FORMAT_PLAIN);
                                $rowdata->name = str_ireplace(array('<br />', '<br/>', '<br>', "\r\n"),
                                        array('', '', '', ''), $rowdata->name);
                            } else {
                                $rowdata->name = format_text(html_to_text($question->questiontext));
                            }
                        } else {
                            $rowdata->name = '';
                        }
                        $rowdata->s = '';
                        $rowdata->facility = '';
                        $rowdata->sd = '';
                        $rowdata->intended_weight = '';
                        $rowdata->effective_weight = '';
                        $rowdata->discrimination_index = '';
                        $this->table->add_data_keyed($this->table->format_row($rowdata), $classname);
                        $partcounter++;
                        break; // We want to display every response only once.
                    }
                }
            }
            $counter2++;
        }
    }

    /**
     * Output the table of overall exportquiz statistics.
     * @param array $exportquizinfo as returned by {@link get_formatted_exportquiz_info_data()}.
     * @return string the HTML.
     */
    protected function output_exportquiz_info_table($exportquizinfo) {
        $exportquizinfotable = new html_table();
        $exportquizinfotable->id = 'statsoverviewtable';
        $exportquizinfotable->align = array('left', 'right');
        $exportquizinfotable->attributes['class'] = 'generaltable titlesleft';
        $exportquizinfotable->data = array();

        foreach ($exportquizinfo as $heading => $value) {
             $exportquizinfotable->data[] = array($heading, $value);
        }

        return html_writer::table($exportquizinfotable);
    }

    /**
     * Download the table of overall exportquiz statistics.
     * @param array $exportquizinfo as returned by {@link get_formatted_exportquiz_info_data()}.
     */
    protected function download_exportquiz_info_table($exportquizinfo) {
        global $OUTPUT;

        // XHTML download is a special case.
        if ($this->table->is_downloading() == 'xhtml') {
            echo $OUTPUT->heading(get_string('exportquizinformation', 'exportquiz_statistics'));
            echo $this->output_exportquiz_info_table($exportquizinfo);
            return;
        }

        // Reformat the data ready for output.
        $headers = array();
        $row = array();
        foreach ($exportquizinfo as $heading => $value) {
            $headers[] = $heading;
            if (is_double($value)) {
                $row[] = format_float($value, 2);
            } else {
                $row[] = $value;
            }
        }

        // Do the output.
        $exportclass = $this->table->export_class_instance();
        $exportclass->start_table(get_string('exportquizinformation', 'exportquiz_statistics'));
        $exportclass->output_headers($headers);
        $exportclass->add_data($row);
        $exportclass->finish_table();
    }

    /**
     * Output the HTML needed to show the statistics graph.
     * @param int $exportquizstatsid the id of the statistics to show in the graph.
     */
    protected function output_statistics_graph($exportquizstatsid, $s) {
        global $PAGE;

        if ($s == 0) {
            return;
        }

        $output = $PAGE->get_renderer('mod_exportquiz');
        $imageurl = new moodle_url('/mod/exportquiz/report/statistics/statistics_graph.php',
                array('id' => $exportquizstatsid));
        $graphname = get_string('statisticsreportgraph', 'exportquiz_statistics');
        echo $output->graph($imageurl, $graphname);
    }

    /**
     * Return the stats data for when there are no stats to show.
     *
     * @param array $questions question definitions.
     * @param int $firstattemptscount number of first attempts (optional).
     * @param int $firstattemptscount total number of attempts (optional).
     * @return array with three elements:
     *      - integer $s Number of attempts included in the stats (0).
     *      - array $exportquizstats The statistics for overall attempt scores.
     *      - array $qstats The statistics for each question.
     */
    protected function get_emtpy_stats($questions, $firstattemptscount = 0,
            $allattemptscount = 0) {
        $exportquizstats = new stdClass();
        $exportquizstats->firstattemptscount = $firstattemptscount;
        $exportquizstats->allattemptscount = $allattemptscount;

        $qstats = new stdClass();
        $qstats->questions = $questions;
        $qstats->subquestions = array();
        $qstats->responses = array();

        return array(0, $exportquizstats, false);
    }

    /**
     * Compute the exportquiz statistics.
     *
     * @param object $exportquizid the exportquiz id.
     * @param int $currentgroup the current group. 0 for none.
     * @param bool $nostudentsingroup true if there a no students.
     * @param bool $useallattempts use all attempts, or just first attempts.
     * @param array $groupstudents students in this group.
     * @param array $questions question definitions.
     * @return array with three elements:
     *      - integer $s Number of attempts included in the stats.
     *      - array $exportquizstats The statistics for overall attempt scores.
     *      - array $qstats The statistics for each question.
     */
    protected function compute_stats($exportquizid, $currentgroup, $nostudentsingroup,
            $useallattempts, $groupstudents, $questions, $exportgroupid) {
        global $DB;

        // Calculating MEAN of marks for all attempts by students
        // http://docs.moodle.org/dev/exportquiz_item_analysis_calculations_in_practise
        //     #Calculating_MEAN_of_grades_for_all_attempts_by_students.
        if ($nostudentsingroup) {
            return $this->get_emtpy_stats($questions);
        }

        list($fromqa, $whereqa, $qaparams) = exportquiz_statistics_attempts_sql(
                $exportquizid, $currentgroup, $groupstudents, true, false, $exportgroupid);

        $attempttotals = $DB->get_records_sql("
                SELECT
                    1,
                    COUNT(1) AS countrecs,
                    SUM(sumgrades) AS total
                FROM $fromqa
                WHERE $whereqa
                GROUP BY 1", $qaparams);
        // GROUP BY CASE WHEN attempt = 1 THEN 1 ELSE 0 END AS isfirst.

        if (!$attempttotals) {
            return $this->get_emtpy_stats($questions);
        }

        if (isset($attempttotals[1])) {
            $firstattempts = $attempttotals[1];
            $firstattempts->average = $firstattempts->total / $firstattempts->countrecs;
        } else {
            $firstattempts = new stdClass();
            $firstattempts->countrecs = 0;
            $firstattempts->total = 0;
            $firstattempts->average = null;
        }

        $allattempts = new stdClass();
        if (isset($attempttotals[0])) {
            $allattempts->countrecs = $firstattempts->countrecs + $attempttotals[0]->countrecs;
            $allattempts->total = $firstattempts->total + $attempttotals[0]->total;
        } else {
            $allattempts->countrecs = $firstattempts->countrecs;
            $allattempts->total = $firstattempts->total;
        }

        if ($useallattempts) {
            $usingattempts = $allattempts;
            $usingattempts->sql = '';
        } else {
            $usingattempts = $firstattempts;
            $usingattempts->sql = 'AND exportquiza.attempt = 1 ';
        }
        $s = $usingattempts->countrecs;
        if ($s == 0) {
            return $this->get_emtpy_stats($questions, $firstattempts->countrecs,
                    $allattempts->countrecs);
        }
        $summarksavg = $usingattempts->total / $usingattempts->countrecs;

        $exportquizstats = new stdClass();
        $exportquizstats->allattempts = $useallattempts;
        $exportquizstats->firstattemptscount = $firstattempts->countrecs;
        $exportquizstats->allattemptscount = $allattempts->countrecs;
        $exportquizstats->firstattemptsavg = $firstattempts->average;
        $exportquizstats->allattemptsavg = $allattempts->total / $allattempts->countrecs;

        $marks = $DB->get_fieldset_sql("
                SELECT sumgrades
                FROM $fromqa
                WHERE $whereqa", $qaparams);

        // Also remember the best and worst grade.
        $exportquizstats->bestgrade = max($marks);
        $exportquizstats->worstgrade = min($marks);

        // Recalculate sql again this time possibly including test for first attempt.
        list($fromqa, $whereqa, $qaparams) = exportquiz_statistics_attempts_sql(
                $exportquizid, $currentgroup, $groupstudents, $useallattempts, false, $exportgroupid);

        // Median ...
        if ($s % 2 == 0) {
            // An even number of attempts.
            $limitoffset = $s / 2 - 1;
            $limit = 2;
        } else {
            $limitoffset = floor($s / 2);
            $limit = 1;
        }
        $sql = "SELECT id, sumgrades
                  FROM $fromqa
                 WHERE $whereqa
              ORDER BY sumgrades";

        $medianmarks = $DB->get_records_sql_menu($sql, $qaparams, $limitoffset, $limit);

        $exportquizstats->median = array_sum($medianmarks) / count($medianmarks);
        if ($s > 1) {
            // Fetch the sum of squared, cubed and power 4d
            // differences between marks and mean mark.
            $mean = $usingattempts->total / $s;
            $sql = "SELECT
                    SUM(POWER((exportquiza.sumgrades - $mean), 2)) AS power2,
                    SUM(POWER((exportquiza.sumgrades - $mean), 3)) AS power3,
                    SUM(POWER((exportquiza.sumgrades - $mean), 4)) AS power4
                    FROM $fromqa
                    WHERE $whereqa";
            $params = array('mean1' => $mean, 'mean2' => $mean, 'mean3' => $mean) + $qaparams;

            $powers = $DB->get_record_sql($sql, $params, MUST_EXIST);

            // Standard_Deviation:
            // see http://docs.moodle.org/dev/exportquiz_item_analysis_calculations_in_practise
            //         #Standard_Deviation.

            $exportquizstats->standarddeviation = sqrt($powers->power2 / ($s - 1));

            // Skewness.
            if ($s > 2) {
                // See http://docs.moodle.org/dev/
                //      exportquiz_item_analysis_calculations_in_practise#Skewness_and_Kurtosis.
                $m2 = $powers->power2 / $s;
                $m3 = $powers->power3 / $s;
                $m4 = $powers->power4 / $s;

                $k2 = $s * $m2 / ($s - 1);
                $k3 = $s * $s * $m3 / (($s - 1) * ($s - 2));
                if ($k2) {
                    $exportquizstats->skewness = $k3 / (pow($k2, 3 / 2));
                }
            }

            // Kurtosis.
            if ($s > 3) {
                $k4 = $s * $s * ((($s + 1) * $m4) - (3 * ($s - 1) * $m2 * $m2)) / (($s - 1) * ($s - 2) * ($s - 3));
                if ($k2) {
                    $exportquizstats->kurtosis = $k4 / ($k2 * $k2);
                }
            }
        }
        $qstats = new exportquiz_statistics_question_stats($questions, $s, $summarksavg);
        $qstats->load_step_data($exportquizid, $currentgroup, $groupstudents, $useallattempts, $exportgroupid);
        $qstats->compute_statistics();

        if ($s > 1) {
            $p = count($qstats->questions); // Number of positions.
            if ($p > 1 && isset($k2)) {
                if ($k2 == 0) {
                    $exportquizstats->cic = null;
                    $exportquizstats->errorratio = null;
                    $exportquizstats->standarderror = null;
                } else {
                    $exportquizstats->cic = (100 * $p / ($p - 1)) * (1 - ($qstats->get_sum_of_mark_variance()) / $k2);
                    $exportquizstats->errorratio = 100 * sqrt(1 - ($exportquizstats->cic / 100));
                    $exportquizstats->standarderror = $exportquizstats->errorratio * $exportquizstats->standarddeviation / 100;
                }
            }
        }

        return array($s, $exportquizstats, $qstats);
    }

    /**
     * Load the cached statistics from the database.
     *
     * @param object $exportquiz the exportquiz settings
     * @param int $currentgroup the current group. 0 for none.
     * @param bool $nostudentsingroup true if there a no students.
     * @param bool $useallattempts use all attempts, or just first attempts.
     * @param array $groupstudents students in this group.
     * @param array $questions question definitions.
     * @return array with 4 elements:
     *     - $exportquizstats The statistics for overall attempt scores.
     *     - $questions The questions, with an additional _stats field.
     *     - $subquestions The subquestions, if any, with an additional _stats field.
     *     - $s Number of attempts included in the stats.
     * If there is no cached data in the database, returns an array of four nulls.
     */
    protected function try_loading_cached_stats($exportquiz, $currentgroup,
            $nostudentsingroup, $useallattempts, $groupstudents, $questions) {
        global $DB;

        $timemodified = time() - self::TIME_TO_CACHE_STATS;
        if ($exportquiz->groupid) {
            $exportquizstats = $DB->get_record_select('exportquiz_statistics',
                'exportquizid = ? AND exportgroupid = ? AND groupid = ? AND allattempts = ? AND timemodified > ?',
                array($exportquiz->id, $exportquiz->groupid, $currentgroup, $useallattempts, $timemodified));
        } else {
            $exportquizstats = $DB->get_record_select('exportquiz_statistics',
                'exportquizid = ? AND exportgroupid = 0 AND groupid = ? AND allattempts = ? AND timemodified > ?',
                    array($exportquiz->id, $currentgroup, $useallattempts, $timemodified));
        }

        if (!$exportquizstats) {
            // No cached data found.
            return array(null, $questions, null, null);
        }

        if ($useallattempts) {
            $s = $exportquizstats->allattemptscount;
        } else {
            $s = $exportquizstats->firstattemptscount;
        }

        $subquestions = array();
        $questionstats = $DB->get_records('exportquiz_q_statistics',
                array('exportquizstatisticsid' => $exportquizstats->id));

        $subquestionstats = array();
        foreach ($questionstats as $stat) {
            $questions[$stat->questionid]->_stats = $stat;
        }

        if (!empty($subquestionstats)) {
            $subqstofetch = array_keys($subquestionstats);
            $subquestions = question_load_questions($subqstofetch);
            foreach ($subquestions as $subqid => $subq) {
                $subquestions[$subqid]->_stats = $subquestionstats[$subqid];
                $subquestions[$subqid]->maxmark = $subq->defaultmark;
            }
        }

        return array($exportquizstats, $questions, $subquestions, $s);
    }

    /**
     * Store the statistics in the cache tables in the database.
     *
     * @param object $exportquizid the exportquiz id.
     * @param int $currentgroup the current group. 0 for none.
     * @param bool $useallattempts use all attempts, or just first attempts.
     * @param object $exportquizstats The statistics for overall attempt scores.
     * @param array $questions The questions, with an additional _stats field.
     * @param array $subquestions The subquestions, if any, with an additional _stats field.
     */
    protected function cache_stats($exportquizid, $currentgroup,
            $exportquizstats, $questions, $subquestions, $exportgroupid = 0) {
        global $DB;

        $toinsert = clone($exportquizstats);
        $toinsert->exportquizid = $exportquizid;
        $toinsert->exportgroupid = $exportgroupid;
        $toinsert->groupid = $currentgroup;
        $toinsert->timemodified = time();

        // Fix up some dodgy data.
        if (isset($toinsert->errorratio) && is_nan($toinsert->errorratio)) {
            $toinsert->errorratio = null;
        }
        if (isset($toinsert->standarderror) && is_nan($toinsert->standarderror)) {
            $toinsert->standarderror = null;
        }

        // Store the data.
        $exportquizstats->id = $DB->insert_record('exportquiz_statistics', $toinsert);

        foreach ($questions as $question) {
            $question->_stats->exportquizstatisticsid = $exportquizstats->id;
            $DB->insert_record('exportquiz_q_statistics', $question->_stats, false);
        }

        foreach ($subquestions as $subquestion) {
            $subquestion->_stats->exportquizstatisticsid = $exportquizstats->id;
            $DB->insert_record('exportquiz_q_statistics', $subquestion->_stats, false);
        }

        return $exportquizstats->id;
    }

    /**
     * Get the exportquiz and question statistics, either by loading the cached results,
     * or by recomputing them.
     *
     * @param object $exportquiz the exportquiz settings.
     * @param int $currentgroup the current group. 0 for none.
     * @param bool $nostudentsingroup true if there a no students.
     * @param bool $useallattempts use all attempts, or just first attempts.
     * @param array $groupstudents students in this group.
     * @param array $questions question definitions.
     * @return array with 4 elements:
     *     - $exportquizstats The statistics for overall attempt scores.
     *     - $questions The questions, with an additional _stats field.
     *     - $subquestions The subquestions, if any, with an additional _stats field.
     *     - $s Number of attempts included in the stats.
     */
    protected function get_exportquiz_and_questions_stats($exportquiz, $currentgroup,
            $nostudentsingroup, $useallattempts, $groupstudents, $questions) {

        list($exportquizstats, $questions, $subquestions, $s) =
                $this->try_loading_cached_stats($exportquiz, $currentgroup, $nostudentsingroup,
                        $useallattempts, $groupstudents, $questions);

        if (is_null($exportquizstats)) {
            list($s, $exportquizstats, $qstats) = $this->compute_stats($exportquiz->id,
                    $currentgroup, $nostudentsingroup, $useallattempts, $groupstudents, $questions, $exportquiz->groupid);

            if ($s) {
                $questions = $qstats->questions;
                $subquestions = $qstats->subquestions;
                $exportquizstatisticsid = $this->cache_stats($exportquiz->id, $currentgroup,
                        $exportquizstats, $questions, $subquestions, $exportquiz->groupid);

                $this->analyse_responses($exportquizstatisticsid, $exportquiz->id, $currentgroup,
                        $nostudentsingroup, $useallattempts, $groupstudents,
                        $questions, $subquestions, $exportquiz->groupid);
            }
        }
        return array($exportquizstats, $questions, $subquestions, $s);
    }

    protected function analyse_responses($exportquizstatisticsid, $exportquizid, $currentgroup,
            $nostudentsingroup, $useallattempts, $groupstudents, $questions, $subquestions, $exportgroupid) {

        $qubaids = exportquiz_statistics_qubaids_condition(
                $exportquizid, $currentgroup, $groupstudents, $useallattempts, false, $exportgroupid);

        $done = array();
        foreach ($questions as $question) {
            if (!question_bank::get_qtype($question->qtype, false)->can_analyse_responses()) {
                continue;
            }
            $done[$question->id] = 1;
            $responesstats = new exportquiz_statistics_response_analyser($question);
            $responesstats->analyse($qubaids);
            $responesstats->store_cached($exportquizstatisticsid);
        }

        foreach ($subquestions as $question) {
            if (!question_bank::get_qtype($question->qtype, false)->can_analyse_responses() ||
                    isset($done[$question->id])) {
                continue;
            }
            $done[$question->id] = 1;

            $responesstats = new exportquiz_statistics_response_analyser($question);
            $responesstats->analyse($qubaids);
            $responesstats->store_cached($exportquizstatisticsid);
        }
    }

    /**
     * @return string HTML snipped for the Download full report as UI.
     */
    protected function everything_download_options() {

        $downloadoptions = $this->table->get_download_menu();
        $downloadelements = new stdClass();
        $downloadelements->formatsmenu = html_writer::select($downloadoptions, 'download',
                $this->table->defaultdownloadformat, false);
        $downloadelements->downloadbutton = '<input type="submit" value="' .
                get_string('download') . '"/>';

        $output = '<form action="'. $this->table->baseurl .'" method="post">';
        $output .= '<div class="mdl-align">';
        $output .= '<input type="hidden" name="everything" value="1"/>';
        $output .= html_writer::tag('label', get_string('downloadeverything', 'exportquiz_statistics', $downloadelements));
        $output .= '</div></form>';

        return $output;
    }

    /**
     * Generate the snipped of HTML that says when the stats were last caculated,
     * with a recalcuate now button.
     * @param object $exportquizstats the overall exportquiz statistics.
     * @param int $exportquizid the exportquiz id.
     * @param int $currentgroup the id of the currently selected group, or 0.
     * @param array $groupstudents ids of students in the group.
     * @param bool $useallattempts whether to use all attempts, instead of just
     *      first attempts.
     * @return string a HTML snipped saying when the stats were last computed,
     *      or blank if that is not appropriate.
     */
    protected function output_caching_info($exportquizstats, $exportquizid, $currentgroup,
            $groupstudents, $useallattempts, $reporturl, $exportgroupid) {
        global $DB, $OUTPUT;

        if (empty($exportquizstats->timemodified)) {
            return '';
        }

        // Find the number of attempts since the cached statistics were computed.
        list($fromqa, $whereqa, $qaparams) = exportquiz_statistics_attempts_sql(
                $exportquizid, $currentgroup, $groupstudents, $useallattempts, true, false, $exportgroupid);
        $count = $DB->count_records_sql("
                SELECT COUNT(1)
                FROM $fromqa
                WHERE $whereqa
                AND exportquiza.timefinish > {$exportquizstats->timemodified}", $qaparams);

        if (!$count) {
            $count = 0;
        }

        // Generate the output.
        $a = new stdClass();
        $a->lastcalculated = format_time(time() - $exportquizstats->timemodified);
        $a->count = $count;

        $recalcualteurl = new moodle_url($reporturl,
                array('recalculate' => 1, 'sesskey' => sesskey()));
        $output = '<br/>';
        $output .= $OUTPUT->box_start(
                'boxaligncenter generalbox boxwidthnormal mdl-align', 'cachingnotice');
        $output .= get_string('lastcalculated', 'exportquiz_statistics', $a);
        $output .= $OUTPUT->single_button($recalcualteurl,
                get_string('recalculatenow', 'exportquiz_statistics'));
        $output .= $OUTPUT->box_end(true);

        return $output;
    }

    /**
     * Clear the cached data for a particular report configuration. This will
     * trigger a re-computation the next time the report is displayed.
     * @param int $exportquizid the exportquiz id.
     * @param int $currentgroup a group id, or 0.
     * @param bool $useallattempts whether all attempts, or just first attempts are included.
     */
    protected function clear_cached_data($exportquizid, $currentgroup, $useallattempts, $exportgroupid) {
        global $DB;

        if ($exportgroupid) {
            $todelete = $DB->get_records_menu('exportquiz_statistics',
                    array('exportquizid' => $exportquizid, 'exportgroupid' => $exportgroupid,
                    'groupid' => $currentgroup, 'allattempts' => $useallattempts), '', 'id, 1');

        } else {
            $todelete = $DB->get_records_menu('exportquiz_statistics', array('exportquizid' => $exportquizid,
                    'groupid' => $currentgroup, 'allattempts' => $useallattempts), '', 'id, 1');
        }

        if (!$todelete) {
            return;
        }

        list($todeletesql, $todeleteparams) = $DB->get_in_or_equal(array_keys($todelete));

        $DB->delete_records_select('exportquiz_q_statistics',
                'exportquizstatisticsid ' . $todeletesql, $todeleteparams);
        $DB->delete_records_select('exportquiz_q_response_stats',
                'exportquizstatisticsid ' . $todeletesql, $todeleteparams);
        $DB->delete_records_select('exportquiz_statistics',
                'id ' . $todeletesql, $todeleteparams);
    }

    /**
     * @param bool $useallattempts whether we are using all attempts.
     * @return the appropriate lang string to describe this option.
     */
    protected function using_attempts_string($useallattempts) {
        if ($useallattempts) {
            return get_string('allattempts', 'exportquiz_statistics');
        } else {
            return get_string('firstattempts', 'exportquiz_statistics');
        }
    }
}

function exportquiz_statistics_attempts_sql($exportquizid, $currentgroup, $groupstudents,
        $allattempts = true, $includeungraded = false, $exportgroupid = 0) {
    global $DB;

    $fromqa = '{exportquiz_results} exportquiza ';

    $whereqa = 'exportquiza.exportquizid = :exportquizid AND  exportquiza.status = :exportquizstatefinished';
    $qaparams = array('exportquizid' => $exportquizid, 'exportquizstatefinished' => 'complete');

    if ($exportgroupid) {
        $whereqa .= ' AND exportquiza.exportgroupid = :exportgroupid';
        $qaparams['exportgroupid'] = $exportgroupid;
    }

    if (!empty($currentgroup) && $groupstudents) {
        list($grpsql, $grpparams) = $DB->get_in_or_equal(array_keys($groupstudents),
                SQL_PARAMS_NAMED, 'u');
        $whereqa .= " AND exportquiza.userid $grpsql";
        $qaparams += $grpparams;
    }

    if (!$includeungraded) {
        $whereqa .= ' AND exportquiza.sumgrades IS NOT NULL';
    }

    return array($fromqa, $whereqa, $qaparams);
}

/**
 * Return a {@link qubaid_condition} from the values returned by
 * {@link exportquiz_statistics_attempts_sql}
 * @param string $fromqa from exportquiz_statistics_attempts_sql.
 * @param string $whereqa from exportquiz_statistics_attempts_sql.
 */
function exportquiz_statistics_qubaids_condition($exportquizid, $currentgroup, $groupstudents,
        $allattempts = true, $includeungraded = false, $exportgroupid = 0) {
    list($fromqa, $whereqa, $qaparams) = exportquiz_statistics_attempts_sql($exportquizid, $currentgroup,
            $groupstudents, $allattempts, $includeungraded, $exportgroupid);
    return new qubaid_join($fromqa, 'exportquiza.usageid', $whereqa, $qaparams);
}
