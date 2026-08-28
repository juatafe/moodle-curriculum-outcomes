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

namespace local_criteriaoutcomes\service;

/**
 * Conservative batch undo which never overwrites later work or academic use.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class import_undo_service {
    /**
     * Analyze what an undo can safely do now.
     */
    public function analyze(int $courseid, int $batchid): array {
        global $DB;
        $batch = $DB->get_record('local_crout_importbatch', ['id' => $batchid, 'courseid' => $courseid], '*', MUST_EXIST);
        $result = [];
        foreach ($DB->get_records('local_crout_importitem', ['batchid' => $batchid], 'id DESC') as $item) {
            $decision = 'preserve';
            if ($item->action === 'created' && $item->entitytype === 'criterion' && $item->entityid) {
                $safety = (new deletion_safety_service())->analyze($courseid, (int)$item->entityid);
                $decision = match ($safety['policy']) {
                    deletion_safety_service::SAFE_DELETE => 'delete',
                    deletion_safety_service::ARCHIVE_ONLY => 'archive_only',
                    default => 'blocked',
                };
            } else if ($item->action === 'matched') {
                $decision = 'matched_preserve';
            } else if ($item->action === 'updated' && $item->entityid) {
                $decision = $this->updated_decision($batch, $item);
            }
            $result[$item->id] = [
                'itemid' => (int)$item->id,
                'entityid' => $item->entityid === null ? null : (int)$item->entityid,
                'action' => $item->action,
                'decision' => $decision,
            ];
        }
        return $result;
    }

    /**
     * Execute a fresh analysis and record an undo batch.
     */
    public function undo(int $courseid, int $batchid, bool $archiveused): array {
        global $DB, $USER;
        require_capability('local/criteriaoutcomes:manage', \context_course::instance($courseid));
        $sourcebatch = $DB->get_record('local_crout_importbatch', [
            'id' => $batchid,
            'courseid' => $courseid,
        ], '*', MUST_EXIST);
        $undobatchid = $DB->insert_record('local_crout_importbatch', (object)[
            'courseid' => $courseid, 'frameworkid' => $sourcebatch->frameworkid,
            'provider' => $sourcebatch->provider, 'sourceid' => $sourcebatch->sourceid,
            'curriculumkey' => $sourcebatch->curriculumkey, 'userid' => $USER->id,
            'operation' => 'undo', 'checksum' => $sourcebatch->checksum, 'status' => 'failed',
            'summary' => null, 'timecreated' => time(), 'timecompleted' => null,
        ]);
        $transaction = $DB->start_delegated_transaction();
        $summary = ['deleted' => 0, 'archived' => 0, 'restored' => 0, 'matched' => 0, 'conflicted' => 0];
        foreach ($this->analyze($courseid, $batchid) as $analysis) {
            $item = $DB->get_record('local_crout_importitem', ['id' => $analysis['itemid']], '*', MUST_EXIST);
            switch ($analysis['decision']) {
                case 'delete':
                    $applied = (new deletion_safety_service())->apply($courseid, [$analysis['entityid']], false);
                    $summary['deleted'] += $applied['deleted'];
                    $DB->set_field('local_crout_importitem', 'status', 'undone', ['id' => $item->id]);
                    break;
                case 'archive_only':
                    if ($archiveused) {
                        (new deletion_safety_service())->archive($courseid, $analysis['entityid']);
                        $summary['archived']++;
                        $DB->set_field('local_crout_importitem', 'status', 'archived', ['id' => $item->id]);
                    } else {
                        $summary['conflicted']++;
                    }
                    break;
                case 'restore':
                    $this->restore_update($courseid, $item);
                    $summary['restored']++;
                    $DB->set_field('local_crout_importitem', 'status', 'undone', ['id' => $item->id]);
                    break;
                case 'matched_preserve':
                    $summary['matched']++;
                    break;
                default:
                    $summary['conflicted']++;
                    $DB->set_field('local_crout_importitem', 'status', 'conflicted_undo', ['id' => $item->id]);
            }
        }
        $now = time();
        $DB->set_field('local_crout_importbatch', 'status', 'undone', ['id' => $batchid]);
        $DB->set_field('local_crout_importbatch', 'status', 'success', ['id' => $undobatchid]);
        $DB->set_field('local_crout_importbatch', 'summary', json_encode($summary), ['id' => $undobatchid]);
        $DB->set_field('local_crout_importbatch', 'timecompleted', $now, ['id' => $undobatchid]);
        $transaction->allow_commit();
        $summary['batchid'] = $undobatchid;
        \local_criteriaoutcomes\event\import_undone::create([
            'context' => \context_course::instance($courseid),
            'objectid' => $undobatchid,
            'other' => ['sourcebatchid' => $batchid, 'summary' => json_encode($summary)],
        ])->trigger();
        return $summary;
    }

    /**
     * Decide whether one update still equals this batch and has no successor.
     */
    private function updated_decision(object $batch, object $item): string {
        global $DB;
        if (
            $DB->record_exists_sql(
                "SELECT 1
               FROM {local_crout_importitem} ii
               JOIN {local_crout_importbatch} ib ON ib.id = ii.batchid
              WHERE ii.entitytype = :entitytype AND ii.entityid = :entityid
                    AND ib.courseid = :courseid AND ib.id > :batchid
                    AND ib.status IN ('success', 'undone')",
                [
                'entitytype' => $item->entitytype, 'entityid' => $item->entityid,
                'courseid' => $batch->courseid, 'batchid' => $batch->id,
                ]
            )
        ) {
            return 'conflicted_later_change';
        }
        $expected = json_decode((string)$item->newdata, true);
        $current = $this->current_snapshot((int)$item->entityid);
        return $expected && $current === $expected ? 'restore' : 'conflicted_current_value';
    }

    /**
     * Restore the compact previous state of one criterion.
     */
    private function restore_update(int $courseid, object $item): void {
        global $DB;
        $previous = json_decode((string)$item->previousdata, true, 32, JSON_THROW_ON_ERROR);
        $criterion = $DB->get_record('local_crout_criterion', ['id' => $item->entityid], '*', MUST_EXIST);
        $criterion->name = $previous['name'];
        $criterion->weight = $previous['weight'];
        $criterion->archived = $previous['archived'];
        $DB->update_record('local_crout_criterion', $criterion);
        $outcome = \grade_outcome::fetch(['id' => $criterion->outcomeid, 'courseid' => $courseid]);
        if ($outcome) {
            $outcome->fullname = $previous['outcomename'];
            if ((int)$outcome->scaleid !== (int)$previous['scaleid']) {
                $usage = $DB->record_exists('grade_items', ['courseid' => $courseid, 'outcomeid' => $outcome->id]);
                if ($usage) {
                    throw new \moodle_exception('errorscaleinuse', 'local_criteriaoutcomes');
                }
                $outcome->scaleid = $previous['scaleid'];
            }
            $outcome->update('local_criteriaoutcomes');
        }
    }

    /**
     * Current state in exactly the audit snapshot shape.
     */
    private function current_snapshot(int $criterionid): ?array {
        global $DB;
        $criterion = $DB->get_record('local_crout_criterion', ['id' => $criterionid]);
        if (!$criterion) {
            return null;
        }
        $outcome = \grade_outcome::fetch(['id' => $criterion->outcomeid]);
        return [
            'code' => $criterion->code, 'name' => $criterion->name, 'weight' => $criterion->weight,
            'parentid' => (int)$criterion->parentid, 'outcomeid' => (int)$criterion->outcomeid,
            'outcomename' => $outcome ? $outcome->fullname : null,
            'scaleid' => $outcome ? (int)$outcome->scaleid : null, 'archived' => (int)$criterion->archived,
        ];
    }
}
