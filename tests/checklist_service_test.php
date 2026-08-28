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

/**
 * Checklist service tests.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_criteriaoutcomes;

use local_criteriaoutcomes\constants;
use local_criteriaoutcomes\service\checklist_service;

/**
 * Tests for checklist definitions, items, mappings, and responses.
 */
final class checklist_service_test extends \advanced_testcase {
    /**
     * Create a checklist with items and criterion mappings.
     */
    public function test_create_checklist_with_items_and_mappings(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $criterionid = $this->create_criterion($course->id);

        $service = new checklist_service();
        $defid = $service->create_definition($course->id, 'Configuración Ubuntu', constants::CHECKLIST_BINARY);
        $this->assertGreaterThan(0, $defid);

        $item1 = $service->create_item($defid, 'Configura IP', 1);
        $item2 = $service->create_item($defid, 'Configura gateway', 2);
        $item3 = $service->create_item($defid, 'Configura DNS', 3, 2.0);

        $service->map_item($item1, $criterionid);
        $service->map_item($item2, $criterionid);

        $def = $service->get_definition_with_items($defid);
        $this->assertSame('Configuración Ubuntu', $def['name']);
        $this->assertCount(3, $def['items']);

        // Check mappings.
        $this->assertCount(1, $def['items'][0]->mappings);
    }

    /**
     * Binary mode only allows done/not_done.
     */
    public function test_binary_mode_rejects_partial(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $service = new checklist_service();
        $defid = $service->create_definition($course->id, 'Checklist', constants::CHECKLIST_BINARY);
        $itemid = $service->create_item($defid, 'Item 1');

        $this->expectException(\invalid_parameter_exception::class);
        $service->save_response($defid, $itemid, $student->id, constants::CHECKLIST_PARTIAL, null, $teacher->id);
    }

    /**
     * Three-state mode allows not_done, partial, done.
     */
    public function test_three_state_allows_partial(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $service = new checklist_service();
        $defid = $service->create_definition($course->id, 'Checklist', constants::CHECKLIST_THREE_STATE);
        $itemid = $service->create_item($defid, 'Item 1');

        $responseid = $service->save_response(
            $defid,
            $itemid,
            $student->id,
            constants::CHECKLIST_PARTIAL,
            'Half done',
            $teacher->id
        );
        $this->assertGreaterThan(0, $responseid);

        $record = $DB->get_record('local_crout_checklist_resp', ['id' => $responseid]);
        $this->assertSame(constants::CHECKLIST_PARTIAL, $record->state);
        $this->assertSame('Half done', $record->feedback);
    }

    /**
     * Responses are idempotent (update on duplicate).
     */
    public function test_response_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $service = new checklist_service();
        $defid = $service->create_definition($course->id, 'Checklist', constants::CHECKLIST_BINARY);
        $itemid = $service->create_item($defid, 'Item 1');

        $id1 = $service->save_response($defid, $itemid, $student->id, constants::CHECKLIST_DONE, null, $teacher->id);
        $id2 = $service->save_response($defid, $itemid, $student->id, constants::CHECKLIST_NOT_DONE, 'Changed', $teacher->id);

        $this->assertSame($id1, $id2);
        $record = $DB->get_record('local_crout_checklist_resp', ['id' => $id1]);
        $this->assertSame(constants::CHECKLIST_NOT_DONE, $record->state);
    }

    /**
     * One item can map to multiple criteria.
     */
    public function test_item_to_multiple_criteria(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $criterion1 = $this->create_criterion($course->id, 'RA1.a');
        $criterion2 = $this->create_criterion($course->id, 'RA1.c');

        $service = new checklist_service();
        $defid = $service->create_definition($course->id, 'Checklist', constants::CHECKLIST_BINARY);
        $itemid = $service->create_item($defid, 'Comprueba y justifica');

        $service->map_item($itemid, $criterion1);
        $service->map_item($itemid, $criterion2);

        $def = $service->get_definition_with_items($defid);
        $this->assertCount(2, $def['items'][0]->mappings);
    }

    /**
     * Duplicate mapping is idempotent.
     */
    public function test_duplicate_mapping_is_idempotent(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $criterion = $this->create_criterion($course->id);

        $service = new checklist_service();
        $defid = $service->create_definition($course->id, 'Checklist', constants::CHECKLIST_BINARY);
        $itemid = $service->create_item($defid, 'Item');

        $id1 = $service->map_item($itemid, $criterion);
        $id2 = $service->map_item($itemid, $criterion);
        $this->assertSame($id1, $id2);
    }

    /**
     * Delete definition cascades to items, mappings, and responses.
     */
    public function test_delete_definition_cascades(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $criterion = $this->create_criterion($course->id);

        $service = new checklist_service();
        $defid = $service->create_definition($course->id, 'Checklist', constants::CHECKLIST_BINARY);
        $itemid = $service->create_item($defid, 'Item');
        $service->map_item($itemid, $criterion);
        $service->save_response($defid, $itemid, $student->id, constants::CHECKLIST_DONE, null, $teacher->id);

        $service->delete_definition($defid);

        $this->assertFalse($DB->record_exists('local_crout_checklist_def', ['id' => $defid]));
        $this->assertFalse($DB->record_exists('local_crout_checklist_item', ['id' => $itemid]));
        $this->assertFalse($DB->record_exists('local_crout_checklist_map', ['itemid' => $itemid]));
        $this->assertFalse($DB->record_exists('local_crout_checklist_resp', ['definitionid' => $defid]));
    }

    /**
     * Get responses for user returns correct data.
     */
    public function test_get_responses_for_user(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $service = new checklist_service();
        $defid = $service->create_definition($course->id, 'Checklist', constants::CHECKLIST_BINARY);
        $item1 = $service->create_item($defid, 'Item 1');
        $item2 = $service->create_item($defid, 'Item 2');

        $service->save_response($defid, $item1, $student->id, constants::CHECKLIST_DONE, null, $teacher->id);
        $service->save_response($defid, $item2, $student->id, constants::CHECKLIST_NOT_DONE, null, $teacher->id);

        $responses = $service->get_responses_for_user($defid, $student->id);
        $this->assertCount(2, $responses);
    }

    /**
     * Create a minimal curriculum criterion for testing.
     */
    private function create_criterion(int $courseid, string $code = 'RA1.a'): int {
        global $DB;
        $frameworkid = $DB->insert_record('local_crout_framework', (object)[
            'courseid' => $courseid,
            'name' => 'Framework',
            'type' => 'fp',
            'identitykey' => hash('sha256', 'checklist-test-' . $courseid . $code),
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $parentid = $DB->insert_record('local_crout_parent', (object)[
            'frameworkid' => $frameworkid,
            'code' => 'RA1',
            'name' => 'Parent',
            'type' => 'ra',
            'sortorder' => 0,
        ]);
        return $DB->insert_record('local_crout_criterion', (object)[
            'parentid' => $parentid,
            'code' => $code,
            'name' => 'Criterion ' . $code,
            'outcomeid' => 2000 + $courseid + crc32($code),
            'sortorder' => 0,
        ]);
    }
}
