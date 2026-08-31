<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Sprachdatei (Deutsch).
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Berufsbildung';
$string['settings:einstellungen'] = 'Einstellungen';
$string['admin:uebersicht'] = 'Ausbildungsverwaltung';
$string['admin:uebersicht_beschreibung'] = 'Zentraler Zugang zu den Funktionen für die berufliche Grundbildung.';
$string['admin:organisation'] = 'Organisation und Planung';
$string['admin:lernbegleitung'] = 'Lernbegleitung und üK';
$string['admin:system'] = 'Einstellungen und Datenschutz';
$string['berufsbildung:managezuordnung'] = 'Zuordnungen zwischen Berufsbildner/innen und Lernenden verwalten';
$string['berufsbildung:viewzuordnung'] = 'Zuordnungsübersicht einsehen, ohne zu ändern';
$string['settings:profilefield_beruf'] = 'Profilfeld: Beruf';
$string['settings:profilefield_beruf_desc'] = 'Benutzerdefiniertes Profilfeld, das den Beruf (z. B. \'AU_EFZ\') enthält.';
$string['settings:profilefield_jahrgang'] = 'Profilfeld: Jahrgang';
$string['settings:profilefield_jahrgang_desc'] = 'Benutzerdefiniertes Profilfeld, das das Jahr des Lehrbeginns enthält. Es genügt ein reines Jahr ("2026"); ein kombiniertes Feld, das zusätzlich den Beruf enthält (z. B. "AU 2026", etwa für die automatische Kursgruppierung), wird ebenfalls erkannt — die Jahreszahl wird daraus extrahiert, der Beruf kommt trotzdem aus dem oben konfigurierten Profilfeld.';
$string['settings:profilefield_none'] = '— keine benutzerdefinierten Profilfelder vorhanden —';
$string['settings:startmonat'] = 'Startmonat der Lehre';
$string['settings:startmonat_desc'] = 'Monat, in dem alle Lehren beginnen (1 = Januar, 12 = Dezember). Standard: 8 (August).';
$string['settings:lehrdauer_semester'] = 'Lehrdauer in Semestern (Standard)';
$string['settings:lehrdauer_semester_desc'] = 'Anzahl Semester bis zum Lehrabschluss, sofern der Beruf nicht unten unter "Lehrdauer je Beruf" abweichend eingetragen ist. Standard: 8 (vier Lehrjahre).';
$string['settings:beruf_dauer'] = 'Lehrdauer je Beruf';
$string['settings:beruf_dauer_desc'] = 'Abweichende Lehrdauer für einzelne Berufe, eine Zeile pro Beruf im Format CODE=Semester, z. B. "PM_EFZ=6" für eine dreijährige Lehre. Berufe, die hier nicht aufgeführt sind, verwenden die Lehrdauer oben.';
$string['settings:beruf_rahmen_mapping'] = 'Kompetenzrahmen je Beruf';
$string['settings:beruf_rahmen_mapping_desc'] = 'Ordnet jedem Beruf seinen core_competency-Kompetenzrahmen zu, eine Zeile je Beruf im Format CODE=framework_idnumber, z. B. "AU_EFZ=au-2022". Wird für die Lückenanalyse und die HKB-Auswahl in aufsetzenden Plugins verwendet. Berufe, die hier nicht aufgeführt sind, haben keinen Rahmen zugeordnet.

Ein Rahmen mit ausschliesslich den betrieblich vermittelten Handlungskompetenzbereichen genügt vollständig und ist die passendere Wahl als ein vollständiger Rahmen mit schulisch/überbetrieblich vermittelten HKB: Sowohl die Lerndokumentation (nur betriebliche Tätigkeiten, Art. 12 BiVo) als auch die Kompetenzabdeckung des Versetzungsplans (`get_ausgebildete_kompetenzen()`, nur Blöcke mit `ist_betrieb = true`) zeigen ohnehin nur betriebsrelevante Kompetenzen an bzw. werten sie aus.

Der Rahmen muss der lernenden Person nicht zusätzlich zugewiesen werden (kein Lernplan, keine Kurs-Verknüpfung nötig) - der Kompetenznachweis (`core_competency\\api::add_evidence()`) legt den `user_competency`-Datensatz beim ersten Eintrag automatisch an.';
$string['settings:beruf_wahlpflicht_hk'] = 'Wahlpflicht-Handlungskompetenzen je Beruf';
$string['settings:beruf_wahlpflicht_hk_desc'] = 'Wahlpflicht-HK werden in der Lückenanalyse nicht als fehlend ausgewiesen. Eine Zeile je Beruf im Format CODE=HK-ID,HK-ID, zum Beispiel "AU_EFZ=7777 a.04,7777 a.05". Die HK-ID ist die ID-Nummer des obersten Knotens im Kompetenzrahmen. Für die AU-Datei sind dies die als W markierten HK.';
$string['settings:versetzungsplan_schwelle_prozent'] = 'Schwelle für unvollständige Lieferungen (%)';
$string['settings:versetzungsplan_schwelle_prozent_desc'] = 'Enthält eine Versetzungsplan-Lieferung deutlich weniger verarbeitete Personen als die vorherige, wird sie abgewiesen statt verarbeitet. Standard: 20 (ein Rückgang um mehr als 20 % gilt als unvollständig).';
$string['settings:versetzungsplan_alterung_tage'] = 'Alterungshinweis nach (Tagen)';
$string['settings:versetzungsplan_alterung_tage_desc'] = 'Liegt der letzte erfolgreiche Versetzungsplan-Import länger zurück, erscheint ein Hinweis. Keine Sperre, nur eine Erinnerung, dass die Vorbelegung auf einem veralteten Plan beruhen könnte.';
$string['settings:retention_monate'] = 'Aufbewahrungsfrist nach Ausbildungsabschluss (Monate)';
$string['settings:retention_monate_desc'] = 'Nach wie vielen Monaten seit dem berechneten Ausbildungsabschluss Zuordnungen (und über local_lerndokumentation die Lerndoku-Inhalte) automatisch endgültig gelöscht werden, sofern keine Aufbewahrungspflicht dokumentiert ist. Standard: 12.';
$string['role:berufsbildner'] = 'Berufsbildner/in';
$string['role:berufsbildner_desc'] = 'Zugewiesen im Nutzerkontext einer lernenden Person, sobald eine Zuordnung besteht. Traegt selbst keine Capabilities - die vergeben die aufsetzenden Plugins.';

$string['nav:meine_lehre'] = 'Meine Ausbildung';
$string['nav:meine_lernenden'] = 'Meine Lernenden';
$string['form:ausbildungsstand'] = '{$a->beruf}, {$a->lehrjahr}. Lehrjahr (Semester {$a->semester})';
$string['form:keine_taetigkeiten'] = 'Keine Tätigkeiten vorhanden.';
$string['luecken:titel'] = 'Noch nicht ausgebildete Pflicht-Handlungskompetenzen';
$string['luecken:titel_anzahl'] = '{$a} Lücken im Kompetenzrahmen';
$string['luecken:keine'] = 'Alle Pflicht-Handlungskompetenzen des Rahmens sind bereits abgedeckt.';
$string['meine_lernenden:keine_lernenden'] = 'Sie haben aktuell keine zugeordneten Lernenden.';
$string['meine_lernenden:taetigkeiten_anzahl'] = '{$a} Tätigkeiten anzeigen';
$string['meine_lernenden:profil_oeffnen'] = 'Profil und Lerndokumentation öffnen';
$string['meine_lehre:kein_ausbildungsstand'] = 'Für Ihr Profil ist kein Beruf oder Jahrgang hinterlegt.';
$string['error:keinezustaendigkeit'] = 'Keine Zuständigkeit für diese lernende Person.';
$string['error:aufbewahrunggrundleer'] = 'Die Aufbewahrungspflicht benötigt eine Begründung.';

$string['zuordnung:uebersicht'] = 'Zuordnungen';
$string['zuordnung:neue_zuordnung'] = 'Neue Zuordnung';
$string['zuordnung:anlegen'] = 'Zuordnung anlegen';
$string['zuordnung:beenden'] = 'Zuordnung beenden';
$string['zuordnung:berufsbildner'] = 'Berufsbildner/in';
$string['zuordnung:lernende'] = 'Lernende';
$string['zuordnung_lernende'] = 'Lernende';
$string['zuordnung_lernende_help'] = 'Mehrfachauswahl möglich. Hat eine ausgewählte Person bereits eine laufende Zuordnung zu einer/einem anderen Berufsbildner/in, wird diese beim Speichern automatisch zum Vortag des Gültig-ab-Datums beendet - nicht stillschweigend überschrieben.';
$string['zuordnung:kohorten'] = 'Globale Gruppen';
$string['zuordnung_kohorten'] = 'Globale Gruppen';
$string['zuordnung_kohorten_help'] = 'Alle Mitglieder der ausgewählten globalen Gruppe(n) werden zusätzlich zu den oben einzeln ausgewählten Lernenden zugeordnet. Die Zuordnung ist eine einmalige Momentaufnahme der aktuellen Mitgliedschaft - spätere Änderungen an der Gruppe wirken sich nicht automatisch auf bereits angelegte Zuordnungen aus.';
$string['zuordnung:beruf'] = 'Beruf';
$string['zuordnung_beruf'] = 'Beruf';
$string['zuordnung_beruf_help'] = 'Leer lassen, um den Beruf aus dem Profil der/des Lernenden zu übernehmen. Bei einer Auswahl mehrerer Personen wird das für jede Person einzeln aufgelöst. Nur nötig, wenn das Profil (noch) keinen Beruf enthält oder ein davon abweichender Wert für diese Zuordnung gelten soll.';
$string['zuordnung:rolle'] = 'Rolle';
$string['zuordnung_rolle'] = 'Rolle';
$string['zuordnung_rolle_help'] = 'Pro Rolle ist je Lernende/r immer nur eine Zuordnung laufend - eine neue mit derselben Rolle beendet automatisch die bisherige. Verschiedene Rollen dürfen gleichzeitig laufen, z. B. "Hauptverantwortlich" und zusätzlich "Stellvertretung" für dieselbe Person. Standard: hauptverantwortlich.

"Hauptverantwortlich": die/der Berufsbildner/in, die/der die Ausbildung dieser Person hauptsächlich verantwortet - der Normalfall.
"Stellvertretung": eine zusätzliche, gleichzeitig laufende Zuständigkeit für dieselbe Person, z. B. während einer Ferienabwesenheit der/des Hauptverantwortlichen.

Für die Zuständigkeitsprüfung (is_zustaendig(), Sichtbarkeit in aufsetzenden Plugins) sind beide Rollen aktuell gleichwertig - es gibt heute keine unterschiedlichen Berechtigungen zwischen ihnen, der Unterschied ist rein organisatorisch.';
$string['zuordnung:rolle_hauptverantwortlich'] = 'Hauptverantwortlich';
$string['zuordnung:rolle_stellvertretung'] = 'Stellvertretung';
$string['zuordnung:gueltig_von'] = 'Gültig ab';
$string['zuordnung:gueltig_bis'] = 'Gültig bis';
$string['zuordnung:fehler_gleiche_person'] = 'Berufsbildner/in und Lernende/r dürfen nicht dieselbe Person sein.';
$string['zuordnung:fehler_keine_lernenden'] = 'Mindestens eine Lernende/ein Lernender oder eine globale Gruppe auswählen.';
$string['zuordnung:fehler_enddatum'] = 'Das Enddatum darf nicht vor dem Startdatum liegen.';
$string['zuordnung:fehler_ueberschneidung'] = 'Diese Zuordnung würde sich mit einer bestehenden Zuordnung derselben Rolle überschneiden.';
$string['zuordnung:loeschen'] = 'Zuordnung löschen';
$string['zuordnung:geloescht'] = 'Die falsche Zuordnung wurde gelöscht.';
$string['zuordnung:loeschen_bestaetigung'] = 'Die Zuordnung „{$a}“ wird endgültig gelöscht. Dies ist nur für falsch erfasste Zuordnungen gedacht. Fortfahren?';
$string['zuordnung:angelegt'] = '{$a} Zuordnung(en) angelegt.';
$string['zuordnung:beendet_erfolgreich'] = 'Zuordnung beendet.';
$string['zuordnung:wiedereroeffnet'] = 'Zuordnung ist wieder laufend.';
$string['zuordnung:beenden_beschreibung'] = 'Gültig-bis-Datum von {$a->berufsbildner} und {$a->lernende} bearbeiten. Leer lassen macht die Zuordnung (wieder) laufend.';
$string['zuordnung:bearbeiten'] = 'Gültig bis bearbeiten';
$string['zuordnung:speichern'] = 'Speichern';
$string['zuordnung_gueltig_bis'] = 'Gültig bis';
$string['zuordnung_gueltig_bis_help'] = 'Leer lassen (Häkchen aus) macht eine beendete Zuordnung wieder laufend - zum Korrigieren eines falsch gesetzten Enddatums, ohne die Zuordnung neu anzulegen.';
$string['zuordnung:status'] = 'Status';
$string['zuordnung:status_laufend'] = 'laufend';
$string['zuordnung:status_beendet'] = 'beendet';
$string['zuordnung:lehrjahr'] = 'Lehrjahr';

$string['task:sync_kohorten'] = 'Kohorten-Zuordnungen abgleichen';
$string['task:sync_role_assignments'] = 'Rolle Berufsbildner/in abgleichen';
$string['task:zuordnung_retention'] = 'Zuordnungen nach Ablauf der Aufbewahrungsfrist löschen';
$string['kohortenlink:uebersicht'] = 'Kohorten-Verknüpfungen';
$string['kohortenlink:neu'] = 'Neue Verknüpfung';
$string['kohortenlink:anlegen'] = 'Verknüpfung anlegen';
$string['kohortenlink:kohorte'] = 'Globale Gruppe';
$string['kohortenlink:beschreibung'] = 'Alle aktuellen und künftigen Mitglieder der ausgewählten globalen Gruppe werden dieser/diesem Berufsbildner/in laufend zugeordnet - der Abgleich läuft stündlich im Hintergrund und zieht sowohl neue als auch ausgeschiedene Mitglieder nach. Anders als die einmalige Auswahl auf der Zuordnungsseite.';
$string['kohortenlink:angelegt'] = 'Verknüpfung angelegt. {$a->erzeugt} Zuordnung(en) sofort erzeugt.';
$string['kohortenlink:aktiv'] = 'Aktiv';
$string['kohortenlink:status_aktiv'] = 'aktiv';
$string['kohortenlink:status_inaktiv'] = 'inaktiv';
$string['kohortenlink:deaktivieren'] = 'Deaktivieren';
$string['kohortenlink:aktivieren'] = 'Aktivieren';
$string['kohortenlink:deaktiviert_hinweis'] = 'Deaktiviert: bestehende Zuordnungen bleiben unangetastet, es werden nur keine neuen oder beendeten Mitglieder mehr nachgezogen.';
$string['kohortenlink:keine_kohorten'] = 'Es sind keine globalen Gruppen vorhanden. Unter Nutzer/innen ▸ Kohorten zuerst eine anlegen.';
$string['kohortenlink:keine_links'] = 'Es sind noch keine Kohorten-Verknüpfungen vorhanden.';
$string['kohortenlink:fehler_existiert'] = 'Für diese Kombination aus Gruppe, Berufsbildner/in und Rolle besteht bereits eine Verknüpfung.';

$string['import:titel'] = 'CSV-Import';
$string['import:beschreibung'] = 'Für den Jahreswechsel, wenn ein ganzer Jahrgang neu zugeordnet wird. Spalten (erste Zeile als Titel): berufsbildner, lernende (je der Moodle-Benutzername), beruf (leer = aus dem Profil übernehmen), gueltig_von (Format JJJJ-MM-TT). Nach der Vorschau werden nur fehlerfreie Zeilen übernommen - fehlerhafte werden einzeln gemeldet, ohne den Import abzubrechen.';
$string['import:datei'] = 'CSV-Datei';
$string['import:trennzeichen'] = 'Trennzeichen';
$string['import:kodierung'] = 'Kodierung';
$string['import:vorschau_anzeigen'] = 'Vorschau anzeigen';
$string['import:vorschau'] = 'Import-Vorschau';
$string['import:vorschau_fehlerhinweis'] = '{$a} Zeile(n) mit Fehlern - diese werden beim Bestätigen übersprungen, die übrigen werden importiert.';
$string['import:zeile'] = 'Zeile';
$string['import:status'] = 'Status';
$string['import:zeile_ok'] = 'bereit';
$string['import:fehler'] = 'Fehler';
$string['import:bestaetigen'] = 'Import bestätigen';
$string['import:abbrechen'] = 'Abbrechen';
$string['import:abgeschlossen'] = '{$a->angelegt} Zuordnung(en) angelegt, {$a->fehler} Zeile(n) übersprungen.';
$string['import:keine_zeilen'] = 'Die Datei enthält keine Datenzeilen.';
$string['import:fehler_datei'] = 'Die Datei konnte nicht gelesen werden: {$a}';
$string['import:fehler_abgelaufen'] = 'Der Import ist abgelaufen oder wurde bereits abgeschlossen. Bitte die Datei erneut hochladen.';
$string['import:fehlende_spalten'] = 'Fehlende Spalte(n) in der ersten Zeile: {$a}';
$string['import:fehler_berufsbildner_fehlt'] = 'Berufsbildner/in fehlt.';
$string['import:fehler_lernende_fehlt'] = 'Lernende/r fehlt.';
$string['import:fehler_person_nicht_gefunden'] = 'Kein Benutzerkonto mit dem Benutzernamen "{$a}" gefunden.';
$string['import:fehler_datum'] = 'Ungültiges Datum "{$a}" (erwartet: JJJJ-MM-TT).';

$string['berufsbildung:manageblocks'] = 'Ausbildungsblöcke und ihre Kompetenzabdeckung verwalten';
$string['berufsbildung:importplan'] = 'Versetzungsplan importieren';
$string['berufsbildung:manageaufbewahrung'] = 'Dokumentierte Aufbewahrungspflichten verwalten';

$string['bloecke:uebersicht'] = 'Ausbildungsblöcke';
$string['bloecke:neu'] = 'Neuer Block';
$string['bloecke:keine_bloecke'] = 'Es sind noch keine Ausbildungsblöcke vorhanden. Sie werden beim nächsten Versetzungsplan-Import automatisch angelegt, oder hier manuell.';
$string['block:nummer'] = 'Nummer';
$string['block:name'] = 'Bezeichnung';
$string['block:ist_betrieb'] = 'Betrieblicher Einsatz';
$string['block_ist_betrieb'] = 'Betrieblicher Einsatz';
$string['block_ist_betrieb_help'] = 'Nur betriebliche Einsätze zählen bei get_ausgebildete_kompetenzen() zur Kompetenzabdeckung. Schulische oder überbetriebliche Blöcke (z.B. üK) hier abwählen.';
$string['block:aktiv'] = 'Aktiv';
$string['block:bearbeiten'] = 'Bearbeiten';
$string['block:anlegen'] = 'Block anlegen';
$string['block:speichern'] = 'Speichern';
$string['block:kompetenzen'] = 'Lern- und Handlungskompetenzen';
$string['block:angelegt'] = 'Block gespeichert.';
$string['block:fehler_nummer_existiert'] = 'Ein Block mit dieser Nummer besteht bereits.';

$string['blockhk:uebersicht'] = 'Kompetenzabdeckung für Block {$a}';
$string['blockhk:kompetenz'] = 'Lern- oder Handlungskompetenz';
$string['blockhk:intensitaet'] = 'Intensität';
$string['blockhk:intensitaet_schwerpunkt'] = 'Schwerpunkt';
$string['blockhk:intensitaet_teilweise'] = 'teilweise';
$string['blockhk:hinzufuegen'] = 'Kompetenz hinzufügen';
$string['blockhk:entfernen'] = 'Entfernen';
$string['blockhk:keine'] = 'Für diesen Block ist noch keine Kompetenz hinterlegt.';
$string['blockhk:fehler_existiert'] = 'Diese Kompetenz ist diesem Block bereits zugeordnet.';
$string['blockhk:keine_kompetenzen'] = 'Es sind keine Handlungskompetenzen vorhanden - core_competency zuerst einrichten.';
$string['blockhk:hinzugefuegt'] = 'Kompetenz hinzugefügt.';
$string['blockhk:entfernt'] = 'Kompetenz entfernt.';
$string['blockhk:zurueck'] = 'Zurück zu den Ausbildungsblöcken';

$string['planimport:titel'] = 'Versetzungsplan-Import';
$string['planimport:beschreibung'] = 'Rückfallweg für den Fall, dass der wöchentliche Webservice-Import (noch) nicht läuft, oder wenn ausserplanmässig etwas nachgezogen werden muss. Nimmt dieselbe CSV entgegen wie der Webservice - siehe docs/schnittstelle_versetzungsplan.md.';
$string['planimport:datei'] = 'CSV-Datei';
$string['planimport:testlauf'] = 'Testlauf (nur prüfen, nichts schreiben)';
$string['planimport:rueckgang_bestaetigt'] = 'Rückgang bestätigt (Vollständigkeitsschutz überschreiben)';
$string['planimport:hochladen'] = 'Hochladen';
$string['planimport:ergebnis'] = 'Ergebnis';
$string['planimport:status'] = 'Status';
$string['planimport:status_ok'] = 'ok';
$string['planimport:status_mit_warnungen'] = 'mit Warnungen';
$string['planimport:status_abgewiesen'] = 'abgewiesen';
$string['planimport:status_fehlgeschlagen'] = 'fehlgeschlagen';
$string['planimport:unveraendert'] = 'Unverändert gegenüber dem letzten erfolgreichen Import - nichts wurde geschrieben.';
$string['planimport:zeilen_gelesen'] = 'Zeilen gelesen';
$string['planimport:personen_verarbeitet'] = 'Personen verarbeitet';
$string['planimport:ausserhalb_geltungsbereich'] = 'Ohne aktive Zuordnung (übersprungen, nicht protokolliert)';
$string['planimport:einsaetze_erzeugt'] = 'Einsätze erzeugt';
$string['planimport:protokoll'] = 'Protokoll';
$string['planimport:abgewiesen_hinweis'] = 'Diese Lieferung wurde abgewiesen, weil deutlich weniger Personen verarbeitet wurden als beim letzten erfolgreichen Import. Falls beabsichtigt, unten bestätigen und erneut hochladen.';
$string['planimport:letzter_import'] = 'Letzter erfolgreicher Import';
$string['planimport:noch_nie'] = 'noch nie';
$string['planimport:alterung_hinweis'] = 'Der letzte erfolgreiche Versetzungsplan-Import liegt mehr als {$a} Tage zurück. Die Vorbelegung im Bildungsbericht könnte auf einem veralteten Plan beruhen.';
$string['planimport:fehler_datei'] = 'Die Datei konnte nicht gelesen werden: {$a}';

$string['aufbewahrung:uebersicht'] = 'Aufbewahrungspflichten';
$string['aufbewahrung:neu'] = 'Neue Aufbewahrungspflicht';
$string['aufbewahrung:bearbeiten'] = 'Bearbeiten';
$string['aufbewahrung:lernende'] = 'Lernende/r';
$string['aufbewahrung:grund'] = 'Begründung';
$string['aufbewahrung:gueltig_von'] = 'Gültig ab';
$string['aufbewahrung:gueltig_bis'] = 'Gültig bis';
$string['aufbewahrung:unbefristet'] = 'unbefristet';
$string['aufbewahrung:keine_eintraege'] = 'Es sind keine Aufbewahrungspflichten dokumentiert.';
$string['aufbewahrung:speichern'] = 'Speichern';
$string['aufbewahrung:gespeichert'] = 'Aufbewahrungspflicht gespeichert.';

$string['ws:import_versetzungsplan'] = 'Versetzungsplan importieren';
$string['ws:import_versetzungsplan_desc'] = 'Nimmt eine Versetzungsplan-CSV entgegen und verarbeitet sie gemäss docs/schnittstelle_versetzungsplan.md.';

$string['privacy:metadata:zuordnung'] = 'Für jede Zuordnung zwischen Berufsbildner/in und lernender Person wird gespeichert, wer für wen und in welchem Zeitraum zuständig ist.';
$string['privacy:metadata:zuordnung:berufsbildnerid'] = 'Die Berufsbildnerin/der Berufsbildner dieser Zuordnung.';
$string['privacy:metadata:zuordnung:lernendeid'] = 'Die lernende Person dieser Zuordnung.';
$string['privacy:metadata:zuordnung:beruf'] = 'Der Beruf, in dem die Zuordnung gilt.';
$string['privacy:metadata:zuordnung:rolle'] = 'Die Rolle innerhalb der Zuordnung, z.B. hauptverantwortlich.';
$string['privacy:metadata:zuordnung:gueltig_von'] = 'Beginn der Zuordnung.';
$string['privacy:metadata:zuordnung:gueltig_bis'] = 'Ende der Zuordnung, falls beendet.';
$string['privacy:metadata:kohortenlink'] = 'Für jede laufende Kohorten-Verknüpfung wird gespeichert, welche/r Berufsbildner/in mit welcher Kohorte synchronisiert wird.';
$string['privacy:metadata:kohortenlink:berufsbildnerid'] = 'Die Berufsbildnerin/der Berufsbildner dieser Verknüpfung.';
$string['privacy:metadata:kohortenlink:rolle'] = 'Die Rolle, mit der neue Zuordnungen aus dieser Verknüpfung angelegt werden.';
$string['privacy:metadata:kohortenlink:beruf'] = 'Der Beruf, mit dem neue Zuordnungen aus dieser Verknüpfung angelegt werden.';
$string['privacy:metadata:einsatz'] = 'Für jeden betrieblichen Einsatz aus dem Versetzungsplan wird gespeichert, welche Person wann in welchem Ausbildungsblock war.';
$string['privacy:metadata:einsatz:userid'] = 'Die lernende Person dieses Einsatzes.';
$string['privacy:metadata:einsatz:von'] = 'Beginn des Einsatzes.';
$string['privacy:metadata:einsatz:bis'] = 'Ende des Einsatzes.';
$string['privacy:metadata:planimport'] = 'Für jeden Versetzungsplan-Importlauf wird protokolliert, wer ihn ausgeführt hat.';
$string['privacy:metadata:planimport:ausgefuehrt_von'] = 'Das Konto, das den Import ausgeführt hat (Dienstkonto oder manueller Upload).';
$string['privacy:metadata:planimport:quelle'] = 'Herkunft des Imports (Webservice oder manueller Upload).';
$string['privacy:metadata:planimport:zeitpunkt'] = 'Zeitpunkt des Imports.';
$string['privacy:metadata:aufbewahrung'] = 'Für jede dokumentierte Aufbewahrungspflicht wird gespeichert, für welche Person, aus welchem Grund und für welchen Zeitraum sie gilt.';
$string['privacy:metadata:aufbewahrung:lernendeid'] = 'Die betroffene lernende Person.';
$string['privacy:metadata:aufbewahrung:grund'] = 'Die Begründung der Aufbewahrungspflicht.';
$string['privacy:metadata:aufbewahrung:gueltig_von'] = 'Beginn der Aufbewahrungspflicht.';
$string['privacy:metadata:aufbewahrung:gueltig_bis'] = 'Ende der Aufbewahrungspflicht, falls befristet.';
$string['privacy:pfad_zuordnungen_lernende'] = 'Zuordnungen als lernende Person';
$string['privacy:pfad_zuordnungen_berufsbildner'] = 'Zuordnungen als Berufsbildner/in';
$string['privacy:pfad_kohortenlinks'] = 'Kohorten-Verknüpfungen';
$string['privacy:pfad_einsaetze'] = 'Betriebliche Einsätze';
$string['privacy:pfad_planimporte'] = 'Ausgeführte Versetzungsplan-Importe';
$string['privacy:pfad_aufbewahrung'] = 'Dokumentierte Aufbewahrungspflichten';
