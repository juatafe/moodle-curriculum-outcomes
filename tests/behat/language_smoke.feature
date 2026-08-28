@local @local_criteriaoutcomes @javascript
Feature: Plugin pages have complete language catalogs
  In order to use curriculum outcomes in the supported interface languages
  As an editing teacher
  I need the main plugin pages to render without missing strings

  Background:
    Given the following config values are set as admin:
      | enableoutcomes | 1 |
    And the following "courses" exist:
      | fullname        | shortname | category |
      | Language course | LANG1     | 0        |
    And the following "users" exist:
      | username    | firstname | lastname | email                |
      | langteacher | Lang      | Teacher  | lang@example.com     |
    And the following "course enrolments" exist:
      | user        | course | role           |
      | langteacher | LANG1  | editingteacher |
    And I log in as "langteacher"

  Scenario Outline: Core plugin pages render without missing strings
    When I visit the "main" plugin page for course "LANG1" in language "<language>"
    Then I should not see "[["
    When I visit the "boe" plugin page for course "LANG1" in language "<language>"
    Then I should not see "[["
    When I visit the "management" plugin page for course "LANG1" in language "<language>"
    Then I should not see "[["
    When I visit the "history" plugin page for course "LANG1" in language "<language>"
    Then I should not see "[["
    When I visit the "progress" plugin page for course "LANG1" in language "<language>"
    Then I should not see "[["

    Examples:
      | language |
      | en       |
      | es       |
      | ca       |
      | eu       |
      | gl       |
