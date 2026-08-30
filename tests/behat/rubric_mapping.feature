@local @local_criteriaoutcomes @javascript
Feature: Map native rubric dimensions to curriculum criteria
  In order to trace rubric evidence to curriculum criteria
  As an editing teacher
  I need to map rubric dimensions to criteria via the plugin UI

  Background:
    Given the following config values are set as admin:
      | enableoutcomes | 1 |
    And the following "courses" exist:
      | fullname | shortname | category |
      | RUB1     | RUB1      | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email              |
      | rubteacher | Rub | Teacher | rubteacher@example.com |
      | rubstudent | Rub | Student | rubstudent@example.com |
    And the following "course enrolments" exist:
      | user       | course | role           |
      | rubteacher | RUB1   | editingteacher |
      | rubstudent | RUB1   | student        |
    And the following "activities" exist:
      | activity | name               | course | idnumber |
      | assign   | Rubric assignment  | RUB1   | assign1  |
    And the course "RUB1" has the outcome scale "Criteria scale"
    And I log in as "rubteacher"
    And I create the native rubric for "Rubric assignment" in course "RUB1" with dimensions:
      | Dimension A | Level 1 | 0 | Level 2 | 1 |
      | Dimension B | Level 1 | 0 | Level 2 | 1 |
    And I import curriculum "RUB1" with criteria "RA1.a, RA1.b, RA2.a"
    And I am on "RUB1" course homepage

  Scenario: Rubric mapping via plugin home is discoverable and persists N:N
    When I follow "Curriculum Outcomes"
    And I follow "Assessment and mappings"
    And I follow "Rubric criteria mapping"
    Then I should see "Activities with native rubrics"
    And I should see "Rubric assignment"
    When I follow "Rubric assignment"
    Then I should see "Dimension A"
    And I should see "Dimension B"
    And I should see "RA1.a"
    When I set the rubric mapping for dimension "Dimension A" to criteria "RA1.a" with value "1"
    And I set the rubric mapping for dimension "Dimension A" to criteria "RA1.b" with value "1"
    And I set the rubric mapping for dimension "Dimension B" to criteria "RA1.b" with value "1"
    And I press "Save mappings"
    Then I should see "Curriculum criteria mappings saved"
    When I reload the page
    Then the rubric mapping for dimension "Dimension A" should include "RA1.a"
    And the rubric mapping for dimension "Dimension A" should include "RA1.b"
    And the rubric mapping for dimension "Dimension B" should include "RA1.b"
    When I set the rubric mapping for dimension "Dimension A" to criteria "RA1.a" with value "0"
    And I set the rubric mapping for dimension "Dimension A" to criteria "RA2.a" with value "1"
    And I press "Save mappings"
    Then I should see "Curriculum criteria mappings saved"
    When I reload the page
    Then the rubric mapping for dimension "Dimension A" should not include "RA1.a"
    And the rubric mapping for dimension "Dimension A" should include "RA1.b"
    And the rubric mapping for dimension "Dimension A" should include "RA2.a"
    And the rubric mapping for dimension "Dimension B" should include "RA1.b"

  Scenario: Student cannot map rubric criteria
    Given I log in as "rubstudent"
    And I am on "RUB1" course homepage
    When I visit the rubric mapping page for "Rubric assignment" in course "RUB1"
    Then I should see "Sorry, but you do not currently have permissions"
