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

/**
 * Maps rubric dimensions to curriculum criteria.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rubric_mapping_service {
    /**
     * Save a mapping from a rubric dimension to a curriculum criterion.
     *
     * @return int Mapping ID.
     */
    public function save_mapping(int $courseid, int $rubriccriterionid, int $curriculumcriterionid, ?float $weight = null): int {
        global $DB;
        $this->validate_entities($courseid, $rubriccriterionid, $curriculumcriterionid);

        $now = time();
        $existing = $DB->get_record('local_crout_rubricmap', [
            'rubriccriterionid' => $rubriccriterionid,
            'curriculumcriterionid' => $curriculumcriterionid,
        ]);

        if ($existing) {
            $existing->weight = $weight;
            $existing->timemodified = $now;
            $DB->update_record('local_crout_rubricmap', $existing);
            return (int)$existing->id;
        }

        return (int)$DB->insert_record('local_crout_rubricmap', (object)[
            'courseid' => $courseid,
            'rubriccriterionid' => $rubriccriterionid,
            'curriculumcriterionid' => $curriculumcriterionid,
            'weight' => $weight,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Delete a mapping.
     */
    public function delete_mapping(int $mappingid): void {
        global $DB;
        $DB->delete_records('local_crout_rubricmap', ['id' => $mappingid]);
    }

    /**
     * Delete a mapping by rubric and curriculum criterion IDs.
     */
    public function delete_mapping_by_ids(int $rubriccriterionid, int $curriculumcriterionid): void {
        global $DB;
        $record = $DB->get_record('local_crout_rubricmap', [
            'rubriccriterionid' => $rubriccriterionid,
            'curriculumcriterionid' => $curriculumcriterionid,
        ]);
        if ($record) {
            $DB->delete_records('local_crout_rubricmap', ['id' => $record->id]);
        }
    }

    /**
     * Replace mappings for one rubric criterion with an exact new set.
     */
    public function replace_mappings_for_rubric_criterion(
        int $courseid,
        int $rubriccriterionid,
        array $curriculumcriterionids
    ): void {
        global $DB;
        $curriculumcriterionids = array_values(array_unique(array_map('intval', $curriculumcriterionids)));
        foreach ($curriculumcriterionids as $cid) {
            $this->validate_entities($courseid, $rubriccriterionid, $cid);
        }
        $existing = $DB->get_records('local_crout_rubricmap', ['rubriccriterionid' => $rubriccriterionid]);
        $existingids = array_map(fn($r) => (int)$r->curriculumcriterionid, $existing);
        $tokeep = array_intersect($existingids, $curriculumcriterionids);
        $toadd = array_diff($curriculumcriterionids, $existingids);
        $toremove = array_diff($existingids, $curriculumcriterionids);
        $transaction = $DB->start_delegated_transaction();
        foreach ($toremove as $cid) {
            $DB->delete_records('local_crout_rubricmap', [
                'rubriccriterionid' => $rubriccriterionid,
                'curriculumcriterionid' => $cid,
            ]);
        }
        $now = time();
        foreach ($toadd as $cid) {
            $DB->insert_record('local_crout_rubricmap', (object)[
                'courseid' => $courseid,
                'rubriccriterionid' => $rubriccriterionid,
                'curriculumcriterionid' => $cid,
                'weight' => null,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        $transaction->allow_commit();
    }

    /**
     * Get all mappings for a rubric definition (via its criteria).
     *
     * @param int[] $rubriccriterionids Array of rubric criterion IDs.
     */
    public function get_mappings_for_rubric_criteria(array $rubriccriterionids): array {
        global $DB;
        if (empty($rubriccriterionids)) {
            return [];
        }
        [$idsql, $idparams] = $DB->get_in_or_equal($rubriccriterionids, SQL_PARAMS_NAMED);
        return $DB->get_records_sql(
            "SELECT m.*, c.code AS curriculumcode, c.name AS curriculumname
               FROM {local_crout_rubricmap} m
               JOIN {local_crout_criterion} c ON c.id = m.curriculumcriterionid
              WHERE m.rubriccriterionid $idsql",
            $idparams
        );
    }

    /**
     * Get all mappings that point to a curriculum criterion.
     */
    public function get_mappings_for_criterion(int $curriculumcriterionid): array {
        global $DB;
        return $DB->get_records('local_crout_rubricmap', [
            'curriculumcriterionid' => $curriculumcriterionid,
        ]);
    }

    /**
     * Validate that entities exist and belong to the same course.
     */
    private function validate_entities(int $courseid, int $rubriccriterionid, int $curriculumcriterionid): void {
        global $DB;

        // Validate rubric criterion exists.
        $rc = $DB->get_record('gradingform_rubric_criteria', ['id' => $rubriccriterionid], '*', MUST_EXIST);

        // Validate curriculum criterion belongs to the course.
        $sql = "SELECT f.courseid FROM {local_crout_criterion} c
                  JOIN {local_crout_parent} p ON p.id = c.parentid
                  JOIN {local_crout_framework} f ON f.id = p.frameworkid
                 WHERE c.id = :criterionid AND c.archived = 0 AND p.archived = 0 AND f.archived = 0";
        $criterioncourse = $DB->get_field_sql($sql, ['criterionid' => $curriculumcriterionid]);
        if ($criterioncourse === false) {
            throw new \invalid_parameter_exception('Curriculum criterion does not exist.');
        }
        if ((int)$criterioncourse !== $courseid) {
            throw new \invalid_parameter_exception('Curriculum criterion does not belong to the course.');
        }

        // Validate rubric definition belongs to the same course context.
        $def = $DB->get_record('grading_definitions', ['id' => $rc->definitionid], '*', MUST_EXIST);
        $area = $DB->get_record('grading_areas', ['id' => $def->areaid], '*', MUST_EXIST);
        $context = \context::instance_by_id($area->contextid, MUST_EXIST);
        if ($context->contextlevel === CONTEXT_COURSE && (int)$context->instanceid !== $courseid) {
            throw new \invalid_parameter_exception('Rubric does not belong to the course.');
        }
        if ($context->contextlevel === CONTEXT_MODULE) {
            $cm = get_coursemodule_from_id(0, $context->instanceid, false, MUST_EXIST);
            if ((int)$cm->course !== $courseid) {
                throw new \invalid_parameter_exception('Rubric does not belong to the course.');
            }
        }
    }
}
