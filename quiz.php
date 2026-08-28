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

/**
 * Select a quiz for criterion mapping.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Supports both Moodle code layouts.
$configpaths = [dirname(__DIR__, 2) . '/config.php', dirname(__DIR__, 3) . '/config.php'];
foreach ($configpaths as $configpath) {
    if (is_readable($configpath)) {
        require_once($configpath);
        break;
    }
}
defined('MOODLE_INTERNAL') || die();

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/criteriaoutcomes:mapquiz', $context);
$PAGE->set_url('/local/criteriaoutcomes/quiz.php', ['id' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('quizcriteriamapping', 'local_criteriaoutcomes'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('quizcriteriamapping', 'local_criteriaoutcomes'));
$modinfo = get_fast_modinfo($course);
$items = [];
foreach ($modinfo->get_instances_of('quiz') as $cm) {
    if ($cm->uservisible) {
        $items[] = html_writer::link(
            new moodle_url('/local/criteriaoutcomes/quiz_mapping.php', ['quizid' => $cm->instance]),
            format_string($cm->name)
        );
    }
}
echo $items ? html_writer::alist($items) : $OUTPUT->notification(get_string('noquizzes', 'local_criteriaoutcomes'));
echo $OUTPUT->footer();
