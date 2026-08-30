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

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$coursecontext = context_course::instance($courseid);
$PAGE->set_url('/local/criteriaoutcomes/rubric_mapping.php', ['id' => $courseid]);
$PAGE->set_context($coursecontext);
$PAGE->set_title(get_string('rubriccriteriamapping', 'local_criteriaoutcomes'));
$PAGE->set_heading(format_string($course->fullname));

$cmid = optional_param('cmid', 0, PARAM_INT);

if ($cmid) {
    $cm = get_coursemodule_from_id('', $cmid, $courseid, false, MUST_EXIST);
    if ((int)$cm->course !== $courseid) {
        throw new moodle_exception('invalidcoursemodule');
    }
    $modcontext = context_module::instance($cm->id);
    require_capability('local/criteriaoutcomes:maprubric', $modcontext);
    require_capability('moodle/grade:managegradingforms', $modcontext);
    $PAGE->set_url('/local/criteriaoutcomes/rubric_mapping.php', ['id' => $courseid, 'cmid' => $cmid]);
    $PAGE->set_context($modcontext);
    $PAGE->set_cm($cm, $course);
    $PAGE->set_title(get_string('rubriccriteriamapping', 'local_criteriaoutcomes'));
    $PAGE->set_heading(format_string($course->fullname . ': ' . $cm->name));

    $gradingmanager = get_grading_manager($modcontext, $cm->modname, $cm->modname === 'assign' ? 'submissions' : null);
    $controller = $gradingmanager->get_controller('rubric');
    if (!$controller) {
        throw new moodle_exception('gradingformunavailable', 'grading');
    }
    $definition = $controller->get_definition();
    if (!$definition || $definition->status != 20) {
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('rubriccriteriamapping', 'local_criteriaoutcomes') . ' — ' . format_string($cm->name));
        echo $OUTPUT->notification(get_string('norubricavailable', 'local_criteriaoutcomes'), 'warning');
        $url = new moodle_url('/grade/grading/manage.php', ['id' => $cmid]);
        echo html_writer::link($url, get_string('managegrades', 'core_grading'));
        echo $OUTPUT->footer();
        exit;
    }
    $rubriccriteria = $definition->rubric_criteria;
    if (empty($rubriccriteria)) {
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('rubriccriteriamapping', 'local_criteriaoutcomes') . ' — ' . format_string($cm->name));
        echo $OUTPUT->notification(get_string('norubricavailable', 'local_criteriaoutcomes'), 'warning');
        echo $OUTPUT->footer();
        exit;
    }

    $sql = "SELECT c.id, c.code, c.name, c.parentid, p.code AS parentcode, p.name AS parentname
              FROM {local_crout_criterion} c
              JOIN {local_crout_parent} p ON p.id = c.parentid
              JOIN {local_crout_framework} f ON f.id = p.frameworkid
             WHERE f.courseid = :courseid AND f.archived = 0 AND p.archived = 0 AND c.archived = 0
          ORDER BY p.sortorder, c.sortorder";
    $curriculumcriteria = array_values($DB->get_records_sql($sql, ['courseid' => $courseid]));

    $service = new \local_criteriaoutcomes\service\rubric_mapping_service();
    $existingmappings = $service->get_mappings_for_rubric_criteria(array_keys($rubriccriteria));
    $mappingstate = [];
    foreach ($existingmappings as $mapping) {
        $rid = (int)$mapping->rubriccriterionid;
        if (!isset($mappingstate[$rid])) {
            $mappingstate[$rid] = [];
        }
        $mappingstate[$rid][] = (int)$mapping->curriculumcriterionid;
    }

    if (optional_param('savemappings', 0, PARAM_BOOL)) {
        require_sesskey();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new moodle_exception('invalidrequest');
        }
        foreach ($rubriccriteria as $rid => $rc) {
            $selected = optional_param_array('curriculacriteria_' . $rid, [], PARAM_INT);
            $selected = array_values(array_unique(array_filter(array_map('intval', $selected))));
            $service->replace_mappings_for_rubric_criterion($courseid, (int)$rid, $selected);
        }
        redirect(
            new moodle_url('/local/criteriaoutcomes/rubric_mapping.php', ['id' => $courseid, 'cmid' => $cmid]),
            get_string('mappingssaved', 'local_criteriaoutcomes'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('rubriccriteriamapping', 'local_criteriaoutcomes') . ' — ' . format_string($cm->name));
    echo $OUTPUT->heading(get_string('rubriccriteriamapping', 'local_criteriaoutcomes'), 3);
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'cmid', 'value' => $cmid]);

    $grouped = [];
    foreach ($curriculumcriteria as $cc) {
        $grouped[$cc->parentcode . ' — ' . $cc->parentname][] = $cc;
    }

    foreach ($rubriccriteria as $rid => $rc) {
        $rcid = (int)$rid;
        $selected = $mappingstate[$rcid] ?? [];
        $counttext = count($selected) . ' ' . get_string('criteria', 'local_criteriaoutcomes');
        if (count($selected) === 1) {
            $counttext = '1 ' . get_string('criterion', 'local_criteriaoutcomes');
        }
        echo html_writer::start_tag('fieldset', ['class' => 'mb-4 p-3 border']);
        echo html_writer::tag('legend', s($rc['description'] ?? $rc['description'] ?? 'Dimension ' . $rcid), ['class' => 'h5']);
        if (!empty($rc['levels'])) {
            echo html_writer::start_tag('div', ['class' => 'small text-muted mb-2']);
            foreach ($rc['levels'] as $level) {
                $scoretext = isset($level['score']) ? ' (' . $level['score'] . ')' : '';
            }
            echo html_writer::end_tag('div');
        }
        echo html_writer::tag('p', s($counttext), ['class' => 'text-muted small']);
        echo html_writer::tag('div', get_string('selectcriteria', 'local_criteriaoutcomes'), ['class' => 'font-weight-bold mb-1']);
        if (empty($curriculumcriteria)) {
            echo html_writer::tag('p', get_string('nocriteriaforquiz', 'local_criteriaoutcomes'), ['class' => 'text-warning']);
        } else {
            foreach ($grouped as $glabel => $crits) {
                echo html_writer::tag('div', s($glabel), ['class' => 'font-weight-bold mt-2']);
                foreach ($crits as $cc) {
                    $cid = (int)$cc->id;
                    $checked = in_array($cid, $selected, true) ? 'checked' : '';
                    $inputid = 'cc_' . $rcid . '_' . $cid;
                    echo html_writer::start_tag('div', ['class' => 'form-check']);
                    echo html_writer::empty_tag('input', [
                        'type' => 'checkbox',
                        'name' => 'curriculacriteria_' . $rcid . '[]',
                        'value' => $cid,
                        'id' => $inputid,
                        'class' => 'form-check-input',
                        $checked => $checked,
                    ]);
                    echo html_writer::end_tag('div');
                }
            }
        }
        echo html_writer::end_tag('fieldset');
    }

    echo html_writer::end_tag('form');
    echo $OUTPUT->footer();
    exit;
}

require_capability('local/criteriaoutcomes:view', $coursecontext);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('rubriccriteriamapping', 'local_criteriaoutcomes'));
echo html_writer::tag('p', get_string('activitywithrubrics', 'local_criteriaoutcomes'), ['class' => 'text-muted']);

$modinfo = get_fast_modinfo($course);
$activities = [];
foreach ($modinfo->get_cms() as $cm) {
    if (!$cm->has_view()) {
        continue;
    }
    $mcontext = context_module::instance($cm->id);
    if (
        !has_capability('local/criteriaoutcomes:maprubric', $mcontext)
            || !has_capability('moodle/grade:managegradingforms', $mcontext)
    ) {
        continue;
    }
    try {
        $gm = get_grading_manager($mcontext, $cm->modname, $cm->modname === 'assign' ? 'submissions' : null);
        $ctrl = $gm->get_controller('rubric');
        if (!$ctrl) {
            continue;
        }
        $def = $ctrl->get_definition();
        if (!$def || $def->status != 20 || empty($def->rubric_criteria)) {
            continue;
        }
        $activities[] = $cm;
    } catch (Throwable $e) {
        continue;
    }
}

if (empty($activities)) {
    echo $OUTPUT->notification(get_string('norubricavailable', 'local_criteriaoutcomes'), 'info');
} else {
    echo html_writer::start_tag('ul', ['class' => 'list-group']);
    foreach ($activities as $cm) {
        $url = new moodle_url('/local/criteriaoutcomes/rubric_mapping.php', ['id' => $courseid, 'cmid' => $cm->id]);
        echo html_writer::tag('li', html_writer::link($url, format_string($cm->name)), ['class' => 'list-group-item']);
    }
    echo html_writer::end_tag('ul');
}
echo $OUTPUT->footer();
