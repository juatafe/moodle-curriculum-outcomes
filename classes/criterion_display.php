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
 * Canonical labels for curriculum criteria shown outside their hierarchy.
 *
 * Identity and curriculum text remain stored separately. This helper only
 * builds the human-readable label used by Moodle's flat Outcome selectors.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class criterion_display {
    /**
     * Return the canonical flat label without changing either source value.
     */
    public static function name(string $code, string $text): string {
        $code = trim($code);
        $text = trim($text);
        if ($code === '') {
            return $text;
        }
        if ($text === '') {
            return $code;
        }
        return $code . ' — ' . $text;
    }

    /**
     * Synchronise labels of mapped plugin-owned Outcomes only.
     *
     * This is deliberately idempotent and updates the existing record in
     * place, preserving Outcome ids, grade items, grades and plugin mappings.
     */
    public static function migrate_owned_outcomes(): int {
        global $DB;

        $sql = "SELECT c.id, c.code, c.name, c.outcomeid, o.fullname
                  FROM {local_crout_criterion} c
                  JOIN {local_crout_parent} p ON p.id = c.parentid
                  JOIN {local_crout_framework} f ON f.id = p.frameworkid
                  JOIN {grade_outcomes} o ON o.id = c.outcomeid AND o.courseid = f.courseid
                 WHERE c.outcomeowned = :owned";
        $changed = 0;
        foreach ($DB->get_recordset_sql($sql, ['owned' => 1]) as $record) {
            $label = self::name($record->code, $record->name);
            if ($record->fullname !== $label) {
                $DB->set_field('grade_outcomes', 'fullname', $label, ['id' => $record->outcomeid]);
                $changed++;
            }
        }
        return $changed;
    }
}
