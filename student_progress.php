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
 * Student progress dashboard.
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
$userid = (int)optional_param('userid', $USER->id, PARAM_INT);
if ($userid !== (int)$USER->id) {
    require_capability('local/criteriaoutcomes:manage', $context);
} else if (!is_enrolled($context, $USER)) {
    throw new required_capability_exception($context, 'local/criteriaoutcomes:view', 'nopermissions', '');
}
$PAGE->set_url('/local/criteriaoutcomes/student_progress.php', ['id' => $courseid, 'userid' => $userid]);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('mystudentprogress', 'local_criteriaoutcomes'));
$PAGE->set_heading(format_string($course->fullname));
$model = (new \local_criteriaoutcomes\service\student_progress_service())->for_student($courseid, $userid);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('mystudentprogress', 'local_criteriaoutcomes'));
if ($model['coursegrade'] && !$model['coursegrade']['hidden']) {
    echo html_writer::tag('p', get_string('coursegrade', 'local_criteriaoutcomes', s((string)$model['coursegrade']['grade'])));
}
foreach ($model['parents'] as $parent) {
    echo html_writer::tag('h3', s($parent['code'] . ' — ' . $parent['name']));
    foreach ($parent['criteria'] as $criterion) {
        $label = $criterion['code'] . ' — ' . $criterion['name'];
        if ($criterion['weight'] !== null) {
            $label .= ' (' . get_string('criterionweight', 'local_criteriaoutcomes', $criterion['weight']) . ')';
        }
        echo html_writer::link(new moodle_url('/local/criteriaoutcomes/criterion_progress.php', [
            'courseid' => $courseid, 'criterionid' => $criterion['id'], 'userid' => $userid,
        ]), s($label));
        echo html_writer::tag('p', get_string('progresscounts', 'local_criteriaoutcomes', (object)[
            'evidence' => $criterion['evidencecount'],
            'feedback' => $criterion['feedbackcount'],
            'unread' => $criterion['unreadcount'],
        ]));
    }
}
echo $OUTPUT->footer();
