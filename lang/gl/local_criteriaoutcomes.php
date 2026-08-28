<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * English language strings.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Resultados curriculares';
$string['criteriaoutcomes'] = 'Curriculum outcomes';
$string['jsonfile'] = 'JSON file';
$string['jsontext'] = 'Or paste JSON';
$string['preview'] = 'Validate and preview';
$string['previewtitle'] = 'Import preview';
$string['importstep'] = 'Paso {$a->current} de 4 — {$a->label}';
$string['importstepsource'] = 'Fonte oficial';
$string['importstepcurriculum'] = 'Currículo';
$string['importstepvaluation'] = 'Valoración';
$string['importstepreview'] = 'Revisar e confirmar';
$string['backandmodifysource'] = 'Volver e modificar a fonte';
$string['backandmodifygroup'] = 'Volver e modificar o título ou tramo';
$string['backandmodifycurriculum'] = 'Volver e modificar o currículo';
$string['backandmodifyvaluation'] = 'Volver e modificar a valoración';
$string['choosevaluation'] = 'Como se valorarán estes resultados?';
$string['valuationhelp'] = 'Escolle un modelo pedagóxico. A escala recomendada créase automaticamente ou reutilízase se xa existe.';
$string['valuationachievement'] = 'Logro — 5 niveis';
$string['valuationnumeric'] = 'Numérica — de 0 a 10';
$string['valuationexisting'] = 'Usar unha escala existente de Moodle (avanzado)';
$string['existingadvanced'] = 'Escala existente';
$string['selectedcriteriacount'] = '{$a} criterios seleccionados';
$string['selectall'] = 'Seleccionar todos';
$string['deselectall'] = 'Deseleccionar todos';
$string['importselectedcriteria'] = 'Importar {$a} criterios';
$string['selectcurriculumfp'] = 'Selecciona o título e o módulo profesional';
$string['selectcurriculumgeneral'] = 'Selecciona o curso e a materia';
$string['coursebandunknown'] = 'Tramo de cursos non identificado na fonte';
$string['selectscale'] = 'Outcome scale';
$string['confirmimport'] = 'Confirm import';
$string['previewexpired'] = 'The import preview expired. Generate it again before confirming.';
$string['noscales'] = 'No usable scale was found. Create a course scale in the gradebook before importing.';
$string['outcomesdisabled'] = 'Outcomes are disabled on this site. An administrator must enable “Enable outcomes” before this plugin can create or display outcomes.';
$string['opensettings'] = 'Open administration search';
$string['statusnew'] = 'NEW';
$string['statusexisting'] = 'EXISTING';
$string['statustext_changed'] = 'TEXT_CHANGED';
$string['statusscale_changed'] = 'SCALE_CHANGED';
$string['statustext_and_scale_changed'] = 'TEXT_AND_SCALE_CHANGED';
$string['statusmetadata_changed'] = 'METADATA_CHANGED';
$string['statusconflict'] = 'CONFLICT';
$string['conflictwarning'] = 'This shortname belongs to an Outcome not mapped by this plugin. It will not be modified or adopted.';
$string['scaleitemsblocked'] = 'Scale change blocked: activity grade items already use this Outcome.';
$string['scalegradesblocked'] = 'Scale change prohibited: academic Outcome grades already exist.';
$string['criteriaandevidence'] = 'Criteria and evidence';
$string['noevidence'] = 'No activity evidence yet';
$string['unnamedcurriculum'] = 'Unnamed curriculum';
$string['errorjsonsize'] = 'The JSON is empty or exceeds the 2 MB limit.';
$string['errorinvalidjson'] = 'Invalid JSON: {$a}';
$string['errornoresults'] = 'The JSON must contain a non-empty resultados array.';
$string['errorparentstructure'] = 'Group {$a} must have a name and a non-empty criterios array.';
$string['errorcriterionstructure'] = 'Every criterion in {$a} must have a name.';
$string['errorduplicatecode'] = 'Criterion code {$a} is duplicated.';
$string['erroremptytext'] = 'Codes and names cannot be empty.';
$string['errorweight'] = 'Weights, when supplied, must be non-negative numbers.';
$string['errorscale'] = 'The selected scale is not available in this course.';
$string['importcomplete'] = 'Import complete: {$a->new} new, {$a->existing} unchanged, {$a->textchanged} text updates, {$a->scalechanged} safe scale updates, {$a->metadatachanged} metadata updates, {$a->scaleblocked} scale changes blocked, {$a->conflict} conflicts skipped.';
$string['imported'] = 'Import';
$string['privacy:metadata'] = 'The plugin stores curriculum structure and outcome mappings, but no personal data.';
$string['criteriaoutcomes:view'] = 'View curriculum outcomes';
$string['criteriaoutcomes:import'] = 'Import curriculum outcomes';
$string['criteriaoutcomes:manage'] = 'Manage curriculum outcomes';
$string['criteriaoutcomes:mapquiz'] = 'Map quiz slots to curriculum criteria';
$string['criteriaoutcomes:viewquizevidence'] = 'View criterion evidence from quiz attempts';
$string['quizcriteriamapping'] = 'Quiz criteria mapping';
$string['quizcriteriamappingfor'] = 'Quiz criteria mapping: {$a}';
$string['noquizzes'] = 'This course has no visible quizzes.';
$string['noquizslots'] = 'This quiz has no question slots.';
$string['nocriteriaforquiz'] = 'Import curriculum criteria in this course before mapping a quiz.';
$string['slotheading'] = 'Question {$a->number}: {$a->name}{$a->random}';
$string['slotinfo'] = 'Type: {$a->type}; maximum mark: {$a->maxmark}. {$a->text}';
$string['randomslot'] = 'RANDOM SLOT';
$string['randomwarning'] = 'This mapping belongs to the random slot. Every selected question will be treated as evidence for these criteria; keep the random set curriculum-homogeneous.';
$string['mappingweight'] = 'Peso de contribución da pregunta';
$string['mappingweight_help'] = 'Este peso controla como contribúe a pregunta ao criterio dentro deste intento de cuestionario. Non cambia o peso curricular do criterio.';
$string['aggregations'] = 'Combinar preguntas mapeadas';
$string['aggregations_help'] = 'Como deben combinarse as preguntas mapeadas ao mesmo criterio nun intento? Isto non calcula o logro global do estudante no criterio.';
$string['aggregationshelp'] = 'Escolle como combinar dúas ou máis preguntas mapeadas ao mesmo criterio neste intento. É evidencia do cuestionario, non o logro global do criterio.';
$string['aggregationmean'] = 'Mean';
$string['aggregationweightedmean'] = 'Weighted mean';
$string['savemappings'] = 'Save mappings';
$string['mappingssaved'] = 'Quiz criterion mappings saved.';
$string['quizevidence'] = 'Quiz criterion evidence';
$string['attemptlink'] = '{$a->user} — attempt {$a->attempt}';
$string['quizevidencefor'] = '{$a->quiz}: {$a->user}, attempt {$a->attempt}';
$string['criterionresult'] = 'Result: {$a} %';
$string['criterionformula'] = '{$a->method}: {$a->numerator} / {$a->denominator}';
$string['criterionpending'] = 'Pending/incomplete: at least one mapped question is unfinished, invalid, or awaiting manual grading.';
$string['fraction'] = 'Fraction';
$string['pending'] = 'Pending';
$string['noquizevidence'] = 'This attempt has no mapped criterion evidence.';
$string['cleanorphanmappings'] = 'Remove orphaned mappings';
$string['orphanmappingsfound'] = '{$a} mapping(s) refer to slots that no longer exist.';
$string['orphansremoved'] = '{$a} orphaned mapping(s) removed.';

// 0.3.0-dev: Assessment and feedback.
$string['privacy:metadata:assessment:userid'] = 'The ID of the student being assessed.';
$string['privacy:metadata:assessment:graderid'] = 'The ID of the teacher who created the assessment.';
$string['privacy:metadata:assessment:feedback'] = 'Feedback text provided by the teacher.';
$string['privacy:metadata:checklistresp:userid'] = 'The ID of the student who responded to the checklist.';
$string['privacy:metadata:checklistresp:graderid'] = 'The ID of the teacher who graded the checklist.';
$string['privacy:metadata:checklistresp:feedback'] = 'Feedback text for a checklist item.';
$string['privacy:metadata:judgement:userid'] = 'The ID of the student being judged.';
$string['privacy:metadata:judgement:graderid'] = 'The ID of the teacher who made the judgement.';
$string['privacy:metadata:judgement:comment'] = 'Teacher comment on student progress.';
$string['privacy:metadata:feedbackread:userid'] = 'The ID of the student who read feedback.';
$string['privacy:metadata:importbatch'] = 'Curriculum import history and its optional user attribution.';
$string['privacy:metadata:importbatch:userid'] = 'The user who performed the curriculum import or undo operation.';
$string['feedbacklabel'] = 'With feedback';
$string['assesscriteria'] = 'Assess curriculum criteria';
$string['manageinstruments'] = 'Manage assessment instruments';
$string['viewallevidence'] = 'View all student evidence';
$string['viewownevidence'] = 'View own evidence';
$string['publishfeedback'] = 'Publish feedback';
$string['studentprogress'] = 'Student progress';
$string['mystudentprogress'] = 'My progress';
$string['teacherprogress'] = 'Student progress overview';
$string['feedbackonly'] = 'Feedback only';
$string['valueonly'] = 'Value only';
$string['valueandfeedback'] = 'Value and feedback';
$string['draft'] = 'Draft';
$string['released'] = 'Released';
$string['saveassessment'] = 'Save assessment';
$string['releaseassessment'] = 'Release';
$string['draftassessment'] = 'Return to draft';
$string['assessmentmode'] = 'Assessment mode';
$string['selectmode'] = 'How would you like to assess?';
$string['evidencecount'] = '{$a} evidence';
$string['feedbackcount'] = '{$a} feedbacks';
$string['unreadcount'] = '{$a} new';
$string['nocurrentjudgement'] = 'No current judgement set';
$string['currentjudgement'] = 'Current judgement';
$string['setjudgement'] = 'Set judgement';
$string['weightnotdefined'] = 'Weight not defined';
$string['latestlabel'] = 'Latest evidence: {$a}';
$string['assessmentfor'] = '{$a->criterion}: assessment for {$a->student}';
$string['scalevalue'] = 'Scale value';
$string['feedbacktext'] = 'Feedback';
$string['coursegrade'] = 'Moodle course grade: {$a}';
$string['criterionweight'] = 'weight {$a}';
$string['progresscounts'] = '{$a->evidence} evidence; {$a->feedback} feedback; {$a->unread} unread';
$string['criterionevidence'] = 'Criterion evidence';
$string['managecurriculum'] = 'Xestionar o currículo';
$string['importcurriculum'] = 'Import curriculum';
$string['importfromboe'] = 'Import from BOE';
$string['importhistory'] = 'Import history';
$string['showarchived'] = 'Show archived';
$string['hidearchived'] = 'Hide archived';
$string['archived'] = 'Archived';
$string['active'] = 'Active';
$string['unarchive'] = 'Restore';
$string['criterionrestored'] = 'The criterion is active again.';
$string['restorearchived'] = 'Restore archived criteria';
$string['unarchivecriterion'] = 'Restore {$a}';
$string['impactpreview'] = 'Impact preview';
$string['analyseimpact'] = 'Analyse impact';
$string['applysafeoperation'] = 'Apply safe operation';
$string['archiveusedconfirm'] = 'Archive used criteria instead of leaving them unchanged.';
$string['deletewarning'] = 'Unused plugin-owned criteria may be permanently deleted. Any criterion with academic use is preserved and can only be archived.';
$string['deleteapplysummary'] = 'Operation complete: {$a->deleted} deleted, {$a->archived} archived, {$a->blocked} unchanged.';
$string['policy'] = 'Policy';
$string['impact'] = 'Detected use';
$string['curriculum'] = 'Curriculum';
$string['criterion'] = 'Criterion';
$string['status'] = 'Status';
$string['actions'] = 'Actions';
$string['provider'] = 'Provider';
$string['source'] = 'Source';
$string['user'] = 'User';
$string['anonymoususer'] = 'Anonymous/deleted user';
$string['batchdetail'] = 'Import batch #{$a}';
$string['entity'] = 'Entity';
$string['action'] = 'Action';
$string['safeundo'] = 'Analyse safe undo';
$string['undopreview'] = 'Safe undo preview';
$string['confirmundo'] = 'Confirm safe undo';
$string['undosummary'] = 'Undo complete: {$a->deleted} deleted, {$a->archived} archived, {$a->restored} restored, {$a->matched} matched entities preserved, {$a->conflicted} conflicts preserved.';
$string['boeimport'] = 'Importar currículo oficial do BOE';
$string['boedisclaimer'] = 'Source: official AEBOE open-data consolidated legislation. Consolidated text is informational. Always verify the applicable official publication and regional curriculum.';
$string['boesearchlabel'] = 'BOE identifier or search text';
$string['boesearchresults'] = 'Official source results';
$string['boepublicationdate'] = 'Published: {$a}';
$string['boelastupdate'] = 'Last consolidated update: {$a}';
$string['educationfamily'] = 'Education family';
$string['bachillerato'] = 'Bachillerato';
$string['loadcurricula'] = 'Load curricula';
$string['selectcurriculum'] = 'Select module, subject or course band';
$string['nocurriculafound'] = 'No curriculum could be extracted deterministically from this source and family.';
$string['selectatleastone'] = 'Select at least one criterion to import.';
$string['statusremoved_from_source'] = 'REMOVED_FROM_SOURCE';
$string['eventcurriculumimported'] = 'Curriculum imported';
$string['eventcurriculumupdated'] = 'Curriculum updated';
$string['eventcurriculumarchived'] = 'Curriculum criteria archived';
$string['eventcurriculumdeleted'] = 'Curriculum criteria deleted';
$string['eventimportundone'] = 'Curriculum import undone';
$string['existingscales'] = 'Escalas de Moodle existentes';
$string['recommendedscales'] = 'Escalas recomendadas de Resultados Curriculares';
$string['createscaletemplate'] = 'Crear para este curso';
$string['scaletemplatenumericname'] = 'Resultados curriculares — 0–10';
$string['scaletemplateachievementname'] = 'Resultados curriculares — Logro (5 niveis)';
$string['scaletemplatecreated'] = 'A escala recomendada do curso está preparada.';
$string['scaletemplatehelp'] = 'Esta escala ordinal valora criterios curriculares. Non modifica automaticamente as notas das actividades, a nota do curso nin a agregación do libro de cualificacións.';
$string['errorscaletemplateconflict'] = 'A escala propiedade do complemento xa non coincide co seu modelo e non se modificou.';
$string['scalelevelinsufficient'] = 'Insuficiente';
$string['scalelevelsufficient'] = 'Suficiente';
$string['scalelevelgood'] = 'Ben';
$string['scalelevelverygood'] = 'Notable';
$string['scalelevelexcellent'] = 'Excelente';
$string['importfromjson'] = 'Importar desde JSON';
$string['jsonimport'] = 'Importar currículo desde JSON';
$string['qualification'] = 'Título de FP';
$string['qualificationunknown'] = 'Título non identificado na fonte';
$string['courseband'] = 'Curso ou tramo';
$string['coursebandunknown'] = 'Todos os cursos';
$string['createsandselectscale'] = 'Crear e seleccionar';
$string['scaleavailabletemplate'] = 'Modelo dispoñible';
$string['scaleavailablecourse'] = 'Dispoñible neste curso';
$string['importcurriculum'] = 'Importar currículo';
$string['assessmentmappings'] = 'Avaliación e mapeamentos';
$string['currentcurriculum'] = 'Currículo actual';
