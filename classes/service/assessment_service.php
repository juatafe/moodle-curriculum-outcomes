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
 * CRUD for criterion assessments and feedback.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class assessment_service {
    /**
     * Create or update an assessment record.
     *
     * @param array $data Assessment data (courseid, criterionid, userid, sourcetype, etc.)
     * @return int Assessment ID.
     */
    public function save_assessment(array $data): int {
        global $DB;
        $this->validate_assessment_data($data);

        $now = time();
        $record = (object)[
            'courseid' => $data['courseid'],
            'criterionid' => $data['criterionid'],
            'userid' => $data['userid'],
            'sourcetype' => $data['sourcetype'],
            'sourceid' => $data['sourceid'],
            'sourceinstanceid' => $data['sourceinstanceid'] ?? null,
            'assessmentmode' => $data['assessmentmode'],
            'value' => $data['value'] ?? null,
            'scalevalue' => $data['scalevalue'] ?? null,
            'feedback' => $data['feedback'] ?? null,
            'feedbackformat' => $data['feedbackformat'] ?? FORMAT_PLAIN,
            'instrumenttype' => $data['instrumenttype'] ?? null,
            'instrumentinstanceid' => $data['instrumentinstanceid'] ?? null,
            'status' => $data['status'] ?? constants::STATUS_DRAFT,
            'graderid' => $data['graderid'],
            'timecreated' => $now,
            'timemodified' => $now,
            'timepublished' => null,
        ];

        if (!empty($data['id'])) {
            $existing = $DB->get_record('local_crout_assessment', ['id' => $data['id']], '*', MUST_EXIST);
            $record->id = $existing->id;
            $record->timecreated = $existing->timecreated;
            if ($existing->status === constants::STATUS_RELEASED && $record->status === constants::STATUS_RELEASED) {
                $record->timepublished = $existing->timepublished;
            }
            $DB->update_record('local_crout_assessment', $record);
            return (int)$record->id;
        }

        return (int)$DB->insert_record('local_crout_assessment', $record);
    }

    /**
     * Release an assessment (make visible to students).
     */
    public function release_assessment(int $assessmentid): void {
        global $DB;
        $record = $DB->get_record('local_crout_assessment', ['id' => $assessmentid], '*', MUST_EXIST);
        $record->status = constants::STATUS_RELEASED;
        $record->timepublished = time();
        $record->timemodified = time();
        $DB->update_record('local_crout_assessment', $record);
    }

    /**
     * Set an assessment back to draft.
     */
    public function draft_assessment(int $assessmentid): void {
        global $DB;
        $record = $DB->get_record('local_crout_assessment', ['id' => $assessmentid], '*', MUST_EXIST);
        $record->status = constants::STATUS_DRAFT;
        $record->timepublished = null;
        $record->timemodified = time();
        $DB->update_record('local_crout_assessment', $record);
    }

    /**
     * Get all assessments for a criterion and student (teacher view, includes drafts).
     */
    public function get_assessments_for_criterion(int $courseid, int $criterionid, int $userid): array {
        global $DB;
        return $DB->get_records('local_crout_assessment', [
            'courseid' => $courseid,
            'criterionid' => $criterionid,
            'userid' => $userid,
        ], 'timecreated DESC');
    }

    /**
     * Get only released assessments (student view).
     */
    public function get_released_assessments(int $courseid, int $criterionid, int $userid): array {
        global $DB;
        return $DB->get_records('local_crout_assessment', [
            'courseid' => $courseid,
            'criterionid' => $criterionid,
            'userid' => $userid,
            'status' => constants::STATUS_RELEASED,
        ], 'timecreated DESC');
    }

    /**
     * Get released assessment by ID.
     */
    public function get_released_assessment(int $assessmentid): ?object {
        global $DB;
        $record = $DB->get_record('local_crout_assessment', ['id' => $assessmentid]);
        if ($record && $record->status === constants::STATUS_RELEASED) {
            return $record;
        }
        return null;
    }

    /**
     * Get assessment by ID (any status).
     */
    public function get_assessment(int $assessmentid): ?object {
        global $DB;
        return $DB->get_record('local_crout_assessment', ['id' => $assessmentid]) ?: null;
    }

    /**
     * Delete an assessment.
     */
    public function delete_assessment(int $assessmentid): void {
        global $DB;
        $DB->delete_records('local_crout_feedback_read', ['assessmentid' => $assessmentid]);
        $DB->delete_records('local_crout_assessment', ['id' => $assessmentid]);
    }

    /**
     * Get evidence count for a criterion and student (released only).
     */
    public function get_evidence_count(int $criterionid, int $userid): int {
        global $DB;
        return $DB->count_records('local_crout_assessment', [
            'criterionid' => $criterionid,
            'userid' => $userid,
            'status' => constants::STATUS_RELEASED,
        ]);
    }

    /**
     * Get released feedback count for a criterion and student.
     */
    public function get_feedback_count(int $criterionid, int $userid): int {
        global $DB;
        return $DB->count_records_select(
            'local_crout_assessment',
            "criterionid = :criterionid AND userid = :userid AND status = :status AND feedback IS NOT NULL AND feedback != ''",
            [
                'criterionid' => $criterionid,
                'userid' => $userid,
                'status' => constants::STATUS_RELEASED,
            ]
        );
    }

    /**
     * Validate assessment data before save.
     */
    private function validate_assessment_data(array $data): void {
        if (empty($data['courseid']) || !is_numeric($data['courseid'])) {
            throw new \invalid_parameter_exception('courseid is required and must be an integer.');
        }
        if (empty($data['criterionid']) || !is_numeric($data['criterionid'])) {
            throw new \invalid_parameter_exception('criterionid is required and must be an integer.');
        }
        if (empty($data['userid']) || !is_numeric($data['userid'])) {
            throw new \invalid_parameter_exception('userid is required and must be an integer.');
        }
        if (empty($data['sourcetype']) || !in_array($data['sourcetype'], constants::VALID_SOURCE_TYPES, true)) {
            throw new \invalid_parameter_exception('sourcetype is invalid.');
        }
        if (!isset($data['sourceid']) || !is_numeric($data['sourceid'])) {
            throw new \invalid_parameter_exception('sourceid is required and must be an integer.');
        }
        if (empty($data['assessmentmode']) || !in_array($data['assessmentmode'], constants::VALID_ASSESSMENT_MODES, true)) {
            throw new \invalid_parameter_exception('assessmentmode is invalid.');
        }
        if (empty($data['graderid']) || !is_numeric($data['graderid'])) {
            throw new \invalid_parameter_exception('graderid is required and must be an integer.');
        }
        if (!empty($data['status']) && !in_array($data['status'], constants::VALID_STATUSES, true)) {
            throw new \invalid_parameter_exception('status is invalid.');
        }

        // Mode-specific validation.
        if ($data['assessmentmode'] === constants::MODE_FEEDBACK_ONLY) {
            if (isset($data['value']) || isset($data['scalevalue'])) {
                throw new \invalid_parameter_exception('FEEDBACK_ONLY mode must not have a value or scalevalue.');
            }
        }
    }
}
