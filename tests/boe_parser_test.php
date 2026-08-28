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

use local_criteriaoutcomes\provider\boe_parser;
use local_criteriaoutcomes\provider\boe_provider;

/**
 * Deterministic BOE curriculum parser tests.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class boe_parser_test extends \advanced_testcase {
    /**
     * FP module, RA and lettered criteria retain order and identity.
     */
    public function test_fp_module_structure(): void {
        $result = (new boe_parser())->parse_fp($this->fixture('fp_block.xml'), $this->source());
        $this->assertCount(1, $result);
        $curriculum = $result[0];
        $this->assertSame('0371', $curriculum['metadata']['modulecode']);
        $this->assertSame(boe_parser::FP_VERSION, $curriculum['metadata']['parserversion']);
        $this->assertSame(['RA1', 'RA2'], array_column($curriculum['parents'], 'code'));
        $this->assertSame(['RA1.a', 'RA1.b', 'RA1.c'], array_column($curriculum['parents'][0]['criteria'], 'code'));
        $this->assertSame(['RA2.a', 'RA2.b'], array_column($curriculum['parents'][1]['criteria'], 'code'));
        $this->assertSame('RA1', $curriculum['parents'][0]['criteria'][0]['parentcode']);
    }

    /**
     * FP modules retain their official qualification boundary.
     */
    public function test_fp_multi_title_structure(): void {
        $result = (new boe_parser())->parse_fp($this->fixture('fp_multi_title.xml'), $this->source());
        $this->assertCount(4, $result);
        $this->assertSame(
            ['Título A', 'Título A', 'Título B', 'Título B'],
            array_column(array_column($result, 'metadata'), 'qualification')
        );
        $selector = new \local_criteriaoutcomes\service\curriculum_selection_service();
        $groups = $selector->groups($result);
        $this->assertSame([0 => '1001 — Módulo A uno', 1 => '1002 — Módulo A dos'], $groups['Título A']);
        $this->assertSame([2 => '2001 — Módulo B uno', 3 => '2002 — Módulo B dos'], $groups['Título B']);
        $this->assertSame([0, 1], array_keys($selector->filter($result, 'Título A')));
    }

    /**
     * A demonstrated cross-title module-code collision gains deterministic context.
     */
    public function test_fp_duplicate_module_code_is_disambiguated_by_title(): void {
        $result = (new boe_parser())->parse_fp($this->fixture('fp_duplicate_code.xml'), $this->source());
        $keys = array_column(array_column($result, 'metadata'), 'curriculumkey');
        $this->assertCount(2, array_unique($keys));
        $this->assertStringStartsWith('1001:', $keys[0]);
        $this->assertStringStartsWith('1001:', $keys[1]);
    }

    /**
     * ESO course bands group subjects without changing parser identities.
     */
    public function test_eso_selection_groups_subjects_by_course_band(): void {
        $curricula = [
            ['metadata' => ['curriculumtype' => 'eso',
                'subjectmodule' => 'Tecnología y Digitalización — Cursos de primero a tercero']],
            ['metadata' => ['curriculumtype' => 'eso', 'subjectmodule' => 'Latín — Cuarto curso']],
        ];
        $groups = (new \local_criteriaoutcomes\service\curriculum_selection_service())->groups($curricula);
        $this->assertSame([0 => 'Tecnología y Digitalización'], $groups['Cursos de primero a tercero']);
        $this->assertSame([1 => 'Latín'], $groups['Cuarto curso']);
    }

    /**
     * ESO specific competences are not guessed beyond explicit decimal criteria.
     */
    public function test_eso_subject_structure(): void {
        $result = (new boe_parser())->parse_eso_bach($this->fixture('eso_block.xml'), $this->source(), 'eso');
        $this->assertCount(1, $result);
        $curriculum = $result[0];
        $this->assertSame(['CE1', 'CE2'], array_column($curriculum['parents'], 'code'));
        $this->assertSame(['1.1', '1.2'], array_column($curriculum['parents'][0]['criteria'], 'code'));
        $this->assertSame(['2.1', '2.2'], array_column($curriculum['parents'][1]['criteria'], 'code'));
        $this->assertStringContainsString('Enseñanzas mínimas', $curriculum['metadata']['provenance']);
    }

    /**
     * A CE uses the semantic competency text, never its first criterion text.
     */
    public function test_eso_parent_uses_real_competency_text(): void {
        $result = (new boe_parser())->parse_eso_bach(
            $this->fixture('eso_semantic_block.xml'),
            $this->source(),
            'eso'
        );
        $parent = $result[0]['parents'][0];
        $this->assertSame('CE1', $parent['code']);
        $this->assertSame('Texto real de competencia específica', $parent['name']);
        $this->assertSame('1.1', $parent['criteria'][0]['code']);
        $this->assertSame('Texto criterio uno', $parent['criteria'][0]['name']);
        $this->assertNotSame($parent['name'], $parent['criteria'][0]['name']);
        $this->assertSame('CE1', $parent['criteria'][0]['parentcode']);
    }

    /**
     * Criteria without a deterministically recoverable competency are rejected.
     */
    public function test_eso_criteria_without_competency_are_rejected(): void {
        $this->expectException(\UnexpectedValueException::class);
        (new boe_parser())->parse_eso_bach(
            $this->fixture('eso_criteria_without_competency.xml'),
            $this->source(),
            'eso'
        );
    }

    /**
     * The final FP criterion stops before later module sections.
     */
    public function test_fp_heading_and_final_criterion_boundaries(): void {
        $result = (new boe_parser())->parse_fp($this->fixture('fp_boundary.xml'), $this->source());
        $parent = $result[0]['parents'][0];
        $lastcriterion = $parent['criteria'][1];
        $this->assertSame('Selecciona elementos para una instalación.', $parent['name']);
        $this->assertStringNotContainsString('Criterios de evaluación', $parent['name']);
        $this->assertCount(2, $parent['criteria']);
        $this->assertSame('RA1.b', $lastcriterion['code']);
        $this->assertSame('Criterio B.', $lastcriterion['name']);
        $this->assertStringNotContainsString('Duración', $lastcriterion['name']);
        $this->assertStringNotContainsString('Contenidos básicos', $lastcriterion['name']);
        $this->assertStringNotContainsString('Orientaciones pedagógicas', $lastcriterion['name']);
    }

    /**
     * The same deterministic parser covers clear Bachillerato structures.
     */
    public function test_bachillerato_subject_structure(): void {
        $result = (new boe_parser())->parse_eso_bach($this->fixture('eso_block.xml'), $this->source(), 'bach');
        $this->assertSame('bach', $result[0]['metadata']['curriculumtype']);
        $this->assertSame('CE2', $result[0]['parents'][1]['code']);
    }

    /**
     * Ambiguous or incomplete text is rejected.
     */
    public function test_ambiguous_text_is_rejected(): void {
        $this->expectException(\UnexpectedValueException::class);
        (new boe_parser())->parse_fp($this->fixture('malformed.xml'), $this->source());
    }

    /**
     * Family detection is conservative and never defaults ambiguously to FP.
     */
    public function test_family_detection_is_conservative(): void {
        $this->assertSame('eso', boe_provider::detect_family([
            'titulo' => 'Enseñanzas mínimas de la Educación Secundaria Obligatoria',
        ]));
        $this->assertSame('bach', boe_provider::detect_family(['titulo' => 'Ordenación del Bachillerato']));
        $this->assertSame('fp', boe_provider::detect_family(['titulo' => 'Título de Técnico en Sistemas']));
        $this->assertNull(boe_provider::detect_family(['titulo' => 'Norma curricular sin etapa inequívoca']));
    }

    /**
     * Load one deliberately small derived fixture.
     */
    private function fixture(string $name): string {
        return file_get_contents(__DIR__ . '/fixtures/boe/' . $name);
    }

    /**
     * Common official provenance.
     */
    private function source(): array {
        return [
            'sourceid' => 'BOE-A-2022-4975',
            'sourcename' => 'Fixture normativa educativa',
            'sourceref' => 'https://www.boe.es/datosabiertos/api/legislacion-consolidada/id/BOE-A-2022-4975',
            'sourcelastupdate' => '20260801T120000Z',
            'retrievedat' => 123456789,
            'provenance' => 'Texto consolidado de carácter informativo.',
        ];
    }
}
