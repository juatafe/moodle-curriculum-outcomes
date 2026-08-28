<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify it under the terms of the GNU GPL v3 or later.
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
 * Safe curriculum import service.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_criteriaoutcomes\service;

use local_criteriaoutcomes\curriculum\normalized_curriculum;
use local_criteriaoutcomes\criterion_display;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/grade/grade_outcome.php');
require_once($CFG->libdir . '/grade/grade_scale.php');
require_once($CFG->libdir . '/grade/grade_item.php');
require_once($CFG->libdir . '/grade/grade_grade.php');

/**
 * Coordinates preview, native Outcomes and plugin-owned mappings.
 */
class import_service {
    /**
     * New criterion.
     */
    public const STATUS_NEW = 'new';
    /**
     * Existing unchanged criterion.
     */
    public const STATUS_EXISTING = 'existing';
    /**
     * Criterion text differs.
     */
    public const STATUS_TEXT_CHANGED = 'text_changed';
    /**
     * Outcome scale differs.
     */
    public const STATUS_SCALE_CHANGED = 'scale_changed';
    /**
     * Criterion text and Outcome scale differ.
     */
    public const STATUS_TEXT_AND_SCALE_CHANGED = 'text_and_scale_changed';
    /**
     * Only plugin metadata differs.
     */
    public const STATUS_METADATA_CHANGED = 'metadata_changed';
    /**
     * Shortname ownership cannot be established.
     */
    public const STATUS_CONFLICT = 'conflict';
    /**
     * Criterion no longer returned by the source.
     */
    public const STATUS_REMOVED_FROM_SOURCE = 'removed_from_source';

    /**
     * Annotate criteria with ownership, changes and scale safety.
     */
    public function preview(int $courseid, array $curriculum, int $scaleid): array {
        global $DB;
        $curriculum = normalized_curriculum::normalize($curriculum);
        if (!$this->scale_available($courseid, $scaleid)) {
            throw new \moodle_exception('errorscale', 'local_criteriaoutcomes');
        }
        $framework = $DB->get_record('local_crout_framework', [
            'courseid' => $courseid, 'identitykey' => $this->identity($curriculum['metadata']),
        ]);
        $mappings = $framework ? $this->framework_mappings((int)$framework->id) : [];
        $seen = [];
        foreach ($curriculum['parents'] as &$parent) {
            foreach ($parent['criteria'] as &$criterion) {
                $seen[$criterion['sourcekey']] = true;
                $mapping = $mappings[$criterion['sourcekey']] ?? null;
                $outcome = $mapping ? \grade_outcome::fetch(['id' => $mapping->outcomeid]) : null;
                $shortnameoutcomes = $this->outcomes_by_shortname($courseid, $criterion['code']);
                $criterion += ['status' => self::STATUS_NEW, 'scalesafe' => true,
                    'hasgradeitems' => false, 'hasgrades' => false, 'metadatachanged' => false];
                if ($mapping && !$outcome) {
                    $criterion['status'] = self::STATUS_CONFLICT;
                    continue;
                }
                if (!$mapping && $this->has_external_outcome($courseid, $shortnameoutcomes)) {
                    $criterion['status'] = self::STATUS_CONFLICT;
                    continue;
                }
                if (!$mapping) {
                    continue;
                }
                $textchanged = $outcome->fullname !== criterion_display::name(
                    $criterion['code'],
                    $criterion['name']
                );
                $scalechanged = (int)$outcome->scaleid !== $scaleid;
                $criterion['metadatachanged'] = $this->metadata_changed($mapping, $parent, $criterion);
                if ($scalechanged) {
                    $criterion = array_merge($criterion, $this->outcome_usage((int)$outcome->id, $courseid));
                }
                if ($textchanged && $scalechanged) {
                    $criterion['status'] = self::STATUS_TEXT_AND_SCALE_CHANGED;
                } else if ($textchanged) {
                    $criterion['status'] = self::STATUS_TEXT_CHANGED;
                } else if ($scalechanged) {
                    $criterion['status'] = self::STATUS_SCALE_CHANGED;
                } else if ($criterion['metadatachanged']) {
                    $criterion['status'] = self::STATUS_METADATA_CHANGED;
                } else {
                    $criterion['status'] = self::STATUS_EXISTING;
                }
            }
        }
        unset($parent, $criterion);
        $curriculum['removed'] = [];
        foreach ($mappings as $sourcekey => $mapping) {
            if (!isset($seen[$sourcekey]) && empty($mapping->archived)) {
                $curriculum['removed'][] = [
                    'id' => (int)$mapping->id,
                    'code' => $mapping->code,
                    'name' => $mapping->name,
                    'sourcekey' => $sourcekey,
                    'status' => self::STATUS_REMOVED_FROM_SOURCE,
                ];
            }
        }
        return $curriculum;
    }

    /**
     * Import a confirmed curriculum after revalidating all conflicts and scale risks.
     */
    public function import(int $courseid, int $scaleid, array $curriculum, ?array $selectedsourcekeys = null): array {
        global $DB, $USER;
        require_capability('local/criteriaoutcomes:import', \context_course::instance($courseid));
        $curriculum = normalized_curriculum::normalize($curriculum);
        $preview = $this->preview($courseid, $curriculum, $scaleid);
        $metadata = $curriculum['metadata'];
        $batchid = $DB->insert_record('local_crout_importbatch', (object)[
            'courseid' => $courseid,
            'frameworkid' => null,
            'provider' => $metadata['provider'],
            'sourceid' => $metadata['sourceid'],
            'curriculumkey' => $metadata['curriculumkey'],
            'userid' => $USER->id,
            'operation' => 'import',
            'checksum' => $metadata['checksum'],
            'status' => 'failed',
            'summary' => null,
            'timecreated' => time(),
            'timecompleted' => null,
        ]);
        $transaction = $DB->start_delegated_transaction();
        try {
            $now = time();
            $identity = $this->identity($metadata);
            $framework = $DB->get_record('local_crout_framework', ['courseid' => $courseid, 'identitykey' => $identity]);
            $record = (object)[
            'courseid' => $courseid, 'name' => $metadata['sourcename'], 'type' => $metadata['curriculumtype'],
            'source' => $metadata['provider'], 'sourceid' => $metadata['sourceid'], 'sourceurl' => $metadata['sourceref'],
            'version' => $metadata['sourceversion'], 'language' => $metadata['language'],
            'checksum' => $metadata['checksum'], 'identitykey' => $identity, 'provider' => $metadata['provider'],
            'sourcetitle' => $metadata['sourcename'], 'sourceref' => $metadata['sourceref'],
            'sourcelastupdate' => $metadata['sourcelastupdate'], 'retrievedat' => $metadata['retrievedat'],
            'parserversion' => $metadata['parserversion'], 'curriculumkey' => $metadata['curriculumkey'],
            'educationlevel' => $metadata['educationlevel'], 'qualification' => $metadata['qualification'],
            'subjectmodule' => $metadata['subjectmodule'], 'modulecode' => $metadata['modulecode'],
            'provenance' => $metadata['provenance'], 'sourcestatus' => 'up_to_date',
            'sourcecheckedat' => $now, 'archived' => 0, 'timemodified' => $now,
            ];
            if ($framework) {
                $record->id = $framework->id;
                $DB->update_record('local_crout_framework', $record);
            } else {
                $record->timecreated = $now;
                $record->id = $DB->insert_record('local_crout_framework', $record);
            }
            $counts = ['new' => 0, 'existing' => 0, 'textchanged' => 0, 'scalechanged' => 0,
            'metadatachanged' => 0, 'scaleblocked' => 0, 'conflict' => 0];
            foreach ($preview['parents'] as $parentdata) {
                $selectedcriteria = array_filter(
                    $parentdata['criteria'],
                    static function (array $criterion) use ($selectedsourcekeys): bool {
                        return $selectedsourcekeys === null ||
                            in_array($criterion['sourcekey'], $selectedsourcekeys, true);
                    }
                );
                if (!$selectedcriteria) {
                    continue;
                }
                $parent = $DB->get_record('local_crout_parent', ['frameworkid' => $record->id, 'code' => $parentdata['code']]);
                $parentrecord = (object)['frameworkid' => $record->id, 'code' => $parentdata['code'],
                'name' => $parentdata['name'], 'type' => $parentdata['type'], 'weight' => $parentdata['weight'],
                'sortorder' => $parentdata['sortorder'], 'sourcekey' => $parentdata['sourcekey'], 'archived' => 0];
                if ($parent) {
                    $parentrecord->id = $parent->id;
                    $DB->update_record('local_crout_parent', $parentrecord);
                } else {
                    $parentrecord->id = $DB->insert_record('local_crout_parent', $parentrecord);
                }
                foreach ($selectedcriteria as $criteriondata) {
                    $this->before_entity_persist($criteriondata);
                    if ($criteriondata['status'] === self::STATUS_CONFLICT) {
                        $counts['conflict']++;
                        $this->add_batch_item($batchid, 'criterion', null, $criteriondata, 'conflict', null, null, 'conflict');
                        continue;
                    }
                    $criterion = $DB->get_record_sql(
                        "SELECT c.*
                       FROM {local_crout_criterion} c
                       JOIN {local_crout_parent} p ON p.id = c.parentid
                      WHERE p.frameworkid = :frameworkid AND c.sourcekey = :sourcekey",
                        ['frameworkid' => $record->id, 'sourcekey' => $criteriondata['sourcekey']]
                    );
                    $outcome = $criterion ? \grade_outcome::fetch(['id' => $criterion->outcomeid]) : null;
                    $previous = $criterion ? $this->criterion_snapshot($criterion, $outcome) : null;
                    $action = 'matched';
                    if (!$outcome) {
                        $outcome = new \grade_outcome();
                        $outcome->courseid = $courseid;
                        $outcome->shortname = $this->outcome_shortname(
                            $courseid,
                            $criteriondata['code'],
                            $metadata['curriculumkey']
                        );
                        $outcome->fullname = criterion_display::name($criteriondata['code'], $criteriondata['name']);
                        $outcome->scaleid = $scaleid;
                        $outcome->description = '';
                        $outcome->descriptionformat = FORMAT_HTML;
                        $outcome->usermodified = $USER->id;
                        $outcome->insert('local_criteriaoutcomes');
                        $counts['new']++;
                        $action = 'created';
                    } else {
                        $needsupdate = false;
                        $displayname = criterion_display::name($criteriondata['code'], $criteriondata['name']);
                        if ($outcome->fullname !== $displayname) {
                            $outcome->fullname = $displayname;
                            $counts['textchanged']++;
                            $needsupdate = true;
                        }
                        if ((int)$outcome->scaleid !== $scaleid) {
                            if ($criteriondata['scalesafe']) {
                                $outcome->scaleid = $scaleid;
                                $counts['scalechanged']++;
                                $needsupdate = true;
                            } else {
                                $counts['scaleblocked']++;
                            }
                        }
                        if ($needsupdate) {
                            $outcome->usermodified = $USER->id;
                            $outcome->update('local_criteriaoutcomes');
                            $action = 'updated';
                        } else if (!$criteriondata['metadatachanged'] && (int)$outcome->scaleid === $scaleid) {
                            $counts['existing']++;
                        }
                    }
                    if ($criteriondata['metadatachanged']) {
                        $counts['metadatachanged']++;
                    }
                    $criterionrecord = (object)['parentid' => $parentrecord->id, 'code' => $criteriondata['code'],
                    'name' => $criteriondata['name'], 'weight' => $criteriondata['weight'],
                    'outcomeid' => $outcome->id, 'sortorder' => $criteriondata['sortorder'],
                    'sourcekey' => $criteriondata['sourcekey'], 'outcomeowned' => 1, 'archived' => 0];
                    if ($criterion) {
                        $criterionrecord->id = $criterion->id;
                        $DB->update_record('local_crout_criterion', $criterionrecord);
                    } else {
                        $criterionrecord->id = $DB->insert_record('local_crout_criterion', $criterionrecord);
                    }
                    $newsnapshot = $this->criterion_snapshot($criterionrecord, $outcome);
                    if ($previous !== null && $previous !== $newsnapshot) {
                        $action = 'updated';
                    }
                    $this->add_batch_item(
                        $batchid,
                        'criterion',
                        (int)$criterionrecord->id,
                        $criteriondata,
                        $action,
                        $previous,
                        $newsnapshot
                    );
                }
            }
            $summary = $counts;
            $DB->set_field('local_crout_importbatch', 'frameworkid', $record->id, ['id' => $batchid]);
            $DB->set_field('local_crout_importbatch', 'status', 'success', ['id' => $batchid]);
            $DB->set_field('local_crout_importbatch', 'summary', json_encode($summary), ['id' => $batchid]);
            $DB->set_field('local_crout_importbatch', 'timecompleted', $now, ['id' => $batchid]);
            $transaction->allow_commit();
            $counts['batchid'] = $batchid;
            $eventdata = [
            'context' => \context_course::instance($courseid),
            'objectid' => $batchid,
            'other' => ['summary' => json_encode($counts)],
            ];
            if ($counts['new'] > 0) {
                \local_criteriaoutcomes\event\curriculum_imported::create($eventdata)->trigger();
            }
            if ($counts['textchanged'] > 0 || $counts['scalechanged'] > 0 || $counts['metadatachanged'] > 0) {
                \local_criteriaoutcomes\event\curriculum_updated::create($eventdata)->trigger();
            }
            return $counts;
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
    }

    /**
     * Return global and course-local scales.
     */
    public function available_scales(int $courseid): array {
        $scales = [];
        foreach ([0, $courseid] as $id) {
            foreach ((array)\grade_scale::fetch_all(['courseid' => $id]) as $scale) {
                if (!$scale) {
                    continue;
                }
                $scales[$scale->id] = $scale->get_name();
            }
        }
        return $scales;
    }

    /**
     * Load plugin mappings without N+1 queries.
     */
    private function framework_mappings(int $frameworkid): array {
        global $DB;
        $sql = "SELECT c.id, c.code, c.name, c.weight, c.outcomeid, c.sourcekey, c.archived,
                       p.name AS parentname, p.weight AS parentweight
                  FROM {local_crout_criterion} c JOIN {local_crout_parent} p ON p.id = c.parentid
                 WHERE p.frameworkid = :frameworkid";
        $result = [];
        foreach ($DB->get_records_sql($sql, ['frameworkid' => $frameworkid]) as $record) {
            $key = $record->sourcekey ?: \core_text::strtolower($record->code);
            $result[$key] = $record;
        }
        return $result;
    }

    /**
     * A scale change is safe only before Moodle creates any Outcome grade item.
     */
    private function outcome_usage(int $outcomeid, int $courseid): array {
        global $DB;
        $items = $DB->get_records('grade_items', ['courseid' => $courseid, 'outcomeid' => $outcomeid], '', 'id');
        $hasitems = !empty($items);
        $hasgrades = false;
        if ($hasitems) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($items), SQL_PARAMS_NAMED, 'item');
            $hasgrades = $DB->record_exists_sql("SELECT 1 FROM {grade_grades} WHERE itemid $insql", $params);
        }
        return ['hasgradeitems' => $hasitems, 'hasgrades' => $hasgrades, 'scalesafe' => !$hasitems];
    }

    /**
     * Detect plugin metadata changes separately from core Outcome changes.
     */
    private function metadata_changed(\stdClass $mapping, array $parent, array $criterion): bool {
        return $mapping->name !== $criterion['name'] || $mapping->parentname !== $parent['name'] ||
            $this->different_number($mapping->weight, $criterion['weight']) ||
            $this->different_number($mapping->parentweight, $parent['weight']);
    }

    /**
     * Compare nullable decimal values.
     */
    private function different_number(mixed $current, mixed $requested): bool {
        if ($current === null || $requested === null) {
            return $current !== $requested;
        }
        return abs((float)$current - (float)$requested) > 0.00001;
    }

    /**
     * Check that a scale is global or belongs to this course.
     */
    private function scale_available(int $courseid, int $scaleid): bool {
        $scale = \grade_scale::fetch(['id' => $scaleid]);
        return $scale && ((int)$scale->courseid === 0 || (int)$scale->courseid === $courseid);
    }

    /**
     * Build a stable framework identity.
     */
    private function identity(array $metadata): string {
        $parts = [$metadata['provider'], $metadata['sourceid'] ?: $metadata['sourcename'], $metadata['curriculumkey']];
        return hash('sha256', implode('|', array_map(fn($value) => \core_text::strtolower(trim($value)), $parts)));
    }

    /**
     * Return every course Outcome with a given shortname.
     */
    private function outcomes_by_shortname(int $courseid, string $shortname): array {
        global $DB;
        return array_values($DB->get_records('grade_outcomes', [
            'courseid' => $courseid,
            'shortname' => $shortname,
        ]));
    }

    /**
     * Use the pedagogical code unless another plugin-owned curriculum already uses it.
     */
    private function outcome_shortname(int $courseid, string $code, string $curriculumkey): string {
        if (!$this->outcomes_by_shortname($courseid, $code)) {
            return $code;
        }
        $prefix = trim(clean_param($curriculumkey, PARAM_TEXT));
        return \core_text::substr($prefix . ' · ' . $code, 0, 255);
    }

    /**
     * Whether any same-code Outcome is not demonstrably owned by this plugin.
     */
    private function has_external_outcome(int $courseid, array $outcomes): bool {
        global $DB;
        foreach ($outcomes as $outcome) {
            if (
                !$DB->record_exists_sql(
                    "SELECT 1
                   FROM {local_crout_criterion} c
                   JOIN {local_crout_parent} p ON p.id = c.parentid
                   JOIN {local_crout_framework} f ON f.id = p.frameworkid
                  WHERE f.courseid = :courseid AND c.outcomeid = :outcomeid AND c.outcomeowned = 1",
                    ['courseid' => $courseid, 'outcomeid' => $outcome->id]
                )
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Add a compact audit item.
     */
    private function add_batch_item(
        int $batchid,
        string $entitytype,
        ?int $entityid,
        array $source,
        string $action,
        ?array $previous,
        ?array $new,
        string $status = 'applied'
    ): void {
        global $DB;
        $DB->insert_record('local_crout_importitem', (object)[
            'batchid' => $batchid, 'entitytype' => $entitytype, 'entityid' => $entityid,
            'sourcekey' => $source['sourcekey'], 'action' => $action,
            'previousdata' => $previous === null ? null : json_encode($previous),
            'newdata' => $new === null ? null : json_encode($new),
            'status' => $status, 'timecreated' => time(),
        ]);
    }

    /**
     * Compact state used for safe undo comparisons.
     */
    private function criterion_snapshot(object $criterion, ?\grade_outcome $outcome): array {
        return [
            'code' => $criterion->code,
            'name' => $criterion->name,
            'weight' => $criterion->weight,
            'parentid' => (int)$criterion->parentid,
            'outcomeid' => (int)$criterion->outcomeid,
            'outcomename' => $outcome ? $outcome->fullname : null,
            'scaleid' => $outcome ? (int)$outcome->scaleid : null,
            'archived' => (int)($criterion->archived ?? 0),
        ];
    }

    /**
     * Test seam immediately before an entity write; production implementation is intentionally empty.
     */
    protected function before_entity_persist(array $criterion): void {
    }
}
