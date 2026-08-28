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
use local_criteriaoutcomes\service\deletion_safety_service;
use local_criteriaoutcomes\service\import_service;

/**
 * Destructive curriculum operations remain conservative.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class deletion_safety_service_test extends \advanced_testcase {
    /**
     * Unused is deletable; gradebook and assessment use force archive.
     */
    public function test_bulk_analysis_and_safe_apply(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $CFG->enableoutcomes = 1;
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($teacher);
        $scale = $this->getDataGenerator()->create_scale(['courseid' => $course->id, 'scale' => 'No,Yes']);
        $data = (new provider\json_provider())->parse(json_encode([
            'metadata' => ['name' => 'Delete safety', 'type' => 'fp'],
            'resultados' => [[
                'codigo' => 'RA1', 'nombre' => 'Result', 'criterios' => [
                    ['codigo' => 'RA1.a', 'nombre' => 'Unused'],
                    ['codigo' => 'RA1.b', 'nombre' => 'Grade item'],
                    ['codigo' => 'RA1.c', 'nombre' => 'Assessment'],
                ],
            ]],
        ]));
        (new import_service())->import($course->id, $scale->id, $data);
        $criteria = $DB->get_records_menu('local_crout_criterion', [], '', 'code,id');
        $b = $DB->get_record('local_crout_criterion', ['id' => $criteria['RA1.b']]);
        $item = new \grade_item([
            'courseid' => $course->id, 'itemtype' => 'manual', 'itemname' => 'Use',
            'outcomeid' => $b->outcomeid, 'gradetype' => GRADE_TYPE_SCALE, 'scaleid' => $scale->id,
        ]);
        $item->insert('phpunit');
        (new assessment_service())->save_assessment([
            'courseid' => $course->id, 'criterionid' => $criteria['RA1.c'], 'userid' => $student->id,
            'sourcetype' => constants::SOURCE_DIRECT, 'sourceid' => 0,
            'assessmentmode' => constants::MODE_FEEDBACK_ONLY, 'feedback' => 'Preserve me',
            'graderid' => $teacher->id, 'status' => constants::STATUS_RELEASED,
        ]);

        $service = new deletion_safety_service();
        $analysis = $service->analyze_many($course->id, array_values($criteria));
        $this->assertSame(deletion_safety_service::SAFE_DELETE, $analysis[$criteria['RA1.a']]['policy']);
        $this->assertSame(deletion_safety_service::ARCHIVE_ONLY, $analysis[$criteria['RA1.b']]['policy']);
        $this->assertSame(deletion_safety_service::ARCHIVE_ONLY, $analysis[$criteria['RA1.c']]['policy']);
        $summary = $service->apply($course->id, array_values($criteria), true);
        $this->assertSame(['deleted' => 1, 'archived' => 2, 'blocked' => 0], $summary);
        $this->assertFalse($DB->record_exists('local_crout_criterion', ['id' => $criteria['RA1.a']]));
        $this->assertEquals(1, $DB->get_field('local_crout_criterion', 'archived', ['id' => $criteria['RA1.b']]));
        $this->assertTrue($DB->record_exists('grade_items', ['id' => $item->id]));
        $this->assertTrue($DB->record_exists('local_crout_assessment', ['criterionid' => $criteria['RA1.c']]));
    }

    /**
     * An Outcome without a plugin-owned mapping is always blocked.
     */
    public function test_external_outcome_is_blocked(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $CFG->enableoutcomes = 1;
        $course = $this->getDataGenerator()->create_course();
        $scale = $this->getDataGenerator()->create_scale(['courseid' => $course->id, 'scale' => 'No,Yes']);
        $outcome = new \grade_outcome();
        $outcome->courseid = $course->id;
        $outcome->shortname = 'RA1.a';
        $outcome->fullname = 'External';
        $outcome->scaleid = $scale->id;
        $outcome->insert('phpunit');

        $analysis = (new deletion_safety_service())->analyze($course->id, 999999);
        $this->assertSame(deletion_safety_service::BLOCKED, $analysis['policy']);
        $this->assertTrue($DB->record_exists('grade_outcomes', ['id' => $outcome->id]));
    }
}
