@local @local_classprogress
Feature: Set up a school year and a plan
  In order to know how many sessions each module has
  As a teacher
  I need to enter my school year, its holidays and the weekly timetable of each plan, and keep them private

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Anna      | Puig     | teacher1@example.com |
      | teacher2 | Bernat    | Soler    | teacher2@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | editingteacher |

  @REQ-HOL-001 @REQ-HOL-002 @REQ-HOL-003 @REQ-CAL-001 @REQ-CAL-002 @REQ-CAL-003
  Scenario: J2 A teacher sets up a school year with holidays and a plan with a timetable, and sees its sessions
    Given I am on the "local_classprogress > Home" page logged in as "teacher1"
    And I should see "You have no school years yet."
    When I click on "New school year" "link"
    And I set the following fields to these values:
      | Name             | Curs 2026-2027        |
      | First day        | ##14 September 2026## |
      | Last day         | ##21 June 2027##      |
      | evalname[0]      | 1a avaluació          |
      | evalstartdate[0] | ##14 September 2026## |
      | evalenddate[0]   | ##27 November 2026##  |
    And I set the field "Holidays and non-teaching days" to multiline:
      """
      # Calendar of the school
      2026-10-12 Festa Nacional
      2026-11-02 Lliure disposició
      2026-12-22 to 2027-1-07 Vacances de Nadal
      """
    And I press "Save changes"
    Then I should see "Some holiday lines cannot be read. Correct or remove them; nothing has been saved."
    And I should see "Line 4 (2026-12-22 to 2027-1-07 Vacances de Nadal): The end date of the period cannot be read."
    And I should not see "Line 2"
    And I set the field "Holidays and non-teaching days" to multiline:
      """
      # Calendar of the school
      2026-10-12 Festa Nacional
      2026-11-02 Lliure disposició
      2026-12-22 to 2027-01-07 Vacances de Nadal
      """
    And I press "Save changes"
    And I should see "School year saved."
    And I should see "Holidays: 3. Evaluation periods: 1."
    And I click on "New plan" "link"
    And I set the following fields to these values:
      | Module code      | MP 0372                  |
      | Module title     | Gestió de bases de dades |
      | First class day  | ##14 September 2026##    |
      | Last class day   | ##27 November 2026##     |
      | Monday           | 2                        |
      | Friday           | 2                        |
    And I press "Save changes"
    Then I should see "Plan saved."
    And I should see "20 sessions, 40 hours of class"
    And I should see "5" in the "September 2026" "table_row"
    And I should see "8" in the "October 2026" "table_row"
    And I should see "7" in the "November 2026" "table_row"
    And I should see "1" in the "Festa Nacional" "table_row"
    And I should see "1" in the "Lliure disposició" "table_row"
    And I should see "0" in the "Vacances de Nadal" "table_row"
    And I am on the "local_classprogress > Home" page
    And I should see "20" in the "MP 0372 · Gestió de bases de dades" "table_row"
    And I should see "40" in the "MP 0372 · Gestió de bases de dades" "table_row"

  @REQ-DAT-003 @REQ-DAT-004
  Scenario: Text with <, > and & is kept and shown as typed, and a save from a stale form is refused
    Given I am on the "local_classprogress > Home" page logged in as "teacher1"
    And I click on "New school year" "link"
    And I set the following fields to these values:
      | Name      | Curs <2026> & co      |
      | First day | ##14 September 2026## |
      | Last day  | ##21 June 2027##      |
    And I set the field "Holidays and non-teaching days" to multiline:
      """
      2026-12-07 Pont <- Immaculada
      2026-12-08 La Immaculada
      """
    And I press "Save changes"
    And I should see "Curs <2026> & co"
    And I should see "Holidays: 2. Evaluation periods: 0."
    And I click on "New plan" "link"
    And I set the following fields to these values:
      | Module code     | MP<03>                   |
      | Module title    | Programació <C++> i Java |
      | First class day | ##1 December 2026##      |
      | Last class day  | ##15 December 2026##     |
      | Monday          | 2                        |
      | Tuesday         | 2                        |
    And I press "Save changes"
    Then I should see "Plan saved."
    And I should see "MP<03> · Programació <C++> i Java"
    And I should see "3 sessions, 6 hours of class"
    And I should see "1" in the "Pont <- Immaculada" "table_row"
    And I am on the "Curs <2026> & co" "local_classprogress > School year" page
    And the school year "Curs <2026> & co" is changed elsewhere
    And I set the field "Name" to "Curs 2026-27"
    And I press "Save changes"
    And I should see "This was changed elsewhere; reload to see the latest version"
    And I am on the "local_classprogress > Home" page
    And I should see "Curs <2026> & co"
    And I should not see "Curs 2026-27"

  @REQ-ACC-002
  Scenario: Every page refuses a user without the capability, and a teacher reaches them from the top bar
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Carla     | Vidal    | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    When I log in as "student1"
    Then the page at "/local/classprogress/index.php" shows the error "Sorry, but you do not currently have permissions to do that (Use Class progress)."
    And the page at "/local/classprogress/schoolyear.php" shows the error "Sorry, but you do not currently have permissions to do that (Use Class progress)."
    And the page at "/local/classprogress/plan.php" shows the error "Sorry, but you do not currently have permissions to do that (Use Class progress)."
    And I log out
    And I log in as "teacher1"
    And I click on "Class progress" "link" in the ".primary-navigation" "css_element"
    And I should see "You have no school years yet."
    And I click on "New school year" "link"
    And I should see "Holidays and non-teaching days"

  @REQ-OWN-001 @REQ-OWN-002
  Scenario: J3 Another teacher cannot see or open a teacher's school year and plan
    Given I am on the "local_classprogress > Home" page logged in as "teacher1"
    And I click on "New school year" "link"
    And I set the following fields to these values:
      | Name      | Curs de l'Anna        |
      | First day | ##14 September 2026## |
      | Last day  | ##21 June 2027##      |
    And I press "Save changes"
    And I click on "New plan" "link"
    And I set the following fields to these values:
      | Module code  | MP 0372                  |
      | Module title | Gestió de bases de dades |
    And I press "Save changes"
    And I should see "Plan saved."
    And I log out
    When I am on the "local_classprogress > Home" page logged in as "teacher2"
    Then I should see "You have no school years yet."
    And I should not see "Curs de l'Anna"
    And I should not see "Gestió de bases de dades"
    And the "MP 0372" "local_classprogress > Plan" page shows the error "That item does not exist, or you do not have permission to see it."
    And the "Curs de l'Anna" "local_classprogress > School year" page shows the error "That item does not exist, or you do not have permission to see it."
    And the page at "/local/classprogress/plan.php?id=999999" shows the error "That item does not exist, or you do not have permission to see it."
    And the page at "/local/classprogress/schoolyear.php?id=999999&action=delete" shows the error "That item does not exist, or you do not have permission to see it."
