@local @local_berufsbildung
Feature: Zuordnung anlegen und beenden
  Als Administrator/in
  will ich eine Zuordnung zwischen Berufsbildner/in und Lernender anlegen und wieder beenden koennen
  damit die Zustaendigkeit jederzeit nachvollziehbar bleibt

  Background:
    Given the following "users" exist:
      | username | firstname | lastname      |
      | test_bb  | Beat      | Berufsbildner |
      | test_ler | Anna      | Muster        |

  @javascript
  Scenario: Eine neue Zuordnung anlegen und in der Uebersicht sehen
    Given I log in as "admin"
    And I visit "/local/berufsbildung/zuordnung.php"
    And I click on "New assignment" "button"
    And I click on "Trainer" "field"
    And I type "Beat"
    And I click on "Beat Berufsbildner" item in the autocomplete list
    And I press the escape key
    And I click on "Trainees" "field"
    And I type "Anna"
    And I click on "Anna Muster" item in the autocomplete list
    And I press the escape key
    When I click on "Create assignment" "button"
    Then I should see "1 assignment(s) created."
    And I should see "Beat Berufsbildner"
    And I should see "Anna Muster"
    And I should see "active"

  @javascript
  Scenario: Eine laufende Zuordnung beenden
    Given I log in as "admin"
    And I visit "/local/berufsbildung/zuordnung.php"
    And I click on "New assignment" "button"
    And I click on "Trainer" "field"
    And I type "Beat"
    And I click on "Beat Berufsbildner" item in the autocomplete list
    And I press the escape key
    And I click on "Trainees" "field"
    And I type "Anna"
    And I click on "Anna Muster" item in the autocomplete list
    And I press the escape key
    And I click on "Create assignment" "button"
    And I should see "active"
    When I choose the "End assignment" item in the "Actions" action menu of the "Anna Muster" "table_row"
    And I set the field "gueltig_bis[enabled]" to "1"
    And I click on "id_submitbutton" "button"
    Then I should see "Assignment ended."
    And I should see "ended"
