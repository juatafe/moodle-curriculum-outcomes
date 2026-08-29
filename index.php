<?php
// This file is part of Moodle - http://moodle.org/
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
 * Course curriculum and evidence page.
 *
 *  local_criteriaoutcomes
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
if (!defined('MOODLE_INTERNAL')) {
    throw new RuntimeException('Moodle config.php was not found.');
}
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/grade/grade_outcome.php');
require_once($CFG->libdir . '/grade/grade_scale.php');

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/criteriaoutcomes:view', $context);

$jsonpage = defined('LOCAL_CRITERIAOUTCOMES_JSON_IMPORT');
$PAGE->set_url('/local/criteriaoutcomes/' . ($jsonpage ? 'json.php' : 'index.php'), ['id' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string($jsonpage ? 'jsonimport' : 'pluginname', 'local_criteriaoutcomes'));
$PAGE->set_heading(format_string($course->fullname));

$service = new \local_criteriaoutcomes\service\import_service();
$provider = new \local_criteriaoutcomes\provider\json_provider();
$preview = null;
$notice = null;

if (!$CFG->enableoutcomes) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('pluginname', 'local_criteriaoutcomes'));
    $message = get_string('outcomesdisabled', 'local_criteriaoutcomes');
    if (is_siteadmin()) {
        $message .= ' ' . html_writer::link(
            new moodle_url('/admin/search.php', ['query' => 'enableoutcomes']),
            get_string('opensettings', 'local_criteriaoutcomes')
        );
    }
    echo $OUTPUT->notification($message, 'warning');
    echo $OUTPUT->footer();
    exit;
}

if (optional_param('confirmimport', 0, PARAM_BOOL)) {
    require_capability('local/criteriaoutcomes:import', $context);
    require_sesskey();
    try {
        $token = required_param('previewtoken', PARAM_ALPHANUM);
        $storedpreview = $SESSION->local_criteriaoutcomes_preview[$token] ?? null;
        if (
            !is_array($storedpreview)
            || ($storedpreview['courseid'] ?? 0) !== $courseid
            || !is_array($storedpreview['curriculum'] ?? null)
        ) {
            throw new InvalidArgumentException(get_string('previewexpired', 'local_criteriaoutcomes'));
        }
        $curriculum = $storedpreview['curriculum'];
        $counts = $service->import($courseid, required_param('scaleid', PARAM_INT), $curriculum);
        unset($SESSION->local_criteriaoutcomes_preview[$token]);
        $notice = get_string('importcomplete', 'local_criteriaoutcomes', (object)$counts);
        if ($jsonpage) {
            redirect(new moodle_url('/local/criteriaoutcomes/index.php', ['id' => $courseid]), $notice);
        }
    } catch (Throwable $e) {
        $notice = $e->getMessage();
    }
}

$scales = $service->available_scales($courseid);
$form = new \local_criteriaoutcomes\form\import_form(null, [
    'courseid' => $courseid,
    'scales' => $scales,
]);
if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/criteriaoutcomes/index.php', ['id' => $courseid]));
}
$formdata = $form->get_data();
if ($formdata) {
    require_capability('local/criteriaoutcomes:import', $context);
    try {
        if ($formdata->valuation === 'existing') {
            $scaleid = (int)$formdata->scaleid;
        } else {
            $template = $formdata->valuation === 'achievement' ?
                \local_criteriaoutcomes\service\scale_template_service::ACHIEVEMENT :
                \local_criteriaoutcomes\service\scale_template_service::NUMERIC;
            $scaleid = (new \local_criteriaoutcomes\service\scale_template_service())->create($courseid, $template);
        }
        $content = $form->content();
        $preview = $service->preview($courseid, $provider->parse($content), $scaleid);
        $preview['scaleid'] = $scaleid;
        $previewtoken = bin2hex(random_bytes(16));
        $SESSION->local_criteriaoutcomes_preview[$previewtoken] = [
            'courseid' => $courseid,
            'curriculum' => $preview,
        ];
        $preview['previewtoken'] = $previewtoken;
    } catch (Throwable $e) {
        $notice = $e->getMessage();
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($jsonpage ? 'jsonimport' : 'pluginname', 'local_criteriaoutcomes'));
if (!$jsonpage) {
    echo $OUTPUT->heading(get_string('importcurriculum', 'local_criteriaoutcomes'), 2);
}
if (!$jsonpage && has_capability('local/criteriaoutcomes:import', $context)) {
    echo html_writer::link(
        new moodle_url('/local/criteriaoutcomes/boe.php', ['id' => $courseid]),
        get_string('importfromboe', 'local_criteriaoutcomes'),
        ['class' => 'btn btn-primary mb-3 mr-2']
    );
    echo html_writer::link(
        new moodle_url('/local/criteriaoutcomes/json.php', ['id' => $courseid]),
        get_string('importfromjson', 'local_criteriaoutcomes'),
        ['class' => 'btn btn-primary mb-3 mr-2']
    );
}
if (!$jsonpage && has_capability('local/criteriaoutcomes:manage', $context)) {
    echo $OUTPUT->heading(get_string('managecurriculum', 'local_criteriaoutcomes'), 2);
    echo html_writer::link(
        new moodle_url('/local/criteriaoutcomes/import_history.php', ['id' => $courseid]),
        get_string('importhistory', 'local_criteriaoutcomes'),
        ['class' => 'btn btn-secondary mb-3 mr-2']
    );
    echo html_writer::link(
        new moodle_url('/local/criteriaoutcomes/curriculum_manage.php', ['id' => $courseid]),
        get_string('managecurriculum', 'local_criteriaoutcomes'),
        ['class' => 'btn btn-secondary mb-3']
    );
}
if (!$jsonpage && has_capability('local/criteriaoutcomes:mapquiz', $context)) {
    echo $OUTPUT->heading(get_string('assessmentmappings', 'local_criteriaoutcomes'), 2);
    echo html_writer::link(
        new moodle_url('/local/criteriaoutcomes/quiz.php', ['id' => $courseid]),
        get_string('quizcriteriamapping', 'local_criteriaoutcomes'),
        ['class' => 'btn btn-secondary mb-3']
    );
}
if ($notice) {
    $notificationtype = str_contains($notice, (string)get_string('imported', 'local_criteriaoutcomes')) ? 'success' : 'info';
    echo $OUTPUT->notification(s($notice), $notificationtype);
}

if ($preview) {
    echo $OUTPUT->heading(get_string('previewtitle', 'local_criteriaoutcomes'), 3);
    echo html_writer::tag('p', s($preview['metadata']['sourcename']));
    echo html_writer::start_tag('div', ['class' => 'local-criteriaoutcomes-preview']);
    foreach ($preview['parents'] as $parent) {
        echo html_writer::tag('h4', s($parent['code'] . ' — ' . $parent['name']), ['class' => 'mt-3']);
        echo html_writer::start_tag('ul', ['class' => 'list-group mb-3']);
        foreach ($parent['criteria'] as $criterion) {
            $badgeclass = ['new' => 'success', 'existing' => 'secondary', 'text_changed' => 'warning',
                'scale_changed' => 'warning', 'text_and_scale_changed' => 'warning',
                'metadata_changed' => 'info', 'conflict' => 'danger'][$criterion['status']];
            $badge = html_writer::span(
                get_string('status' . $criterion['status'], 'local_criteriaoutcomes'),
                'badge bg-' . $badgeclass . ' ml-2'
            );
            $warning = '';
            if ($criterion['status'] === 'conflict') {
                $warning = html_writer::div(get_string('conflictwarning', 'local_criteriaoutcomes'), 'text-danger');
            } else if (!$criterion['scalesafe']) {
                $warning = html_writer::div(get_string($criterion['hasgrades'] ? 'scalegradesblocked' :
                    'scaleitemsblocked', 'local_criteriaoutcomes'), 'text-danger');
            }
            echo html_writer::tag(
                'li',
                s($criterion['code'] . ' — ' . $criterion['name']) . $badge . $warning,
                ['class' => 'list-group-item']
            );
        }
        echo html_writer::end_tag('ul');
    }
    echo html_writer::end_tag('div');
    if ($scales) {
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url, 'class' => 'mt-3']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirmimport', 'value' => 1]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'scaleid', 'value' => $preview['scaleid']]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'previewtoken',
            'value' => $preview['previewtoken']]);
        echo html_writer::tag(
            'button',
            get_string('confirmimport', 'local_criteriaoutcomes'),
            ['type' => 'submit', 'class' => 'btn btn-primary']
        );
        echo html_writer::end_tag('form');
    } else {
        echo $OUTPUT->notification(get_string('noscales', 'local_criteriaoutcomes'), 'warning');
    }
} else if ($jsonpage) {
    if (has_capability('local/criteriaoutcomes:import', $context)) {
        $form->display();
    }
}

if ($jsonpage) {
    echo $OUTPUT->footer();
    exit;
}

// Hierarchy and activity evidence from native outcome grade items.
$sql = "SELECT f.id AS frameworkid, f.name AS frameworkname, p.id AS parentid, p.code AS parentcode,
               p.name AS parentname, p.sortorder AS parentsort, c.id AS criterionid, c.code AS criterioncode,
               c.name AS criterionname, c.outcomeid, c.sortorder AS criterionsort,
               gi.id AS gradeitemid, gi.itemmodule, gi.iteminstance, gi.itemname
          FROM {local_crout_framework} f
          JOIN {local_crout_parent} p ON p.frameworkid = f.id
          JOIN {local_crout_criterion} c ON c.parentid = p.id
     LEFT JOIN {grade_items} gi ON gi.outcomeid = c.outcomeid AND gi.courseid = f.courseid
         WHERE f.courseid = :courseid AND f.archived = 0 AND p.archived = 0 AND c.archived = 0
      ORDER BY f.name, p.sortorder, c.sortorder, gi.id";
$rows = $DB->get_recordset_sql($sql, ['courseid' => $courseid]);
$tree = [];
foreach ($rows as $row) {
    if (!isset($tree[$row->frameworkid])) {
        $tree[$row->frameworkid] = ['name' => $row->frameworkname, 'parents' => []];
    }
    if (!isset($tree[$row->frameworkid]['parents'][$row->parentid])) {
        $tree[$row->frameworkid]['parents'][$row->parentid] = [
            'label' => $row->parentcode . ' — ' . $row->parentname,
            'criteria' => [],
        ];
    }
    if (!isset($tree[$row->frameworkid]['parents'][$row->parentid]['criteria'][$row->criterionid])) {
        $tree[$row->frameworkid]['parents'][$row->parentid]['criteria'][$row->criterionid] =
            ['label' => $row->criterioncode . ' — ' . $row->criterionname, 'evidence' => []];
    }
    if ($row->gradeitemid && $row->itemmodule && $row->iteminstance) {
        $cm = get_coursemodule_from_instance($row->itemmodule, $row->iteminstance, $courseid, false, IGNORE_MISSING);
        if ($cm) {
            $tree[$row->frameworkid]['parents'][$row->parentid]['criteria'][$row->criterionid]['evidence'][] =
                html_writer::link(
                    new moodle_url('/mod/' . $row->itemmodule . '/view.php', ['id' => $cm->id]),
                    format_string($cm->name)
                );
        }
    }
}
$rows->close();
if ($tree) {
    echo $OUTPUT->heading(get_string('currentcurriculum', 'local_criteriaoutcomes'), 2, 'mt-5');
    echo html_writer::tag('p', get_string('criteriaandevidence', 'local_criteriaoutcomes'));
    foreach ($tree as $framework) {
        echo html_writer::tag('h3', s($framework['name']));
        foreach ($framework['parents'] as $parent) {
            echo html_writer::tag('h4', s($parent['label']), ['class' => 'mt-3']);
            foreach ($parent['criteria'] as $criterion) {
                echo html_writer::tag('strong', s($criterion['label']));
                echo $criterion['evidence'] ? html_writer::alist($criterion['evidence']) :
                    html_writer::tag('p', get_string('noevidence', 'local_criteriaoutcomes'), ['class' => 'text-muted']);
            }
        }
    }
}
echo $OUTPUT->footer();
