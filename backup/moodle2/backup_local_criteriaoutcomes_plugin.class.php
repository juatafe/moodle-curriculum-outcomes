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
 * Course backup support.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Adds curriculum hierarchy records to course backup.
 */
class backup_local_criteriaoutcomes_plugin extends backup_local_plugin {
    /**
     * Add quiz-specific mappings beside a quiz activity backup.
     */
    protected function define_module_plugin_structure() {
        if ($this->task->get_modulename() !== 'quiz') {
            return null;
        }
        $plugin = $this->get_plugin_element(null, null, null);
        $wrapper = new backup_nested_element($this->get_recommended_name());

        $configurations = new backup_nested_element('quiz_configurations');
        $configuration = new backup_nested_element('quiz_configuration', ['id'], [
            'quizid', 'criterionid', 'aggregation', 'timecreated', 'timemodified',
        ]);
        $mappings = new backup_nested_element('quiz_mappings');
        $mapping = new backup_nested_element('quiz_mapping', ['id'], [
            'quizid', 'slotid', 'criterionid', 'weight', 'timecreated', 'timemodified',
        ]);

        $plugin->add_child($wrapper);
        $wrapper->add_child($configurations);
        $configurations->add_child($configuration);
        $wrapper->add_child($mappings);
        $mappings->add_child($mapping);
        $configuration->set_source_table('local_crout_quizcfg', ['quizid' => backup::VAR_ACTIVITYID]);
        $mapping->set_source_table('local_crout_quizmap', ['quizid' => backup::VAR_ACTIVITYID]);
        return $plugin;
    }

    /**
     * Define course-level plugin backup structure.
     */
    protected function define_course_plugin_structure(): backup_plugin_element {
        $plugin = $this->get_plugin_element(null, null, null);
        $wrapper = new backup_nested_element($this->get_recommended_name());

        // Curriculum hierarchy (always backed up).
        $frameworks = new backup_nested_element('frameworks');
        $framework = new backup_nested_element(
            'framework',
            ['id'],
            ['name', 'type', 'source', 'sourceid', 'sourceurl', 'version', 'language',
                'checksum', 'identitykey', 'provider', 'sourcetitle', 'sourceref', 'sourcelastupdate',
                'retrievedat', 'parserversion', 'curriculumkey', 'educationlevel', 'qualification',
                'subjectmodule', 'modulecode', 'provenance', 'sourcestatus', 'sourcecheckedat', 'archived',
                'timecreated', 'timemodified']
        );
        $parents = new backup_nested_element('parents');
        $parent = new backup_nested_element(
            'parent',
            ['id'],
            ['code', 'name', 'type', 'weight', 'sortorder', 'sourcekey', 'archived']
        );
        $criteria = new backup_nested_element('criteria');
        $criterion = new backup_nested_element(
            'criterion',
            ['id'],
            ['code', 'name', 'weight', 'outcomeid', 'sortorder', 'sourcekey', 'outcomeowned', 'archived']
        );

        // Import audit is structural and survives backups without user data.
        $importbatches = new backup_nested_element('importbatches');
        $batchfields = [
            'frameworkid', 'provider', 'sourceid', 'curriculumkey', 'operation', 'checksum',
            'status', 'summary', 'timecreated', 'timecompleted',
        ];
        if ($this->get_setting_value('users')) {
            $batchfields[] = 'userid';
        }
        $importbatch = new backup_nested_element('importbatch', ['id'], $batchfields);
        $importitems = new backup_nested_element('importitems');
        $importitem = new backup_nested_element('importitem', ['id'], [
            'batchid', 'entitytype', 'entityid', 'sourcekey', 'action', 'previousdata', 'newdata', 'status', 'timecreated',
        ]);

        // Rubric dimension mapping (always backed up).
        $rubricmaps = new backup_nested_element('rubricmaps');
        $rubricmap = new backup_nested_element('rubricmap', ['id'], [
            'rubriccriterionid', 'curriculumcriterionid', 'weight', 'timecreated', 'timemodified',
        ]);

        // Checklist definitions and items (always backed up).
        $checklistdefs = new backup_nested_element('checklistdefs');
        $checklistdef = new backup_nested_element('checklistdef', ['id'], [
            'name', 'description', 'descriptionformat', 'itemmode', 'timecreated', 'timemodified',
        ]);
        $checklistitems = new backup_nested_element('checklistitems');
        $checklistitem = new backup_nested_element('checklistitem', ['id'], [
            'name', 'sortorder', 'weight', 'timecreated', 'timemodified',
        ]);
        $checklistmaps = new backup_nested_element('checklistmaps');
        $checklistmap = new backup_nested_element('checklistmap', ['id'], [
            'itemid', 'criterionid', 'timecreated',
        ]);

        $plugin->add_child($wrapper);
        $wrapper->add_child($frameworks);
        $frameworks->add_child($framework);
        $framework->add_child($parents);
        $parents->add_child($parent);
        $parent->add_child($criteria);
        $criteria->add_child($criterion);

        $wrapper->add_child($importbatches);
        $importbatches->add_child($importbatch);
        $wrapper->add_child($importitems);
        $importitems->add_child($importitem);

        $wrapper->add_child($rubricmaps);
        $rubricmaps->add_child($rubricmap);

        $wrapper->add_child($checklistdefs);
        $checklistdefs->add_child($checklistdef);
        $checklistdef->add_child($checklistitems);
        $checklistitems->add_child($checklistitem);
        $checklistitem->add_child($checklistmaps);
        $checklistmaps->add_child($checklistmap);

        $framework->set_source_table('local_crout_framework', ['courseid' => backup::VAR_COURSEID]);
        $parent->set_source_table('local_crout_parent', ['frameworkid' => backup::VAR_PARENTID]);
        $criterion->set_source_table('local_crout_criterion', ['parentid' => backup::VAR_PARENTID]);
        $criterion->annotate_ids('outcome', 'outcomeid');
        $importbatch->set_source_table('local_crout_importbatch', ['courseid' => backup::VAR_COURSEID]);
        $importitem->set_source_sql(
            'SELECT ii.*
               FROM {local_crout_importitem} ii
               JOIN {local_crout_importbatch} ib ON ib.id = ii.batchid
              WHERE ib.courseid = ?',
            [backup::VAR_COURSEID]
        );
        if ($this->get_setting_value('users')) {
            $importbatch->annotate_ids('user', 'userid');
        }
        $rubricmap->set_source_table('local_crout_rubricmap', ['courseid' => backup::VAR_COURSEID]);
        $rubricmap->annotate_ids('gradingform_rubric_criterion', 'rubriccriterionid');
        $rubricmap->annotate_ids('local_crout_criterion', 'curriculumcriterionid');
        $checklistdef->set_source_table('local_crout_checklist_def', ['courseid' => backup::VAR_COURSEID]);
        $checklistitem->set_source_table('local_crout_checklist_item', ['definitionid' => backup::VAR_PARENTID]);
        $checklistmap->set_source_table('local_crout_checklist_map', ['itemid' => backup::VAR_PARENTID]);
        $checklistmap->annotate_ids('local_crout_criterion', 'criterionid');

        // Personal and academic records are included only when the backup includes users.
        if ($this->get_setting_value('users')) {
            $assessments = new backup_nested_element('assessments');
            $assessment = new backup_nested_element('assessment', ['id'], [
                'courseid', 'criterionid', 'userid', 'sourcetype', 'sourceid', 'sourceinstanceid',
                'assessmentmode', 'value', 'scalevalue', 'feedback', 'feedbackformat',
                'instrumenttype', 'instrumentinstanceid', 'status', 'graderid',
                'timecreated', 'timemodified', 'timepublished',
            ]);
            $checklistresponses = new backup_nested_element('checklistresponses');
            $checklistresponse = new backup_nested_element('checklistresponse', ['id'], [
                'definitionid', 'itemid', 'userid', 'state', 'feedback', 'feedbackformat',
                'graderid', 'timecreated', 'timemodified',
            ]);
            $judgements = new backup_nested_element('judgements');
            $judgement = new backup_nested_element('judgement', ['id'], [
                'courseid', 'criterionid', 'userid', 'scalevalue', 'comment', 'commentformat',
                'graderid', 'timecreated', 'timemodified',
            ]);
            $feedbackreads = new backup_nested_element('feedbackreads');
            $feedbackread = new backup_nested_element('feedbackread', ['id'], [
                'assessmentid', 'userid', 'timeread',
            ]);

            $wrapper->add_child($assessments);
            $assessments->add_child($assessment);
            $wrapper->add_child($checklistresponses);
            $checklistresponses->add_child($checklistresponse);
            $wrapper->add_child($judgements);
            $judgements->add_child($judgement);
            $wrapper->add_child($feedbackreads);
            $feedbackreads->add_child($feedbackread);

            $assessment->set_source_table('local_crout_assessment', ['courseid' => backup::VAR_COURSEID]);
            $assessment->annotate_ids('local_crout_criterion', 'criterionid');
            $assessment->annotate_ids('user', 'userid');
            $assessment->annotate_ids('user', 'graderid');

            $checklistresponse->set_source_sql(
                'SELECT r.*
                   FROM {local_crout_checklist_resp} r
                   JOIN {local_crout_checklist_def} d ON d.id = r.definitionid
                  WHERE d.courseid = ?',
                [backup::VAR_COURSEID]
            );
            $checklistresponse->annotate_ids('local_crout_checklist_def', 'definitionid');
            $checklistresponse->annotate_ids('local_crout_checklist_item', 'itemid');
            $checklistresponse->annotate_ids('user', 'userid');
            $checklistresponse->annotate_ids('user', 'graderid');

            $judgement->set_source_table('local_crout_judgement', ['courseid' => backup::VAR_COURSEID]);
            $judgement->annotate_ids('local_crout_criterion', 'criterionid');
            $judgement->annotate_ids('user', 'userid');
            $judgement->annotate_ids('user', 'graderid');

            $feedbackread->set_source_sql(
                'SELECT r.*
                   FROM {local_crout_feedback_read} r
                   JOIN {local_crout_assessment} a ON a.id = r.assessmentid
                  WHERE a.courseid = ?',
                [backup::VAR_COURSEID]
            );
            $feedbackread->annotate_ids('local_crout_assessment', 'assessmentid');
            $feedbackread->annotate_ids('user', 'userid');
        }

        return $plugin;
    }

    /**
     * Define user-level plugin backup structure.
     */
    protected function define_user_plugin_structure(): backup_plugin_element {
        $plugin = $this->get_plugin_element(null, null, null);
        $wrapper = new backup_nested_element($this->get_recommended_name());

        // Teacher assessments per criterion per student.
        $assessments = new backup_nested_element('assessments');
        $assessment = new backup_nested_element('assessment', ['id'], [
            'courseid', 'criterionid', 'userid', 'sourcetype', 'sourceid', 'sourceinstanceid',
            'assessmentmode', 'value', 'scalevalue', 'feedback', 'feedbackformat',
            'instrumenttype', 'instrumentinstanceid', 'status', 'graderid',
            'timecreated', 'timemodified', 'timepublished',
        ]);

        // Checklist responses per user.
        $checklistresponses = new backup_nested_element('checklistresponses');
        $checklistresponse = new backup_nested_element('checklistresponse', ['id'], [
            'definitionid', 'itemid', 'userid', 'state', 'feedback', 'feedbackformat',
            'graderid', 'timecreated', 'timemodified',
        ]);

        // Current judgement per criterion.
        $judgements = new backup_nested_element('judgements');
        $judgement = new backup_nested_element('judgement', ['id'], [
            'courseid', 'criterionid', 'userid', 'scalevalue', 'comment', 'commentformat',
            'graderid', 'timecreated', 'timemodified',
        ]);

        // Feedback read tracking.
        $feedbackreads = new backup_nested_element('feedbackreads');
        $feedbackread = new backup_nested_element('feedbackread', ['id'], [
            'assessmentid', 'userid', 'timeread',
        ]);

        $plugin->add_child($wrapper);
        $wrapper->add_child($assessments);
        $assessments->add_child($assessment);

        $wrapper->add_child($checklistresponses);
        $checklistresponses->add_child($checklistresponse);

        $wrapper->add_child($judgements);
        $judgements->add_child($judgement);

        $wrapper->add_child($feedbackreads);
        $feedbackreads->add_child($feedbackread);

        $assessment->set_source_table('local_crout_assessment', ['userid' => backup::VAR_USERID]);
        $assessment->annotate_ids('local_crout_criterion', 'criterionid');
        $assessment->annotate_ids('user', 'userid');
        $assessment->annotate_ids('user', 'graderid');

        $checklistresponse->set_source_table('local_crout_checklist_resp', ['userid' => backup::VAR_USERID]);
        $checklistresponse->annotate_ids('local_crout_checklist_def', 'definitionid');
        $checklistresponse->annotate_ids('local_crout_checklist_item', 'itemid');
        $checklistresponse->annotate_ids('user', 'userid');
        $checklistresponse->annotate_ids('user', 'graderid');

        $judgement->set_source_table('local_crout_judgement', ['userid' => backup::VAR_USERID]);
        $judgement->annotate_ids('local_crout_criterion', 'criterionid');
        $judgement->annotate_ids('user', 'userid');
        $judgement->annotate_ids('user', 'graderid');

        $feedbackread->set_source_table('local_crout_feedback_read', ['userid' => backup::VAR_USERID]);
        $feedbackread->annotate_ids('local_crout_assessment', 'assessmentid');
        $feedbackread->annotate_ids('user', 'userid');

        return $plugin;
    }
}
