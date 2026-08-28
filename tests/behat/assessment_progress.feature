@local @local_criteriaoutcomes @javascript
Feature: Teacher assessment and student progress visibility
  In order to give criterion feedback safely
  As a teacher and student
  Draft feedback must remain private and released feedback must be traceable

  Background:
    Given the following config values are set as admin:
      | enableoutcomes | 1 |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the course "C1" has the outcome scale "Criteria scale"
    And I log in as "teacher1"
    And I visit the curriculum outcomes page for course "C1"
    And I follow "Import from JSON"
    And I set the field "Or paste JSON" to "{\"metadata\":{\"name\":\"Progress curriculum\",\"type\":\"fp\"},\"resultados\":[{\"codigo\":\"RA1\",\"nombre\":\"Result one\",\"peso\":50,\"criterios\":[{\"codigo\":\"RA1.a\",\"nombre\":\"Criterion A\",\"peso\":20}]}]}"
    And I set the field "Outcome scale" to "Criteria scale"
    And I press "Validate and preview"
    And I press "Confirm import"

  Scenario: Drafts are private and released feedback becomes read through drill-down
    When I assess criterion "RA1.a" for user "student1" in course "C1"
    And I set the field "Assessment mode" to "Feedback only"
    And I set the field "Feedback" to "Revisa la configuración DNS."
    And I press "Save assessment"
    And I log out
    And I log in as "student1"
    And I visit my progress in course "C1"
    Then I should not see "Revisa la configuración DNS."
    And I should see "0 evidence; 0 feedback; 0 unread"
    When I log out
    And I log in as "teacher1"
    And I assess criterion "RA1.a" for user "student1" in course "C1"
    And I press "Release"
    And I log out
    And I log in as "student1"
    And I visit my progress in course "C1"
    Then I should see "RA1"
    And I should see "RA1.a"
    And I should see "weight 20"
    And I should see "1 evidence; 1 feedback; 1 unread"
    When I open criterion "RA1.a" in my progress for course "C1"
    Then I should see "Revisa la configuración DNS."
    When I visit my progress in course "C1"
    Then I should see "1 evidence; 1 feedback; 0 unread"
