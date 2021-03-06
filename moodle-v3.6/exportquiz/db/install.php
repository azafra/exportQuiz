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
 * The reports interface for exportquizzes
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
 * Code run after the exportquiz module database tables have been created.
 */
function xmldb_exportquiz_install() {
    global $DB;

    $record = new stdClass();
    $record->name         = 'overview';
    $record->displayorder = '10000';
    $DB->insert_record('exportquiz_reports', $record);

    $record = new stdClass();
    $record->name         = 'rimport';
    $record->displayorder = '9000';
    $DB->insert_record('exportquiz_reports', $record);

    $record = new stdClass();
    $record->name         = 'regrade';
    $record->displayorder = '6000';
    $DB->insert_record('exportquiz_reports', $record);
}
