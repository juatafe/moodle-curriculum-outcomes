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

namespace local_criteriaoutcomes\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/**
 * Dynamic quiz-slot criterion mapping form.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class quiz_mapping_form extends \moodleform {
    /**
     * Define fields from already-authorised quiz structure and criteria.
     */
    protected function definition(): void {
        $form = $this->_form;
        $criteria = $this->_customdata['criteria'];
        $mappings = $this->_customdata['mappings'];
        $configurations = $this->_customdata['configurations'];
        foreach ($this->_customdata['slots'] as $slot) {
            $random = $slot->random ? ' — ' . get_string('randomslot', 'local_criteriaoutcomes') : '';
            $form->addElement(
                'header',
                'slotheader_' . $slot->id,
                get_string('slotheading', 'local_criteriaoutcomes', (object)[
                    'number' => $slot->slot, 'name' => s($slot->questionname), 'random' => $random,
                ])
            );
            $form->addElement(
                'static',
                'slotinfo_' . $slot->id,
                '',
                get_string('slotinfo', 'local_criteriaoutcomes', (object)[
                    'type' => s($slot->questiontype), 'maxmark' => format_float($slot->maxmark, 2),
                    'text' => s($slot->questiontext),
                ])
            );
            if ($slot->random) {
                $form->addElement(
                    'static',
                    'randomwarning_' . $slot->id,
                    '',
                    get_string('randomwarning', 'local_criteriaoutcomes')
                );
            }
            $lastparent = null;
            foreach ($criteria as $criterion) {
                if ($lastparent !== $criterion->parentid) {
                    $form->addElement(
                        'static',
                        'parent_' . $slot->id . '_' . $criterion->parentid,
                        '',
                        s($criterion->parentcode . ' — ' . $criterion->parentname)
                    );
                    $lastparent = $criterion->parentid;
                }
                $key = $slot->id . '_' . $criterion->id;
                $form->addElement('checkbox', 'map_' . $key, '', s($criterion->code . ' — ' . $criterion->name));
                $form->addElement(
                    'text',
                    'weight_' . $key,
                    get_string('mappingweight', 'local_criteriaoutcomes'),
                    ['size' => 8]
                );
                $form->setType('weight_' . $key, PARAM_FLOAT);
                $form->setDefault('weight_' . $key, isset($mappings[$key]) ? $mappings[$key]->weight : 1);
                $form->hideIf('weight_' . $key, 'map_' . $key, 'notchecked');
                $form->addHelpButton('weight_' . $key, 'mappingweight', 'local_criteriaoutcomes');
                if (isset($mappings[$key])) {
                    $form->setDefault('map_' . $key, 1);
                }
            }
        }
        $aggregationadded = false;
        foreach ($criteria as $criterion) {
            if (($this->_customdata['mappedcounts'][$criterion->id] ?? 0) < 2) {
                continue;
            }
            if (!$aggregationadded) {
                $form->addElement('header', 'aggregations', get_string('aggregations', 'local_criteriaoutcomes'));
                $form->addElement(
                    'static',
                    'aggregationhelp',
                    '',
                    get_string('aggregationshelp', 'local_criteriaoutcomes')
                );
                $aggregationadded = true;
            }
            $name = 'aggregation_' . $criterion->id;
            $form->addElement('select', $name, s($criterion->code), [
                'mean' => get_string('aggregationmean', 'local_criteriaoutcomes'),
                'weightedmean' => get_string('aggregationweightedmean', 'local_criteriaoutcomes'),
            ]);
            $form->setType($name, PARAM_ALPHA);
            $form->addHelpButton($name, 'aggregations', 'local_criteriaoutcomes');
            $form->setDefault($name, isset($configurations[$criterion->id]) ?
                $configurations[$criterion->id]->aggregation : 'mean');
        }
        $form->addElement('hidden', 'quizid', $this->_customdata['quizid']);
        $form->setType('quizid', PARAM_INT);
        $this->add_action_buttons(false, get_string('savemappings', 'local_criteriaoutcomes'));
    }
}
