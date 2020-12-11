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
 * The file defines some subclasses that can be used when you are building
 * a report like the overview or responses report.
 *
 * @package       mod
 * @subpackage    exportquiz
 * @author        Jose Manuel Ventura Martínez
 * @copyright     2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @since         Moodle 2.1
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 **/

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/tablelib.php');

class exportquiz_selectall_table extends flexible_table {

    protected $reportscript;
    protected $params;


    public function __construct($uniqueid, $reportscript, $params) {
        parent::__construct($uniqueid);
        $this->reportscript = $reportscript;
        $this->params = $params;
    }

    public function print_nothing_to_display() {
        global $OUTPUT;
        return;
    }

    public function wrap_html_start() {

        echo '<div id="tablecontainer" class="centerbox">';
        echo '<form id="reportform" method="post" action="'. $this->reportscript .
             '" onsubmit="return confirm(\'' . $this->params['strreallydel'] . '\');">';
        echo ' <div>';

        foreach ($this->params as $name => $value) {
            echo '<input type="hidden" name="' . $name .'" value="' . $value . '" />';
        }
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';
        echo '  <center>';
    }

    public function wrap_html_finish() {
        $strselectall = get_string('selectall', 'exportquiz');
        $strselectnone = get_string('selectnone', 'exportquiz');

        echo '<table id="commands">';
        echo '<tr><td>';
        echo '<a href="javascript:select_all_in(\'FORM\',null, \'reportform\');">'. $strselectall . '</a> / ';
        echo '<a href="javascript:deselect_all_in(\'FORM\',null,\'reportform\');">' . $strselectnone . '</a> ';
        echo '&nbsp;&nbsp;';
        echo '<input type="submit" value="'.get_string('deleteselectedpages', 'exportquiz_rimport').'"/>';
        echo '</td></tr></table>';
        echo '  </center>';
        // Close form.
        echo ' </div>';
        echo '</form></div>';
    }
} // End class.

class exportquiz_partlist_table extends exportquiz_selectall_table {

    public function wrap_html_finish() {
        $strselectall = get_string('selectall', 'exportquiz');
        $strselectnone = get_string('selectnone', 'exportquiz');

        echo '<table id="commands">';
        echo '<tr><td>';
        echo '<a href="javascript:select_all_in(\'FORM\',null, \'reportform\');">'. $strselectall . '</a> / ';
        echo '<a href="javascript:deselect_all_in(\'FORM\',null,\'reportform\');">' . $strselectnone . '</a> ';
        echo '&nbsp;&nbsp;';
        $options = array('check' => get_string('checkparts', 'exportquiz'),
                'uncheck' => get_string('uncheckparts', 'exportquiz'));
        echo html_writer::select($options, 'action', '', array('' => 'choosedots'),
                array('onchange' => 'this.form.submit(); return true;'));

        // Choose_from_menu($options, 'action', '', get_string('withselected', 'exportquiz'),
        // 'if(this.selectedIndex > 0) submitFormById(\'participantform\');', '', true).
        echo '<noscript id="noscriptmenuaction" style="display: inline;"><div>';
        echo '<input type="submit" value="'.get_string('go').'" /></div></noscript>';
        echo '<script type="text/javascript">' . "\n<!--\n" .
            'document.getElementById("noscriptmenuaction").style.display = "none";'."\n-->\n".'</script>';
        echo '</td></tr></table>';
        echo '  </center>';
        // Close form.
        echo ' </div>';
        echo '</form></div>';

    }

    protected function print_one_initials_bar($alpha, $current, $class, $title, $urlvar) {
        echo html_writer::start_tag('div', array('class' => 'initialbar ' . $class)) .
        $title . ' : ';
        if ($current) {
            echo html_writer::link($this->baseurl->out(false, array($urlvar => '')), get_string('all'));
        } else {
            echo html_writer::tag('strong', get_string('all'));
        }
        echo '&nbsp;';

        foreach ($alpha as $letter) {
            if ($letter === $current) {
                echo html_writer::tag('strong', $letter);
            } else {
                echo html_writer::link($this->baseurl->out(false, array($urlvar => $letter)), $letter);
            }
            echo '&nbsp;';
        }

        echo html_writer::end_tag('div');
    }


}