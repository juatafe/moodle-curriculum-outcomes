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
//

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
$token = optional_param('token', '', PARAM_ALPHANUM);
$flowstate = null;
$viewstep = 1;
$statehandler = new \local_criteriaoutcomes\service\guided_import_state();

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
            $flowstate = $statehandler->create($curricula, $identifier, $family, $courseid);
            $SESSION->local_criteriaoutcomes_boe[$token] = $flowstate;
            $viewstep = 2;
        } else if ($action === 'selectgroup') {
            $token = required_param('token', PARAM_ALPHANUM);
            $flowstate = $statehandler->require_course($SESSION->local_criteriaoutcomes_boe[$token] ?? [], $courseid);
            if (!is_array($flowstate) || !isset($flowstate['curricula'])) {
                throw new invalid_parameter_exception(get_string('previewexpired', 'local_criteriaoutcomes'));
            }
            $flowstate = $statehandler->select_group(
                $flowstate,
                required_param('selectiongroup', PARAM_TEXT)
            );
            $SESSION->local_criteriaoutcomes_boe[$token] = $flowstate;
            $curricula = $flowstate['curricula'];
            $viewstep = 2;
        } else if ($action === 'cleargroup') {
            $flowstate = $statehandler->require_course($SESSION->local_criteriaoutcomes_boe[$token] ?? [], $courseid);
            if (!is_array($flowstate) || !isset($flowstate['curricula'])) {
                throw new invalid_parameter_exception(get_string('previewexpired', 'local_criteriaoutcomes'));
            }
            $flowstate = $statehandler->clear_group($flowstate);
            $SESSION->local_criteriaoutcomes_boe[$token] = $flowstate;
            $curricula = $flowstate['curricula'];
            $viewstep = 2;
        } else if ($action === 'selectcurriculum') {
            $token = required_param('token', PARAM_ALPHANUM);
            $flowstate = $statehandler->require_course($SESSION->local_criteriaoutcomes_boe[$token] ?? [], $courseid);
            if (!is_array($flowstate) || !isset($flowstate['curricula'])) {
                throw new invalid_parameter_exception(get_string('previewexpired', 'local_criteriaoutcomes'));
            }
            $flowstate = $statehandler->select_curriculum(
                $flowstate,
                required_param('curriculumindex', PARAM_INT)
            );
            $SESSION->local_criteriaoutcomes_boe[$token] = $flowstate;
            $viewstep = 3;
        } else if ($action === 'valuation') {
            $flowstate = $statehandler->require_course($SESSION->local_criteriaoutcomes_boe[$token] ?? [], $courseid);
            if (!is_array($flowstate) || !isset($flowstate['curricula'][$flowstate['curriculumindex']])) {
                throw new invalid_parameter_exception(get_string('previewexpired', 'local_criteriaoutcomes'));
            }
            $valuation = required_param('valuation', PARAM_ALPHA);
            if ($valuation === 'achievement' || $valuation === 'numeric') {
                $template = $valuation === 'achievement' ?
                    \local_criteriaoutcomes\service\scale_template_service::ACHIEVEMENT :
                    \local_criteriaoutcomes\service\scale_template_service::NUMERIC;
                $scaleid = (new \local_criteriaoutcomes\service\scale_template_service())->create($courseid, $template);
            } else if ($valuation === 'existing') {
                $scaleid = required_param('scaleid', PARAM_INT);
            } else {
                throw new invalid_parameter_exception(get_string('required'));
            }
            $preservedselection = $flowstate['selectedsourcekeys'];
            $flowstate = $statehandler->select_valuation($flowstate, $valuation, $scaleid);
            $curriculum = $flowstate['curricula'][$flowstate['curriculumindex']];
            $preview = $importer->preview($courseid, $curriculum, $scaleid);
            $previewtoken = bin2hex(random_bytes(16));
            $SESSION->local_criteriaoutcomes_boe_preview[$previewtoken] = [
                'curriculum' => $curriculum,
                'scaleid' => $scaleid,
                'courseid' => $courseid,
                'flowtoken' => $token,
                'selectedsourcekeys' => $flowstate['selectedsourcekeys'] === null ? null : $preservedselection,
            ];
            $SESSION->local_criteriaoutcomes_boe[$token] = $flowstate;
            $viewstep = 4;
        } else if ($action === 'backcurriculum') {
            $flowstate = $statehandler->require_course($SESSION->local_criteriaoutcomes_boe[$token] ?? [], $courseid);
            if (!is_array($flowstate) || !isset($flowstate['curricula'])) {
                throw new invalid_parameter_exception(get_string('previewexpired', 'local_criteriaoutcomes'));
            }
            $curricula = $flowstate['curricula'];
            $viewstep = 2;
        } else if ($action === 'backsource') {
            $flowstate = $statehandler->require_course($SESSION->local_criteriaoutcomes_boe[$token] ?? [], $courseid);
            if (!is_array($flowstate)) {
                throw new invalid_parameter_exception(get_string('previewexpired', 'local_criteriaoutcomes'));
            }
            $query = $flowstate['identifier'];
            $viewstep = 1;
        } else if ($action === 'backvaluation') {
            $previewtoken = required_param('previewtoken', PARAM_ALPHANUM);
            $stored = $SESSION->local_criteriaoutcomes_boe_preview[$previewtoken] ?? null;
            if (!is_array($stored) || ($stored['courseid'] ?? 0) !== $courseid) {
                throw new invalid_parameter_exception(get_string('previewexpired', 'local_criteriaoutcomes'));
            }
            $token = $stored['flowtoken'];
            $flowstate = $statehandler->require_course($SESSION->local_criteriaoutcomes_boe[$token] ?? [], $courseid);
            if (!is_array($flowstate)) {
                throw new invalid_parameter_exception(get_string('previewexpired', 'local_criteriaoutcomes'));
            }
            $flowstate['selectedsourcekeys'] = optional_param_array('sourcekeys', [], PARAM_ALPHANUMEXT);
            $SESSION->local_criteriaoutcomes_boe[$token] = $flowstate;
            unset($SESSION->local_criteriaoutcomes_boe_preview[$previewtoken]);
            $viewstep = 3;
        } else if ($action === 'previewselection') {
            $previewtoken = required_param('previewtoken', PARAM_ALPHANUM);
            $stored = $SESSION->local_criteriaoutcomes_boe_preview[$previewtoken] ?? null;
            if (!is_array($stored) || ($stored['courseid'] ?? 0) !== $courseid) {
                throw new invalid_parameter_exception(get_string('previewexpired', 'local_criteriaoutcomes'));
            }
            $preview = $importer->preview($courseid, $stored['curriculum'], $stored['scaleid']);
            $selectionmode = required_param('selectionmode', PARAM_ALPHA);
            $selected = optional_param_array('sourcekeys', [], PARAM_ALPHANUMEXT);
            if ($selectionmode === 'all') {
                $selected = [];
                foreach ($preview['parents'] as $parent) {
                    foreach ($parent['criteria'] as $criterion) {
                        if ($criterion['status'] !== \local_criteriaoutcomes\service\import_service::STATUS_CONFLICT) {
                            $selected[] = $criterion['sourcekey'];
                        }
                    }
                }
            } else if ($selectionmode === 'none') {
                $selected = [];
            }
            $stored['selectedsourcekeys'] = $selected;
            $SESSION->local_criteriaoutcomes_boe_preview[$previewtoken] = $stored;
            $viewstep = 4;
        } else if ($action === 'confirm') {
            $previewtoken = required_param('previewtoken', PARAM_ALPHANUM);
            $stored = $SESSION->local_criteriaoutcomes_boe_preview[$previewtoken] ?? null;
            if (!is_array($stored) || ($stored['courseid'] ?? 0) !== $courseid) {
                throw new invalid_parameter_exception(get_string('previewexpired', 'local_criteriaoutcomes'));
            }
            $selected = optional_param_array('sourcekeys', [], PARAM_ALPHANUMEXT);
            if (!$selected) {
                throw new invalid_parameter_exception(get_string('selectatleastone', 'local_criteriaoutcomes'));
            }
            $summary = $importer->import($courseid, $stored['scaleid'], $stored['curriculum'], $selected);
            unset($SESSION->local_criteriaoutcomes_boe_preview[$previewtoken]);
            $notice = get_string('importcomplete', 'local_criteriaoutcomes', (object)$summary);
        }
    } catch (Throwable $e) {
        $notice = $e->getMessage();
    }
}

if ($viewstep > 1 && $flowstate === null && $token !== '') {
    $flowstate = $statehandler->require_course($SESSION->local_criteriaoutcomes_boe[$token] ?? [], $courseid);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('boeimport', 'local_criteriaoutcomes'));
echo html_writer::tag('p', get_string('boedisclaimer', 'local_criteriaoutcomes'), ['class' => 'alert alert-info']);
if ($notice) {
    echo $OUTPUT->notification(s($notice), $action === 'confirm' ? 'success' : 'warning');
}

$steplabels = [
    1 => get_string('importstepsource', 'local_criteriaoutcomes'),
    2 => get_string('importstepcurriculum', 'local_criteriaoutcomes'),
    3 => get_string('importstepvaluation', 'local_criteriaoutcomes'),
    4 => get_string('importstepreview', 'local_criteriaoutcomes'),
];
echo html_writer::div(
    get_string('importstep', 'local_criteriaoutcomes', (object)['current' => $viewstep, 'label' => $steplabels[$viewstep]]),
    'local-criteriaoutcomes-step mb-3',
    ['aria-live' => 'polite']
);
echo html_writer::start_div('local-criteriaoutcomes-flow');

if ($viewstep === 1) {
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
            // Show detected family or selector.
            if ($detectedfamily) {
                echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'load']);
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'identifier', 'value' => $identifier]);
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'family', 'value' => $detectedfamily]);
                echo html_writer::tag(
                    'p',
                    get_string('detectedfamily', 'local_criteriaoutcomes', $detectedfamily),
                    ['class' => 'mb-2']
                );
                echo html_writer::tag(
                    'button',
                    get_string('usecurriculum', 'local_criteriaoutcomes'),
                    ['type' => 'submit', 'class' => 'btn btn-primary', 'style' => 'width: 100%']
                );
                echo html_writer::end_tag('form');
                echo html_writer::end_div();
                echo html_writer::end_div();
                echo html_writer::end_div();
                continue;
            } else {
                echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'load']);
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'identifier', 'value' => $identifier]);
                echo html_writer::label(get_string('educationfamily', 'local_criteriaoutcomes'), 'family-' . $identifier);
                echo html_writer::select(
                    ['fp' => 'FP', 'eso' => 'ESO', 'bach' => get_string('bachillerato', 'local_criteriaoutcomes')],
                    'family',
                    '',
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
                echo html_writer::end_div();
            }
        }
    }
} else if ($viewstep === 2) {
    // Step 2: Select subject (ESO/Bach) or continue with FP qualification.
    $service = new \local_criteriaoutcomes\service\curriculum_selection_service();
    $curricula = $flowstate['curricula'] ?? [];
    $family = $flowstate['family'] ?? '';

    if ($family === 'fp') {
        // FP flow: show qualification → module selector.
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url, 'class' => 'mb-4']);
        foreach (['id' => $courseid, 'sesskey' => sesskey(), 'token' => $token, 'action' => 'selectgroup'] as $name => $value) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
        }
        $groups = $service->groups($curricula);
        echo html_writer::label(get_string('qualification', 'local_criteriaoutcomes'), 'group-' . $flowstate['identifier']);
        echo html_writer::select(
            $groups,
            'selectiongroup',
            $flowstate['selectiongroup'] ?? '',
            ['' => get_string('choosedots')],
            ['class' => 'custom-select mb-2']
        );
        echo html_writer::tag(
            'button',
            get_string('continue', 'local_criteriaoutcomes'),
            ['type' => 'submit', 'class' => 'btn btn-primary']
        );
        echo html_writer::end_tag('form');
    } else {
        // ESO/Bach flow: show subject selector first.
        $subjects = $service->subjects($curricula);
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url, 'class' => 'mb-4']);
        foreach (['id' => $courseid, 'sesskey' => sesskey(), 'token' => $token, 'action' => 'selectgroup'] as $name => $value) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
        }
        echo html_writer::label(get_string('selectsubject', 'local_criteriaoutcomes'), 'subject-' . $flowstate['identifier']);
        echo html_writer::select(
            array_combine($subjects, $subjects),
            'selectiongroup',
            $flowstate['subject'] ?? '',
            ['' => get_string('choosedots')],
            ['class' => 'custom-select mb-2', 'id' => 'subject-selector']
        );
        echo html_writer::tag(
            'button',
            get_string('continue', 'local_criteriaoutcomes'),
            ['type' => 'submit', 'class' => 'btn btn-primary']
        );
        echo html_writer::end_tag('form');

        // Check if selected subject has multiple variants.
        $selectedsubject = $flowstate['subject'] ?? '';
        if ($selectedsubject !== '') {
            $variants = $service->variants_for_subject($curricula, $selectedsubject);
            if (count($variants) > 1) {
                // Multiple variants - show course/band selector.
                echo html_writer::start_div('local-criteriaoutcomes-variants mb-3');
                echo html_writer::select(
                    array_combine($variants, $variants),
                    'selectiongroup',
                    $flowstate['selectiongroup'] ?? '',
                    ['' => get_string('choosedots')],
                    ['class' => 'custom-select mb-2']
                );
                echo html_writer::tag(
                    'button',
                    get_string('continue', 'local_criteriaoutcomes'),
                    ['type' => 'submit', 'class' => 'btn btn-primary']
                );
                echo html_writer::end_div();
            }
        }
    }
} else if ($viewstep === 3) {
    // Step 3: Valuation selection.
    $service = new \local_criteriaoutcomes\service\curriculum_selection_service();
    $curricula = $flowstate['curricula'] ?? [];
    $family = $flowstate['family'] ?? '';

    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url, 'class' => 'mb-4']);
    foreach (['id' => $courseid, 'sesskey' => sesskey(), 'token' => $token, 'action' => 'valuation'] as $name => $value) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
    }

    $valuationoptions = [
        'achievement' => get_string('valuationachievement', 'local_criteriaoutcomes'),
        'numeric' => get_string('valuationnumeric', 'local_criteriaoutcomes'),
        'existing' => get_string('valuationexisting', 'local_criteriaoutcomes'),
    ];
    if ($family === 'fp') {
        // FP valuation.
        echo html_writer::label(get_string('choosevaluation', 'local_criteriaoutcomes'), 'valuation');
        echo html_writer::select(
            $valuationoptions,
            'valuation',
            '',
            ['' => get_string('choosedots')],
            ['id' => 'valuation', 'class' => 'form-control mb-3']
        );
        echo html_writer::select(
            [],
            'scaleid',
            $flowstate['scaleid'] ?? 0,
            [],
            ['class' => 'd-none']
        );
    } else {
        // ESO/Bach valuation - no default selection.
        echo html_writer::label(get_string('choosevaluation', 'local_criteriaoutcomes'), 'valuation');
        echo html_writer::select(
            $valuationoptions,
            'valuation',
            '',
            ['' => get_string('choosedots')],
            ['id' => 'valuation', 'class' => 'form-control mb-3']
        );
        echo html_writer::select(
            [],
            'scaleid',
            0,
            [],
            ['class' => 'd-none']
        );
    }

    echo html_writer::tag(
        'button',
        get_string('continue', 'local_criteriaoutcomes'),
        ['type' => 'submit', 'class' => 'btn btn-primary']
    );
    echo html_writer::end_tag('form');
} else if ($viewstep === 4) {
    // Step 4: Review and import.
    $previewtoken = optional_param('previewtoken', '', PARAM_ALPHANUM);
    $stored = $SESSION->local_criteriaoutcomes_boe_preview[$previewtoken] ?? null;
    if (is_array($stored) && ($stored['courseid'] ?? 0) === $courseid) {
        $preview = $importer->preview($courseid, $stored['curriculum'], $stored['scaleid']);
        $selectionmode = optional_param('selectionmode', 'partial', PARAM_ALPHA);
        $selected = optional_param_array('sourcekeys', [], PARAM_ALPHANUMEXT);
        if ($selectionmode === 'all') {
            $selected = [];
            foreach ($preview['parents'] as $parent) {
                foreach ($parent['criteria'] as $criterion) {
                    if ($criterion['status'] !== \local_criteriaoutcomes\service\import_service::STATUS_CONFLICT) {
                        $selected[] = $criterion['sourcekey'];
                    }
                }
            }
        } else if ($selectionmode === 'none') {
            $selected = [];
        }
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url, 'class' => 'mb-4']);
        foreach (
            [
            'id' => $courseid,
            'sesskey' => sesskey(),
            'previewtoken' => $previewtoken,
            'action' => 'confirm',
            ] as $name => $value
        ) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
        }
        echo html_writer::tag('h3', get_string('preview', 'local_criteriaoutcomes'), ['class' => 'mb-3']);
        echo html_writer::start_tag('div', ['class' => 'row']);
        $open = true;
        foreach ($preview['parents'] as $parent) {
            $open = !$open;
            echo html_writer::start_tag('details', ['open' => $open, 'class' => 'mb-3']);
            echo html_writer::summary(get_string('criterios', 'local_criteriaoutcomes') . ' ' . $parent['name']);
            foreach ($parent['criteria'] as $criterion) {
                $checked = in_array($criterion['sourcekey'], $selected, true) ? 'checked' : '';
                echo html_writer::tag(
                    'label',
                    s($criterion['name']),
                    ['class' => 'form-check-label', 'for' => 'criterion-' . $criterion['sourcekey']]
                );
                echo html_writer::empty_tag('input', [
                    'type' => 'checkbox',
                    'name' => 'sourcekeys[' . $criterion['sourcekey'] . ']',
                    'value' => $criterion['sourcekey'],
                    'class' => 'form-check-input',
                    'id' => 'criterion-' . $criterion['sourcekey'],
                    $checked,
                ]);
            }
            echo html_writer::end_tag('details');
        }
        echo html_writer::end_tag('div');
        echo html_writer::tag(
            'p',
            get_string('selected_count', 'local_criteriaoutcomes', count($selected))
                . ' ' . get_string('criterios', 'local_criteriaoutcomes'),
            ['class' => 'mb-3']
        );
        echo html_writer::tag(
            'button',
            get_string('import_all', 'local_criteriaoutcomes'),
            ['type' => 'submit', 'name' => 'selectionmode', 'value' => 'all', 'class' => 'btn btn-primary mr-2']
        );
        echo html_writer::tag(
            'button',
            get_string('deselect_all', 'local_criteriaoutcomes'),
            ['type' => 'submit', 'name' => 'selectionmode', 'value' => 'none', 'class' => 'btn btn-secondary']
        );
        echo html_writer::tag(
            'button',
            get_string('import_selected', 'local_criteriaoutcomes'),
            ['type' => 'submit', 'class' => 'btn btn-success']
        );
        echo html_writer::end_tag('form');
    }
}
echo html_writer::end_div('local-criteriaoutcomes-flow');
