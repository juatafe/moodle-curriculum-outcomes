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
 * Database upgrades.
 *
 *  local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute plugin database upgrades.
 */
function xmldb_local_criteriaoutcomes_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026082600) {
        $table = new xmldb_table('local_crout_quizcfg');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('quizid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('criterionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('aggregation', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'mean');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('quizfk', XMLDB_KEY_FOREIGN, ['quizid'], 'quiz', ['id']);
        $table->add_key('criterionfk', XMLDB_KEY_FOREIGN, ['criterionid'], 'local_crout_criterion', ['id']);
        $table->add_index('quizcriterion', XMLDB_INDEX_UNIQUE, ['quizid', 'criterionid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_crout_quizmap');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('quizid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('slotid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('criterionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('weight', XMLDB_TYPE_NUMBER, '12, 7', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('quizfk', XMLDB_KEY_FOREIGN, ['quizid'], 'quiz', ['id']);
        $table->add_key('slotfk', XMLDB_KEY_FOREIGN, ['slotid'], 'quiz_slots', ['id']);
        $table->add_key('criterionfk', XMLDB_KEY_FOREIGN, ['criterionid'], 'local_crout_criterion', ['id']);
        $table->add_index('quizslotcriterion', XMLDB_INDEX_UNIQUE, ['quizid', 'slotid', 'criterionid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026082600, 'local', 'criteriaoutcomes');
    }

    // 0.3.0-dev: Assessment and feedback tables.
    if ($oldversion < 2026082700) {
        // Assessment table.
        $table = new xmldb_table('local_crout_assessment');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('criterionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('sourcetype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
        $table->add_field('sourceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('sourceinstanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('assessmentmode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
        $table->add_field('value', XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, null);
        $table->add_field('scalevalue', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('feedback', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('feedbackformat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('instrumenttype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('instrumentinstanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'draft');
        $table->add_field('graderid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timepublished', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('coursefk', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('criterionfk', XMLDB_KEY_FOREIGN, ['criterionid'], 'local_crout_criterion', ['id']);
        $table->add_key('userfk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('graderfk', XMLDB_KEY_FOREIGN, ['graderid'], 'user', ['id']);
        $table->add_index('criterionuser', XMLDB_INDEX_NOTUNIQUE, ['criterionid', 'userid']);
        $table->add_index('coursecriterion', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'criterionid']);
        $table->add_index('sourceref', XMLDB_INDEX_NOTUNIQUE, ['sourcetype', 'sourceid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Rubric mapping table.
        $table = new xmldb_table('local_crout_rubricmap');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('rubriccriterionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('curriculumcriterionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('weight', XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('coursefk', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('currcritfk', XMLDB_KEY_FOREIGN, ['curriculumcriterionid'], 'local_crout_criterion', ['id']);
        $table->add_index('rubriccrit', XMLDB_INDEX_UNIQUE, ['rubriccriterionid', 'curriculumcriterionid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Checklist definition table.
        $table = new xmldb_table('local_crout_checklist_def');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('descriptionformat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('itemmode', XMLDB_TYPE_CHAR, '12', null, XMLDB_NOTNULL, null, 'binary');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('coursefk', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Checklist item table.
        $table = new xmldb_table('local_crout_checklist_item');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('definitionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('name', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('weight', XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('deffk', XMLDB_KEY_FOREIGN, ['definitionid'], 'local_crout_checklist_def', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Checklist item-to-criterion mapping table.
        $table = new xmldb_table('local_crout_checklist_map');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('criterionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('itemfk', XMLDB_KEY_FOREIGN, ['itemid'], 'local_crout_checklist_item', ['id']);
        $table->add_key('criterionfk', XMLDB_KEY_FOREIGN, ['criterionid'], 'local_crout_criterion', ['id']);
        $table->add_index('itemcriterion', XMLDB_INDEX_UNIQUE, ['itemid', 'criterionid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Checklist response table.
        $table = new xmldb_table('local_crout_checklist_resp');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('definitionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('state', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'not_done');
        $table->add_field('feedback', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('feedbackformat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('graderid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('deffk', XMLDB_KEY_FOREIGN, ['definitionid'], 'local_crout_checklist_def', ['id']);
        $table->add_key('itemfk', XMLDB_KEY_FOREIGN, ['itemid'], 'local_crout_checklist_item', ['id']);
        $table->add_key('userfk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('graderfk', XMLDB_KEY_FOREIGN, ['graderid'], 'user', ['id']);
        $table->add_index('defitemuser', XMLDB_INDEX_UNIQUE, ['definitionid', 'itemid', 'userid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Current judgement table.
        $table = new xmldb_table('local_crout_judgement');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('criterionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('scalevalue', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('comment', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('commentformat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('graderid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('coursefk', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('criterionfk', XMLDB_KEY_FOREIGN, ['criterionid'], 'local_crout_criterion', ['id']);
        $table->add_key('userfk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('graderfk', XMLDB_KEY_FOREIGN, ['graderid'], 'user', ['id']);
        $table->add_index('criterionuser', XMLDB_INDEX_UNIQUE, ['criterionid', 'userid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Feedback read tracking table.
        $table = new xmldb_table('local_crout_feedback_read');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('assessmentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timeread', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('assessfk', XMLDB_KEY_FOREIGN, ['assessmentid'], 'local_crout_assessment', ['id']);
        $table->add_key('userfk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('assessuser', XMLDB_INDEX_UNIQUE, ['assessmentid', 'userid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026082700, 'local', 'criteriaoutcomes');
    }

    // 0.3.0-dev: Widen itemmode column to fit 'three_state' (11 chars) and make weight nullable.
    if ($oldversion < 2026082701) {
        $table = new xmldb_table('local_crout_checklist_def');
        $field = new xmldb_field('itemmode', XMLDB_TYPE_CHAR, '12', null, XMLDB_NOTNULL, null, 'binary');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }

        $table = new xmldb_table('local_crout_checklist_item');
        $field = new xmldb_field('weight', XMLDB_TYPE_NUMBER, '10, 5', null, null, null, null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_notnull($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026082701, 'local', 'criteriaoutcomes');
    }

    // 0.3.0-alpha release metadata; no schema change.
    if ($oldversion < 2026082702) {
        upgrade_plugin_savepoint(true, 2026082702, 'local', 'criteriaoutcomes');
    }

    // 0.4.0-dev: source provenance, stable entity identity, archive state and import audit.
    if ($oldversion < 2026082800) {
        $table = new xmldb_table('local_crout_framework');
        $fields = [
            new xmldb_field('provider', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, 'json', 'identitykey'),
            new xmldb_field('sourcetitle', XMLDB_TYPE_TEXT, null, null, null, null, null, 'provider'),
            new xmldb_field('sourceref', XMLDB_TYPE_TEXT, null, null, null, null, null, 'sourcetitle'),
            new xmldb_field('sourcelastupdate', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'sourceref'),
            new xmldb_field('retrievedat', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'sourcelastupdate'),
            new xmldb_field('parserversion', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'retrievedat'),
            new xmldb_field('curriculumkey', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'legacy', 'parserversion'),
            new xmldb_field('educationlevel', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'curriculumkey'),
            new xmldb_field('qualification', XMLDB_TYPE_TEXT, null, null, null, null, null, 'educationlevel'),
            new xmldb_field('subjectmodule', XMLDB_TYPE_TEXT, null, null, null, null, null, 'qualification'),
            new xmldb_field('modulecode', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'subjectmodule'),
            new xmldb_field('provenance', XMLDB_TYPE_TEXT, null, null, null, null, null, 'modulecode'),
            new xmldb_field('sourcestatus', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, 'up_to_date', 'provenance'),
            new xmldb_field('sourcecheckedat', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'sourcestatus'),
            new xmldb_field('archived', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'sourcecheckedat'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        $table = new xmldb_table('local_crout_parent');
        $fields = [
            new xmldb_field('sourcekey', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'sortorder'),
            new xmldb_field('archived', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'sourcekey'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        $table = new xmldb_table('local_crout_criterion');
        $fields = [
            new xmldb_field('sourcekey', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'sortorder'),
            new xmldb_field('outcomeowned', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'sourcekey'),
            new xmldb_field('archived', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'outcomeowned'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        $table = new xmldb_table('local_crout_importbatch');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('frameworkid', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('provider', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL);
        $table->add_field('sourceid', XMLDB_TYPE_CHAR, '255');
        $table->add_field('curriculumkey', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('operation', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'import');
        $table->add_field('checksum', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('summary', XMLDB_TYPE_TEXT);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timecompleted', XMLDB_TYPE_INTEGER, '10');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('coursefk', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('frameworkfk', XMLDB_KEY_FOREIGN, ['frameworkid'], 'local_crout_framework', ['id']);
        $table->add_key('userfk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('coursecreated', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'timecreated']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_crout_importitem');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('batchid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('entitytype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
        $table->add_field('entityid', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('sourcekey', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
        $table->add_field('action', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
        $table->add_field('previousdata', XMLDB_TYPE_TEXT);
        $table->add_field('newdata', XMLDB_TYPE_TEXT);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'applied');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('batchfk', XMLDB_KEY_FOREIGN, ['batchid'], 'local_crout_importbatch', ['id']);
        $table->add_index('batchsource', XMLDB_INDEX_NOTUNIQUE, ['batchid', 'sourcekey']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026082800, 'local', 'criteriaoutcomes');
    }

    // 0.4.0-dev: expose curriculum codes in Moodle's flat native Outcome selectors.
    if ($oldversion < 2026082801) {
        \local_criteriaoutcomes\criterion_display::migrate_owned_outcomes();
        upgrade_plugin_savepoint(true, 2026082801, 'local', 'criteriaoutcomes');
    }

    return true;
}
