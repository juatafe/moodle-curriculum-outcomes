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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/grade/constants.php');
require_once($CFG->libdir . '/grade/grade_scale.php');

/**
 * Creates explicitly requested, course-local recommended scales.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scale_template_service {
    /** Numeric ordinal template. */
    public const NUMERIC = 'numeric_0_10';
    /** Five-level achievement template. */
    public const ACHIEVEMENT = 'achievement_5';
    /** Stable ownership marker revision. */
    private const MARKER_PREFIX = '[local_criteriaoutcomes:scale:';

    /**
     * Create or return one owned course scale without adopting name collisions.
     */
    public function create(int $courseid, string $template): int {
        global $DB, $USER;
        $course = get_course($courseid);
        $definition = $this->definition($template);
        $marker = self::MARKER_PREFIX . $template . ':v1]';

        foreach ((array)\grade_scale::fetch_all(['courseid' => $course->id]) as $scale) {
            if (!$scale || !str_contains((string)$scale->description, $marker)) {
                continue;
            }
            if ((string)$scale->scale !== $definition['items']) {
                throw new \moodle_exception('errorscaletemplateconflict', 'local_criteriaoutcomes');
            }
            return (int)$scale->id;
        }

        $scale = new \grade_scale();
        $scale->courseid = $course->id;
        $scale->userid = $USER->id ?: get_admin()->id;
        $scale->name = $definition['name'];
        $scale->scale = $definition['items'];
        $scale->description = $marker . ' ' . get_string('scaletemplatehelp', 'local_criteriaoutcomes');
        $scale->descriptionformat = FORMAT_PLAIN;
        return (int)$scale->insert('local_criteriaoutcomes');
    }

    /**
     * Return the owned scale id, or zero while this is only an available template.
     */
    public function existing_id(int $courseid, string $template): int {
        $definition = $this->definition($template);
        $marker = self::MARKER_PREFIX . $template . ':v1]';
        foreach ((array)\grade_scale::fetch_all(['courseid' => $courseid]) as $scale) {
            if (
                $scale && str_contains((string)$scale->description, $marker) &&
                    (string)$scale->scale === $definition['items']
            ) {
                return (int)$scale->id;
            }
        }
        return 0;
    }

    /**
     * Return the localized levels so users know what a template will create.
     */
    public function levels(string $template): array {
        return explode(',', $this->definition($template)['items']);
    }

    /**
     * Return localized persisted values for one supported template.
     */
    private function definition(string $template): array {
        return match ($template) {
            self::NUMERIC => [
                'name' => get_string('scaletemplatenumericname', 'local_criteriaoutcomes'),
                'items' => implode(',', range(0, 10)),
            ],
            self::ACHIEVEMENT => [
                'name' => get_string('scaletemplateachievementname', 'local_criteriaoutcomes'),
                'items' => implode(',', [
                    get_string('scalelevelinsufficient', 'local_criteriaoutcomes'),
                    get_string('scalelevelsufficient', 'local_criteriaoutcomes'),
                    get_string('scalelevelgood', 'local_criteriaoutcomes'),
                    get_string('scalelevelverygood', 'local_criteriaoutcomes'),
                    get_string('scalelevelexcellent', 'local_criteriaoutcomes'),
                ]),
            ],
            default => throw new \invalid_parameter_exception('Unknown recommended scale template.'),
        };
    }
}
