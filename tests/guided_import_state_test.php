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

use local_criteriaoutcomes\service\guided_import_state;

/**
 * Guided import transition regressions.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class guided_import_state_test extends \advanced_testcase {
    public function test_back_preserves_choices_and_change_invalidates_downstream_state(): void {
        $curricula = [
            0 => ['metadata' => ['curriculumtype' => 'fp', 'qualification' => 'Title A',
                'modulecode' => '1001', 'subjectmodule' => 'Module A']],
            1 => ['metadata' => ['curriculumtype' => 'fp', 'qualification' => 'Title B',
                'modulecode' => '2001', 'subjectmodule' => 'Module B']],
        ];
        $service = new guided_import_state();
        $state = $service->select_group($service->create($curricula, 'BOE-A-2026-1', 'fp', 7), 'Title A');
        $state = $service->select_curriculum($state, 0);
        $state = $service->select_valuation($state, 'achievement', 12);
        $state['selectedsourcekeys'] = ['criterion-a'];

        $this->assertSame('Title A', $state['selectiongroup']);
        $this->assertSame(0, $state['curriculumindex']);
        $this->assertSame(12, $state['scaleid']);

        $changed = $service->select_group($state, 'Title B');
        $this->assertNull($changed['curriculumindex']);
        $this->assertSame('', $changed['valuation']);
        $this->assertSame(0, $changed['scaleid']);
        $this->assertNull($changed['selectedsourcekeys']);
    }

    public function test_changing_curriculum_invalidates_preview_but_reselecting_it_does_not(): void {
        $curricula = [
            0 => ['metadata' => ['curriculumtype' => 'fp', 'qualification' => 'Title A',
                'modulecode' => '1001', 'subjectmodule' => 'Module A']],
            1 => ['metadata' => ['curriculumtype' => 'fp', 'qualification' => 'Title A',
                'modulecode' => '1002', 'subjectmodule' => 'Module B']],
        ];
        $service = new guided_import_state();
        $state = $service->select_curriculum(
            $service->select_group($service->create($curricula, 'BOE-A-2026-1', 'fp', 7), 'Title A'),
            0
        );
        $state = $service->select_valuation($state, 'numeric', 15);
        $state['selectedsourcekeys'] = ['criterion-a'];
        $this->assertSame($state, $service->select_curriculum($state, 0));

        $changed = $service->select_curriculum($state, 1);
        $this->assertSame(1, $changed['curriculumindex']);
        $this->assertSame('', $changed['valuation']);
        $this->assertNull($changed['selectedsourcekeys']);
    }

    public function test_state_cannot_be_used_for_another_course(): void {
        $service = new guided_import_state();
        $state = $service->create([], 'BOE-A-2026-1', 'fp', 7);
        $this->assertSame($state, $service->require_course($state, 7));
        $this->expectException(\invalid_parameter_exception::class);
        $service->require_course($state, 8);
    }
}
