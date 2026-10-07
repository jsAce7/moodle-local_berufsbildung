@local @local_berufsbildung
Feature: Leistungskriterien je Block
  Als Administrator/in
  will ich die Bloecke nach Beruf sehen und wissen, welches Leistungskriterium in welchem Block vorkommt
  damit ich erkenne, was noch keinem Block zugeordnet ist

  Background:
    Given the following config values are set as admin:
      | beruf_rahmen_mapping | AU_EFZ=au-2022 | local_berufsbildung |
    And the following "core_competency > frameworks" exist:
      | shortname    | idnumber |
      | Automatik/in | au-2022  |
    And the following "local_berufsbildung > competencies" exist:
      | shortname                 | idnumber    | framework | parent      |
      | c Instandhalten           | 7777BE c    | au-2022   |             |
      | Anlagen warten            | 7777BE c.01 | au-2022   | 7777BE c    |
      | AU c1 01                  | lk-c1-01    | au-2022   | 7777BE c.01 |
      | AU c1 02                  | lk-c1-02    | au-2022   | 7777BE c.01 |
      | Stoerungen beheben        | 7777BE c.02 | au-2022   | 7777BE c    |
      | AU c2 01                  | lk-c2-01    | au-2022   | 7777BE c.02 |
    And the following "local_berufsbildung > blocks" exist:
      | nummer | name           | beruf  | ist_betrieb |
      | B2     | Instandhaltung | AU_EFZ | 1           |
      | B10    | Montage        | AU_EFZ | 1           |
      | P1     | Polymechanik   | PM_EFZ | 1           |
      | S1     | Berufsschule   |        | 0           |
    And the following "local_berufsbildung > block competencies" exist:
      | block | competency  |
      | B2    | 7777BE c.01 |
      | B10   | lk-c1-01    |

  Scenario: Die Bloecke stehen nach Beruf gruppiert, mit der Zahl der offenen LK
    Given I log in as "admin"
    When I visit "/local/berufsbildung/bloecke.php"
    Then "AU_EFZ" "heading" should appear before "PM_EFZ" "heading"
    And "PM_EFZ" "heading" should appear before "Cross-occupation" "heading"
    And "B2" "table_row" should appear before "B10" "table_row"
    And I should see "1 criteria in no block"
    And I should see "Check the setup"
    And I should see "No competency framework assigned"

  Scenario: Die LK-Uebersicht zeigt je LK die Bloecke und filtert die offenen
    Given I log in as "admin"
    And I visit "/local/berufsbildung/bloecke.php"
    When I click on "Criteria overview" "link"
    Then I should see "2 of 3 performance criteria are assigned to a block, 1 to none yet."
    And I should see "B2 Instandhaltung (whole competency)" in the "AU c1 01" "table_row"
    And I should see "B10 Montage" in the "AU c1 01" "table_row"
    And I should see "in no block" in the "AU c2 01" "table_row"
    And I click on "Unassigned only" "link"
    And I should see "AU c2 01"
    And I should not see "AU c1 01"
