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
 * Teacher-facing official AEBOE curriculum import flow.
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
require_capability('local/criteriaoutcomes:import', $context);
$PAGE->set_url('/local/criteriaoutcomes/boe.php', ['id' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('boeimport', 'local_criteriaoutcomes'));
$PAGE->set_heading(format_string($course->fullname));

/**
 * Extract display text from AEBOE's scalar or coded values.
 */
function local_criteriaoutcomes_boe_value(mixed $value): string {
    if (is_array($value)) {
        $value = $value['texto'] ?? $value['value'] ?? $value['id'] ?? '';
    }
    return trim((string)$value);
}

/**
 * Locate the official identifier without accepting a caller-provided URL.
 */
function local_criteriaoutcomes_boe_identifier(array $record): string {
    foreach (['identificador', 'id', 'id_norma', 'codigo'] as $field) {
        $value = strtoupper(local_criteriaoutcomes_boe_value($record[$field] ?? ''));
        if (preg_match('/^BOE-A-\d{4}-\d{1,8}$/', $value)) {
            return $value;
        }
    }
    $encoded = json_encode($record);
    return preg_match('/BOE-A-\d{4}-\d{1,8}/i', (string)$encoded, $match) ? strtoupper($match[0]) : '';
}

$provider = new \local_criteriaoutcomes\provider\boe_provider();
$importer = new \local_criteriaoutcomes\service\import_service();
$action = optional_param('action', '', PARAM_ALPHAEXT);
$results = [];
$curricula = null;
$preview = null;
$notice = null;
$offset = 0;
$query = '';
$selectedgroup = optional_param('selectiongroup', '', PARAM_TEXT);
$selectedscaleid = 0;

if ($action !== '') {
    require_sesskey();
    try {
        if ($action === 'search') {
            $query = required_param('query', PARAM_TEXT);
            $offset = optional_param('offset', 0, PARAM_INT);
            $results = $provider->search($query, $offset, 20, optional_param('force', 0, PARAM_BOOL));
        } else if ($action === 'load') {
            $identifier = required_param('identifier', PARAM_ALPHANUMEXT);
            $family = required_param('family', PARAM_ALPHA);
            $curricula = $provider->curricula($identifier, $family, optional_param('force', 0, PARAM_BOOL));
            $token = bin2hex(random_bytes(16));
            $SESSION->local_criteriaoutcomes_boe[$token] = $curricula;
        } else if ($action === 'selectgroup') {
            $token = required_param('token', PARAM_ALPHANUM);
            $curricula = $SESSION->local_criteriaoutcomes_boe[$token] ?? null;
            if (!is_array($curricula)) {
                throw new invalid_parameter_exception(get_string('previewexpired', 'local_criteriaoutcomes'));
            }
        } else if ($action === 'preview') {
            $token = required_param('token', PARAM_ALPHANUM);
            $index = required_param('curriculumindex', PARAM_INT);
            $scaleid = required_param('scaleid', PARAM_INT);
            $stored = $SESSION->local_criteriaoutcomes_boe[$token] ?? null;
            if (!is_array($stored) || !isset($stored[$index])) {
                throw new invalid_parameter_exception(get_string('previewexpired', 'local_criteriaoutcomes'));
            }
            $preview = $importer->preview($courseid, $stored[$index], $scaleid);
            $previewtoken = bin2hex(random_bytes(16));
            $SESSION->local_criteriaoutcomes_boe_preview[$previewtoken] = [
                'curriculum' => $stored[$index], 'scaleid' => $scaleid,
            ];
        } else if ($action === 'confirm') {
            $previewtoken = required_param('previewtoken', PARAM_ALPHANUM);
            $stored = $SESSION->local_criteriaoutcomes_boe_preview[$previewtoken] ?? null;
            if (!is_array($stored)) {
                throw new invalid_parameter_exception(get_string('previewexpired', 'local_criteriaoutcomes'));
            }
            $selected = optional_param_array('sourcekeys', [], PARAM_ALPHANUM);
            if (!$selected) {
                throw new invalid_parameter_exception(get_string('selectatleastone', 'local_criteriaoutcomes'));
            }
            $summary = $importer->import($courseid, $stored['scaleid'], $stored['curriculum'], $selected);
            unset($SESSION->local_criteriaoutcomes_boe_preview[$previewtoken]);
            $notice = get_string('importcomplete', 'local_criteriaoutcomes', (object)$summary);
        } else if ($action === 'createscale') {
            $token = required_param('token', PARAM_ALPHANUM);
            $curricula = $SESSION->local_criteriaoutcomes_boe[$token] ?? null;
            if (!is_array($curricula)) {
                throw new invalid_parameter_exception(get_string('previewexpired', 'local_criteriaoutcomes'));
            }
            $selectedscaleid = (new \local_criteriaoutcomes\service\scale_template_service())->create(
                $courseid,
                required_param('template', PARAM_ALPHANUMEXT)
            );
            $notice = get_string('scaletemplatecreated', 'local_criteriaoutcomes');
        }
    } catch (Throwable $e) {
        $notice = $e->getMessage();
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('boeimport', 'local_criteriaoutcomes'));
echo html_writer::tag('p', get_string('boedisclaimer', 'local_criteriaoutcomes'), ['class' => 'alert alert-info']);
if ($notice) {
    echo $OUTPUT->notification(s($notice), in_array($action, ['confirm', 'createscale'], true) ? 'success' : 'warning');
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url, 'class' => 'mb-4']);
foreach (['id' => $courseid, 'sesskey' => sesskey(), 'action' => 'search'] as $name => $value) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
}
echo html_writer::label(get_string('boesearchlabel', 'local_criteriaoutcomes'), 'boe-query');
echo html_writer::empty_tag('input', ['id' => 'boe-query', 'name' => 'query', 'value' => $query,
    'required' => 'required', 'maxlength' => 250, 'class' => 'form-control mb-2']);
echo html_writer::tag('button', get_string('search'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

if ($results) {
    echo $OUTPUT->heading(get_string('boesearchresults', 'local_criteriaoutcomes'), 3);
    foreach ($results as $record) {
        $identifier = local_criteriaoutcomes_boe_identifier($record);
        if ($identifier === '') {
            continue;
        }
        $title = local_criteriaoutcomes_boe_value($record['titulo'] ?? $record['title'] ?? $identifier);
        $date = local_criteriaoutcomes_boe_value($record['fecha_publicacion'] ?? $record['fecha'] ?? '');
        $lastupdate = local_criteriaoutcomes_boe_value($record['fecha_actualizacion'] ?? '');
        $range = local_criteriaoutcomes_boe_value($record['rango'] ?? '');
        $number = local_criteriaoutcomes_boe_value($record['numero_oficial'] ?? '');
        $detectedfamily = \local_criteriaoutcomes\provider\boe_provider::detect_family($record);
        echo html_writer::start_div('card mb-3');
        echo html_writer::start_div('card-body');
        echo html_writer::tag('h4', s($title), ['class' => 'card-title']);
        if ($range || $number) {
            echo html_writer::tag('p', s(trim($range . ' ' . $number)), ['class' => 'mb-1']);
        }
        echo html_writer::tag('p', s($identifier), ['class' => 'mb-1']);
        if ($date) {
            echo html_writer::tag(
                'p',
                s(get_string('boepublicationdate', 'local_criteriaoutcomes', $date)),
                ['class' => 'mb-1']
            );
        }
        if ($lastupdate) {
            echo html_writer::tag(
                'p',
                s(get_string('boelastupdate', 'local_criteriaoutcomes', $lastupdate)),
                ['class' => 'mb-2']
            );
        }
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
        foreach (
            ['id' => $courseid, 'sesskey' => sesskey(), 'action' => 'load',
            'identifier' => $identifier] as $name => $value
        ) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
        }
        echo html_writer::label(get_string('educationfamily', 'local_criteriaoutcomes'), 'family-' . $identifier);
        echo html_writer::select(
            ['fp' => 'FP', 'eso' => 'ESO', 'bach' => get_string('bachillerato', 'local_criteriaoutcomes')],
            'family',
            $detectedfamily ?? '',
            ['' => get_string('choosedots')],
            ['id' => 'family-' . $identifier, 'required' => 'required', 'class' => 'custom-select mx-2']
        );
        echo html_writer::tag(
            'button',
            get_string('loadcurricula', 'local_criteriaoutcomes'),
            ['type' => 'submit', 'class' => 'btn btn-secondary']
        );
        echo html_writer::end_tag('form');
        echo html_writer::end_div();
        echo html_writer::end_div();
    }
}

if ($curricula !== null) {
    echo $OUTPUT->heading(get_string('selectcurriculum', 'local_criteriaoutcomes'), 3);
    if (!$curricula) {
        echo $OUTPUT->notification(get_string('nocurriculafound', 'local_criteriaoutcomes'), 'warning');
    } else {
        $scales = $importer->available_scales($courseid);
        $selector = new \local_criteriaoutcomes\service\curriculum_selection_service();
        $groups = $selector->groups($curricula);
        if ($selectedgroup !== '' && !isset($groups[$selectedgroup])) {
            $selectedgroup = '';
        }
        if ($selectedgroup === '') {
            echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url, 'class' => 'mb-4']);
            $hiddenparams = [
                'id' => $courseid,
                'sesskey' => sesskey(),
                'action' => 'selectgroup',
                'token' => $token,
            ];
            foreach (
                $hiddenparams as $name => $value
            ) {
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
            }
            $family = $curricula[array_key_first($curricula)]['metadata']['curriculumtype'] ?? '';
            $grouplabel = $family === 'fp' ? 'qualification' : 'courseband';
            echo html_writer::label(get_string($grouplabel, 'local_criteriaoutcomes'), 'selectiongroup');
            echo html_writer::select(
                array_combine(array_keys($groups), array_keys($groups)),
                'selectiongroup',
                '',
                ['' => get_string('choosedots')],
                ['id' => 'selectiongroup', 'required' => 'required', 'class' => 'form-control mb-3']
            );
            echo html_writer::tag('button', get_string('continue'), ['type' => 'submit', 'class' => 'btn btn-primary']);
            echo html_writer::end_tag('form');
            echo $OUTPUT->footer();
            exit;
        }
        $filteredcurricula = $selector->filter($curricula, $selectedgroup);
        echo $OUTPUT->heading(get_string('recommendedscales', 'local_criteriaoutcomes'), 4);
        echo html_writer::tag('p', get_string('scaletemplatehelp', 'local_criteriaoutcomes'));
        $templateservice = new \local_criteriaoutcomes\service\scale_template_service();
        foreach (
            [
                \local_criteriaoutcomes\service\scale_template_service::NUMERIC => 'scaletemplatenumericname',
                \local_criteriaoutcomes\service\scale_template_service::ACHIEVEMENT => 'scaletemplateachievementname',
            ] as $template => $namestring
        ) {
            echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url, 'class' => 'mb-2']);
            foreach (
                ['id' => $courseid, 'sesskey' => sesskey(), 'action' => 'createscale',
                    'token' => $token, 'template' => $template, 'selectiongroup' => $selectedgroup] as $name => $value
            ) {
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
            }
            echo html_writer::tag('span', s(get_string($namestring, 'local_criteriaoutcomes')), ['class' => 'mr-2']);
            echo html_writer::tag(
                'span',
                s(implode(' · ', $templateservice->levels($template))),
                ['class' => 'd-block text-muted']
            );
            $existingid = $templateservice->existing_id($courseid, $template);
            echo html_writer::tag(
                'span',
                get_string($existingid ? 'scaleavailablecourse' : 'scaleavailabletemplate', 'local_criteriaoutcomes'),
                ['class' => 'badge badge-info mr-2']
            );
            echo html_writer::tag('button', get_string('createsandselectscale', 'local_criteriaoutcomes'), [
                'type' => 'submit', 'class' => 'btn btn-secondary',
            ]);
            echo html_writer::end_tag('form');
        }
        echo $OUTPUT->heading(get_string('existingscales', 'local_criteriaoutcomes'), 4);
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
        foreach (
            ['id' => $courseid, 'sesskey' => sesskey(), 'action' => 'preview', 'token' => $token,
                'selectiongroup' => $selectedgroup] as $name => $value
        ) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
        }
        $options = [];
        foreach ($filteredcurricula as $index => $curriculum) {
            $options[$index] = $groups[$selectedgroup][$index];
        }
        echo html_writer::label(get_string('curriculum', 'local_criteriaoutcomes'), 'curriculumindex');
        echo html_writer::select(
            $options,
            'curriculumindex',
            '',
            ['' => get_string('choosedots')],
            ['id' => 'curriculumindex', 'required' => 'required', 'class' => 'form-control mb-3']
        );
        echo html_writer::label(get_string('selectscale', 'local_criteriaoutcomes'), 'boe-scale');
        echo html_writer::select(
            $scales,
            'scaleid',
            $selectedscaleid,
            [0 => get_string('choosedots')],
            ['id' => 'boe-scale', 'required' => 'required', 'class' => 'form-control mb-3']
        );
        echo html_writer::tag(
            'button',
            get_string('preview', 'local_criteriaoutcomes'),
            ['type' => 'submit', 'class' => 'btn btn-primary']
        );
        echo html_writer::end_tag('form');
    }
}

if ($preview !== null) {
    echo $OUTPUT->heading(get_string('previewtitle', 'local_criteriaoutcomes'), 3);
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
    foreach (
        ['id' => $courseid, 'sesskey' => sesskey(), 'action' => 'confirm',
        'previewtoken' => $previewtoken] as $name => $value
    ) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
    }
    foreach ($preview['parents'] as $parent) {
        echo html_writer::tag('h4', s($parent['code'] . ' — ' . $parent['name']));
        foreach ($parent['criteria'] as $criterion) {
            $checked = $criterion['status'] !== \local_criteriaoutcomes\service\import_service::STATUS_CONFLICT;
            $label = $criterion['code'] . ' — ' . $criterion['name'] . ' [' . strtoupper($criterion['status']) . ']';
            echo html_writer::div(html_writer::checkbox('sourcekeys[]', $criterion['sourcekey'], $checked, s($label)), 'mb-2');
        }
    }
    foreach ($preview['removed'] as $removed) {
        echo html_writer::div(
            s($removed['code'] . ' — ' . $removed['name'] . ' [REMOVED_FROM_SOURCE]'),
            'alert alert-warning'
        );
    }
    echo html_writer::tag(
        'button',
        get_string('confirmimport', 'local_criteriaoutcomes'),
        ['type' => 'submit', 'class' => 'btn btn-primary mt-3']
    );
    echo html_writer::end_tag('form');
}
echo $OUTPUT->footer();
