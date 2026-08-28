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

use local_criteriaoutcomes\provider\json_provider;
use local_criteriaoutcomes\service\import_service;
use local_criteriaoutcomes\service\rubric_mapping_service;
use local_criteriaoutcomes\service\student_progress_service;

/**
 * Integration with real Moodle Advanced Grading rubrics.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rubric_integration_test extends \advanced_testcase {
    /**
     * Native rubric dimensions remain separate evidence and retain level remarks.
     */
    public function test_native_assignment_rubric_dimension_evidence(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $CFG->enableoutcomes = 1;
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assignment = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $scale = $this->getDataGenerator()->create_scale(['courseid' => $course->id, 'scale' => 'Initial,Developing,Acquired']);
        $curriculum = (new json_provider())->parse(json_encode([
            'metadata' => ['name' => 'Rubric curriculum', 'type' => 'fp'],
            'resultados' => [[
                'codigo' => 'RA1', 'nombre' => 'Result', 'criterios' => [
                    ['codigo' => 'RA1.a', 'nombre' => 'Network configuration'],
                    ['codigo' => 'RA1.b', 'nombre' => 'Documentation'],
                    ['codigo' => 'RA1.c', 'nombre' => 'Verification'],
                ],
            ]],
        ]));
        $this->setAdminUser();
        (new import_service())->import($course->id, $scale->id, $curriculum);
        $criteria = $DB->get_records_menu('local_crout_criterion', [], '', 'code,id');

        $this->setUser($teacher);
        $rubricgenerator = $this->getDataGenerator()->get_plugin_generator('gradingform_rubric');
        $controller = $rubricgenerator->create_instance(
            \context_module::instance($assignment->cmid),
            'mod_assign',
            'submissions',
            'Network rubric',
            'Native Moodle rubric',
            [
                'Network configuration' => ['Initial' => 0, 'Developing' => 1, 'Acquired' => 2],
                'Documentation' => ['Initial' => 0, 'Developing' => 1, 'Acquired' => 2],
            ]
        );
        $definition = $controller->get_definition();
        $dimensionids = array_keys($definition->rubric_criteria);
        $mapping = new rubric_mapping_service();
        $mapping->save_mapping($course->id, $dimensionids[0], $criteria['RA1.a']);
        $mapping->save_mapping($course->id, $dimensionids[1], $criteria['RA1.b']);
        $mapping->save_mapping($course->id, $dimensionids[0], $criteria['RA1.c']);
        $mapping->save_mapping($course->id, $dimensionids[1], $criteria['RA1.a']);

        $gradeid = $DB->insert_record('assign_grades', (object)[
            'assignment' => $assignment->id,
            'userid' => $student->id,
            'timecreated' => time(),
            'timemodified' => time(),
            'grader' => $teacher->id,
            'grade' => 75,
            'attemptnumber' => 0,
        ]);
        $instance = $controller->create_instance($teacher->id, $gradeid);
        $instance->update($rubricgenerator->get_submitted_form_data($controller, $gradeid, [
            'Network configuration' => ['score' => 2, 'remark' => 'Network is correct.'],
            'Documentation' => ['score' => 1, 'remark' => 'Add DNS notes.'],
        ]));

        $service = new student_progress_service();
        $ra1a = array_values(array_filter(
            $service->for_student_criterion($course->id, $criteria['RA1.a'], $student->id)['evidence'],
            fn($item) => $item['type'] === 'rubric'
        ));
        $this->assertCount(2, $ra1a);
        $this->assertSame(
            ['Network configuration', 'Documentation'],
            array_map('strip_tags', array_column($ra1a, 'dimension'))
        );
        $this->assertSame(['Acquired', 'Developing'], array_map('strip_tags', array_column($ra1a, 'level')));
        $this->assertSame([2.0, 1.0], array_map('floatval', array_column($ra1a, 'score')));
        $this->assertSame(
            ['Network is correct.', 'Add DNS notes.'],
            array_map('strip_tags', array_column($ra1a, 'remark'))
        );

        $ra1b = $service->for_student_criterion($course->id, $criteria['RA1.b'], $student->id)['evidence'];
        $this->assertCount(1, array_filter($ra1b, fn($item) => $item['type'] === 'rubric'));
        $ra1c = $service->for_student_criterion($course->id, $criteria['RA1.c'], $student->id)['evidence'];
        $this->assertCount(1, array_filter($ra1c, fn($item) => $item['type'] === 'rubric'));
        $this->assertFalse($DB->record_exists('local_crout_assessment', ['userid' => $student->id]));
    }
}
