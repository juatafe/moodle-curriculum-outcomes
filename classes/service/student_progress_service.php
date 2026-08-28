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

namespace local_criteriaoutcomes\service;

use local_criteriaoutcomes\constants;

/**
 * Builds the student dashboard model.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class student_progress_service {
    /**
     * @var assessment_service
     */
    private $assessmentservice;

    /**
     * @var feedback_service
     */
    private $feedbackservice;

    /**
     * @var judgement_service
     */
    private $judgementservice;

    /**
     * Create the dashboard service and its read-only collaborators.
     */
    public function __construct() {
        $this->assessmentservice = new assessment_service();
        $this->feedbackservice = new feedback_service();
        $this->judgementservice = new judgement_service();
    }

    /**
     * Build the complete student dashboard model for a course.
     *
     * @return array Course data with RA/CE hierarchy, criteria, evidence counts, etc.
     */
    public function for_student(int $courseid, int $userid): array {
        global $DB, $CFG;

        // Get course grade from Moodle Gradebook.
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->libdir . '/grade/grade_item.php');
        require_once($CFG->libdir . '/grade/grade_grade.php');
        $coursegrade = null;
        $item = \grade_item::fetch_course_item($courseid);
        if ($item) {
            $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
            if ($grade && $grade->finalgrade !== null) {
                $coursegrade = [
                    'grade' => grade_format_gradevalue($grade->finalgrade, $item),
                    'hidden' => (bool)$item->is_hidden(),
                ];
            }
        }

        // Get RA/CE hierarchy.
        $sql = "SELECT c.id AS criterionid, p.id AS parentid, p.code AS parentcode,
                       p.name AS parentname, p.weight AS parentweight, p.sortorder AS parentsort,
                       c.code AS criterioncode,
                       c.name AS criterionname, c.weight AS criterionweight,
                       c.sortorder AS criterionsort
                  FROM {local_crout_parent} p
                  JOIN {local_crout_criterion} c ON c.parentid = p.id
                  JOIN {local_crout_framework} f ON f.id = p.frameworkid
                 WHERE f.courseid = :courseid
                 ORDER BY p.sortorder, c.sortorder";
        $records = $DB->get_records_sql($sql, ['courseid' => $courseid]);

        // Group by parent.
        $parents = [];
        foreach ($records as $record) {
            $pid = (int)$record->parentid;
            if (!isset($parents[$pid])) {
                $parents[$pid] = [
                    'id' => $pid,
                    'code' => $record->parentcode,
                    'name' => $record->parentname,
                    'weight' => $record->parentweight,
                    'sortorder' => (int)$record->parentsort,
                    'criteria' => [],
                ];
            }
            $cid = (int)$record->criterionid;
            $evidencecount = $this->assessmentservice->get_evidence_count($cid, $userid);
            $feedbackcount = $this->assessmentservice->get_feedback_count($cid, $userid);
            $unreadcount = $this->feedbackservice->get_unread_for_criterion($cid, $userid);
            $judgement = $this->judgementservice->get_judgement($cid, $userid);

            $latest = $DB->get_record_sql(
                "SELECT * FROM {local_crout_assessment}
                  WHERE criterionid = :criterionid AND userid = :userid AND status = :status
                  ORDER BY timecreated DESC LIMIT 1",
                ['criterionid' => $cid, 'userid' => $userid, 'status' => constants::STATUS_RELEASED]
            );

            $latestlabel = null;
            if ($latest) {
                if ($latest->assessmentmode === constants::MODE_FEEDBACK_ONLY) {
                    $latestlabel = get_string('feedbacklabel', 'local_criteriaoutcomes');
                } else if ($latest->scalevalue !== null) {
                    $scale = $DB->get_field_sql(
                        "SELECT s.scale FROM {grade_outcomes} o
                           JOIN {local_crout_criterion} cr ON cr.outcomeid = o.id
                           JOIN {scale} s ON s.id = o.scaleid
                          WHERE cr.id = :criterionid",
                        ['criterionid' => $cid]
                    );
                    $latestlabel = $latest->scalevalue . '/' . ($scale ?: '?');
                } else if ($latest->value !== null) {
                    $latestlabel = round($latest->value * 100, 1) . '%';
                }
            }

            $parents[$pid]['criteria'][] = [
                'id' => $cid,
                'code' => $record->criterioncode,
                'name' => $record->criterionname,
                'weight' => $record->criterionweight,
                'sortorder' => (int)$record->criterionsort,
                'evidencecount' => $evidencecount,
                'feedbackcount' => $feedbackcount,
                'unreadcount' => $unreadcount,
                'judgement' => $judgement ? [
                    'scalevalue' => $judgement->scalevalue,
                    'comment' => $judgement->comment,
                ] : null,
                'latestlabel' => $latestlabel,
            ];
        }

        ksort($parents);

        return [
            'courseid' => $courseid,
            'userid' => $userid,
            'coursegrade' => $coursegrade,
            'parents' => array_values($parents),
        ];
    }

    /**
     * Build the criterion detail model with chronological evidence.
     */
    public function for_student_criterion(int $courseid, int $criterionid, int $userid): array {
        global $DB;

        $criterion = $DB->get_record_sql(
            "SELECT c.*, p.code AS parentcode, p.name AS parentname, p.weight AS parentweight
               FROM {local_crout_criterion} c
               JOIN {local_crout_parent} p ON p.id = c.parentid
               JOIN {local_crout_framework} f ON f.id = p.frameworkid
              WHERE c.id = :criterionid AND f.courseid = :courseid",
            ['criterionid' => $criterionid, 'courseid' => $courseid]
        );
        if (!$criterion) {
            throw new \invalid_parameter_exception('Criterion not found.');
        }

        // Get released assessments.
        $assessments = $this->assessmentservice->get_released_assessments($courseid, $criterionid, $userid);

        // Get judgement.
        $judgement = $this->judgementservice->get_judgement($criterionid, $userid);

        // Get rubric evidence (from mapping).
        $rubricevidence = $this->get_rubric_evidence($criterionid, $userid);

        // Get checklist evidence.
        $checklistevidence = $this->get_checklist_evidence($criterionid, $userid);

        // Get quiz evidence (from 0.2).
        $quizevidence = $this->get_quiz_evidence($criterionid, $userid);

        // Merge all evidence into chronological order.
        $evidence = [];
        foreach ($assessments as $assessment) {
            $evidence[] = [
                'type' => 'assessment',
                'date' => $assessment->timecreated,
                'sourcetype' => $assessment->sourcetype,
                'sourceid' => $assessment->sourceid,
                'value' => $assessment->value,
                'scalevalue' => $assessment->scalevalue,
                'feedback' => $assessment->feedback,
                'instrumenttype' => $assessment->instrumenttype,
                'status' => $assessment->status,
            ];
        }
        foreach ($rubricevidence as $rev) {
            $evidence[] = [
                'type' => 'rubric',
                'date' => $rev['timecreated'],
                'sourcetype' => constants::SOURCE_RUBRIC,
                'dimension' => $rev['dimension'],
                'level' => $rev['level'],
                'descriptor' => $rev['descriptor'],
                'remark' => $rev['remark'],
                'score' => $rev['score'],
            ];
        }
        foreach ($checklistevidence as $cev) {
            $evidence[] = [
                'type' => 'checklist',
                'date' => $cev['timecreated'],
                'sourcetype' => constants::SOURCE_CHECKLIST,
                'checklistname' => $cev['checklistname'],
                'items' => $cev['items'],
                'generalfeedback' => $cev['generalfeedback'],
            ];
        }
        foreach ($quizevidence as $qev) {
            $evidence[] = [
                'type' => 'quiz',
                'date' => $qev['date'],
                'sourcetype' => constants::SOURCE_QUIZ_CRITERION,
                'quizname' => $qev['quizname'],
                'attemptnum' => $qev['attemptnum'],
                'result' => $qev['result'],
                'questions' => $qev['questions'],
                'method' => $qev['method'],
            ];
        }

        usort($evidence, fn($a, $b) => ($b['date'] ?? 0) <=> ($a['date'] ?? 0));

        // Get scale info for criterion.
        $scaleinfo = null;
        if ($criterion->outcomeid) {
            $scaleinfo = $DB->get_record_sql(
                "SELECT o.scaleid, s.scale FROM {grade_outcomes} o
                   JOIN {scale} s ON s.id = o.scaleid
                  WHERE o.id = :outcomeid",
                ['outcomeid' => $criterion->outcomeid]
            );
        }

        return [
            'criterion' => (array)$criterion,
            'scale' => $scaleinfo ? (array)$scaleinfo : null,
            'judgement' => $judgement ? (array)$judgement : null,
            'evidence' => $evidence,
        ];
    }

    /**
     * Get rubric evidence for a criterion.
     */
    private function get_rubric_evidence(int $criterionid, int $userid): array {
        global $DB;
        $mappings = $DB->get_records('local_crout_rubricmap', [
            'curriculumcriterionid' => $criterionid,
        ]);
        if (empty($mappings)) {
            return [];
        }

        $results = [];
        foreach ($mappings as $mapping) {
            // Get the rubric criterion info.
            $rc = $DB->get_record('gradingform_rubric_criteria', ['id' => $mapping->rubriccriterionid]);
            if (!$rc) {
                continue;
            }

            // Get the grading definition.
            $def = $DB->get_record('grading_definitions', ['id' => $rc->definitionid]);
            if (!$def) {
                continue;
            }

            // Resolve the native assignment grade item for this student. In Advanced Grading,
            // grading_instances.raterid is the teacher and itemid is assign_grades.id.
            $instance = $DB->get_record_sql(
                "SELECT gi.*
                   FROM {grading_instances} gi
                   JOIN {grading_definitions} gd ON gd.id = gi.definitionid
                   JOIN {grading_areas} ga ON ga.id = gd.areaid
                   JOIN {context} ctx ON ctx.id = ga.contextid AND ctx.contextlevel = :modulecontext
                   JOIN {course_modules} cm ON cm.id = ctx.instanceid
                   JOIN {modules} md ON md.id = cm.module AND md.name = :assignmodule
                   JOIN {assign} a ON a.id = cm.instance
                   JOIN {assign_grades} ag ON ag.assignment = a.id AND ag.id = gi.itemid
                  WHERE gi.definitionid = :defid AND ag.userid = :userid
                  ORDER BY gi.timemodified DESC",
                [
                    'modulecontext' => CONTEXT_MODULE,
                    'assignmodule' => 'assign',
                    'defid' => $def->id,
                    'userid' => $userid,
                ],
                IGNORE_MULTIPLE
            );
            if (!$instance) {
                continue;
            }

            // Get filling.
            $filling = $DB->get_record('gradingform_rubric_fillings', [
                'instanceid' => $instance->id,
                'criterionid' => $mapping->rubriccriterionid,
            ]);
            if (!$filling || !$filling->levelid) {
                continue;
            }

            // Get level info.
            $level = $DB->get_record('gradingform_rubric_levels', ['id' => $filling->levelid]);
            if (!$level) {
                continue;
            }

            $results[] = [
                'timecreated' => $instance->timemodified,
                'rubriccriterionid' => $mapping->rubriccriterionid,
                'dimension' => format_text($rc->description, $rc->descriptionformat),
                'level' => format_text($level->definition, $level->definitionformat),
                'descriptor' => format_text($level->definition, $level->definitionformat),
                'remark' => $filling->remark ? format_text($filling->remark, $filling->remarkformat) : null,
                'score' => $level->score,
            ];
        }
        return $results;
    }

    /**
     * Get checklist evidence for a criterion.
     */
    private function get_checklist_evidence(int $criterionid, int $userid): array {
        global $DB;
        $mappings = $DB->get_records('local_crout_checklist_map', [
            'criterionid' => $criterionid,
        ]);
        if (empty($mappings)) {
            return [];
        }

        // Group by definition.
        $bydef = [];
        foreach ($mappings as $mapping) {
            $item = $DB->get_record('local_crout_checklist_item', ['id' => $mapping->itemid]);
            if (!$item) {
                continue;
            }
            $defid = $item->definitionid;
            if (!isset($bydef[$defid])) {
                $def = $DB->get_record('local_crout_checklist_def', ['id' => $defid]);
                $bydef[$defid] = [
                    'definitionid' => $defid,
                    'checklistname' => $def ? $def->name : '',
                    'items' => [],
                    'generalfeedback' => null,
                    'timecreated' => 0,
                ];
            }

            $response = $DB->get_record('local_crout_checklist_resp', [
                'definitionid' => $defid,
                'itemid' => $item->id,
                'userid' => $userid,
            ]);

            $bydef[$defid]['items'][] = [
                'itemid' => $item->id,
                'name' => $item->name,
                'state' => $response ? $response->state : constants::CHECKLIST_NOT_DONE,
                'feedback' => $response && $response->feedback ? $response->feedback : null,
            ];

            if ($response && $response->timemodified > $bydef[$defid]['timecreated']) {
                $bydef[$defid]['timecreated'] = $response->timemodified;
            }
        }

        return array_values($bydef);
    }

    /**
     * Get quiz criterion evidence for a criterion.
     */
    private function get_quiz_evidence(int $criterionid, int $userid): array {
        global $DB;
        // Get quiz mappings for this criterion.
        $mappings = $DB->get_records_sql(
            "SELECT qm.*, q.name AS quizname, qa.id AS attemptid, qa.attempt AS attemptnum,
                    qa.timefinish, qa.sumgrades
               FROM {local_crout_quizmap} qm
               JOIN {quiz} q ON q.id = qm.quizid
               JOIN {quiz_attempts} qa ON qa.quiz = qm.quizid AND qa.userid = :userid AND qa.preview = 0
              WHERE qm.criterionid = :criterionid
              ORDER BY qa.timefinish DESC",
            ['criterionid' => $criterionid, 'userid' => $userid]
        );

        $results = [];
        foreach ($mappings as $mapping) {
            // Use quiz_evidence_service for actual evidence.
            $evidence = (new quiz_evidence_service())->for_attempt($mapping->attemptid);
            $critdata = null;
            foreach ($evidence['criteria'] as $c) {
                if ((int)$c['criterionid'] === $criterionid) {
                    $critdata = $c;
                    break;
                }
            }
            if (!$critdata) {
                continue;
            }

            $results[] = [
                'date' => $mapping->timefinish,
                'quizname' => $mapping->quizname,
                'attemptnum' => $mapping->attemptnum,
                'result' => $critdata['result'],
                'questions' => $critdata['questions'],
                'method' => $critdata['aggregation'],
            ];
        }
        return $results;
    }
}
