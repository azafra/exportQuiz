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


require_once("../../config.php");
require_once('locallib.php');
require_once('pdflib.php');
require_once($CFG->libdir . '/questionlib.php');

$id 				= optional_param('id', 0, PARAM_INT);               		// Course Module ID.
$q 					= optional_param('q', 0, PARAM_INT);                		// Or exportquiz ID.
$forcenew 			= optional_param('forcenew', false, PARAM_BOOL);			// Reshuffle answers.
$forcepdfnew 		= optional_param('forcepdfnew', false, PARAM_BOOL);			// Recreate PDFs.
$mode 				= optional_param('mode', 'preview', PARAM_ALPHA);			// Mode.
$downloadall		= optional_param('downloadall' , false, PARAM_BOOL);
$shufflequestions	= optional_param('shufflequestions', false, PARAM_BOOL);	// Reshuffle questions
$createdocs            = optional_param('createdocs', false, PARAM_BOOL);
$shuffletype           = optional_param('shuffletype', 0, PARAM_INT);
$groupid               = optional_param('groupid', 1, PARAM_INT);

if($shuffletype == 1)
{
	$shufflequestions = 1;
}
else if ($shuffletype == 2)
{
	$forcenew = 1;
}
else
{
	$shufflequestions = 0;
	$forcenew = 0;
}


$letterstr = 'ABCDEFGHIJKL';

if ($id) {
    if (!$cm = get_coursemodule_from_id('exportquiz', $id)) {
        print_error("There is no coursemodule with id $id");
    }

    if (!$course = $DB->get_record("course", array('id' => $cm->course))) {
        print_error("Course is misconfigured");
    }

    if (!$exportquiz = $DB->get_record("exportquiz", array('id' => $cm->instance))) {
        print_error("The exportquiz with id $cm->instance corresponding to this coursemodule $id is missing");
    }

} else {
    if (! $exportquiz = $DB->get_record("exportquiz", array('id' => $q))) {
        print_error("There is no exportquiz with id $q");
    }
    if (! $course = $DB->get_record("course", array('id' => $exportquiz->course))) {
        print_error("The course with id $exportquiz->course that the exportquiz with id $q belongs to is missing");
    }
    if (! $cm = get_coursemodule_from_instance("exportquiz", $exportquiz->id, $course->id)) {
        print_error("The course module for the exportquiz with id $q is missing");
    }
}

$exportquiz->optionflags = 0;

require_login($course->id, false, $cm);
if (!$context = context_module::instance($cm->id)) {
    print_error("The context for the course module with ID $cm->id is missing");
}
$exportquiz->cmid = $cm->id;

$coursecontext = context_course::instance($course->id);

// We redirect students to info.
if (!has_capability('mod/exportquiz:createexportquiz', $context)) {
    redirect('view.php?q='.$exportquiz->id);
}

// If not in all group questions have been set up yet redirect to edit.php.
exportquiz_load_useridentification();

$strpreview = get_string('createquiz', 'exportquiz');
$strexportquizzes = get_string("modulenameplural", "exportquiz");

$PAGE->set_url('/mod/exportquiz/createquiz.php?id=' . $cm->id);
$PAGE->set_title($strpreview);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('report'); // Or 'admin'.
$PAGE->set_cacheable(true);

if ($node = $PAGE->settingsnav->find('mod_exportquiz_createquiz', navigation_node::TYPE_SETTING)) {
    $node->make_active();
}

if (!$groups = $DB->get_records('exportquiz_groups', array('exportquizid' => $exportquiz->id), 'number', '*', 0,
        $exportquiz->numgroups)) {
    print_error('There are no exportquiz groups', "edit.php?q=$exportquiz->id$amp;sesskey=".sesskey());
}

// Redmine 2131: Handle download all before any HTML output is produced.
if ($downloadall && $exportquiz->docscreated) {
    $fs = get_file_storage();

    // Simply pack all files in the 'pdfs' filearea in a ZIP file.
    $files = $fs->get_area_files($context->id, 'mod_exportquiz', 'pdfs');
    $timestamp = date('Ymd_His', time());
    $shortname = $DB->get_field('course', 'shortname', array('id' => $exportquiz->course));
    $zipfilename = clean_filename($shortname . '_' . $exportquiz->name . '_' . $timestamp . '.zip');
    $tempzip = tempnam($CFG->tempdir . '/', 'exportquizzip');
    $filelist = array();

    foreach ($files as $file) {
        $filename = $file->get_filename();
        if ($filename != '.') {
            $path = '';
            if (0 === strpos($filename, 'form-')) {
                $path = get_string('questionforms', 'exportquiz');
            } else if (0 === strpos($filename, 'answer-')) {
                $path = get_string('answerforms', 'exportquiz');
            } else {
                $path = get_string('correctionforms', 'exportquiz');
            }
            $path = clean_filename($path);
            $filelist[$path . '/' . $filename] = $file;
        }
    }

    $zipper = new zip_packer();

    if ($zipper->archive_to_pathname($filelist, $tempzip)) {
        send_temp_file($tempzip, $zipfilename);
    }
}

// Print the page header.
echo $OUTPUT->header();

// Print the exportquiz name heading and tabs for teacher.
$currenttab = 'createexportquiz';

require('tabs.php');

$hasscannedpages = exportquiz_has_scanned_pages($exportquiz->id);

if ($exportquiz->grade == 0) {
    echo '<div class="linkbox"><strong>';
    echo $OUTPUT->notification(get_string('gradeiszero', 'exportquiz'), 'notifyproblem');
    echo '</strong></div>';
}

// Preview.
if ($mode == 'preview') {
    // Print shuffle again buttons.
    if (!$exportquiz->docscreated && !$hasscannedpages) {
    
        echo $OUTPUT->heading(get_string('formspreview', 'exportquiz'));

        echo $OUTPUT->box_start('generalbox controlbuttonbox');
         
        $formurloptions['q'] = $exportquiz->id;        
        $formurl = new moodle_url('/mod/exportquiz/createquiz.php', $formurloptions);
        
        echo '<center>';
        
        echo '<form method="post" action="'.$formurl.'">';
        	
        	echo '<div class="felement fselect" data-fieldtype="select">';
        
			echo '<select name="groupid" id="groupid">';
			
			
			$grupos = $DB->get_records('exportquiz_group_questions', array('exportquizid' => $exportquiz->id), '', 'DISTINCT(exportgroupid)', 0, 0);
			
			$i=0;
			foreach($grupos as $grupo)
			{
				echo '<option value="'.$grupo->exportgroupid.'">'.get_string('group', 'exportquiz').' '.$letterstr[$i].'</option>';
				$i++;
			}
			
			echo '</select>';
			
			echo '&nbsp&nbsp';
			
			echo '<select name="shuffletype" id="shuffletype">';
				echo '<option value="1">'.get_string('shufflequestions', 'exportquiz').'</option>';
				echo '<option value="2">'.get_string('shuffleanswers', 'exportquiz').'</option>';
			echo '</select>';
			
			echo '<input type="hidden" name="q" value="1">';
			
			echo '&nbsp&nbsp';
			echo '<input type="submit" value="Barajar"';
		
		
		echo '</div>';
		
        echo '</form>';
        
        echo '</center>';
        
        


        echo $OUTPUT->box_end();
        

    }
    
    //Shuffle again questions
    if($shufflequestions)
    {
    	if ($exportquiz->docscreated || $hasscannedpages) {
            echo $OUTPUT->notification(get_string('formsexist', 'exportquiz'), 'notifyproblem');
        } else {
        
        	$grupos = $DB->get_records('exportquiz_group_questions', array('exportquizid' => $exportquiz->id, 'exportgroupid' => $groupid), '', 'DISTINCT(exportgroupid)', 0, 0);
        	
        	foreach($grupos as $grupo)
        	{
        		$idgrupo = $grupo->exportgroupid;
        		
        		
        		$tuplas = $DB->get_records('exportquiz_group_questions', array('exportgroupid' => $idgrupo), '', '*', 0, 0);
        		
        		$numTuplas = count($tuplas);
        		
				$posicionesUsadas = array();
        		                
		        foreach($tuplas as $tupla)
		        {
		        	do{
		        		$posicionGenerada = rand(1, $numTuplas);
		        	}while(in_array($posicionGenerada, $posicionesUsadas));
		        	
		        	
		        	$posicionesUsadas[] = $posicionGenerada;
		        	
		        	$tupla->slot = $posicionGenerada;
		        	
		        	$DB->update_record('exportquiz_group_questions', $tupla);
		        }
		        
		        unset($posicionesUsadas);
        	
        	}
        	
        	
        }
    }

    // Shuffle again if no scanned pages.
    if ($forcenew) {
        if ($exportquiz->docscreated || $hasscannedpages) {
            echo $OUTPUT->notification(get_string('formsexist', 'exportquiz'), 'notifyproblem');
        } else {
            $exportquiz = exportquiz_delete_template_usages_for_group($exportquiz, $groupid);
            $groups = $DB->get_records('exportquiz_groups', array('exportquizid' => $exportquiz->id), 'number',
                      '*', 0, $exportquiz->numgroups);
        }
    }

    $done = 0;
    // Process group data.
    foreach ($groups as $group) {
        $groupletter = $letterstr[$group->number - 1];

        // Print the group heading.
        echo $OUTPUT->heading(get_string('previewforgroup', 'exportquiz', $groupletter));

        echo $OUTPUT->box_start('generalbox groupcontainer');

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
 
        $questions = $DB->get_records_sql($sql, $params);
        
        // Load the questions.
        if (!$questions = $DB->get_records_sql($sql, $params)) {
            $url = new moodle_url($CFG->wwwroot . '/mod/exportquiz/edit.php',
                    array('cmid' => $cm->id, 'groupnumber' => $group->number, 'noquestions' => 1));
            echo html_writer::link($url,  get_string('noquestionsfound', 'exportquiz', $groupletter),
                    array('class' => 'notifyproblem linkbox'));
            echo $OUTPUT->box_end();
            continue;
        }
        // Load the question type specific information.
        if (!get_question_options($questions)) {
            print_error('Could not load question options');
        }

        // Get or create a question usage for this export group.
        if (!$templateusage = exportquiz_get_group_template_usage($exportquiz, $group, $context)) {
            echo $OUTPUT->notification(get_string('missingquestions', 'exportquiz'), 'notifyproblem');
            echo $OUTPUT->box_end();
            echo $OUTPUT->footer();
            continue;
        }
        if (!$slots = $templateusage->get_slots()) {
            echo $OUTPUT->box_start('notify');
            echo $OUTPUT->error_text(get_string('nomcquestions', 'exportquiz', $groupletter));
            echo $OUTPUT->box_end();
        }

        // We need a mapping from question IDs to slots, assuming that each question occurs only once..
        $questionslots = array();
        foreach ($slots as $qid => $slot) {
            $questionslots[$templateusage->get_question($slot)->id] = $slot;
        }

        $questionnumber = 1;
        $currentpage = 1;
        if ($exportquiz->shufflequestions) {
            foreach ($slots as $slot) {
                $slotquestion = $templateusage->get_question($slot);
                $question = $questions[$slotquestion->id];
                $attempt = $templateusage->get_question_attempt($slot);
                $order = $slotquestion->get_order($attempt);  // Order.
                exportquiz_print_question_preview($question, $order, $questionnumber, $context, $PAGE);
                // Note: we don't have description questions in quba slots.
                $questionnumber++;
            }
        } else {
            foreach ($questions as $question) {
                if ($question->page > $currentpage) {
                    echo '<center>//---------------------- ' . get_string('newpage', 'exportquiz') .
                            ' ----------------//</center>';
                    $currentpage++;
                }
                $order = array();
                if ($question->qtype == 'multichoice' || $question->qtype == 'multichoiceset') {
                    $slot = $questionslots[$question->id];
                    $slotquestion = $templateusage->get_question($slot);
                    $attempt = $templateusage->get_question_attempt($slot);
                    $order = $slotquestion->get_order($attempt);
                }
                // Use our own function to print the preview.
                exportquiz_print_question_preview($question, $order, $questionnumber, $context, $PAGE);
                if ($question->qtype != 'description') {
                    $questionnumber++;
                }
            }
        }
        echo $OUTPUT->box_end();
    }// End foreach.

    // O==============================================================.
    // O TAB for creating, downloading and deleting PDF forms.
    // O==============================================================.
} else if ($mode == 'createpdfs') {


    // Print the heading.
    echo $OUTPUT->heading(get_string('downloadpdfs', 'exportquiz'));

    $emptygroups = exportquiz_get_empty_groups($exportquiz);
    if (!empty($emptygroups)) {
        echo $OUTPUT->box_start('linkbox');
        foreach ($emptygroups as $groupnumber) {
            $groupletter = $letterstr[$groupnumber - 1];
            echo $OUTPUT->notification(get_string('noquestionsfound', 'exportquiz', $groupletter), 'notifyproblem');
        }
        echo $OUTPUT->notification(get_string('nopdfscreated', 'exportquiz'), 'notifyproblem');
        echo $OUTPUT->box_end();

        echo $OUTPUT->footer();
        return true;
    }
    
    	if($createdocs)
    	{
    	
	    	// Options for the popup_action.
		$options = array();
		$options['height'] = 1200; // Optional.
		$options['width'] = 1170; // Optional.

		
		
		//GENERO LOS DOCUMENTOS DE ALUMNOS
		foreach ($groups as $group) {
			$groupletter = $letterstr[$group->number - 1];
			
			if (!$templateusage = exportquiz_get_group_template_usage($exportquiz, $group, $context)) {
			    print_error("Missing data for group ".$groupletter,
			        "createquiz.php?q=$exportquiz->id&amp;mode=preview&amp;sesskey=" . sesskey());
			}

			if ($exportquiz->fileformat == EXPORTQUIZ_DOCX_FORMAT) {
			    require_once('docxlib.php'); //La misma libreria tanto para DOCX como para ODT
			    $questionfile = exportquiz_create_docx_question($templateusage, $exportquiz, $group, $course->id, $context);
			}
			else if($exportquiz->fileformat == EXPORTQUIZ_ODT_FORMAT)
			{
				require_once('docxlib.php'); //La misma libreria tanto para DOCX como para ODT
				$questionfile = exportquiz_create_odt_question($templateusage, $exportquiz, $group, $course->id, $context);
			}
			else
			{
			    $questionfile = exportquiz_create_pdf_question($templateusage, $exportquiz, $group, $course->id, $context);
			}
		}
		
		
		
		//GENERO LAS PLANTILLAS DE CORRECCION
		foreach ($groups as $group) {
			$groupletter = $letterstr[$group->number - 1];
			
			if (!$templateusage = exportquiz_get_group_template_usage($exportquiz, $group, $context)) {
				print_error("Missing data for group " . $groupletter,
				    "createquiz.php?q=$exportquiz->id&amp;mode=preview&amp;sesskey=" . sesskey());
			}

			if (!$exportquiz->docscreated) {
				$correctpdffile = exportquiz_create_pdf_question($templateusage, $exportquiz, $group,
				                     $course->id, $context, true);
			}
		}
		
		
		
		// Remember that we have created the documents.
		$exportquiz->docscreated = 1;
		$DB->set_field('exportquiz', 'docscreated', 1, array('id' => $exportquiz->id));

		$doctype = 'PDF';
		if ($exportquiz->fileformat == EXPORTQUIZ_DOCX_FORMAT) {
		    $doctype = 'DOCX';
		}
		else if($exportquiz->fileformat == EXPORTQUIZ_ODT_FORMAT)
		{
			$doctype = 'ODT';
		}
		
		$params = array(
		    'context' => $context,
		    'other' => array(
		            'exportquizid' => $exportquiz->id,
		            'reportname' => $mode,
		            'doctype' => $doctype

		    )
		);
		$event = \mod_exportquiz\event\docs_created::create($params);
		$event->trigger();
		
		
		
		
		/*

		
		
		

		
	    }*/
    	
    	}
    	
    	
	// Delete the PDF forms if forcepdfnew and if there are no scanned pages yet.
	if ($forcepdfnew) {
		if ($hasscannedpages) {
		    print_error('Some answer forms have already been analysed',
			"createquiz.php?q=$exportquiz->id&amp;mode=createpdfs&amp;sesskey=" . sesskey());
		} else {
		    $fs = get_file_storage();
		    
		    // Redmine 2750: Always delete templates as well.
		    exportquiz_delete_template_usages($exportquiz);
		    $exportquiz = exportquiz_delete_pdf_forms($exportquiz);

		    $doctype = 'PDF';
		    if ($exportquiz->fileformat == EXPORTQUIZ_DOCX_FORMAT) {
			$doctype = 'DOCX';
		    }else if($exportquiz->fileformat == EXPORTQUIZ_ODT_FORMAT)
		    {
		    	$doctype = 'ODT';
		    }
		    $params = array(
			'context' => $context,
			'other' => array(
				'exportquizid' => $exportquiz->id,
				'reportname' => $mode,
				'doctype' => $doctype
			)
		    );
		    $event = \mod_exportquiz\event\docs_deleted::create($params);
		    $event->trigger();
		}
	}
    
    
	if(!$exportquiz->docscreated)
	{
	
		?>
		<div class="singlebutton linkbox">
		<form action="<?php echo "$CFG->wwwroot/mod/exportquiz/createquiz.php?q=" . $exportquiz->id .
		      "&mode=createpdfs" ?>" method="POST">
		    <div>
				<input type="hidden" name="createdocs" value="1" /> 
				<input type="submit" value="<?php echo get_string('createpdfforms', 'exportquiz') ?>"/>
		    </div>
		     </form>
		</div>
		<?php	
		
	}
	else
	{
	    

	    	?>
		<div class="singlebutton linkbox">
		<form action="<?php echo "$CFG->wwwroot/mod/exportquiz/createquiz.php?q=" . $exportquiz->id .
		      "&mode=createpdfs" ?>" method="POST">
		    <div>
				<input type="hidden" name="forcepdfnew" value="1" /> 
				    <input type="submit" value="<?php echo get_string('deletepdfs', 'exportquiz') ?>"
					 onClick='return confirm("<?php echo get_string('realydeletepdfs', 'exportquiz') ?>")' />
		    </div>
		     </form>
		</div>
		<?php

		// Redmine 2131: Add download all link.
		$downloadallurl = new moodle_url($CFG->wwwroot . '/mod/exportquiz/createquiz.php',
		        array('q' => $exportquiz->id,
		                'mode' => 'createpdfs',
		                'downloadall' => 1));
		echo '<div class="controlbuttons linkbox">';
		echo $OUTPUT->single_button($downloadallurl->out(false), get_string('downloadallzip', 'exportquiz'), 'post');
		echo '</div>';
		
		
		
		
		//MUESTRO LOS DOCUMENTOS DE LOS ALUMNOS
		
		
		
		
		
		echo $OUTPUT->box_start('generalbox linkbox docsbox');

		foreach ($groups as $group) {
			
			$groupletter = $letterstr[$group->number - 1];

			if ($exportquiz->fileformat == EXPORTQUIZ_DOCX_FORMAT) {
			    $suffix = '.docx';
			}
			else if($exportquiz->fileformat == EXPORTQUIZ_ODT_FORMAT)
			{
				$suffix = '.odt';
			}
			else {
			    $suffix = '.pdf';
			}
			

		        $sqllike = $DB->sql_like('filename', ':filename');
		        $sql = "SELECT filename
		                  FROM {files}
		                 WHERE contextid = :contextid
		                   AND component = 'mod_exportquiz'
		                   AND filearea = 'pdfs'
		                   AND itemid = 0
		                   AND filepath = '/'
		                   AND " . $sqllike;
		        $params = array('contextid' => $context->id,
		                'filename' => 'form-' . strtolower($groupletter) . '%' . $suffix);
		        $filename = $DB->get_field_sql($sql, $params);
		        
		        $fs = get_file_storage();
		        $questionfile = $fs->get_file($context->id, 'mod_exportquiz', 'pdfs', 0, '/', $filename);
		        
		        
		        
		        if ($questionfile) {
				$filestring = get_string('formforgroup', 'exportquiz', $groupletter);
				
				if ($exportquiz->fileformat == EXPORTQUIZ_DOCX_FORMAT) {
				    $filestring = get_string('formforgroupdocx', 'exportquiz', $groupletter);
				} else if($exportquiz->fileformat == EXPORTQUIZ_ODT_FORMAT)
				{
					$filestring = get_string('formforgroupodt', 'exportquiz', $groupletter);
				}
				
				
				$url = "$CFG->wwwroot/pluginfile.php/" . $questionfile->get_contextid() . '/' . $questionfile->get_component() .
				            '/' . $questionfile->get_filearea() . '/' . $questionfile->get_itemid() . '/' .
				            $questionfile->get_filename() . '?forcedownload=1';
				echo $OUTPUT->action_link($url, $filestring);
				echo '<br />&nbsp;<br />';
				@flush();@ob_flush();
			}
			else
			{
				echo $OUTPUT->notification(get_string('createpdferror', 'exportquiz', $groupletter));
			}
		       
		}
		
		echo $OUTPUT->box_end();
		
		
		
		//MUESTRO LOS DOCUMENTOS DE LOS PROFESORES
		
		
		
		
		
		echo $OUTPUT->box_start('generalbox linkbox docsbox');

		foreach ($groups as $group) {
		    $groupletter = $letterstr[$group->number - 1];

		    if (!$templateusage = exportquiz_get_group_template_usage($exportquiz, $group, $context)) {
		        print_error("Missing data for group " . $groupletter,
		            "createquiz.php?q=$exportquiz->id&amp;mode=preview&amp;sesskey=" . sesskey());
		    }

		    
		        $sqllike = $DB->sql_like('filename', ':filename');
		        $sql = "SELECT filename
		                  FROM {files}
		                 WHERE contextid = :contextid
		                   AND component = 'mod_exportquiz'
		                   AND filearea = 'pdfs'
		                   AND itemid = 0
		                   AND filepath = '/'
		                   AND " . $sqllike;
		        $params = array('contextid' => $context->id,
		                'filename' => 'correction-' . strtolower($groupletter) . '%.pdf');
		        $filename = $DB->get_field_sql($sql, $params);
		        
		        $fs = get_file_storage();
		        $correctpdffile = $fs->get_file($context->id, 'mod_exportquiz', 'pdfs', 0, '/', $filename);
		    

		    if ($correctpdffile) {
		        $url = "$CFG->wwwroot/pluginfile.php/" . $correctpdffile->get_contextid() . '/' .
		                $correctpdffile->get_component() . '/' . $correctpdffile->get_filearea() . '/' .
		                $correctpdffile->get_itemid() . '/' . $correctpdffile->get_filename() . '?forcedownload=1';
		        echo $OUTPUT->action_link($url, get_string('formforcorrection', 'exportquiz', $groupletter));

		        echo '<br />&nbsp;<br />';
		        @flush();@ob_flush();

		    } else {
		        echo $OUTPUT->notification(get_string('createpdferror', 'exportquiz', $groupletter));
		    }
		}


		echo $OUTPUT->box_end();
	    
		
	}
}

// Finish the page.
echo $OUTPUT->footer();
