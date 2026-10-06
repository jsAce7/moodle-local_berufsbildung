@local @local_berufsbildung
Feature: Kompetenzraster auf Meine Lehre
  Als Lernende/r
  will ich sehen, welche Handlungskompetenzen in meinen Einsaetzen vorkommen
  damit ich weiss, wo ich in der Ausbildung stehe

  Background:
    Given the following config values are set as admin:
      | beruf_rahmen_mapping | AU_EFZ=au-2022 | local_berufsbildung |
      | beruf_wahlpflicht_hk | AU_EFZ=a.03    | local_berufsbildung |
    And the following "core_competency > frameworks" exist:
      | shortname    | idnumber |
      | Automatik/in | au-2022  |
    And the following "local_berufsbildung > competencies" exist:
      | shortname                       | idnumber    | framework | parent      |
      | Entwickeln von Anlagen          | 7777BE a    | au-2022   |             |
      | Fertigungsunterlagen erstellen  | 7777BE a.01 | au-2022   | 7777BE a    |
      | AU a1 01                        | lk-a1-01    | au-2022   | 7777BE a.01 |
      | Skizzen erstellen               | 7777BE a.02 | au-2022   | 7777BE a    |
      | AU a2 01                        | lk-a2-01    | au-2022   | 7777BE a.02 |
      | Antriebe dimensionieren         | 7777BE a.03 | au-2022   | 7777BE a    |
    And the following "local_berufsbildung > blocks" exist:
      | nummer | name         | beruf  |
      | B1     | Werkstatt    | AU_EFZ |
      | B2     | Konstruktion | AU_EFZ |
    And the following "local_berufsbildung > learners" exist:
      | username | firstname | lastname | beruf  | jahrgang        |
      | lea      | Lea       | Lernende | AU_EFZ | ##-1 year##%Y## |
    And the following "local_berufsbildung > placements" exist:
      | user | block | von          | bis          |
      | lea  | B1    | ##-60 days## | ##-30 days## |
      | lea  | B2    | ##+30 days## | ##+60 days## |

  Scenario: Ohne Kompetenzzuordnung der Bloecke erklaert das Raster, warum nichts im Plan steht
    When I log in as "lea"
    And I visit "/local/berufsbildung/meine_lehre.php"
    Then I should see "No competency area occurs in the rotation plan on file yet."
    And I should not see "required competency areas:"
    And I should not see "Assign competencies to the training blocks"

  Scenario: Die Zusammenfassung trennt bereits Vorgekommenes von spaeter Eingeplantem
    Given the following "local_berufsbildung > block competencies" exist:
      | block | competency  |
      | B1    | lk-a1-01    |
      | B2    | 7777BE a.02 |
    When I log in as "lea"
    And I visit "/local/berufsbildung/meine_lehre.php"
    Then I should see "2 required competency areas: 1 already encountered, 1 scheduled later, 0 not in the plan"
    And I should see "1 of 2 required already encountered"
    And I should see "1 of 1 criteria encountered"
    And I should see "0 of 1 criteria encountered, 1 scheduled"
    And I should not see "No competency area occurs in the rotation plan on file yet."

  @javascript
  Scenario: Die Leistungskriterien einer Zelle oeffnen sich in einem Dialog
    Given the following "local_berufsbildung > block competencies" exist:
      | block | competency |
      | B1    | lk-a1-01   |
    When I log in as "lea"
    And I visit "/local/berufsbildung/meine_lehre.php"
    And I click on "1 of 1 criteria encountered" "text"
    Then "a.01 Fertigungsunterlagen erstellen" "dialogue" should be visible
    And I should see "Already encountered (1)" in the "a.01 Fertigungsunterlagen erstellen" "dialogue"
    And I should see "AU a1 01" in the "a.01 Fertigungsunterlagen erstellen" "dialogue"
