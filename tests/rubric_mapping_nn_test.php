<?php
// phpcs:ignoreFile
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

use local_criteriaoutcomes\service\rubric_mapping_service;

/**
 * Rubric N:N service tests.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rubric_mapping_nn_test extends \advanced_testcase {
    private function create_course_with_rubric_and_criteria(): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $assignment = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $scale = $this->getDataGenerator()->create_scale(['courseid' => $course->id, 'scale' => 'A,B,C']);
        $curriculum = (new \local_criteriaoutcomes\provider\json_provider())->parse(json_encode([
            'metadata' => ['name' => 'Test', 'type' => 'fp'],
            'resultados' => [[
                'codigo' => 'RA1', 'nombre' => 'Parent',
                'criterios' => [
                    ['codigo' => 'RA1.a', 'nombre' => 'Criterion 1'],
                    ['codigo' => 'RA1.b', 'nombre' => 'Criterion 2'],
                    ['codigo' => 'RA1.c', 'nombre' => 'Criterion 3'],
                ],
            ]],
        ]));
        (new \local_criteriaoutcomes\service\import_service())->import($course->id, $scale->id, $curriculum);
        $criteria = $DB->get_records_menu('local_crout_criterion', [], '', 'code,id');
        $this->setUser($teacher);
        $gen = $this->getDataGenerator()->get_plugin_generator('gradingform_rubric');
        $controller = $gen->create_instance(
            \context_module::instance($assignment->cmid),
            'mod_assign',
            'submissions',
            'Rubric NN',
            'desc',
            [
                'Dim A' => ['Level 1' => 0, 'Level 2' => 1],
                'Dim B' => ['Level 1' => 0, 'Level 2' => 1],
            ]
        );
        $def = $controller->get_definition();
        $dimids = array_keys($def->rubric_criteria);
        return [$course, $assignment, $criteria, $dimids, $teacher];
    }

    public function test_one_to_many(): void {
        $this->resetAfterTest();
        [$course, $assignment, $criteria, $dimids] = $this->create_course_with_rubric_and_criteria();
        $svc = new rubric_mapping_service();
        $svc->replace_mappings_for_rubric_criterion($course->id, $dimids[0], [$criteria['RA1.a'], $criteria['RA1.b'], $criteria['RA1.c']]);
        $maps = $svc->get_mappings_for_rubric_criteria([$dimids[0]]);
        $this->assertCount(3, $maps);
    }

    public function test_many_to_one(): void {
        $this->resetAfterTest();
        [$course, $assignment, $criteria, $dimids] = $this->create_course_with_rubric_and_criteria();
        $svc = new rubric_mapping_service();
        $svc->replace_mappings_for_rubric_criterion($course->id, $dimids[0], [$criteria['RA1.b']]);
        $svc->replace_mappings_for_rubric_criterion($course->id, $dimids[1], [$criteria['RA1.b']]);
        $this->assertCount(1, $svc->get_mappings_for_rubric_criteria([$dimids[0]]));
        $this->assertCount(1, $svc->get_mappings_for_rubric_criteria([$dimids[1]]));
        $this->assertCount(2, $svc->get_mappings_for_criterion($criteria['RA1.b']));
    }

    public function test_replace(): void {
        $this->resetAfterTest();
        [$course, $assignment, $criteria, $dimids] = $this->create_course_with_rubric_and_criteria();
        $svc = new rubric_mapping_service();
        $svc->replace_mappings_for_rubric_criterion($course->id, $dimids[0], [$criteria['RA1.a'], $criteria['RA1.b']]);
        $svc->replace_mappings_for_rubric_criterion($course->id, $dimids[1], [$criteria['RA1.b']]);
        $svc->replace_mappings_for_rubric_criterion($course->id, $dimids[0], [$criteria['RA1.b'], $criteria['RA1.c']]);
        $a = $svc->get_mappings_for_rubric_criteria([$dimids[0]]);
        $b = $svc->get_mappings_for_rubric_criteria([$dimids[1]]);
        $this->assertCount(2, $a);
        $this->assertCount(1, $b);
        $aids = array_map(fn($r) => (int)$r->curriculumcriterionid, $a);
        sort($aids);
        $this->assertSame([(int)$criteria['RA1.b'], (int)$criteria['RA1.c']], $aids);
    }

    public function test_idempotency(): void {
        $this->resetAfterTest();
        [$course, $assignment, $criteria, $dimids] = $this->create_course_with_rubric_and_criteria();
        $svc = new rubric_mapping_service();
        $svc->replace_mappings_for_rubric_criterion($course->id, $dimids[0], [$criteria['RA1.a'], $criteria['RA1.b']]);
        $svc->replace_mappings_for_rubric_criterion($course->id, $dimids[0], [$criteria['RA1.a'], $criteria['RA1.b']]);
        $this->assertCount(2, $svc->get_mappings_for_rubric_criteria([$dimids[0]]));
    }

    public function test_empty(): void {
        $this->resetAfterTest();
        [$course, $assignment, $criteria, $dimids] = $this->create_course_with_rubric_and_criteria();
        $svc = new rubric_mapping_service();
        $svc->replace_mappings_for_rubric_criterion($course->id, $dimids[0], [$criteria['RA1.a']]);
        $svc->replace_mappings_for_rubric_criterion($course->id, $dimids[0], []);
        $this->assertCount(0, $svc->get_mappings_for_rubric_criteria([$dimids[0]]));
    }

    public function test_reject_archived(): void {
        $this->resetAfterTest();
        global $DB;
        [$course, $assignment, $criteria, $dimids] = $this->create_course_with_rubric_and_criteria();
        $DB->set_field('local_crout_criterion', 'archived', 1, ['id' => $criteria['RA1.a']]);
        $svc = new rubric_mapping_service();
        $this->expectException(\invalid_parameter_exception::class);
        $svc->replace_mappings_for_rubric_criterion($course->id, $dimids[0], [$criteria['RA1.a']]);
    }

    public function test_reject_cross_course(): void {
        $this->resetAfterTest();
        [$course, $assignment, $criteria, $dimids] = $this->create_course_with_rubric_and_criteria();
        $othercourse = $this->getDataGenerator()->create_course();
        $this->setAdminUser();
        $scale = $this->getDataGenerator()->create_scale(['courseid' => $othercourse->id, 'scale' => 'A,B,C']);
        $curriculum = (new \local_criteriaoutcomes\provider\json_provider())->parse(json_encode([
            'metadata' => ['name' => 'Other', 'type' => 'fp'],
            'resultados' => [[
                'codigo' => 'RA9', 'nombre' => 'Parent',
                'criterios' => [['codigo' => 'RA9.a', 'nombre' => 'Other criterion']],
            ]],
        ]));
        (new \local_criteriaoutcomes\service\import_service())->import($othercourse->id, $scale->id, $curriculum);
        global $DB;
        $otherid = $DB->get_field('local_crout_criterion', 'id', ['code' => 'RA9.a']);
        $svc = new rubric_mapping_service();
        $this->expectException(\invalid_parameter_exception::class);
        $svc->replace_mappings_for_rubric_criterion($course->id, $dimids[0], [$otherid]);
    }

    public function test_reject_rubric_from_other_course(): void {
        $this->resetAfterTest();
        [$course, $assignment, $criteria, $dimids] = $this->create_course_with_rubric_and_criteria();
        $othercourse = $this->getDataGenerator()->create_course();
        $otherassign = $this->getDataGenerator()->create_module('assign', ['course' => $othercourse->id]);
        $teacher = $this->getDataGenerator()->create_and_enrol($othercourse, 'editingteacher');
        $this->setUser($teacher);
        $gen = $this->getDataGenerator()->get_plugin_generator('gradingform_rubric');
        $ctrl = $gen->create_instance(
            \context_module::instance($otherassign->cmid),
            'mod_assign',
            'submissions',
            'Other Rubric',
            'desc',
            ['Other Dim' => ['L1' => 0, 'L2' => 1]]
        );
        $otherdef = $ctrl->get_definition();
        $otherdim = array_key_first($otherdef->rubric_criteria);
        $svc = new rubric_mapping_service();
        $this->expectException(\invalid_parameter_exception::class);
        $svc->replace_mappings_for_rubric_criterion($course->id, $otherdim, [$criteria['RA1.a']]);
    }
}
