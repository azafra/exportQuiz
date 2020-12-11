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

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/exportquiz/locallib.php');


class mod_exportquiz_mod_form extends moodleform_mod {

    protected function definition() {
        global $COURSE, $CFG, $DB, $PAGE;

        $exportquizconfig = get_config('exportquiz');

        $exportquiz = null;
        if (!empty($this->_instance)) {
            $exportquiz = $DB->get_record('exportquiz', array('id' => $this->_instance));
        }

        $mform = $this->_form;

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('html', '<center>' . get_string('pluginname', 'exportquiz') . '</center>');

        // Name.
        $mform->addElement('text', 'name', get_string('name', 'exportquiz'), array('size' => '64'));

        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');

        // Introduction.
        $this->add_intro_editor(false, get_string('introduction', 'exportquiz'));


        if (!$exportquiz || !$exportquiz->docscreated) {
            for ($i = 1; $i <= 5; $i++) {
                $groupmenu[$i] = "$i";
            }
            $mform->addElement('select', 'numgroups', get_string('numbergroups', 'exportquiz'), $groupmenu);
            $mform->setDefault('numgroups', 1);
        } else {
            $mform->addElement('static', 'numgroups', get_string('numbergroups', 'exportquiz'));
        }

        // Only allow to change shufflequestions and shuffleanswers if the PDF documents have not been created.
        if (!$exportquiz || !$exportquiz->docscreated) {
            $attribs = '';
        } else {
            $attribs = ' disabled="disabled"';
        }

        $mform->addElement('selectyesno', 'shufflequestions', get_string("shufflequestions", "exportquiz"), $attribs);
        $mform->setDefault('shufflequestions', $exportquizconfig->shufflequestions);

        $mform->addElement('selectyesno', 'shuffleanswers', get_string("shufflewithin", "exportquiz"), $attribs);
        $mform->addHelpButton('shuffleanswers', 'shufflewithin', 'exportquiz');
        $mform->setDefault('shuffleanswers', $exportquizconfig->shuffleanswers);

        unset($options);
        $options = array();
        for ($i = 0; $i <= 3; $i++) {
            $options[$i] = $i;
        }
        $mform->addElement('select', 'decimalpoints', get_string('decimalplaces', 'exportquiz'), $options);
        $mform->addHelpButton('decimalpoints', 'decimalplaces', 'exportquiz');
        $mform->setDefault('decimalpoints', $exportquizconfig->decimalpoints);

        // -------------------------------------------------------------------------
        $mform->addElement('header', 'layouthdr', get_string('formsheetsettings', 'exportquiz'));


        // ------------------------------------------------------------------------------
        if ($exportquiz && $exportquiz->docscreated) {
            $mform->addElement('html', "<center><a style='color:red;' href=\"" . $CFG->wwwroot .
                    "/mod/exportquiz/createquiz.php?mode=createpdfs&amp;q=$exportquiz->id\">" .
                    get_string('formsexist', 'exportquiz')."</a></center>");
        }
        if (!$exportquiz || !$exportquiz->docscreated) {
            $mform->addElement('editor', 'pdfintro', get_string('pdfintro', 'exportquiz'), array('rows' => 20),
                     exportquiz_get_editor_options($this->context));
        } else {
            $mform->addElement('static', 'pdfintro', get_string('pdfintro', 'exportquiz'), $exportquiz->pdfintro);
        }
        
        $mform->setType('pdfintro', PARAM_RAW);
        //$mform->setDefault('pdfintro', array('text' => get_string('pdfintrotext', 'exportquiz')));
        $mform->addHelpButton('pdfintro', 'pdfintro', 'exportquiz');

        unset($options);
        $options[8] = 8;
        $options[9] = 9;
        $options[10] = 10;
        $options[12] = 12;
        $options[14] = 14;
        $mform->addElement('select', 'fontsize', get_string('fontsize', 'exportquiz'), $options, $attribs);
        $mform->setDefault('fontsize', 10);

        $options = array();
        $options[EXPORTQUIZ_PDF_FORMAT] = 'PDF';
        $options[EXPORTQUIZ_DOCX_FORMAT] = 'DOCX';
        $mform->addElement('select', 'fileformat', get_string('fileformat', 'exportquiz'), $options, $attribs);
        $mform->addHelpButton('fileformat', 'fileformat', 'exportquiz');
        $mform->setDefault('fileformat', 0);

        $mform->addElement('selectyesno', 'showgrades', get_string("showgrades", "exportquiz"), $attribs);
        $mform->addHelpButton('showgrades', "showgrades", "exportquiz");
        
        $mform->addElement('selectyesno', 'heading', get_string("heading", "exportquiz"), $attribs);
        
        // Try to insert student view for teachers.

        $language = current_language();

        $module = array(
                'name'      => 'mod_exportquiz_mod_form',
                'fullpath'  => '/mod/exportquiz/mod_form.js',
                'requires'  => array(),
                'strings'   => array(),
                'async'     => false,
        );

        $PAGE->requires->jquery();
        $PAGE->requires->js('/mod/exportquiz/mod_form.js');

        // -------------------------------------------------------------------------------
        $this->standard_coursemodule_elements();

        // -------------------------------------------------------------------------------
        $this->add_action_buttons();
    }

    /**
     * (non-PHPdoc)
     * @see moodleform_mod::data_preprocessing()
     */
    public function data_preprocessing(&$toform) {
        if (!empty($this->_feedbacks)) {
            $key = 0;
            foreach ($this->_feedbacks as $feedback) {
                $toform['feedbacktext['.$key.']'] = $feedback->feedbacktext;
                if ($feedback->mingrade > 0) {
                    $toform['feedbackboundaries['.$key.']'] = (100.0 * $feedback->mingrade / $toform['grade']) . '%';
                }
                $key++;
            }

        }

        // Set the pdfintro text.
        if ($this->current->instance) {
            if (!$toform['docscreated']) {
                // Editing an existing pdfintro - let us prepare the added editor elements (intro done automatically).
                $draftitemid = file_get_submitted_draft_itemid('pdfintro');
                $text = file_prepare_draft_area($draftitemid, $this->context->id,
                                        'mod_exportquiz', 'pdfintro', false,
                                        exportquiz_get_editor_options($this->context),
                                        $toform['pdfintro']);
                // $default_values['pdfintro']['format'] = $default_values['pdfintro'];
                $toform['pdfintro'] = array();
                $toform['pdfintro']['text'] = $text; 
                $toform['pdfintro']['format'] = editors_get_preferred_format();
                $toform['pdfintro']['itemid'] = $draftitemid;
            }
        } else {
            // Adding a new feedback instance.
            $draftitemid = file_get_submitted_draft_itemid('pdfintro');

            // No context yet, itemid not used.
            file_prepare_draft_area($draftitemid, null, 'mod_exportquiz', 'pdfintro', false);
            $toform['pdfintro'] = array();
            $toform['pdfintro']['format'] = editors_get_preferred_format();
            $toform['pdfintro']['itemid'] = $draftitemid;
        }
        
        if (empty($toform['timelimit'])) {
            $toform['timelimitenable'] = 0;
        } else {
            $toform['timelimitenable'] = 1;
        }

        if (isset($toform['review'])) {
            $review = (int) $toform['review'];
            unset($toform['review']);

            $toform['attemptclosed'] = $review & EXPORTQUIZ_REVIEW_ATTEMPT;
            $toform['correctnessclosed'] = $review & EXPORTQUIZ_REVIEW_CORRECTNESS;
            $toform['marksclosed'] = $review & EXPORTQUIZ_REVIEW_MARKS;
            $toform['specificfeedbackclosed'] = $review & EXPORTQUIZ_REVIEW_SPECIFICFEEDBACK;
            $toform['generalfeedbackclosed'] = $review & EXPORTQUIZ_REVIEW_GENERALFEEDBACK;
            $toform['rightanswerclosed'] = $review & EXPORTQUIZ_REVIEW_RIGHTANSWER;
            $toform['sheetclosed'] = $review & EXPORTQUIZ_REVIEW_SHEET;
            $toform['gradedsheetclosed'] = $review & EXPORTQUIZ_REVIEW_GRADEDSHEET;
        }
    }

}
