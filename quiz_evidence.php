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
 * Display transparent criterion evidence for one quiz attempt.
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

$attemptid = required_param('attemptid', PARAM_INT);
$attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', MUST_EXIST);
$quiz = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', MUST_EXIST);
$course = get_course($quiz->course);
$cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('local/criteriaoutcomes:viewquizevidence', $context);
$PAGE->set_url('/local/criteriaoutcomes/quiz_evidence.php', ['attemptid' => $attemptid]);
$PAGE->set_context($context);
$PAGE->set_cm($cm, $course);
$PAGE->set_title(get_string('quizevidence', 'local_criteriaoutcomes'));
$PAGE->set_heading(format_string($quiz->name));

$evidence = (new \local_criteriaoutcomes\service\quiz_evidence_service())->for_attempt($attemptid);
$user = core_user::get_user($attempt->userid, '*', MUST_EXIST);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('quizevidencefor', 'local_criteriaoutcomes', (object)[
    'quiz' => format_string($quiz->name), 'user' => fullname($user), 'attempt' => $attempt->attempt,
]));
foreach ($evidence['criteria'] as $criterion) {
    echo html_writer::tag('h3', s($criterion['parentcode'] . ' — ' . $criterion['parentname']));
    echo html_writer::tag('h4', s($criterion['code'] . ' — ' . $criterion['name']));
    $result = $criterion['result'];
    if ($result['status'] === 'complete') {
        echo html_writer::tag('p', get_string(
            'criterionresult',
            'local_criteriaoutcomes',
            format_float($result['value'] * 100, 2)
        ));
        echo html_writer::tag('p', get_string('criterionformula', 'local_criteriaoutcomes', (object)[
            'method' => get_string('aggregation' . $criterion['aggregation'], 'local_criteriaoutcomes'),
            'numerator' => format_float($result['numerator'], 7),
            'denominator' => format_float($result['denominator'], 7),
        ]));
    } else {
        echo $OUTPUT->notification(get_string('criterionpending', 'local_criteriaoutcomes'), 'warning');
    }
    $table = new html_table();
    $table->head = [get_string('question', 'question'), get_string('state', 'question'),
        get_string('fraction', 'local_criteriaoutcomes'), get_string('mappingweight', 'local_criteriaoutcomes')];
    foreach ($criterion['questions'] as $question) {
        $fraction = $question['fraction'] === null ? get_string('pending', 'local_criteriaoutcomes') :
            format_float($question['fraction'] * 100, 2) . ' %';
        $table->data[] = [s($question['slot'] . '. ' . $question['name']), s($question['statestring']),
            $fraction, format_float($question['weight'], 7)];
    }
    echo html_writer::table($table);
}
if (!$evidence['criteria']) {
    echo $OUTPUT->notification(get_string('noquizevidence', 'local_criteriaoutcomes'));
}
echo $OUTPUT->footer();
