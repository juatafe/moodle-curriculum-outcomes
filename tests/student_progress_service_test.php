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
 * Student progress service tests.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_criteriaoutcomes;

use local_criteriaoutcomes\service\assessment_service;
use local_criteriaoutcomes\service\student_progress_service;
use local_criteriaoutcomes\constants;

/**
 * Tests for student dashboard model building.
 */
final class student_progress_service_test extends \advanced_testcase {
    /**
     * for_student returns RA/CE hierarchy with criteria.
     */
    public function test_for_student_returns_hierarchy(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $criterion1 = $this->create_criterion($course->id, 'RA1.a');
        $criterion2 = $this->create_criterion($course->id, 'RA1.b');

        $service = new student_progress_service();
        $model = $service->for_student($course->id, $student->id);

        $this->assertSame((int)$course->id, (int)$model['courseid']);
        $this->assertSame((int)$student->id, (int)$model['userid']);
        $this->assertIsArray($model['parents']);
        $this->assertNotEmpty($model['parents']);
        $totalcriteria = 0;
        foreach ($model['parents'] as $parent) {
            $this->assertArrayHasKey('criteria', $parent);
            $totalcriteria += count($parent['criteria']);
        }
        $this->assertSame(2, $totalcriteria);
    }

    /**
     * Several criteria sharing an RA are never collapsed by Moodle record keys.
     */
    public function test_for_student_keeps_all_ra_criteria_grouped_once(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $criteria = $this->create_hierarchy($course->id, 'fp', [
            'RA1' => ['RA1.a', 'RA1.b', 'RA1.c'],
            'RA2' => ['RA2.a', 'RA2.b'],
        ]);
        (new assessment_service())->save_assessment([
            'courseid' => $course->id, 'criterionid' => $criteria['RA1.a'], 'userid' => $student->id,
            'sourcetype' => constants::SOURCE_DIRECT, 'sourceid' => 0,
            'assessmentmode' => constants::MODE_FEEDBACK_ONLY, 'feedback' => 'Visible feedback',
            'graderid' => $teacher->id, 'status' => constants::STATUS_RELEASED,
        ]);

        $service = new student_progress_service();
        $model = $service->for_student($course->id, $student->id);
        $this->assertDebuggingNotCalled();
        $this->assertSame(['RA1', 'RA2'], array_column($model['parents'], 'code'));
        $this->assertSame(['RA1.a', 'RA1.b', 'RA1.c'], array_column($model['parents'][0]['criteria'], 'code'));
        $this->assertSame(['RA2.a', 'RA2.b'], array_column($model['parents'][1]['criteria'], 'code'));
        $this->assertCount(5, array_merge(...array_column($model['parents'], 'criteria')));
        $this->assertSame(1, $model['parents'][0]['criteria'][0]['evidencecount']);
        $this->assertSame(1, $model['parents'][0]['criteria'][0]['feedbackcount']);
        $detail = $service->for_student_criterion($course->id, $criteria['RA1.a'], $student->id);
        $this->assertCount(1, $detail['evidence']);
        $this->assertSame('Visible feedback', $detail['evidence'][0]['feedback']);
    }

    /**
     * The same keying regression is covered for CE and decimal criterion codes.
     */
    public function test_for_student_keeps_all_ce_criteria_grouped_once(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->create_hierarchy($course->id, 'eso', [
            'CE1' => ['1.1', '1.2', '1.3'],
        ]);

        $model = (new student_progress_service())->for_student($course->id, $student->id);
        $this->assertDebuggingNotCalled();
        $this->assertCount(1, $model['parents']);
        $this->assertSame('CE1', $model['parents'][0]['code']);
        $this->assertSame(['1.1', '1.2', '1.3'], array_column($model['parents'][0]['criteria'], 'code'));
        $this->assertCount(3, $model['parents'][0]['criteria']);
    }

    /**
     * Evidence counts reflect released assessments only.
     */
    public function test_evidence_counts_reflect_released_only(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $criterion = $this->create_criterion($course->id, 'RA1.a');

        $assessmentservice = new assessment_service();
        // 2 released, 1 draft.
        for ($i = 0; $i < 3; $i++) {
            $id = $assessmentservice->save_assessment([
                'courseid' => $course->id,
                'criterionid' => $criterion,
                'userid' => $student->id,
                'sourcetype' => constants::SOURCE_DIRECT,
                'sourceid' => 0,
                'assessmentmode' => constants::MODE_FEEDBACK_ONLY,
                'feedback' => "Feedback $i",
                'graderid' => $teacher->id,
                'status' => constants::STATUS_DRAFT,
            ]);
            if ($i < 2) {
                $assessmentservice->release_assessment($id);
            }
        }

        $service = new student_progress_service();
        $model = $service->for_student($course->id, $student->id);

        $criteria = $model['parents'][0]['criteria'];
        $this->assertSame(2, $criteria[0]['evidencecount']);
        $this->assertSame(2, $criteria[0]['feedbackcount']);
    }

    /**
     * Unread count reflects unread feedback.
     */
    public function test_unread_count_in_model(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $criterion = $this->create_criterion($course->id, 'RA1.a');

        $assessmentservice = new assessment_service();
        $id = $assessmentservice->save_assessment([
            'courseid' => $course->id,
            'criterionid' => $criterion,
            'userid' => $student->id,
            'sourcetype' => constants::SOURCE_DIRECT,
            'sourceid' => 0,
            'assessmentmode' => constants::MODE_FEEDBACK_ONLY,
            'feedback' => 'Unread feedback',
            'graderid' => $teacher->id,
            'status' => constants::STATUS_DRAFT,
        ]);
        $assessmentservice->release_assessment($id);

        $service = new student_progress_service();
        $model = $service->for_student($course->id, $student->id);

        $criteria = $model['parents'][0]['criteria'];
        $this->assertSame(1, $criteria[0]['unreadcount']);
    }

    /**
     * for_student_criterion throws on invalid criterion.
     */
    public function test_for_student_criterion_invalid_criterion(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $service = new student_progress_service();
        $this->expectException(\invalid_parameter_exception::class);
        $service->for_student_criterion($course->id, 999999, $student->id);
    }

    /**
     * A criterion cannot be read through the context of another course.
     */
    public function test_for_student_criterion_rejects_criterion_from_another_course(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $criterionid = $this->create_criterion($othercourse->id);

        $this->expectException(\invalid_parameter_exception::class);
        (new student_progress_service())->for_student_criterion($course->id, $criterionid, $student->id);
    }

    /**
     * for_student_criterion returns evidence array.
     */
    public function test_for_student_criterion_returns_evidence(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $criterion = $this->create_criterion($course->id, 'RA1.a');

        $assessmentservice = new assessment_service();
        $id = $assessmentservice->save_assessment([
            'courseid' => $course->id,
            'criterionid' => $criterion,
            'userid' => $student->id,
            'sourcetype' => constants::SOURCE_DIRECT,
            'sourceid' => 0,
            'assessmentmode' => constants::MODE_FEEDBACK_ONLY,
            'feedback' => 'Good work',
            'graderid' => $teacher->id,
            'status' => constants::STATUS_DRAFT,
        ]);
        $assessmentservice->release_assessment($id);

        $service = new student_progress_service();
        $model = $service->for_student_criterion($course->id, $criterion, $student->id);

        $this->assertArrayHasKey('criterion', $model);
        $this->assertArrayHasKey('evidence', $model);
        $this->assertCount(1, $model['evidence']);
        $this->assertSame('assessment', $model['evidence'][0]['type']);
        $this->assertSame('Good work', $model['evidence'][0]['feedback']);
    }

    /**
     * Course grade visibility comes directly from the Moodle grade item.
     */
    public function test_course_grade_visibility_is_preserved(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/grade/grade_item.php');
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $gradeitem = \grade_item::fetch_course_item($course->id);
        $gradeitem->update_final_grade($student->id, 75, 'test');
        $gradeitem->hidden = 1;
        $gradeitem->update();

        $model = (new student_progress_service())->for_student($course->id, $student->id);
        $this->assertNotNull($model['coursegrade']);
        $this->assertTrue($model['coursegrade']['hidden']);
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
            'identitykey' => hash('sha256', 'progress-test-' . $courseid . $code),
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
            'outcomeid' => 0,
            'sortorder' => 0,
        ]);
    }

    /**
     * Create several real rows sharing each parent, returning criterion IDs by code.
     */
    private function create_hierarchy(int $courseid, string $type, array $structure): array {
        global $DB;
        $frameworkid = $DB->insert_record('local_crout_framework', (object)[
            'courseid' => $courseid, 'name' => 'Shared hierarchy', 'type' => $type,
            'identitykey' => hash('sha256', 'shared-progress-' . $courseid . $type),
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $result = [];
        foreach ($structure as $parentcode => $codes) {
            $parentid = $DB->insert_record('local_crout_parent', (object)[
                'frameworkid' => $frameworkid, 'code' => $parentcode, 'name' => 'Parent ' . $parentcode,
                'type' => $type === 'fp' ? 'ra' : 'ce', 'sortorder' => count($result),
            ]);
            foreach (array_values($codes) as $sortorder => $code) {
                $result[$code] = $DB->insert_record('local_crout_criterion', (object)[
                    'parentid' => $parentid, 'code' => $code, 'name' => 'Criterion ' . $code,
                    'outcomeid' => 0, 'sortorder' => $sortorder,
                ]);
            }
        }
        return $result;
    }
}
