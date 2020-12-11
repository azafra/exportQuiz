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

//  If, for some reason, you need to use global variables instead of constants, do not forget to make them
//  global as this file can be included inside a function scope. However, using the global variables
//  at the module level is not recommended.

// CONSTANTS.

// The different review options are stored in the bits of $exportquiz->review.
// These constants help to extract the options.
// Originally this method was copied from the Moodle 1.9 quiz module. We use:
// 111111100000000000.
define('EXPORTQUIZ_REVIEW_ATTEMPT',          0x1000);  // Show responses.
define('EXPORTQUIZ_REVIEW_MARKS',            0x2000);  // Show scores.
define('EXPORTQUIZ_REVIEW_SPECIFICFEEDBACK', 0x4000);  // Show feedback.
define('EXPORTQUIZ_REVIEW_RIGHTANSWER',      0x8000);  // Show correct answers.
define('EXPORTQUIZ_REVIEW_GENERALFEEDBACK',  0x10000); // Show general feedback.
define('EXPORTQUIZ_REVIEW_SHEET',            0x20000); // Show scanned sheet.
define('EXPORTQUIZ_REVIEW_CORRECTNESS',      0x40000); // Show scanned sheet.
define('EXPORTQUIZ_REVIEW_GRADEDSHEET',      0x800); // Show scanned sheet.

// Define constants for cron job status.
define('OQ_STATUS_PENDING', 1);
define('OQ_STATUS_OPERATING', 2);
define('OQ_STATUS_PROCESSED', 3);
define('OQ_STATUS_NEEDS_CORRECTION', 4);
define('OQ_STATUS_DOUBLE', 5);


// If start and end date for the export quiz are more than this many seconds apart
// they will be represented by two separate events in the calendar.

define('EXPORTQUIZ_MAX_EVENT_LENGTH', 5 * 24 * 60 * 60); // 5 days.

// FUNCTIONS.

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $exportquiz An object from the form in mod_form.php
 * @return int The id of the newly inserted exportquiz record
 */
function exportquiz_add_instance($exportquiz) {
    global $CFG, $DB;

    // Process the options from the form.
    $exportquiz->timecreated = time();
    $exportquiz->questions = '';
    $exportquiz->grade = 100;

    $result = exportquiz_process_options($exportquiz);

    if ($result && is_string($result)) {
        return $result;
    }
    if (!property_exists($exportquiz, 'intro') || $exportquiz->intro == null) {
        $exportquiz->intro = '';
    }

    if (!$course = $DB->get_record('course', array('id' => $exportquiz->course))) {
        print_error('invalidcourseid', 'error');
    }

	$context = context_module::instance($exportquiz->coursemodule);

    // Process the HTML editor data in pdfintro.
    if (is_array($exportquiz->pdfintro) && array_key_exists('text', $exportquiz->pdfintro)) {
    	if ($draftitemid = $exportquiz->pdfintro['itemid']) {
  		    $editoroptions = exportquiz_get_editor_options();

        	$exportquiz->pdfintro = file_save_draft_area_files($draftitemid, $context->id,
                                                    'mod_exportquiz', 'pdfintro',
                                                    0, $editoroptions,
                                                    $exportquiz->pdfintro['text']);
    	}
    }

    // Try to store it in the database.
    try {
        if (!$exportquiz->id = $DB->insert_record('exportquiz', $exportquiz)) {
            print_error('Could not create exportquiz object!');
            return false;
        }
    } catch (Exception $e) {
        print_error("ERROR: " . $e->debuginfo);
    }

    // Do the processing required after an add or an update.
    exportquiz_after_add_or_update($exportquiz);

    return $exportquiz->id;
}


/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $exportquiz An object from the form in mod_form.php
 * @return boolean Success/Fail
 */
function exportquiz_update_instance($exportquiz) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/mod/exportquiz/locallib.php');

    $exportquiz->timemodified = time();
    $exportquiz->id = $exportquiz->instance;

    // Remember the old values of the shuffle settings.
    $shufflequestions = $DB->get_field('exportquiz', 'shufflequestions', array('id' => $exportquiz->id));
    $shuffleanswers = $DB->get_field('exportquiz', 'shuffleanswers', array('id' => $exportquiz->id));

    // Process the options from the form.
    $result = exportquiz_process_options($exportquiz);
    if ($result && is_string($result)) {
        return $result;
    }

	$context = context_module::instance($exportquiz->coursemodule);

    // Process the HTML editor data in pdfintro.
    if (property_exists($exportquiz, 'pdfintro') && is_array($exportquiz->pdfintro)
            && array_key_exists('text', $exportquiz->pdfintro)) {
    	if ($draftitemid = $exportquiz->pdfintro['itemid']) {
  		    $editoroptions = exportquiz_get_editor_options();

        	$exportquiz->pdfintro = file_save_draft_area_files($draftitemid, $context->id,
                                                    'mod_exportquiz', 'pdfintro',
                                                    0, $editoroptions,
                                                    $exportquiz->pdfintro['text']);
    	}
        // $exportquiz->pdfintro = $feedback->pdfintro['format'];
    }

    // Update the database.
    if (! $DB->update_record('exportquiz', $exportquiz)) {
        return false;  // Some error occurred.
    }

    // Do the processing required after an add or an update.
    exportquiz_after_add_or_update($exportquiz);

    // We also need the docscreated and the numgroups field. 
    $exportquiz = $DB->get_record('exportquiz', array('id' => $exportquiz->id));

    // Delete the question usage templates if no documents have been created and no answer forms have been scanned.
    if (!$exportquiz->docscreated && !exportquiz_has_scanned_pages($exportquiz->id)) {
        exportquiz_delete_template_usages($exportquiz);
    }

    return true;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function exportquiz_delete_instance($id) {
    global $DB, $CFG;

    require_once($CFG->dirroot . '/mod/exportquiz/locallib.php');
    require_once($CFG->dirroot . '/calendar/lib.php');

    if (! $exportquiz = $DB->get_record('exportquiz', array('id' => $id))) {
        return false;
    }

    if (! $cm = get_coursemodule_from_instance("exportquiz", $exportquiz->id, $exportquiz->course)) {
        return false;
    }
    $context = context_module::instance($cm->id);

    // Delete any dependent records here.
    if ($results = $DB->get_records("exportquiz_results", array('exportquizid' => $exportquiz->id))) {
        foreach ($results as $result) {
            exportquiz_delete_result($result->id, $context);
        }
    }

    if ($events = $DB->get_records('event', array('modulename' => 'exportquiz', 'instance' => $exportquiz->id))) {
        foreach ($events as $event) {
            $event = calendar_event::load($event);
            $event->delete();
        }
    }

    if ($plists = $DB->get_records('exportquiz_p_lists', array('exportquizid' => $exportquiz->id))) {
        foreach ($plists as $plist) {
            $DB->delete_records('exportquiz_participants', array('listid' => $plist->id));
            $DB->delete_records('exportquiz_p_lists', array('id' => $plist->id));
        }
    }

    // Remove the grade item.
    exportquiz_grade_item_delete($exportquiz);

    // Delete template question usages of exportquiz groups.
    exportquiz_delete_template_usages($exportquiz);

    // All the tables with no dependencies...
    $tablestopurge = array(
            'exportquiz_groups' => 'exportquizid',
            'exportquiz' => 'id'
    );

    foreach ($tablestopurge as $table => $keyfield) {
        if (! $DB->delete_records($table, array($keyfield => $exportquiz->id))) {
            $result = false;
        }
    }

    return true;
}

/**
 * This gets an array with default options for the editor
 *
 * @return array the options
 */
function exportquiz_get_editor_options($context = null) {
    $options = array('maxfiles' => EDITOR_UNLIMITED_FILES,
    		     'noclean' => true);
    if ($context) {
    	$options['context'] = $context;
    }
    return $options;
}

/**
 * Delete grade item for given exportquiz
 *
 * @param object $exportquiz object
 * @return object exportquiz
 */
function exportquiz_grade_item_delete($exportquiz) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod/exportquiz', $exportquiz->course, 'mod', 'exportquiz', $exportquiz->id, 0,
            null, array('deleted' => 1));
}

/**
 * Called via pluginfile.php -> question_pluginfile to serve files belonging to
 * a question in a question_attempt when that attempt is an exportquiz attempt.
 *
 * @package  mod_exportquiz
 * @category files
 * @param stdClass $course course settings object
 * @param stdClass $context context object
 * @param string $component the name of the component we are serving files for.
 * @param string $filearea the name of the file area.
 * @param int $qubaid the attempt usage id.
 * @param int $slot the id of a question in this quiz attempt.
 * @param array $args the remaining bits of the file path.
 * @param bool $forcedownload whether the user must be forced to download the file.
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function exportquiz_question_pluginfile($course, $context, $component,
        $filearea, $qubaid, $slot, $args, $forcedownload, array $options=array()) {
    global $CFG, $DB, $USER;

    list($context, $course, $cm) = get_context_info_array($context->id);
    require_login($course, false, $cm);

    if (!has_capability('mod/exportquiz:viewreports', $context)) {
        // If the user is not a teacher then check whether a complete result exists.
        if (!$result = $DB->get_record('exportquiz_results', array('usageid' => $qubaid, 'status' => 'complete'))) {
            send_file_not_found();
        }
        // If the user's ID is not the ID of the result we don't serve the file.
        if ($result->userid != $USER->id) {
            send_file_not_found();
        }
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/$component/$filearea/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Serve questiontext files in the question text when they are displayed in a report.
 *
 * @param context $previewcontext the quiz context
 * @param int $questionid the question id.
 * @param context $filecontext the file (question) context
 * @param string $filecomponent the component the file belongs to.
 * @param string $filearea the file area.
 * @param array $args remaining file args.
 * @param bool $forcedownload.
 * @param array $options additional options affecting the file serving.
 */
function exportquiz_question_preview_pluginfile($previewcontext, $questionid, $filecontext, $filecomponent, $filearea,
         $args, $forcedownload, $options = array()) {
     global $CFG;

    require_once($CFG->dirroot . '/mod/exportquiz/locallib.php');
    require_once($CFG->dirroot . '/lib/questionlib.php');

    list($context, $course, $cm) = get_context_info_array($previewcontext->id);
    require_login($course, false, $cm);

    // We assume that only trusted people can see this report. There is no real way to
    // validate questionid, because of the complexity of random questions.
    require_capability('mod/exportquiz:viewreports', $context);

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/{$filecontext->id}/{$filecomponent}/{$filearea}/{$relativepath}";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        send_file_not_found();
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Serve image files in the answer text when they are displayed in the preview
 *
 * @param context $context the context
 * @param int $answerid the answer id
 * @param array $args remaining file args
 * @param bool $forcedownload
 */
function exportquiz_answertext_preview_pluginfile($context, $answerid, $args, $forcedownload, array $options=array()) {
    global $CFG;

    require_once($CFG->dirroot . '/mod/exportquiz/locallib.php');
    require_once($CFG->dirroot . '/lib/questionlib.php');

    list($context, $course, $cm) = get_context_info_array($context->id);
    require_login($course, false, $cm);

    // Assume only trusted people can see this report. There is no real way to
    // validate questionid, becuase of the complexity of random quetsions.
    require_capability('mod/exportquiz:viewreports', $context);

    exportquiz_send_answertext_file($context, $answerid, $args, $forcedownload, $options);
}

/**
 * Send a file in the text of an answer.
 *
 * @param int $questionid the question id
 * @param array $args the remaining file arguments (file path).
 * @param bool $forcedownload whether the user must be forced to download the file.
 */
function exportquiz_send_answertext_file($context, $answerid, $args, $forcedownload) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/mod/exportquiz/locallib.php');

    $fs = get_file_storage();
    $fullpath = "/$context->id/question/answer/$answerid/" . implode('/', $args);
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file($file, 0, 0, $forcedownload);
}

/**
 * Serves the exportquiz files.
 *
 * @param object $course
 * @param object $cm
 * @param object $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return bool false if file not found, does not return if found - justsend the file
 */
function exportquiz_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $CFG, $DB, $USER;
    require_once($CFG->dirroot . '/mod/exportquiz/locallib.php');
    require_once($CFG->libdir . '/questionlib.php');

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, false, $cm);

    if (!$exportquiz = $DB->get_record('exportquiz', array('id' => $cm->instance))) {
        return false;
    }

    // The file area 'pdfs' is served by pluginfile.php.
    $fileareas = array('pdfs', 'imagefiles');
    if (!in_array($filearea, $fileareas)) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);

    $fullpath = '/' . $context->id . '/mod_exportquiz/' . $filearea . '/' . $relativepath;

    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // Teachers in this context are allowed to see all the files in the context.
    if (has_capability('mod/exportquiz:viewreports', $context)) {
        if ($filearea == 'pdfs') {
            $filename = clean_filename($course->shortname) . '_' . clean_filename($exportquiz->name) . '_' . $file->get_filename();
            send_stored_file($file, 86400, 0, $forcedownload, array('filename' => $filename));
        } else {
            send_stored_file($file, 86400, 0, $forcedownload);
        }
    } else {

        // Get the corresponding scanned pages. There might be several in case an image file is used twice.
        if (!$scannedpages = $DB->get_records('exportquiz_scanned_pages',
                array('exportquizid' => $exportquiz->id, 'warningfilename' => $file->get_filename()))) {
            if (!$scannedpages = $DB->get_records('exportquiz_scanned_pages', array('exportquizid' => $exportquiz->id,
                    'filename' => $file->get_filename()))) {
                    print_error('scanned page not found');
                    return false;
            }
        }

        // Actually, there should be only one scannedpage with that filename...
        foreach ($scannedpages as $scannedpage) {
            $sql = "SELECT *
                      FROM {exportquiz_results}
                     WHERE id = :resultid
                       AND status = 'complete'";
            if (!$result = $DB->get_record_sql($sql, array('resultid' => $scannedpage->resultid))) {
                return false;
            }

            // Check whether the student is allowed to see scanned sheets.
            $options = exportquiz_get_review_options($exportquiz, $result, $context);
            if ($options->sheetfeedback == question_display_options::HIDDEN and
                    $options->gradedsheetfeedback == question_display_options::HIDDEN) {
                return false;
            }

            // If we found a page of a complete result that belongs to the user, we can send the file.
            if ($result->userid == $USER->id) {
                send_stored_file($file, 86400, 0, $forcedownload);
                return true;
            }
        }
    }
}

/**
 * Return a list of page types
 *
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function exportquiz_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $modulepagetype = array(
            'mod-exportquiz-*' => get_string('page-mod-exportquiz-x', 'exportquiz'),
            'mod-exportquiz-edit' => get_string('page-mod-exportquiz-edit', 'exportquiz'));
    return $modulepagetype;
}

/**
 * Return a textual summary of the number of attempts that have been made at a particular exportquiz,
 * returns '' if no attempts have been made yet, unless $returnzero is passed as true.
 *
 * @param object $exportquiz the exportquiz object. Only $exportquiz->id is used at the moment.
 * @param object $cm the cm object. Only $cm->course, $cm->groupmode and
 *      $cm->groupingid fields are used at the moment.
 * @param bool $returnzero if false (default), when no attempts have been
 *      made '' is returned instead of 'Attempts: 0'.
 * @param int $currentgroup if there is a concept of current group where this method is being called
 *         (e.g. a report) pass it in here. Default 0 which means no current group.
 * @return string a string like "Attempts: 123", "Attemtps 123 (45 from your groups)" or
 *          "Attemtps 123 (45 from this group)".
 */
function exportquiz_num_attempt_summary($exportquiz, $cm, $returnzero = false, $currentgroup = 0) {
    global $DB, $USER;

    $sql = "SELECT COUNT(*)
              FROM {exportquiz_results}
             WHERE exportquizid = :exportquizid
               AND status = 'complete'";

    $numattempts = $DB->count_records_sql($sql, array('exportquizid' => $exportquiz->id));
    if ($numattempts || $returnzero) {
        return get_string('attemptsnum', 'exportquiz', $numattempts);
    }
    return '';
}


/**
 * Returns the same as {@link exportquiz_num_attempt_summary()} but wrapped in a link
 * to the exportquiz reports.
 *
 * @param object $exportquiz the exportquiz object. Only $exportquiz->id is used at the moment.
 * @param object $cm the cm object. Only $cm->course, $cm->groupmode and
 *      $cm->groupingid fields are used at the moment.
 * @param object $context the exportquiz context.
 * @param bool $returnzero if false (default), when no attempts have been made
 *      '' is returned instead of 'Attempts: 0'.
 * @param int $currentgroup if there is a concept of current group where this method is being called
 *         (e.g. a report) pass it in here. Default 0 which means no current group.
 * @return string HTML fragment for the link.
 */
function exportquiz_attempt_summary_link_to_reports($exportquiz, $cm, $context, $returnzero = false,
        $currentgroup = 0) {
    global $CFG;
    $summary = exportquiz_num_attempt_summary($exportquiz, $cm, $returnzero, $currentgroup);
    if (!$summary) {
        return '';
    }

    $url = new moodle_url('/mod/exportquiz/report.php', array(
            'id' => $cm->id, 'mode' => 'overview'));
    return html_writer::link($url, $summary);
}


/**
 * Check for features supported by exportquizzes.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return bool True if exportquiz supports feature
 */
function exportquiz_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GROUPMEMBERSONLY:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return false;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_USES_QUESTIONS:
          return true;

        default:
            return null;
    }
}

/**
 * Is this a graded exportquiz? If this method returns true, you can assume that
 * $exportquiz->grade and $exportquiz->sumgrades are non-zero (for example, if you want to
 * divide by them).
 *
 * @param object $exportquiz a row from the exportquiz table.
 * @return bool whether this is a graded exportquiz.
 */
function exportquiz_has_grades($exportquiz) {
    return $exportquiz->grade >= 0.000005 && $exportquiz->sumgrades >= 0.000005;
}

/**
 * Pre-process the exportquiz options form data, making any necessary adjustments.
 * Called by add/update instance in this file, and the save code in admin/module.php.
 *
 * @param object $exportquiz The variables set on the form.
 */
function exportquiz_process_options(&$exportquiz) {
    global $CFG;
    require_once($CFG->libdir . '/questionlib.php');

    $exportquiz->timemodified = time();

    // exportquiz name. (Make up a default if one was not given).
    if (empty($exportquiz->name)) {
        if (empty($exportquiz->intro)) {
            $exportquiz->name = get_string('modulename', 'exportquiz');
        } else {
            $exportquiz->name = shorten_text(strip_tags($exportquiz->intro));
        }
    }
    $exportquiz->name = trim($exportquiz->name);

    // Settings that get combined to go into the optionflags column.
    $exportquiz->optionflags = 0;
    if (!empty($exportquiz->adaptive)) {
        $exportquiz->optionflags |= QUESTION_ADAPTIVE;
    }

    // Settings that get combined to go into the review column.
    $review = 0;
    if (isset($exportquiz->attemptclosed)) {
        $review += EXPORTQUIZ_REVIEW_ATTEMPT;
        unset($exportquiz->attemptclosed);
    }

    if (isset($exportquiz->marksclosed)) {
        $review += EXPORTQUIZ_REVIEW_MARKS;
        unset($exportquiz->marksclosed);
    }

    if (isset($exportquiz->feedbackclosed)) {
        $review += EXPORTQUIZ_REVIEW_FEEDBACK;
        unset($exportquiz->feedbackclosed);
    }

    if (isset($exportquiz->correctnessclosed)) {
        $review += EXPORTQUIZ_REVIEW_CORRECTNESS;
        unset($exportquiz->correctnessclosed);
    }

    if (isset($exportquiz->rightanswerclosed)) {
        $review += EXPORTQUIZ_REVIEW_RIGHTANSWER;
        unset($exportquiz->rightanswerclosed);
    }

    if (isset($exportquiz->generalfeedbackclosed)) {
        $review += EXPORTQUIZ_REVIEW_GENERALFEEDBACK;
        unset($exportquiz->generalfeedbackclosed);
    }

    if (isset($exportquiz->specificfeedbackclosed)) {
        $review += EXPORTQUIZ_REVIEW_SPECIFICFEEDBACK;
        unset($exportquiz->specificfeedbackclosed);
    }

    if (isset($exportquiz->sheetclosed)) {
        $review += EXPORTQUIZ_REVIEW_SHEET;
        unset($exportquiz->sheetclosed);
    }

    if (isset($exportquiz->gradedsheetclosed)) {
        $review += EXPORTQUIZ_REVIEW_GRADEDSHEET;
        unset($exportquiz->gradedsheetclosed);
    }

    $exportquiz->review = $review;
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param unknown_type $course
 * @param unknown_type $user
 * @param unknown_type $mod
 * @param unknown_type $exportquiz
 * @return stdClass|NULL
 */
function exportquiz_user_outline($course, $user, $mod, $exportquiz) {
    global $DB;

    $return = new stdClass;
    $return->time = 0;
    $return->info = '';

    if ($grade = $DB->get_record('exportquiz_results', array('userid' => $user->id, 'exportquizid' => $exportquiz->id))) {
        if ((float) $grade->sumgrades) {
            $return->info = get_string('grade') . ':&nbsp;' . round($grade->sumgrades, $exportquiz->decimalpoints);
        }
        $return->time = $grade->timemodified;
        return $return;
    }
    return null;
}

/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param unknown_type $course
 * @param unknown_type $user
 * @param unknown_type $mod
 * @param unknown_type $exportquiz
 * @return boolean
 */
function exportquiz_user_complete($course, $user, $mod, $exportquiz) {
    global $DB;

    if ($results = $DB->get_records('exportquiz_results', array('userid' => $user->id, 'exportquiz' => $exportquiz->id))) {
        if ($exportquiz->grade && $exportquiz->sumgrades &&
                $grade = $DB->get_record('exportquiz_results', array('userid' => $user->id, 'exportquiz' => $exportquiz->id))) {
            echo get_string('grade') . ': ' . round($grade->grade, $exportquiz->decimalpoints) .
                '/' . $exportquiz->grade . '<br />';
        }
        foreach ($results as $result) {
            echo get_string('result', 'exportquiz') . ': ';
            if ($result->timefinish == 0) {
                print_string('unfinished');
            } else {
                echo round($result->sumgrades, $exportquiz->decimalpoints) . '/' . $exportquiz->sumgrades;
            }
            echo ' - ' . userdate($result->timemodified) . '<br />';
        }
    } else {
        print_string('noresults', 'exportquiz');
    }

    return true;
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in exportquiz activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @param unknown_type $course
 * @param unknown_type $viewfullnames
 * @param unknown_type $timestart
 * @return boolean
 */
function exportquiz_print_recent_mod_activity($course, $viewfullnames, $timestart) {
    return false;  //  True if anything was printed, otherwise false.
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * Note: The evaluation of answer forms is done by a separate cron job using the script mod/exportquiz/cron.php.
 *
 **/
function exportquiz_cron() {
    global $DB;

    cron_execute_plugin_type('exportquiz', 'exportquiz reports');

    // Remove all saved hotspot data that is older than 7 days.
    $timenow = time();

    // We have to make sure we do this atomic for each scanned page.
    $sql = "SELECT DISTINCT(scannedpageid)
              FROM {exportquiz_hotspots}
             WHERE time < :expiretime";
    $params = array('expiretime' => $timenow - 604800);

    // First we get the different IDs.
    $ids = $DB->get_fieldset_sql($sql, $params);

    if (!empty($ids)) {
        list($isql, $iparams) = $DB->get_in_or_equal($ids);

        // Now we delete the records.
        $DB->delete_records_select('exportquiz_hotspots', 'scannedpageid ' . $isql, $iparams);
    }

    // Delete old temporary files not needed any longer.
    $keepdays = get_config('exportquiz', 'keepfilesfordays');
    $keepseconds = $keepdays * 24 * 60 * 60;

    $sql = "SELECT id
              FROM {exportquiz_queue}
             WHERE timecreated < :expiretime";
    $params = array('expiretime' => $timenow - $keepseconds);

    // First we get the IDs of cronjobs older than the configured number of days.
    $jobids = $DB->get_fieldset_sql($sql, $params);
    foreach ($jobids as $jobid) {
        $dirname = null;
        // Delete all temporary files and the database entries.
        if ($files = $DB->get_records('exportquiz_queue_data', array('queueid' => $jobid))) {
            foreach ($files as $file) {
                if (empty($dirname)) {
                    $pathparts = pathinfo($file->filename);
                    $dirname = $pathparts['dirname'];
                }
                $DB->delete_records('exportquiz_queue_data', array('id' => $file->id));
            }
            // Remove the temporary directory.
            echo "Removing dir " . $dirname . "\n";
            remove_dir($dirname);
        }
    }

    return true;
}

/**
 * Must return an array of users who are participants for a given instance
 * of exportquiz. Must include every user involved in the instance,
 * independient of his role (student, teacher, admin...). The returned
 * objects must contain at least id property.
 * See other modules as example.
 *
 * @param int $exportquizid ID of an instance of this module
 * @return boolean|array false if no participants, array of objects otherwise
 */
function exportquiz_get_participants($exportquizid) {
    global $CFG, $DB;

    // Get users from exportquiz results.
    $usattempts = $DB->get_records_sql("
            SELECT DISTINCT u.id, u.id
              FROM {user} u,
                   {exportquiz_results} r
             WHERE r.exportquizid = '$exportquizid'
               AND (u.id = r.userid OR u.id = r.teacherid");

    // Return us_attempts array (it contains an array of unique users).
    return $usattempts;
}

/**
 * This function returns if a scale is being used by one exportquiz
 * if it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $exportquizid ID of an instance of this module
 * @return mixed
 */
function exportquiz_scale_used($exportquizid, $scaleid) {
    global $DB;

    $return = false;

    $rec = $DB->get_record('exportquiz', array('id' => $exportquizid, 'grade' => -$scaleid));

    if (!empty($rec) && !empty($scaleid)) {
        $return = true;
    }

    return $return;
}

/**
 * Checks if scale is being used by any instance of exportquiz.
 * This function was added in 1.9
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any exportquiz
 */
function exportquiz_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('exportquiz', array('grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * This function is called at the end of exportquiz_add_instance
 * and exportquiz_update_instance, to do the common processing.
 *
 * @param object $exportquiz the exportquiz object.
 */
function exportquiz_after_add_or_update($exportquiz) {
    global $DB;

    // Create group entries if they don't exist.
    if (property_exists($exportquiz, 'numgroups')) {
        for ($i = 1; $i <= $exportquiz->numgroups; $i++) {
            if (!$group = $DB->get_record('exportquiz_groups', array('exportquizid' => $exportquiz->id, 'number' => $i))) {
                $group = new stdClass();
                $group->exportquizid = $exportquiz->id;
                $group->number = $i;
                $group->numberofpages = 1;
                $DB->insert_record('exportquiz_groups', $group);
            }
        }
    }

    exportquiz_update_events($exportquiz);
    exportquiz_grade_item_update($exportquiz);
    return;
}

/**
 * This function updates the events associated to the exportquiz.
 * If $override is non-zero, then it updates only the events
 * associated with the specified override.
 *
 * @uses EXPORTQUIZ_MAX_EVENT_LENGTH
 * @param object $exportquiz the exportquiz object.
 * @param object optional $override limit to a specific override
 */
function exportquiz_update_events($exportquiz) {
    global $DB, $CFG;

    require_once($CFG->dirroot . '/calendar/lib.php');

    // Load the old events relating to this exportquiz.
    $conds = array('modulename' => 'exportquiz',
                   'instance' => $exportquiz->id);

    if (!empty($override)) {
        // Only load events for this override.
        $conds['groupid'] = isset($override->groupid) ? $override->groupid : 0;
        $conds['userid'] = isset($override->userid) ? $override->userid : 0;
    }
    $oldevents = $DB->get_records('event', $conds);

    $groupid   = 0;
    $userid    = 0;
    $timeopen  = $exportquiz->timeopen;
    $timeclose = $exportquiz->timeclose;

    if ($exportquiz->time) {
        $timeopen = $exportquiz->time;
    }

    // Only add open/close events if they differ from the exportquiz default.
    if (!empty($exportquiz->coursemodule)) {
        $cmid = $exportquiz->coursemodule;
    } else {
        $cmid = get_coursemodule_from_instance('exportquiz', $exportquiz->id, $exportquiz->course)->id;
    }

    if (!empty($timeopen)) {
        $event = new stdClass();
        $event->name = $exportquiz->name;
        $event->description = format_module_intro('exportquiz', $exportquiz, $cmid);
        // Events module won't show user events when the courseid is nonzero.
        $event->courseid    = ($userid) ? 0 : $exportquiz->course;
        $event->groupid     = $groupid;
        $event->userid      = $userid;
        $event->modulename  = 'exportquiz';
        $event->instance    = $exportquiz->id;
        $event->timestart   = $timeopen;
        $event->timeduration = max($timeclose - $timeopen, 0);
        $event->visible     = instance_is_visible('exportquiz', $exportquiz);

        if ($timeopen == $exportquiz->time) {
            $event->name = $exportquiz->name;
        }
        if ($timeopen == $exportquiz->timeopen) {
            $event->name = $exportquiz->name . ' (' . get_string('reportstarts', 'exportquiz') . ')';
        }

        calendar_event::create($event);
    }

    // Delete any leftover events.
    foreach ($oldevents as $badevent) {
        $badevent = calendar_event::load($badevent);
        $badevent->delete();
    }
}


/**
 * Prints exportquiz summaries on MyMoodle Page
 * @param arry $courses
 * @param array $htmlarray
 */
function exportquiz_print_overview($courses, &$htmlarray) {
    global $USER, $CFG;
    // These next 6 Lines are constant in all modules (just change module name).
    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return array();
    }

    if (!$exportquizzes = get_all_instances_in_courses('exportquiz', $courses)) {
        return;
    }

    // Fetch some language strings outside the main loop.
    $strexportquiz = get_string('modulename', 'exportquiz');
    $strnoattempts = get_string('noresults', 'exportquiz');

    // We want to list exportquizzes that are currently available, and which have a close date.
    // This is the same as what the lesson does, and the dabate is in MDL-10568.
    $now = time();
    foreach ($exportquizzes as $exportquiz) {
        if ($exportquiz->timeclose >= $now && $exportquiz->timeopen < $now) {
            // Give a link to the exportquiz, and the deadline.
            $str = '<div class="exportquiz overview">' .
                    '<div class="name">' . $strexportquiz . ': <a ' .
                    ($exportquiz->visible ? '' : ' class="dimmed"') .
                    ' href="' . $CFG->wwwroot . '/mod/exportquiz/view.php?id=' .
                    $exportquiz->coursemodule . '">' .
                    $exportquiz->name . '</a></div>';
            $str .= '<div class="info">' . get_string('exportquizcloseson', 'exportquiz',
                    userdate($exportquiz->timeclose)) . '</div>';

            // Now provide more information depending on the uers's role.
            $context = context_module::instance($exportquiz->coursemodule);
            if (has_capability('mod/exportquiz:viewreports', $context)) {
                // For teacher-like people, show a summary of the number of student attempts.
                // The $exportquiz objects returned by get_all_instances_in_course have the necessary $cm
                // fields set to make the following call work.
                $str .= '<div class="info">' .
                        exportquiz_num_attempt_summary($exportquiz, $exportquiz, true) . '</div>';
            } else if (has_capability('mod/exportquiz:attempt', $context)) { // Student
                // For student-like people, tell them how many attempts they have made.
                if (isset($USER->id) && ($results = exportquiz_get_user_results($exportquiz->id, $USER->id))) {
                    $str .= '<div class="info">' .
                            get_string('hasresult', 'exportquiz') . '</div>';
                } else {
                    $str .= '<div class="info">' . $strnoattempts . '</div>';
                }
            } else {
                // For ayone else, there is no point listing this exportquiz, so stop processing.
                continue;
            }

            // Add the output for this exportquiz to the rest.
            $str .= '</div>';
            if (empty($htmlarray[$exportquiz->course]['exportquiz'])) {
                $htmlarray[$exportquiz->course]['exportquiz'] = $str;
            } else {
                $htmlarray[$exportquiz->course]['exportquiz'] .= $str;
            }
        }
    }
}


/**
 * Round a grade to to the correct number of decimal places, and format it for display.
 *
 * @param object $exportquiz The exportquiz table row, only $exportquiz->decimalpoints is used.
 * @param float $grade The grade to round.
 * @return float
 */
function exportquiz_format_grade($exportquiz, $grade) {
    if (is_null($grade)) {
        return get_string('notyetgraded', 'exportquiz');
    }
    return format_float($grade, $exportquiz->decimalpoints);
}

/**
 * Round a grade to to the correct number of decimal places, and format it for display.
 *
 * @param object $exportquiz The exportquiz table row, only $exportquiz->decimalpoints is used.
 * @param float $grade The grade to round.
 * @return float
 */
function exportquiz_format_question_grade($exportquiz, $grade) {
    global $CFG;

    require_once($CFG->dirroot . '/mod/exportquiz/locallib.php');

    if (empty($exportquiz->questiondecimalpoints)) {
        $exportquiz->questiondecimalpoints = -1;
    }
    if ($exportquiz->questiondecimalpoints == -1) {
        return format_float($grade, $exportquiz->decimalpoints);
    } else {
        return format_float($grade, $exportquiz->questiondecimalpoints);
    }
}


/**
 * Return grade for given user or all users. The grade is taken from all complete exportquiz results
 *
 * @param mixed $exportquiz The export quiz
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function exportquiz_get_user_grades($exportquiz, $userid=0) {
    global $CFG, $DB;

    $maxgrade = $exportquiz->grade;
    $groups = $DB->get_records('exportquiz_groups',
                               array('exportquizid' => $exportquiz->id), 'number', '*', 0, $exportquiz->numgroups);

    $user = $userid ? " AND userid =  $userid " : "";

    $sql = "SELECT id, userid, sumgrades, exportgroupid, timemodified as dategraded, timefinish AS datesubmitted
              FROM {exportquiz_results}
             WHERE exportquizid = :exportquizid
               AND status = 'complete'
    $user";
    $params = array('exportquizid' => $exportquiz->id);

    $grades = array();

    if ($results = $DB->get_records_sql($sql, $params)) {
        foreach ($results as $result) {
            $key = $result->userid;
            $grades[$key] = array();
            $groupsumgrades = $groups[$result->exportgroupid]->sumgrades;
            $grades[$key]['userid'] = $result->userid;
            $grades[$key]['rawgrade'] = round($result->sumgrades / $groupsumgrades * $maxgrade, $exportquiz->decimalpoints);
            $grades[$key]['dategraded'] = $result->dategraded;
            $grades[$key]['datesubmitted'] = $result->datesubmitted;
        }
    }

    return $grades;
}

/**
 * Update grades in central gradebook
 *
 * @param object $exportquiz the export quiz settings.
 * @param int $userid specific user only, 0 means all users.
 */
function exportquiz_update_grades($exportquiz, $userid = 0, $nullifnone = true) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    if ($exportquiz->grade == 0) {
        exportquiz_grade_item_update($exportquiz);

    } else if ($grades = exportquiz_get_user_grades($exportquiz, $userid)) {
        exportquiz_grade_item_update($exportquiz, $grades);

    } else if ($userid && $nullifnone) {
        $grade = new stdClass();
        $grade->userid = $userid;
        $grade->rawgrade = null;
        exportquiz_grade_item_update($exportquiz, $grade);

    } else {
        exportquiz_grade_item_update($exportquiz);
    }
}


/**
 * Create grade item for given exportquiz
 *
 * @param object $exportquiz object with extra cmidnumber
 * @param mixed $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function exportquiz_grade_item_update($exportquiz, $grades = null) {
    global $CFG, $OUTPUT, $DB;

    require_once($CFG->dirroot . '/mod/exportquiz/locallib.php');
    require_once($CFG->libdir . '/gradelib.php');
    require_once($CFG->libdir . '/questionlib.php');

    if (array_key_exists('cmidnumber', $exportquiz)) {
        // May not be always present.
        $params = array('itemname' => $exportquiz->name, 'idnumber' => $exportquiz->cmidnumber);
    } else {
        $params = array('itemname' => $exportquiz->name);
    }

    $exportquiz->grade = $DB->get_field('exportquiz', 'grade', array('id' => $exportquiz->id));

    if (property_exists($exportquiz, 'grade') && $exportquiz->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $exportquiz->grade;
        $params['grademin']  = 0;

    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }

    // Description by Juergen Zimmer (Tim Hunt):
    // 1. If the exportquiz is set to not show grades while the exportquiz is still open,
    //    and is set to show grades after the exportquiz is closed, then create the
    //    grade_item with a show-after date that is the exportquiz close date.
    // 2. If the exportquiz is set to not show grades at either of those times,
    //    create the grade_item as hidden.
    // 3. If the exportquiz is set to show grades, create the grade_item visible.
    $openreviewoptions = mod_exportquiz_display_options::make_from_exportquiz($exportquiz);
    $closedreviewoptions = mod_exportquiz_display_options::make_from_exportquiz($exportquiz);
    if ($openreviewoptions->marks < question_display_options::MARK_AND_MAX &&
            $closedreviewoptions->marks < question_display_options::MARK_AND_MAX) {
        $params['hidden'] = 1;

    } else if ($openreviewoptions->marks < question_display_options::MARK_AND_MAX &&
            $closedreviewoptions->marks >= question_display_options::MARK_AND_MAX) {
        if ($exportquiz->timeclose) {
            $params['hidden'] = $exportquiz->timeclose;
        } else {
            $params['hidden'] = 1;
        }
    } else {
        // A) both open and closed enabled
        // B) open enabled, closed disabled - we can not "hide after",
        //    grades are kept visible even after closing.
        $params['hidden'] = 0;
    }

    if (!$params['hidden']) {
        // If the grade item is not hidden by the exportquiz logic, then we need to
        // hide it if the exportquiz is hidden from students.
        $cm = get_coursemodule_from_instance('exportquiz', $exportquiz->id);
        if ($cm) {
            $params['hidden'] = !$cm->visible;
        } else {
            $params['hidden'] = !$exportquiz->visible;
        }
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    $gradebookgrades = grade_get_grades($exportquiz->course, 'mod', 'exportquiz', $exportquiz->id);
    if (!empty($gradebookgrades->items)) {
        $gradeitem = $gradebookgrades->items[0];
        if ($gradeitem->hidden) {
            $params['hidden'] = 1;
        }
        if ($gradeitem->locked) {
            $confirmregrade = optional_param('confirm_regrade', 0, PARAM_INT);
            if (!$confirmregrade) {
                if (!AJAX_SCRIPT) {
                    $message = get_string('gradeitemislocked', 'grades');
                    $backlink = $CFG->wwwroot . '/mod/exportquiz/edit.php?q=' . $exportquiz->id .
                    '&amp;mode=overview';
                    $regradelink = qualified_me() . '&amp;confirm_regrade=1';
                    echo $OUTPUT->box_start('generalbox', 'notice');
                    echo '<p>'. $message .'</p>';
                    echo $OUTPUT->container_start('buttons');
                    echo $OUTPUT->single_button($regradelink, get_string('regradeanyway', 'grades'));
                    echo $OUTPUT->single_button($backlink,  get_string('cancel'));
                    echo $OUTPUT->container_end();
                    echo $OUTPUT->box_end();
                }
                return GRADE_UPDATE_ITEM_LOCKED;
            }
        }
    }

    return grade_update('mod/exportquiz', $exportquiz->course, 'mod', 'exportquiz', $exportquiz->id, 0, $grades, $params);
}

/**
 * @param int $exportquizid the exportquiz id.
 * @param int $userid the userid.
 * @param string $status 'all', 'finished' or 'unfinished' to control
 * @param bool $includepreviews
 * @return an array of all the user's results at this exportquiz. Returns an empty
 *      array if there are none.
 */
function exportquiz_get_user_results($exportquizid, $userid) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/mod/exportquiz/locallib.php');

    $params = array();

    $params['exportquizid'] = $exportquizid;
    $params['userid'] = $userid;
    return $DB->get_records_select('exportquiz_results',
            "exportquizid = :exportquizid AND userid = :userid AND status = 'complete'", $params, 'id ASC');
}

/**
 * This function extends the settings navigation block for the site.
 *
 * It is safe to rely on PAGE here as we will only ever be within the module
 * context when this is called
 *
 * @param settings_navigation $settings
 * @param navigation_node $exportquiznode
 */
function exportquiz_extend_settings_navigation($settings, $exportquiznode) {
    global $PAGE, $CFG;

    // Included here as we only ever want to include this file if we really need to.
    require_once($CFG->libdir . '/questionlib.php');

    // We want to add these new nodes after the Edit settings node, and before the
    // Locally assigned roles node. Of course, both of those are controlled by capabilities.
    $keys = $exportquiznode->get_children_key_list();
    $beforekey = null;
    $i = array_search('modedit', $keys);
    if ($i === false and array_key_exists(0, $keys)) {
        $beforekey = $keys[0];
    } else if (array_key_exists($i + 1, $keys)) {
        $beforekey = $keys[$i + 1];
    }

    if (has_capability('mod/exportquiz:manage', $PAGE->cm->context)) {
        $node = navigation_node::create(get_string('groupquestions', 'exportquiz'),
                new moodle_url('/mod/exportquiz/edit.php', array('cmid' => $PAGE->cm->id)),
                navigation_node::TYPE_SETTING, null, 'mod_exportquiz_edit',
                new pix_icon('i/questions', ''));
        $exportquiznode->add_node($node, $beforekey);

        $node = navigation_node::create(get_string('createexportquiz', 'exportquiz'),
                new moodle_url('/mod/exportquiz/createquiz.php', array('id' => $PAGE->cm->id)),
                navigation_node::TYPE_SETTING, null, 'mod_exportquiz_createpdfs',
                new pix_icon('f/text', ''));
        $exportquiznode->add_node($node, $beforekey);
    }

    question_extend_settings_navigation($exportquiznode, $PAGE->cm->context)->trim_if_empty();
}

/**
 * Determine the correct number of decimal places required to format a grade.
 *
 * @param object $exportquiz The exportquiz table row, only $exportquiz->decimalpoints is used.
 * @return integer
 */
function exportquiz_get_grade_format($exportquiz) {
    if (empty($exportquiz->questiondecimalpoints)) {
        $exportquiz->questiondecimalpoints = -1;
    }

    if ($exportquiz->questiondecimalpoints == -1) {
        return $exportquiz->decimalpoints;
    }

    return $exportquiz->questiondecimalpoints;
}

/**
 * @param array $questionids of question ids.
 * @return bool whether any of these questions are used by any instance of this module.
 */
function exportquiz_questions_in_use($questionids) {
    global $DB, $CFG;
    require_once($CFG->libdir . '/questionlib.php');
    list($test, $params) = $DB->get_in_or_equal($questionids);

    // Either the questions are used in the group questions, or in results, or in template qubas.
    return $DB->record_exists_select('exportquiz_group_questions', 'questionid ' . $test, $params) ||
           question_engine::questions_in_use($questionids, new qubaid_join('{exportquiz_results} quiza',
            'quiza.usageid', '')) ||
           question_engine::questions_in_use($questionids, new qubaid_join('{exportquiz_groups} groupa',
            'groupa.templateusageid', ''));
}
