<?php
// phpcs:disable -- Compatibility shim for the interrupted 0.3 restore implementation.
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
class local_criteriaoutcomes_restore_legacy extends restore_local_plugin {
    /**
 * @var array Quiz configurations waiting until activity mappings exist.
 */
    private array $pendingquizconfigurations = [];

    /**
 * @var array Quiz mappings waiting until activity mappings exist.
 */
    private array $pendingquizmappings = [];

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
        return $paths;
    }
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
        if ($data['outcomeid'] && !$criterionexists) {
            $newid = $DB->insert_record('local_crout_criterion', (object)$data);
        } else {
            $newid = $DB->get_field('local_crout_criterion', 'id', [
                'parentid' => $data['parentid'], 'code' => $data['code'],
            ]);
        }
        if ($newid) {
            $this->set_mapping('local_crout_criterion', $oldid, $newid);
        }
    }

    /**
     * Restore one quiz/criterion aggregation configuration.
     */
    public function process_local_crout_quiz_configuration(array $data): void {
        $this->pendingquizconfigurations[] = $data;
    }

    /**
     * Restore a mapping using Moodle's real quiz slot restore mapping.
     */
    public function process_local_crout_quiz_mapping(array $data): void {
        $this->pendingquizmappings[] = $data;
    }

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
    }
}

require_once(dirname(__DIR__, 2) . '/restore/moodle2/restore_local_criteriaoutcomes_plugin.class.php');

/**
 * Canonical Moodle loader class backed by the complete 0.3 restore implementation.
 */
class restore_local_criteriaoutcomes_plugin extends local_criteriaoutcomes_restore_implementation {
}
