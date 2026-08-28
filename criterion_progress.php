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
 * Student criterion evidence drill-down.
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
$courseid = required_param('courseid', PARAM_INT);
$criterionid = required_param('criterionid', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
$userid = (int)optional_param('userid', $USER->id, PARAM_INT);
if ($userid !== (int)$USER->id) {
    require_capability('local/criteriaoutcomes:manage', $context);
} else if (!is_enrolled($context, $USER)) {
    throw new required_capability_exception($context, 'local/criteriaoutcomes:view', 'nopermissions', '');
}
$PAGE->set_url('/local/criteriaoutcomes/criterion_progress.php', [
    'courseid' => $courseid, 'criterionid' => $criterionid, 'userid' => $userid,
]);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('criterionevidence', 'local_criteriaoutcomes'));
$PAGE->set_heading(format_string($course->fullname));
$model = (new \local_criteriaoutcomes\service\student_progress_service())->for_student_criterion(
    $courseid,
    $criterionid,
    $userid
);
if ($userid === (int)$USER->id) {
    (new \local_criteriaoutcomes\service\feedback_service())->mark_criterion_read($criterionid, $userid);
}
echo $OUTPUT->header();
echo $OUTPUT->heading(s($model['criterion']['code'] . ' — ' . $model['criterion']['name']));
foreach ($model['evidence'] as $evidence) {
    echo html_writer::start_div('card card-body mb-2');
    echo html_writer::tag('strong', s(ucfirst($evidence['type'])));
    if (!empty($evidence['feedback'])) {
        echo html_writer::tag('p', s($evidence['feedback']));
    }
    if (!empty($evidence['dimension'])) {
        echo html_writer::tag('p', clean_text($evidence['dimension']) . ': ' . clean_text($evidence['level']));
    }
    echo html_writer::end_div();
}
echo $OUTPUT->footer();
