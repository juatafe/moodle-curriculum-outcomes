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
 * Map curriculum criteria to native Moodle rubric dimensions.
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

$course = required_param('course', PARAM_INT);
$course = get_course($course);
$cmid = required_param('cmid', PARAM_INT);
$cm = get_coursemodule_from_instance('rubric', $cmid, $course->id, false, MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('local/criteriaoutcomes:maprubric', $context);
$PAGE->set_url('/local/criteriaoutcomes/rubric_mapping.php', ['course' => $course->id, 'cmid' => $cmid]);
$PAGE->set_context($context);
$PAGE->set_cm($cm, $course);
$PAGE->set_title(get_string('rubriccriteriamapping', 'local_criteriaoutcomes'));
$PAGE->set_heading(format_string($course->fullname . ': ' . $cm->name));

// Get rubric definition
$def = $DB->get_record('grading_definitions', ['id' => $cm->rubricdefinitionid], '*', MUST_EXIST);
$area = $DB->get_record('grading_areas', ['id' => $def->areaid], '*', MUST_EXIST);

// Get all rubric criteria
$rubriccriteria = $DB->get_records('gradingform_rubric_criteria', ['definitionid' => $def->id], 'sortorder, id');

// Get curriculum criteria for this course
$sql = "SELECT c.*, p.code AS parentcode, p.name AS parentname, p.id AS parentid
          FROM {local_crout_criterion} c
          JOIN {local_crout_parent} p ON p.id = c.parentid
          JOIN {local_crout_framework} f ON f.id = p.frameworkid
         WHERE f.courseid = :courseid AND f.archived = 0 AND p.archived = 0 AND c.archived = 0
     ORDER BY p.sortorder, c.sortorder";
$curriculumcriteria = array_values($DB->get_records_sql($sql, ['courseid' => $course->id]));

// Get existing mappings from database
$service = new \local_criteriaoutcomes\service\rubric_mapping_service();
$existingmappings = $service->get_mappings_for_rubric_criteria(
    array_keys($rubriccriteria)
);

// Build mapping state: rubric_criterion_id -> [curriculum_criterion_ids]
$mappingstate = [];
foreach ($existingmappings as $mapping) {
    $rubricid = $mapping->rubriccriterionid;
    if (!isset($mappingstate[$rubricid])) {
        $mappingstate[$rubricid] = [];
    }
    $mappingstate[$rubricid][] = $mapping->curriculumcriterionid;
}

// Handle form submission
if (optional_param('savemappings', '', PARAM_ALPHA)) {
    require_sesskey();
    $transaction = $DB->start_delegated_transaction();
    try {
        foreach ($rubriccriteria as $rubriccriterionid => $rubriccriterion) {
            $selectedids = optional_param_array('curriculacriteria[' . $rubriccriterionid . ']', [], PARAM_INT);
            $previousids = $mappingstate[$rubriccriterionid] ?? [];

            // Remove mappings that were unselected
            $removedids = array_diff($previousids, $selectedids);
            foreach ($removedids as $removedid) {
                $service->delete_mapping($rubriccriterionid, $removedid);
            }

            // Save/new mappings (only add if not already existing)
            foreach ($selectedids as $selectedid) {
                // Check if mapping already exists
                $existing = $DB->get_record('local_crout_rubricmap', [
                    'rubriccriterionid' => $rubriccriterionid,
                    'curriculumcriterionid' => $selectedid,
                ], '*', MUST_EXIST);
                if (!$existing) {
                    $now = time();
                    $DB->insert_record('local_crout_rubricmap', (object)[
                        'courseid' => $course->id,
                        'rubriccriterionid' => $rubriccriterionid,
                        'curriculumcriterionid' => $selectedid,
                        'weight' => null,
                        'timecreated' => $now,
                        'timemodified' => $now,
                    ]);
                }
            }
        }
        $transaction->allow_commit();
        echo $OUTPUT->notification(get_string('mappings_saved', 'local_criteriaoutcomes'));
        redirect(new moodle_url('/local/criteriaoutcomes/rubric_mapping.php', ['course' => $course->id, 'cmid' => $cmid]));
    } catch (Throwable $e) {
        $transaction->rollback();
        echo $OUTPUT->notification($e->getMessage(), 'error');
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('rubriccriteriamapping', 'local_criteriaoutcomes') . ' — ' . format_string($cm->name));

// Display each rubric dimension with mapping checkboxes
foreach ($displaydata as $rubriccriterionid => $data) {
    $rc = $data;
    echo html_writer::start_div('rubric-dimension mb-3');
    echo html_writer::start_div('d-flex justify-between align-items-center mb-2');
    echo html_writer::tag('h4', s($rc['name']), ['class' => 'mb-0']);
    echo html_writer::tag('small', get_string('rubricdimension', 'local_criteriaoutcomes'), ['class' => 'text-muted']);
    echo html_writer::end_div();

    // Show levels summary
    if (!empty($rc['levels'])) {
        echo html_writer::start_div('rubric-levels small text-muted mb-2');
        $levels = explode("\n", $rc['levels']);
        foreach ($levels as $level) {
            if (trim($level)) {
                echo html_writer::tag('span', trim($level), ['class' => 'mr-2']);
            }
        }
        echo html_writer::end_div();
    }

    // Curriculum criteria selector
    echo html_writer::start_div('curriculum-mapping mb-3');
    echo html_writer::label(get_string('selectcriteria', 'local_criteriaoutcomes'), 'criteria-' . $rubriccriterionid);

    // Create checkboxes for each curriculum criterion
    $checkboxes = [];
    foreach ($rc['allcurriculacriteria'] as $criteria) {
        $isselected = in_array($criteria->id, $rc['curriculumcriteria'] ?? [], true);
        $key = $rubriccriterionid . '_' . $criteria->id;
        $checkboxes[$key] = html_writer::empty_tag('input', [
            'type' => 'checkbox',
            'name' => 'curriculacriteria[' . $rubriccriterionid . ']',
            'value' => $criteria->id,
            'class' => 'form-check-input',
            'id' => 'criterion-' . $key,
            'checked' => $isselected ? 'checked' : '',
        ]);
        $checkboxes[$key . '_label'] = html_writer::tag('label', s($criteria->code . ' — ' . $criteria->name), [
            'class' => 'form-check-label',
            'for' => 'criterion-' . $key,
        ]);
    }

    // Group checkboxes in rows of 4
    echo html_writer::start_div('d-flex flex-wrap');
    $idx = 0;
    foreach ($checkboxes as $key => $checkbox) {
        echo html_writer::start_div('form-check me-2 mb-1', null, false);
        echo $checkbox;
        echo $checkboxes[$key . '_label'];
        echo html_writer::end_div();
        $idx++;
        if ($idx % 4 === 0 && $idx < count($checkboxes)) {
            echo html_writer::end_div() . "\n" . html_writer::start_div('d-flex flex-wrap');
        }
    }
    echo html_writer::end_div();

    echo html_writer::end_div();
    echo html_writer::end_div();
}

// Save button
echo html_writer::start_div('d-flex justify-content-end mt-3');
echo html_writer::tag('button', get_string('savemappings', 'local_criteriaoutcomes'), [
    'type' => 'submit',
    'name' => 'savemappings',
    'value' => '1',
    'class' => 'btn btn-primary',
]);
echo html_writer::end_div();

echo $OUTPUT->footer();
