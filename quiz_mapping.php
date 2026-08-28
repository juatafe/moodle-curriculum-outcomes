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
 * Configure criterion mappings for one quiz.
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

$quizid = required_param('quizid', PARAM_INT);
$quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
$course = get_course($quiz->course);
$cm = get_coursemodule_from_instance('quiz', $quizid, $course->id, false, MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('local/criteriaoutcomes:mapquiz', $context);
$PAGE->set_url('/local/criteriaoutcomes/quiz_mapping.php', ['quizid' => $quizid]);
$PAGE->set_context($context);
$PAGE->set_cm($cm, $course);
$PAGE->set_title(get_string('quizcriteriamapping', 'local_criteriaoutcomes'));
$PAGE->set_heading(format_string($quiz->name));

if (optional_param('cleanorphans', 0, PARAM_BOOL)) {
    require_sesskey();
    $removed = (new \local_criteriaoutcomes\service\quiz_mapping_service())->clean_orphans($quizid);
    redirect($PAGE->url, get_string('orphansremoved', 'local_criteriaoutcomes', $removed));
}

$sql = "SELECT c.*, p.code AS parentcode, p.name AS parentname, p.id AS parentid
          FROM {local_crout_criterion} c
          JOIN {local_crout_parent} p ON p.id = c.parentid
          JOIN {local_crout_framework} f ON f.id = p.frameworkid
         WHERE f.courseid = :courseid AND f.archived = 0 AND p.archived = 0 AND c.archived = 0
      ORDER BY p.sortorder, c.sortorder";
$criteria = array_values($DB->get_records_sql($sql, ['courseid' => $course->id]));
$service = new \local_criteriaoutcomes\service\quiz_mapping_service();
$slots = $service->get_slots($quizid);
$mappingrecords = $service->get_mappings($quizid);
$mappings = [];
$mappedcounts = [];
foreach ($mappingrecords as $mapping) {
    $mappings[$mapping->slotid . '_' . $mapping->criterionid] = $mapping;
    if (empty($mapping->orphaned)) {
        $mappedcounts[$mapping->criterionid] = ($mappedcounts[$mapping->criterionid] ?? 0) + 1;
    }
}
$configurations = $service->get_configurations($quizid);
$form = new \local_criteriaoutcomes\form\quiz_mapping_form(null, [
    'quizid' => $quizid, 'slots' => $slots, 'criteria' => $criteria,
    'mappings' => $mappings, 'configurations' => $configurations, 'mappedcounts' => $mappedcounts,
]);
if ($data = $form->get_data()) {
    require_sesskey();
    $transaction = $DB->start_delegated_transaction();
    foreach ($slots as $slot) {
        foreach ($criteria as $criterion) {
            $key = $slot->id . '_' . $criterion->id;
            if (!empty($data->{'map_' . $key})) {
                $service->save_mapping($quizid, $slot->id, $criterion->id, (float)$data->{'weight_' . $key});
                $service->save_configuration(
                    $quizid,
                    $criterion->id,
                    $data->{'aggregation_' . $criterion->id} ??
                        ($configurations[$criterion->id]->aggregation ?? 'mean')
                );
            } else {
                $service->delete_mapping($quizid, $slot->id, $criterion->id);
            }
        }
    }
    $transaction->allow_commit();
    redirect($PAGE->url, get_string('mappingssaved', 'local_criteriaoutcomes'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('quizcriteriamappingfor', 'local_criteriaoutcomes', format_string($quiz->name)));
$orphancount = count(array_filter($mappingrecords, fn($mapping) => !empty($mapping->orphaned)));
if ($orphancount) {
    $cleanurl = new moodle_url($PAGE->url, ['cleanorphans' => 1, 'sesskey' => sesskey()]);
    echo $OUTPUT->notification(get_string('orphanmappingsfound', 'local_criteriaoutcomes', $orphancount), 'warning');
    echo $OUTPUT->single_button($cleanurl, get_string('cleanorphanmappings', 'local_criteriaoutcomes'));
}
if (!$criteria) {
    echo $OUTPUT->notification(get_string('nocriteriaforquiz', 'local_criteriaoutcomes'));
} else if (!$slots) {
    echo $OUTPUT->notification(get_string('noquizslots', 'local_criteriaoutcomes'));
} else {
    $form->display();
}
$attempts = $DB->get_records('quiz_attempts', ['quiz' => $quizid, 'preview' => 0], 'attempt DESC', '*', 0, 50);
if ($attempts && has_capability('local/criteriaoutcomes:viewquizevidence', $context)) {
    echo $OUTPUT->heading(get_string('quizevidence', 'local_criteriaoutcomes'), 3);
    $links = [];
    foreach ($attempts as $attempt) {
        $user = core_user::get_user($attempt->userid, '*', MUST_EXIST);
        $links[] = html_writer::link(
            new moodle_url('/local/criteriaoutcomes/quiz_evidence.php', ['attemptid' => $attempt->id]),
            get_string('attemptlink', 'local_criteriaoutcomes', (object)[
                'user' => fullname($user), 'attempt' => $attempt->attempt,
            ])
        );
    }
    echo html_writer::alist($links);
}
echo $OUTPUT->footer();
