@local @local_classprogress @REQ-ACC-001
Feature: The Class progress link in the primary navigation
  In order to reach my record of what was planned and what was done in class
  As a teacher
  I need a "Class progress" link in the top bar that only teachers see

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | teacher2 | Teacher   | Two      | teacher2@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | teacher        |
      | student1 | C1     | student        |

  Scenario Outline: A teacher sees the link
    When I am on the "My courses" page logged in as "<user>"
    Then "Class progress" "link" should exist in the ".primary-navigation" "css_element"

    Examples:
      | user     |
      | teacher1 |
      | teacher2 |

  Scenario: A student does not see the link
    When I am on the "My courses" page logged in as "student1"
    Then "Class progress" "link" should not exist in the ".primary-navigation" "css_element"
    # Proves the top bar was rendered, so the absence above is not a false pass. Checking for a named item
    # there is version-dependent: "My courses" (student) and "Home" (guest) were not found in it on 5.3 in CI.
    And ".primary-navigation" "css_element" should exist

  Scenario: A guest does not see the link
    When I log in as "guest"
    And I am on site homepage
    Then "Class progress" "link" should not exist in the ".primary-navigation" "css_element"
    And ".primary-navigation" "css_element" should exist
