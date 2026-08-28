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

use local_criteriaoutcomes\service\assessment_service;
use local_criteriaoutcomes\service\import_service;
use local_criteriaoutcomes\service\import_undo_service;

/**
 * Conservative import undo tests.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class import_undo_service_test extends \advanced_testcase {
    /**
     * A clean created curriculum can be removed with its empty ancestors.
     */
    public function test_clean_created_items_are_deleted(): void {
        global $DB;
        [$course, $teacher, $student, $scale, $data] = $this->fixture();
        $batch = (new import_service())->import($course->id, $scale->id, $data)['batchid'];
        $summary = (new import_undo_service())->undo($course->id, $batch, false);
        $this->assertSame(3, $summary['deleted']);
        $this->assertEquals(0, $DB->count_records('local_crout_criterion'));
        $this->assertEquals(0, $DB->count_records('grade_outcomes', ['courseid' => $course->id]));
        $this->assertTrue($DB->record_exists('local_crout_importbatch', ['id' => $batch, 'status' => 'undone']));
    }

    /**
     * Used created items survive and may only be archived explicitly.
     */
    public function test_used_created_items_are_archived(): void {
        global $DB;
        [$course, $teacher, $student, $scale, $data] = $this->fixture();
        $batch = (new import_service())->import($course->id, $scale->id, $data)['batchid'];
        $criteria = $DB->get_records_menu('local_crout_criterion', [], '', 'code,id');
        $criterionb = $DB->get_record('local_crout_criterion', ['id' => $criteria['RA1.b']]);
        $item = new \grade_item([
            'courseid' => $course->id, 'itemtype' => 'manual', 'itemname' => 'Use',
            'outcomeid' => $criterionb->outcomeid, 'gradetype' => GRADE_TYPE_SCALE, 'scaleid' => $scale->id,
        ]);
        $item->insert('phpunit');
        (new assessment_service())->save_assessment([
            'courseid' => $course->id, 'criterionid' => $criteria['RA1.c'], 'userid' => $student->id,
            'sourcetype' => constants::SOURCE_DIRECT, 'sourceid' => 0,
            'assessmentmode' => constants::MODE_FEEDBACK_ONLY, 'feedback' => 'Used',
            'graderid' => $teacher->id, 'status' => constants::STATUS_RELEASED,
        ]);
        $summary = (new import_undo_service())->undo($course->id, $batch, true);
        $this->assertSame(1, $summary['deleted']);
        $this->assertSame(2, $summary['archived']);
        $this->assertTrue($DB->record_exists('grade_items', ['id' => $item->id]));
        $this->assertTrue($DB->record_exists('local_crout_assessment', ['criterionid' => $criteria['RA1.c']]));
    }

    /**
     * A matched item predating the batch is never deleted by its undo.
     */
    public function test_matched_item_is_preserved(): void {
        global $DB;
        [$course, $teacher, $student, $scale, $data] = $this->fixture();
        (new import_service())->import($course->id, $scale->id, $data);
        $batch = (new import_service())->import($course->id, $scale->id, $data)['batchid'];
        $summary = (new import_undo_service())->undo($course->id, $batch, false);
        $this->assertSame(3, $summary['matched']);
        $this->assertEquals(3, $DB->count_records('local_crout_criterion'));
    }

    /**
     * Undoing an older update never overwrites a later batch.
     */
    public function test_later_change_conflicts_old_undo(): void {
        global $DB;
        [$course, $teacher, $student, $scale, $data] = $this->fixture();
        $service = new import_service();
        $service->import($course->id, $scale->id, $data);
        $data['parents'][0]['criteria'][0]['name'] = 'Batch two text';
        $batchtwo = $service->import($course->id, $scale->id, $data)['batchid'];
        $data['parents'][0]['criteria'][0]['name'] = 'Batch three text';
        $service->import($course->id, $scale->id, $data);
        $summary = (new import_undo_service())->undo($course->id, $batchtwo, false);
        $this->assertGreaterThanOrEqual(1, $summary['conflicted']);
        $criterion = $DB->get_record('local_crout_criterion', ['code' => 'RA1.a']);
        $this->assertSame('Batch three text', $criterion->name);
    }

    /**
     * Create a three-criterion course curriculum.
     */
    private function fixture(): array {
        global $CFG;
        $this->resetAfterTest();
        $CFG->enableoutcomes = 1;
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($teacher);
        $scale = $this->getDataGenerator()->create_scale(['courseid' => $course->id, 'scale' => 'No,Yes']);
        $data = (new provider\json_provider())->parse(json_encode([
            'metadata' => ['name' => 'Undo', 'type' => 'fp'],
            'resultados' => [[
                'codigo' => 'RA1', 'nombre' => 'Result', 'criterios' => [
                    ['codigo' => 'RA1.a', 'nombre' => 'A'],
                    ['codigo' => 'RA1.b', 'nombre' => 'B'],
                    ['codigo' => 'RA1.c', 'nombre' => 'C'],
                ],
            ]],
        ]));
        return [$course, $teacher, $student, $scale, $data];
    }
}
