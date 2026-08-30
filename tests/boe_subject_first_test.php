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

use local_criteriaoutcomes\service\curriculum_selection_service;
use local_criteriaoutcomes\service\guided_import_state;

/**
 * BOE subject-first regression tests.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class boe_subject_first_test extends \advanced_testcase {
    public function test_subjects_unique(): void {
        $svc = new curriculum_selection_service();
        $curricula = [
            ['metadata' => ['curriculumtype' => 'eso', 'subjectmodule' => 'Matemáticas — Cursos de primero a tercero', 'curriculumkey' => 'eso:Matemáticas:band1']],
            ['metadata' => ['curriculumtype' => 'eso', 'subjectmodule' => 'Matemáticas — Cuarto curso', 'curriculumkey' => 'eso:Matemáticas:band2']],
            ['metadata' => ['curriculumtype' => 'eso', 'subjectmodule' => 'Latín — Cuarto curso', 'curriculumkey' => 'eso:Latín:band2']],
        ];
        $subjects = $svc->subjects($curricula);
        $this->assertSame(['Latín', 'Matemáticas'], $subjects);
        $this->assertCount(2, $subjects);
    }

    public function test_two_variants_same_subject_one_subject_entry(): void {
        $svc = new curriculum_selection_service();
        $curricula = [
            ['metadata' => ['curriculumtype' => 'eso', 'subjectmodule' => 'Biología y Geología — Cursos de primero a tercero']],
            ['metadata' => ['curriculumtype' => 'eso', 'subjectmodule' => 'Biología y Geología — Cuarto curso']],
        ];
        $this->assertSame(['Biología y Geología'], $svc->subjects($curricula));
        $this->assertCount(2, $svc->variants_for_subject($curricula, 'Biología y Geología'));
    }

    public function test_variants_for_subject_only_belonging(): void {
        $svc = new curriculum_selection_service();
        $curricula = [
            ['metadata' => ['curriculumtype' => 'eso', 'subjectmodule' => 'Matemáticas — Cursos de primero a tercero']],
            ['metadata' => ['curriculumtype' => 'eso', 'subjectmodule' => 'Matemáticas — Cuarto curso']],
            ['metadata' => ['curriculumtype' => 'eso', 'subjectmodule' => 'Física y Química — Cursos de primero a tercero']],
        ];
        $this->assertSame(['Cuarto curso', 'Cursos de primero a tercero'], $svc->variants_for_subject($curricula, 'Matemáticas'));
        $this->assertSame(['Cursos de primero a tercero'], $svc->variants_for_subject($curricula, 'Física y Química'));
        $this->assertSame([], $svc->variants_for_subject($curricula, 'Inexistente'));
    }

    public function test_single_variant_auto_select(): void {
        $curricula = [
            0 => ['metadata' => ['curriculumtype' => 'eso', 'subjectmodule' => 'Latín — Cuarto curso']],
        ];
        $svc = new guided_import_state();
        $state = $svc->create($curricula, 'BOE-A-2022-4975', 'eso', 5);
        $state = $svc->select_group($state, 'Latín');
        $state = $svc->select_curriculum($state, 0);
        $this->assertSame('Latín', $state['subject']);
        $this->assertSame('Cuarto curso', $state['variant']);
    }

    public function test_multiple_variants_require_explicit(): void {
        $curricula = [
            0 => ['metadata' => ['curriculumtype' => 'eso', 'subjectmodule' => 'Matemáticas — Cursos de primero a tercero']],
            1 => ['metadata' => ['curriculumtype' => 'eso', 'subjectmodule' => 'Matemáticas — Cuarto curso']],
        ];
        $svc = new curriculum_selection_service();
        $this->assertCount(2, $svc->variants_for_subject($curricula, 'Matemáticas'));
        $state = (new guided_import_state())->create($curricula, 'BOE-A-2022-4975', 'eso', 5);
        $state = (new guided_import_state())->select_group($state, 'Matemáticas');
        $this->assertSame('', $state['variant']);
        $this->assertNull($state['curriculumindex']);
    }

    public function test_switching_subject_invalidates_downstream(): void {
        $curricula = [
            0 => ['metadata' => ['curriculumtype' => 'eso', 'subjectmodule' => 'Matemáticas — Cursos de primero a tercero']],
            1 => ['metadata' => ['curriculumtype' => 'eso', 'subjectmodule' => 'Latín — Cuarto curso']],
        ];
        $svc = new guided_import_state();
        $state = $svc->create($curricula, 'BOE-A-2022-4975', 'eso', 5);
        $state = $svc->select_group($state, 'Matemáticas');
        $state['curriculumindex'] = 0;
        $state['valuation'] = 'achievement';
        $state['scaleid'] = 10;
        $state['selectedsourcekeys'] = ['a'];
        $changed = $svc->select_group($state, 'Latín');
        $this->assertNull($changed['curriculumindex']);
        $this->assertSame('', $changed['valuation']);
        $this->assertSame(0, $changed['scaleid']);
        $this->assertNull($changed['selectedsourcekeys']);
        $this->assertSame('Latín', $changed['subject']);
    }

    public function test_fp_qualification_unchanged(): void {
        $curricula = [
            ['metadata' => ['curriculumtype' => 'fp', 'qualification' => 'Title A', 'modulecode' => '1001', 'subjectmodule' => 'Module A']],
            ['metadata' => ['curriculumtype' => 'fp', 'qualification' => 'Title B', 'modulecode' => '2001', 'subjectmodule' => 'Module B']],
        ];
        $svc = new curriculum_selection_service();
        $groups = $svc->groups($curricula);
        $this->assertArrayHasKey('Title A', $groups);
        $this->assertArrayHasKey('Title B', $groups);
    }

    public function test_curriculum_indexes_neither_lost_nor_duplicated(): void {
        $curricula = [
            0 => ['metadata' => ['curriculumtype' => 'eso', 'subjectmodule' => 'Matemáticas — Cursos de primero a tercero', 'curriculumkey' => 'k0', 'sourceid' => 's0']],
            1 => ['metadata' => ['curriculumtype' => 'eso', 'subjectmodule' => 'Matemáticas — Cuarto curso', 'curriculumkey' => 'k1', 'sourceid' => 's1']],
            2 => ['metadata' => ['curriculumtype' => 'eso', 'subjectmodule' => 'Latín — Cuarto curso', 'curriculumkey' => 'k2', 'sourceid' => 's2']],
        ];
        $svc = new curriculum_selection_service();
        $subjects = $svc->subjects($curricula);
        $all = [];
        foreach ($subjects as $subj) {
            foreach ($svc->variants_for_subject($curricula, $subj) as $variant) {
                $all[] = $subj . '|' . $variant;
            }
            if (empty($svc->variants_for_subject($curricula, $subj))) {
                $all[] = $subj;
            }
        }
        $this->assertCount(3, $curricula);
        $keys = array_column(array_column($curricula, 'metadata'), 'curriculumkey');
        $this->assertCount(3, array_unique($keys));
    }

    public function test_curriculumkey_sourcekey_identity_unchanged(): void {
        $curricula = [
            ['metadata' => ['curriculumtype' => 'eso', 'subjectmodule' => 'Tech — Cursos de primero a tercero', 'curriculumkey' => 'eso:Tech:band1', 'sourceid' => 'BOE-A-1']],
        ];
        $svc = new curriculum_selection_service();
        $this->assertSame('Tech', $svc->subjects($curricula)[0]);
        $this->assertSame('eso:Tech:band1', $curricula[0]['metadata']['curriculumkey']);
    }
}
