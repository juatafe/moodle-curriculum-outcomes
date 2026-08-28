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
 * JSON provider tests.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_criteriaoutcomes;

/**
 * Tests parser validation and legacy compatibility.
 */
final class json_provider_test extends \advanced_testcase {
    public function test_parses_current_and_legacy_codes(): void {
        $provider = new \local_criteriaoutcomes\provider\json_provider();
        $data = $provider->parse(json_encode(['metadata' => ['name' => 'SMR', 'type' => 'fp'], 'resultados' => [[
            'nombre' => 'RA1: Configura sistemas', 'peso' => null,
            'criterios' => [['nombre' => 'RA1.a: Instala el sistema', 'peso' => 25]],
        ]]]));
        $this->assertSame('RA1', $data['parents'][0]['code']);
        $this->assertSame('RA1.a', $data['parents'][0]['criteria'][0]['code']);
        $this->assertSame(25.0, $data['parents'][0]['criteria'][0]['weight']);
    }

    public function test_rejects_duplicate_codes(): void {
        $provider = new \local_criteriaoutcomes\provider\json_provider();
        $this->expectException(\InvalidArgumentException::class);
        $provider->parse(json_encode(['resultados' => [[
            'codigo' => 'RA1', 'nombre' => 'One', 'criterios' => [
                ['codigo' => 'RA1.a', 'nombre' => 'A'], ['codigo' => 'ra1.A', 'nombre' => 'B'],
            ],
        ]]]));
    }

    public function test_weights_are_optional(): void {
        $provider = new \local_criteriaoutcomes\provider\json_provider();
        $data = $provider->parse('{"resultados":[{"nombre":"RA1: One","criterios":[{"nombre":"RA1.a: A"}]}]}');
        $this->assertNull($data['parents'][0]['weight']);
        $this->assertNull($data['parents'][0]['criteria'][0]['weight']);
    }
}
