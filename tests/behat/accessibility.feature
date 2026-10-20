@local @local_classprogress @javascript @accessibility @REQ-REL-007
Feature: The plugin's pages meet the accessibility standards
  In order to keep my school years with a screen reader or the keyboard
  As a teacher
  I need every page of Class progress to pass Moodle's automated accessibility check

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Anna      | Puig     | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |

  Scenario: The top-menu page without data, and a new school year empty and with an error
    Given I am on the "local_classprogress > Home" page logged in as "teacher1"
    And I should see "You have no school years yet."
    Then the page should meet accessibility standards
    When I click on "New school year" "link"
    Then the page should meet accessibility standards
    When I set the field "Name" to "Curs 2026-2027"
    And I set the field "Holidays and non-teaching days" to "2026-13-45 Festa"
    And I press "Save changes"
    Then I should see "Some holiday lines cannot be read. Correct or remove them; nothing has been saved."
    And the page should meet accessibility standards

  Scenario: The top-menu page with a school year, its form with holidays and evaluation periods, and its deletion
    Given the following "local_classprogress > school years" exist:
      | user     | name           | startdate  | enddate    |
      | teacher1 | Curs 2026-2027 | 2026-09-14 | 2027-06-21 |
    And the following "local_classprogress > holidays" exist:
      | schoolyear     | startdate  | label          |
      | Curs 2026-2027 | 2026-10-12 | Festa Nacional |
    And the following "local_classprogress > evaluation periods" exist:
      | schoolyear     | name         | startdate  | enddate    |
      | Curs 2026-2027 | 1a avaluació | 2026-09-14 | 2026-11-27 |
    And I am on the "local_classprogress > Home" page logged in as "teacher1"
    And I should see "Holidays: 1. Evaluation periods: 1."
    Then the page should meet accessibility standards
    When I click on "Edit school year" "link"
    Then the page should meet accessibility standards
    When I set the field "Last day" to "##1 September 2026##"
    And I press "Save changes"
    Then I should see "The last day is before the first day."
    And the page should meet accessibility standards
    And I am on the "local_classprogress > Home" page
    When I click on "Delete school year" "link"
    Then I should see "Delete the school year Curs 2026-2027"
    And the page should meet accessibility standards
