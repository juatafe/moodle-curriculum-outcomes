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
 * Pure state transitions for the guided BOE import flow.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class guided_import_state {
    /**
     * Create a new source-bound flow.
     */
    public function create(array $curricula, string $identifier, string $family, int $courseid): array {
        if ($courseid <= 0) {
            throw new \invalid_parameter_exception('Invalid course for guided import state.');
        }
        return [
            'courseid' => $courseid,
            'curricula' => $curricula,
            'identifier' => $identifier,
            'family' => $family,
            'selectiongroup' => '',
            'curriculumindex' => null,
            'subject' => '',
            'variant' => '',
            'valuation' => '',
            'scaleid' => 0,
            'selectedsourcekeys' => null,
        ];
    }

    /**
     * Reject session workflow state created for a different course.
     */
    public function require_course(array $state, int $courseid): array {
        if (($state['courseid'] ?? 0) !== $courseid) {
            throw new \invalid_parameter_exception('The guided import state belongs to another course.');
        }
        return $state;
    }

    /**
     * Select or change the official hierarchy group and invalidate downstream choices.
     */
    public function select_group(array $state, string $group): array {
        $service = new curriculum_selection_service();
        $groups = $service->groups($state["curricula"]);
        $subjects = $service->subjects($state["curricula"]);

        // Check if this is a subject selection (ESO/Bach, at step 2)
        $isSubjectSelection = $state["family"] !== "fp" && in_array($group, $subjects, true);

        if ($isSubjectSelection) {
            // Subject selection - set subject and reset downstream
            if ($state["selectiongroup"] !== $group) {
                $state["selectiongroup"] = $group;
                $state["curriculumindex"] = null;
                $state["subject"] = $group;
                $state["variant"] = "";
                $state["valuation"] = "";
                $state["scaleid"] = 0;
                $state["selectedsourcekeys"] = null;
            }
        } else {
            // Course band or FP group selection
            if (!isset($groups[$group])) {
                throw new invalid_parameter_exception("Invalid curriculum selection group.");
            }
            if ($state["selectiongroup"] !== $group) {
                $state["selectiongroup"] = $group;
                $state["curriculumindex"] = null;
                // Only reset subject if switching from a different group type
                if ($state["family"] !== "fp") {
                    $state["subject"] = "";
                }
                $state["variant"] = "";
                $state["valuation"] = "";
                $state["scaleid"] = 0;
                $state["selectedsourcekeys"] = null;
            }
        }
        return $state;
    }
        }
        return $state;
    }

    /**
     * Clear the group while retaining the loaded official source.
     */
    public function clear_group(array $state): array {
        $state['selectiongroup'] = '';
        $state['curriculumindex'] = null;
        $state['valuation'] = '';
        $state['scaleid'] = 0;
        $state['selectedsourcekeys'] = null;
        return $state;
    }

    /**
     * Select one curriculum and invalidate a preview only when it changes.
     */
    public function select_curriculum(array $state, int $index): array {
        $service = new curriculum_selection_service();
        $available = $service->filter(
            $state['curricula'],
            $state['selectiongroup']
        );
        if (!isset($available[$index])) {
            throw new \invalid_parameter_exception('Invalid curriculum selection.');
        }
        if ($state['curriculumindex'] !== $index) {
            $state['curriculumindex'] = $index;
            $curriculum = $available[$index];
            $metadata = $curriculum['metadata'] ?? [];
            // If ESO/Bach and subject was previously selected, check for variants
            if ($state['family'] !== 'fp' && $state['subject'] !== '') {
                $variants = $service->variants_for_subject(
                    $state['curricula'],
                    $state['subject']
                );
                if (count($variants) <= 1) {
                    // Only one variant - auto-select, no course band needed
                    $state['variant'] = $variants[0] ?? '';
                    $state['valuation'] = '';
                    $state['scaleid'] = 0;
                    $state['selectedsourcekeys'] = null;
                } else {
                    // Multiple variants - will be selected via selectiongroup later
                    $state['variant'] = '';
                }
            } else {
                $state['valuation'] = '';
                $state['scaleid'] = 0;
                $state['selectedsourcekeys'] = null;
            }
        }
        return $state;
    }

    /**
     * Store the explicit pedagogical valuation choice.
     */
    public function select_valuation(array $state, string $valuation, int $scaleid): array {
        if (!in_array($valuation, ['achievement', 'numeric', 'existing'], true) || $scaleid <= 0) {
            throw new \invalid_parameter_exception('Invalid valuation choice.');
        }
        if ($state['valuation'] !== $valuation || $state['scaleid'] !== $scaleid) {
            $state['selectedsourcekeys'] = null;
        }
        $state['valuation'] = $valuation;
        $state['scaleid'] = $scaleid;
        return $state;
    }
}
