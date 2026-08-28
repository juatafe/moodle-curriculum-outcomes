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
 * Judgement and feedback service tests.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_criteriaoutcomes;

use local_criteriaoutcomes\service\assessment_service;
use local_criteriaoutcomes\service\feedback_service;
use local_criteriaoutcomes\service\judgement_service;
use local_criteriaoutcomes\constants;

/**
 * Tests for current judgement and feedback read tracking.
 */
final class judgement_feedback_test extends \advanced_testcase {
    /**
     * Save and retrieve a current judgement.
     */
    public function test_save_and_get_judgement(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $criterionid = $this->create_criterion($course->id);

        $service = new judgement_service();
        $id = $service->save_judgement(
            $course->id,
            $criterionid,
            $student->id,
            3,
            'Las últimas prácticas muestran trabajo autónomo.',
            $teacher->id
        );
        $this->assertGreaterThan(0, $id);

        $judgement = $service->get_judgement($criterionid, $student->id);
        $this->assertNotNull($judgement);
        $this->assertSame(3, (int)$judgement->scalevalue);
        $this->assertSame('Las últimas prácticas muestran trabajo autónomo.', $judgement->comment);
    }

    /**
     * Judgement is idempotent (update on duplicate).
     */
    public function test_judgement_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $criterionid = $this->create_criterion($course->id);

        $service = new judgement_service();
        $id1 = $service->save_judgement($course->id, $criterionid, $student->id, 2, 'First', $teacher->id);
        $id2 = $service->save_judgement($course->id, $criterionid, $student->id, 3, 'Updated', $teacher->id);

        $this->assertSame($id1, $id2);
        $judgement = $service->get_judgement($criterionid, $student->id);
        $this->assertSame(3, (int)$judgement->scalevalue);
        $this->assertSame('Updated', $judgement->comment);
    }

    /**
     * Judgement with NULL scalevalue and comment.
     */
    public function test_judgement_allows_null_values(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $criterionid = $this->create_criterion($course->id);

        $service = new judgement_service();
        $id = $service->save_judgement($course->id, $criterionid, $student->id, null, null, $teacher->id);
        $judgement = $service->get_judgement($criterionid, $student->id);
        $this->assertNull($judgement->scalevalue);
        $this->assertNull($judgement->comment);
    }

    /**
     * Feedback read tracking marks as read and counts unread.
     */
    public function test_feedback_read_tracking(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $criterionid = $this->create_criterion($course->id);

        $assessmentservice = new assessment_service();

        // Create 3 released assessments with feedback.
        for ($i = 0; $i < 3; $i++) {
            $id = $assessmentservice->save_assessment([
                'courseid' => $course->id,
                'criterionid' => $criterionid,
                'userid' => $student->id,
                'sourcetype' => constants::SOURCE_DIRECT,
                'sourceid' => 0,
                'assessmentmode' => constants::MODE_FEEDBACK_ONLY,
                'feedback' => "Feedback $i",
                'graderid' => $teacher->id,
                'status' => constants::STATUS_DRAFT,
            ]);
            $assessmentservice->release_assessment($id);
        }

        $feedbackservice = new feedback_service();

        // All 3 should be unread.
        $unread = $feedbackservice->get_unread_for_criterion($criterionid, $student->id);
        $this->assertSame(3, $unread);

        // Mark one as read.
        $assessments = $assessmentservice->get_released_assessments($course->id, $criterionid, $student->id);
        $first = reset($assessments);
        $feedbackservice->mark_read($first->id, $student->id);

        $unread = $feedbackservice->get_unread_for_criterion($criterionid, $student->id);
        $this->assertSame(2, $unread);

        // Mark all as read.
        $feedbackservice->mark_criterion_read($criterionid, $student->id);
        $unread = $feedbackservice->get_unread_for_criterion($criterionid, $student->id);
        $this->assertSame(0, $unread);
    }

    /**
     * Course-level unread count aggregates across criteria.
     */
    public function test_unread_count_course_level(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $criterion1 = $this->create_criterion($course->id, 'RA1.a');
        $criterion2 = $this->create_criterion($course->id, 'RA1.b');

        $assessmentservice = new assessment_service();
        $feedbackservice = new feedback_service();

        // 2 for criterion 1, 1 for criterion 2.
        foreach ([$criterion1, $criterion1, $criterion2] as $cid) {
            $id = $assessmentservice->save_assessment([
                'courseid' => $course->id,
                'criterionid' => $cid,
                'userid' => $student->id,
                'sourcetype' => constants::SOURCE_DIRECT,
                'sourceid' => 0,
                'assessmentmode' => constants::MODE_FEEDBACK_ONLY,
                'feedback' => 'Feedback',
                'graderid' => $teacher->id,
                'status' => constants::STATUS_DRAFT,
            ]);
            $assessmentservice->release_assessment($id);
        }

        $this->assertSame(3, $feedbackservice->get_unread_count($course->id, $student->id));
    }

    /**
     * Judgements for student returns indexed by criterionid.
     */
    public function test_get_judgements_for_student(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $c1 = $this->create_criterion($course->id, 'RA1.a');
        $c2 = $this->create_criterion($course->id, 'RA1.b');

        $service = new judgement_service();
        $service->save_judgement($course->id, $c1, $student->id, 3, 'Good', $teacher->id);
        $service->save_judgement($course->id, $c2, $student->id, 2, 'In progress', $teacher->id);

        $judgements = $service->get_judgements_for_student($course->id, $student->id);
        $this->assertCount(2, $judgements);
        $this->assertArrayHasKey($c1, $judgements);
        $this->assertArrayHasKey($c2, $judgements);
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
            'identitykey' => hash('sha256', 'jf-test-' . $courseid . $code),
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
            'outcomeid' => 3000 + $courseid + crc32($code),
            'sortorder' => 0,
        ]);
    }
}
