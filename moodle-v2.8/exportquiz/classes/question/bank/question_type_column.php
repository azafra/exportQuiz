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

namespace mod_exportquiz\question\bank;
defined('MOODLE_INTERNAL') || die();

/**
 * A column type for the name of the question type.
 *
 * @package       mod
 * @subpackage    exportquiz
 * @author        Jose Manuel Ventura Martínez
 * @copyright     2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @since         Moodle 2.8+
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_type_column extends \core_question\bank\question_type_column {
    protected function display_content($question, $rowclasses) {
        $contains = $this->qbank->exportquiz_contains($question->id);
        if ($contains) {
            echo '<span class="greyed">'; 
        }
        echo print_question_icon($question);
        if ($contains) {
            echo '</span>';
        }
    }

}
