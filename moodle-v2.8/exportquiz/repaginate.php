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


require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/exportquiz/locallib.php');
require_once($CFG->dirroot . '/mod/exportquiz/exportquiz.class.php');

$cmid = required_param('cmid', PARAM_INT);
$exportquizid = required_param('exportquizid', PARAM_INT);
$exportgroupid = required_param('exportgroupid', PARAM_INT);
$slotnumber = required_param('slot', PARAM_INT);
$repagtype = required_param('repag', PARAM_INT);

require_sesskey();
$exportquizobj = exportquiz::create($exportquizid, $exportgroupid);
$group = $DB->get_record('exportquiz_groups', array('id' => $exportgroupid));

require_login($exportquizobj->get_course(), false, $exportquizobj->get_cm());
require_capability('mod/exportquiz:manage', $exportquizobj->get_context());

if (exportquiz_has_scanned_pages($exportquizid)) {
    $reportlink = exportquiz_attempt_summary_link_to_reports($exportquizobj->get_exportquiz(),
                    $exportquizobj->get_cm(), $exportquizobj->get_context());
    throw new \moodle_exception('cannoteditafterattempts', 'exportquiz',
            new moodle_url('/mod/exportquiz/edit.php', array('cmid' => $cmid)), $reportlink);
}

$slotnumber++;
$repage = new \mod_exportquiz\repaginate($exportquizid, $exportgroupid);
$repage->repaginate_slots($slotnumber, $repagtype);

exportquiz_delete_template_usages($exportquizobj->get_exportquiz());

$structure = $exportquizobj->get_structure();
$slots = $structure->refresh_page_numbers_and_update_db($structure->get_exportquiz());

redirect(new moodle_url('edit.php',
    array('cmid' => $exportquizobj->get_cmid(),
          'groupnumber' => $group->number)));
