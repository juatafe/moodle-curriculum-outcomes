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
     * Return unique subject names from curricula, without duplication by band.
     */
    public function subjects(array $curricula): array {
        $subjects = [];
        foreach ($curricula as $index => $curriculum) {
            $metadata = $curriculum['metadata'] ?? [];
            $subject = $metadata['subjectmodule'] ?? '';
            if ($subject === '') {
                continue;
            }
            // Extract just the subject name (before the — band separator).
            $subjectname = $this->extract_subject_name($subject);
            if (!in_array($subjectname, $subjects, true)) {
                $subjects[] = $subjectname;
            }
        }
        sort($subjects);
        return $subjects;
    }

    /**
     * Extract the base subject name from a subjectmodule string.
     * e.g. "Matemáticas — Cursos primero a tercero" -> "Matemáticas"
     * e.g. "Matemáticas" -> "Matemáticas"
     */
    private function extract_subject_name(string $subjectmodule): string {
        $parts = preg_split('/\s+—\s+/u', $subjectmodule, 2);
        return trim($parts[0] ?? $subjectmodule);
    }

    /**
     * Filter a curriculum list to one explicit group.
     */
    public function filter(array $curricula, string $selectedgroup): array {
        return array_intersect_key($curricula, $this->groups($curricula)[$selectedgroup] ?? []);
    }

    /**
     * Return course bands/variants for a specific subject.
     */
    public function variants_for_subject(array $curricula, string $subject): array {
        $variants = [];
        foreach ($curricula as $index => $curriculum) {
            $metadata = $curriculum['metadata'] ?? [];
            $subjectmodule = $metadata['subjectmodule'] ?? '';
            if ($subjectmodule === '') {
                continue;
            }
            // Check if this curriculum matches the subject (base name only).
            $curriculumsubject = $this->extract_subject_name($subjectmodule);
            if ($curriculumsubject !== $subject) {
                continue;
            }
            $band = $this->infer_band_from_subjectmodule($subjectmodule);
            if ($band !== '') {
                $variants[$band] = $band;
            }
        }
        ksort($variants);
        return array_values($variants);
    }

    /**
     * Infer course band from subjectmodule metadata.
     */
    private function infer_band_from_subjectmodule(string $subjectmodule): string {
        [, $band] = $this->subject_and_band($subjectmodule);
        return $band;
    }

    /**
     * Split the parser's stable subject display value into its hierarchy parts.
     */
    private function subject_and_band(string $label): array {
        $parts = preg_split('/\s+—\s+/u', $label, 2);
        return [trim($parts[0] ?? ''), trim($parts[1] ?? '')];
    }
}
