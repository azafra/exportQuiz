<?PHP
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
 * The Spanish language strings for exportquizzes
 *
 * @package       mod
 * @subpackage    exportquiz
 * @author        Manuel Tejero Martín
 * @copyright     2015 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @since         Moodle 2.2+
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 **/

$string['modulename'] = 'Export Quiz';
$string['modulenameplural'] = 'Export Quizzes';
$string['pluginname'] = 'Export Quiz';
$string['addnewquestion'] = 'Nueva pregunta';
$string['addquestionfrombanktopage'] = 'Añade desde el banco de preguntas';
$string['add'] = 'Añadir';
$string['addarandomselectedquestion'] = 'Añade una pregunta seleccionada al azar ...';
$string['addrandomfromcategory'] = 'Preguntas aleatorias';
$string['addrandomquestion'] = 'Preguntas aleatorias';
$string['addrandomquestiontoexportquiz'] = 'Añade preguntas al exportquiz {$a->name} (group {$a->group})';
$string['addrandomquestiontopage'] = 'Añadir una pregunta aleatoria a la página {$a}';
$string['addarandomquestion'] = 'Preguntas aleatorias';
$string['addtoexportquiz'] = 'Añade a exportquiz';
$string['areyousureremoveselected'] = '¿Eliminar todas las preguntas seleccionadas?';
$string['attemptexists'] = 'Intento existente';
$string['attemptsexist'] = 'Ya no se pueden agregar o eliminar preguntas.';
$string['basicideasofexportquiz'] = 'Ideas básicas de exportquiz-making';
$string['bulksavegrades'] = 'Guardar puntuaciones';
$string['cannoteditafterattempts'] = 'No se puede agregar o quitar preguntas porque hay resultados ya completos. ({$a})';
$string['category'] = 'Categoría';
$string['changed'] = 'El resultado ha sido cambiado.';
$string['cmmissing'] = 'El curso para el exportquiz con ID {$a} no se encuentra';
$string['configintro'] = 'Los valores establecidos aquí se utilizan como valores por defecto de la configuración de nuevos exportquizzes';
$string['configshufflequestions'] = 'Si se habilita esta opción, el orden de las preguntas en los grupos de exportquiz será aleatorio cada vez que se vuelva a crear la vista previa en la pestaña "Crear documentos"';
$string['configshufflewithin'] = 'Habilitando esta opción, las partes que componen las preguntas individuales serán ordenadas aleatoriamente cuando los cuestionarios son creados';
$string['confirmremovequestion'] = '¿Seguro que desea eliminar esta {$a} pregunta?';
$string['copyright'] = '<strong>Advertencia: Los textos de esta página son sólo para su información personal. Al igual que cualquier otro texto a estas preguntas están bajo restricciones de copyright. No se le permite copiar o para mostrarles a otras personas!</strong>';
$string['copyselectedtogroup'] = 'Añadir preguntas seleccionadas a la versión: {$a}';
$string['copy'] = 'Copiar';
$string['correct'] = 'correcto';
$string['correcterror'] = 'Resolver';
$string['correctionform'] = 'Profesor';
$string['correctionforms'] = 'Tests del profesor ';
$string['couldnotgrab'] = 'No se pudo tomar la imagen {$a}';
$string['createexportquiz'] = 'Crear documentos';
$string['createpdferror'] = 'El cuestionario para el grupo {$a} no se pudo crear. Quizás no haya preguntas en la versión.';
$string['createpdfforms'] = 'Crear documentos';
$string['createpdfs'] = 'Crear';
$string['createquiz'] = 'Crear documentos';
$string['configdecimalplaces'] = 'Número de dígitos que deben mostrarse después del punto decimal cuando se muestran las calificaciones de un exportquiz .';
$string['decimalplaces'] = 'Posición decimales';
$string['deletepagecheck'] = '¿Realmente quieres eliminar las páginas seleccionadas?';
$string['deletepdfs'] = 'Eliminar documentos';
$string['deleteselectedresults'] = 'Eliminar resultados seleccionados';
$string['deleteupdatepdf'] = 'Elimina y actualiza los cuestionarios PDF';
$string['displayoptions'] = 'Mostrar opciones';
$string['done'] = 'hecho';
$string['downloadallzip'] = 'Descarga todos los archivos en ZIP';
$string['downloadpdfs'] = 'Descargar documentos';
$string['dragtoafter'] = 'Después {$a}';
$string['dragtostart'] = 'Al iniciar';
$string['editexportquiz'] = 'Editar export quiz';
$string['editingexportquiz'] = 'Editar versiones';
$string['editingexportquiz_help'] = 'Selecciona las preguntas para esta versión';
$string['editingexportquizx'] = 'Editar export quiz: {$a}';
$string['editmaxmark'] = 'Editar máxima puntuación';
$string['editquestion'] = 'Editar pregunta';
$string['editquestions'] = 'Editar preguntas';
$string['emptygroups'] = 'Algunas versiones de exportquiz están vacías. Por favor, añade algunas preguntas.';
$string['everythingon'] = 'habilitado';
$string['fileformat'] = 'Formato para hojas de preguntas';
$string['fileformat_help'] = 'Elegir formato en el que se desea exportar la prueba. La plantilla de respuestas siempre se genera en formato PDF.';
$string['fontsize'] = 'Tamaño de fuente';
$string['formforcorrection'] = 'Test del profesor - Versión {$a}';
$string['formforgroup'] = 'Test de preguntas para el alumno - Versión {$a}';
$string['formforgroupdocx'] = 'Test de preguntas para el alumno - Versión {$a} (DOCX)';
$string['formforgroupodt'] = 'Test de preguntas para el alumno - Versión {$a} (ODT)';
$string['formsexist'] = 'Documentos ya creados. Por favor, elimina los documentos antes de editar los ajustes.';
$string['formsexistx'] = 'Documentos ya creados (<a href="{$a}">Descargar documentos</a>)';
$string['formsheetsettings'] = 'Configuracion de test';
$string['formspreview'] = 'Previsualización de las pruebas';
$string['fromquestionbank'] = 'Desde el banco de preguntas';
$string['functiondisabledbysecuremode'] = 'Esta funcionalidad está actualmente desactivada';
$string['generalfeedback'] = 'Feedback general';
$string['grade'] = 'Puntuación';
$string['gradeiszero'] = 'Nota: la puntuación máxima para este exportquiz es 0';
$string['gradingexportquiz'] = 'Editar puntuaciones';
$string['gradingexportquizx'] = 'Puntuaciones: {$a}';
$string['gradingoptionsheading'] = 'Opciones de puntuación';
$string['greeniscross'] = 'cuenta en cruz';
$string['group'] = 'Versión';
$string['groupquestions'] = 'Versiones';
$string['hasresult'] = 'Resultados existentes';
$string['heading'] = 'Cabecera';
$string['idnumber'] = 'número ID';
$string['imagefile'] = 'Archivo de imagen';
$string['imagenotfound'] = 'Archivo de imagen: {$a} no encontrado!';
$string['import'] = 'Importar';
$string['importnew'] = 'Importar';
$string['importedon'] = 'Importado';
$string['importisfinished'] = 'Importación de exportquiz {$a} terminada.';
$string['importlinkresults'] = 'Enlace a resultados: {$a}';
$string['importlinkverify'] = 'Enlace para verificar: {$a}';
$string['importmailsubject'] = 'Importar notificación de exportquiz';
$string['importnumberexisting'] = 'Numero de tests dobles: {$a}';
$string['importnumberpages'] = 'Número de páginas importadas satisfactoriamente: {$a}';
$string['importnumberverify'] = 'Número de tests que necesitan verificación: {$a}';
$string['importtimefinish'] = 'Proceso finalizado: {$a}';
$string['importtimestart'] = 'Proceso iniciado: {$a}';
$string['info'] = 'Información';
$string['infoshort'] = 'i';
$string['invaliduserfield'] = 'Campo no válido de la tabla de usuario utilizado.';
$string['keepfilesfordays'] = 'Mantener los ficheros por días';
$string['lastname'] = 'Apellidos';
$string['marks'] = 'Puntos';
$string['maxgradewarning'] = 'La puntuación máxima tine que ser un número!';
$string['maxmark'] = 'Puntuación máxima';
$string['copytogroup'] = 'Añade todas las preguntas al grupo: {$a}';
$string['modulename_help'] = 'Este módulo permite crear y diseñar cuestionarios para ser exportados en papel. Las preguntas se mantienen en el banco de preguntas de Moodle y pueden ser reutilizadas en el mismo curso o en otros';
$string['multichoice'] = 'Multirespuesta';
$string['name'] = 'Nombre del Export quiz';
$string['newgrade'] = 'Puntuada';
$string['newpage'] = 'Nueva página';
$string['nomcquestions'] = 'No hay preguntas multirespuesta en el grupo {$a}!';
$string['nopdfscreated'] = 'Los documentos aún no han sido creados';
$string['noquestions'] = 'Algunos export quiz están vacíos. Por favor, añade algunas preguntas.';
$string['noquestionsfound'] = 'No hay preguntas en la versión {$a}!';
$string['noquestionsonpage'] = 'Página vacía';
$string['noresults'] = 'No hay resultados.';
$string['noreview'] = 'No tienes permiso para revisar este Export quiz';
$string['nothingtodo'] = 'Nada que hacer!';
$string['notyetgraded'] = 'Aun no puntuado';
$string['numattempts'] = 'Número de resultados importados: {$a}';
$string['numbergroups'] = 'Número de versiones';
$string['numpages'] = '{$a} páginas importadas';
$string['numquestionsx'] = 'Preguntas: {$a}';
$string['exportquiz:addinstance'] = 'Añadir un Export quiz';
$string['exportquiz:manage'] = 'Gestionar Export quizzes';
$string['exportquiz:preview'] = 'Previsualizar Export quizzes';
$string['exportquiz:viewreports'] = 'Ver informes Export quiz';
$string['exportquiz:view'] = 'Ver información de Export quiz';
$string['outof'] = '{$a->grade} de un máximo de {$a->maxgrade}';
$string['outofshort'] = '{$a->grade}/{$a->maxgrade}';
$string['overview'] = 'Visión general';
$string['page-mod-exportquiz-x'] = 'Cualquier página de Export quiz';
$string['page-mod-exportquiz-edit'] = 'Editar página Export quiz';
$string['pdfscreated'] = 'Documentos ya creados';
$string['pdfintro'] = 'Información adiccional';
$string['pdfintro_help'] = 'Esta información será impresa en la primera página y debería contener, por ejemplo, indicaciones sobre cómo realizar la prueba.';
$string['pdfintrotext'] = '<b> <br />';
$string['point'] = 'punto';
$string['present'] = 'presente';
$string['previewforgroup'] = 'Previsualización versión {$a}';
$string['preview'] = 'Previsualizar';
$string['previewquestion'] = 'Previsualizar pregunta';
$string['questionforms'] = 'Tests de preguntas';
$string['questionsingroup'] = 'Preguntas de la versión';
$string['questionname'] = 'Nombre de pregunta';
$string['questionsheet'] = 'Hoja de preguntas';
$string['questiontextisempty'] = '[Texto de la pregunta vacío]';
$string['quizquestions'] = 'Preguntas del text';
$string['randomnumber'] = 'Número de preguntas aleatorias';
$string['realydeletepdfs'] = '¿Quieres borrar los archivos de los tests?';
$string['recurse'] = 'Incluir también preguntas de subcategorías';
$string['regrade'] = 'Puntuar de nuevo';
$string['regradedisplayexplanation'] = '<b>Atención:</b> Al puntuar de nuevo también se cambian puntuaciones que han sido sobreescritas manualmente!';
$string['regradingquiz'] = 'Puntuando de nuevo';
$string['reloadquestionlist'] = 'Actualizar lista de preguntas';
$string['remove'] = 'Eliminar';
$string['removepagebreak'] = 'Eliminar salto de página';
$string['removeselected'] = 'Eliminar seleccionado';
$string['repaginatecommand'] = 'Repaginar';
$string['repaginatenow'] = 'Repaginar ahora';
$string['reportoverview'] = 'Visión general';
$string['score'] = 'Puntuación';
$string['select'] = 'Seleccionar';
$string['selectagroup'] = 'Seleccionar una versión';
$string['selectall'] = 'Seleccionar todo';
$string['selectcategory'] = 'Seleccionar categoría';
$string['selectnone'] = 'Deselecccionar todo';
$string['showgrades'] = 'Escribir puntuación de las preguntas';
$string['showgrades_help'] = 'Esta opción controla si la puntuación de cada pregunta debería imprimirse en la plantilla de respuestas o no';
$string['showtutorial'] = 'Mostrar un tutorial de Export quiz a los estudiantes.';
$string['shuffleanswers'] = 'Barajar respuestas';
$string['shufflequestions'] = 'Barajar preguntas';
$string['shufflequestionsanswers'] = 'Barajar preguntas y respuestas';
$string['shufflequestionsselected'] = 'Preguntas barajadas, por lo que algunas acciones relativas a las páginas no están disponibles. Cambiar la opción de Barajar {$a}.';
$string['shufflewithin'] = 'Barajar respuestas';
$string['shufflewithin_help'] = 'Si se activa, las respuestas de cada pregunta se barajarán. NOTA: Esta opción únicamente funciona en aquellas preguntas que tengan la opción de barajar activada en el banco de preguntas original.';
$string['totalmarksx'] = 'Puntuación total: {$a}';
$string['tutorial'] = 'Tutorial para Export quizzes';
$string['type'] = 'Tipo';
$string['updatedsumgrades'] = 'La suma de todas las puntuaciones del grupo {$a->letter} fue recalculada a {$a->grade}.';
$string['upload'] = 'Cargado/correcto';
$string['useridentification'] = 'Identificación de usuario';
$string['usernotincourse'] = 'El usuario {$a} no está en el curso.';
$string['white'] = 'Blanco';
$string['withselected'] = 'Con seleccionar...';
$string['zipfile'] = 'Archivo ZIP';
$string['gotocreate'] = 'Crear documentos';
$string['nopermissions'] = 'No tiene permisos para acceder aquí. Pulse para volver a inicio.';
$string['true'] = 'Verdadero';
$string['false'] = 'Falso';
$string['answer'] = 'Respuesta';
$string['DNI'] = 'DNI';
$string['campo_libre_help'] = 'Puede añadir algún texto a la cabecera o dejarlo en blanco, si lo desea.';
$string['headingoptions'] = 'Opciones de cabecera';
$string['campo_libre'] = 'Campo libre {$a}';
$string['user_name'] = 'Nombre';
$string['numbergroups_help'] = "Número de versiones diferentes que desea crear. Se crearán tantos documentos como usted elija aquí.";
$string['pluginadministration'] = "Administración del ExportQuiz";


