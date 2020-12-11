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
 * The mod_exportquiz course module viewed event.
 *
 * @package    mod_exportquiz
 * @author     Manuel Tejero Martín
 * @copyright  2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @since      Moodle 2.7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_exportquiz\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_exportquiz course module viewed event class.
 *
 * @package    mod_exportquiz
 * @since      Moodle 2.7
 * @copyright  2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_module_viewed extends \core\event\course_module_viewed {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['objecttable'] = 'exportquiz';
        parent::init();
    }
}
