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
 * Course-scoped curriculum import history and conservative undo.
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
$PAGE->set_url('/local/criteriaoutcomes/import_history.php', ['id' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('importhistory', 'local_criteriaoutcomes'));
$PAGE->set_heading(format_string($course->fullname));

$batchid = optional_param('batchid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHAEXT);
$undoanalysis = null;
$notice = null;
if ($action !== '') {
    require_sesskey();
    $batchid = required_param('batchid', PARAM_INT);
    $service = new \local_criteriaoutcomes\service\import_undo_service();
    if ($action === 'analyseundo') {
        $undoanalysis = $service->analyze($courseid, $batchid);
    } else if ($action === 'applyundo') {
        $summary = $service->undo($courseid, $batchid, optional_param('archiveused', 0, PARAM_BOOL));
        $notice = get_string('undosummary', 'local_criteriaoutcomes', (object)$summary);
    }
}

$batches = $DB->get_records_sql(
    "SELECT ib.*, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
            u.middlename, u.alternatename
       FROM {local_crout_importbatch} ib
  LEFT JOIN {user} u ON u.id = ib.userid
      WHERE ib.courseid = :courseid
   ORDER BY ib.timecreated DESC, ib.id DESC",
    ['courseid' => $courseid]
);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importhistory', 'local_criteriaoutcomes'));
if ($notice) {
    echo $OUTPUT->notification($notice, 'success');
}
if ($batchid && isset($batches[$batchid])) {
    $batch = $batches[$batchid];
    echo $OUTPUT->heading(get_string('batchdetail', 'local_criteriaoutcomes', $batchid), 3);
    echo html_writer::tag('pre', s(json_encode(json_decode((string)$batch->summary, true), JSON_PRETTY_PRINT)));
    $items = $DB->get_records('local_crout_importitem', ['batchid' => $batchid], 'id');
    $table = new html_table();
    $table->head = [get_string('entity', 'local_criteriaoutcomes'), get_string('action', 'local_criteriaoutcomes'),
        get_string('status', 'local_criteriaoutcomes')];
    foreach ($items as $item) {
        $table->data[] = [s($item->entitytype . ' #' . ($item->entityid ?? '—')), s(strtoupper($item->action)), s($item->status)];
    }
    echo html_writer::table($table);
    if ($undoanalysis !== null) {
        echo $OUTPUT->heading(get_string('undopreview', 'local_criteriaoutcomes'), 4);
        foreach ($undoanalysis as $decision) {
            echo html_writer::div(s('#' . $decision['itemid'] . ': ' . strtoupper($decision['decision'])), 'alert alert-info');
        }
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
        foreach (['id' => $courseid, 'batchid' => $batchid, 'sesskey' => sesskey(), 'action' => 'applyundo'] as $name => $value) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
        }
        echo html_writer::checkbox('archiveused', 1, false, get_string('archiveusedconfirm', 'local_criteriaoutcomes'));
        echo html_writer::tag(
            'button',
            get_string('confirmundo', 'local_criteriaoutcomes'),
            ['type' => 'submit', 'class' => 'btn btn-danger d-block mt-3']
        );
        echo html_writer::end_tag('form');
    } else if ($batch->operation === 'import' && $batch->status === 'success') {
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
        foreach (['id' => $courseid, 'batchid' => $batchid, 'sesskey' => sesskey(), 'action' => 'analyseundo'] as $name => $value) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
        }
        echo html_writer::tag(
            'button',
            get_string('safeundo', 'local_criteriaoutcomes'),
            ['type' => 'submit', 'class' => 'btn btn-warning']
        );
        echo html_writer::end_tag('form');
    }
}

$table = new html_table();
$table->head = ['#', get_string('provider', 'local_criteriaoutcomes'), get_string('source', 'local_criteriaoutcomes'),
    get_string('curriculum', 'local_criteriaoutcomes'), get_string('date'), get_string('user'),
    get_string('status', 'local_criteriaoutcomes'), get_string('actions', 'local_criteriaoutcomes')];
foreach ($batches as $batch) {
    $user = $batch->userid ? fullname($batch) : get_string('anonymoususer', 'local_criteriaoutcomes');
    $table->data[] = [$batch->id, s($batch->provider), s($batch->sourceid ?? '—'), s($batch->curriculumkey),
        userdate($batch->timecreated), s($user), s(strtoupper($batch->status)),
        html_writer::link(new moodle_url($PAGE->url, ['batchid' => $batch->id]), get_string('view'))];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
