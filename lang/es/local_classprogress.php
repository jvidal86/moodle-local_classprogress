<?php
// This file is part of Moodle - http://moodle.org/
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
 * Spanish strings for the class progress plugin.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['addevaluation'] = 'Añadir una evaluación';
$string['addschoolyear'] = 'Nuevo curso escolar';
$string['cachedef_showlink'] = 'Si se muestra el enlace de Seguimiento de clase al usuario actual';
$string['classprogress:use'] = 'Usar Seguimiento de clase';
$string['daterange'] = '{$a->start} a {$a->end}';
$string['deleteschoolyear'] = 'Eliminar curso escolar';
$string['deleteschoolyearconfirm'] = '¿Eliminar el curso escolar {$a} con sus festivos y evaluaciones? Las actividades de programación aprobadas conservan su propia copia; las que están en borrador y lo usan pedirán otro curso escolar. Esta acción no se puede deshacer.';
$string['editschoolyear'] = 'Editar curso escolar';
$string['enddate'] = 'Último día';
$string['error_baddate'] = 'Active la fecha y elija un día que exista.';
$string['error_changedelsewhere'] = 'Se ha modificado en otro lugar; vuelva a cargar la página para ver la versión más reciente';
$string['error_daterange'] = 'Introduzca una fecha del 2000-01-01 al 2099-12-31.';
$string['error_endbeforestart'] = 'El último día es anterior al primero.';
$string['error_evaluationoutsideyear'] = 'La evaluación debe estar dentro del curso escolar.';
$string['error_evaluationsoverlap'] = 'Las evaluaciones no pueden compartir ningún día.';
$string['error_holidaylines'] = 'Algunas líneas de festivos no se pueden leer. Corríjalas o elimínelas; no se ha guardado nada.';
$string['error_holidaytexttoolong'] = 'El texto de los festivos es demasiado largo: debe ocupar menos de 32 KB.';
$string['error_name'] = 'Introduzca un nombre de hasta 255 caracteres.';
$string['error_notfound'] = 'Ese elemento no existe o no tiene permiso para verlo.';
$string['error_toomanyevaluations'] = 'Un curso escolar puede tener hasta 20 evaluaciones.';
$string['error_yeartoolong'] = 'Un curso escolar puede durar como máximo 400 días.';
$string['evaluation'] = 'Evaluación {no}';
$string['evaluationname'] = 'Nombre';
$string['evaluations'] = 'Evaluaciones';
$string['evaluationsintro'] = 'Opcional. Las evaluaciones (1.ª, 2.ª y 3.ª evaluación) agrupan el resumen de la exportación. Cada una debe estar dentro del curso escolar, y dos evaluaciones no pueden compartir ningún día. Para eliminar una, deje su fila vacía, con las fechas sin activar.';
$string['holidayerror_bad_end_date'] = 'No se puede leer la fecha final del período.';
$string['holidayerror_end_before_start'] = 'El período termina antes de empezar.';
$string['holidayerror_label_too_long'] = 'El nombre tiene más de 255 caracteres.';
$string['holidayerror_no_such_date'] = 'Esa fecha no existe.';
$string['holidayerror_not_a_date'] = 'La línea no empieza por una fecha escrita como aaaa-mm-dd o dd/mm/aaaa.';
$string['holidayerror_out_of_range'] = 'La fecha no está entre el 2000-01-01 y el 2099-12-31.';
$string['holidayerror_too_long'] = 'El período dura más de 400 días.';
$string['holidayline'] = 'Línea {$a->line} ({$a->text}): {$a->reason}';
$string['holidays'] = 'Festivos y días no lectivos';
$string['holidays_help'] = 'Uno por línea: una fecha o un período y, después, un nombre opcional. Las fechas se escriben 2026-12-08 o 08/12/2026; un período se escribe con dos fechas unidas por "a", "to", "-", "–", "—", ".." o "→", como en "2026-12-22 a 2027-01-07 Navidad". Las líneas en blanco y las que empiezan por # no se tienen en cuenta. Los festivos se aplican a todas las actividades de programación de este curso escolar mientras no estén aprobadas; una programación aprobada conserva su propia copia.';
$string['noplanactivities'] = 'Todavía no puede abrir ninguna actividad de programación. Para hacer una programación, añada una actividad Seguimiento de clase a uno de sus cursos de Moodle y asígnele uno de sus cursos escolares.';
$string['noschoolyears'] = 'Todavía no tiene ningún curso escolar. Un curso escolar contiene las fechas, los festivos y las evaluaciones que comparten todas sus actividades de programación de ese año.';
$string['pendingsessions'] = 'Sesiones pendientes';
$string['planactivities'] = 'Actividades de programación';
$string['planactivity'] = 'Actividad de programación';
$string['pluginname'] = 'Seguimiento de clase';
$string['privacy:metadata:local_classprogress_evaluation'] = 'Una evaluación de un curso escolar, que se usa para agrupar el resumen de la exportación.';
$string['privacy:metadata:local_classprogress_evaluation:enddate'] = 'El último día de la evaluación.';
$string['privacy:metadata:local_classprogress_evaluation:name'] = 'El nombre de la evaluación.';
$string['privacy:metadata:local_classprogress_evaluation:schoolyearid'] = 'El curso escolar al que pertenece la evaluación.';
$string['privacy:metadata:local_classprogress_evaluation:startdate'] = 'El primer día de la evaluación.';
$string['privacy:metadata:local_classprogress_evaluation:timecreated'] = 'Cuándo se creó la evaluación.';
$string['privacy:metadata:local_classprogress_evaluation:timemodified'] = 'Cuándo se modificó la evaluación por última vez.';
$string['privacy:metadata:local_classprogress_holiday'] = 'Un festivo (un solo día o un período) de un curso escolar, que pertenece al propietario del curso escolar.';
$string['privacy:metadata:local_classprogress_holiday:enddate'] = 'El último día del festivo.';
$string['privacy:metadata:local_classprogress_holiday:label'] = 'El nombre que el profesor dio al festivo.';
$string['privacy:metadata:local_classprogress_holiday:schoolyearid'] = 'El curso escolar al que pertenece el festivo.';
$string['privacy:metadata:local_classprogress_holiday:startdate'] = 'El primer día del festivo.';
$string['privacy:metadata:local_classprogress_holiday:timecreated'] = 'Cuándo se creó el festivo.';
$string['privacy:metadata:local_classprogress_holiday:timemodified'] = 'Cuándo se modificó el festivo por última vez.';
$string['privacy:metadata:local_classprogress_schoolyear'] = 'El curso escolar de un profesor, cuyas fechas contienen los festivos y las evaluaciones que comparten todas las actividades de programación de ese profesor en ese año.';
$string['privacy:metadata:local_classprogress_schoolyear:enddate'] = 'El último día del curso escolar.';
$string['privacy:metadata:local_classprogress_schoolyear:name'] = 'El nombre del curso escolar.';
$string['privacy:metadata:local_classprogress_schoolyear:startdate'] = 'El primer día del curso escolar.';
$string['privacy:metadata:local_classprogress_schoolyear:timecreated'] = 'Cuándo se creó el curso escolar.';
$string['privacy:metadata:local_classprogress_schoolyear:timemodified'] = 'Cuándo se modificó el curso escolar por última vez.';
$string['privacy:metadata:local_classprogress_schoolyear:userid'] = 'El profesor propietario del curso escolar.';
$string['privacy:path:schoolyear'] = 'Curso escolar {$a}';
$string['schoolyearcounts'] = 'Festivos: {$a->holidays}. Evaluaciones: {$a->evaluations}.';
$string['schoolyeardeleted'] = 'Curso escolar eliminado.';
$string['schoolyearname'] = 'Nombre';
$string['schoolyearsaved'] = 'Curso escolar guardado.';
$string['startdate'] = 'Primer día';
$string['status'] = 'Estado';
