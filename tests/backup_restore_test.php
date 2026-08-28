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
 * Backup and restore tests for local_criteriaoutcomes 0.3 tables.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_criteriaoutcomes;

use local_criteriaoutcomes\constants;
use local_criteriaoutcomes\service\assessment_service;
use local_criteriaoutcomes\service\checklist_service;
use local_criteriaoutcomes\service\judgement_service;
use local_criteriaoutcomes\service\import_service;

/**
 * Tests backup/restore for curriculum hierarchy, rubric mapping, checklist,
 * and user data (assessments, checklist responses, judgements, feedback reads).
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Course-level backup restores curriculum definitions but not user data.
     */
    public function test_backup_restore_course_only_no_user_data(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $this->resetAfterTest();
        $CFG->enableoutcomes = 1;

        [$course, $teacher, $student, $criterionid] = $this->create_course_with_curriculum();

        // Failed batches are structural audit history too. They deliberately have no partial items.
        $DB->insert_record('local_crout_importbatch', (object)[
            'courseid' => $course->id,
            'frameworkid' => null,
            'provider' => 'json',
            'sourceid' => 'failed-backup-audit',
            'curriculumkey' => 'failed-backup-audit',
            'userid' => $teacher->id,
            'operation' => 'import',
            'checksum' => str_repeat('f', 64),
            'status' => 'failed',
            'summary' => null,
            'timecreated' => time(),
            'timecompleted' => null,
        ]);

        // Checklist definition + item + mapping.
        $clservice = new checklist_service();
        $defid = $clservice->create_definition($course->id, 'Test checklist', constants::CHECKLIST_BINARY);
        $itemid = $clservice->create_item($defid, 'Item 1', 0, 1.0);
        $clservice->map_item($itemid, $criterionid);

        // Assessment (user data - should NOT be backed up without userinfo).
        $aservice = new assessment_service();
        $assessmentid = $aservice->save_assessment([
            'courseid' => $course->id, 'criterionid' => $criterionid,
            'userid' => $student->id, 'sourcetype' => constants::SOURCE_DIRECT,
            'sourceid' => 0, 'assessmentmode' => constants::MODE_FEEDBACK_ONLY,
            'feedback' => 'Released feedback excluded from backup', 'graderid' => $teacher->id,
            'status' => constants::STATUS_RELEASED,
        ]);
        (new judgement_service())->save_judgement(
            $course->id,
            $criterionid,
            $student->id,
            1,
            'Draft judgement',
            $teacher->id
        );
        $clservice->save_response($defid, $itemid, $student->id, constants::CHECKLIST_DONE, null, $teacher->id);
        $DB->insert_record('local_crout_feedback_read', (object)[
            'assessmentid' => $assessmentid,
            'userid' => $student->id,
            'timeread' => time(),
        ]);

        // Backup + restore.
        [$newcourseid, $extractpath] = $this->backup_and_restore($course->id, false);

        // Verify curriculum restored.
        $this->assertTrue($DB->record_exists('local_crout_framework', ['courseid' => $newcourseid]));
        $newcriterion = $DB->get_record_sql(
            "SELECT c.* FROM {local_crout_criterion} c
            JOIN {local_crout_parent} p ON p.id = c.parentid
            JOIN {local_crout_framework} f ON f.id = p.frameworkid
            WHERE f.courseid = :courseid",
            ['courseid' => $newcourseid]
        );
        $this->assertNotEmpty($newcriterion);
        $this->assertSame('RA1.a', $newcriterion->code);
        $newframework = $DB->get_record('local_crout_framework', ['courseid' => $newcourseid], '*', MUST_EXIST);
        $this->assertSame('json', $newframework->provider);
        $this->assertSame(0, (int)$newframework->archived);
        $restoredbatch = $DB->get_record('local_crout_importbatch', [
            'courseid' => $newcourseid,
            'status' => 'success',
        ], '*', MUST_EXIST);
        $this->assertNull($restoredbatch->userid);
        $this->assertSame((int)$newframework->id, (int)$restoredbatch->frameworkid);
        $this->assertTrue($DB->record_exists('local_crout_importitem', ['batchid' => $restoredbatch->id]));
        $failedbatch = $DB->get_record('local_crout_importbatch', [
            'courseid' => $newcourseid,
            'status' => 'failed',
        ], '*', MUST_EXIST);
        $this->assertNull($failedbatch->userid);
        $this->assertNull($failedbatch->frameworkid);
        $this->assertFalse($DB->record_exists('local_crout_importitem', ['batchid' => $failedbatch->id]));

        // Verify checklist definition restored.
        $newdef = $DB->get_record('local_crout_checklist_def', ['courseid' => $newcourseid]);
        $this->assertNotEmpty($newdef);
        $this->assertSame('Test checklist', $newdef->name);

        // Verify checklist item restored.
        $newitem = $DB->get_record('local_crout_checklist_item', ['definitionid' => $newdef->id]);
        $this->assertNotEmpty($newitem);

        // Verify user data NOT restored.
        $this->assertFalse($DB->record_exists('local_crout_assessment', ['courseid' => $newcourseid]));
        $this->assertFalse($DB->record_exists('local_crout_judgement', ['courseid' => $newcourseid]));
        $this->assertFalse($DB->record_exists('local_crout_checklist_resp', ['definitionid' => $newdef->id]));
        $this->assertEquals(0, $DB->count_records_sql(
            "SELECT COUNT(fr.id)
               FROM {local_crout_feedback_read} fr
               JOIN {local_crout_assessment} a ON a.id = fr.assessmentid
              WHERE a.courseid = :courseid",
            ['courseid' => $newcourseid]
        ));
    }

    /**
     * Course-level backup with userinfo restores user data with remapped IDs.
     */
    public function test_backup_restore_with_user_data(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $this->resetAfterTest();
        $CFG->enableoutcomes = 1;

        [$course, $teacher, $student, $criterionid] = $this->create_course_with_curriculum();

        // Assessment (released, visible to students).
        $aservice = new assessment_service();
        $assessmentid = $aservice->save_assessment([
            'courseid' => $course->id, 'criterionid' => $criterionid,
            'userid' => $student->id, 'sourcetype' => constants::SOURCE_DIRECT,
            'sourceid' => 0, 'assessmentmode' => constants::MODE_FEEDBACK_ONLY,
            'feedback' => 'Released feedback', 'graderid' => $teacher->id,
            'status' => constants::STATUS_RELEASED,
        ]);

        // Judgement.
        $jservice = new judgement_service();
        $jservice->save_judgement($course->id, $criterionid, $student->id, 3, 'Good progress', $teacher->id);

        // Feedback read.
        $DB->insert_record('local_crout_feedback_read', (object)[
            'assessmentid' => $assessmentid, 'userid' => $student->id, 'timeread' => time(),
        ]);

        // Backup + restore.
        [$newcourseid] = $this->backup_and_restore($course->id, true);

        // Verify assessment restored.
        $restoredassessment = $DB->get_record_sql(
            "SELECT a.* FROM {local_crout_assessment} a
            JOIN {local_crout_criterion} c ON c.id = a.criterionid
            JOIN {local_crout_parent} p ON p.id = c.parentid
            JOIN {local_crout_framework} f ON f.id = p.frameworkid
            WHERE f.courseid = :courseid",
            ['courseid' => $newcourseid]
        );
        $this->assertNotEmpty($restoredassessment);
        $this->assertSame(constants::STATUS_RELEASED, $restoredassessment->status);
        $this->assertSame('Released feedback', $restoredassessment->feedback);
        $this->assertSame((int)$student->id, (int)$restoredassessment->userid);
        $this->assertSame((int)$teacher->id, (int)$restoredassessment->graderid);
        $restoredbatch = $DB->get_record('local_crout_importbatch', ['courseid' => $newcourseid], '*', MUST_EXIST);
        $this->assertSame((int)$teacher->id, (int)$restoredbatch->userid);

        // Verify judgement restored.
        $restoredjudgement = $DB->get_record_sql(
            "SELECT j.* FROM {local_crout_judgement} j
            JOIN {local_crout_criterion} c ON c.id = j.criterionid
            JOIN {local_crout_parent} p ON p.id = c.parentid
            JOIN {local_crout_framework} f ON f.id = p.frameworkid
            WHERE f.courseid = :courseid",
            ['courseid' => $newcourseid]
        );
        $this->assertNotEmpty($restoredjudgement);
        $this->assertSame(3, (int)$restoredjudgement->scalevalue);
        $this->assertSame((int)$student->id, (int)$restoredjudgement->userid);
        $this->assertSame((int)$teacher->id, (int)$restoredjudgement->graderid);

        // Verify feedback read restored.
        $this->assertTrue($DB->record_exists('local_crout_feedback_read', [
            'assessmentid' => $restoredassessment->id,
        ]));
    }

    /**
     * Checklist backup/restore preserves definitions, items, mappings and responses.
     */
    public function test_backup_restore_checklist(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $this->resetAfterTest();
        $CFG->enableoutcomes = 1;

        [$course, $teacher, $student, $criterionid] = $this->create_course_with_curriculum();

        $clservice = new checklist_service();
        $defid = $clservice->create_definition($course->id, 'Checklist A', constants::CHECKLIST_BINARY);
        $itemid = $clservice->create_item($defid, 'Item 1', 0, 2.0);
        $clservice->map_item($itemid, $criterionid);
        $clservice->save_response($defid, $itemid, $student->id, constants::CHECKLIST_DONE, null, $teacher->id);

        // Backup + restore.
        [$newcourseid] = $this->backup_and_restore($course->id, true);

        // Verify checklist definition restored.
        $newdef = $DB->get_record('local_crout_checklist_def', ['courseid' => $newcourseid]);
        $this->assertNotEmpty($newdef);
        $this->assertSame('Checklist A', $newdef->name);

        // Verify checklist item restored.
        $newitem = $DB->get_record('local_crout_checklist_item', ['definitionid' => $newdef->id]);
        $this->assertNotEmpty($newitem);
        $this->assertSame('Item 1', $newitem->name);

        // Verify mapping restored.
        $newmap = $DB->get_record('local_crout_checklist_map', ['itemid' => $newitem->id]);
        $this->assertNotEmpty($newmap);
        $newmappedcriterion = $DB->get_record('local_crout_criterion', ['id' => $newmap->criterionid]);
        $this->assertNotEmpty($newmappedcriterion);
        $this->assertSame('RA1.a', $newmappedcriterion->code);

        // Verify checklist response restored.
        $newresp = $DB->get_record('local_crout_checklist_resp', [
            'definitionid' => $newdef->id, 'itemid' => $newitem->id,
        ]);
        $this->assertNotEmpty($newresp);
        $this->assertSame(constants::CHECKLIST_DONE, $newresp->state);
    }

    /**
     * Create a course with a curriculum using the import service.
     *
     * @return array [$course, $teacher, $student, $criterionid]
     */
    private function create_course_with_curriculum(): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($teacher);

        $scale = $this->getDataGenerator()->create_scale(['courseid' => $course->id, 'scale' => 'No,Yes']);
        $data = (new provider\json_provider())->parse(json_encode([
            'metadata' => ['name' => 'Test', 'type' => 'fp'],
            'resultados' => [['codigo' => 'RA1', 'nombre' => 'Parent',
                'criterios' => [['codigo' => 'RA1.a', 'nombre' => 'Criterion A']]]],
        ]));
        $importer = new import_service();
        $importer->import($course->id, $scale->id, $data);

        $criterionid = $DB->get_field('local_crout_criterion', 'id', ['code' => 'RA1.a']);
        return [$course, $teacher, $student, $criterionid];
    }

    /**
     * Perform a course backup and restore, returning the new course ID.
     *
     * @return array [$newcourseid, $backupdir]
     */
    private function backup_and_restore(int $courseid, bool $userinfo): array {
        global $DB;
        $backupdir = make_backup_temp_directory('');
        $controller = new \backup_controller(
            \backup::TYPE_1COURSE,
            $courseid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            get_admin()->id
        );
        $controller->get_plan()->get_setting('users')->set_value($userinfo);
        $controller->execute_plan();
        $results = $controller->get_results();
        $packer = get_file_packer('application/vnd.moodle.backup');
        $extractpath = $backupdir . '/crout_restore_test';
        $results['backup_destination']->extract_to_pathname($packer, $extractpath);
        $controller->destroy();

        $categoryid = $DB->get_field_sql('SELECT MIN(id) FROM {course_categories}');
        $newcourseid = \restore_dbops::create_new_course('Restored', 'restored-crout-' . $courseid, $categoryid);
        $restore = new \restore_controller(
            'crout_restore_test',
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            get_admin()->id,
            \backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($restore->execute_precheck());
        $restore->execute_plan();
        $restore->destroy();

        return [$newcourseid, $extractpath];
    }
}
