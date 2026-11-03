@local @local_berufsbildung
Feature: Zurueckgehaltene Versetzungsplan-Lieferung freigeben oder verwerfen
  Als Administrator/in
  will ich eine Lieferung mit deutlich weniger Personen pruefen und dann freigeben oder verwerfen
  damit eine unvollstaendige Lieferung den bestehenden Plan nicht ueberschreibt

  Background:
    Given the following "users" exist:
      | username | firstname | lastname      |
      | test_bb  | Beat      | Berufsbildner |
    And the following "local_berufsbildung > learners" exist:
      | username | firstname | lastname |
      | ler1     | Anna      | Eins     |
      | ler2     | Ben       | Zwei     |
      | ler3     | Carla     | Drei     |
      | ler4     | Dario     | Vier     |
      | ler5     | Eva       | Fuenf    |
    And the following "local_berufsbildung > assignments" exist:
      | trainer | learner |
      | test_bb | ler1    |
      | test_bb | ler2    |
      | test_bb | ler3    |
      | test_bb | ler4    |
      | test_bb | ler5    |
    # Die zweite Lieferung enthaelt nur noch eine von fuenf Personen und
    # wird deshalb zurueckgehalten.
    And the following "local_berufsbildung > rotation plan deliveries" exist:
      | learners                 | block | kw_von   | kw_bis   |
      | ler1,ler2,ler3,ler4,ler5 | 4     | 2027-W15 | 2027-W16 |
      | ler1                     | 4     | 2027-W20 | 2027-W21 |

  Scenario: Die zurueckgehaltene Lieferung zeigt, was sich aendern wuerde
    Given I log in as "admin"
    When I visit "/local/berufsbildung/plan_freigabe.php"
    Then I should see "Changes for delivered people (1)"
    And I should see "Anna Eins"
    And I should see "2027-W20 – 2027-W21"
    And I should see "No longer in the delivery (4)"
    And I should see "Ben Zwei"
    And I should see "Eva Fuenf"

  Scenario: Die Lieferung freigeben
    Given I log in as "admin"
    And I visit "/local/berufsbildung/plan_freigabe.php"
    When I press "Approve"
    And I press "Continue"
    Then I should see "The delivery was applied, 1 placements created."
    And I should see "No rotation-plan delivery is awaiting approval."

  Scenario: Die Lieferung verwerfen
    Given I log in as "admin"
    And I visit "/local/berufsbildung/plan_freigabe.php"
    When I press "Discard"
    And I press "Continue"
    Then I should see "The delivery was discarded."
    And I should see "No rotation-plan delivery is awaiting approval."

  Scenario: Eine inzwischen ueberholte Lieferung laesst sich nicht mehr freigeben
    Given I log in as "admin"
    And I visit "/local/berufsbildung/plan_freigabe.php"
    And I press "Approve"
    And the following "local_berufsbildung > rotation plan deliveries" exist:
      | learners                 | block | kw_von   | kw_bis   |
      | ler1,ler2,ler3,ler4,ler5 | 4     | 2027-W30 | 2027-W31 |
    When I press "Continue"
    Then I should see "This delivery is no longer awaiting approval."
    And I should see "No rotation-plan delivery is awaiting approval."
