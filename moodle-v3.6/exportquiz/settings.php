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

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/exportquiz/lib.php');
    require_once($CFG->dirroot.'/mod/exportquiz/settingslib.php');


    // Introductory explanation that all the settings are defaults for the add exportquiz form.
    $settings->add(new admin_setting_heading('exportquizintro', '', get_string('configintro', 'exportquiz')));
    
    // User identification.
    $settings->add(new admin_setting_configtext_user_formula('exportquiz/useridentification',
            get_string('useridentification', 'exportquiz'), get_string('configuseridentification', 'exportquiz'),
            '[7]=idnumber' , PARAM_RAW, 30));


    // Shuffle questions.
    $settings->add(new admin_setting_configcheckbox('exportquiz/shufflequestions',
            get_string('shufflequestions', 'exportquiz'), get_string('configshufflequestions', 'exportquiz'),
            0));

    // Shuffle within questions.
    $settings->add(new admin_setting_configcheckbox('exportquiz/shuffleanswers',
            get_string('shufflewithin', 'exportquiz'), get_string('configshufflewithin', 'exportquiz'),
            1));

    // Review options.
    $settings->add(new admin_setting_heading('reviewheading',
            get_string('reviewoptionsheading', 'exportquiz'), ''));

    foreach (mod_exportquiz_admin_review_setting::fields() as $field => $name) {
        $default = mod_exportquiz_admin_review_setting::all_on();
        $forceduring = null;
        if ($field == 'attempt') {
            $forceduring = true;
        } else if ($field == 'overallfeedback') {
            $default = $default ^ mod_exportquiz_admin_review_setting::DURING;
            $forceduring = false;
        }
        $settings->add(new mod_exportquiz_admin_review_setting('exportquiz/review' . $field,
                $name, '', $default, $forceduring));
    }


    // Decimal places for overall grades.
    $settings->add(new admin_setting_heading('gradingheading',
            get_string('gradingoptionsheading', 'exportquiz'), ''));

    $options = array();
    for ($i = 0; $i <= 3; $i++) {
        $options[$i] = $i;
    }
    $settings->add(new admin_setting_configselect('exportquiz/decimalpoints',
            get_string('decimalplaces', 'exportquiz'), get_string('configdecimalplaces', 'exportquiz'),
            2, $options));

}
