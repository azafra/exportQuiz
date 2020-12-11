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
 * Upgrade script for the exportquiz module
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


function xmldb_exportquiz_upgrade($oldversion = 0) {
    global $CFG, $THEME, $DB, $OUTPUT;

    $dbman = $DB->get_manager();

    // And upgrade begins here. For each one, you'll need one
    // Block of code similar to the next one. Please, delete
    // This comment lines once this file start handling proper
    // Upgrade code.

    // ONLY UPGRADE FROM Moodle 1.9.x (module version 2009042100) is supported.

    if ($oldversion < 2009120700) {

        // Define field counter to be added to exportquiz_i_log.
        $table = new xmldb_table('exportquiz_i_log');
        $field = new xmldb_field('counter');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'rawdata');

        // Launch add field counter.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field corners to be added to exportquiz_i_log.
        $field = new xmldb_field('corners');
        $field->set_attributes(XMLDB_TYPE_CHAR, '50', null, null, null, null, 'counter');

        // Launch add field corners.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field pdfintro to be added to exportquiz.
        $table = new xmldb_table('exportquiz');
        $field = new xmldb_field('pdfintro');
        $field->set_attributes(XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'intro');

        // Launch add field pdfintro.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2009120700, 'exportquiz');
    }

    if ($oldversion < 2010082900) {

        // Define table exportquiz_p_list to be created.
        $table = new xmldb_table('exportquiz_p_list');

        // Adding fields to table exportquiz_p_list.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('exportquiz', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, null, null, null);
        $table->add_field('list', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '1');

        // Adding keys to table exportquiz_p_list.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Launch create table for exportquiz_p_list.
        $dbman->create_table($table);

        // Define field position to be dropped from exportquiz_participants.
        $table = new xmldb_table('exportquiz_participants');
        $field = new xmldb_field('position');

        // Launch drop field position.
        $dbman->drop_field($table, $field);

        // Define field page to be dropped from exportquiz_participants.
        $table = new xmldb_table('exportquiz_participants');
        $field = new xmldb_field('page');

        // Launch drop field page.
        $dbman->drop_field($table, $field);

        // Define field list to be added to exportquiz_participants.
        $table = new xmldb_table('exportquiz_participants');
        $field = new xmldb_field('list');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '1', 'userid');

        // Launch add field list.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2010082900, 'exportquiz');
    }

    if ($oldversion < 2010090600) {

        // Define index exportquiz (not unique) to be added to exportquiz_p_list.
        $table = new xmldb_table('exportquiz_p_list');
        $index = new XMLDBIndex('exportquiz');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('exportquiz'));

        // Launch add index exportquiz.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $index = new XMLDBIndex('list');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('list'));

        // Launch add index list.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index exportquiz (not unique) to be added to exportquiz_participants.
        $table = new xmldb_table('exportquiz_participants');
        $index = new XMLDBIndex('exportquiz');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('exportquiz'));

        // Launch add index exportquiz.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $index = new XMLDBIndex('list');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('list'));

        // Launch add index list.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $index = new XMLDBIndex('userid');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('userid'));

        // Launch add index list.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2010090600, 'exportquiz');
    }

    if ($oldversion < 2011021400) {

        // Define field fileformat to be added to exportquiz.
        $table = new xmldb_table('exportquiz');
        $field = new xmldb_field('fileformat');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'timemodified');

        // Launch add field fileformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2011021400, 'exportquiz');
    }

    if ($oldversion < 2011032900) {

        // Define field page to be added to exportquiz_i_log.
        $table = new xmldb_table('exportquiz_i_log');
        $field = new xmldb_field('page');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'corners');

        // Launch add field page.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field username to be added to exportquiz_i_log.
        $field = new xmldb_field('username');
        $field->set_attributes(XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null, 'page');

        // Launch add field username.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define index username (not unique) to be added to exportquiz_i_log.
        $index = new XMLDBIndex('username');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('username'));

        // Launch add index username.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define field showgrades to be added to exportquiz.
        $table = new xmldb_table('exportquiz');
        $field = new xmldb_field('showgrades');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'fileformat');

        // Launch add field showgrades.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2011032900, 'exportquiz');
    }

    if ($oldversion < 2011081700) {
        // Define field showtutorial to be added to exportquiz.
        $table = new xmldb_table('exportquiz');
        $field = new xmldb_field('showtutorial');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'showgrades');

        // Launch add field showtutorial.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2011081700, 'exportquiz');
    }

    // ------------------------------------------------------
    // UPGRADE for Moodle 2.0 module starts here.
    // ------------------------------------------------------
    // First we do the changes to the main table 'exportquiz'.
    // ------------------------------------------------------
    if ($oldversion < 2012010100) {

        // Define field docscreated to be added to exportquiz.
        $table = new xmldb_table('exportquiz');
        $field = new xmldb_field('docscreated', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null,
                                 '0', 'questionsperpage');

        // Conditionally launch add field docscreated.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2012010100, 'exportquiz');
    }

    // Fill the new field docscreated.
    if ($oldversion < 2012010101) {

        $exportquizzes = $DB->get_records('exportquiz');
        foreach ($exportquizzes as $exportquiz) {
            $dirname = $CFG->dataroot . '/' . $exportquiz->course . '/moddata/exportquiz/' . $exportquiz->id . '/pdfs';
            // If the answer pdf file for group 1 exists then we have created the documents.
            if (file_exists($dirname . '/answer-a.pdf')) {
                $DB->set_field('exportquiz', 'docscreated', 1, array('id' => $exportquiz->id));
            }
        }
        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2012010101, 'exportquiz');
    }

    if ($oldversion < 2012010105) {

        // Define table exportquiz_reports to be created.
        $table = new xmldb_table('exportquiz_reports');

        // Adding fields to table exportquiz_reports.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('displayorder', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('lastcron', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('cron', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('capability', XMLDB_TYPE_CHAR, '255', null, null, null, null);

        // Adding keys to table exportquiz_reports.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table exportquiz_reports.
        // $table->add_index('name', XMLDB_INDEX_UNIQUE, array('name'));.

        // Conditionally launch create table for exportquiz_reports.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        if (!$DB->get_records_sql("SELECT * FROM {exportquiz_reports} WHERE name = 'overview'", array())) {
            $record = new stdClass();
            $record->name         = 'overview';
            $record->displayorder = '10000';
            $DB->insert_record('exportquiz_reports', $record);
        }
        if (!$DB->get_records_sql("SELECT * FROM {exportquiz_reports} WHERE name = 'rimport'", array())) {
            $record = new stdClass();
            $record->name         = 'rimport';
            $record->displayorder = '9000';
            $DB->insert_record('exportquiz_reports', $record);
        }

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2012010105, 'exportquiz');
    }

    // Now we create all the new tables.
    // Create table exportquiz_groups.
    if ($oldversion < 2012010200) {

        echo $OUTPUT->notification('Creating new tables', 'notifysuccess');

        // Define table exportquiz_groups to be created.
        $table = new xmldb_table('exportquiz_groups');

        // Adding fields to table exportquiz_groups.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('exportquizid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('number', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('sumgrades', XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('numberofpages', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('templateusageid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');

        // Adding keys to table exportquiz_groups.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table exportquiz_groups.
        $table->add_index('exportquizid', XMLDB_INDEX_NOTUNIQUE, array('exportquizid'));

        // Conditionally launch create table for exportquiz_groups.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2012010200, 'exportquiz');
    }

    // Create table exportquiz_group_questions.
    if ($oldversion < 2012010300) {

        // Define table exportquiz_group_questions to be created.
        $table = new xmldb_table('exportquiz_group_questions');

        // Adding fields to table exportquiz_group_questions.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('exportquizid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('exportgroupid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('questionid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('position', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '1');
        $table->add_field('pagenumber', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('usageslot', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, null, null, null);

        // Adding keys to table exportquiz_group_questions.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table exportquiz_group_questions.
        $table->add_index('exportquiz', XMLDB_INDEX_NOTUNIQUE, array('exportquizid'));

        // Conditionally launch create table for exportquiz_group_questions.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2012010300, 'exportquiz');
    }

    if ($oldversion < 2012010400) {

        // Define table exportquiz_scanned_pages to be created.
        $table = new xmldb_table('exportquiz_scanned_pages');

        // Adding fields to table exportquiz_scanned_pages.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('exportquizid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('resultid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('filename', XMLDB_TYPE_CHAR, '1000', null, null, null, null);
        $table->add_field('warningfilename', XMLDB_TYPE_CHAR, '1000', null, null, null, null);
        $table->add_field('groupnumber', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('userkey', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('pagenumber', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('time', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('error', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('info', XMLDB_TYPE_TEXT, 'medium', null, null, null, null);

        // Adding keys to table exportquiz_scanned_pages.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table exportquiz_scanned_pages.
        $table->add_index('exportquizid', XMLDB_INDEX_NOTUNIQUE, array('exportquizid'));

        // Conditionally launch create table for exportquiz_scanned_pages.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2012010400, 'exportquiz');
    }

    if ($oldversion < 2012010500) {

        // Define table exportquiz_choices to be created.
        $table = new xmldb_table('exportquiz_choices');

        // Adding fields to table exportquiz_choices.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('scannedpageid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('slotnumber', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('choicenumber', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('value', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table exportquiz_choices.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table exportquiz_choices.
        $table->add_index('scannedpageid', XMLDB_INDEX_NOTUNIQUE, array('scannedpageid'));

        // Conditionally launch create table for exportquiz_choices.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2012010500, 'exportquiz');
    }

    if ($oldversion < 2012010600) {

        // Define table exportquiz_page_corners to be created.
        $table = new xmldb_table('exportquiz_page_corners');

        // Adding fields to table exportquiz_page_corners.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('scannedpageid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('x', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('y', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('position', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);

        // Adding keys to table exportquiz_page_corners.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for exportquiz_page_corners.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2012010600, 'exportquiz');
    }

    if ($oldversion < 2012010700) {

        // Define table exportquiz_results to be created.
        $table = new xmldb_table('exportquiz_results');

        // Adding fields to table exportquiz_results.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('exportquizid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('exportgroupid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('sumgrades', XMLDB_TYPE_NUMBER, '10, 5', null, null, null, null);
        $table->add_field('usageid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('teacherid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('attendant', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timestart', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('timefinish', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('preview', XMLDB_TYPE_INTEGER, '3', XMLDB_UNSIGNED, null, null, '0');

        // Adding keys to table exportquiz_results.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for exportquiz_results.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2012010700, 'exportquiz');
    }

    if ($oldversion < 2012010800) {

        // Define table exportquiz_scanned_p_pages to be created.
        $table = new xmldb_table('exportquiz_scanned_p_pages');

        // Adding fields to table exportquiz_scanned_p_pages.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('exportquizid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('listnumber', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('filename', XMLDB_TYPE_CHAR, '1000', null, null, null, null);
        $table->add_field('time', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('status', XMLDB_TYPE_TEXT, 'small', null, XMLDB_NOTNULL, null);
        $table->add_field('error', XMLDB_TYPE_TEXT, 'small', null, null, null, null);

        // Adding keys to table exportquiz_scanned_p_pages.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for exportquiz_scanned_p_pages.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2012010800, 'exportquiz');
    }

    if ($oldversion < 2012010900) {

        // Define table exportquiz_p_choices to be created.
        $table = new xmldb_table('exportquiz_p_choices');

        // Adding fields to table exportquiz_p_choices.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('scannedppageid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('value', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table exportquiz_p_choices.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for exportquiz_p_choices.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2012010900, 'exportquiz');
    }

    if ($oldversion < 2012011000) {

        // Define table exportquiz_p_lists to be created.
        $table = new xmldb_table('exportquiz_p_lists');

        // Adding fields to table exportquiz_p_lists.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('exportquizid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('number', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '1');
        $table->add_field('filename', XMLDB_TYPE_CHAR, '1000', null, null, null, null);

        // Adding keys to table exportquiz_p_lists.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table exportquiz_p_lists.
        $table->add_index('exportquizid', XMLDB_INDEX_NOTUNIQUE, array('exportquizid'));

        // Conditionally launch create table for exportquiz_p_lists.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2012011000, 'exportquiz');
    }

    // ------------------------------------------------------
    // New we rename fields in old tables.
    // ------------------------------------------------------

    // Rename fields in exportquiz_queue table.
    if ($oldversion < 2012020100) {

        echo $OUTPUT->notification('Renaming fields in old tables.', 'notifysuccess');

        // Rename field exportquiz on table exportquiz_queue to NEWNAMEGOESHERE.
        $table = new xmldb_table('exportquiz_queue');
        $field = new xmldb_field('exportquiz');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'timefinish');

        // Launch rename field exportquiz.
        $dbman->rename_field($table, $field, 'exportquizid');

        $field = new xmldb_field('importadmin');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'id');

        // Launch rename field importadmin.
        $dbman->rename_field($table, $field, 'importuserid');

        // New status field.
        $field = new xmldb_field('status', XMLDB_TYPE_TEXT, 'small', null, null, null, 'processed', 'timefinish');

        // Conditionally launch add field status.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2012020100, 'exportquiz');
    }

    // Add and rename fields in table offlinquiz_queue_data.
    if ($oldversion < 2012020200) {

        // Define field status to be added to exportquiz_queue_data.
        $table = new xmldb_table('exportquiz_queue_data');
        $field = new xmldb_field('status', XMLDB_TYPE_TEXT, 'small', null, XMLDB_NOTNULL, null, 'ok', 'filename');

        // Conditionally launch add field status.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        } else {
            $dbman->change_field_type($table, $field);
            $dbman->change_field_precision($table, $field);
            $dbman->change_field_notnull($table, $field);
            $dbman->change_field_unsigned($table, $field);
        }

        // Add new field 'error'.
        $field = new xmldb_field('error', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'status');

        // Conditionally launch add field error.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Rename field queue to queueid.
        $field = new xmldb_field('queue', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'id');

        // Launch rename field queueid.
        $dbman->rename_field($table, $field, 'queueid');

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2012020200, 'exportquiz');
    }

    // Rename field list on table exportquiz_participants to listid.
    if ($oldversion < 2012020300) {

        // Rename field list on table exportquiz_participants to listid.
        $table = new xmldb_table('exportquiz_participants');
        $field = new xmldb_field('list', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'id');

        // Launch rename field listid.
        $dbman->rename_field($table, $field, 'listid');

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2012020300, 'exportquiz');
    }

    // Migrate the old lists of participants to the new table exportquiz_p_lists (with 's').
    if ($oldversion < 2012020400) {

        $oldplists = $DB->get_records('exportquiz_p_list');
        foreach ($oldplists as $oldplist) {
            $newplist = new StdClass();
            $newplist->exportquizid = $oldplist->exportquiz;
            $newplist->name = $oldplist->name;
            $newplist->number = $oldplist->list;
            // NOTE.
            // We don't set filename because we can always recreate the PDF files if needed.
            $newplist->id = $DB->insert_record('exportquiz_p_lists', $newplist);

            // Get all the participants linked to the old list and link them to the new list in exportquiz_p_lists.
            if ($oldparts = $DB->get_records('exportquiz_participants', array('listid' => $oldplist->id))) {
                foreach ($oldparts as $oldpart) {
                    $oldpart->listid = $newplist->id;
                    $DB->update_record('exportquiz_participants', $oldpart);
                }
            }
        }

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2012020400, 'exportquiz');
    }

    // Check if there are inconsistencies in the DB, i.e. uniqueids used by both quizzes and exportquizzes.
    if ($oldversion < 2012020410) {

        $sql = 'SELECT uniqueid
        FROM {exportquiz_attempts} qa WHERE
        EXISTS (SELECT id from {quiz_attempts} where uniqueid = qa.uniqueid)';
        $doubleids = $DB->get_fieldset_sql($sql, array());

        // For each double uniqueid create a new uniqueid and change the fields in the tables.
        // exportquiz_attempts, question_sessions and question_states.
        echo $OUTPUT->notification('Fixing ' . count($doubleids) . ' question attempt uniqueids that are not unique',
                                   'notifysuccess');

        foreach ($doubleids as $doubleid) {
            echo $doubleid . ', ';
            if ($usage = $DB->get_record('question_usages', array('id' => $doubleid))) {
                $transaction = $DB->start_delegated_transaction();
                unset($usage->id);
                $usage->id = $DB->insert_record('question_usages', $usage);

                $DB->set_field_select('exportquiz_attempts', 'uniqueid', $usage->id, 'uniqueid = :oldid',
                                      array('oldid' => $doubleid));
                $DB->set_field_select('question_states', 'attempt', $usage->id, 'attempt = :oldid',
                                      array('oldid' => $doubleid));
                $DB->set_field_select('question_sessions', 'attemptid', $usage->id, 'attemptid = :oldid',
                                      array('oldid' => $doubleid));
                $transaction->allow_commit();
            }
        }
        upgrade_mod_savepoint(true, 2012020410, 'exportquiz');
    }

    // -----------------------------------------------------
    //  Update the contextid field in question_usages (compare lib/db/upgrade.php lines 6108 following).
    // -----------------------------------------------------
    if ($oldversion < 2012020500) {

        echo $OUTPUT->notification('Fixing question usages context ID', 'notifysuccess');

        // Update the component field if necessary.
        $DB->set_field('question_usages', 'component', 'mod_exportquiz', array('component' => 'exportquiz'));

        // Populate the contextid field.
        $exportquizmoduleid = $DB->get_field('modules', 'id', array('name' => 'exportquiz'));
        $DB->execute("
                UPDATE {question_usages} SET contextid = (
                SELECT ctx.id
                FROM {context} ctx
                JOIN {course_modules} cm ON cm.id = ctx.instanceid AND cm.module = $exportquizmoduleid
                JOIN {exportquiz_attempts} quiza ON quiza.exportquiz = cm.instance
                WHERE ctx.contextlevel = " . CONTEXT_MODULE . "
                AND quiza.uniqueid = {question_usages}.id)
                WHERE (
                SELECT ctx.id
                FROM {context} ctx
                JOIN {course_modules} cm ON cm.id = ctx.instanceid AND cm.module = $exportquizmoduleid
                JOIN {exportquiz_attempts} quiza ON quiza.exportquiz = cm.instance
                WHERE ctx.contextlevel = " . CONTEXT_MODULE . "
                AND quiza.uniqueid = {question_usages}.id) IS NOT NULL
                ");

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2012020500, 'exportquiz');
    }

    // -----------------------------------------------------
    //  Now we migrate data from the old to the new tables.
    // -----------------------------------------------------

    // We have to delete redundant question instances from exportquizzes because they are incompatible with the new code.
    if ($oldversion < 2012030100) {

        echo $OUTPUT->notification('Migrating old export quizzes to new export quizzes..', 'notifysuccess');

        require_once($CFG->dirroot . '/mod/exportquiz/db/upgradelib.php');
        exportquiz_remove_redundant_q_instances();

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2012030100, 'exportquiz');
    }

    // Migrate all entries in the exportquiz_group table to the new tables exportquiz_groups  and exportquiz_group_questions.
    if ($oldversion < 2012030101) {

        echo $OUTPUT->notification('Creating new exportquiz groups', 'notifysuccess');

        $exportquizzes = $DB->get_records('exportquiz');

        $counter = 0;
        foreach ($exportquizzes as $exportquiz) {
            if (!$DB->get_records('exportquiz_groups', array('exportquizid' => $exportquiz->id))) {
                echo '.';
                $counter++;
                flush();
                ob_flush();
                if ($counter % 100 == 0) {
                    echo "<br/>\n";
                    echo $counter;
                }
                $transaction = $DB->start_delegated_transaction();
                $oldgroups = $DB->get_records('exportquiz_group', array('exportquiz' => $exportquiz->id), 'groupid ASC');
                $newgroups = array();
                foreach ($oldgroups as $oldgroup) {
                    $newgroup = new StdClass();
                    $newgroup->exportquizid = $exportquiz->id;
                    $newgroup->number = $oldgroup->groupid;
                    $newgroup->sumgrades = $oldgroup->sumgrades;
                    $newgroup->timecreated = time();
                    $newgroup->timemodified = time();
                    // First we need the ID of the new group.
                    if (!$oldid = $DB->get_field('exportquiz_groups', 'id', array('exportquizid' => $exportquiz->id,
                            'number' => $newgroup->number))) {
                            $newgroup->id = $DB->insert_record('exportquiz_groups', $newgroup);
                    } else {
                        $newgroup->id = $oldid;
                    }
                    // Now create an entry in offlinquiz_group_questions for each question in the old group layout.
                    $questions = explode(',', $oldgroup->questions);
                    $position = 1;
                    foreach ($questions as $question) {
                        $groupquestion = new StdClass();
                        $groupquestion->exportquizid = $exportquiz->id;
                        $groupquestion->exportgroupid = $newgroup->id;
                        $groupquestion->questionid = $question;
                        $groupquestion->position = $position++;
                        if (!$DB->get_record('exportquiz_group_questions', array('exportquizid' => $exportquiz->id,
                                'exportgroupid' => $newgroup->id,
                                'questionid' => $question))) {
                                $DB->insert_record('exportquiz_group_questions', $groupquestion);
                        }
                    }
                    $newgroups[] = $newgroup;

                }
                require_once($CFG->dirroot . '/mod/exportquiz/evallib.php');
                list($maxquestions, $maxanswers, $formtype, $questionsperpage) =
                    exportquiz_get_question_numbers($exportquiz, $newgroups);

                foreach ($newgroups as $newgroup) {
                    // Now we know the number of pages of the group.
                    $newgroup->numberofpages = ceil($maxquestions / ($formtype * 24));
                    $DB->update_record('exportquiz_groups', $newgroup);
                }

                $transaction->allow_commit();
            }
        }

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2012030101, 'exportquiz');
    }

    // Migrate all entries in the exportquiz_i_log table to the new tables exportquiz_scanned_pages, exportquiz_choices and.
    // exportquiz_page_corners. Also migrate the files to the new filesystem.

    // First we mark all exportquizzes s.t. we upgrade them only once. Many things can go wrong here..
    if ($oldversion < 2012030200) {
        // Define field needsilogupgrade to be added to exportquiz_attempts.
        $table = new xmldb_table('exportquiz');
        $field = new xmldb_field('needsilogupgrade', XMLDB_TYPE_INTEGER, '3', XMLDB_UNSIGNED,
                XMLDB_NOTNULL, null, '0', 'timeopen');

        // Launch add field needsilogupgrade.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $DB->set_field('exportquiz', 'needsilogupgrade', 1);

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2012030200, 'exportquiz');
    }

    // Then we mark all exportquiz_attempts to be upgraded.
    if ($oldversion < 2012030300) {
        // Define field needsupgradetonewqe to be added to exportquiz_attempts.
        $table = new xmldb_table('exportquiz_attempts');
        $field = new xmldb_field('needsupgradetonewqe', XMLDB_TYPE_INTEGER, '3', XMLDB_UNSIGNED,
                XMLDB_NOTNULL, null, '0', 'sheet');

        // Launch add field needsupgradetonewqe.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $DB->set_field('exportquiz_attempts', 'needsupgradetonewqe', 1);

        // Quiz savepoint reached.
        upgrade_mod_savepoint(true, 2012030300, 'exportquiz');
    }

    // In a first step we upgrade the exportquiz_attempts exactly like quiz_attempts (see mod/quiz/db/upgrade.php).
    if ($oldversion < 2012030400) {
        $table = new xmldb_table('question_states');
        // Echo "upgrading attempts to new question engine <br/>\n";.

        if ($dbman->table_exists($table)) {
            // NOTE: We need all attemps, also the ones with sheet=1 because the are the groups' template attempts.

            // Now update all the old attempt data.
            $oldrcachesetting = $CFG->rcache;
            $CFG->rcache = false;

            require_once($CFG->dirroot . '/mod/exportquiz/db/upgradelib.php');

            $upgrader = new exportquiz_attempt_upgrader();
            $upgrader->convert_all_quiz_attempts();

            $CFG->rcache = $oldrcachesetting;
        }

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2012030400, 'exportquiz');
    }

    // Then we mark all exportquiz_attempts to be upgraded.
    if ($oldversion < 2012030500) {
        // Define field resultid to be added to exportquiz_attempts for later reference.
        set_time_limit(3000);

        $table = new xmldb_table('exportquiz_attempts');
        $field = new xmldb_field('resultid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');

        // Launch add field resultid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Quiz savepoint reached.
        upgrade_mod_savepoint(true, 2012030500, 'exportquiz');
    }

    // In a second step we convert all exportquiz_attempts into exportquiz_results and also upgrade the ilog table.
    if ($oldversion < 2012060101) {

        require_once($CFG->dirroot . '/mod/exportquiz/db/upgradelib.php');

        $oldrcachesetting = $CFG->rcache;
        $CFG->rcache = false;

        $upgrader = new exportquiz_ilog_upgrader();
        $upgrader->convert_all_exportquiz_attempts();

        $CFG->rcache = $oldrcachesetting;
        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2012060101, 'exportquiz');
    }

    if ($oldversion < 2012060105) {

        // Changing type of field grade on table exportquiz_q_instances to number.
        $table = new xmldb_table('exportquiz_q_instances');
        $field = new xmldb_field('grade', XMLDB_TYPE_NUMBER, '12, 7', null, XMLDB_NOTNULL, null, '0', 'question');

        // Launch change of type for field grade.
        $dbman->change_field_type($table, $field);
        // Launch change of precision for field grade.
        $dbman->change_field_precision($table, $field);

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2012060105, 'exportquiz');
    }

    if ($oldversion < 2012121200) {

        // Define field introformat to be added to exportquiz.
        $table = new xmldb_table('exportquiz');
        $field = new xmldb_field('introformat', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'intro');

        // Conditionally launch add field introformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2012121200, 'exportquiz');
    }

    if ($oldversion < 2013012400) {

        // Define field info to be added to exportquiz_queue.
        $table = new xmldb_table('exportquiz_queue');
        $field = new xmldb_field('info', XMLDB_TYPE_TEXT, 'medium', null, null, null, null, 'status');

        // Conditionally launch add field info.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2013012400, 'exportquiz');
    }

    if ($oldversion < 2013012410) {

        // Define field info to be added to exportquiz_queue_data.
        $table = new xmldb_table('exportquiz_queue_data');
        $field = new xmldb_field('info', XMLDB_TYPE_TEXT, 'medium', null, null, null, null, 'error');

        // Conditionally launch add field info.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2013012410, 'exportquiz');
    }

    if ($oldversion < 2013012500) {

        // Changing type of field grade on table exportquiz to int.
        $table = new xmldb_table('exportquiz');
        $field = new xmldb_field('grade', XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, '0', 'time');

        // Launch change for field grade.
        $dbman->change_field_type($table, $field);
        $dbman->change_field_precision($table, $field);
        $dbman->change_field_unsigned($table, $field);

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2013012500, 'exportquiz');
    }

    if ($oldversion < 2013041600) {

        // Rename field question on table exportquiz_q_instances to questionid.
        $table = new xmldb_table('exportquiz_q_instances');
        $field = new xmldb_field('question', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'exportquiz');

        // Launch rename field question.
        $dbman->rename_field($table, $field, 'questionid');

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2013041600, 'exportquiz');
    }

    if ($oldversion < 2013041601) {

        // Rename field exportquiz on table exportquiz_q_instances to exportquizid.
        $table = new xmldb_table('exportquiz_q_instances');
        $field = new xmldb_field('exportquiz', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');

        // Launch rename field exportquiz.
        $dbman->rename_field($table, $field, 'exportquizid');

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2013041601, 'exportquiz');
    }

    if ($oldversion < 2013061300) {

        // Define table exportquiz_hotspots to be created.
        $table = new xmldb_table('exportquiz_hotspots');

        // Adding fields to table exportquiz_hotspots.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('scannedpageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('x', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, null);
        $table->add_field('y', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, null);
        $table->add_field('blank', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
        $table->add_field('time', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table exportquiz_hotspots.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table exportquiz_hotspots.
        $table->add_index('scannedpageididx', XMLDB_INDEX_NOTUNIQUE, array('scannedpageid'));

        // Conditionally launch create table for exportquiz_hotspots.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2013061300, 'exportquiz');
    }

    if ($oldversion < 2013110800) {

        // Define field timecreated to be added to exportquiz_queue.
        $table = new xmldb_table('exportquiz_queue');
        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'importuserid');

        // Conditionally launch add field timecreated.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2013110800, 'exportquiz');
    }

    if ($oldversion < 2013112500) {

        // Define index exportquiz_userid_idx (not unique) to be added to exportquiz_results.
        $table = new xmldb_table('exportquiz_results');
        $index = new xmldb_index('exportquiz_userid_idx', XMLDB_INDEX_NOTUNIQUE, array('userid'));

        // Conditionally launch add index exportquiz_userid_idx.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2013112500, 'exportquiz');
    }
    
    // Moodle v2.8.5+ release upgrade line.
    // Put any upgrade step following this.
    
    if ($oldversion < 2015060500) {
    
        // Rename field page on table exportquiz_group_questions to NEWNAMEGOESHERE.
        $table = new xmldb_table('exportquiz_group_questions');
        $field = new xmldb_field('pagenumber', XMLDB_TYPE_INTEGER, '4', null, null, null, null, 'position');
    
        // Launch rename field page.
        $dbman->rename_field($table, $field, 'page');
    
        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2015060500, 'exportquiz');
    }
    
    if ($oldversion < 2015060501) {
    
        // Rename field page on table exportquiz_group_questions to NEWNAMEGOESHERE.
        $table = new xmldb_table('exportquiz_group_questions');
        $field = new xmldb_field('usageslot', XMLDB_TYPE_INTEGER, '4', null, null, null, null, 'position');
    
        // Launch rename field page.
        $dbman->rename_field($table, $field, 'slot');
    
        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2015060501, 'exportquiz');
    }
    
    if ($oldversion < 2015060502) {
    
        // Define field maxmark to be added to exportquiz_group_questions.
        $table = new xmldb_table('exportquiz_group_questions');
        $field = new xmldb_field('maxmark', XMLDB_TYPE_NUMBER, '12, 7', null, XMLDB_NOTNULL, null, '1', 'slot');
    
        // Conditionally launch add field maxmark.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    
        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2015060502, 'exportquiz');
    }
    
    if ($oldversion < 2015060902) {
    
        // This upgrade migrates old exportquiz_q_instances grades (maxgrades) to new 
        // maxmark field in exportquiz_group_questions.
        // It also deletes group questions with questionid 0 (pagebreaks) and inserts the 
        // correct page number instead. 
        
        $numexportquizzes = $DB->count_records('exportquiz');
        if ($numexportquizzes > 0) {
            $pbar = new progress_bar('exportquizquestionstoslots', 500, true);
            $pbar->create();
            $pbar->update(0, $numexportquizzes,
                        "Upgrading exportquiz group questions - {0}/{$numexportquizzes}.");

            $numberdone = 0;
            $exportquizzes = $DB->get_recordset('exportquiz', null, 'id', 'id, numgroups');
            foreach ($exportquizzes as $exportquiz) {
                $transaction = $DB->start_delegated_transaction();

                $groups = $DB->get_records('exportquiz_groups', array('exportquizid' => $exportquiz->id),
                        'number', '*');
                $instancesraw = $DB->get_records('exportquiz_q_instances',
                        array('exportquizid' => $exportquiz->id));
                
                $questioninstances = array();
                foreach ($instancesraw as $instance) {
                	if (!array_key_exists($instance->questionid, $questioninstances)) {
                		$questioninstances[$instance->questionid] = $instance;
                	}
                }
                
                foreach ($groups as $group) {
                    $groupquestions = $DB->get_records('exportquiz_group_questions',
                            array('exportquizid' => $exportquiz->id, 'exportgroupid' => $group->id), 'position');
                    // For every group we start on page 1.
                    $currentpage = 1;
                    $currentslot = 1;
                    foreach ($groupquestions as $groupquestion) {
                        $needsupdate = false; 
                        if ($groupquestion->questionid == 0) {
                            // We remove the old pagebreaks with questionid==0.
                            $DB->delete_records('exportquiz_group_questions', array('id' => $groupquestion->id));
                            $currentpage++;
                            continue;
                        }
                        // If the maxmarks in the question instances differs from the default maxmark (1)
                        // of the exportquiz_group_questions then change it.
                        if (array_key_exists($groupquestion->questionid, $questioninstances)
                            && ($maxmark = floatval($questioninstances[$groupquestion->questionid]->grade))
                            && abs(floatval($groupquestion->maxmark) - $maxmark) > 0.001) {
                                $groupquestion->maxmark = $maxmark;
                                $needsupdate = true;
                        }
                        // If the page number is not correct, then change it.
                        if ($groupquestion->page != $currentpage) {
                            $groupquestion->page = $currentpage;
                            $needsupdate = true;
                        }
                        // If the slot is not set, then fill it.
                        if (!$groupquestion->slot) {
	                        $groupquestion->slot = $currentslot;
    	                    $needsupdate = true;
                        }
                        
                        if ($needsupdate) {
                            $DB->update_record('exportquiz_group_questions', $groupquestion);
                        }
                        $currentslot++;
                    }
                }    

                // Done with this exportquiz. Update progress bar.
                $numberdone++;
                $pbar->update($numberdone, $numexportquizzes,
                        "Upgrading exportquiz group questions - {$numberdone}/{$numexportquizzes}.");

                $transaction->allow_commit();
            }
        }
        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2015060902, 'exportquiz');
    }
    
    //Lo único que se han añadido han sido tres nuevos campos a tener en cuenta
    if ($oldversion < 2020012900) {
    
        // Define table exportquiz.
        $table = new xmldb_table('exportquiz');
        
        // Defino el nuevo campo que se añadira despues del campo heading
        $field = new xmldb_field('campo_libre_1');
        $field->set_attributes(XMLDB_TYPE_CHAR, '20', null, XMLDB_NULL, null, null, 'heading');

        // Launch add field
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Defino el nuevo campo que se añadira despues del campo campo_libre_1
        $field = new xmldb_field('campo_libre_2');
        $field->set_attributes(XMLDB_TYPE_CHAR, '20', null, XMLDB_NULL, null, null, 'campo_libre_1');

        // Launch add field
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Defino el nuevo campo que se añadira despues del campo campo_libre_2
        $field = new xmldb_field('campo_libre_3');
        $field->set_attributes(XMLDB_TYPE_CHAR, '20', null, XMLDB_NULL, null, null, 'campo_libre_2');

        // Launch add field
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // exportquiz savepoint reached.
        upgrade_mod_savepoint(true, 2020012900, 'exportquiz');
    }
    
    
    // TODO migrate old exportquiz_q_instances maxmarks to new maxmark field in exportquiz_group_questions.
    // TODO migrate  exportquiz_group_questions to fill in page field correctly. For every group use the 
    //      position field to find new pages and insert them.
    //      Adapt exportquiz code to handle missing zeros as pagebreaks.

    return true;
}
