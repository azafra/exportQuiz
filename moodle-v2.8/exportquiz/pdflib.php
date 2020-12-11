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

require_once($CFG->libdir . '/moodlelib.php');
require_once($CFG->libdir . '/pdflib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/type/questionbase.php');
require_once($CFG->dirroot . '/filter/tex/filter.php');
require_once($CFG->dirroot . '/mod/exportquiz/html2text.php');

class exportquiz_pdf extends pdf
{
    /**
     * Containing the current page buffer after checkpoint() was called.
     */
    private $checkpoint;

    public function checkpoint() {
        $this->checkpoint = $this->getPageBuffer($this->page);
    }

    public function backtrack() {
        $this->setPageBuffer($this->page, $this->checkpoint);
    }

    public function is_overflowing() {
        return $this->y > $this->PageBreakTrigger;
    }

    public function set_title($newtitle) {
        $this->title = $newtitle;
    }

}

class exportquiz_question_pdf extends exportquiz_pdf
{
    private $tempfiles = array();

    /**
     * (non-PHPdoc)
     * @see TCPDF::Header()
     */
    public function Header() {
        $this->SetFont('FreeSans', 'I', 8);
        // Title.
        $this->Ln(15);
        if (!empty($this->title)) {
            $this->Cell(0, 10, $this->title, 0, 0, 'C');
        }
        $this->Rect(15, 25, 175, 0.3, 'F');
        // Line break.
        $this->Ln(15);
        $this->diskcache = false;
    }

    /**
     * (non-PHPdoc)
     * @see TCPDF::Footer()
     */
    public function Footer() {
        // Position at 2.5 cm from bottom.
        $this->SetY(-25);
        $this->SetFont('FreeSans', 'I', 8);
        // Page number.
        $this->Cell(0, 10, exportquiz_str_html_pdf(get_string('page')) . ' ' . $this->getAliasNumPage() .
                '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

/**
 * Returns a rendering of the number depending on the answernumbering format.
 * 
 * @param int $num The number, starting at 0.
 * @param string $style The style to render the number in. One of the
 * options returned by {@link qtype_multichoice:;get_numbering_styles()}.
 * @return string the number $num in the requested style.
 */
function number_in_style($num, $style) {
        return $number = chr(ord('a') + $num);
}


/**
 * Generates the PDF question/correction form for an exportquiz group.
 *
 * @param question_usage_by_activity $templateusage the template question  usage for this export group
 * @param object $exportquiz The exportquiz object
 * @param object $group the export group object
 * @param int $courseid the ID of the Moodle course
 * @param object $context the context of the export quiz.
 * @param boolean correction if true the correction form is generated.
 * @return stored_file instance, the generated PDF file.
 */
function exportquiz_create_pdf_question(question_usage_by_activity $templateusage, $exportquiz, $group,
                                         $courseid, $context, $correction = false) {
    global $CFG, $DB, $OUTPUT;

    $letterstr = 'abcdefghijklmnopqrstuvwxyz';
    $groupletter = strtoupper($letterstr[$group->number - 1]);

    $coursecontext = context_course::instance($courseid);

    $pdf = new exportquiz_question_pdf('P', 'mm', 'A4');
    $trans = new exportquiz_html_translator();

    $title = exportquiz_str_html_pdf($exportquiz->name);
    if (!empty($exportquiz->time)) {
        $title .= ": ".exportquiz_str_html_pdf(userdate($exportquiz->time));
    }
    $title .= ",  ".exportquiz_str_html_pdf(get_string('group')." $groupletter");
    $pdf->set_title($title);
    $pdf->SetMargins(15, 28, 15);
    $pdf->SetAutoPageBreak(false, 25);
    $pdf->AddPage();

    // Print title page.
    $pdf->SetFont('FreeSans', 'B', 14);
    $pdf->Ln(4);
    if (!$correction) {
        if ($exportquiz->heading){
        
        $pdf->Rect(34, 46, 137, 53, 'D');
        $pdf->SetFont('FreeSans', '', 10);
        // Line breaks to position name string etc. properly.
        $pdf->Ln(20);
        $pdf->Cell(58, 10, exportquiz_str_html_pdf(get_string('name')).":", 0, 0, 'R');
        $pdf->Rect(76, 60, 80, 0.3, 'F');
        $pdf->Ln(10);
        $pdf->Cell(58, 10, exportquiz_str_html_pdf(get_string('lastname', 'exportquiz')).":", 0, 0, 'R');
        $pdf->Rect(76, 70, 80, 0.3, 'F');
        $pdf->Ln(10);
        $pdf->Cell(58, 10, exportquiz_str_html_pdf(get_string('idnumber', 'exportquiz')).":", 0, 0, 'R');
        $pdf->Rect(76, 80, 80, 0.3, 'F');}
        
        $pdf->Ln(50);

        $pdf->SetFont('FreeSans', '', $exportquiz->fontsize);
        $pdf->SetFontSize($exportquiz->fontsize);

        // The PDF intro text can be arbitrarily long so we have to catch page overflows.
        if (!empty($exportquiz->pdfintro)) {
            $oldx = $pdf->GetX();
            $oldy = $pdf->GetY();

            $pdf->checkpoint();
            $pdf->writeHTMLCell(165, round($exportquiz->fontsize / 2), $pdf->GetX(), $pdf->GetY(), $exportquiz->pdfintro);
            $pdf->Ln();

            
        }
        
        $pdf->Ln(2);
    }
    $pdf->SetMargins(15, 15, 15);

    // Load all the questions needed for this export quiz group.
    $sql = "SELECT q.*, c.contextid, ogq.page, ogq.slot, ogq.maxmark 
              FROM {exportquiz_group_questions} ogq,
                   {question} q,
                   {question_categories} c
             WHERE ogq.exportquizid = :exportquizid
               AND ogq.exportgroupid = :exportgroupid
               AND q.id = ogq.questionid
               AND q.category = c.id
          ORDER BY ogq.slot ASC ";
    $params = array('exportquizid' => $exportquiz->id, 'exportgroupid' => $group->id);
 
    // Load the questions.
    $questions = $DB->get_records_sql($sql, $params);
    if (!$questions) {
        echo $OUTPUT->box_start();
        echo $OUTPUT->error_text(get_string('noquestionsfound', 'exportquiz', $groupletter));
        echo $OUTPUT->box_end();
        return;
    }

    // Load the question type specific information.
    if (!get_question_options($questions)) {
        print_error('Could not load question options');
    }

    // Restore the question sessions to their most recent states.
    // Creating new sessions where required.
    $number = 1;

    // We need a mapping from question IDs to slots, assuming that each question occurs only once.
    $slots = $templateusage->get_slots();

    $texfilter = new filter_tex($context, array());

    // If shufflequestions has been activated we go through the questions in the order determined by
    // the template question usage.
    if ($exportquiz->shufflequestions) {
        foreach ($slots as $slot) {
            $slotquestion = $templateusage->get_question($slot);
            $currentquestionid = $slotquestion->id;

            // Add page break if necessary because of overflow.
            if ($pdf->GetY() > 230) {
                $pdf->AddPage();
                $pdf->Ln(14);
            }
            set_time_limit(120);
            $question = $questions[$currentquestionid];

            /*****************************************************/
            /*  Either we print the question HTML */
            /*****************************************************/
            $pdf->checkpoint();

            $questiontext = $question->questiontext;

            // Filter only for tex formulas.
            if (!empty($texfilter)) {
                $questiontext = $texfilter->filter($questiontext);
            }

            // Remove all HTML comments (typically from MS Office).
            $questiontext = preg_replace("/<!--.*?--\s*>/ms", "", $questiontext);

            // Remove <font> tags.
            $questiontext = preg_replace("/<font[^>]*>[^<]*<\/font>/ms", "", $questiontext);

            // Remove <script> tags that are created by mathjax preview.
            $questiontext = preg_replace("/<script[^>]*>[^<]*<\/script>/ms", "", $questiontext);

            // Remove all class info from paragraphs because TCPDF won't use CSS.
            $questiontext = preg_replace('/<p[^>]+class="[^"]*"[^>]*>/i', "<p>", $questiontext);

            $questiontext = $trans->fix_image_paths($questiontext, $question->contextid, 'questiontext', $question->id, 1, 300);

            $html = '';

            $html .= $questiontext . '<br/><br/>';
            if ($question->qtype == 'multichoice' || $question->qtype == 'multichoiceset') {

                // Save the usage slot in the group questions table.
//                 $DB->set_field('exportquiz_group_questions', 'usageslot', $slot,
//                         array('exportquizid' => $exportquiz->id,
//                                 'exportgroupid' => $group->id, 'questionid' => $question->id));

                // There is only a slot for multichoice questions.
                $attempt = $templateusage->get_question_attempt($slot);
                $order = $slotquestion->get_order($attempt);  // Order of the answers.

                foreach ($order as $key => $answer) {
                    $answertext = $question->options->answers[$answer]->answer;
                    // Filter only for tex formulas.
                    if (!empty($texfilter)) {
                        $answertext = $texfilter->filter($answertext);
                    }

                    // Remove all HTML comments (typically from MS Office).
                    $answertext = preg_replace("/<!--.*?--\s*>/ms", "", $answertext);
                    // Remove all paragraph tags because they mess up the layout.
                    $answertext = preg_replace("/<p[^>]*>/ms", "", $answertext);
                    // Remove <script> tags that are created by mathjax preview.
                    $answertext = preg_replace("/<script[^>]*>[^<]*<\/script>/ms", "", $answertext);
                    $answertext = preg_replace("/<\/p[^>]*>/ms", "", $answertext);
                    $answertext = $trans->fix_image_paths($answertext, $question->contextid, 'answer', $answer, 1, 300);

                    if ($correction) {
                        if ($question->options->answers[$answer]->fraction > 0) {
                            $html .= '<b>';
                        }

                        $answertext .= " (".round($question->options->answers[$answer]->fraction * 100)."%)";
                    }

                    $html .= number_in_style($key, $question->options->answernumbering) . ') &nbsp; ';
                    $html .= $answertext;

                    if ($correction) {
                        if ($question->options->answers[$answer]->fraction > 0) {
                            $html .= '</b>';
                        }
                    }

                    $html .= "<br/>\n";
                }

                if ($exportquiz->showgrades) {
                    $pointstr = get_string('points', 'grades');
                    if ($question->maxmark == 1) {
                        $pointstr = get_string('point', 'exportquiz');
                    }
                    $html .= '<br/>(' . ($question->maxmark + 0) . ' ' . $pointstr .')<br/>';
                }
            }

            // Finally print the question number and the HTML string.
            if ($question->qtype == 'multichoice' || $question->qtype == 'multichoiceset') {
                $pdf->SetFont('FreeSans', 'B', $exportquiz->fontsize);
                $pdf->Cell(4, round($exportquiz->fontsize / 2), "$number)  ", 0, 0, 'R');
                $pdf->SetFont('FreeSans', '', $exportquiz->fontsize);
            }

            $pdf->writeHTMLCell(165,  round($exportquiz->fontsize / 2), $pdf->GetX(), $pdf->GetY() + 0.3, $html);
            $pdf->Ln();

            if ($pdf->is_overflowing()) {
                $pdf->backtrack();
                $pdf->AddPage();
                $pdf->Ln(14);

                // Print the question number and the HTML string again on the new page.
                if ($question->qtype == 'multichoice' || $question->qtype == 'multichoiceset') {
                    $pdf->SetFont('FreeSans', 'B', $exportquiz->fontsize);
                    $pdf->Cell(4, round($exportquiz->fontsize / 2), "$number)  ", 0, 0, 'R');
                    $pdf->SetFont('FreeSans', '', $exportquiz->fontsize);
                }

                $pdf->writeHTMLCell(165,  round($exportquiz->fontsize / 2), $pdf->GetX(), $pdf->GetY() + 0.3, $html);
                $pdf->Ln();
            }
            $number += $questions[$currentquestionid]->length;
        }
    } else {
        // No shufflequestions, so go through the questions as they have been added to the exportquiz group.
        // We also have to show description questions that are not in the template.

        // First, compute mapping  questionid -> slotnumber.
        $questionslots = array();
        foreach ($slots as $slot) {
            $questionslots[$templateusage->get_question($slot)->id] = $slot;
        }
        $currentpage = 1;
        foreach($questions as $question) {
            $currentquestionid = $question->id;
            
            // Add page break if set explicitely by teacher.
            if ($question->page > $currentpage) {
                $pdf->AddPage();
                $pdf->Ln(14);
                $currentpage++;
            }

            // Add page break if necessary because of overflow.
            if ($pdf->GetY() > 230) {
                $pdf->AddPage();
                $pdf->Ln( 14 );
            }
            set_time_limit( 120 );
            
            /**
             * **************************************************
             * either we print the question HTML 
             * **************************************************
             */
            $pdf->checkpoint();
            
            $questiontext = $question->questiontext;
            
            // Filter only for tex formulas.
            if (! empty ( $texfilter )) {
                $questiontext = $texfilter->filter ( $questiontext );
            }
            
            // Remove all HTML comments (typically from MS Office).
            $questiontext = preg_replace ( "/<!--.*?--\s*>/ms", "", $questiontext );
            
            // Remove <font> tags.
            $questiontext = preg_replace ( "/<font[^>]*>[^<]*<\/font>/ms", "", $questiontext );
            
            // Remove <script> tags that are created by mathjax preview.
            $questiontext = preg_replace ( "/<script[^>]*>[^<]*<\/script>/ms", "", $questiontext );
            
            // Remove all class info from paragraphs because TCPDF won't use CSS.
            $questiontext = preg_replace ( '/<p[^>]+class="[^"]*"[^>]*>/i', "<p>", $questiontext );
            
            $questiontext = $trans->fix_image_paths ( $questiontext, $question->contextid, 'questiontext', $question->id, 1, 300 );
            
            $html = '';
            
            $html .= $questiontext . '<br/><br/>';
            if ($question->qtype == 'multichoice' || $question->qtype == 'multichoiceset') {
                
                $slot = $questionslots[$currentquestionid];
                // Save the usage slot in the group questions table.
                // $DB->set_field('exportquiz_group_questions', 'usageslot', $slot,
                // array('exportquizid' => $exportquiz->id,
                // 'exportgroupid' => $group->id, 'questionid' => $question->id));
                
                // There is only a slot for multichoice questions.
                $slotquestion = $templateusage->get_question ( $slot );
                $attempt = $templateusage->get_question_attempt ( $slot );
                $order = $slotquestion->get_order ( $attempt ); // Order of the answers.
                
                foreach ( $order as $key => $answer ) {
                    $answertext = $question->options->answers[$answer]->answer;
                    // Filter only for tex formulas.
                    if (! empty ( $texfilter )) {
                        $answertext = $texfilter->filter ( $answertext );
                    }
                    
                    // Remove all HTML comments (typically from MS Office).
                    $answertext = preg_replace ( "/<!--.*?--\s*>/ms", "", $answertext );
                    // Remove all paragraph tags because they mess up the layout.
                    $answertext = preg_replace ( "/<p[^>]*>/ms", "", $answertext );
                    // Remove <script> tags that are created by mathjax preview.
                    $answertext = preg_replace ( "/<script[^>]*>[^<]*<\/script>/ms", "", $answertext );
                    $answertext = preg_replace ( "/<\/p[^>]*>/ms", "", $answertext );
                    $answertext = $trans->fix_image_paths ( $answertext, $question->contextid, 'answer', $answer, 1, 300 );
                    // Was $pdf->GetK()).
                    
                    if ($correction) {
                        if ($question->options->answers[$answer]->fraction > 0) {
                            $html .= '<b>';
                        }
                        
                        $answertext .= " (" . round ( $question->options->answers[$answer]->fraction * 100 ) . "%)";
                    }
                    
                    $html .= number_in_style ( $key, $question->options->answernumbering ) . ') &nbsp; ';
                    $html .= $answertext;
                    
                    if ($correction) {
                        if ($question->options->answers[$answer]->fraction > 0) {
                            $html .= '</b>';
                        }
                    }
                    $html .= "<br/>\n";
                }
                
                if ($exportquiz->showgrades) {
                    $pointstr = get_string ( 'points', 'grades' );
                    if ($question->maxmark == 1) {
                        $pointstr = get_string ( 'point', 'exportquiz' );
                    }
                    $html .= '<br/>(' . ($question->maxmark + 0) . ' ' . $pointstr . ')<br/>';
                }
            }
            
            // Finally print the question number and the HTML string.
            if ($question->qtype == 'multichoice' || $question->qtype == 'multichoiceset') {
                $pdf->SetFont ( 'FreeSans', 'B', $exportquiz->fontsize );
                $pdf->Cell ( 4, round ( $exportquiz->fontsize / 2 ), "$number)  ", 0, 0, 'R' );
                $pdf->SetFont ( 'FreeSans', '', $exportquiz->fontsize );
            }
            
            $pdf->writeHTMLCell ( 165, round ( $exportquiz->fontsize / 2 ), $pdf->GetX (), $pdf->GetY () + 0.3, $html );
            $pdf->Ln ();
            
            if ($pdf->is_overflowing ()) {
                $pdf->backtrack ();
                $pdf->AddPage ();
                $pdf->Ln ( 14 );
                
                // Print the question number and the HTML string again on the new page.
                if ($question->qtype == 'multichoice' || $question->qtype == 'multichoiceset') {
                    $pdf->SetFont ( 'FreeSans', 'B', $exportquiz->fontsize );
                    $pdf->Cell ( 4, round ( $exportquiz->fontsize / 2 ), "$number)  ", 0, 0, 'R' );
                    $pdf->SetFont ( 'FreeSans', '', $exportquiz->fontsize );
                }
                
                $pdf->writeHTMLCell ( 165, round ( $exportquiz->fontsize / 2 ), $pdf->GetX (), $pdf->GetY () + 0.3, $html );
                $pdf->Ln ();
            }
            $number += $questions[$currentquestionid]->length;
        }

    }

    $fs = get_file_storage();

    $fileprefix = 'form';
    if ($correction) {
        $fileprefix = 'correction';
    }

    // Prepare file record object.
    $timestamp = date('Ymd_His', time());
    $fileinfo = array(
            'contextid' => $context->id,
            'component' => 'mod_exportquiz',
            'filearea' => 'pdfs',
            'filepath' => '/',
            'itemid' => 0,
            'filename' => $fileprefix . '-' . strtolower($groupletter) . '_' . $timestamp . '.pdf');

    if ($oldfile = $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'],
            $fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename'])) {

        $oldfile->delete();
    }
    $pdfstring = $pdf->Output('', 'S');

    $file = $fs->create_file_from_string($fileinfo, $pdfstring);
    $trans->remove_temp_files();

    return $file;
}

/**
 * Function to transform Moodle HTML code of a question into proprietary markup that only supports italic, underline and bold.
 *
 * @param unknown_type $input The input text.
 * @param unknown_type $stripalltags Whether all tags should be stripped.
 * @param unknown_type $questionid The ID of the question the text stems from.
 * @param unknown_type $coursecontextid The course context ID.
 * @return mixed
 */
function exportquiz_str_html_pdf($input, $stripalltags=true, $questionid=null, $coursecontextid=null) {
    global $CFG;

    $output = $input;
    $fs = get_file_storage();

    // Replace linebreaks.
    $output = preg_replace('!<br>!i', "\n", $output);
    $output = preg_replace('!<br />!i', "\n", $output);
    $output = preg_replace('!</p>!i', "\n", $output);

    if (!$stripalltags) {
        // First replace the plugin image tags.
        $output = str_replace('[', '(', $output);
        $output = str_replace(']', ')', $output);
        $strings = preg_split("/<img/i", $output);
        $output = array_shift($strings);
        foreach ($strings as $string) {
            $output .= '[*p ';
            $imagetag = substr($string, 0, strpos($string, '>'));
            $attributes = explode(' ', $imagetag);
            foreach ($attributes as $attribute) {
                $valuepair = explode('=', $attribute);
                if (strtolower(trim($valuepair[0])) == 'src') {
                    $pluginfilename = str_replace('"', '', str_replace("'", '', $valuepair[1]));
                    $pluginfilename = str_replace('@@PLUGINFILE@@/', '', $pluginfilename);
                    $file = $fs->get_file($coursecontextid, 'question', 'questiontext', $questionid, '/', $pluginfilename);
                    // Copy file to temporary file.
                    $output .= $file->get_id(). ']';
                }
            }
            $output .= substr($string, strpos($string, '>') + 1);
        }
        $strings = preg_split("/<span/i", $output);
        $output = array_shift($strings);
        foreach ($strings as $string) {
            $tags = preg_split("/<\/span>/i", $string);
            $styleinfo = explode('>', $tags[0]);
            $style = array();
            if (stripos($styleinfo[0], 'bold')) {
                $style[] = '[*b]';
            }
            if (stripos($styleinfo[0], 'italic')) {
                $style[] = '[*i]';
            }
            if (stripos($styleinfo[0], 'underline')) {
                $style[] = '[*u]';
            }
            sort($style);
            array_shift($styleinfo);
            $output .= implode($style) . implode($styleinfo, '>');
            rsort($style);
            $output .= implode($style);
            if (!empty($tags[1])) {
                $output .= $tags[1];
            }
        }

        $search  = array('/<i[ ]*>(.*?)<\/i[ ]*>/smi', '/<b[ ]*>(.*?)<\/b[ ]*>/smi', '/<em[ ]*>(.*?)<\/em[ ]*>/smi',
                '/<strong[ ]*>(.*?)<\/strong[ ]*>/smi', '/<u[ ]*>(.*?)<\/u[ ]*>/smi',
                '/<sub[ ]*>(.*?)<\/sub[ ]*>/smi', '/<sup[ ]*>(.*?)<\/sup[ ]*>/smi' );
        $replace = array('[*i]\1[*i]', '[*b]\1[*b]', '[*i]\1[*i]',
                '[*b]\1[*b]', '[*u]\1[*u]',
                '[*l]\1[*l]', '[*h]\1[*h]');
        $output = preg_replace($search, $replace, $output);
    }
    $output = strip_tags($output);

    $search  = array('&quot;', '&amp;', '&gt;', '&lt;');
    $replace = array('"', '&', '>', '<');
    $result = str_ireplace($search, $replace, $output);

    return $result;
}
