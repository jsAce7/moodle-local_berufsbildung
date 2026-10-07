@local @local_berufsbildung
Feature: Kompetenzen einem Ausbildungsblock zuordnen
  Als Administrator/in
  will ich die Kompetenzen eines Ausbildungsblocks aus dem Rahmen des Berufs auswaehlen
  damit die Lueckenanalyse weiss, was in diesem Block ausgebildet wird

  Background:
    Given the following config values are set as admin:
      | beruf_rahmen_mapping | AU_EFZ=au-2022 | local_berufsbildung |
    And the following "core_competency > frameworks" exist:
      | shortname    | idnumber |
      | Automatik/in | au-2022  |
    And the following "local_berufsbildung > competencies" exist:
      | shortname                          | idnumber     | framework | parent       | description                                   |
      | Instandhalten von Anlagen          | 7777BE c     | au-2022   |              |                                               |
      | Anlagen warten                     | 7777BE c.01  | au-2022   | 7777BE c     |                                               |
      | AU c1 01                           | lk-c1-01     | au-2022   | 7777BE c.01  | Sie planen die Wartung nach Herstellervorgabe. |
      | AU c1 02                           | lk-c1-02     | au-2022   | 7777BE c.01  | Sie tauschen Verschleissteile aus.            |
      | Stoerungen beheben                 | 7777BE c.02  | au-2022   | 7777BE c     |                                               |
      | AU c2 01                           | lk-c2-01     | au-2022   | 7777BE c.02  | Sie grenzen Stoerungen systematisch ein.      |
      | AU c2 02                           | lk-c2-02     | au-2022   | 7777BE c.02  | Sie dokumentieren die Stoerungsursache.       |
    And the following "local_berufsbildung > blocks" exist:
      | nummer | name           | beruf  |
      | B1     | Instandhaltung | AU_EFZ |

  @javascript
  Scenario: Eine eingefuegte Liste von LK-Codes filtert die Auswahl und laesst sich zuordnen
    Given I log in as "admin"
    And I visit "/local/berufsbildung/bloecke.php"
    And I click on "Assign competencies" "link" in the "B1" "table_row"
    When I set the field "suche" to "AU c1 02, AU c2 01, AU z9 99"
    Then I should see "AU c1 02"
    And I should see "AU c2 01"
    And I should not see "AU c1 01"
    And I should not see "AU c2 02"
    And I should see "Not found: AU z9 99"
    And I click on "AU c1 02" "text"
    And I click on "AU c2 01" "text"
    And I press "Save selection"
    And I should see "Competency coverage saved (2 newly assigned, 0 removed)."
    And I should see "Assigned competencies (2)"
    And "AU c1 02" "text" should exist in the "generaltable" "table"
    And "AU c2 01" "text" should exist in the "generaltable" "table"

  Scenario: Zugeordnete Handlungskompetenzen decken ihre Leistungskriterien ab
    Given the following "local_berufsbildung > block competencies" exist:
      | block | competency  |
      | B1    | 7777BE c.02 |
      | B1    | lk-c1-01    |
    And I log in as "admin"
    When I visit "/local/berufsbildung/bloecke.php"
    And I click on "Assign competencies" "link" in the "B1" "table_row"
    Then I should see "Assigned competencies (2)"
    And I should see "1 of 2 criteria"
    And I should see "covered by c.02"
    And I set the field "suche" to "AU c1 02, AU z9 99"
    And I click on "Search" "button" in the ".local-berufsbildung-auswahl-suche" "css_element"
    And I should see "Not found: AU z9 99"
    And I should see "AU c1 02"
    And I should not see "AU c2 01"

  Scenario: Eine zugeordnete Handlungskompetenz abwaehlen
    Given the following "local_berufsbildung > block competencies" exist:
      | block | competency  |
      | B1    | 7777BE c.02 |
      | B1    | lk-c1-01    |
    And I log in as "admin"
    When I visit "/local/berufsbildung/bloecke.php"
    And I click on "Assign competencies" "link" in the "B1" "table_row"
    And I set the field "Stoerungen beheben" to "0"
    And I press "Save selection"
    Then I should see "Competency coverage saved (0 newly assigned, 1 removed)."
    And I should see "Assigned competencies (1)"
    And I should not see "covered by c.02"

  Scenario: Ein Suchfilter entfernt keine ausgeblendeten Zuordnungen
    Given the following "local_berufsbildung > block competencies" exist:
      | block | competency  |
      | B1    | 7777BE c.02 |
    And I log in as "admin"
    When I visit "/local/berufsbildung/bloecke.php"
    And I click on "Assign competencies" "link" in the "B1" "table_row"
    And I set the field "suche" to "AU c1 02"
    And I click on "Search" "button" in the ".local-berufsbildung-auswahl-suche" "css_element"
    And I set the field "AU c1 02" to "1"
    And I press "Save selection"
    Then I should see "Competency coverage saved (1 newly assigned, 0 removed)."
    And I should see "Assigned competencies (2)"

  Scenario: Einen Block samt Kompetenzzuordnung kopieren
    Given the following "local_berufsbildung > block competencies" exist:
      | block | competency  |
      | B1    | 7777BE c.02 |
      | B1    | lk-c1-01    |
    And I log in as "admin"
    When I visit "/local/berufsbildung/bloecke.php"
    And I click on "Copy" "link" in the "B1" "table_row"
    Then the field "Name" matches value "Instandhaltung"
    And I set the field "Number" to "B7"
    And I press "Save"
    And I should see "Block copied. 2 competency assignments carried over."
    And I click on "Assign competencies" "link" in the "B7" "table_row"
    And I should see "Assigned competencies (2)"
