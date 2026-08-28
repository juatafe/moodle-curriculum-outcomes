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

use local_criteriaoutcomes\service\criterion_aggregation_service;

/**
 * Pure aggregation tests.
 *
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class criterion_aggregation_service_test extends \advanced_testcase {
    public function test_mean_and_weighted_mean(): void {
        $service = new criterion_aggregation_service();
        $items = [
            ['fraction' => 1.0, 'weight' => 1],
            ['fraction' => 0.5, 'weight' => 2],
            ['fraction' => 0.75, 'weight' => 1],
        ];
        $this->assertEqualsWithDelta(0.75, $service->aggregate($items, $service::MEAN)['value'], 0.0000001);
        $weighted = $service->aggregate($items, $service::WEIGHTED_MEAN);
        $this->assertEqualsWithDelta(0.6875, $weighted['value'], 0.0000001);
        $this->assertEquals(2.75, $weighted['numerator']);
        $this->assertEquals(4.0, $weighted['denominator']);
    }

    public function test_edge_cases_are_explicit(): void {
        $service = new criterion_aggregation_service();
        $single = $service->aggregate([['fraction' => 0.3333333, 'weight' => 2.5]], $service::WEIGHTED_MEAN);
        $this->assertEqualsWithDelta(0.3333333, $single['value'], 0.0000001);
        $this->assertSame('empty', $service->aggregate([], $service::MEAN)['status']);
        $pending = $service->aggregate([
            ['fraction' => 1.0, 'weight' => 1],
            ['fraction' => null, 'weight' => 1, 'pending' => true],
        ], $service::MEAN);
        $this->assertSame('pending', $pending['status']);
        $this->assertNull($pending['value']);
    }

    public function test_invalid_weight_is_rejected(): void {
        $this->expectException(\invalid_parameter_exception::class);
        (new criterion_aggregation_service())->aggregate(
            [['fraction' => 0.5, 'weight' => 0]],
            criterion_aggregation_service::WEIGHTED_MEAN
        );
    }
}
