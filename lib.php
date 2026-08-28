<?php
// This file is part of Moodle - http://moodle.org/
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
 * Library callbacks.
 *
 *  local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Add the plugin page to course navigation.
 */
function local_criteriaoutcomes_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context_course $context
): void {
    global $USER;
    if (is_enrolled($context, $USER) && !isguestuser()) {
        $navigation->add(
            get_string('mystudentprogress', 'local_criteriaoutcomes'),
            new moodle_url('/local/criteriaoutcomes/student_progress.php', ['id' => $course->id]),
            navigation_node::TYPE_CUSTOM,
            null,
            'criteriaoutcomesprogress',
            new pix_icon('i/grades', '')
        );
    }
    if (has_capability('local/criteriaoutcomes:view', $context)) {
        $navigation->add(
            get_string('pluginname', 'local_criteriaoutcomes'),
            new moodle_url('/local/criteriaoutcomes/index.php', ['id' => $course->id]),
            navigation_node::TYPE_CUSTOM,
            null,
            'criteriaoutcomes',
            new pix_icon('i/outcomes', '')
        );
    }
    if (has_capability('local/criteriaoutcomes:import', $context)) {
        $navigation->add(
            get_string('importcurriculum', 'local_criteriaoutcomes'),
            new moodle_url('/local/criteriaoutcomes/boe.php', ['id' => $course->id]),
            navigation_node::TYPE_CUSTOM,
            null,
            'criteriaoutcomesimport',
            new pix_icon('i/import', '')
        );
    }
    if (has_capability('local/criteriaoutcomes:manage', $context)) {
        $navigation->add(
            get_string('importhistory', 'local_criteriaoutcomes'),
            new moodle_url('/local/criteriaoutcomes/import_history.php', ['id' => $course->id]),
            navigation_node::TYPE_CUSTOM
        );
        $navigation->add(
            get_string('managecurriculum', 'local_criteriaoutcomes'),
            new moodle_url('/local/criteriaoutcomes/curriculum_manage.php', ['id' => $course->id]),
            navigation_node::TYPE_CUSTOM
        );
    }
    if (has_capability('local/criteriaoutcomes:mapquiz', $context)) {
        $navigation->add(
            get_string('quizcriteriamapping', 'local_criteriaoutcomes'),
            new moodle_url('/local/criteriaoutcomes/quiz.php', ['id' => $course->id]),
            navigation_node::TYPE_CUSTOM,
            null,
            'criteriaoutcomesquiz',
            new pix_icon('i/questions', '')
        );
    }
}
