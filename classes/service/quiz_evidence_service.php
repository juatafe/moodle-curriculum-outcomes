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
 * Builds transparent criterion evidence for one real quiz attempt.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class quiz_evidence_service {
    /**
     * Calculate mapped evidence for one attempt.
     *
     * @return array Attempt and criterion details.
     */
    public function for_attempt(int $attemptid): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/question/engine/lib.php');

        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', MUST_EXIST);
        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', MUST_EXIST);
        $sql = "SELECT m.*, s.slot, c.code, c.name,
                       COALESCE(cfg.aggregation, :defaultaggregation) AS aggregation,
                       p.code AS parentcode, p.name AS parentname
                  FROM {local_crout_quizmap} m
                  JOIN {quiz_slots} s ON s.id = m.slotid AND s.quizid = m.quizid
                  JOIN {local_crout_criterion} c ON c.id = m.criterionid
                  JOIN {local_crout_parent} p ON p.id = c.parentid
             LEFT JOIN {local_crout_quizcfg} cfg
                    ON cfg.quizid = m.quizid AND cfg.criterionid = m.criterionid
                 WHERE m.quizid = :quizid
              ORDER BY p.sortorder, c.sortorder, s.slot";
        $mappings = $DB->get_records_sql($sql, [
            'quizid' => $quiz->id, 'defaultaggregation' => criterion_aggregation_service::MEAN,
        ]);
        $quba = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);
        $criteria = [];
        foreach ($mappings as $mapping) {
            $qa = $quba->get_question_attempt((int)$mapping->slot);
            $state = $qa->get_state();
            $fraction = $qa->get_fraction();
            $pending = $state === \question_state::$needsgrading || !$state->is_finished() ||
                $state === \question_state::$invalid;
            if ($fraction === null && !$pending) {
                $fraction = 0.0;
            }
            if (!isset($criteria[$mapping->criterionid])) {
                $criteria[$mapping->criterionid] = [
                    'criterionid' => (int)$mapping->criterionid,
                    'code' => $mapping->code,
                    'name' => $mapping->name,
                    'parentcode' => $mapping->parentcode,
                    'parentname' => $mapping->parentname,
                    'aggregation' => $mapping->aggregation,
                    'questions' => [],
                ];
            }
            $criteria[$mapping->criterionid]['questions'][] = [
                'slot' => (int)$mapping->slot,
                'slotid' => (int)$mapping->slotid,
                'questionid' => (int)$qa->get_question_id(),
                'name' => $qa->get_question()->name,
                'fraction' => $fraction === null ? null : (float)$fraction,
                'weight' => (float)$mapping->weight,
                'pending' => $pending,
                'state' => $state->get_state_class(true),
                'statestring' => $qa->get_state_string(true),
            ];
        }
        $aggregator = new criterion_aggregation_service();
        foreach ($criteria as &$criterion) {
            $criterion['result'] = $aggregator->aggregate($criterion['questions'], $criterion['aggregation']);
        }
        unset($criterion);
        return ['attempt' => $attempt, 'quiz' => $quiz, 'criteria' => array_values($criteria)];
    }
}
