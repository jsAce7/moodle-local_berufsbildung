@local @local_berufsbildung
Feature: Meine Lernenden
  Als Berufsbildner/in
  will ich je betreute Person den Stand ihrer Handlungskompetenzen sehen
  damit ich erkenne, was in der Ausbildungsplanung noch fehlt

  Background:
    Given the following config values are set as admin:
      | beruf_rahmen_mapping     | AU_EFZ=au-2022 | local_berufsbildung |
      | beruf_wahlpflicht_hk     | AU_EFZ=a.03    | local_berufsbildung |
      | beruf_wahlpflicht_anzahl | AU_EFZ=a:1     | local_berufsbildung |
    And the following "core_competency > frameworks" exist:
      | shortname    | idnumber |
      | Automatik/in | au-2022  |
    And the following "local_berufsbildung > competencies" exist:
      | shortname                      | idnumber    | framework | parent      |
      | Entwickeln von Anlagen         | 7777BE a    | au-2022   |             |
      | Fertigungsunterlagen erstellen | 7777BE a.01 | au-2022   | 7777BE a    |
      | AU a1 01                       | lk-a1-01    | au-2022   | 7777BE a.01 |
      | Skizzen erstellen              | 7777BE a.02 | au-2022   | 7777BE a    |
      | AU a2 01                       | lk-a2-01    | au-2022   | 7777BE a.02 |
      | Antriebe dimensionieren        | 7777BE a.03 | au-2022   | 7777BE a    |
      | AU a3 01                       | lk-a3-01    | au-2022   | 7777BE a.03 |
    And the following "local_berufsbildung > blocks" exist:
      | nummer | name      | beruf  |
      | B1     | Werkstatt | AU_EFZ |
      | B4     | Montage   | AU_EFZ |
    And the following "local_berufsbildung > block competencies" exist:
      | block | competency  |
      | B1    | lk-a1-01    |
      | B4    | 7777BE a.02 |
    And the following "users" exist:
      | username | firstname | lastname    |
      | bea      | Bea       | Bildnerin   |
    And the following "local_berufsbildung > learners" exist:
      | username | firstname | lastname | beruf  | jahrgang        |
      | lea      | Lea       | Lernende | AU_EFZ | ##-1 year##%Y## |
      | tom      | Tom       | Tester   | AU_EFZ | ##-1 year##%Y## |
    And the following "local_berufsbildung > placements" exist:
      | user | block | von          | bis          |
      | lea  | B1    | ##-60 days## | ##-30 days## |
    And the following "local_berufsbildung > assignments" exist:
      | trainer | learner |
      | bea     | lea     |
      | bea     | tom     |

  Scenario: Je Person stehen offene Pflicht- und Wahlpflicht-Kompetenzen und das Raster
    When I log in as "bea"
    And I visit "/local/berufsbildung/meine_lernenden.php"
    Then I should see "2 trainees · 2 with open gaps"
    And I should see "1 still open" in the "//div[contains(concat(' ', @class, ' '), ' local-berufsbildung-kachel-rahmen ')][contains(., 'Lea Lernende')]" "xpath_element"
    And I should see "1 elective(s) open" in the "//div[contains(concat(' ', @class, ' '), ' local-berufsbildung-kachel-rahmen ')][contains(., 'Lea Lernende')]" "xpath_element"
    And I should see "2 still open" in the "//div[contains(concat(' ', @class, ' '), ' local-berufsbildung-kachel-rahmen ')][contains(., 'Tom Tester')]" "xpath_element"
    And I should see "1 of 2 required complete" in the "//div[contains(concat(' ', @class, ' '), ' local-berufsbildung-kachel-rahmen ')][contains(., 'Lea Lernende')]" "xpath_element"

  Scenario: Das Raster nennt fuer fehlende Leistungskriterien die Bloecke, in denen sie vorkaemen
    When I log in as "bea"
    And I visit "/local/berufsbildung/meine_lernenden.php"
    Then I should see "Taught in: B4 Montage" in the "//div[contains(concat(' ', @class, ' '), ' local-berufsbildung-kachel-rahmen ')][contains(., 'Lea Lernende')]" "xpath_element"
    And I should see "Taught in: B1 Werkstatt" in the "//div[contains(concat(' ', @class, ' '), ' local-berufsbildung-kachel-rahmen ')][contains(., 'Tom Tester')]" "xpath_element"

  Scenario: Personen anderer Berufsbildner/innen erscheinen nicht
    Given the following "local_berufsbildung > learners" exist:
      | username | firstname | lastname | beruf  | jahrgang        |
      | fremd    | Fritz     | Fremder  | AU_EFZ | ##-1 year##%Y## |
    When I log in as "bea"
    And I visit "/local/berufsbildung/meine_lernenden.php"
    Then I should not see "Fritz Fremder"
    And I should see "Lea Lernende"
