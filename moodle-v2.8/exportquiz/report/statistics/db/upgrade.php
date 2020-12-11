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
 * Post-install script for the exportquiz statistics report.
 *
 * @package   exportquiz_statistics
 * @copyright 2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * exportquiz statistics report upgrade code.
 */
function xmldb_exportquiz_statistics_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Moodle v2.2.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2012061800) {

        // Changing type of field subqid on table exportquiz_question_response_stats to char.
        $table = new xmldb_table('exportquiz_question_response_stats');
        $field = new xmldb_field('subqid', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null, 'questionid');

        // Launch change of type for field subqid.
        $dbman->change_field_type($table, $field);

        // Statistics savepoint reached.
        upgrade_plugin_savepoint(true, 2012061800, 'exportquiz', 'statistics');
    }

    if ($oldversion < 2012061801) {

        // Changing type of field aid on table exportquiz_question_response_stats to char.
        $table = new xmldb_table('exportquiz_question_response_stats');
        $field = new xmldb_field('aid', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'subqid');

        // Launch change of type for field aid.
        $dbman->change_field_type($table, $field);

        // Statistics savepoint reached.
        upgrade_plugin_savepoint(true, 2012061801, 'exportquiz', 'statistics');
    }

    // Moodle v2.3.0 release upgrade line
    // Put any upgrade step following this.

    // Moodle v2.4.0 release upgrade line
    // Put any upgrade step following this.
    if ($oldversion < 2013040300) {

        // Define field exportgroupid to be added to exportquiz_statistics.
        $table = new xmldb_table('exportquiz_statistics');
        $field = new xmldb_field('exportgroupid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'exportquizid');

        // Conditionally launch add field exportgroupid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Statistics savepoint reached.
        upgrade_plugin_savepoint(true, 2013040300, 'exportquiz', 'statistics');
    }

    if ($oldversion < 2013120400) {

        // Define field correct to be added to exportquiz_q_statistics.
        $table = new xmldb_table('exportquiz_q_statistics');
        $field = new xmldb_field('correct', XMLDB_TYPE_INTEGER, '4', null, null, null, '0', 'randomguessscore');

        // Conditionally launch add field correct.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('partially', XMLDB_TYPE_INTEGER, '4', null, null, null, '0', 'correct');

        // Conditionally launch add field partially.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('wrong', XMLDB_TYPE_INTEGER, '4', null, null, null, '0', 'partially');

        // Conditionally launch add field wrong.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Statistics savepoint reached.
        upgrade_plugin_savepoint(true, 2013120400, 'exportquiz', 'statistics');
    }

    return true;
}
