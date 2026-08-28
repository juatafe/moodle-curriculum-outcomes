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

use local_criteriaoutcomes\service\import_service;

/**
 * Source identity, diff, idempotency and transaction behavior.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class import_lifecycle_test extends \advanced_testcase {
    /**
     * Same source reimport matches without duplicate entities.
     */
    public function test_provider_idempotency_and_matched_batch(): void {
        global $DB;
        [$course, $scale, $data] = $this->fixture('BOE-A-2022-1', '0371', ['RA1.a' => 'A', 'RA1.b' => 'B']);
        $service = new import_service();
        $first = $service->import($course->id, $scale->id, $data);
        $second = $service->import($course->id, $scale->id, $data);
        $this->assertSame(2, $first['new']);
        $this->assertSame(2, $second['existing']);
        $this->assertEquals(2, $DB->count_records('local_crout_criterion'));
        $this->assertEquals(2, $DB->count_records('local_crout_importitem', [
            'batchid' => $second['batchid'], 'action' => 'matched',
        ]));
    }

    /**
     * Identical short codes in distinct source curricula remain distinct.
     */
    public function test_same_code_in_distinct_curricula(): void {
        global $DB;
        [$course, $scale, $first] = $this->fixture('BOE-A-2022-1', '0371', ['RA1.a' => 'Module one']);
        $second = $this->data('BOE-A-2022-1', '0372', ['RA1.a' => 'Module two']);
        $service = new import_service();
        $service->import($course->id, $scale->id, $first);
        $service->import($course->id, $scale->id, $second);
        $this->assertEquals(2, $DB->count_records('local_crout_criterion', ['code' => 'RA1.a']));
        $this->assertEquals(2, $DB->count_records('grade_outcomes', ['courseid' => $course->id]));
        $this->assertTrue($DB->record_exists('grade_outcomes', [
            'courseid' => $course->id, 'shortname' => '0372 · RA1.a',
        ]));
        $this->assertEquals(2, $DB->count_records('local_crout_framework'));
    }

    /**
     * Text and removal are preview-only until a confirmed import/archive.
     */
    public function test_diff_and_removed_from_source_do_not_apply(): void {
        global $DB;
        [$course, $scale, $vone] = $this->fixture('BOE-A-2022-1', '0371', [
            'RA1.a' => 'Text A', 'RA1.b' => 'Text B',
        ]);
        $service = new import_service();
        $service->import($course->id, $scale->id, $vone);
        $vtwo = $this->data('BOE-A-2022-1', '0371', ['RA1.a' => 'Text changed', 'RA1.c' => 'New']);
        $preview = $service->preview($course->id, $vtwo, $scale->id);
        $this->assertSame(import_service::STATUS_TEXT_CHANGED, $preview['parents'][0]['criteria'][0]['status']);
        $this->assertSame(import_service::STATUS_NEW, $preview['parents'][0]['criteria'][1]['status']);
        $this->assertSame(import_service::STATUS_REMOVED_FROM_SOURCE, $preview['removed'][0]['status']);
        $this->assertSame('Text A', $DB->get_field('local_crout_criterion', 'name', ['code' => 'RA1.a']));
        $this->assertTrue($DB->record_exists('local_crout_criterion', ['code' => 'RA1.b', 'archived' => 0]));
    }

    /**
     * A failure after one entity write rolls back all curriculum changes and leaves a failed batch.
     */
    public function test_import_transaction_rolls_back_partial_entities(): void {
        global $DB;
        [$course, $scale, $data] = $this->fixture('BOE-A-2022-1', '0371', ['RA1.a' => 'A', 'RA1.b' => 'B']);
        // PostgreSQL advanced_testcase tests normally run inside a Moodle-owned outer transaction.
        // End that wrapper so the service transaction is the real outermost transaction and its
        // rollback is observable here, just as it is in a production request.
        $this->preventResetByRollback();
        $service = new class extends import_service {
            /**
             * @var int Number of entities observed.
             */
            private int $seen = 0;

            /**
             * Fail before the second entity is persisted.
             */
            protected function before_entity_persist(array $criterion): void {
                $this->seen++;
                if ($this->seen === 2) {
                    throw new \RuntimeException('Intentional transaction test failure.');
                }
            }
        };
        try {
            $service->import($course->id, $scale->id, $data);
            $this->fail('The intentional failure must escape.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Intentional', $e->getMessage());
        }
        $this->assertEquals(0, $DB->count_records('local_crout_framework'));
        $this->assertEquals(0, $DB->count_records('local_crout_criterion'));
        $this->assertEquals(0, $DB->count_records('grade_outcomes', ['courseid' => $course->id]));
        $this->assertTrue($DB->record_exists('local_crout_importbatch', ['courseid' => $course->id, 'status' => 'failed']));
    }

    /**
     * Set up course, teacher, scale and normalized data.
     */
    private function fixture(string $sourceid, string $modulecode, array $criteria): array {
        global $CFG;
        $this->resetAfterTest();
        $CFG->enableoutcomes = 1;
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $scale = $this->getDataGenerator()->create_scale(['courseid' => $course->id, 'scale' => 'No,Yes']);
        return [$course, $scale, $this->data($sourceid, $modulecode, $criteria)];
    }

    /**
     * Build provider-neutral normalized data.
     */
    private function data(string $sourceid, string $modulecode, array $criteria): array {
        $items = [];
        foreach ($criteria as $code => $name) {
            $items[] = ['codigo' => $code, 'nombre' => $name];
        }
        return (new provider\json_provider())->parse(json_encode([
            'metadata' => [
                'name' => 'Module ' . $modulecode, 'type' => 'fp', 'source' => 'boe',
                'source_id' => $sourceid, 'modulecode' => $modulecode, 'curriculumkey' => $modulecode,
            ],
            'resultados' => [['codigo' => 'RA1', 'nombre' => 'Result', 'criterios' => $items]],
        ]));
    }
}
