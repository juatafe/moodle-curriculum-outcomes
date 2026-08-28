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
 * Plugin language catalog completeness tests.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class language_catalog_test extends \advanced_testcase {
    /**
     * Every shipped language contains the canonical English key set.
     */
    public function test_all_language_catalogs_are_complete(): void {
        $english = $this->catalog('en');
        foreach (['es', 'ca', 'eu', 'gl'] as $language) {
            $catalog = $this->catalog($language);
            $this->assertSame([], array_values(array_diff(array_keys($english), array_keys($catalog))), $language);
            $this->assertSame([], array_values(array_diff(array_keys($catalog), array_keys($english))), $language);
            $this->assertStringNotContainsString('[[', implode("\n", $catalog), $language);
        }
    }

    /**
     * Recommended achievement values are correct in every shipped language.
     */
    public function test_scale_level_translations(): void {
        $expected = [
            'en' => ['Insufficient', 'Sufficient', 'Good', 'Very good', 'Excellent'],
            'es' => ['Insuficiente', 'Suficiente', 'Bien', 'Notable', 'Excelente'],
            'ca' => ['Insuficient', 'Suficient', 'Bé', 'Notable', 'Excel·lent'],
            'eu' => ['Ez nahikoa', 'Nahikoa', 'Ongi', 'Oso ongi', 'Bikain'],
            'gl' => ['Insuficiente', 'Suficiente', 'Ben', 'Notable', 'Excelente'],
        ];
        $keys = ['scalelevelinsufficient', 'scalelevelsufficient', 'scalelevelgood',
            'scalelevelverygood', 'scalelevelexcellent'];
        foreach ($expected as $language => $values) {
            $catalog = $this->catalog($language);
            $this->assertSame($values, array_map(static fn(string $key): string => $catalog[$key], $keys));
        }
    }

    /**
     * Load one plugin catalog without requiring an installed language pack.
     */
    private function catalog(string $language): array {
        $string = [];
        include(__DIR__ . '/../lang/' . $language . '/local_criteriaoutcomes.php');
        return $string;
    }
}
