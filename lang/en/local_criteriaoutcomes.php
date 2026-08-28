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

$string['pluginname'] = 'Curriculum outcomes';
$string['criteriaoutcomes'] = 'Curriculum outcomes';
$string['jsonfile'] = 'JSON file';
$string['jsontext'] = 'Or paste JSON';
$string['preview'] = 'Validate and preview';
$string['previewtitle'] = 'Import preview';
$string['importstep'] = 'Step {$a->current} of 4 — {$a->label}';
$string['importstepsource'] = 'Official source';
$string['importstepcurriculum'] = 'Curriculum';
$string['importstepvaluation'] = 'Valuation';
$string['importstepreview'] = 'Review and confirm';
$string['backandmodifysource'] = 'Back and modify source';
$string['backandmodifygroup'] = 'Back and modify title or course band';
$string['backandmodifycurriculum'] = 'Back and modify curriculum';
$string['backandmodifyvaluation'] = 'Back and modify valuation';
$string['choosevaluation'] = 'How will these outcomes be valued?';
$string['valuationhelp'] = 'Choose a pedagogical model. The recommended scale is created automatically or reused when it already exists.';
$string['valuationachievement'] = 'Achievement — 5 levels';
$string['valuationnumeric'] = 'Numeric — 0 to 10';
$string['valuationexisting'] = 'Use an existing Moodle scale (advanced)';
$string['existingadvanced'] = 'Existing scale';
$string['selectedcriteriacount'] = '{$a} criteria selected';
$string['selectall'] = 'Select all';
$string['deselectall'] = 'Deselect all';
$string['importselectedcriteria'] = 'Import {$a} criteria';
$string['selectcurriculumfp'] = 'Select the qualification and vocational module';
$string['selectcurriculumgeneral'] = 'Select the course band and subject';
$string['coursebandunknown'] = 'Course band not identified in the source';
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
$string['mappingweight'] = 'Question contribution weight';
$string['mappingweight_help'] = 'This weight controls how this question contributes to this criterion inside this quiz attempt. It does not change the curriculum weight of the criterion.';
$string['aggregations'] = 'Combine mapped questions';
$string['aggregations_help'] = 'How should questions mapped to the same criterion be combined within one quiz attempt? This does not calculate the student\'s overall achievement of the criterion.';
$string['aggregationshelp'] = 'Choose how two or more questions mapped to the same criterion are combined within this quiz attempt. This is quiz evidence, not the student\'s overall criterion achievement.';
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
$string['managecurriculum'] = 'Manage curriculum';
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
$string['boeimport'] = 'Import official BOE curriculum';
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
$string['existingscales'] = 'Existing Moodle scales';
$string['recommendedscales'] = 'Recommended Curriculum Outcomes scales';
$string['createscaletemplate'] = 'Create for this course';
$string['scaletemplatenumericname'] = 'Curriculum Outcomes — 0–10';
$string['scaletemplateachievementname'] = 'Curriculum Outcomes — Achievement (5 levels)';
$string['scaletemplatecreated'] = 'The recommended course scale is ready.';
$string['scaletemplatehelp'] = 'This ordinal scale assesses curriculum criteria. It does not automatically change activity grades, course grades or gradebook aggregation.';
$string['errorscaletemplateconflict'] = 'The plugin-owned scale definition no longer matches its template and was not modified.';
$string['scalelevelinsufficient'] = 'Insufficient';
$string['scalelevelsufficient'] = 'Sufficient';
$string['scalelevelgood'] = 'Good';
$string['scalelevelverygood'] = 'Very good';
$string['scalelevelexcellent'] = 'Excellent';
$string['importfromjson'] = 'Import from JSON';
$string['jsonimport'] = 'Import curriculum from JSON';
$string['qualification'] = 'FP qualification / title';
$string['qualificationunknown'] = 'Qualification not identified in the source';
$string['courseband'] = 'Course / course band';
$string['coursebandunknown'] = 'All courses';
$string['createsandselectscale'] = 'Create and select';
$string['scaleavailabletemplate'] = 'Available template';
$string['scaleavailablecourse'] = 'Available in this course';
$string['importcurriculum'] = 'Import curriculum';
$string['assessmentmappings'] = 'Assessment and mappings';
$string['currentcurriculum'] = 'Current curriculum';
