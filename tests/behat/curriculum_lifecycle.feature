@local @local_criteriaoutcomes @javascript
Feature: Safely manage and undo imported curriculum criteria
  In order to preserve academic evidence while maintaining a course curriculum
  As an editing teacher
  I need destructive operations to be previewed and revalidated by the real UI

  Background:
    Given the following config values are set as admin:
      | enableoutcomes | 1 |
    And the following "courses" exist:
      | fullname         | shortname | category |
      | Lifecycle course | LIFE1     | 0        |
    And the following "users" exist:
      | username    | firstname | lastname | email                  |
      | lifeteacher | Life      | Teacher  | life@example.com       |
    And the following "course enrolments" exist:
      | user        | course | role           |
      | lifeteacher | LIFE1  | editingteacher |
    And the course "LIFE1" has the outcome scale "Criteria scale"
    And I log in as "lifeteacher"
    And I am on "Lifecycle course" course homepage
    And I visit the curriculum outcomes page for course "LIFE1"
    And I follow "Import from JSON"
    And I set the field "Or paste JSON" to "{\"metadata\":{\"name\":\"Lifecycle curriculum\",\"type\":\"fp\"},\"resultados\":[{\"codigo\":\"RA1\",\"nombre\":\"Lifecycle result\",\"criterios\":[{\"codigo\":\"RA1.a\",\"nombre\":\"Unused criterion\"},{\"codigo\":\"RA1.b\",\"nombre\":\"Used criterion\"}]}]}"
    And I set the field "Outcome scale" to "Criteria scale"
    And I press "Validate and preview"
    And I press "Confirm import"
    And criterion "RA1.b" in course "LIFE1" has an academic grade item

  Scenario: Bulk management deletes unused criteria and archives used criteria
    Given I visit curriculum management for course "LIFE1"
    And I select criterion "RA1.a" for the safe curriculum operation
    And I select criterion "RA1.b" for the safe curriculum operation
    When I press "Analyse impact"
    Then I should see "RA1.a"
    And I should see "SAFE_DELETE"
    And I should see "RA1.b"
    And I should see "ARCHIVE_ONLY"
    When I set the field "Archive used criteria instead of leaving them unchanged." to "1"
    And I press "Apply safe operation"
    Then I should see "Operation complete: 1 deleted, 1 archived, 0 unchanged."
    And I should not see "RA1.a"
    And I should not see "RA1.b"
    When I follow "Show archived"
    Then I should not see "RA1.a"
    And I should see "RA1.b"
    And I should see "Archived"
    And course "LIFE1" should have exactly "0" criterion "RA1.a" with status "active"
    And course "LIFE1" should have exactly "1" criterion "RA1.b" with status "archived"
    And the academic grade item for criterion "RA1.b" in course "LIFE1" should be preserved

  Scenario: Safe undo deletes unused created criteria and archives used created criteria
    Given I visit the latest successful import batch for course "LIFE1"
    When I press "Analyse safe undo"
    Then I should see "Safe undo preview"
    And I should see "DELETE"
    And I should see "ARCHIVE_ONLY"
    When I set the field "Archive used criteria instead of leaving them unchanged." to "1"
    And I press "Confirm safe undo"
    Then I should see "Undo complete: 1 deleted, 1 archived, 0 restored, 0 matched entities preserved, 0 conflicts preserved."
    And course "LIFE1" should have exactly "0" criterion "RA1.a" with status "active"
    And course "LIFE1" should have exactly "1" criterion "RA1.b" with status "archived"
    And the academic grade item for criterion "RA1.b" in course "LIFE1" should be preserved
