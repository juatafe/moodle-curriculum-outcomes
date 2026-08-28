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

use local_criteriaoutcomes\constants;

/**
 * Checklist definitions, items, mappings, and responses.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class checklist_service {
    /**
     * Create a checklist definition.
     *
     * @return int Definition ID.
     */
    public function create_definition(int $courseid, string $name, string $itemmode, ?string $description = null): int {
        global $DB;
        if (!in_array($itemmode, [constants::CHECKLIST_BINARY, constants::CHECKLIST_THREE_STATE], true)) {
            throw new \invalid_parameter_exception('itemmode must be "binary" or "three_state".');
        }
        $now = time();
        return (int)$DB->insert_record('local_crout_checklist_def', (object)[
            'courseid' => $courseid,
            'name' => $name,
            'description' => $description,
            'descriptionformat' => FORMAT_PLAIN,
            'itemmode' => $itemmode,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Update a checklist definition.
     */
    public function update_definition(int $id, array $data): void {
        global $DB;
        $record = $DB->get_record('local_crout_checklist_def', ['id' => $id], '*', MUST_EXIST);
        if (isset($data['name'])) {
            $record->name = $data['name'];
        }
        if (array_key_exists('description', $data)) {
            $record->description = $data['description'];
        }
        if (isset($data['itemmode'])) {
            if (!in_array($data['itemmode'], [constants::CHECKLIST_BINARY, constants::CHECKLIST_THREE_STATE], true)) {
                throw new \invalid_parameter_exception('itemmode must be "binary" or "three_state".');
            }
            $record->itemmode = $data['itemmode'];
        }
        $record->timemodified = time();
        $DB->update_record('local_crout_checklist_def', $record);
    }

    /**
     * Create a checklist item.
     *
     * @return int Item ID.
     */
    public function create_item(int $definitionid, string $name, int $sortorder = 0, ?float $weight = null): int {
        global $DB;
        $DB->get_record('local_crout_checklist_def', ['id' => $definitionid], '*', MUST_EXIST);
        $now = time();
        return (int)$DB->insert_record('local_crout_checklist_item', (object)[
            'definitionid' => $definitionid,
            'name' => $name,
            'sortorder' => $sortorder,
            'weight' => $weight,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Map a checklist item to a curriculum criterion.
     */
    public function map_item(int $itemid, int $criterionid): int {
        global $DB;
        $DB->get_record('local_crout_checklist_item', ['id' => $itemid], '*', MUST_EXIST);

        // Archived criteria remain historical but cannot receive new mappings.
        $DB->get_record('local_crout_criterion', [
            'id' => $criterionid, 'archived' => 0,
        ], '*', MUST_EXIST);

        $existing = $DB->get_record('local_crout_checklist_map', [
            'itemid' => $itemid,
            'criterionid' => $criterionid,
        ]);
        if ($existing) {
            return (int)$existing->id;
        }

        return (int)$DB->insert_record('local_crout_checklist_map', (object)[
            'itemid' => $itemid,
            'criterionid' => $criterionid,
            'timecreated' => time(),
        ]);
    }

    /**
     * Remove a mapping between a checklist item and a curriculum criterion.
     */
    public function unmap_item(int $itemid, int $criterionid): void {
        global $DB;
        $DB->delete_records('local_crout_checklist_map', [
            'itemid' => $itemid,
            'criterionid' => $criterionid,
        ]);
    }

    /**
     * Save a checklist response for a user.
     *
     * @return int Response ID.
     */
    public function save_response(
        int $definitionid,
        int $itemid,
        int $userid,
        string $state,
        ?string $feedback = null,
        int $graderid = 0
    ): int {
        global $DB, $USER;

        $def = $DB->get_record('local_crout_checklist_def', ['id' => $definitionid], '*', MUST_EXIST);

        // Validate state.
        $validstates = $def->itemmode === constants::CHECKLIST_BINARY
            ? [constants::CHECKLIST_NOT_DONE, constants::CHECKLIST_DONE]
            : [constants::CHECKLIST_NOT_DONE, constants::CHECKLIST_PARTIAL, constants::CHECKLIST_DONE];
        if (!in_array($state, $validstates, true)) {
            throw new \invalid_parameter_exception('Invalid state for this checklist mode.');
        }

        if ($graderid === 0) {
            $graderid = $USER->id;
        }

        $now = time();
        $existing = $DB->get_record('local_crout_checklist_resp', [
            'definitionid' => $definitionid,
            'itemid' => $itemid,
            'userid' => $userid,
        ]);

        $record = (object)[
            'definitionid' => $definitionid,
            'itemid' => $itemid,
            'userid' => $userid,
            'state' => $state,
            'feedback' => $feedback,
            'feedbackformat' => FORMAT_PLAIN,
            'graderid' => $graderid,
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        if ($existing) {
            $record->id = $existing->id;
            $record->timecreated = $existing->timecreated;
            $DB->update_record('local_crout_checklist_resp', $record);
            return (int)$record->id;
        }

        return (int)$DB->insert_record('local_crout_checklist_resp', $record);
    }

    /**
     * Get a checklist definition with its items and mappings.
     */
    public function get_definition_with_items(int $definitionid): ?array {
        global $DB;
        $def = $DB->get_record('local_crout_checklist_def', ['id' => $definitionid]);
        if (!$def) {
            return null;
        }
        $items = array_values($DB->get_records('local_crout_checklist_item', ['definitionid' => $definitionid], 'sortorder'));
        foreach ($items as $item) {
            $item->mappings = $DB->get_records_sql(
                "SELECT m.*, c.code, c.name AS criterionname
                   FROM {local_crout_checklist_map} m
                   LEFT JOIN {local_crout_criterion} c ON c.id = m.criterionid
                  WHERE m.itemid = :itemid",
                ['itemid' => $item->id]
            );
        }
        $def->items = $items;
        return (array)$def;
    }

    /**
     * Get all responses for a user in a checklist.
     */
    public function get_responses_for_user(int $definitionid, int $userid): array {
        global $DB;
        return $DB->get_records('local_crout_checklist_resp', [
            'definitionid' => $definitionid,
            'userid' => $userid,
        ]);
    }

    /**
     * Get all checklist definitions for a course.
     */
    public function get_definitions_for_course(int $courseid): array {
        global $DB;
        return $DB->get_records('local_crout_checklist_def', ['courseid' => $courseid], 'name');
    }

    /**
     * Delete a checklist definition and all related data.
     */
    public function delete_definition(int $definitionid): void {
        global $DB;
        $DB->delete_records('local_crout_checklist_resp', ['definitionid' => $definitionid]);
        $items = $DB->get_records('local_crout_checklist_item', ['definitionid' => $definitionid]);
        foreach ($items as $item) {
            $DB->delete_records('local_crout_checklist_map', ['itemid' => $item->id]);
        }
        $DB->delete_records('local_crout_checklist_item', ['definitionid' => $definitionid]);
        $DB->delete_records('local_crout_checklist_def', ['id' => $definitionid]);
    }
}
