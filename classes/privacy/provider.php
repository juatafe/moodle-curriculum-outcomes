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

namespace local_criteriaoutcomes\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_criteriaoutcomes.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the types of data stored by this plugin.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_crout_assessment', [
            'userid' => 'privacy:metadata:assessment:userid',
            'graderid' => 'privacy:metadata:assessment:graderid',
            'feedback' => 'privacy:metadata:assessment:feedback',
        ], 'privacy:metadata:assessment');

        $collection->add_database_table('local_crout_checklist_resp', [
            'userid' => 'privacy:metadata:checklistresp:userid',
            'graderid' => 'privacy:metadata:checklistresp:graderid',
            'feedback' => 'privacy:metadata:checklistresp:feedback',
        ], 'privacy:metadata:checklistresp');

        $collection->add_database_table('local_crout_judgement', [
            'userid' => 'privacy:metadata:judgement:userid',
            'graderid' => 'privacy:metadata:judgement:graderid',
            'comment' => 'privacy:metadata:judgement:comment',
        ], 'privacy:metadata:judgement');

        $collection->add_database_table('local_crout_feedback_read', [
            'userid' => 'privacy:metadata:feedbackread:userid',
        ], 'privacy:metadata:feedbackread');

        $collection->add_database_table('local_crout_importbatch', [
            'userid' => 'privacy:metadata:importbatch:userid',
        ], 'privacy:metadata:importbatch');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user data for the specified user.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course} c ON c.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                 WHERE EXISTS (
                     SELECT 1 FROM {local_crout_assessment} a
                      WHERE a.courseid = c.id AND (a.userid = :userid1 OR a.graderid = :userid2)
                 ) OR EXISTS (
                     SELECT 1 FROM {local_crout_checklist_resp} cr
                      JOIN {local_crout_checklist_def} cd ON cd.id = cr.definitionid
                     WHERE cd.courseid = c.id AND (cr.userid = :userid3 OR cr.graderid = :userid4)
                 ) OR EXISTS (
                     SELECT 1 FROM {local_crout_judgement} j
                      WHERE j.courseid = c.id AND (j.userid = :userid5 OR j.graderid = :userid6)
                 ) OR EXISTS (
                     SELECT 1 FROM {local_crout_feedback_read} fr
                      JOIN {local_crout_assessment} a ON a.id = fr.assessmentid
                     WHERE a.courseid = c.id AND fr.userid = :userid7
                 ) OR EXISTS (
                     SELECT 1 FROM {local_crout_importbatch} ib
                      WHERE ib.courseid = c.id AND ib.userid = :userid8
                 )";

        $params = [
            'contextlevel' => CONTEXT_COURSE,
            'userid1' => $userid,
            'userid2' => $userid,
            'userid3' => $userid,
            'userid4' => $userid,
            'userid5' => $userid,
            'userid6' => $userid,
            'userid7' => $userid,
            'userid8' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if (!$context instanceof \context_course) {
            return;
        }

        $courseid = $context->instanceid;

        $sql = "SELECT DISTINCT u.id AS userid
                  FROM {user} u
                 WHERE EXISTS (
                     SELECT 1 FROM {local_crout_assessment} a
                      WHERE a.courseid = :courseid1 AND (a.userid = u.id OR a.graderid = u.id)
                 ) OR EXISTS (
                     SELECT 1 FROM {local_crout_checklist_resp} cr
                      JOIN {local_crout_checklist_def} cd ON cd.id = cr.definitionid
                     WHERE cd.courseid = :courseid2 AND (cr.userid = u.id OR cr.graderid = u.id)
                 ) OR EXISTS (
                     SELECT 1 FROM {local_crout_judgement} j
                      WHERE j.courseid = :courseid3 AND (j.userid = u.id OR j.graderid = u.id)
                 ) OR EXISTS (
                     SELECT 1 FROM {local_crout_feedback_read} fr
                      JOIN {local_crout_assessment} a ON a.id = fr.assessmentid
                     WHERE a.courseid = :courseid4 AND fr.userid = u.id
                 ) OR EXISTS (
                     SELECT 1 FROM {local_crout_importbatch} ib
                      WHERE ib.courseid = :courseid5 AND ib.userid = u.id
                 )";

        $params = [
            'courseid1' => $courseid,
            'courseid2' => $courseid,
            'courseid3' => $courseid,
            'courseid4' => $courseid,
            'courseid5' => $courseid,
        ];

        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all user data for the specified contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        foreach ($contextlist as $context) {
            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }

            $courseid = $context->instanceid;

            // Export assessments.
            $assessments = $DB->get_records_sql(
                "SELECT a.*, c.code AS criterioncode, c.name AS criterionname
                   FROM {local_crout_assessment} a
                   JOIN {local_crout_criterion} c ON c.id = a.criterionid
                  WHERE a.courseid = :courseid AND (a.userid = :userid1 OR a.graderid = :userid2)",
                ['courseid' => $courseid, 'userid1' => $user->id, 'userid2' => $user->id]
            );
            foreach ($assessments as $assessment) {
                $data = (object)[
                    'criterion' => $assessment->criterioncode . ' - ' . $assessment->criterionname,
                    'sourcetype' => $assessment->sourcetype,
                    'assessmentmode' => $assessment->assessmentmode,
                    'value' => $assessment->value,
                    'scalevalue' => $assessment->scalevalue,
                    'feedback' => $assessment->feedback,
                    'status' => $assessment->status,
                    'grader' => transform::user($assessment->graderid),
                    'timecreated' => transform::datetime($assessment->timecreated),
                    'timemodified' => transform::datetime($assessment->timemodified),
                ];
                writer::with_context($context)->export_data(['Assessments', $assessment->id], $data);
            }

            // Export checklist responses.
            $responses = $DB->get_records_sql(
                "SELECT cr.*, cd.name AS checklistname, ci.name AS itemname
                   FROM {local_crout_checklist_resp} cr
                   JOIN {local_crout_checklist_def} cd ON cd.id = cr.definitionid
                   JOIN {local_crout_checklist_item} ci ON ci.id = cr.itemid
                  WHERE cd.courseid = :courseid AND (cr.userid = :userid1 OR cr.graderid = :userid2)",
                ['courseid' => $courseid, 'userid1' => $user->id, 'userid2' => $user->id]
            );
            foreach ($responses as $response) {
                $data = (object)[
                    'checklist' => $response->checklistname,
                    'item' => $response->itemname,
                    'state' => $response->state,
                    'feedback' => $response->feedback,
                    'grader' => transform::user($response->graderid),
                    'timecreated' => transform::datetime($response->timecreated),
                    'timemodified' => transform::datetime($response->timemodified),
                ];
                writer::with_context($context)->export_data(['Checklist Responses', $response->id], $data);
            }

            // Export judgements.
            $judgements = $DB->get_records_sql(
                "SELECT j.*, c.code AS criterioncode, c.name AS criterionname
                   FROM {local_crout_judgement} j
                   JOIN {local_crout_criterion} c ON c.id = j.criterionid
                  WHERE j.courseid = :courseid AND (j.userid = :userid1 OR j.graderid = :userid2)",
                ['courseid' => $courseid, 'userid1' => $user->id, 'userid2' => $user->id]
            );
            foreach ($judgements as $judgement) {
                $data = (object)[
                    'criterion' => $judgement->criterioncode . ' - ' . $judgement->criterionname,
                    'scalevalue' => $judgement->scalevalue,
                    'comment' => $judgement->comment,
                    'grader' => transform::user($judgement->graderid),
                    'timecreated' => transform::datetime($judgement->timecreated),
                    'timemodified' => transform::datetime($judgement->timemodified),
                ];
                writer::with_context($context)->export_data(['Judgements', $judgement->id], $data);
            }

            // Export read tracking.
            $reads = $DB->get_records_sql(
                "SELECT fr.* FROM {local_crout_feedback_read} fr
                  JOIN {local_crout_assessment} a ON a.id = fr.assessmentid
                 WHERE a.courseid = :courseid AND fr.userid = :userid",
                ['courseid' => $courseid, 'userid' => $user->id]
            );
            foreach ($reads as $read) {
                $data = (object)[
                    'assessmentid' => $read->assessmentid,
                    'timeread' => transform::datetime($read->timeread),
                ];
                writer::with_context($context)->export_data(['Feedback Read', $read->id], $data);
            }

            // Export attribution in the durable curriculum audit log.
            $batches = $DB->get_records('local_crout_importbatch', [
                'courseid' => $courseid, 'userid' => $user->id,
            ], 'timecreated ASC');
            foreach ($batches as $batch) {
                $data = (object)[
                    'provider' => $batch->provider,
                    'sourceid' => $batch->sourceid,
                    'curriculumkey' => $batch->curriculumkey,
                    'operation' => $batch->operation,
                    'status' => $batch->status,
                    'summary' => $batch->summary,
                    'timecreated' => transform::datetime($batch->timecreated),
                    'timecompleted' => $batch->timecompleted ? transform::datetime($batch->timecompleted) : null,
                ];
                writer::with_context($context)->export_data(['Import history', $batch->id], $data);
            }
        }
    }

    /**
     * Delete all data for all users within a single context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        $courseid = $context->instanceid;

        // Delete read tracking.
        $DB->delete_records_select(
            'local_crout_feedback_read',
            "assessmentid IN (SELECT id FROM {local_crout_assessment} WHERE courseid = :courseid)",
            ['courseid' => $courseid]
        );

        // Delete assessments.
        $DB->delete_records('local_crout_assessment', ['courseid' => $courseid]);

        // Delete checklist responses.
        $DB->delete_records_select(
            'local_crout_checklist_resp',
            "definitionid IN (SELECT id FROM {local_crout_checklist_def} WHERE courseid = :courseid)",
            ['courseid' => $courseid]
        );

        // Delete judgements.
        $DB->delete_records('local_crout_judgement', ['courseid' => $courseid]);

        // The audit remains structural; only personal attribution is removed.
        $DB->set_field('local_crout_importbatch', 'userid', null, ['courseid' => $courseid]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist as $context) {
            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }

            $courseid = $context->instanceid;

            // Delete read tracking.
            $DB->delete_records_select(
                'local_crout_feedback_read',
                "assessmentid IN (
                    SELECT id FROM {local_crout_assessment}
                     WHERE courseid = :courseid AND (userid = :userid1 OR graderid = :userid2)
                ) OR (userid = :userid3 AND assessmentid IN (
                    SELECT id FROM {local_crout_assessment} WHERE courseid = :courseid2
                ))",
                [
                    'userid1' => $user->id,
                    'userid2' => $user->id,
                    'userid3' => $user->id,
                    'courseid' => $courseid,
                    'courseid2' => $courseid,
                ]
            );

            // Delete assessments.
            $DB->delete_records_select(
                'local_crout_assessment',
                'courseid = :courseid AND (userid = :userid1 OR graderid = :userid2)',
                ['courseid' => $courseid, 'userid1' => $user->id, 'userid2' => $user->id]
            );

            // Delete checklist responses.
            $DB->delete_records_select(
                'local_crout_checklist_resp',
                "(userid = :userid1 OR graderid = :userid2)
                  AND definitionid IN (SELECT id FROM {local_crout_checklist_def} WHERE courseid = :courseid)",
                ['userid1' => $user->id, 'userid2' => $user->id, 'courseid' => $courseid]
            );

            // Delete judgements.
            $DB->delete_records_select(
                'local_crout_judgement',
                'courseid = :courseid AND (userid = :userid1 OR graderid = :userid2)',
                ['courseid' => $courseid, 'userid1' => $user->id, 'userid2' => $user->id]
            );

            $DB->set_field('local_crout_importbatch', 'userid', null, [
                'courseid' => $courseid, 'userid' => $user->id,
            ]);
        }
    }

    /**
     * Delete multiple users within a single context.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        $courseid = $context->instanceid;
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        // Delete read tracking.
        $DB->delete_records_select(
            'local_crout_feedback_read',
            "assessmentid IN (
                SELECT id FROM {local_crout_assessment}
                 WHERE courseid = :courseid AND (userid $usersql OR graderid $usersql)
            ) OR (userid $usersql AND assessmentid IN (
                SELECT id FROM {local_crout_assessment} WHERE courseid = :courseid
            ))",
            $userparams + ['courseid' => $courseid]
        );

        // Delete assessments.
        $DB->delete_records_select(
            'local_crout_assessment',
            "courseid = :courseid AND (userid $usersql OR graderid $usersql)",
            $userparams + ['courseid' => $courseid]
        );

        // Delete checklist responses.
        $DB->delete_records_select(
            'local_crout_checklist_resp',
            "(userid $usersql OR graderid $usersql)
              AND definitionid IN (SELECT id FROM {local_crout_checklist_def} WHERE courseid = :courseid)",
            $userparams + ['courseid' => $courseid]
        );

        // Delete judgements.
        $DB->delete_records_select(
            'local_crout_judgement',
            "courseid = :courseid AND (userid $usersql OR graderid $usersql)",
            $userparams + ['courseid' => $courseid]
        );

        $DB->set_field_select(
            'local_crout_importbatch',
            'userid',
            null,
            "courseid = :courseid AND userid $usersql",
            $userparams + ['courseid' => $courseid]
        );
    }
}
