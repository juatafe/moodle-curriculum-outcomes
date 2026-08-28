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

namespace local_criteriaoutcomes;

/**
 * Constants for the assessment system.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class constants {
    /**
     * Evidence source: native Moodle Outcome grade item.
     */
    public const SOURCE_NATIVE_OUTCOME = 'native_outcome';

    /**
     * Evidence source: quiz criterion evidence (0.2).
     */
    public const SOURCE_QUIZ_CRITERION = 'quiz_criterion';

    /**
     * Evidence source: direct teacher assessment.
     */
    public const SOURCE_DIRECT = 'direct';

    /**
     * Evidence source: rubric dimension result.
     */
    public const SOURCE_RUBRIC = 'rubric';

    /**
     * Evidence source: checklist item response.
     */
    public const SOURCE_CHECKLIST = 'checklist';

    /**
     * Assessment mode: feedback only, no scale value.
     */
    public const MODE_FEEDBACK_ONLY = 'feedback_only';

    /**
     * Assessment mode: scale value only, no feedback required.
     */
    public const MODE_VALUE_ONLY = 'value_only';

    /**
     * Assessment mode: both scale value and feedback.
     */
    public const MODE_VALUE_AND_FEEDBACK = 'value_and_feedback';

    /**
     * Assessment status: draft, not visible to students.
     */
    public const STATUS_DRAFT = 'draft';

    /**
     * Assessment status: released, visible to students.
     */
    public const STATUS_RELEASED = 'released';

    /**
     * Checklist item state: not done.
     */
    public const CHECKLIST_NOT_DONE = 'not_done';

    /**
     * Checklist item state: partially done.
     */
    public const CHECKLIST_PARTIAL = 'partial';

    /**
     * Checklist item state: done.
     */
    public const CHECKLIST_DONE = 'done';

    /**
     * Checklist mode: binary (done/not_done).
     */
    public const CHECKLIST_BINARY = 'binary';

    /**
     * Checklist mode: three-state (not_done/partial/done).
     */
    public const CHECKLIST_THREE_STATE = 'three_state';

    /**
     * Instrument type: direct assessment.
     */
    public const INSTRUMENT_DIRECT = 'direct';

    /**
     * Instrument type: rubric.
     */
    public const INSTRUMENT_RUBRIC = 'rubric';

    /**
     * Instrument type: checklist.
     */
    public const INSTRUMENT_CHECKLIST = 'checklist';

    /**
     * All valid source types.
     */
    public const VALID_SOURCE_TYPES = [
        self::SOURCE_NATIVE_OUTCOME,
        self::SOURCE_QUIZ_CRITERION,
        self::SOURCE_DIRECT,
        self::SOURCE_RUBRIC,
        self::SOURCE_CHECKLIST,
    ];

    /**
     * All valid assessment modes.
     */
    public const VALID_ASSESSMENT_MODES = [
        self::MODE_FEEDBACK_ONLY,
        self::MODE_VALUE_ONLY,
        self::MODE_VALUE_AND_FEEDBACK,
    ];

    /**
     * All valid assessment statuses.
     */
    public const VALID_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_RELEASED,
    ];
}
