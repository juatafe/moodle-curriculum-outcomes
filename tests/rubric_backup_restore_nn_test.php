<?php
// phpcs:ignoreFile
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

use local_criteriaoutcomes\service\rubric_mapping_service;

/**
 * Backup/restore N:N for rubric mappings.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rubric_backup_restore_nn_test extends \advanced_testcase {
    public function test_backup_restore_rubric_nn(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        $this->resetAfterTest();
        $CFG->enableoutcomes = 1;
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $assignment = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $scale = $this->getDataGenerator()->create_scale(['courseid' => $course->id, 'scale' => 'A,B,C']);
        $curriculum = (new \local_criteriaoutcomes\provider\json_provider())->parse(json_encode([
            'metadata' => ['name' => 'Test', 'type' => 'fp'],
            'resultados' => [[
                'codigo' => 'RA1', 'nombre' => 'Parent',
                'criterios' => [
                    ['codigo' => 'RA1.a', 'nombre' => 'C1'],
                    ['codigo' => 'RA1.b', 'nombre' => 'C2'],
                ],
            ]],
        ]));
        (new \local_criteriaoutcomes\service\import_service())->import($course->id, $scale->id, $curriculum);
        $criteria = $DB->get_records_menu('local_crout_criterion', [], '', 'code,id');
        $gen = $this->getDataGenerator()->get_plugin_generator('gradingform_rubric');
        $controller = $gen->create_instance(
            \context_module::instance($assignment->cmid),
            'mod_assign',
            'submissions',
            'Rubric N:N',
            'desc',
            [
                'Dim A' => ['L1' => 0, 'L2' => 1],
                'Dim B' => ['L1' => 0, 'L2' => 1],
            ]
        );
        $def = $controller->get_definition();
        $dimids = array_keys($def->rubric_criteria);
        $svc = new rubric_mapping_service();
        $svc->replace_mappings_for_rubric_criterion($course->id, $dimids[0], [$criteria['RA1.a'], $criteria['RA1.b']]);
        $svc->replace_mappings_for_rubric_criterion($course->id, $dimids[1], [$criteria['RA1.b']]);
        $this->assertCount(3, $DB->get_records('local_crout_rubricmap', ['courseid' => $course->id]));

        $backupdir = make_backup_temp_directory('');
        $controllerb = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            get_admin()->id
        );
        $controllerb->get_plan()->get_setting('users')->set_value(false);
        $controllerb->execute_plan();
        $results = $controllerb->get_results();
        $packer = get_file_packer('application/vnd.moodle.backup');
        $extractpath = $backupdir . '/crout_restore_nn';
        $results['backup_destination']->extract_to_pathname($packer, $extractpath);
        $controllerb->destroy();
        $categoryid = $DB->get_field_sql('SELECT MIN(id) FROM {course_categories}');
        $newcourseid = \restore_dbops::create_new_course('Restored NN', 'restored-nn-' . $course->id, $categoryid);
        $restore = new \restore_controller(
            'crout_restore_nn',
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            get_admin()->id,
            \backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($restore->execute_precheck());
        $restore->execute_plan();
        $restore->destroy();
        $this->assertCount(3, $DB->get_records('local_crout_rubricmap', ['courseid' => $newcourseid]));
        $newmaps = $DB->get_records('local_crout_rubricmap', ['courseid' => $newcourseid]);
        $byrubric = [];
        foreach ($newmaps as $m) {
            $byrubric[$m->rubriccriterionid][] = $m->curriculumcriterionid;
        }
        $this->assertCount(2, $byrubric);
        $counts = array_map('count', $byrubric);
        sort($counts);
        $this->assertSame([1, 2], $counts);
    }
}
