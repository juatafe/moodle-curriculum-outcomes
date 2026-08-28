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
 * Curriculum import form.
 *
 *  local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_criteriaoutcomes\form;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/**
 * Accepts either a JSON upload or pasted JSON.
 */
class import_form extends \moodleform {
    /**
     * Define form fields.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement(
            'filepicker',
            'jsonfile',
            get_string('jsonfile', 'local_criteriaoutcomes'),
            null,
            ['accepted_types' => ['.json'], 'maxbytes' => \local_criteriaoutcomes\provider\json_provider::MAX_BYTES]
        );
        $mform->addElement(
            'textarea',
            'jsontext',
            get_string('jsontext', 'local_criteriaoutcomes'),
            ['rows' => 14, 'class' => 'w-100']
        );
        $mform->setType('jsontext', PARAM_RAW);
        $valuationoptions = [
            $mform->createElement(
                'radio',
                'valuation',
                '',
                get_string('valuationachievement', 'local_criteriaoutcomes'),
                'achievement'
            ),
            $mform->createElement(
                'radio',
                'valuation',
                '',
                get_string('valuationnumeric', 'local_criteriaoutcomes'),
                'numeric'
            ),
            $mform->createElement(
                'radio',
                'valuation',
                '',
                get_string('valuationexisting', 'local_criteriaoutcomes'),
                'existing'
            ),
        ];
        $mform->addGroup(
            $valuationoptions,
            'valuationgroup',
            get_string('choosevaluation', 'local_criteriaoutcomes'),
            ['<br>'],
            false
        );
        $mform->addRule('valuationgroup', null, 'required', null, 'client');
        $mform->setType('valuation', PARAM_ALPHA);
        $scaleoptions = [0 => get_string('choosedots')] + $this->_customdata['scales'];
        $mform->addElement('select', 'scaleid', get_string('selectscale', 'local_criteriaoutcomes'), $scaleoptions);
        $mform->setType('scaleid', PARAM_INT);
        $mform->setDefault('scaleid', 0);
        $mform->hideIf('scaleid', 'valuation', 'neq', 'existing');
        $mform->addElement('hidden', 'id', $this->_customdata['courseid']);
        $mform->setType('id', PARAM_INT);
        $this->add_action_buttons(true, get_string('preview', 'local_criteriaoutcomes'));
    }

    /**
     * Validate the explicit valuation choice without accepting an arbitrary default scale.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $valuation = $data['valuation'] ?? '';
        if (!in_array($valuation, ['achievement', 'numeric', 'existing'], true)) {
            $errors['valuationgroup'] = get_string('required');
        } else if ($valuation === 'existing' && empty($data['scaleid'])) {
            $errors['scaleid'] = get_string('required');
        }
        return $errors;
    }

    /**
     * Return uploaded content in preference to pasted content.
     */
    public function content(): string {
        $file = $this->get_file_content('jsonfile');
        $data = $this->get_data();
        return $file !== false && $file !== '' ? $file : trim((string)($data->jsontext ?? ''));
    }
}
