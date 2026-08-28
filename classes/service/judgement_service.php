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
 * Manual current judgement per criterion per student.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class judgement_service {
    /**
     * Save or update a current judgement.
     *
     * @return int Judgement ID.
     */
    public function save_judgement(
        int $courseid,
        int $criterionid,
        int $userid,
        ?int $scalevalue,
        ?string $comment,
        int $graderid
    ): int {
        global $DB;
        $now = time();

        $existing = $DB->get_record('local_crout_judgement', [
            'criterionid' => $criterionid,
            'userid' => $userid,
        ]);

        $record = (object)[
            'courseid' => $courseid,
            'criterionid' => $criterionid,
            'userid' => $userid,
            'scalevalue' => $scalevalue,
            'comment' => $comment,
            'commentformat' => FORMAT_PLAIN,
            'graderid' => $graderid,
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        if ($existing) {
            $record->id = $existing->id;
            $record->timecreated = $existing->timecreated;
            $DB->update_record('local_crout_judgement', $record);
            return (int)$record->id;
        }

        return (int)$DB->insert_record('local_crout_judgement', $record);
    }

    /**
     * Get the current judgement for a criterion and student.
     */
    public function get_judgement(int $criterionid, int $userid): ?object {
        global $DB;
        return $DB->get_record('local_crout_judgement', [
            'criterionid' => $criterionid,
            'userid' => $userid,
        ]) ?: null;
    }

    /**
     * Delete a judgement.
     */
    public function delete_judgement(int $judgementid): void {
        global $DB;
        $DB->delete_records('local_crout_judgement', ['id' => $judgementid]);
    }

    /**
     * Get judgements for all criteria for a student in a course.
     */
    public function get_judgements_for_student(int $courseid, int $userid): array {
        global $DB;
        $records = $DB->get_records('local_crout_judgement', [
            'courseid' => $courseid,
            'userid' => $userid,
        ]);
        $result = [];
        foreach ($records as $record) {
            $result[$record->criterionid] = $record;
        }
        return $result;
    }
}
