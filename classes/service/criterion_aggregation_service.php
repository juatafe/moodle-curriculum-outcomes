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

namespace local_criteriaoutcomes\service;

/**
 * Pure criterion fraction aggregation.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class criterion_aggregation_service {
    /**
     * Arithmetic mean.
     */
    public const MEAN = 'mean';

    /**
     * Explicit mapping-weight mean.
     */
    public const WEIGHTED_MEAN = 'weightedmean';

    /**
     * Aggregate question evidence without database access.
     *
     * @param array $items Each item has fraction, weight and optionally pending.
     * @param string $method Aggregation method.
     * @return array Structured status, value and formula inputs.
     */
    public function aggregate(array $items, string $method): array {
        if (!in_array($method, [self::MEAN, self::WEIGHTED_MEAN], true)) {
            throw new \invalid_parameter_exception('Unknown aggregation method.');
        }
        if (!$items) {
            return ['status' => 'empty', 'value' => null, 'numerator' => null, 'denominator' => null];
        }
        foreach ($items as $item) {
            if (!empty($item['pending']) || !array_key_exists('fraction', $item) || $item['fraction'] === null) {
                return ['status' => 'pending', 'value' => null, 'numerator' => null, 'denominator' => null];
            }
            if (!is_numeric($item['fraction'])) {
                throw new \invalid_parameter_exception('Fraction must be numeric.');
            }
            if (
                $method === self::WEIGHTED_MEAN &&
                    (!isset($item['weight']) || !is_numeric($item['weight']) || (float)$item['weight'] <= 0)
            ) {
                throw new \invalid_parameter_exception('Weight must be a positive number.');
            }
        }
        $numerator = 0.0;
        $denominator = 0.0;
        foreach ($items as $item) {
            $weight = $method === self::MEAN ? 1.0 : (float)$item['weight'];
            $numerator += (float)$item['fraction'] * $weight;
            $denominator += $weight;
        }
        return [
            'status' => 'complete',
            'value' => $numerator / $denominator,
            'numerator' => $numerator,
            'denominator' => $denominator,
        ];
    }
}
