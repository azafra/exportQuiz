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
 * The mod_exportquiz results regraded event.
 *
 * @package    mod_exportquiz
 * @author     Jose Manuel Ventura Martínez
 * @copyright  2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @since      Moodle 2.7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_exportquiz\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_exportquiz results regraded event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int exportquizid: the id of the exportquiz.
 * }
 *
 * @package    mod_exportquiz
 * @since      Moodle 2.7
 * @copyright  2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class results_regraded extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'exportquiz';
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventresultsregraded', 'mod_exportquiz');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has regraded " . $this->other['numberofresults'] .
         " result(s) in the export quiz with the course module id '$this->contextinstanceid'.";
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/exportquiz/report.php', array('id' => $this->objectid, 'mode' => 'regrade'));
    }

    /**
     * Return the legacy event log data.
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        return array($this->courseid, 'exportquiz', 'regrade', 'report.php?mode=regrade&id=' . $this->objectid,
            $this->other['exportquizid'], $this->contextinstanceid);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['numberofresults'])) {
            throw new \coding_exception('The \'numberofresults\' value must be set in other.');
        }

        if (!isset($this->other['exportquizid'])) {
            throw new \coding_exception('The \'exportquizid\' value must be set in other.');
        }
    }
}
