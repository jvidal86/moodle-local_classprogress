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
 * Catalan strings for the class progress plugin.
 *
 * @package    local_classprogress
 * @copyright  2026 Jordi Vidal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['addevaluation'] = 'Afegeix una avaluació';
$string['addschoolyear'] = 'Curs escolar nou';
$string['cachedef_showlink'] = 'Si es mostra l\'enllaç del Seguiment de classe a l\'usuari actual';
$string['classprogress:use'] = 'Utilitza el Seguiment de classe';
$string['daterange'] = '{$a->start} a {$a->end}';
$string['deleteschoolyear'] = 'Suprimeix el curs escolar';
$string['deleteschoolyearconfirm'] = 'Voleu suprimir el curs escolar {$a} amb els seus festius i avaluacions? Les activitats de seguiment amb la programació aprovada en conserven una còpia pròpia; les que tenen la programació en esborrany i el fan servir demanaran un altre curs escolar. Això no es pot desfer.';
$string['editschoolyear'] = 'Edita el curs escolar';
$string['enddate'] = 'Últim dia';
$string['error_baddate'] = 'Habiliteu la data i trieu un dia que existeixi.';
$string['error_changedelsewhere'] = 'Això s\'ha canviat en un altre lloc; torneu a carregar la pàgina per veure\'n la versió més recent';
$string['error_daterange'] = 'Introduïu una data entre el 2000-01-01 i el 2099-12-31.';
$string['error_endbeforestart'] = 'L\'últim dia és anterior al primer.';
$string['error_evaluationoutsideyear'] = 'L\'avaluació ha de ser dins del curs escolar.';
$string['error_evaluationsoverlap'] = 'Dues avaluacions no poden compartir cap dia.';
$string['error_holidaylines'] = 'Algunes línies de festius no es poden llegir. Corregiu-les o traieu-les; no s\'ha desat res.';
$string['error_holidaytexttoolong'] = 'El text dels festius és massa llarg: ha d\'ocupar menys de 32 kB.';
$string['error_name'] = 'Introduïu un nom de 255 caràcters com a màxim.';
$string['error_notfound'] = 'Aquest element no existeix o no teniu permís per veure\'l.';
$string['error_toomanyevaluations'] = 'Un curs escolar pot tenir 20 avaluacions com a màxim.';
$string['error_yeartoolong'] = 'Un curs escolar pot durar 400 dies com a màxim.';
$string['evaluation'] = 'Avaluació {no}';
$string['evaluationname'] = 'Nom';
$string['evaluations'] = 'Avaluacions';
$string['evaluationsintro'] = 'Opcional. Les avaluacions (1a, 2a, 3a avaluació) agrupen el resum de l\'exportació. Cadascuna ha de ser dins del curs escolar, i dues avaluacions no poden compartir cap dia. Per suprimir-ne una, deixeu-ne la fila buida i les dates sense habilitar.';
$string['holidayerror_bad_end_date'] = 'La data final del període no es pot llegir.';
$string['holidayerror_end_before_start'] = 'El període acaba abans de començar.';
$string['holidayerror_label_too_long'] = 'L\'etiqueta té més de 255 caràcters.';
$string['holidayerror_no_such_date'] = 'Aquesta data no existeix.';
$string['holidayerror_not_a_date'] = 'La línia no comença amb una data escrita aaaa-mm-dd o dd/mm/aaaa.';
$string['holidayerror_out_of_range'] = 'La data no és entre el 2000-01-01 i el 2099-12-31.';
$string['holidayerror_too_long'] = 'El període dura més de 400 dies.';
$string['holidayline'] = 'Línia {$a->line} ({$a->text}): {$a->reason}';
$string['holidays'] = 'Festius i dies no lectius';
$string['holidays_help'] = 'Un per línia: una data o un període i, després, una etiqueta opcional. Les dates s\'escriuen 2026-12-08 o 08/12/2026; un període són dues dates unides per «to», «a», «-», «–», «—», «..» o «→», per exemple «2026-12-22 a 2027-01-07 Nadal». Les línies en blanc i les que comencen amb # no es tenen en compte. Els festius s\'apliquen a totes les activitats de seguiment d\'aquest curs escolar fins que s\'aproven; una programació aprovada en conserva una còpia pròpia.';
$string['noplanactivities'] = 'Encara no podeu obrir cap activitat de seguiment. Per fer una programació, afegiu una activitat Seguiment de classe a un dels vostres cursos de Moodle i trieu-hi un dels vostres cursos escolars.';
$string['noschoolyears'] = 'Encara no teniu cap curs escolar. Un curs escolar conté les dates, els festius i les avaluacions que comparteixen totes les vostres activitats de seguiment d\'aquell curs escolar.';
$string['pendingsessions'] = 'Sessions pendents';
$string['planactivities'] = 'Activitats de seguiment';
$string['planactivity'] = 'Activitat de seguiment';
$string['pluginname'] = 'Seguiment de classe';
$string['privacy:metadata:local_classprogress_evaluation'] = 'Un període d\'avaluació d\'un curs escolar, que serveix per agrupar el resum de l\'exportació.';
$string['privacy:metadata:local_classprogress_evaluation:enddate'] = 'L\'últim dia del període d\'avaluació.';
$string['privacy:metadata:local_classprogress_evaluation:name'] = 'El nom del període d\'avaluació.';
$string['privacy:metadata:local_classprogress_evaluation:schoolyearid'] = 'El curs escolar al qual pertany el període d\'avaluació.';
$string['privacy:metadata:local_classprogress_evaluation:startdate'] = 'El primer dia del període d\'avaluació.';
$string['privacy:metadata:local_classprogress_evaluation:timecreated'] = 'Quan es va crear el període d\'avaluació.';
$string['privacy:metadata:local_classprogress_evaluation:timemodified'] = 'Quan es va modificar per última vegada el període d\'avaluació.';
$string['privacy:metadata:local_classprogress_holiday'] = 'Un festiu (un sol dia o un període) d\'un curs escolar, que pertany al propietari del curs escolar.';
$string['privacy:metadata:local_classprogress_holiday:enddate'] = 'L\'últim dia del festiu.';
$string['privacy:metadata:local_classprogress_holiday:label'] = 'El nom que el professor va donar al festiu.';
$string['privacy:metadata:local_classprogress_holiday:schoolyearid'] = 'El curs escolar al qual pertany el festiu.';
$string['privacy:metadata:local_classprogress_holiday:startdate'] = 'El primer dia del festiu.';
$string['privacy:metadata:local_classprogress_holiday:timecreated'] = 'Quan es va crear el festiu.';
$string['privacy:metadata:local_classprogress_holiday:timemodified'] = 'Quan es va modificar per última vegada el festiu.';
$string['privacy:metadata:local_classprogress_schoolyear'] = 'El curs escolar d\'un professor, amb les dates que contenen els festius i els períodes d\'avaluació que comparteixen totes les activitats de seguiment d\'aquest professor durant el curs.';
$string['privacy:metadata:local_classprogress_schoolyear:enddate'] = 'L\'últim dia del curs escolar.';
$string['privacy:metadata:local_classprogress_schoolyear:name'] = 'El nom del curs escolar.';
$string['privacy:metadata:local_classprogress_schoolyear:startdate'] = 'El primer dia del curs escolar.';
$string['privacy:metadata:local_classprogress_schoolyear:timecreated'] = 'Quan es va crear el curs escolar.';
$string['privacy:metadata:local_classprogress_schoolyear:timemodified'] = 'Quan es va modificar per última vegada el curs escolar.';
$string['privacy:metadata:local_classprogress_schoolyear:userid'] = 'El professor propietari del curs escolar.';
$string['privacy:path:schoolyear'] = 'Curs escolar {$a}';
$string['schoolyearcounts'] = 'Festius: {$a->holidays}. Avaluacions: {$a->evaluations}.';
$string['schoolyeardeleted'] = 'S\'ha suprimit el curs escolar.';
$string['schoolyearname'] = 'Nom';
$string['schoolyearsaved'] = 'S\'ha desat el curs escolar.';
$string['startdate'] = 'Primer dia';
$string['status'] = 'Estat';
