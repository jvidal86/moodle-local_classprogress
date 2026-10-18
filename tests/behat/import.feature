@local @local_classprogress
Feature: Import, approve and lock a plan
  In order to show the inspector a plan that cannot shift afterwards
  As a teacher
  I need to import my plan text, check its preview, approve it, and find it locked

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
    And the following "local_classprogress > school years" exist:
      | user     | name           | startdate  | enddate    |
      | teacher1 | Curs 2026-2027 | 2026-09-14 | 2027-06-21 |
    And the following "local_classprogress > holidays" exist:
      | schoolyear     | startdate  | label          |
      | Curs 2026-2027 | 2026-10-12 | Festa Nacional |
    And the following "local_classprogress > plans" exist:
      | schoolyear     | modulecode | moduletitle |
      | Curs 2026-2027 | MP 0372    | Bases       |
    And the following "local_classprogress > slots" exist:
      | plan    | weekday | hours |
      | MP 0372 | Monday  | 2     |
      | MP 0372 | Friday  | 2     |

  @REQ-PLN-005 @REQ-IMP-002 @REQ-IMP-004
  Scenario: J4 A plan with a bad date shows the error with its line and offers no saving and no approval
    Given I am on the "MP 0372" "local_classprogress > Plan import" page logged in as "teacher1"
    When I set the field "Plan text" to multiline:
      """
      # MP 0372 · Gestió de bases de dades
      ## RA1 · Reconeix els elements
      ### 2026-09-14 [2h]
      - Presentació
      ### 2026-02-30 [2h]
      - Un dia que no existeix
      """
    And I press "Preview"
    Then I should see "Line 5: The day heading" in the "[data-region='preview-errors']" "css_element"
    And I should see "2026-02-30 [2h]" in the "[data-region='preview-errors']" "css_element"
    And I should see "has no date that can be read" in the "[data-region='preview-errors']" "css_element"
    And "Save draft" "button" should not exist
    And "Preview" "button" should exist
    And I am on the "MP 0372" "local_classprogress > Plan" page
    And I should see "This plan has no days yet."
    And "Approve plan" "link" should not exist

  @REQ-IMP-002 @REQ-IMP-003 @REQ-IMP-004 @REQ-LCK-001 @REQ-LCK-002
  Scenario: J5 A clean plan is previewed, saved as a draft, approved after its warnings are confirmed, and is then read-only
    Given I am on the "MP 0372" "local_classprogress > Plan import" page logged in as "teacher1"
    And I should see "Instructions for my AI assistant"
    When I set the field "Plan text" to multiline:
      """
      # MP 0372 · Gestió de bases de dades
      ## RA1 · Reconeix els elements
      ### 2026-09-14 [2h] Presentació
      - Normes de l'aula
      ### dv 18/09/2026
      - Entorn de treball
      ## RA2 · Escriu i prova codi
      ### 2026-10-12
      - Primer programa
      """
    And I press "Preview"
    Then I should see "MP 0372 · Gestió de bases de dades"
    And I should see "The module heading of the text replaces the module typed for this plan (MP 0372 · Bases)."
    And I should see "3" in the "[data-region='preview-days']" "css_element"
    And I should see "6" in the "[data-region='preview-hours']" "css_element"
    And I should see "Line 8: The day 2026-10-12 is a holiday: Festa Nacional." in the "[data-region='preview-warnings']" "css_element"
    When I press "Save draft"
    Then I should see "Draft saved."
    And I should see "Draft: the plan can still be changed"
    And I should see "Normes de l'aula"
    And I should see "RA2 · Escriu i prova codi"
    When I click on "Approve plan" "link"
    Then I should see "Approval is final."
    And I should see "Line 8: The day 2026-10-12 is a holiday: Festa Nacional."
    When I press "Approve plan"
    Then I should see "Confirm that you have read the warnings."
    When I set the field "I have read the warnings" to "1"
    And I press "Approve plan"
    Then I should see "Plan approved."
    And I should see "Approved on"
    And I should see "Normes de l'aula"
    And "Import plan text" "link" should not exist
    And "Approve plan" "link" should not exist
    And "Delete plan" "link" should not exist
    And "Save changes" "button" should not exist
    And the "MP 0372" "local_classprogress > Plan import" page shows the error "This plan is approved and can no longer be changed."
    And the "MP 0372" "local_classprogress > Plan deletion" page shows the error "This plan is approved and can no longer be changed."
    And I am on the "local_classprogress > Home" page
    And I should see "Approved" in the "MP 0372 · Gestió de bases de dades" "table_row"
    And "Delete" "link" should not exist in the "MP 0372 · Gestió de bases de dades" "table_row"
