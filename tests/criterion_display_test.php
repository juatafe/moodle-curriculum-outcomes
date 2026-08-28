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

/**
 * Tests canonical native Outcome labels and the ownership-safe migration.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class criterion_display_test extends \advanced_testcase {
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->libdir . '/grade/grade_outcome.php');
        require_once($CFG->libdir . '/grade/grade_item.php');
        require_once($CFG->libdir . '/grade/grade_grade.php');
    }

    public function test_owned_outcome_migration_is_idempotent_and_preserves_relations(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $scale = $this->getDataGenerator()->create_scale(['courseid' => $course->id]);
        $outcome = new \grade_outcome();
        $outcome->courseid = $course->id;
        $outcome->shortname = 'RA1.a';
        $outcome->fullname = 'Instal·la el sistema operatiu';
        $outcome->scaleid = $scale->id;
        $outcome->insert('local_criteriaoutcomes');
        $frameworkid = $DB->insert_record('local_crout_framework', (object)[
            'courseid' => $course->id, 'name' => 'Module', 'type' => 'fp', 'source' => 'json',
            'sourceid' => 'test', 'version' => '1', 'checksum' => hash('sha256', 'test'),
            'identitykey' => hash('sha256', 'identity'), 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $parentid = $DB->insert_record('local_crout_parent', (object)[
            'frameworkid' => $frameworkid, 'code' => 'RA1', 'name' => 'Configure', 'type' => 'ra',
            'sortorder' => 1,
        ]);
        $criterionid = $DB->insert_record('local_crout_criterion', (object)[
            'parentid' => $parentid, 'outcomeid' => $outcome->id, 'code' => 'RA1.a',
            'name' => 'Instal·la el sistema operatiu', 'sortorder' => 1, 'outcomeowned' => 1,
        ]);
        $gradeitem = new \grade_item((object)[
            'courseid' => $course->id, 'itemtype' => 'manual', 'itemname' => 'Evidence',
            'outcomeid' => $outcome->id, 'gradetype' => GRADE_TYPE_SCALE, 'scaleid' => $scale->id,
        ], false);
        $gradeitem->insert('local_criteriaoutcomes');
        $student = $this->getDataGenerator()->create_user();
        $gradeitem->update_final_grade($student->id, 1, 'local_criteriaoutcomes');
        $grade = \grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $student->id]);

        $this->assertSame(1, criterion_display::migrate_owned_outcomes());
        $this->assertSame(0, criterion_display::migrate_owned_outcomes());
        $migrated = \grade_outcome::fetch(['id' => $outcome->id]);
        $this->assertSame('RA1.a — Instal·la el sistema operatiu', $migrated->fullname);
        $this->assertSame($outcome->id, $migrated->id);
        $this->assertTrue($DB->record_exists('grade_items', ['id' => $gradeitem->id, 'outcomeid' => $outcome->id]));
        $preservedgrade = \grade_grade::fetch(['id' => $grade->id]);
        $this->assertSame($grade->id, $preservedgrade->id);
        $this->assertEquals($grade->finalgrade, $preservedgrade->finalgrade);
        $this->assertTrue($DB->record_exists('local_crout_criterion', [
            'id' => $criterionid, 'outcomeid' => $outcome->id,
        ]));
    }

    public function test_external_outcome_is_not_modified(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $scale = $this->getDataGenerator()->create_scale(['courseid' => $course->id]);
        $outcome = new \grade_outcome();
        $outcome->courseid = $course->id;
        $outcome->shortname = '1.1';
        $outcome->fullname = 'External label';
        $outcome->scaleid = $scale->id;
        $outcome->insert('manual');
        $frameworkid = $DB->insert_record('local_crout_framework', (object)[
            'courseid' => $course->id, 'name' => 'Subject', 'type' => 'eso', 'source' => 'json',
            'sourceid' => 'test', 'version' => '1', 'checksum' => hash('sha256', 'test'),
            'identitykey' => hash('sha256', 'external'), 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $parentid = $DB->insert_record('local_crout_parent', (object)[
            'frameworkid' => $frameworkid, 'code' => 'CE1', 'name' => 'Competence', 'type' => 'ce',
            'sortorder' => 1,
        ]);
        $DB->insert_record('local_crout_criterion', (object)[
            'parentid' => $parentid, 'outcomeid' => $outcome->id, 'code' => '1.1',
            'name' => 'Criterion text', 'sortorder' => 1, 'outcomeowned' => 0,
        ]);

        $this->assertSame(0, criterion_display::migrate_owned_outcomes());
        $this->assertSame('External label', \grade_outcome::fetch(['id' => $outcome->id])->fullname);
    }
}
