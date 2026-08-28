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
 * Import service tests.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_criteriaoutcomes;

use local_criteriaoutcomes\service\import_service;


/**
 * Tests native Outcome creation, mapping and idempotency.
 */
final class import_service_test extends \advanced_testcase {
    public function test_import_is_idempotent_and_maps_outcome(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        $CFG->enableoutcomes = 1;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);
        $scale = $this->getDataGenerator()->create_scale(['courseid' => $course->id, 'scale' => 'Not yet,Achieved']);
        $data = (new \local_criteriaoutcomes\provider\json_provider())->parse(json_encode([
            'metadata' => ['name' => 'Test', 'type' => 'fp'],
            'resultados' => [['codigo' => 'RA1', 'nombre' => 'Parent',
                'criterios' => [['codigo' => 'RA1.a', 'nombre' => 'Criterion']]]],
        ]));
        $service = new \local_criteriaoutcomes\service\import_service();
        $first = $service->import($course->id, $scale->id, $data);
        $second = $service->import($course->id, $scale->id, $data);
        $this->assertSame(1, $first['new']);
        $this->assertSame(1, $second['existing']);
        $this->assertEquals(1, $DB->count_records('grade_outcomes', ['courseid' => $course->id, 'shortname' => 'RA1.a']));
        $mapping = $DB->get_record('local_crout_criterion', ['code' => 'RA1.a'], '*', MUST_EXIST);
        $this->assertTrue($DB->record_exists('grade_outcomes', ['id' => $mapping->outcomeid]));
    }

    /**
     * External shortnames are conflicts and are never adopted.
     */
    public function test_external_outcome_is_conflict(): void {
        [$course, $scale, $data, $service] = $this->fixture();
        $external = new \grade_outcome();
        $external->courseid = $course->id;
        $external->shortname = 'RA1.a';
        $external->fullname = 'External';
        $external->scaleid = $scale->id;
        $external->insert('phpunit');
        $preview = $service->preview($course->id, $data, $scale->id);
        $this->assertSame(import_service::STATUS_CONFLICT, $preview['parents'][0]['criteria'][0]['status']);
        $result = $service->import($course->id, $scale->id, $data);
        $this->assertSame(1, $result['conflict']);
        $this->assertSame('External', \grade_outcome::fetch(['id' => $external->id])->fullname);
    }

    /**
     * Plugin mappings distinguish text and scale changes.
     */
    public function test_preview_change_statuses(): void {
        [$course, $scale, $data, $service] = $this->fixture();
        $service->import($course->id, $scale->id, $data);
        $data['parents'][0]['criteria'][0]['name'] = 'Changed text';
        $preview = $service->preview($course->id, $data, $scale->id);
        $this->assertSame(import_service::STATUS_TEXT_CHANGED, $preview['parents'][0]['criteria'][0]['status']);
        $scale2 = $this->getDataGenerator()->create_scale(['courseid' => $course->id, 'scale' => 'No,Maybe,Yes']);
        $preview = $service->preview($course->id, $data, $scale2->id);
        $this->assertSame(import_service::STATUS_TEXT_AND_SCALE_CHANGED, $preview['parents'][0]['criteria'][0]['status']);
        $this->assertTrue($preview['parents'][0]['criteria'][0]['scalesafe']);
    }

    /**
     * Existing grade items block scale changes, and grades are never reinterpreted.
     */
    public function test_scale_change_is_blocked_after_use(): void {
        global $DB;
        [$course, $scale, $data, $service] = $this->fixture();
        $service->import($course->id, $scale->id, $data);
        $outcome = \grade_outcome::fetch(['courseid' => $course->id, 'shortname' => 'RA1.a']);
        $item = new \grade_item(['courseid' => $course->id, 'itemtype' => 'manual', 'itemname' => 'Evidence',
            'outcomeid' => $outcome->id, 'gradetype' => GRADE_TYPE_SCALE, 'scaleid' => $scale->id]);
        $item->insert('phpunit');
        $grade = new \grade_grade(['itemid' => $item->id, 'userid' => $this->getDataGenerator()->create_user()->id,
            'finalgrade' => 1]);
        $grade->insert('phpunit');
        $scale2 = $this->getDataGenerator()->create_scale(['courseid' => $course->id, 'scale' => 'Low,High']);
        $preview = $service->preview($course->id, $data, $scale2->id);
        $criterion = $preview['parents'][0]['criteria'][0];
        $this->assertFalse($criterion['scalesafe']);
        $this->assertTrue($criterion['hasgrades']);
        $result = $service->import($course->id, $scale2->id, $data);
        $this->assertSame(1, $result['scaleblocked']);
        $this->assertSame((int)$scale->id, (int)\grade_outcome::fetch(['id' => $outcome->id])->scaleid);
        $this->assertEquals(1, $DB->get_field('grade_grades', 'finalgrade', ['id' => $grade->id]));
    }

    /**
     * Native activities create independent, repeatable evidence grade items.
     */
    public function test_multiple_activity_evidence_uses_native_grade_items(): void {
        global $DB;
        [$course, $scale, $data, $service] = $this->fixture();
        $data['parents'][0]['criteria'][] = [
            'code' => 'RA1.c', 'name' => 'Criterion C', 'weight' => null, 'sortorder' => 1,
        ];
        $service->import($course->id, $scale->id, $data);
        $outcomea = \grade_outcome::fetch(['courseid' => $course->id, 'shortname' => 'RA1.a']);
        $outcomec = \grade_outcome::fetch(['courseid' => $course->id, 'shortname' => 'RA1.c']);
        $assigna = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id, 'name' => 'Activity A', 'outcome_' . $outcomea->id => 1,
        ]);
        $assignb = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id, 'name' => 'Activity B',
            'outcome_' . $outcomea->id => 1, 'outcome_' . $outcomec->id => 1,
        ]);
        $items = $DB->get_records('grade_items', [
            'courseid' => $course->id, 'itemmodule' => 'assign', 'outcomeid' => $outcomea->id,
        ]);
        $this->assertCount(2, $items);
        $this->assertEqualsCanonicalizing([$assigna->id, $assignb->id], array_column($items, 'iteminstance'));
        $this->assertEquals(1, $DB->count_records('grade_items', [
            'courseid' => $course->id, 'itemmodule' => 'assign', 'iteminstance' => $assignb->id,
            'outcomeid' => $outcomec->id,
        ]));
        $normalitems = $DB->get_records('grade_items', [
            'courseid' => $course->id, 'itemmodule' => 'assign', 'iteminstance' => $assigna->id, 'outcomeid' => null,
        ]);
        $this->assertCount(1, $normalitems);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $normalitem = reset($normalitems);
        $outcomeitem = reset($items);
        (new \grade_grade(['itemid' => $normalitem->id, 'userid' => $student->id, 'finalgrade' => 8.5]))->insert('phpunit');
        (new \grade_grade(['itemid' => $outcomeitem->id, 'userid' => $student->id, 'finalgrade' => 2]))->insert('phpunit');
        $this->assertEquals(8.5, $DB->get_field('grade_grades', 'finalgrade', [
            'itemid' => $normalitem->id, 'userid' => $student->id,
        ]));
        $this->assertEquals(2, $DB->get_field('grade_grades', 'finalgrade', [
            'itemid' => $outcomeitem->id, 'userid' => $student->id,
        ]));
    }

    /**
     * Course restore remaps plugin criteria to restored core Outcomes.
     */
    public function test_backup_restore_remaps_outcomes(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        [$course, $scale, $data, $service] = $this->fixture();
        $service->import($course->id, $scale->id, $data);
        $outcome = \grade_outcome::fetch(['courseid' => $course->id, 'shortname' => 'RA1.a']);
        $assignment = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id, 'name' => 'Restored evidence', 'outcome_' . $outcome->id => 1,
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $outcomeitem = $DB->get_record('grade_items', [
            'courseid' => $course->id, 'itemmodule' => 'assign', 'iteminstance' => $assignment->id,
            'outcomeid' => $outcome->id,
        ], '*', MUST_EXIST);
        $outcomegrade = new \grade_grade(['itemid' => $outcomeitem->id, 'userid' => $student->id, 'finalgrade' => 2]);
        $outcomegrade->insert('phpunit');
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id, 'name' => 'Mapped quiz', 'sumgrades' => 1,
        ]);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('truefalse', null, ['category' => $category->id]);
        quiz_add_quiz_question($question->id, $quiz);
        $oldslot = $DB->get_record('quiz_slots', ['quizid' => $quiz->id], '*', MUST_EXIST);
        $criterionid = $DB->get_field('local_crout_criterion', 'id', ['outcomeid' => $outcome->id]);
        $quizmapping = new \local_criteriaoutcomes\service\quiz_mapping_service();
        $quizmapping->save_mapping($quiz->id, $oldslot->id, $criterionid, 2.5);
        $quizmapping->save_configuration(
            $quiz->id,
            $criterionid,
            \local_criteriaoutcomes\service\criterion_aggregation_service::WEIGHTED_MEAN
        );
        $oldcriterion = $DB->get_record('local_crout_criterion', ['outcomeid' => $outcome->id], '*', MUST_EXIST);
        $backupdir = make_backup_temp_directory('');
        $controller = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            get_admin()->id
        );
        $controller->execute_plan();
        $results = $controller->get_results();
        $packer = get_file_packer('application/vnd.moodle.backup');
        $results['backup_destination']->extract_to_pathname($packer, $backupdir . '/criteriaoutcomes_restore');
        $controller->destroy();
        $categoryid = $DB->get_field_sql('SELECT MIN(id) FROM {course_categories}');
        $newcourseid = \restore_dbops::create_new_course('Restored', 'restored-crout', $categoryid);
        $restore = new \restore_controller(
            'criteriaoutcomes_restore',
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            get_admin()->id,
            \backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($restore->execute_precheck());
        $restore->execute_plan();
        $newcourseid = $restore->get_courseid();
        $restore->destroy();
        $restored = $DB->get_record_sql(
            "SELECT c.* FROM {local_crout_criterion} c
            JOIN {local_crout_parent} p ON p.id = c.parentid
            JOIN {local_crout_framework} f ON f.id = p.frameworkid WHERE f.courseid = :courseid",
            ['courseid' => $newcourseid],
            MUST_EXIST
        );
        $this->assertNotSame((int)$oldcriterion->outcomeid, (int)$restored->outcomeid);
        $this->assertTrue($DB->record_exists('grade_outcomes', [
            'id' => $restored->outcomeid, 'courseid' => $newcourseid, 'shortname' => 'RA1.a',
        ]));
        $this->assertTrue($DB->record_exists('grade_items', [
            'courseid' => $newcourseid, 'outcomeid' => $restored->outcomeid,
        ]));
        $this->assertTrue($DB->record_exists_sql(
            "SELECT 1 FROM {grade_grades} gg
            JOIN {grade_items} gi ON gi.id = gg.itemid
            WHERE gi.courseid = :courseid AND gi.outcomeid = :outcomeid AND gg.finalgrade = :grade",
            ['courseid' => $newcourseid, 'outcomeid' => $restored->outcomeid, 'grade' => 2]
        ));
        $restoredquiz = $DB->get_record('quiz', ['course' => $newcourseid, 'name' => 'Mapped quiz'], '*', MUST_EXIST);
        $restoredmap = $DB->get_record('local_crout_quizmap', [
            'quizid' => $restoredquiz->id, 'criterionid' => $restored->id,
        ], '*', MUST_EXIST);
        $this->assertNotSame((int)$oldslot->id, (int)$restoredmap->slotid);
        $this->assertTrue($DB->record_exists('quiz_slots', [
            'id' => $restoredmap->slotid, 'quizid' => $restoredquiz->id,
        ]));
        $this->assertEquals(2.5, $restoredmap->weight);
        $this->assertSame(
            \local_criteriaoutcomes\service\criterion_aggregation_service::WEIGHTED_MEAN,
            $DB->get_field('local_crout_quizcfg', 'aggregation', [
                'quizid' => $restoredquiz->id, 'criterionid' => $restored->id,
            ])
        );
    }

    /**
     * Build an isolated course, teacher, scale and normalized curriculum.
     */
    private function fixture(): array {
        global $CFG;
        $this->resetAfterTest();
        $CFG->enableoutcomes = 1;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);
        $scale = $this->getDataGenerator()->create_scale(['courseid' => $course->id, 'scale' => 'No,Yes']);
        $data = (new \local_criteriaoutcomes\provider\json_provider())->parse(json_encode([
            'metadata' => ['name' => 'Test', 'type' => 'fp'],
            'resultados' => [['codigo' => 'RA1', 'nombre' => 'Parent',
                'criterios' => [['codigo' => 'RA1.a', 'nombre' => 'Criterion']]]],
        ]));
        return [$course, $scale, $data, new import_service()];
    }
}
