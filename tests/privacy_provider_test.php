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
 * Privacy provider tests.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_criteriaoutcomes;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use local_criteriaoutcomes\privacy\provider;
use local_criteriaoutcomes\service\assessment_service;
use local_criteriaoutcomes\service\judgement_service;
use local_criteriaoutcomes\service\feedback_service;
use local_criteriaoutcomes\constants;

/**
 * Tests metadata, context lookup, export, and delete operations.
 */
final class privacy_provider_test extends \advanced_testcase {
    /**
     * Metadata declares expected tables.
     */
    public function test_metadata_declares_tables(): void {
        $collection = new \core_privacy\local\metadata\collection('local_criteriaoutcomes');
        $result = provider::get_metadata($collection);
        $types = $result->get_collection();
        $this->assertNotEmpty($types);
    }

    /**
     * get_contexts_for_userid returns course context when data exists.
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $criterion = $this->create_criterion($course->id);

        $assessmentservice = new assessment_service();
        $id = $assessmentservice->save_assessment([
            'courseid' => $course->id,
            'criterionid' => $criterion,
            'userid' => $student->id,
            'sourcetype' => constants::SOURCE_DIRECT,
            'sourceid' => 0,
            'assessmentmode' => constants::MODE_FEEDBACK_ONLY,
            'feedback' => 'Test',
            'graderid' => $teacher->id,
            'status' => constants::STATUS_DRAFT,
        ]);
        $assessmentservice->release_assessment($id);

        $contexts = provider::get_contexts_for_userid($student->id);
        $this->assertNotEmpty($contexts->get_contextids());
    }

    /**
     * delete_data_for_all_users_in_context removes all data.
     */
    public function test_delete_all_in_context(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $criterion = $this->create_criterion($course->id);
        $context = \context_course::instance($course->id);

        $assessmentservice = new assessment_service();
        $id = $assessmentservice->save_assessment([
            'courseid' => $course->id,
            'criterionid' => $criterion,
            'userid' => $student->id,
            'sourcetype' => constants::SOURCE_DIRECT,
            'sourceid' => 0,
            'assessmentmode' => constants::MODE_FEEDBACK_ONLY,
            'feedback' => 'Delete me',
            'graderid' => $teacher->id,
            'status' => constants::STATUS_DRAFT,
        ]);
        $assessmentservice->release_assessment($id);

        $judgementservice = new judgement_service();
        $judgementservice->save_judgement($course->id, $criterion, $student->id, 3, 'Delete', $teacher->id);

        $this->assertNotEmpty($DB->get_records('local_crout_assessment', ['courseid' => $course->id]));
        $this->assertNotEmpty($DB->get_records('local_crout_judgement', ['courseid' => $course->id]));

        provider::delete_data_for_all_users_in_context($context);

        $this->assertEmpty($DB->get_records('local_crout_assessment', ['courseid' => $course->id]));
        $this->assertEmpty($DB->get_records('local_crout_judgement', ['courseid' => $course->id]));
    }

    /**
     * delete_data_for_user removes only target user data.
     */
    public function test_delete_for_user_isolation(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $criterion = $this->create_criterion($course->id);
        $context = \context_course::instance($course->id);

        $assessmentservice = new assessment_service();
        foreach ([$student1, $student2] as $s) {
            $id = $assessmentservice->save_assessment([
                'courseid' => $course->id,
                'criterionid' => $criterion,
                'userid' => $s->id,
                'sourcetype' => constants::SOURCE_DIRECT,
                'sourceid' => 0,
                'assessmentmode' => constants::MODE_FEEDBACK_ONLY,
                'feedback' => 'Feedback for ' . $s->id,
                'graderid' => $teacher->id,
                'status' => constants::STATUS_DRAFT,
            ]);
            $assessmentservice->release_assessment($id);
        }

        $approved = new approved_contextlist($student1, 'local_criteriaoutcomes', [$context->id]);
        provider::delete_data_for_user($approved);

        $this->assertEmpty($DB->get_records('local_crout_assessment', [
            'courseid' => $course->id, 'userid' => $student1->id,
        ]));
        $this->assertNotEmpty($DB->get_records('local_crout_assessment', [
            'courseid' => $course->id, 'userid' => $student2->id,
        ]));
    }

    /**
     * Approved-user discovery and export include both student and grader data.
     */
    public function test_approved_users_and_export(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $criterion = $this->create_criterion($course->id);
        $context = \context_course::instance($course->id);
        $assessmentid = (new assessment_service())->save_assessment([
            'courseid' => $course->id,
            'criterionid' => $criterion,
            'userid' => $student->id,
            'sourcetype' => constants::SOURCE_DIRECT,
            'sourceid' => 0,
            'assessmentmode' => constants::MODE_FEEDBACK_ONLY,
            'feedback' => 'Exported feedback',
            'graderid' => $teacher->id,
            'status' => constants::STATUS_DRAFT,
        ]);

        $userlist = new userlist($context, 'local_criteriaoutcomes');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$student->id, $teacher->id], $userlist->get_userids());

        provider::export_user_data(new approved_contextlist(
            $student,
            'local_criteriaoutcomes',
            [$context->id]
        ));
        $data = \core_privacy\local\request\writer::with_context($context)->get_data([
            'Assessments',
            $assessmentid,
        ]);
        $this->assertSame('Exported feedback', $data->feedback);
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
            'identitykey' => hash('sha256', 'privacy-test-' . $courseid . $code),
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
            'outcomeid' => 6000 + $courseid + crc32($code),
            'sortorder' => 0,
        ]);
    }

    /**
     * Import audit attribution is discoverable/exportable and deletion anonymises it.
     */
    public function test_import_batch_user_is_anonymised_but_history_survives(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $context = \context_course::instance($course->id);
        $batchid = $DB->insert_record('local_crout_importbatch', (object)[
            'courseid' => $course->id, 'provider' => 'json', 'sourceid' => 'privacy-source',
            'curriculumkey' => 'privacy-curriculum', 'userid' => $teacher->id, 'operation' => 'import',
            'checksum' => hash('sha256', 'privacy'), 'status' => 'success', 'summary' => '{"created":1}',
            'timecreated' => time(), 'timecompleted' => time(),
        ]);

        $contexts = provider::get_contexts_for_userid($teacher->id);
        $this->assertContains((int)$context->id, array_map('intval', $contexts->get_contextids()));
        $userlist = new userlist($context, 'local_criteriaoutcomes');
        provider::get_users_in_context($userlist);
        $this->assertContains((int)$teacher->id, array_map('intval', $userlist->get_userids()));

        provider::export_user_data(new approved_contextlist($teacher, 'local_criteriaoutcomes', [$context->id]));
        $export = \core_privacy\local\request\writer::with_context($context)->get_data([
            'Import history', $batchid,
        ]);
        $this->assertSame('privacy-curriculum', $export->curriculumkey);

        provider::delete_data_for_user(new approved_contextlist(
            $teacher,
            'local_criteriaoutcomes',
            [$context->id]
        ));
        $batch = $DB->get_record('local_crout_importbatch', ['id' => $batchid], '*', MUST_EXIST);
        $this->assertNull($batch->userid);
        $this->assertSame('success', $batch->status);
    }
}
