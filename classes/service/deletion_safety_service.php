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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/grade/constants.php');
require_once($CFG->libdir . '/grade/grade_outcome.php');

/**
 * Revalidates every known academic relationship before curriculum deletion.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class deletion_safety_service {
    /**
     * The plugin owns the unused Outcome and may delete it.
     */
    public const SAFE_DELETE = 'safe_delete';
    /**
     * Academic/plugin use exists; preserve everything and archive only.
     */
    public const ARCHIVE_ONLY = 'archive_only';
    /**
     * Ownership or course scope cannot be proven.
     */
    public const BLOCKED = 'blocked';

    /**
     * Analyze one criterion against all real 0.3/0.4 references.
     */
    public function analyze(int $courseid, int $criterionid): array {
        global $DB;
        $criterion = $DB->get_record_sql(
            "SELECT c.*, p.frameworkid, f.courseid
               FROM {local_crout_criterion} c
               JOIN {local_crout_parent} p ON p.id = c.parentid
               JOIN {local_crout_framework} f ON f.id = p.frameworkid
              WHERE c.id = :criterionid AND f.courseid = :courseid",
            ['criterionid' => $criterionid, 'courseid' => $courseid]
        );
        if (!$criterion || empty($criterion->outcomeowned)) {
            return ['criterionid' => $criterionid, 'policy' => self::BLOCKED, 'reasons' => ['ownership'], 'counts' => []];
        }
        $outcome = $DB->get_record('grade_outcomes', ['id' => $criterion->outcomeid, 'courseid' => $courseid]);
        if (!$outcome) {
            return ['criterionid' => $criterionid, 'policy' => self::BLOCKED, 'reasons' => ['outcome'], 'counts' => []];
        }

        $gradeitems = $DB->get_records('grade_items', [
            'courseid' => $courseid,
            'outcomeid' => $criterion->outcomeid,
        ], '', 'id');
        $gradecount = 0;
        if ($gradeitems) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($gradeitems), SQL_PARAMS_NAMED, 'gradeitem');
            $gradecount = $DB->count_records_select('grade_grades', "itemid $insql", $params);
        }
        $assessmentids = $DB->get_records('local_crout_assessment', [
            'courseid' => $courseid,
            'criterionid' => $criterionid,
        ], '', 'id');
        $readcount = 0;
        if ($assessmentids) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($assessmentids), SQL_PARAMS_NAMED, 'assessment');
            $readcount = $DB->count_records_select('local_crout_feedback_read', "assessmentid $insql", $params);
        }
        $checklistmaps = $DB->get_records('local_crout_checklist_map', ['criterionid' => $criterionid], '', 'itemid');
        $checklistresponses = 0;
        if ($checklistmaps) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($checklistmaps), SQL_PARAMS_NAMED, 'checkitem');
            $checklistresponses = $DB->count_records_select('local_crout_checklist_resp', "itemid $insql", $params);
        }
        $counts = [
            'gradeitems' => count($gradeitems),
            'grades' => $gradecount,
            'activityassociations' => count($gradeitems),
            'quizmappings' => $DB->count_records('local_crout_quizmap', ['criterionid' => $criterionid]) +
                $DB->count_records('local_crout_quizcfg', ['criterionid' => $criterionid]),
            'assessments' => count($assessmentids),
            'feedback' => $DB->count_records_select(
                'local_crout_assessment',
                "courseid = :courseid AND criterionid = :criterionid AND feedback IS NOT NULL AND feedback <> ''",
                ['courseid' => $courseid, 'criterionid' => $criterionid]
            ),
            'checklistmappings' => count($checklistmaps),
            'checklistresponses' => $checklistresponses,
            'rubricmappings' => $DB->count_records('local_crout_rubricmap', ['curriculumcriterionid' => $criterionid]),
            'judgements' => $DB->count_records('local_crout_judgement', ['criterionid' => $criterionid]),
            'feedbackreads' => $readcount,
            'auditreferences' => $DB->count_records('local_crout_importitem', [
                'entitytype' => 'criterion', 'entityid' => $criterionid,
            ]),
        ];
        $academiccounts = $counts;
        unset($academiccounts['auditreferences']);
        $reasons = array_keys(array_filter($academiccounts, static fn(int $count): bool => $count > 0));
        return [
            'criterionid' => $criterionid,
            'outcomeid' => (int)$criterion->outcomeid,
            'policy' => $reasons ? self::ARCHIVE_ONLY : self::SAFE_DELETE,
            'reasons' => $reasons,
            'counts' => $counts,
        ];
    }

    /**
     * Analyze a course-scoped selection.
     */
    public function analyze_many(int $courseid, array $criterionids): array {
        $result = [];
        foreach (array_unique(array_map('intval', $criterionids)) as $criterionid) {
            $result[$criterionid] = $this->analyze($courseid, $criterionid);
        }
        return $result;
    }

    /**
     * Apply the safe policy after re-running analysis inside the transaction.
     */
    public function apply(int $courseid, array $criterionids, bool $archiveused): array {
        global $DB;
        require_capability('local/criteriaoutcomes:manage', \context_course::instance($courseid));
        $transaction = $DB->start_delegated_transaction();
        $summary = ['deleted' => 0, 'archived' => 0, 'blocked' => 0];
        foreach ($this->analyze_many($courseid, $criterionids) as $analysis) {
            if ($analysis['policy'] === self::SAFE_DELETE) {
                $this->delete_unused($courseid, $analysis['criterionid'], $analysis['outcomeid']);
                $summary['deleted']++;
            } else if ($analysis['policy'] === self::ARCHIVE_ONLY && $archiveused) {
                $this->archive($courseid, $analysis['criterionid']);
                $summary['archived']++;
            } else {
                $summary['blocked']++;
            }
        }
        $transaction->allow_commit();
        $eventdata = [
            'context' => \context_course::instance($courseid),
            'other' => ['summary' => json_encode($summary)],
        ];
        if ($summary['archived'] > 0) {
            \local_criteriaoutcomes\event\curriculum_archived::create($eventdata)->trigger();
        }
        if ($summary['deleted'] > 0) {
            \local_criteriaoutcomes\event\curriculum_deleted::create($eventdata)->trigger();
        }
        return $summary;
    }

    /**
     * Archive a criterion and retain every academic relationship.
     */
    public function archive(int $courseid, int $criterionid): void {
        global $DB;
        $criterion = $DB->get_record_sql(
            "SELECT c.id, c.parentid, p.frameworkid
               FROM {local_crout_criterion} c
               JOIN {local_crout_parent} p ON p.id = c.parentid
               JOIN {local_crout_framework} f ON f.id = p.frameworkid
              WHERE c.id = :criterionid AND f.courseid = :courseid",
            ['criterionid' => $criterionid, 'courseid' => $courseid],
            MUST_EXIST
        );
        $DB->set_field('local_crout_criterion', 'archived', 1, ['id' => $criterionid]);
        if (!$DB->record_exists('local_crout_criterion', ['parentid' => $criterion->parentid, 'archived' => 0])) {
            $DB->set_field('local_crout_parent', 'archived', 1, ['id' => $criterion->parentid]);
        }
        if (
            !$DB->record_exists_sql(
                "SELECT 1 FROM {local_crout_parent} WHERE frameworkid = :frameworkid AND archived = 0",
                ['frameworkid' => $criterion->frameworkid]
            )
        ) {
            $DB->set_field('local_crout_framework', 'archived', 1, ['id' => $criterion->frameworkid]);
        }
    }

    /**
     * Delete one revalidated unused owned criterion and empty ancestors.
     */
    private function delete_unused(int $courseid, int $criterionid, int $outcomeid): void {
        global $DB;
        $criterion = $DB->get_record_sql(
            "SELECT c.parentid, p.frameworkid
               FROM {local_crout_criterion} c
               JOIN {local_crout_parent} p ON p.id = c.parentid
               JOIN {local_crout_framework} f ON f.id = p.frameworkid
              WHERE c.id = :criterionid AND f.courseid = :courseid",
            ['criterionid' => $criterionid, 'courseid' => $courseid],
            MUST_EXIST
        );
        // Retain the immutable audit snapshot/source key without a stale local id.
        $DB->set_field_select(
            'local_crout_importitem',
            'entityid',
            null,
            'entitytype = :entitytype AND entityid = :entityid',
            ['entitytype' => 'criterion', 'entityid' => $criterionid]
        );
        $DB->delete_records('local_crout_criterion', ['id' => $criterionid]);
        $outcome = \grade_outcome::fetch(['id' => $outcomeid, 'courseid' => $courseid]);
        if ($outcome) {
            $outcome->delete('local_criteriaoutcomes');
        }
        if (!$DB->record_exists('local_crout_criterion', ['parentid' => $criterion->parentid])) {
            $DB->delete_records('local_crout_parent', ['id' => $criterion->parentid]);
        }
        if (!$DB->record_exists('local_crout_parent', ['frameworkid' => $criterion->frameworkid])) {
            $DB->set_field('local_crout_importbatch', 'frameworkid', null, ['frameworkid' => $criterion->frameworkid]);
            $DB->delete_records('local_crout_framework', ['id' => $criterion->frameworkid]);
        }
    }
}
