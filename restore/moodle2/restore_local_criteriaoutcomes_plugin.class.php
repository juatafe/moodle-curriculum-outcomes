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
 * Course restore support.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Restores curriculum hierarchy and remaps core Outcomes.
 */
class local_criteriaoutcomes_restore_implementation extends restore_local_plugin {
    /**
     * @var array Quiz configurations waiting until activity mappings exist.
     */
    private array $pendingquizconfigurations = [];

    /**
     * @var array Quiz mappings waiting until activity mappings exist.
     */
    private array $pendingquizmappings = [];

    /**
     * @var array Checklist maps waiting until checklist items and criteria exist.
     */
    private array $pendingchecklistmaps = [];

    /**
     * @var array Rubric maps waiting until rubric criteria and curriculum criteria exist.
     */
    private array $pendingrubricmaps = [];

    /**
     * @var array Assessments waiting until criteria exist.
     */
    private array $pendingassessments = [];

    /**
     * @var array Checklist responses waiting until checklist defs/items exist.
     */
    private array $pendingchecklistresponses = [];

    /**
     * @var array Judgements waiting until criteria exist.
     */
    private array $pendingjudgements = [];

    /**
     * @var array Feedback reads waiting until assessments exist.
     */
    private array $pendingfeedbackreads = [];

    /**
     * @var array Structural import audit batches waiting for hierarchy mappings.
     */
    private array $pendingimportbatches = [];

    /**
     * @var array Import audit items indexed by their old batch id.
     */
    private array $pendingimportitems = [];

    /**
     * Define activity-level quiz mapping restore paths.
     */
    protected function define_module_plugin_structure(): array {
        if ($this->task->get_modulename() !== 'quiz') {
            return [];
        }
        $root = $this->get_pathfor('/');
        return [
            new restore_path_element(
                'local_crout_quiz_configuration',
                $root . '/quiz_configurations/quiz_configuration'
            ),
            new restore_path_element('local_crout_quiz_mapping', $root . '/quiz_mappings/quiz_mapping'),
        ];
    }

    /**
     * Define course-level restore paths.
     */
    protected function define_course_plugin_structure(): array {
        $paths = [];
        $root = $this->get_pathfor('/frameworks/framework');
        $paths[] = new restore_path_element('local_crout_framework', $root);
        $paths[] = new restore_path_element('local_crout_parent', $root . '/parents/parent');
        $paths[] = new restore_path_element('local_crout_criterion', $root . '/parents/parent/criteria/criterion');
        $paths[] = new restore_path_element(
            'local_crout_rubricmap',
            $this->get_pathfor('/rubricmaps/rubricmap')
        );
        $paths[] = new restore_path_element(
            'local_crout_checklist_def',
            $this->get_pathfor('/checklistdefs/checklistdef')
        );
        $paths[] = new restore_path_element(
            'local_crout_checklist_item',
            $this->get_pathfor('/checklistdefs/checklistdef/checklistitems/checklistitem')
        );
        $paths[] = new restore_path_element(
            'local_crout_checklist_map',
            $this->get_pathfor('/checklistdefs/checklistdef/checklistitems/checklistitem/checklistmaps/checklistmap')
        );
        $paths[] = new restore_path_element(
            'local_crout_user_assessment',
            $this->get_pathfor('/assessments/assessment')
        );
        $paths[] = new restore_path_element(
            'local_crout_user_checklistresponse',
            $this->get_pathfor('/checklistresponses/checklistresponse')
        );
        $paths[] = new restore_path_element(
            'local_crout_user_judgement',
            $this->get_pathfor('/judgements/judgement')
        );
        $paths[] = new restore_path_element(
            'local_crout_user_feedbackread',
            $this->get_pathfor('/feedbackreads/feedbackread')
        );
        $paths[] = new restore_path_element(
            'local_crout_importbatch',
            $this->get_pathfor('/importbatches/importbatch')
        );
        $paths[] = new restore_path_element(
            'local_crout_importitem',
            $this->get_pathfor('/importitems/importitem')
        );
        return $paths;
    }

    /**
     * Define user-level restore paths.
     */
    protected function define_user_plugin_structure(): array {
        $root = $this->get_pathfor('/');
        return [
            new restore_path_element('local_crout_user_assessment', $root . '/assessments/assessment'),
            new restore_path_element('local_crout_user_checklistresponse', $root . '/checklistresponses/checklistresponse'),
            new restore_path_element('local_crout_user_judgement', $root . '/judgements/judgement'),
            new restore_path_element('local_crout_user_feedbackread', $root . '/feedbackreads/feedbackread'),
        ];
    }

    // Course-level processors.
    /**
     * Restore one curriculum framework.
     */
    public function process_local_crout_framework(array $data): void {
        global $DB;
        $oldid = $data['id'];
        unset($data['id']);
        $data['courseid'] = $this->task->get_courseid();
        $existing = $DB->get_record('local_crout_framework', [
            'courseid' => $data['courseid'],
            'identitykey' => $data['identitykey'],
        ]);
        $newid = $existing ? $existing->id : $DB->insert_record('local_crout_framework', (object)$data);
        $this->set_mapping('local_crout_framework', $oldid, $newid);
    }

    /**
     * Restore one RA or CE parent.
     */
    public function process_local_crout_parent(array $data): void {
        global $DB;
        $oldid = $data['id'];
        unset($data['id']);
        $data['frameworkid'] = $this->get_new_parentid('local_crout_framework');
        $existing = $DB->get_record('local_crout_parent', ['frameworkid' => $data['frameworkid'], 'code' => $data['code']]);
        $newid = $existing ? $existing->id : $DB->insert_record('local_crout_parent', (object)$data);
        $this->set_mapping('local_crout_parent', $oldid, $newid);
    }

    /**
     * Restore one criterion and its remapped Outcome relationship.
     */
    public function process_local_crout_criterion(array $data): void {
        global $DB;
        $oldid = $data['id'];
        unset($data['id']);
        $data['parentid'] = $this->get_new_parentid('local_crout_parent');
        $data['outcomeid'] = $this->get_mappingid('outcome', $data['outcomeid'], 0);
        $criterionexists = $DB->record_exists('local_crout_criterion', [
            'parentid' => $data['parentid'],
            'code' => $data['code'],
        ]);
        if ($criterionexists) {
            $newid = $DB->get_field('local_crout_criterion', 'id', [
                'parentid' => $data['parentid'], 'code' => $data['code'],
            ]);
        } else {
            $newid = $DB->insert_record('local_crout_criterion', (object)$data);
        }
        if ($newid) {
            $this->set_mapping('local_crout_criterion', $oldid, $newid);
        }
    }

    /**
     * Restore one rubric dimension → curriculum criterion mapping.
     */
    public function process_local_crout_rubricmap(array $data): void {
        $this->pendingrubricmaps[] = $data;
    }

    /**
     * Restore one checklist definition.
     */
    public function process_local_crout_checklist_def(array $data): void {
        global $DB;
        $oldid = $data['id'];
        unset($data['id']);
        $data['courseid'] = $this->task->get_courseid();
        $existing = $DB->get_record('local_crout_checklist_def', [
            'courseid' => $data['courseid'], 'name' => $data['name'],
        ]);
        $newid = $existing ? $existing->id : $DB->insert_record('local_crout_checklist_def', (object)$data);
        $this->set_mapping('local_crout_checklist_def', $oldid, $newid);
    }

    /**
     * Restore one checklist item.
     */
    public function process_local_crout_checklist_item(array $data): void {
        global $DB;
        $oldid = $data['id'];
        unset($data['id']);
        $data['definitionid'] = $this->get_new_parentid('local_crout_checklist_def');
        $newid = $DB->insert_record('local_crout_checklist_item', (object)$data);
        $this->set_mapping('local_crout_checklist_item', $oldid, $newid);
    }

    /**
     * Restore one checklist item → criterion mapping.
     */
    public function process_local_crout_checklist_map(array $data): void {
        $this->pendingchecklistmaps[] = $data;
    }

    /**
     * Restore one quiz/criterion aggregation configuration.
     */
    public function process_local_crout_quiz_configuration(array $data): void {
        $this->pendingquizconfigurations[] = $data;
    }

    /**
     * Restore a quiz slot mapping using Moodle's real quiz slot restore mapping.
     */
    public function process_local_crout_quiz_mapping(array $data): void {
        $this->pendingquizmappings[] = $data;
    }

    // User-level processors.

    /**
     * Restore one teacher assessment.
     */
    public function process_local_crout_user_assessment(array $data): void {
        $this->pendingassessments[] = $data;
    }

    /**
     * Restore one checklist response.
     */
    public function process_local_crout_user_checklistresponse(array $data): void {
        $this->pendingchecklistresponses[] = $data;
    }

    /**
     * Restore one judgement.
     */
    public function process_local_crout_user_judgement(array $data): void {
        $this->pendingjudgements[] = $data;
    }

    /**
     * Restore one feedback read record.
     */
    public function process_local_crout_user_feedbackread(array $data): void {
        $this->pendingfeedbackreads[] = $data;
    }

    /**
     * Queue one structural import batch until framework and user mappings exist.
     */
    public function process_local_crout_importbatch(array $data): void {
        $this->pendingimportbatches[] = $data;
    }

    /**
     * Queue one batch item; its parent id is the old batch id.
     */
    public function process_local_crout_importitem(array $data): void {
        $this->pendingimportitems[] = $data;
    }

    // Deferred insert hooks.

    /**
     * Insert deferred quiz records after mod_quiz has published its restore mappings.
     */
    public function after_restore_module(): void {
        global $DB;

        foreach ($this->pendingquizconfigurations as $data) {
            unset($data['id']);
            $data['quizid'] = $this->get_mappingid('quiz', $data['quizid']);
            $data['criterionid'] = $this->get_mappingid('local_crout_criterion', $data['criterionid']);
            if (
                $data['quizid'] && $data['criterionid'] && !$DB->record_exists('local_crout_quizcfg', [
                    'quizid' => $data['quizid'], 'criterionid' => $data['criterionid'],
                ])
            ) {
                $DB->insert_record('local_crout_quizcfg', (object)$data);
            }
        }

        foreach ($this->pendingquizmappings as $data) {
            unset($data['id']);
            $data['quizid'] = $this->get_mappingid('quiz', $data['quizid']);
            $data['slotid'] = $this->get_mappingid('quiz_question_instance', $data['slotid']);
            $data['criterionid'] = $this->get_mappingid('local_crout_criterion', $data['criterionid']);
            if (
                $data['quizid'] && $data['slotid'] && $data['criterionid'] &&
                    !$DB->record_exists('local_crout_quizmap', [
                        'quizid' => $data['quizid'], 'slotid' => $data['slotid'],
                        'criterionid' => $data['criterionid'],
                    ])
            ) {
                $DB->insert_record('local_crout_quizmap', (object)$data);
            }
        }

        $this->pendingquizconfigurations = [];
        $this->pendingquizmappings = [];
    }

    /**
     * Insert deferred checklist maps, rubric maps, assessments, checklist responses,
     * judgements and feedback reads after all course data is restored.
     */
    public function after_restore_course(): void {
        global $DB;

        foreach ($this->pendingimportbatches as $data) {
            $oldid = $data['id'];
            unset($data['id']);
            $data['courseid'] = $this->task->get_courseid();
            $data['frameworkid'] = empty($data['frameworkid']) ? null : $this->get_mappingid(
                'local_crout_framework',
                $data['frameworkid'],
                0
            );
            $data['userid'] = empty($data['userid']) ? null : $this->get_mappingid('user', $data['userid'], null);
            $newid = $DB->insert_record('local_crout_importbatch', (object)$data);
            $this->set_mapping('local_crout_importbatch', $oldid, $newid);
        }

        foreach ($this->pendingimportitems as $data) {
            unset($data['id']);
            $data['batchid'] = $this->get_mappingid('local_crout_importbatch', $data['batchid'], 0);
            if (!empty($data['entityid'])) {
                $mappingname = match ($data['entitytype']) {
                    'framework' => 'local_crout_framework',
                    'parent' => 'local_crout_parent',
                    'criterion' => 'local_crout_criterion',
                    default => null,
                };
                $data['entityid'] = $mappingname ? $this->get_mappingid($mappingname, $data['entityid'], null) : null;
            }
            if ($data['batchid']) {
                $DB->insert_record('local_crout_importitem', (object)$data);
            }
        }

        foreach ($this->pendingchecklistmaps as $data) {
            unset($data['id']);
            $data['itemid'] = $this->get_mappingid('local_crout_checklist_item', $data['itemid']);
            $data['criterionid'] = $this->get_mappingid('local_crout_criterion', $data['criterionid']);
            if (
                $data['itemid'] && $data['criterionid'] && !$DB->record_exists('local_crout_checklist_map', [
                    'itemid' => $data['itemid'], 'criterionid' => $data['criterionid'],
                ])
            ) {
                $DB->insert_record('local_crout_checklist_map', (object)$data);
            }
        }

        foreach ($this->pendingrubricmaps as $data) {
            unset($data['id']);
            $data['courseid'] = $this->task->get_courseid();
            $data['rubriccriterionid'] = $this->get_mappingid(
                'gradingform_rubric_criterion',
                $data['rubriccriterionid'],
                0
            );
            $data['curriculumcriterionid'] = $this->get_mappingid(
                'local_crout_criterion',
                $data['curriculumcriterionid'],
                0
            );
            if (
                $data['rubriccriterionid'] && $data['curriculumcriterionid'] &&
                    !$DB->record_exists('local_crout_rubricmap', [
                        'rubriccriterionid' => $data['rubriccriterionid'],
                        'curriculumcriterionid' => $data['curriculumcriterionid'],
                    ])
            ) {
                $DB->insert_record('local_crout_rubricmap', (object)$data);
            }
        }

        foreach ($this->pendingassessments as $data) {
            $oldid = $data['id'];
            unset($data['id']);
            $data['courseid'] = $this->task->get_courseid();
            $data['criterionid'] = $this->get_mappingid('local_crout_criterion', $data['criterionid'], 0);
            $data['userid'] = $this->get_mappingid('user', $data['userid']);
            $data['graderid'] = $this->get_mappingid('user', $data['graderid']);
            if ($data['criterionid'] && $data['userid'] && $data['graderid']) {
                $newid = $DB->insert_record('local_crout_assessment', (object)$data);
                $this->set_mapping('local_crout_assessment', $oldid, $newid);
            }
        }

        foreach ($this->pendingchecklistresponses as $data) {
            unset($data['id']);
            $data['definitionid'] = $this->get_mappingid('local_crout_checklist_def', $data['definitionid'], 0);
            $data['itemid'] = $this->get_mappingid('local_crout_checklist_item', $data['itemid'], 0);
            $data['userid'] = $this->get_mappingid('user', $data['userid']);
            $data['graderid'] = $this->get_mappingid('user', $data['graderid']);
            if ($data['definitionid'] && $data['itemid'] && $data['userid'] && $data['graderid']) {
                $DB->insert_record('local_crout_checklist_resp', (object)$data);
            }
        }

        foreach ($this->pendingjudgements as $data) {
            unset($data['id']);
            $data['courseid'] = $this->task->get_courseid();
            $data['criterionid'] = $this->get_mappingid('local_crout_criterion', $data['criterionid'], 0);
            $data['userid'] = $this->get_mappingid('user', $data['userid']);
            $data['graderid'] = $this->get_mappingid('user', $data['graderid']);
            if ($data['criterionid'] && $data['userid'] && $data['graderid']) {
                $DB->insert_record('local_crout_judgement', (object)$data);
            }
        }

        foreach ($this->pendingfeedbackreads as $data) {
            unset($data['id']);
            $data['assessmentid'] = $this->get_mappingid('local_crout_assessment', $data['assessmentid'], 0);
            $data['userid'] = $this->get_mappingid('user', $data['userid']);
            if ($data['assessmentid'] && $data['userid']) {
                $DB->insert_record('local_crout_feedback_read', (object)$data);
            }
        }

        $this->pendingchecklistmaps = [];
        $this->pendingrubricmaps = [];
        $this->pendingassessments = [];
        $this->pendingchecklistresponses = [];
        $this->pendingjudgements = [];
        $this->pendingfeedbackreads = [];
        $this->pendingimportbatches = [];
        $this->pendingimportitems = [];
    }
}
