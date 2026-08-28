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
 * Builds accessible, server-side curriculum selection hierarchies.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class curriculum_selection_service {
    /**
     * Return qualification or course-band groups without changing curriculum identity.
     */
    public function groups(array $curricula): array {
        $groups = [];
        foreach ($curricula as $index => $curriculum) {
            $metadata = $curriculum['metadata'] ?? [];
            $family = $metadata['curriculumtype'] ?? '';
            if ($family === 'fp') {
                $group = trim((string)($metadata['qualification'] ?? ''));
                $group = $group !== '' ? $group : get_string('qualificationunknown', 'local_criteriaoutcomes');
                $label = trim(implode(' — ', array_filter([
                    $metadata['modulecode'] ?? null,
                    $metadata['subjectmodule'] ?? null,
                ])));
            } else {
                [$subject, $band] = $this->subject_and_band((string)($metadata['subjectmodule'] ?? ''));
                $group = $band !== '' ? $band : get_string('coursebandunknown', 'local_criteriaoutcomes');
                $label = $subject;
            }
            $groups[$group][$index] = $label !== '' ? $label : (string)($metadata['curriculumkey'] ?? $index);
        }
        return $groups;
    }

    /**
     * Filter a curriculum list to one explicit group.
     */
    public function filter(array $curricula, string $selectedgroup): array {
        return array_intersect_key($curricula, $this->groups($curricula)[$selectedgroup] ?? []);
    }

    /**
     * Split the parser's stable subject display value into its hierarchy parts.
     */
    private function subject_and_band(string $label): array {
        $parts = preg_split('/\s+—\s+/u', $label, 2);
        return [trim($parts[0] ?? ''), trim($parts[1] ?? '')];
    }
}
