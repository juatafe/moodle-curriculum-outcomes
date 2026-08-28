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
 * Assessment service tests.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_criteriaoutcomes;

use local_criteriaoutcomes\constants;
use local_criteriaoutcomes\service\assessment_service;

/**
 * Tests for assessment modes, draft/released status, and CRUD.
 */
final class assessment_service_test extends \advanced_testcase {
    /**
     * FEEDBACK_ONLY stores feedback but no value or scalevalue.
     */
    public function test_feedback_only_mode(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $criterionid = $this->create_criterion($course->id);

        $service = new assessment_service();
        $id = $service->save_assessment([
            'courseid' => $course->id,
            'criterionid' => $criterionid,
            'userid' => $user->id,
            'sourcetype' => constants::SOURCE_DIRECT,
            'sourceid' => 0,
            'assessmentmode' => constants::MODE_FEEDBACK_ONLY,
            'feedback' => 'Revisa la configuración DNS.',
            'feedbackformat' => FORMAT_HTML,
            'graderid' => $teacher->id,
            'status' => constants::STATUS_DRAFT,
        ]);

        $this->assertGreaterThan(0, $id);
        $record = $DB->get_record('local_crout_assessment', ['id' => $id]);
        $this->assertSame(constants::MODE_FEEDBACK_ONLY, $record->assessmentmode);
        $this->assertNull($record->value);
        $this->assertNull($record->scalevalue);
        $this->assertSame('Revisa la configuración DNS.', $record->feedback);
        $this->assertSame(constants::STATUS_DRAFT, $record->status);
    }

    /**
     * VALUE_ONLY stores scalevalue but feedback is optional.
     */
    public function test_value_only_mode(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $criterionid = $this->create_criterion($course->id);

        $service = new assessment_service();
        $id = $service->save_assessment([
            'courseid' => $course->id,
            'criterionid' => $criterionid,
            'userid' => $user->id,
            'sourcetype' => constants::SOURCE_DIRECT,
            'sourceid' => 0,
            'assessmentmode' => constants::MODE_VALUE_ONLY,
            'scalevalue' => 3,
            'graderid' => $teacher->id,
            'status' => constants::STATUS_DRAFT,
        ]);

        $record = $DB->get_record('local_crout_assessment', ['id' => $id]);
        $this->assertSame(constants::MODE_VALUE_ONLY, $record->assessmentmode);
        $this->assertSame(3, (int)$record->scalevalue);
        $this->assertNull($record->feedback);
    }

    /**
     * VALUE_AND_FEEDBACK stores both.
     */
    public function test_value_and_feedback_mode(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $criterionid = $this->create_criterion($course->id);

        $service = new assessment_service();
        $id = $service->save_assessment([
            'courseid' => $course->id,
            'criterionid' => $criterionid,
            'userid' => $user->id,
            'sourcetype' => constants::SOURCE_DIRECT,
            'sourceid' => 0,
            'assessmentmode' => constants::MODE_VALUE_AND_FEEDBACK,
            'scalevalue' => 2,
            'feedback' => 'En proceso, falta justificar.',
            'graderid' => $teacher->id,
            'status' => constants::STATUS_DRAFT,
        ]);

        $record = $DB->get_record('local_crout_assessment', ['id' => $id]);
        $this->assertSame(constants::MODE_VALUE_AND_FEEDBACK, $record->assessmentmode);
        $this->assertSame(2, (int)$record->scalevalue);
        $this->assertSame('En proceso, falta justificar.', $record->feedback);
    }

    /**
     * DRAFT is not visible to students, RELEASED is visible.
     */
    public function test_draft_and_release_status(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $criterionid = $this->create_criterion($course->id);

        $service = new assessment_service();
        $id = $service->save_assessment([
            'courseid' => $course->id,
            'criterionid' => $criterionid,
            'userid' => $user->id,
            'sourcetype' => constants::SOURCE_DIRECT,
            'sourceid' => 0,
            'assessmentmode' => constants::MODE_FEEDBACK_ONLY,
            'feedback' => 'Draft feedback.',
            'graderid' => $teacher->id,
            'status' => constants::STATUS_DRAFT,
        ]);

        // Student should not see draft.
        $released = $service->get_released_assessments($course->id, $criterionid, $user->id);
        $this->assertCount(0, $released);

        // Release.
        $service->release_assessment($id);
        $released = $service->get_released_assessments($course->id, $criterionid, $user->id);
        $this->assertCount(1, $released);

        // Teacher still sees all (draft + released).
        $all = $service->get_assessments_for_criterion($course->id, $criterionid, $user->id);
        $this->assertCount(1, $all);

        // Draft it again.
        $service->draft_assessment($id);
        $released = $service->get_released_assessments($course->id, $criterionid, $user->id);
        $this->assertCount(0, $released);
    }

    /**
     * FEEDBACK_ONLY mode rejects value or scalevalue.
     */
    public function test_feedback_only_rejects_value(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $criterionid = $this->create_criterion($course->id);

        $service = new assessment_service();
        $this->expectException(\invalid_parameter_exception::class);
        $service->save_assessment([
            'courseid' => $course->id,
            'criterionid' => $criterionid,
            'userid' => $user->id,
            'sourcetype' => constants::SOURCE_DIRECT,
            'sourceid' => 0,
            'assessmentmode' => constants::MODE_FEEDBACK_ONLY,
            'value' => 0.8,
            'graderid' => $teacher->id,
        ]);
    }

    /**
     * Evidence count only includes released assessments.
     */
    public function test_evidence_count_only_released(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $criterionid = $this->create_criterion($course->id);

        $service = new assessment_service();
        for ($i = 0; $i < 3; $i++) {
            $id = $service->save_assessment([
                'courseid' => $course->id,
                'criterionid' => $criterionid,
                'userid' => $user->id,
                'sourcetype' => constants::SOURCE_DIRECT,
                'sourceid' => 0,
                'assessmentmode' => constants::MODE_FEEDBACK_ONLY,
                'feedback' => "Feedback $i",
                'graderid' => $teacher->id,
                'status' => constants::STATUS_DRAFT,
            ]);
            if ($i < 2) {
                $service->release_assessment($id);
            }
        }

        $this->assertSame(2, $service->get_evidence_count($criterionid, $user->id));
    }

    /**
     * Feedback count only includes released assessments with non-empty feedback.
     */
    public function test_feedback_count(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $criterionid = $this->create_criterion($course->id);

        $service = new assessment_service();
        // 2 with feedback, 1 without, 1 draft with feedback.
        foreach (
            [
            ['feedback' => 'Yes', 'status' => constants::STATUS_RELEASED],
            ['feedback' => 'Yes too', 'status' => constants::STATUS_RELEASED],
            ['feedback' => null, 'status' => constants::STATUS_RELEASED],
            ['feedback' => 'Draft feedback', 'status' => constants::STATUS_DRAFT],
            ] as $data
        ) {
            $service->save_assessment(array_merge([
                'courseid' => $course->id,
                'criterionid' => $criterionid,
                'userid' => $user->id,
                'sourcetype' => constants::SOURCE_DIRECT,
                'sourceid' => 0,
                'assessmentmode' => constants::MODE_FEEDBACK_ONLY,
                'graderid' => $teacher->id,
            ], $data));
        }

        $this->assertSame(2, $service->get_feedback_count($criterionid, $user->id));
    }

    /**
     * Create a minimal curriculum criterion for testing.
     */
    private function create_criterion(int $courseid): int {
        global $DB;
        $frameworkid = $DB->insert_record('local_crout_framework', (object)[
            'courseid' => $courseid,
            'name' => 'Framework',
            'type' => 'fp',
            'identitykey' => hash('sha256', 'test-assess-' . $courseid),
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
            'code' => 'RA1.a',
            'name' => 'Criterion A',
            'outcomeid' => 1000 + $courseid,
            'sortorder' => 0,
        ]);
    }
}
