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
 * Teacher criterion assessment page.
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
$userid = required_param('userid', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/criteriaoutcomes:manage', $context);
$criterion = $DB->get_record_sql(
    "SELECT c.*
       FROM {local_crout_criterion} c
       JOIN {local_crout_parent} p ON p.id = c.parentid
       JOIN {local_crout_framework} f ON f.id = p.frameworkid
      WHERE c.id = :criterionid AND f.courseid = :courseid",
    ['criterionid' => $criterionid, 'courseid' => $courseid],
    MUST_EXIST
);
$student = core_user::get_user($userid, '*', MUST_EXIST);
if (!is_enrolled($context, $student)) {
    throw new moodle_exception('notenrolled', 'error');
}
$urlparams = ['courseid' => $courseid, 'criterionid' => $criterionid, 'userid' => $userid];
$PAGE->set_url('/local/criteriaoutcomes/assessment.php', $urlparams);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('assesscriteria', 'local_criteriaoutcomes'));
$PAGE->set_heading(format_string($course->fullname));
$service = new \local_criteriaoutcomes\service\assessment_service();

if (data_submitted()) {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHA);
    if ($action === 'release') {
        $assessmentid = required_param('assessmentid', PARAM_INT);
        $assessment = $service->get_assessment($assessmentid);
        if (!$assessment || (int)$assessment->courseid !== $courseid || (int)$assessment->userid !== $userid) {
            throw new invalid_parameter_exception('Invalid assessment.');
        }
        $service->release_assessment($assessmentid);
    } else if ($action === 'savedraft') {
        $mode = required_param('assessmentmode', PARAM_ALPHANUMEXT);
        $record = [
            'courseid' => $courseid,
            'criterionid' => $criterionid,
            'userid' => $userid,
            'sourcetype' => \local_criteriaoutcomes\constants::SOURCE_DIRECT,
            'sourceid' => 0,
            'assessmentmode' => $mode,
            'feedback' => optional_param('feedback', '', PARAM_TEXT),
            'graderid' => $USER->id,
            'status' => \local_criteriaoutcomes\constants::STATUS_DRAFT,
        ];
        $scalevalue = optional_param('scalevalue', 0, PARAM_INT);
        if ($mode !== \local_criteriaoutcomes\constants::MODE_FEEDBACK_ONLY && $scalevalue > 0) {
            $record['scalevalue'] = $scalevalue;
        }
        $service->save_assessment($record);
    }
    redirect($PAGE->url);
}

$assessments = $service->get_assessments_for_criterion($courseid, $criterionid, $userid);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('assessmentfor', 'local_criteriaoutcomes', (object)[
    'criterion' => $criterion->code,
    'student' => fullname($student),
]));
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
foreach ($urlparams as $name => $value) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
}
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'savedraft']);
echo html_writer::label(get_string('assessmentmode', 'local_criteriaoutcomes'), 'assessmentmode');
echo html_writer::select([
    \local_criteriaoutcomes\constants::MODE_FEEDBACK_ONLY => get_string('feedbackonly', 'local_criteriaoutcomes'),
    \local_criteriaoutcomes\constants::MODE_VALUE_ONLY => get_string('valueonly', 'local_criteriaoutcomes'),
    \local_criteriaoutcomes\constants::MODE_VALUE_AND_FEEDBACK => get_string('valueandfeedback', 'local_criteriaoutcomes'),
], 'assessmentmode', \local_criteriaoutcomes\constants::MODE_FEEDBACK_ONLY, false, ['id' => 'assessmentmode']);
echo html_writer::label(get_string('scalevalue', 'local_criteriaoutcomes'), 'scalevalue');
echo html_writer::empty_tag('input', ['type' => 'number', 'name' => 'scalevalue', 'id' => 'scalevalue', 'min' => 1]);
echo html_writer::label(get_string('feedbacktext', 'local_criteriaoutcomes'), 'feedback');
echo html_writer::tag('textarea', '', ['name' => 'feedback', 'id' => 'feedback', 'rows' => 4, 'class' => 'form-control']);
echo html_writer::tag('button', get_string('saveassessment', 'local_criteriaoutcomes'), [
    'type' => 'submit', 'class' => 'btn btn-primary mt-2',
]);
echo html_writer::end_tag('form');

foreach ($assessments as $assessment) {
    echo html_writer::div(s((string)$assessment->feedback) . ' — ' . s($assessment->status), 'card card-body mt-3');
    if ($assessment->status === \local_criteriaoutcomes\constants::STATUS_DRAFT) {
        $releaseurl = new moodle_url($PAGE->url, [
            'action' => 'release', 'assessmentid' => $assessment->id, 'sesskey' => sesskey(),
        ]);
        echo $OUTPUT->single_button($releaseurl, get_string('releaseassessment', 'local_criteriaoutcomes'), 'post');
    }
}
echo $OUTPUT->footer();
