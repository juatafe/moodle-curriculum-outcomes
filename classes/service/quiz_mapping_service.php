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
 * Validates and persists quiz-slot criterion mappings.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class quiz_mapping_service {
    /**
     * Return stable slot records augmented with safe question metadata.
     */
    public function get_slots(int $quizid): array {
        global $DB;
        $structure = \mod_quiz\quiz_settings::create($quizid)->get_structure();
        $result = [];
        foreach ($structure->get_slots() as $slot) {
            $question = $structure->get_question_in_slot($slot->slot);
            $slot->questionname = (string)($question->name ?? '');
            $slot->questiontext = shorten_text(trim(strip_tags((string)($question->questiontext ?? ''))), 160);
            $slot->questiontype = (string)($question->qtype ?? '');
            $slot->random = $DB->record_exists('question_set_references', [
                'component' => 'mod_quiz',
                'questionarea' => 'slot',
                'itemid' => $slot->id,
            ]);
            $result[$slot->id] = $slot;
        }
        return $result;
    }

    /**
     * Return mapped records, including orphan state.
     */
    public function get_mappings(int $quizid): array {
        global $DB;
        $sql = "SELECT m.*, CASE WHEN s.id IS NULL THEN 1 ELSE 0 END AS orphaned
                  FROM {local_crout_quizmap} m
             LEFT JOIN {quiz_slots} s ON s.id = m.slotid AND s.quizid = m.quizid
                 WHERE m.quizid = :quizid ORDER BY m.slotid, m.criterionid";
        return array_values($DB->get_records_sql($sql, ['quizid' => $quizid]));
    }

    /**
     * Return quiz criterion configurations keyed by criterion ID.
     */
    public function get_configurations(int $quizid): array {
        global $DB;
        $result = [];
        foreach ($DB->get_records('local_crout_quizcfg', ['quizid' => $quizid]) as $configuration) {
            $result[$configuration->criterionid] = $configuration;
        }
        return $result;
    }

    /**
     * Create or update one mapping after cross-course validation.
     */
    public function save_mapping(int $quizid, int $slotid, int $criterionid, float $weight = 1.0): int {
        global $DB;
        [$quiz, $slot] = $this->validate_entities($quizid, $slotid, $criterionid);
        unset($quiz, $slot);
        if (!is_finite($weight) || $weight <= 0) {
            throw new \invalid_parameter_exception('Weight must be a finite positive number.');
        }
        $now = time();
        $existing = $DB->get_record('local_crout_quizmap', [
            'quizid' => $quizid, 'slotid' => $slotid, 'criterionid' => $criterionid,
        ]);
        if ($existing) {
            $existing->weight = $weight;
            $existing->timemodified = $now;
            $DB->update_record('local_crout_quizmap', $existing);
            return (int)$existing->id;
        }
        return $DB->insert_record('local_crout_quizmap', (object)[
            'quizid' => $quizid, 'slotid' => $slotid, 'criterionid' => $criterionid,
            'weight' => $weight, 'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    /**
     * Save the aggregation for a mapped criterion.
     */
    public function save_configuration(int $quizid, int $criterionid, string $aggregation): int {
        global $DB;
        $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
        $this->validate_criterion_course($criterionid, (int)$quiz->course);
        if (
            !in_array($aggregation, [criterion_aggregation_service::MEAN,
                criterion_aggregation_service::WEIGHTED_MEAN], true)
        ) {
            throw new \invalid_parameter_exception('Invalid aggregation method.');
        }
        $now = time();
        $existing = $DB->get_record('local_crout_quizcfg', ['quizid' => $quizid, 'criterionid' => $criterionid]);
        if ($existing) {
            $existing->aggregation = $aggregation;
            $existing->timemodified = $now;
            $DB->update_record('local_crout_quizcfg', $existing);
            return (int)$existing->id;
        }
        return $DB->insert_record('local_crout_quizcfg', (object)[
            'quizid' => $quizid, 'criterionid' => $criterionid, 'aggregation' => $aggregation,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    /**
     * Delete one mapping and unused configuration safely.
     */
    public function delete_mapping(int $quizid, int $slotid, int $criterionid): void {
        global $DB;
        $DB->delete_records('local_crout_quizmap', [
            'quizid' => $quizid, 'slotid' => $slotid, 'criterionid' => $criterionid,
        ]);
        if (!$DB->record_exists('local_crout_quizmap', ['quizid' => $quizid, 'criterionid' => $criterionid])) {
            $DB->delete_records('local_crout_quizcfg', ['quizid' => $quizid, 'criterionid' => $criterionid]);
        }
    }

    /**
     * Remove only mappings whose slot no longer exists in the quiz.
     */
    public function clean_orphans(int $quizid): int {
        global $DB;
        $orphans = array_filter($this->get_mappings($quizid), fn($mapping) => !empty($mapping->orphaned));
        foreach ($orphans as $mapping) {
            $this->delete_mapping($quizid, (int)$mapping->slotid, (int)$mapping->criterionid);
        }
        return count($orphans);
    }

    /**
     * Validate quiz, slot and criterion ownership.
     */
    private function validate_entities(int $quizid, int $slotid, int $criterionid): array {
        global $DB;
        $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
        $slot = $DB->get_record('quiz_slots', ['id' => $slotid], '*', MUST_EXIST);
        if ((int)$slot->quizid !== $quizid) {
            throw new \invalid_parameter_exception('Slot does not belong to the quiz.');
        }
        $this->validate_criterion_course($criterionid, (int)$quiz->course);
        return [$quiz, $slot];
    }

    /**
     * Validate criterion ownership through its framework course.
     */
    private function validate_criterion_course(int $criterionid, int $courseid): void {
        global $DB;
        $sql = "SELECT f.courseid FROM {local_crout_criterion} c
                  JOIN {local_crout_parent} p ON p.id = c.parentid
                  JOIN {local_crout_framework} f ON f.id = p.frameworkid
                 WHERE c.id = :criterionid AND c.archived = 0 AND p.archived = 0 AND f.archived = 0";
        $criterioncourse = $DB->get_field_sql($sql, ['criterionid' => $criterionid]);
        if ($criterioncourse === false) {
            throw new \invalid_parameter_exception('Criterion does not exist.');
        }
        if ((int)$criterioncourse !== $courseid) {
            throw new \invalid_parameter_exception('Criterion does not belong to the quiz course.');
        }
    }
}
