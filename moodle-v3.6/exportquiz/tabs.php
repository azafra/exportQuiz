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

if (empty($exportquiz)) {
    print_error('export exportquiz not defined for tab navigation');
}
if (!isset($currenttab)) {
    $currenttab = '';
}
if (!isset($exportquizcm)) {
    $exportquizcm = get_coursemodule_from_instance('exportquiz', $exportquiz->id);
}

$context = context_module::instance($exportquizcm->id);

if (!isset($contexts)) {
    $contexts = new question_edit_contexts($context);
}
$tabs = array();
$row  = array();
$inactive = array();
$activated = array();

if (has_capability('mod/exportquiz:view', $context)) {
    $row[] = new tabobject('info', "$CFG->wwwroot/mod/exportquiz/view.php?q=$exportquiz->id", get_string('info', 'exportquiz'));
}
if (has_capability('mod/exportquiz:manage', $context)) {
    $row[] = new tabobject('editq', "$CFG->wwwroot/mod/exportquiz/edit.php?cmid=$cm->id",
            get_string('groupquestions', 'exportquiz'));
}
if (has_capability('mod/exportquiz:createexportquiz', $context)) {
    $row[] = new tabobject('createexportquiz', "$CFG->wwwroot/mod/exportquiz/createquiz.php?q=$exportquiz->id",
            get_string('createexportquiz', 'exportquiz'));
}

if ($currenttab != 'info' || count($row) != 1) {
    $tabs[] = $row;
}


if ($currenttab == 'createexportquiz' and isset($mode)) {
    $inactive[] = 'createexportquiz';
    $activated[] = 'createexportquiz';

    $createlist = array ('preview', 'createpdfs');

    $row  = array();
    $currenttab = '';
    foreach ($createlist as $createtab) {
        $row[] = new tabobject($createtab,
                "$CFG->wwwroot/mod/exportquiz/createquiz.php?q=$exportquiz->id&amp;mode=$createtab",
        get_string($createtab, 'exportquiz'));
        if ($createtab == $mode) {
            $currenttab = $createtab;
        }
    }
    if ($currenttab == '') {
        $currenttab = 'preview';
    }
    $tabs[] = $row;
}


if ($currenttab == 'editq' and isset($mode)) {
    $inactive[] = 'editq';
    $activated[] = 'editq';

    $row  = array();
    $currenttab = $mode;

    $strexportquizzes = get_string('modulenameplural', 'exportquiz');
    $strexportquiz = get_string('modulename', 'exportquiz');
    $streditingexportquiz = get_string("editinga", "moodle", $strexportquiz);
    $strupdate = get_string('updatethis', 'moodle', $strexportquiz);

    $row[] = new tabobject('edit', new moodle_url($thispageurl,
            array('gradetool' => 0)), get_string('editingexportquiz', 'exportquiz'));
    $row[] = new tabobject('grade', new moodle_url($thispageurl,
            array('gradetool' => 1)), get_string('gradingexportquiz', 'exportquiz'));

    $tabs[] = $row;
}


print_tabs($tabs, $currenttab, $inactive, $activated);
