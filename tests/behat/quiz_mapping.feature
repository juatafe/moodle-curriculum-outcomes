@local @local_criteriaoutcomes @javascript
Feature: Map quiz slots to curriculum criteria
  In order to explain criterion evidence from quiz attempts
  As an editing teacher
  I need quiz-slot mappings and weights to persist

  Background:
    Given the following config values are set as admin:
      | enableoutcomes | 1 |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name   | course | idnumber |
      | quiz     | Quiz 1 | C1     | quiz1    |
    And the following "question categories" exist:
      | contextlevel    | reference | name           |
      | Activity module | quiz1     | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name       | questiontext |
      | Test questions   | truefalse | Question 1 | First text   |
      | Test questions   | truefalse | Question 2 | Second text  |
    And quiz "Quiz 1" contains the following questions:
      | question   | page |
      | Question 1 | 1    |
      | Question 2 | 1    |
    And the course "C1" has the outcome scale "Criteria scale"
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I visit the curriculum outcomes page for course "C1"
    And I follow "Import from JSON"
    And I set the field "Or paste JSON" to "{\"metadata\":{\"name\":\"Quiz curriculum\",\"type\":\"fp\"},\"resultados\":[{\"codigo\":\"RA1\",\"nombre\":\"Result one\",\"criterios\":[{\"codigo\":\"RA1.a\",\"nombre\":\"Criterion A\"}]}]}"
    And I set the field "Use an existing Moodle scale (advanced)" to "1"
    And I set the field "Outcome scale" to "Criteria scale"
    And I press "Validate and preview"
    And I press "Confirm import"

  Scenario: Mapping and weighted aggregation persist after saving
    When I visit the quiz criteria page for course "C1"
    And I should see "Quiz 1"
    And I visit criterion mappings for quiz "Quiz 1" in course "C1"
    Then the field matching "weight_" should not be visible
    And I map slot "1" of quiz "Quiz 1" to criterion "RA1.a" with weight "1"
    And the field matching "weight_" should be visible
    And I map slot "2" of quiz "Quiz 1" to criterion "RA1.a" with weight "2"
    And I should not see "Combine mapped questions"
    And I press "Save mappings"
    Then I should see "Quiz criterion mappings saved"
    And I should see "Combine mapped questions"
    And I should see "Question contribution weight"
    And I set criterion "RA1.a" aggregation to "weightedmean"
    And I press "Save mappings"
    Then I should see "Quiz criterion mappings saved"
    And the field matching "map_" should be checked
    And the field matching "aggregation_" matches value "weightedmean"

  Scenario: One mapped question does not show an unnecessary aggregation choice
    When I visit criterion mappings for quiz "Quiz 1" in course "C1"
    And I map slot "1" of quiz "Quiz 1" to criterion "RA1.a" with weight "1"
    And I press "Save mappings"
    Then I should see "Quiz criterion mappings saved"
    And I should not see "Combine mapped questions"
