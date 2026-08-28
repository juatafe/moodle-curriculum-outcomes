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
use local_criteriaoutcomes\service\quiz_evidence_service;
use local_criteriaoutcomes\service\quiz_mapping_service;

/**
 * Real Moodle Question Engine evidence tests.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class quiz_evidence_service_test extends \advanced_testcase {
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
    }

    public function test_real_attempt_produces_per_criterion_evidence_without_changing_quiz_grade(): void {
        global $DB;
        $this->resetAfterTest();
        [$quiz, $user, $slots, $criteria] = $this->quiz_fixture(['truefalse', 'truefalse', 'truefalse', 'truefalse']);
        $mapping = new quiz_mapping_service();
        $mapping->save_mapping($quiz->id, $slots[0]->id, $criteria[0]->id, 1);
        $mapping->save_mapping($quiz->id, $slots[1]->id, $criteria[0]->id, 2);
        $mapping->save_mapping($quiz->id, $slots[3]->id, $criteria[0]->id, 1);
        $mapping->save_mapping($quiz->id, $slots[2]->id, $criteria[1]->id, 1);
        $mapping->save_mapping($quiz->id, $slots[3]->id, $criteria[2]->id, 1);
        $mapping->save_configuration(
            $quiz->id,
            $criteria[0]->id,
            criterion_aggregation_service::WEIGHTED_MEAN
        );
        $this->setUser($user);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $attempt = $generator->create_attempt($quiz->id, $user->id);
        $generator->submit_responses(
            $attempt->id,
            [1 => 'True', 2 => 'False', 3 => 'True', 4 => 'True'],
            false,
            true
        );
        $evidence = (new quiz_evidence_service())->for_attempt($attempt->id);
        $bycode = array_column($evidence['criteria'], null, 'code');
        $this->assertEqualsWithDelta(0.5, $bycode['RA1.a']['result']['value'], 0.0000001);
        $this->assertEqualsWithDelta(1.0, $bycode['RA1.b']['result']['value'], 0.0000001);
        $this->assertEqualsWithDelta(1.0, $bycode['RA1.c']['result']['value'], 0.0000001);
        $this->assertCount(3, $bycode['RA1.a']['questions']);
        $quizgrade = $DB->get_field('quiz_attempts', 'sumgrades', ['id' => $attempt->id]);
        $this->assertEquals(3.0, $quizgrade);
        $this->assertFalse($DB->record_exists('grade_grades', ['rawgrade' => 0.5]));
        $this->assertSame(0, $DB->count_records('local_crout_judgement', [
            'criterionid' => $criteria[0]->id,
            'userid' => $user->id,
        ]));

        $secondattempt = $generator->create_attempt($quiz->id, $user->id);
        $generator->submit_responses(
            $secondattempt->id,
            [1 => 'True', 2 => 'True', 3 => 'True', 4 => 'True'],
            false,
            true
        );
        $this->assertEquals(2, $secondattempt->attempt);
        $this->assertEqualsWithDelta(
            1.0,
            (new quiz_evidence_service())->for_attempt($secondattempt->id)['criteria'][0]['result']['value'],
            0.0000001
        );
    }

    public function test_essay_is_pending_until_manual_grading(): void {
        $this->resetAfterTest();
        [$quiz, $user, $slots, $criteria] = $this->quiz_fixture(['essay']);
        (new quiz_mapping_service())->save_mapping($quiz->id, $slots[0]->id, $criteria[0]->id, 1);
        $this->setUser($user);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $attempt = $generator->create_attempt($quiz->id, $user->id);
        $generator->submit_responses($attempt->id, [1 => 'An essay response'], false, true);
        $service = new quiz_evidence_service();
        $this->assertSame('pending', $service->for_attempt($attempt->id)['criteria'][0]['result']['status']);
        $quba = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);
        $quba->manual_grade(1, 'Manually assessed', 0.8, FORMAT_HTML);
        \question_engine::save_questions_usage_by_activity($quba);
        $result = $service->for_attempt($attempt->id)['criteria'][0]['result'];
        $this->assertSame('complete', $result['status']);
        $this->assertEqualsWithDelta(0.8, $result['value'], 0.0000001);
    }

    public function test_random_slot_uses_selected_question_as_slot_evidence(): void {
        global $DB;
        $this->resetAfterTest();
        [$quiz, $user, $unusedslots, $criteria] = $this->quiz_fixture([]);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('truefalse', null, ['category' => $category->id]);
        $filtercondition = [
            'filter' => [
                'category' => [
                    'jointype' => \core_question\local\bank\condition::JOINTYPE_DEFAULT,
                    'values' => [$category->id],
                    'filteroptions' => ['includesubcategories' => false],
                ],
            ],
        ];
        $this->setAdminUser();
        $quizsettings = \mod_quiz\quiz_settings::create($quiz->id);
        $quizsettings->get_structure()->add_random_questions(0, 1, $filtercondition);
        $slot = $DB->get_record('quiz_slots', ['quizid' => $quiz->id], '*', MUST_EXIST);
        $quizsettings->get_structure()->update_slot_maxmark($slot, 1.0);
        $quizsettings->get_grade_calculator()->recompute_quiz_sumgrades();
        $this->assertTrue((new quiz_mapping_service())->get_slots($quiz->id)[$slot->id]->random);
        (new quiz_mapping_service())->save_mapping($quiz->id, $slot->id, $criteria[0]->id, 1);
        $this->setUser($user);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $attempt = $generator->create_attempt($quiz->id, $user->id, [1 => $question->id]);
        $generator->submit_responses($attempt->id, [1 => 'True'], false, true);
        $evidence = (new quiz_evidence_service())->for_attempt($attempt->id);
        $this->assertEqualsWithDelta(1.0, $evidence['criteria'][0]['result']['value'], 0.0000001);
        $this->assertSame((int)$question->id, $evidence['criteria'][0]['questions'][0]['questionid']);
        unset($unusedslots);
    }

    public function test_mapping_survives_always_latest_question_version_change(): void {
        global $DB;
        $this->resetAfterTest();
        [$quiz, $user, $slots, $criteria, $questions] = $this->quiz_fixture(['truefalse']);
        $mapping = new quiz_mapping_service();
        $mappingid = $mapping->save_mapping($quiz->id, $slots[0]->id, $criteria[0]->id, 1);
        $updated = $this->getDataGenerator()->get_plugin_generator('core_question')->update_question(
            $questions[0],
            null,
            ['name' => 'Version two']
        );
        $this->assertNotSame((int)$questions[0]->id, (int)$updated->id);
        $this->assertSame($mappingid, (int)$DB->get_field('local_crout_quizmap', 'id', [
            'quizid' => $quiz->id, 'slotid' => $slots[0]->id, 'criterionid' => $criteria[0]->id,
        ]));
        $this->assertSame(
            (int)$slots[0]->id,
            (int)array_key_first(\mod_quiz\quiz_settings::create($quiz->id)->get_structure()->get_slots())
        );
        unset($user);
    }

    /**
     * Create a quiz with real version-aware question references and plugin criteria.
     */
    private function quiz_fixture(array $types): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance([
            'course' => $course->id, 'questionsperpage' => 0, 'grade' => 100.0, 'sumgrades' => count($types),
        ]);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $questions = [];
        foreach ($types as $type) {
            $question = $questiongenerator->create_question($type, null, ['category' => $category->id]);
            quiz_add_quiz_question($question->id, $quiz);
            $questions[] = $question;
        }
        $slots = array_values($DB->get_records('quiz_slots', ['quizid' => $quiz->id], 'slot'));
        $frameworkid = $DB->insert_record('local_crout_framework', (object)[
            'courseid' => $course->id, 'name' => 'Framework', 'type' => 'fp',
            'identitykey' => hash('sha256', 'evidence-' . $course->id),
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $parentid = $DB->insert_record('local_crout_parent', (object)[
            'frameworkid' => $frameworkid, 'code' => 'RA1', 'name' => 'Result', 'type' => 'ra', 'sortorder' => 0,
        ]);
        $criteria = [];
        foreach (['a', 'b', 'c'] as $sort => $letter) {
            $id = $DB->insert_record('local_crout_criterion', (object)[
                'parentid' => $parentid, 'code' => 'RA1.' . $letter, 'name' => 'Criterion ' . $letter,
                'outcomeid' => 5000 + $course->id + $sort, 'sortorder' => $sort,
            ]);
            $criteria[] = $DB->get_record('local_crout_criterion', ['id' => $id]);
        }
        return [$quiz, $user, $slots, $criteria, $questions];
    }
}
