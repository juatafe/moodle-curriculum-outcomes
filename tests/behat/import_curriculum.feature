@local @local_criteriaoutcomes
Feature: Import curriculum criteria as native Outcomes
  In order to assess curriculum criteria with Moodle activities
  As an editing teacher
  I need to preview and confirm a curriculum import

  Background:
    Given the following config values are set as admin:
      | enableoutcomes | 1 |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | teacher2 | Teacher   | Two      | teacher2@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | teacher        |
      | student1 | C1     | student        |
    And the course "C1" has the outcome scale "Criteria scale"

  @javascript
  Scenario: An editing teacher previews and imports a criterion
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    When I visit the curriculum outcomes page for course "C1"
    And I follow "Import from JSON"
    And I set the field "Or paste JSON" to "{\"metadata\":{\"name\":\"Behat curriculum\",\"type\":\"fp\"},\"resultados\":[{\"codigo\":\"RA1\",\"nombre\":\"Result one\",\"criterios\":[{\"codigo\":\"RA1.a\",\"nombre\":\"Observable criterion\"}]}]}"
    And I set the field "Use an existing Moodle scale (advanced)" to "1"
    And I set the field "Outcome scale" to "Criteria scale"
    And I press "Validate and preview"
    Then I should see "Import preview"
    And I should see "RA1.a"
    And I should see "NEW"
    When I press "Confirm import"
    Then I should see "Import complete: 1 new, 0 unchanged"
    And I should see "Criteria and evidence"
    And I should see "Observable criterion"

  @javascript
  Scenario: Scale selection is explicit and a recommended scale is created transparently
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    When I visit the curriculum outcomes page for course "C1"
    And I follow "Import from JSON"
    Then the field "Achievement — 5 levels" matches value ""
    And the field "Numeric — 0 to 10" matches value ""
    And the field "Use an existing Moodle scale (advanced)" matches value ""
    And I should not see "Available template"
    And I should not see "Create and select"
    When I set the field "Or paste JSON" to "{\"metadata\":{\"name\":\"Recommended scale\",\"type\":\"fp\"},\"resultados\":[{\"codigo\":\"RA1\",\"nombre\":\"Result one\",\"criterios\":[{\"codigo\":\"RA1.a\",\"nombre\":\"Criterion\"}]}]}"
    And I set the field "Achievement — 5 levels" to "1"
    And I press "Validate and preview"
    Then I should see "Import preview"

  Scenario: An administrator can open the curriculum page
    Given I log in as "admin"
    When I visit the curriculum outcomes page for course "C1"
    Then I should see "Curriculum outcomes"
    And I should see "Import from JSON"
