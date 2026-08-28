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

use local_criteriaoutcomes\service\scale_template_service;

/**
 * Recommended course scale tests.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scale_template_service_test extends \advanced_testcase {
    /**
     * Numeric creation is course-local and idempotent.
     */
    public function test_numeric_template_is_local_and_idempotent(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->libdir . '/grade/grade_category.php');
        require_once($CFG->libdir . '/grade/grade_item.php');
        $course = $this->getDataGenerator()->create_course();
        $service = new scale_template_service();
        $first = $service->create($course->id, scale_template_service::NUMERIC);
        $this->assertSame($first, $service->existing_id($course->id, scale_template_service::NUMERIC));
        $this->assertSame(range(0, 10), array_map('intval', $service->levels(scale_template_service::NUMERIC)));
        $item = new \grade_item((object)[
            'courseid' => $course->id,
            'itemtype' => 'manual',
            'itemname' => 'Scale usage',
            'gradetype' => GRADE_TYPE_SCALE,
            'scaleid' => $first,
        ]);
        $item->insert('test');
        $second = $service->create($course->id, scale_template_service::NUMERIC);
        $this->assertSame($first, $second);
        $scale = $DB->get_record('scale', ['id' => $first], '*', MUST_EXIST);
        $this->assertSame((int)$course->id, (int)$scale->courseid);
        $this->assertSame('0,1,2,3,4,5,6,7,8,9,10', $scale->scale);
        $this->assertSame(1, $DB->count_records('scale', ['courseid' => $course->id]));
        $this->assertTrue($DB->record_exists('grade_items', ['id' => $item->id, 'scaleid' => $first]));
    }

    /**
     * A same-name external scale is neither adopted nor modified.
     */
    public function test_external_name_collision_is_preserved(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->libdir . '/grade/grade_scale.php');
        $course = $this->getDataGenerator()->create_course();
        $this->assertSame(0, (new scale_template_service())->existing_id(
            $course->id,
            scale_template_service::ACHIEVEMENT
        ));
        $external = new \grade_scale();
        $external->courseid = $course->id;
        $external->userid = get_admin()->id;
        $external->name = get_string('scaletemplatenumericname', 'local_criteriaoutcomes');
        $external->scale = 'Low,High';
        $external->description = 'External scale';
        $external->descriptionformat = FORMAT_PLAIN;
        $externalid = $external->insert('test');

        $ownedid = (new scale_template_service())->create($course->id, scale_template_service::NUMERIC);
        $this->assertNotSame((int)$externalid, $ownedid);
        $this->assertSame('Low,High', $DB->get_field('scale', 'scale', ['id' => $externalid]));
        $this->assertSame(2, $DB->count_records('scale', ['courseid' => $course->id]));
    }

    /**
     * Descriptive values are persisted in the active language at creation time.
     */
    public function test_achievement_template_uses_active_language(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        force_current_language('en');
        $id = (new scale_template_service())->create($course->id, scale_template_service::ACHIEVEMENT);
        $this->assertSame(
            'Insufficient,Sufficient,Good,Very good,Excellent',
            $DB->get_field('scale', 'scale', ['id' => $id])
        );
    }
}
