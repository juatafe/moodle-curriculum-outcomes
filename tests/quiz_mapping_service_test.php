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

namespace local_criteriaoutcomes;

use local_criteriaoutcomes\service\criterion_aggregation_service;
use local_criteriaoutcomes\service\quiz_mapping_service;

/**
 * Quiz-slot mapping validation and persistence tests.
 *
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class quiz_mapping_service_test extends \advanced_testcase {
    public function test_mapping_cardinalities_updates_and_delete(): void {
        global $DB;
        [$course, $quiz, $slots, $criteria] = $this->fixture();
        $service = new quiz_mapping_service();
        $firstid = $service->save_mapping($quiz->id, $slots[0]->id, $criteria[0]->id, 1);
        $this->assertSame($firstid, $service->save_mapping($quiz->id, $slots[0]->id, $criteria[0]->id, 2.5));
        $service->save_mapping($quiz->id, $slots[0]->id, $criteria[1]->id, 1);
        $service->save_mapping($quiz->id, $slots[1]->id, $criteria[0]->id, 3);
        $this->assertCount(3, $service->get_mappings($quiz->id));
        $this->assertEquals(2.5, $DB->get_field('local_crout_quizmap', 'weight', ['id' => $firstid]));
        $service->save_configuration($quiz->id, $criteria[0]->id, criterion_aggregation_service::WEIGHTED_MEAN);
        $service->save_configuration($quiz->id, $criteria[0]->id, criterion_aggregation_service::MEAN);
        $this->assertSame(
            criterion_aggregation_service::MEAN,
            $DB->get_field('local_crout_quizcfg', 'aggregation', ['quizid' => $quiz->id,
            'criterionid' => $criteria[0]->id])
        );
        $service->delete_mapping($quiz->id, $slots[0]->id, $criteria[1]->id);
        $this->assertCount(2, $service->get_mappings($quiz->id));
        unset($course);
    }

    public function test_cross_course_entities_and_invalid_weight_are_rejected(): void {
        [$course, $quiz, $slots, $criteria] = $this->fixture();
        [$othercourse, $otherquiz, $otherslots, $othercriteria] = $this->fixture('other');
        $service = new quiz_mapping_service();
        foreach (
            [
            fn() => $service->save_mapping($quiz->id, $otherslots[0]->id, $criteria[0]->id, 1),
            fn() => $service->save_mapping($quiz->id, $slots[0]->id, $othercriteria[0]->id, 1),
            fn() => $service->save_mapping($quiz->id, $slots[0]->id, $criteria[0]->id, 0),
            ] as $operation
        ) {
            try {
                $operation();
                $this->fail('Invalid mapping was accepted.');
            } catch (\invalid_parameter_exception $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
        unset($course, $othercourse, $otherquiz);
    }

    /**
     * Create real course/quiz rows and plugin criteria.
     */
    private function fixture(string $suffix = ''): array {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id, 'name' => 'Quiz ' . $suffix]);
        $slots = [];
        for ($number = 1; $number <= 2; $number++) {
            $id = $DB->insert_record('quiz_slots', (object)[
                'quizid' => $quiz->id, 'slot' => $number, 'page' => 1, 'requireprevious' => 0, 'maxmark' => 1,
            ]);
            $slots[] = $DB->get_record('quiz_slots', ['id' => $id]);
        }
        $frameworkid = $DB->insert_record('local_crout_framework', (object)[
            'courseid' => $course->id, 'name' => 'Framework', 'type' => 'fp', 'identitykey' => hash('sha256', $suffix),
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $parentid = $DB->insert_record('local_crout_parent', (object)[
            'frameworkid' => $frameworkid, 'code' => 'RA1', 'name' => 'Parent', 'type' => 'ra', 'sortorder' => 0,
        ]);
        $criteria = [];
        foreach (['a', 'b'] as $sort => $letter) {
            $id = $DB->insert_record('local_crout_criterion', (object)[
                'parentid' => $parentid, 'code' => 'RA1.' . $letter, 'name' => 'Criterion ' . $letter,
                'outcomeid' => 1000 + $course->id + $sort, 'sortorder' => $sort,
            ]);
            $criteria[] = $DB->get_record('local_crout_criterion', ['id' => $id]);
        }
        return [$course, $quiz, $slots, $criteria];
    }
}
