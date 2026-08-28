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
 * Read tracking for released assessments.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class feedback_service {
    /**
     * Mark an assessment as read by a student.
     */
    public function mark_read(int $assessmentid, int $userid): void {
        global $DB;
        $existing = $DB->get_record('local_crout_feedback_read', [
            'assessmentid' => $assessmentid,
            'userid' => $userid,
        ]);
        if ($existing) {
            $existing->timeread = time();
            $DB->update_record('local_crout_feedback_read', $existing);
        } else {
            $DB->insert_record('local_crout_feedback_read', (object)[
                'assessmentid' => $assessmentid,
                'userid' => $userid,
                'timeread' => time(),
            ]);
        }
    }

    /**
     * Get total unread feedback count for a student in a course.
     */
    public function get_unread_count(int $courseid, int $userid): int {
        global $DB;
        return (int)$DB->get_field_sql(
            "SELECT COUNT(*)
               FROM {local_crout_assessment} a
              WHERE a.courseid = :courseid
                AND a.userid = :userid
                AND a.status = :status
                AND a.feedback IS NOT NULL
                AND a.feedback != ''
                AND NOT EXISTS (
                    SELECT 1 FROM {local_crout_feedback_read} r
                     WHERE r.assessmentid = a.id AND r.userid = :userid2
                )",
            [
                'courseid' => $courseid,
                'userid' => $userid,
                'status' => constants::STATUS_RELEASED,
                'userid2' => $userid,
            ]
        );
    }

    /**
     * Get unread feedback count for a specific criterion and student.
     */
    public function get_unread_for_criterion(int $criterionid, int $userid): int {
        global $DB;
        return (int)$DB->get_field_sql(
            "SELECT COUNT(*)
               FROM {local_crout_assessment} a
              WHERE a.criterionid = :criterionid
                AND a.userid = :userid
                AND a.status = :status
                AND a.feedback IS NOT NULL
                AND a.feedback != ''
                AND NOT EXISTS (
                    SELECT 1 FROM {local_crout_feedback_read} r
                     WHERE r.assessmentid = a.id AND r.userid = :userid2
                )",
            [
                'criterionid' => $criterionid,
                'userid' => $userid,
                'status' => constants::STATUS_RELEASED,
                'userid2' => $userid,
            ]
        );
    }

    /**
     * Mark all released assessments with feedback as read for a student in a criterion.
     */
    public function mark_criterion_read(int $criterionid, int $userid): void {
        global $DB;
        $assessments = $DB->get_records_sql(
            "SELECT a.id
               FROM {local_crout_assessment} a
              WHERE a.criterionid = :criterionid
                AND a.userid = :userid
                AND a.status = :status
                AND a.feedback IS NOT NULL
                AND a.feedback != ''
                AND NOT EXISTS (
                    SELECT 1 FROM {local_crout_feedback_read} r
                     WHERE r.assessmentid = a.id AND r.userid = :userid2
                )",
            [
                'criterionid' => $criterionid,
                'userid' => $userid,
                'status' => constants::STATUS_RELEASED,
                'userid2' => $userid,
            ]
        );
        foreach ($assessments as $assessment) {
            $this->mark_read($assessment->id, $userid);
        }
    }
}
