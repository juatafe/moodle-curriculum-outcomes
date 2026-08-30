<?php
// phpcs:ignoreFile
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
 * Behat steps for Curriculum Outcomes.
 *
 * @package local_criteriaoutcomes
 * @copyright 2026 Juan Bautista Talens Felis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Navigation steps that resolve generated course identifiers safely.
 */
class behat_local_criteriaoutcomes extends behat_base {
    /**
     * @var array Criterion IDs resolved during this scenario.
     */
    private array $criterionids = [];

    /**
     * Visit one of the required language-smoke pages in a selected language.
     *
     * @Given /^I visit the "(main|boe|management|history|progress)" plugin page for course "([^"]*)" in language "([a-z_]+)"$/
     */
    public function i_visit_plugin_page_for_course_in_language(
        string $page,
        string $shortname,
        string $language
    ): void {
        global $DB;
        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $scripts = [
            'main' => 'index.php',
            'boe' => 'boe.php',
            'management' => 'curriculum_manage.php',
            'history' => 'import_history.php',
            'progress' => 'student_progress.php',
        ];
        $url = new moodle_url('/local/criteriaoutcomes/' . $scripts[$page], [
            'id' => $courseid,
            'lang' => $language,
        ]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
    }

    /**
     * Select a criterion in the real bulk-management form by its stable label.
     *
     * @When /^I select criterion "([^"]*)" for the safe curriculum operation$/
     */
    public function i_select_criterion_for_the_safe_curriculum_operation(string $code): void {
        $checkbox = $this->getSession()->getPage()->find(
            'css',
            'input[name="criterionids[]"][aria-label="' . $code . '"]'
        );
        if (!$checkbox) {
            throw new \Exception('The curriculum-management checkbox was not found: ' . $code);
        }
        $checkbox->check();
    }

    /**
     * Submit a BOE form by its stable hidden action through the browser.
     *
     * @When /^I submit the BOE "(search|load|preview|confirm)" action$/
     */
    public function i_submit_the_boe_action(string $action): void {
        $hidden = $this->getSession()->getPage()->find(
            'css',
            'form input[name="action"][value="' . $action . '"]'
        );
        if (!$hidden) {
            throw new \Exception('The BOE action form was not found: ' . $action);
        }
        $form = $hidden->getParent();
        while ($form && strtolower($form->getTagName()) !== 'form') {
            $form = $form->getParent();
        }
        $button = $form ? $form->find('css', 'button[type="submit"]') : null;
        if (!$button) {
            throw new \Exception('The BOE action submit button was not found: ' . $action);
        }
        $button->click();
    }

    /**
     * Seed deterministic responses for the fixed BOE endpoints.
     *
     * This helper is deliberately unavailable outside a Behat installation. It
     * primes the normal Moodle cache; the browser still uses the real UI,
     * provider, parser and import service.
     *
     * @Given /^the controlled BOE curriculum fixture is available$/
     */
    public function the_controlled_boe_curriculum_fixture_is_available(): void {
        global $CFG;
        if (empty($CFG->behat_dataroot)) {
            throw new \coding_exception('The controlled BOE fixture is available only under Behat.');
        }

        $identifier = 'BOE-A-2026-1';
        $basepath = \local_criteriaoutcomes\external\boe_client::BASEPATH . '/id/' . $identifier;
        $metadata = json_encode([
            'status' => ['code' => 200, 'text' => 'ok'],
            'data' => ['metadatos' => [
                'identificador' => $identifier,
                'titulo' => 'Norma curricular Behat controlada',
                'fecha_publicacion' => '2026-01-01',
                'numero_oficial' => 'TEST-1',
                'fecha_actualizacion' => '2026-01-02',
            ]],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $xml = file_get_contents(__DIR__ . '/../fixtures/boe/behat_fp.xml');
        if ($xml === false) {
            throw new \coding_exception('The controlled BOE XML fixture could not be read.');
        }

        $cache = \cache::make('local_criteriaoutcomes', 'boe_public');
        $cache->set(hash('sha256', 'application/json|' . $basepath . '/metadatos'), $metadata);
        $cache->set(hash('sha256', 'application/xml|' . $basepath . '/texto'), $xml);

        $esoid = 'BOE-A-2026-2';
        $esobasepath = \local_criteriaoutcomes\external\boe_client::BASEPATH . '/id/' . $esoid;
        $esometadata = json_encode([
            'status' => ['code' => 200, 'text' => 'ok'],
            'data' => ['metadatos' => [
                'identificador' => $esoid,
                'titulo' => 'Enseñanzas mínimas de la Educación Secundaria Obligatoria',
                'fecha_publicacion' => '2026-02-01',
                'fecha_actualizacion' => '2026-02-02',
            ]],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $esoxml = file_get_contents(__DIR__ . '/../fixtures/boe/eso_semantic_block.xml');
        if ($esoxml === false) {
            throw new \coding_exception('The controlled BOE ESO fixture could not be read.');
        }
        $cache->set(hash('sha256', 'application/json|' . $esobasepath . '/metadatos'), $esometadata);
        $cache->set(hash('sha256', 'application/xml|' . $esobasepath . '/texto'), $esoxml);

        $multiid = 'BOE-A-2026-3';
        $multibasepath = \local_criteriaoutcomes\external\boe_client::BASEPATH . '/id/' . $multiid;
        $multimetadata = json_encode([
            'status' => ['code' => 200, 'text' => 'ok'],
            'data' => ['metadatos' => [
                'identificador' => $multiid,
                'titulo' => 'Norma FP con varios títulos',
                'fecha_publicacion' => '2026-03-01',
            ]],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $multixml = file_get_contents(__DIR__ . '/../fixtures/boe/fp_multi_title.xml');
        if ($multixml === false) {
            throw new \coding_exception('The controlled multi-title FP fixture could not be read.');
        }
        $cache->set(hash('sha256', 'application/json|' . $multibasepath . '/metadatos'), $multimetadata);
        $cache->set(hash('sha256', 'application/xml|' . $multibasepath . '/texto'), $multixml);
    }

    /**
     * Add a real Moodle grade item linked to a generated criterion Outcome.
     *
     * @Given /^criterion "([^"]*)" in course "([^"]*)" has an academic grade item$/
     */
    public function criterion_in_course_has_an_academic_grade_item(string $code, string $shortname): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/grade/constants.php');
        require_once($CFG->libdir . '/grade/grade_category.php');
        require_once($CFG->libdir . '/grade/grade_item.php');
        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $criterion = $DB->get_record_sql(
            "SELECT c.id, c.outcomeid, o.scaleid
               FROM {local_crout_criterion} c
               JOIN {local_crout_parent} p ON p.id = c.parentid
               JOIN {local_crout_framework} f ON f.id = p.frameworkid
               JOIN {grade_outcomes} o ON o.id = c.outcomeid
              WHERE f.courseid = :courseid AND c.code = :code",
            ['courseid' => $courseid, 'code' => $code],
            MUST_EXIST
        );
        $item = new \grade_item((object)[
            'courseid' => $courseid,
            'itemtype' => 'manual',
            'itemname' => 'Behat evidence for ' . $code,
            'outcomeid' => $criterion->outcomeid,
            'gradetype' => GRADE_TYPE_SCALE,
            'scaleid' => $criterion->scaleid,
        ]);
        $item->insert('behat');
    }

    /**
     * Visit curriculum management for a generated course.
     *
     * @Given /^I visit curriculum management for course "([^"]*)"$/
     */
    public function i_visit_curriculum_management_for_course(string $shortname): void {
        $this->visit_course_plugin_page($shortname, 'curriculum_manage.php');
    }

    /**
     * Visit the newest successful import batch through the real history UI.
     *
     * @Given /^I visit the latest successful import batch for course "([^"]*)"$/
     */
    public function i_visit_the_latest_successful_import_batch_for_course(string $shortname): void {
        global $DB;
        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $batchid = $DB->get_field_sql(
            "SELECT MAX(id)
               FROM {local_crout_importbatch}
              WHERE courseid = :courseid AND operation = :operation AND status = :status",
            ['courseid' => $courseid, 'operation' => 'import', 'status' => 'success'],
            MUST_EXIST
        );
        $url = new moodle_url('/local/criteriaoutcomes/import_history.php', [
            'id' => $courseid,
            'batchid' => $batchid,
        ]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
    }

    /**
     * Assert exact active/archived criterion state and uniqueness in the DB.
     *
     * @Then /^course "([^"]*)" should have exactly "([0-9]+)" criterion "([^"]*)" with status "(active|archived)"$/
     */
    public function course_should_have_exactly_criterion_with_status(
        string $shortname,
        int $expected,
        string $code,
        string $status
    ): void {
        global $DB;
        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $actual = $DB->count_records_sql(
            "SELECT COUNT(c.id)
               FROM {local_crout_criterion} c
               JOIN {local_crout_parent} p ON p.id = c.parentid
               JOIN {local_crout_framework} f ON f.id = p.frameworkid
              WHERE f.courseid = :courseid AND c.code = :code AND c.archived = :archived",
            ['courseid' => $courseid, 'code' => $code, 'archived' => $status === 'archived' ? 1 : 0]
        );
        if ((int)$actual !== $expected) {
            throw new \Exception("Expected {$expected} {$status} {$code} criterion, found {$actual}.");
        }
    }

    /**
     * Assert exact framework and parent cardinality for an imported course.
     *
     * @Then /^course "([^"]*)" should have "([0-9]+)" framework and "([0-9]+)" parents$/
     */
    public function course_should_have_framework_and_parents(
        string $shortname,
        int $frameworks,
        int $parents
    ): void {
        global $DB;
        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $actualframeworks = $DB->count_records('local_crout_framework', ['courseid' => $courseid]);
        $actualparents = $DB->count_records_sql(
            "SELECT COUNT(p.id)
               FROM {local_crout_parent} p
               JOIN {local_crout_framework} f ON f.id = p.frameworkid
              WHERE f.courseid = :courseid",
            ['courseid' => $courseid]
        );
        if ((int)$actualframeworks !== $frameworks || (int)$actualparents !== $parents) {
            throw new \Exception(
                "Expected {$frameworks} framework and {$parents} parents, found " .
                "{$actualframeworks} and {$actualparents}."
            );
        }
    }

    /**
     * Assert that academic grade-item use survived an operation.
     *
     * @Then /^the academic grade item for criterion "([^"]*)" in course "([^"]*)" should be preserved$/
     */
    public function the_academic_grade_item_for_criterion_should_be_preserved(
        string $code,
        string $shortname
    ): void {
        global $DB;
        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $count = $DB->count_records_sql(
            "SELECT COUNT(gi.id)
               FROM {grade_items} gi
               JOIN {local_crout_criterion} c ON c.outcomeid = gi.outcomeid
               JOIN {local_crout_parent} p ON p.id = c.parentid
               JOIN {local_crout_framework} f ON f.id = p.frameworkid
              WHERE f.courseid = :courseid AND c.code = :code",
            ['courseid' => $courseid, 'code' => $code]
        );
        if ((int)$count !== 1) {
            throw new \Exception("Expected one preserved academic grade item for {$code}, found {$count}.");
        }
    }

    /**
     * Visit one course-scoped plugin page.
     */
    private function visit_course_plugin_page(string $shortname, string $page): void {
        global $DB;
        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $url = new moodle_url('/local/criteriaoutcomes/' . $page, ['id' => $courseid]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
    }

    /**
     * Set one dynamic mapping field through the browser.
     *
     * @When /^I map slot "([0-9]+)" of quiz "([^"]*)" to criterion "([^"]*)" with weight "([^"]*)"$/
     * @param int $slotnumber Visual slot number.
     * @param string $quizname Quiz name.
     * @param string $code Criterion code.
     * @param string $weight Mapping weight.
     */
    public function i_map_quiz_slot_to_criterion(
        int $slotnumber,
        string $quizname,
        string $code,
        string $weight
    ): void {
        global $DB;
        $query = [];
        parse_str((string)parse_url($this->getSession()->getCurrentUrl(), PHP_URL_QUERY), $query);
        $quizid = isset($query['quizid']) ? (int)$query['quizid'] : 0;
        if (!$quizid || !$DB->record_exists('quiz', ['id' => $quizid, 'name' => $quizname])) {
            throw new \Exception('The current page is not the requested quiz mapping page: ' .
                $this->getSession()->getCurrentUrl());
        }
        $slotid = $DB->get_field('quiz_slots', 'id', ['quizid' => $quizid, 'slot' => $slotnumber], MUST_EXIST);
        $criterionid = $DB->get_field_sql(
            "SELECT c.id
               FROM {local_crout_criterion} c
               JOIN {local_crout_parent} p ON p.id = c.parentid
               JOIN {local_crout_framework} f ON f.id = p.frameworkid
               JOIN {quiz} q ON q.course = f.courseid
              WHERE q.id = :quizid AND c.code = :code",
            ['quizid' => $quizid, 'code' => $code],
            MUST_EXIST
        );
        $this->criterionids[$code] = (int)$criterionid;
        $key = $slotid . '_' . $criterionid;
        $script = 'const checkbox = document.querySelector('
            . json_encode('[name="map_' . $key . '"]') . ');'
            . 'const weight = document.querySelector(' . json_encode('[name="weight_' . $key . '"]') . ');'
            . 'if (!checkbox || !weight) { throw new Error("Mapping fields not found"); }'
            . 'checkbox.checked = true; checkbox.dispatchEvent(new Event("change", {bubbles: true}));'
            . 'weight.value = ' . json_encode($weight) . '; weight.dispatchEvent(new Event("change", {bubbles: true}));';
        $this->getSession()->executeScript($script);
    }

    /**
     * Set aggregation for a dynamic criterion field.
     *
     * @When /^I set criterion "([^"]*)" aggregation to "([^"]*)"$/
     * @param string $code Criterion code.
     * @param string $aggregation Select value.
     */
    public function i_set_criterion_aggregation(string $code, string $aggregation): void {
        if (!isset($this->criterionids[$code])) {
            throw new \Exception('Map at least one slot to the criterion before selecting aggregation.');
        }
        $criterionid = $this->criterionids[$code];
        $selector = '[name="aggregation_' . $criterionid . '"]';
        $script = 'const field = document.querySelector(' . json_encode($selector) . ');'
            . 'if (!field) { throw new Error("Aggregation field not found"); }'
            . 'field.value = ' . json_encode($aggregation) . ';'
            . 'field.dispatchEvent(new Event("change", {bubbles: true}));';
        $this->getSession()->executeScript($script);
    }

    /**
     * Assert a generated mapping checkbox is checked.
     *
     * @Then /^the field matching "([^"]*)" should be checked$/
     * @param string $fragment Field-name fragment.
     */
    public function the_field_matching_should_be_checked(string $fragment): void {
        $field = $this->getSession()->getPage()->find('css', '[name*="' . $fragment . '"]');
        if (!$field || !$field->isChecked()) {
            throw new \Exception('No checked field name contains: ' . $fragment);
        }
    }

    /**
     * Assert a generated field has a value.
     *
     * @Then /^the field matching "([^"]*)" matches value "([^"]*)"$/
     * @param string $fragment Field-name fragment.
     * @param string $value Expected value.
     */
    public function the_field_matching_matches_value(string $fragment, string $value): void {
        $field = $this->getSession()->getPage()->find('css', '[name*="' . $fragment . '"]');
        if (!$field || (string)$field->getValue() !== $value) {
            throw new \Exception('No matching field has expected value: ' . $value);
        }
    }

    /**
     * Assert generated-field visibility after Moodle form dependency handling.
     *
     * @Then /^the field matching "([^"]*)" should( not)? be visible$/
     * @param string $fragment Field-name fragment.
     * @param string|null $negative Negated assertion marker.
     */
    public function the_field_matching_should_be_visible(string $fragment, ?string $negative = null): void {
        $field = $this->getSession()->getPage()->find('css', '[name*="' . $fragment . '"]');
        if (!$field) {
            throw new \Exception('No field name contains: ' . $fragment);
        }
        $expected = $negative === null;
        if ($field->isVisible() !== $expected) {
            throw new \Exception('Field visibility did not match expectation: ' . $fragment);
        }
    }

    /**
     * Create a course scale through the Moodle grade API.
     *
     * @Given /^the course "([^"]*)" has the outcome scale "([^"]*)"$/
     * @param string $shortname Course shortname.
     * @param string $name Scale name.
     */
    public function the_course_has_the_outcome_scale(string $shortname, string $name): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/grade/constants.php');
        require_once($CFG->libdir . '/grade/grade_scale.php');
        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $scale = new grade_scale();
        $scale->courseid = $courseid;
        $scale->userid = get_admin()->id;
        $scale->name = $name;
        $scale->scale = 'Not yet,Achieved';
        $scale->description = '';
        $scale->descriptionformat = FORMAT_HTML;
        $scale->insert('behat');
    }

    /**
     * Visit the plugin page for a generated course.
     *
     * @Given /^I visit the curriculum outcomes page for course "([^"]*)"$/
     * @param string $shortname Course shortname.
     */
    public function i_visit_the_curriculum_outcomes_page(string $shortname): void {
        global $DB;
        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $url = new moodle_url('/local/criteriaoutcomes/index.php', ['id' => $courseid]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
    }

    /**
     * Visit the quiz mapping selector for a generated course.
     *
     * @Given /^I visit the quiz criteria page for course "([^"]*)"$/
     * @param string $shortname Course shortname.
     */
    public function i_visit_the_quiz_criteria_page(string $shortname): void {
        global $DB;
        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $url = new moodle_url('/local/criteriaoutcomes/quiz.php', ['id' => $courseid]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
    }

    /**
     * Visit one quiz mapping page using generated course data.
     *
     * @Given /^I visit criterion mappings for quiz "([^"]*)" in course "([^"]*)"$/
     * @param string $quizname Quiz name.
     * @param string $shortname Course shortname.
     */
    public function i_visit_criterion_mappings_for_quiz(string $quizname, string $shortname): void {
        global $DB;
        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $quizid = $DB->get_field('quiz', 'id', ['course' => $courseid, 'name' => $quizname], MUST_EXIST);
        $url = new moodle_url('/local/criteriaoutcomes/quiz_mapping.php', ['quizid' => $quizid]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
    }

    /**
     * Visit the teacher assessment page for generated records.
     *
     * @Given /^I assess criterion "([^"]*)" for user "([^"]*)" in course "([^"]*)"$/
     */
    public function i_assess_criterion_for_user(string $code, string $username, string $shortname): void {
        global $DB;
        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $criterionid = $DB->get_field_sql(
            "SELECT c.id
               FROM {local_crout_criterion} c
               JOIN {local_crout_parent} p ON p.id = c.parentid
               JOIN {local_crout_framework} f ON f.id = p.frameworkid
              WHERE f.courseid = :courseid AND c.code = :code",
            ['courseid' => $courseid, 'code' => $code],
            MUST_EXIST
        );
        $url = new moodle_url('/local/criteriaoutcomes/assessment.php', [
            'courseid' => $courseid, 'criterionid' => $criterionid, 'userid' => $userid,
        ]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
    }

    /**
     * Visit the current user's progress dashboard.
     *
     * @Given /^I visit my progress in course "([^"]*)"$/
     */
    public function i_visit_my_progress(string $shortname): void {
        global $DB;
        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $url = new moodle_url('/local/criteriaoutcomes/student_progress.php', ['id' => $courseid]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
    }

    /**
     * Open one criterion in the current user's progress view.
     *
     * @Given /^I open criterion "([^"]*)" in my progress for course "([^"]*)"$/
     */
    public function i_open_criterion_in_my_progress(string $code, string $shortname): void {
        global $DB;
        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $criterionid = $DB->get_field_sql(
            "SELECT c.id
               FROM {local_crout_criterion} c
               JOIN {local_crout_parent} p ON p.id = c.parentid
               JOIN {local_crout_framework} f ON f.id = p.frameworkid
              WHERE f.courseid = :courseid AND c.code = :code",
            ['courseid' => $courseid, 'code' => $code],
            MUST_EXIST
        );
        $url = new moodle_url('/local/criteriaoutcomes/criterion_progress.php', [
            'courseid' => $courseid, 'criterionid' => $criterionid,
        ]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
    }
    /**
     * Create native rubric for Behat.
     *
     * @Given /^I create the native rubric for "([^"]*)" in course "([^"]*)" with dimensions:$/
     */
    public function i_create_the_native_rubric_for_in_course_with_dimensions(string $assignname, string $courseshortname, \Behat\Gherkin\Node\TableNode $table): void {
        global $DB;
        $courseid = $DB->get_field('course', 'id', ['shortname' => $courseshortname], MUST_EXIST);
        $cm = $DB->get_record_sql(
            "SELECT cm.id FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module
              JOIN {assign} a ON a.id = cm.instance WHERE a.name = :name AND cm.course = :courseid",
            ['name' => $assignname, 'courseid' => $courseid],
            MUST_EXIST
        );
        $context = \context_module::instance($cm->id);
        $criteria = [];
        foreach ($table->getRows() as $row) {
            $dimension = trim($row[0]);
            $levels = [];
            for ($i = 1; $i < count($row); $i += 2) {
                $levelname = trim($row[$i] ?? '');
                $score = isset($row[$i + 1]) ? (int)trim($row[$i + 1]) : 0;
                if ($levelname !== '') {
                    $levels[$levelname] = $score;
                }
            }
            if ($dimension !== '' && !empty($levels)) {
                $criteria[$dimension] = $levels;
            }
        }
        if (empty($criteria)) {
            throw new \Exception('No rubric dimensions provided');
        }
        $rubricgenerator = \behat_util::get_data_generator()->get_plugin_generator('gradingform_rubric');
        $rubricgenerator->create_instance($context, 'mod_assign', 'submissions', 'Rubric for ' . $assignname, '', $criteria);
    }

    /**
     * Import curriculum for Behat.
     *
     * @Given /^I import curriculum "([^"]*)" with criteria "([^"]*)"$/
     */
    public function i_import_curriculum_with_criteria(string $courseshortname, string $codes): void {
        global $DB, $CFG;
        require_once($CFG->libdir . '/grade/grade_scale.php');
        $courseid = $DB->get_field('course', 'id', ['shortname' => $courseshortname], MUST_EXIST);
        $codelist = array_map('trim', explode(',', $codes));
        $codelist = array_filter($codelist);
        if (empty($codelist)) {
            throw new \Exception('No criteria codes provided');
        }
        $parent = ['codigo' => 'RA1', 'nombre' => 'Parent', 'criterios' => []];
        foreach ($codelist as $code) {
            $parent['criterios'][] = ['codigo' => $code, 'nombre' => 'Criterion ' . $code];
        }
        $scale = new \grade_scale();
        $scale->courseid = $courseid;
        $scale->userid = get_admin()->id;
        $scale->name = 'Rubric scale ' . $courseshortname . ' ' . time();
        $scale->scale = 'A,B,C';
        $scale->description = '';
        $scale->descriptionformat = FORMAT_HTML;
        $scale->insert('behat');
        $provider = new \local_criteriaoutcomes\provider\json_provider();
        $curriculum = $provider->parse(json_encode([
            'metadata' => ['name' => 'Behat curriculum ' . $courseshortname, 'type' => 'fp'],
            'resultados' => [[
                'codigo' => $parent['codigo'], 'nombre' => $parent['nombre'], 'criterios' => $parent['criterios'],
            ]],
        ]));
        $importer = new \local_criteriaoutcomes\service\import_service();
        $importer->import($courseid, $scale->id, $curriculum);
    }

    /**
     * Visit rubric mapping page.
     *
     * @Given /^I visit the rubric mapping page for "([^"]*)" in course "([^"]*)"$/
     */
    public function i_visit_the_rubric_mapping_page_for_in_course(string $assignname, string $courseshortname): void {
        global $DB;
        $courseid = $DB->get_field('course', 'id', ['shortname' => $courseshortname], MUST_EXIST);
        $cmid = $DB->get_field_sql(
            "SELECT cm.id FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module
              JOIN {assign} a ON a.id = cm.instance WHERE a.name = :name AND cm.course = :courseid",
            ['name' => $assignname, 'courseid' => $courseid],
            MUST_EXIST
        );
        $url = new moodle_url('/local/criteriaoutcomes/rubric_mapping.php', ['id' => $courseid, 'cmid' => $cmid]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
    }

    /**
     * Set rubric mapping for dimension.
     *
     * @When /^I set the rubric mapping for dimension "([^"]*)" to criteria "([^"]*)" with value "([^"]*)"$/
     */
    public function i_set_the_rubric_mapping_for_dimension_to_criteria_with_value(string $dimension, string $code, string $value): void {
        $page = $this->getSession()->getPage();
        $fieldsets = $page->findAll('css', 'fieldset');
        foreach ($fieldsets as $fs) {
            $legend = $fs->find('css', 'legend');
            if ($legend && strpos($legend->getText(), $dimension) !== false) {
                $labels = $fs->findAll('css', 'label');
                foreach ($labels as $label) {
                    if (strpos($label->getText(), $code) !== false) {
                        $for = $label->getAttribute('for');
                        $input = $page->find('css', '#' . $for);
                        if ($input) {
                            if ($value === '1' && !$input->isChecked()) {
                                $input->check();
                            } else if ($value === '0' && $input->isChecked()) {
                                $input->uncheck();
                            }
                            return;
                        }
                    }
                }
            }
        }
        throw new \Exception('Rubric mapping checkbox not found: ' . $dimension . ' ' . $code);
    }

    /**
     * Assert rubric mapping includes criterion.
     *
     * @Then /^the rubric mapping for dimension "([^"]*)" should include "([^"]*)"$/
     */
    public function the_rubric_mapping_for_dimension_should_include(string $dimension, string $code): void {
        $page = $this->getSession()->getPage();
        $found = false;
        $fieldsets = $page->findAll('css', 'fieldset');
        foreach ($fieldsets as $fs) {
            $legend = $fs->find('css', 'legend');
            if ($legend && strpos($legend->getText(), $dimension) !== false) {
                $labels = $fs->findAll('css', 'label');
                foreach ($labels as $label) {
                    if (strpos($label->getText(), $code) !== false) {
                        $for = $label->getAttribute('for');
                        $input = $page->find('css', '#' . $for);
                        if ($input && $input->isChecked()) {
                            $found = true;
                            break 2;
                        }
                    }
                }
            }
        }
        if (!$found) {
            throw new \Exception('Expected rubric mapping ' . $dimension // phpcs:ignore Generic.Files.LineLength.TooLong
                . ' to include ' . $code . ' but it was not checked');
        }
    }

    /**
     * Assert rubric mapping does not include criterion.
     *
     * @Then /^the rubric mapping for dimension "([^"]*)" should not include "([^"]*)"$/
     */
    public function the_rubric_mapping_for_dimension_should_not_include(string $dimension, string $code): void {
        $page = $this->getSession()->getPage();
        $fieldsets = $page->findAll('css', 'fieldset');
        foreach ($fieldsets as $fs) {
            $legend = $fs->find('css', 'legend');
            if ($legend && strpos($legend->getText(), $dimension) !== false) {
                $labels = $fs->findAll('css', 'label');
                foreach ($labels as $label) {
                    if (strpos($label->getText(), $code) !== false) {
                        $for = $label->getAttribute('for');
                        $input = $page->find('css', '#' . $for);
                        if ($input && $input->isChecked()) {
                            throw new \Exception('Expected rubric mapping ' . $dimension // phpcs:ignore Generic.Files.LineLength.TooLong
                                . ' to NOT include ' . $code . ' but it was checked');
                        }
                        return;
                    }
                }
            }
        }
    }
}
