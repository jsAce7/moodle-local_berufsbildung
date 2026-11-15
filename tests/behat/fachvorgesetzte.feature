@local @local_berufsbildung @javascript
Feature: Fachvorgesetzte einer Abteilung pflegen
  Als Ausbildungsplanung
  will ich bei einem betrieblichen Block die Fachvorgesetzten eintragen
  damit sie die Arbeitsplatzberichte dieser Abteilung schreiben koennen

  Background:
    Given the following "users" exist:
      | username | firstname | lastname     | email               |
      | planer   | Paula     | Planerin     | planer@example.com  |
      | fv       | Fritz     | Vorgesetzter | fritz@example.com   |
    And the following "role assigns" exist:
      | user   | role                  | contextlevel | reference |
      | planer | berufsbildung_planung | System       |           |
    And the following "local_berufsbildung > blocks" exist:
      | nummer | name        | beruf  | ist_betrieb |
      | B1     | Buchhaltung | AU_EFZ | 1           |

  Scenario: Die Ausbildungsplanung findet die Person ohne Recht auf alle Benutzerdetails und traegt sie ein
    Given I log in as "planer"
    And I visit "/local/berufsbildung/bloecke.php"
    And I should see "none – no workplace report" in the "B1" "table_row"
    When I click on "Edit" "link" in the "B1" "table_row"
    And I set the field "Department supervisors" to "Fritz Vorgesetzter"
    And I press "Save"
    Then I should see "Fritz Vorgesetzter" in the "B1" "table_row"
    When I click on "Edit" "link" in the "B1" "table_row"
    Then I should see "Fritz Vorgesetzter (fritz@example.com)"
