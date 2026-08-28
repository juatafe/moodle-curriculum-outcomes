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
 * Safe archive and deletion management for a course curriculum.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/criteriaoutcomes:manage', $context);

$PAGE->set_url('/local/criteriaoutcomes/curriculum_manage.php', ['id' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('managecurriculum', 'local_criteriaoutcomes'));
$PAGE->set_heading(format_string($course->fullname));

$service = new \local_criteriaoutcomes\service\deletion_safety_service();
$action = optional_param('action', '', PARAM_ALPHAEXT);
$analysis = null;
$notice = null;
if ($action !== '') {
    require_sesskey();
    if ($action === 'analyse') {
        $ids = optional_param_array('criterionids', [], PARAM_INT);
        $analysis = $service->analyze_many($courseid, $ids);
    } else if ($action === 'apply') {
        $ids = optional_param_array('criterionids', [], PARAM_INT);
        $summary = $service->apply($courseid, $ids, optional_param('archiveused', 0, PARAM_BOOL));
        $notice = get_string('deleteapplysummary', 'local_criteriaoutcomes', (object)$summary);
    } else if ($action === 'unarchive') {
        $criterionid = required_param('criterionid', PARAM_INT);
        $criterion = $DB->get_record_sql(
            "SELECT c.id, c.parentid, p.frameworkid
               FROM {local_crout_criterion} c
               JOIN {local_crout_parent} p ON p.id = c.parentid
               JOIN {local_crout_framework} f ON f.id = p.frameworkid
              WHERE c.id = :criterionid AND f.courseid = :courseid",
            ['criterionid' => $criterionid, 'courseid' => $courseid],
            MUST_EXIST
        );
        $transaction = $DB->start_delegated_transaction();
        $DB->set_field('local_crout_criterion', 'archived', 0, ['id' => $criterion->id]);
        $DB->set_field('local_crout_parent', 'archived', 0, ['id' => $criterion->parentid]);
        $DB->set_field('local_crout_framework', 'archived', 0, ['id' => $criterion->frameworkid]);
        $transaction->allow_commit();
        $notice = get_string('criterionrestored', 'local_criteriaoutcomes');
    }
}

$showarchived = optional_param('showarchived', 0, PARAM_BOOL);
$archivedsql = $showarchived ? '' : ' AND c.archived = 0 AND p.archived = 0 AND f.archived = 0';
$records = $DB->get_records_sql(
    "SELECT c.id, c.code, c.name, c.archived, p.code AS parentcode, p.name AS parentname,
            f.name AS frameworkname
       FROM {local_crout_criterion} c
       JOIN {local_crout_parent} p ON p.id = c.parentid
       JOIN {local_crout_framework} f ON f.id = p.frameworkid
      WHERE f.courseid = :courseid $archivedsql
   ORDER BY f.name, p.sortorder, c.sortorder",
    ['courseid' => $courseid]
);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managecurriculum', 'local_criteriaoutcomes'));
if ($notice) {
    echo $OUTPUT->notification($notice, 'success');
}
echo html_writer::link(
    new moodle_url($PAGE->url, ['showarchived' => $showarchived ? 0 : 1]),
    get_string($showarchived ? 'hidearchived' : 'showarchived', 'local_criteriaoutcomes'),
    ['class' => 'btn btn-secondary mb-3']
);

if ($analysis !== null) {
    echo $OUTPUT->heading(get_string('impactpreview', 'local_criteriaoutcomes'), 3);
    $table = new html_table();
    $table->head = [get_string('criterion', 'local_criteriaoutcomes'), get_string('policy', 'local_criteriaoutcomes'),
        get_string('impact', 'local_criteriaoutcomes')];
    foreach ($analysis as $item) {
        $criterion = $records[$item['criterionid']] ?? $DB->get_record('local_crout_criterion', ['id' => $item['criterionid']]);
        $table->data[] = [s($criterion ? $criterion->code . ' — ' . $criterion->name : (string)$item['criterionid']),
            s(strtoupper($item['policy'])), s(implode(', ', $item['reasons']))];
    }
    echo html_writer::table($table);
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'apply']);
    foreach (array_keys($analysis) as $criterionid) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'criterionids[]', 'value' => $criterionid]);
    }
    echo html_writer::checkbox('archiveused', 1, false, get_string('archiveusedconfirm', 'local_criteriaoutcomes'));
    echo html_writer::tag('p', get_string('deletewarning', 'local_criteriaoutcomes'), ['class' => 'alert alert-warning mt-3']);
    echo html_writer::tag(
        'button',
        get_string('applysafeoperation', 'local_criteriaoutcomes'),
        ['type' => 'submit', 'class' => 'btn btn-danger']
    );
    echo html_writer::end_tag('form');
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'analyse']);
$table = new html_table();
$table->head = ['', get_string('curriculum', 'local_criteriaoutcomes'), get_string('criterion', 'local_criteriaoutcomes'),
    get_string('status', 'local_criteriaoutcomes'), get_string('actions', 'local_criteriaoutcomes')];
foreach ($records as $record) {
    $table->data[] = [html_writer::checkbox('criterionids[]', $record->id, false, '', ['aria-label' => $record->code]),
        s($record->frameworkname . ' / ' . $record->parentcode), s($record->code . ' — ' . $record->name),
        get_string($record->archived ? 'archived' : 'active', 'local_criteriaoutcomes'), ''];
}
echo html_writer::table($table);
echo html_writer::tag(
    'button',
    get_string('analyseimpact', 'local_criteriaoutcomes'),
    ['type' => 'submit', 'class' => 'btn btn-primary']
);
echo html_writer::end_tag('form');
if ($showarchived) {
    echo $OUTPUT->heading(get_string('restorearchived', 'local_criteriaoutcomes'), 3, 'mt-4');
    foreach ($records as $record) {
        if (!$record->archived) {
            continue;
        }
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url, 'class' => 'mb-2']);
        foreach (
            ['id' => $courseid, 'sesskey' => sesskey(), 'action' => 'unarchive',
            'criterionid' => $record->id] as $name => $value
        ) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
        }
        echo html_writer::tag(
            'button',
            get_string('unarchivecriterion', 'local_criteriaoutcomes', $record->code),
            ['type' => 'submit', 'class' => 'btn btn-sm btn-secondary']
        );
        echo html_writer::end_tag('form');
    }
}
echo $OUTPUT->footer();
