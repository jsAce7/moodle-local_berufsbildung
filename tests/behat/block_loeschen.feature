@local @local_berufsbildung
Feature: Ausbildungsbloecke loeschen
  Als Administrator/in
  will ich einen falsch, doppelt oder nur vorlaeufig angelegten Ausbildungsblock loeschen
  damit die Blockliste nur enthaelt, was tatsaechlich ausgebildet wird

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | lernende | Lea       | Lernende |
    And the following "local_berufsbildung > blocks" exist:
      | nummer | name           | beruf  |
      | B1     | Instandhaltung | AU_EFZ |
      | B2     | Montage        | AU_EFZ |
      | B3     | Robotik        | AU_EFZ |
    And the following "local_berufsbildung > placements" exist:
      | user     | block | von                     | bis                             |
      | lernende | B2    | ##1 February 2026##     | ##28 February 2026##            |
      | lernende | B3    | ##+1 year##             | ##+1 year +4 weeks##            |

  Scenario: Ein Block ohne Einsaetze wird nach Rueckfrage geloescht
    Given I log in as "admin"
    And I visit "/local/berufsbildung/bloecke.php"
    When I click on "Delete" "link" in the "B1" "table_row"
    Then I should see "Permanently delete block B1 \"Instandhaltung\"?"
    And I should not see "planned placements"
    And I press "Delete"
    And I should see "Block B1 deleted, along with 0 competency assignments and 0 planned placements."
    And I should not see "Instandhaltung"
    And I should see "Montage"

  Scenario: Ein Block mit nur geplanten Einsaetzen wird samt Einsaetzen geloescht
    Given I log in as "admin"
    And I visit "/local/berufsbildung/bloecke.php"
    When I click on "Delete" "link" in the "B3" "table_row"
    Then I should see "So are the 1 planned placements of 1 learners in this block."
    And I press "Delete"
    And I should see "Block B3 deleted, along with 0 competency assignments and 1 planned placements."
    And I should not see "Robotik"

  Scenario: Abbrechen laesst den Block stehen
    Given I log in as "admin"
    And I visit "/local/berufsbildung/bloecke.php"
    When I click on "Delete" "link" in the "B1" "table_row"
    And I press "Cancel"
    Then I should see "Instandhaltung"

  Scenario: Ein Block mit begonnenem Einsatz laesst sich nicht loeschen
    Given I log in as "admin"
    When I visit "/local/berufsbildung/bloecke.php"
    Then "Delete" "link" should not exist in the "B2" "table_row"
    And "Delete" "link" should exist in the "B1" "table_row"
