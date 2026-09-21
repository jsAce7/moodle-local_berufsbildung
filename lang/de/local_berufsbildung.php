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
$string['admin:uebersicht'] = 'Berufsbildung';
$string['admin:organisation'] = 'Zuordnungen und Gruppen';
$string['admin:planung'] = 'Ausbildungsplanung';
$string['admin:lernbegleitung'] = 'Lernbegleitung';
$string['admin:system'] = 'Einstellungen und Datenschutz';
$string['berufsbildung:managezuordnung'] = 'Zuordnungen zwischen Berufsbildner/innen und Lernenden verwalten';
$string['berufsbildung:viewzuordnung'] = 'Zuordnungsübersicht einsehen, ohne zu ändern';
$string['settings:profilefield_beruf'] = 'Profilfeld: Beruf';
$string['settings:profilefield_beruf_desc'] = 'Benutzerdefiniertes Profilfeld, das den Beruf (z. B. \'AU_EFZ\') enthält.';
$string['settings:profilefield_jahrgang'] = 'Profilfeld: Jahrgang';
$string['settings:profilefield_jahrgang_desc'] = 'Benutzerdefiniertes Profilfeld mit dem Jahr des Lehrbeginns. "2026" genügt; ein kombiniertes Feld wie "AU 2026" wird ebenfalls erkannt — daraus wird nur die Jahreszahl gelesen.';
$string['settings:profilefield_none'] = '— keine benutzerdefinierten Profilfelder vorhanden —';
$string['settings:startmonat'] = 'Startmonat der Lehre';
$string['settings:startmonat_desc'] = 'Monat, in dem alle Lehren beginnen (1 = Januar, 12 = Dezember). Standard: 8 (August).';
$string['settings:lehrdauer_semester'] = 'Lehrdauer in Semestern (Standard)';
$string['settings:lehrdauer_semester_desc'] = 'Anzahl Semester bis zum Lehrabschluss, sofern der Beruf nicht unten unter "Lehrdauer je Beruf" abweichend eingetragen ist. Standard: 8 (vier Lehrjahre).';
$string['settings:beruf_dauer'] = 'Lehrdauer je Beruf';
$string['settings:beruf_dauer_desc'] = 'Abweichende Lehrdauer für einzelne Berufe, eine Zeile pro Beruf im Format CODE=Semester, z. B. "PM_EFZ=6" für eine dreijährige Lehre. Berufe, die hier nicht aufgeführt sind, verwenden die Lehrdauer oben.';
$string['settings:beruf_wahlpflicht_hk'] = 'Wahlpflicht-Handlungskompetenzen je Beruf';
$string['settings:beruf_wahlpflicht_hk_desc'] = 'Diese Handlungskompetenzen gelten in der Lückenanalyse nicht als fehlend und erscheinen im Kompetenzraster grün statt gelb. Eine Zeile je Beruf im Format CODE=HK-ID,HK-ID, z. B. "AU_EFZ=a.04,a.05". Gemeint ist die ID-Nummer einer Handlungskompetenz, nicht eines Bereichs. Der Rahmen-Präfix darf weggelassen werden: "a.04" trifft auch eine Kompetenz mit der ID-Nummer "7777BE a.04". In der AU-Datei sind das die mit "W" markierten HK.';
$string['settings:versetzungsplan_schwelle_prozent'] = 'Schwelle für unvollständige Lieferungen (%)';
$string['settings:versetzungsplan_schwelle_prozent_desc'] = 'Enthält eine Lieferung deutlich weniger Personen als die vorherige, wird sie abgewiesen statt verarbeitet. Standard: 20 (Rückgang über 20 % gilt als unvollständig).';
$string['settings:versetzungsplan_alterung_tage'] = 'Alterungshinweis nach (Tagen)';
$string['settings:versetzungsplan_alterung_tage_desc'] = 'Liegt der letzte erfolgreiche Versetzungsplan-Import länger zurück, erscheint ein Hinweis. Keine Sperre, nur eine Erinnerung, dass die Vorbelegung auf einem veralteten Plan beruhen könnte.';
$string['settings:retention_monate'] = 'Aufbewahrungsfrist nach Ausbildungsabschluss (Monate)';
$string['settings:retention_monate_desc'] = 'Wie viele Monate nach dem berechneten Lehrabschluss die Zuordnungen einer Person endgültig gelöscht werden — und damit auch ihre Lerndokumentation. Ausnahme: eine dokumentierte Aufbewahrungspflicht. Standard: 12.';
$string['role:berufsbildner'] = 'Berufsbildner/in';
$string['role:berufsbildner_desc'] = 'Zugewiesen im Nutzerkontext einer lernenden Person, sobald eine Zuordnung besteht. Traegt selbst keine Capabilities - die vergeben die aufsetzenden Plugins.';
$string['role:planung'] = 'Ausbildungsplanung';
$string['role:planung_desc'] = 'Systemweite Rolle für die Pflege der Ausbildungsblöcke und ihrer Kompetenzabdeckung. Wird automatisch jeder Person zugewiesen, die mindestens eine laufende Zuordnung als Berufsbildner/in hat, und wieder entzogen, sobald die letzte davon beendet ist. Bewusst getrennt von der Rolle "Berufsbildner/in": diese hängt am Nutzerkontext der einzelnen lernenden Person, bleibt für die stichtagsgeprüfte Einsicht auch nach dem Ende bestehen und darf keine systemweiten Rechte tragen.';

$string['nav:meine_lehre'] = 'Meine Lehre';
$string['nav:meine_lernenden'] = 'Meine Lernenden';
$string['teilnahmeart:uek_extern'] = 'Extern · nur üK';
$string['teilnahmeart:uek_extern_setzen'] = 'Als extern (nur üK) markieren';
$string['teilnahmeart:lehre_setzen'] = 'Als reguläre Lehre führen';
$string['teilnahmeart:gespeichert'] = 'Teilnahmeart gespeichert.';
$string['faelligkeiten:titel'] = 'Fälligkeiten';
$string['faelligkeiten:ich'] = 'Ich';
$string['faelligkeiten:leer'] = 'Aktuell sind keine Fälligkeiten vorhanden.';
$string['faelligkeiten:ueberfaellig'] = 'Überfällig';
$string['faelligkeiten:ueberfaellig_anzahl'] = '{$a} überfällig';
$string['form:ausbildungsstand'] = '{$a->beruf}, {$a->lehrjahr}. Lehrjahr (Semester {$a->semester})';
$string['form:keine_taetigkeiten'] = 'Keine Tätigkeiten vorhanden.';
$string['luecken:titel'] = 'Noch nicht ausgebildete Pflicht-Handlungskompetenzen';
$string['luecken:titel_anzahl'] = '{$a} noch offen';
$string['luecken:keine'] = 'Alle Pflicht-Handlungskompetenzen des Rahmens sind bereits abgedeckt.';
$string['meine_lernenden:keine_lernenden'] = 'Sie haben aktuell keine zugeordneten Lernenden.';
$string['meine_lernenden:keine_treffer'] = 'Keine Lernenden gefunden. Bitte Suche oder Beruf-Filter anpassen.';
$string['meine_lernenden:taetigkeiten_anzahl'] = '{$a} Tätigkeiten';
$string['meine_lernenden:profil_oeffnen'] = 'Profil und Lerndokumentation öffnen';
$string['meine_lernenden:suche_placeholder'] = 'Name suchen…';
$string['meine_lernenden:beruf_alle'] = 'Alle Berufe';
$string['meine_lernenden:filtern'] = 'Filtern';
$string['meine_lernenden:zusammenfassung'] = '{$a->anzahl} Lernende · {$a->luecken} mit offenen Lücken';
$string['meine_lernenden:bloecke'] = 'Ausbildungsblöcke verwalten';
$string['meine_lehre:kein_ausbildungsstand'] = 'Für Ihr Profil ist kein Beruf oder Jahrgang hinterlegt.';
$string['meine_lehre:uek_extern'] = 'Sie nehmen extern nur an überbetrieblichen Kursen teil. Eine Lehre und eine Lerndokumentation werden hier nicht geführt.';
$string['meine_lehre:vor_beginn'] = 'Ihre Lehre beginnt am {$a}.';
$string['meine_lehre:abgeschlossen'] = '{$a->beruf} — Ausbildung abgeschlossen am {$a->datum}.';
$string['luecken:zusammenfassung'] = '{$a->abgedeckt} von {$a->soll} abgedeckt';
$string['luecken:bereich_abdeckung'] = '{$a->abgedeckt} von {$a->soll}';
$string['raster:titel'] = 'Handlungskompetenzen';
$string['raster:zusammenfassung'] = '{$a->abgedeckt} von {$a->soll} Pflicht-Handlungskompetenzen sind im Plan abgedeckt';
$string['raster:bereich_abdeckung'] = '{$a->abgedeckt} von {$a->soll}';
$string['raster:horizont'] = 'Vorliegender Versetzungsplan reicht bis {$a}.';
$string['raster:pflicht'] = 'Pflicht';
$string['raster:wahlpflicht'] = 'Wahlpflicht';
$string['raster:status_abgedeckt'] = 'bereits vorgekommen';
$string['raster:status_eingeplant'] = 'später eingeplant';
$string['raster:status_offen'] = 'nicht im Plan';
$string['raster:legende_pflicht'] = 'Pflicht-Handlungskompetenz';
$string['raster:legende_wahlpflicht'] = 'Wahlpflicht-Handlungskompetenz';
$string['raster:legende_abgedeckt'] = 'kam bereits in einem Einsatz vor';
$string['raster:legende_eingeplant'] = 'im vorliegenden Plan später eingeplant';
$string['raster:legende_offen'] = 'ohne Zeichen: im vorliegenden Plan nicht enthalten';
$string['stepper:legende'] = 'Lehrjahr';
$string['stepper:lehrjahr'] = '{$a}. Lehrjahr';
$string['einsatz:zeitraum'] = '{$a->von} bis {$a->bis}';
$string['einsatz:kw'] = 'KW {$a}';
$string['einsatz:kw_spanne'] = 'KW {$a->von}–{$a->bis}';
$string['einsatz:aktuell'] = 'aktuell';
$string['einsatz:aktuell_titel'] = 'Aktueller Einsatz';
$string['einsatz:timeline_titel'] = 'Versetzungsplan';
$string['einsatz:gruppe_anzahl'] = '{$a} Einsätze';
$string['einsatz:gruppe_anzahl_eins'] = '1 Einsatz';
$string['einsatz:unbekannter_block'] = 'Ausbildungsblock ohne Bezeichnung';
$string['nachweis:titel'] = 'Tätigkeiten';
$string['nachweis:semester'] = '{$a}. Semester';
$string['nachweis:ohne_semester'] = 'Ausserhalb der Lehrzeit';
$string['error:keinezustaendigkeit'] = 'Keine Zuständigkeit für diese lernende Person.';
$string['error:aufbewahrunggrundleer'] = 'Die Aufbewahrungspflicht benötigt eine Begründung.';

$string['zuordnung:uebersicht'] = 'Zuordnungen';
$string['zuordnung:neue_zuordnung'] = 'Neue Zuordnung';
$string['zuordnung:anlegen'] = 'Zuordnung anlegen';
$string['zuordnung:beenden'] = 'Zuordnung beenden';
$string['zuordnung:berufsbildner'] = 'Berufsbildner/in';
$string['zuordnung:lernende'] = 'Lernende';
$string['zuordnung_lernende'] = 'Lernende';
$string['zuordnung_lernende_help'] = 'Mehrfachauswahl möglich. Eine schon laufende Zuordnung zu einer anderen Person wird beim Speichern automatisch am Vortag des Gültig-ab-Datums beendet, nicht überschrieben.';
$string['zuordnung:kohorten'] = 'Globale Gruppen';
$string['zuordnung_kohorten'] = 'Globale Gruppen';
$string['zuordnung_kohorten_help'] = 'Alle Mitglieder der gewählten Gruppen werden zusätzlich zugeordnet — als einmalige Momentaufnahme. Spätere Änderungen an der Gruppe wirken nicht nach; dafür gibt es die Verknüpfungen mit globalen Gruppen.';
$string['zuordnung:beruf'] = 'Beruf';
$string['zuordnung_beruf'] = 'Beruf';
$string['zuordnung_beruf_help'] = 'Leer lassen: der Beruf kommt aus dem Profil, bei mehreren Personen je einzeln. Nur nötig, wenn im Profil kein Beruf steht oder hier ein anderer gelten soll.';
$string['zuordnung:rolle'] = 'Rolle';
$string['zuordnung:herkunft'] = 'Herkunft';
$string['zuordnung:herkunft_manuell'] = 'Manuell';
$string['zuordnung:herkunft_kohorte'] = 'Globale Gruppe: {$a}';
$string['zuordnung_rolle'] = 'Rolle';
$string['zuordnung_rolle_help'] = 'Je Rolle läuft pro Lernende/r nur eine Zuordnung - eine neue mit derselben Rolle beendet die bisherige automatisch. Verschiedene Rollen dürfen gleichzeitig laufen.

"Hauptverantwortlich" ist der Normalfall. "Stellvertretung" ist eine zusätzliche Zuständigkeit für dieselbe Person, z. B. während einer Ferienabwesenheit.

Für die Sichtbarkeit sind beide Rollen heute gleichwertig; der Unterschied ist rein organisatorisch.';
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

$string['task:sync_kohorten'] = 'Zuordnungen globaler Gruppen abgleichen';
$string['task:sync_role_assignments'] = 'Rolle Berufsbildner/in abgleichen';
$string['task:zuordnung_retention'] = 'Zuordnungen nach Ablauf der Aufbewahrungsfrist löschen';
$string['kohortenlink:uebersicht'] = 'Verknüpfungen mit globalen Gruppen';
$string['kohortenlink:neu'] = 'Neue Verknüpfung mit globaler Gruppe';
$string['kohortenlink:anlegen'] = 'Verknüpfung mit globaler Gruppe anlegen';
$string['kohortenlink:kohorte'] = 'Globale Gruppe';
$string['kohortenlink:beschreibung'] = 'Alle heutigen und künftigen Mitglieder der gewählten Gruppe werden dieser/diesem Berufsbildner/in laufend zugeordnet. Der stündliche Abgleich zieht neue und ausgeschiedene Mitglieder nach - anders als die einmalige Auswahl auf der Zuordnungsseite.';
$string['kohortenlink:angelegt'] = 'Verknüpfung angelegt. {$a->erzeugt} Zuordnung(en) sofort erzeugt.';
$string['kohortenlink:aktiv'] = 'Aktiv';
$string['kohortenlink:status_aktiv'] = 'aktiv';
$string['kohortenlink:status_inaktiv'] = 'inaktiv';
$string['kohortenlink:deaktivieren'] = 'Deaktivieren';
$string['kohortenlink:aktivieren'] = 'Aktivieren';
$string['kohortenlink:loeschen'] = 'Löschen';
$string['kohortenlink:loeschen_bestaetigung'] = 'Die Verknüpfung mit der globalen Gruppe (ID {$a}) wird endgültig gelöscht. Fortfahren?';
$string['kohortenlink:geloescht'] = 'Verknüpfung mit globaler Gruppe gelöscht.';
$string['kohortenlink:loeschen_mit_zuordnungen'] = 'Diese Verknüpfung hat schon Zuordnungen erzeugt und kann darum nicht gelöscht werden. Bitte stattdessen deaktivieren, damit die Herkunft dieser Zuordnungen erhalten bleibt.';
$string['kohortenlink:deaktiviert_hinweis'] = 'Deaktiviert: bestehende Zuordnungen bleiben unangetastet, es werden nur keine neuen oder beendeten Mitglieder mehr nachgezogen.';
$string['kohortenlink:keine_kohorten'] = 'Es sind keine globalen Gruppen vorhanden. Unter Nutzer/innen ▸ Globale Gruppen zuerst eine anlegen.';
$string['kohortenlink:keine_links'] = 'Es sind noch keine Verknüpfungen mit globalen Gruppen vorhanden.';
$string['kohortenlink:fehler_existiert'] = 'Für diese Kombination aus Gruppe, Berufsbildner/in und Rolle besteht bereits eine Verknüpfung.';

$string['import:titel'] = 'CSV-Import';
$string['import:beschreibung'] = 'Für den Jahreswechsel, wenn ein ganzer Jahrgang neu zugeordnet wird. Erste Zeile als Titel, Spalten: berufsbildner, lernende (je der Moodle-Benutzername), beruf (leer = aus dem Profil), gueltig_von (JJJJ-MM-TT). Übernommen werden nach der Vorschau nur fehlerfreie Zeilen; fehlerhafte werden einzeln gemeldet.';
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
$string['bloecke:einleitung'] = 'Ausbildungsblöcke sind die Stationen des Versetzungsplans. Mit "Kompetenzen zuordnen" wird je Block festgelegt, welche Leistungskriterien dort vermittelt werden - daraus entstehen die Vorbelegungen in den aufsetzenden Plugins und die Lückenanalyse der Lernenden.';
$string['bloecke:neu'] = 'Neuer Block';
$string['bloecke:keine_bloecke'] = 'Es sind noch keine Ausbildungsblöcke vorhanden. Sie werden beim nächsten Versetzungsplan-Import automatisch angelegt, oder hier manuell.';
$string['block:nummer'] = 'Nummer';
$string['block:name'] = 'Bezeichnung';
$string['block:beruf'] = 'Beruf';
$string['block:beruf_leer'] = 'berufsübergreifend';
$string['block:beruf_leer_label'] = 'Berufsübergreifend';
$string['block_beruf'] = 'Beruf';
$string['block_beruf_help'] = 'Bestimmt, aus welchem Kompetenzrahmen bei der Kompetenzabdeckung dieses Blocks ausgewählt werden kann. Leer lassen für berufsübergreifende Blöcke wie Schule, üK, Ferien oder Militär.';
$string['block:ist_betrieb'] = 'Betrieblicher Einsatz';
$string['block_ist_betrieb'] = 'Betrieblicher Einsatz';
$string['block_ist_betrieb_help'] = 'Nur betriebliche Einsätze zählen zur Kompetenzabdeckung. Schulische und überbetriebliche Blöcke (z. B. üK) hier abwählen.';
$string['block:aktiv'] = 'Aktiv';
$string['block:kurs'] = 'Moodle-Kurs';
$string['block:kein_kurs'] = 'Kein Kurs verknüpft';
$string['block_kurs'] = 'Moodle-Kurs';
$string['block_kurs_help'] = 'Optionaler Kurs, der Lernenden während eines aktuellen Einsatzes dieses Blocks in «Meine Lehre» angezeigt wird. Diese Verknüpfung ändert keine Einschreibungen oder Zugriffsrechte.';
$string['block:bearbeiten'] = 'Bearbeiten';
$string['block:anlegen'] = 'Block anlegen';
$string['block:speichern'] = 'Speichern';
$string['block:kompetenzen'] = 'Leistungskriterien';
$string['block:aktionen'] = 'Aktionen';
$string['block:angelegt'] = 'Block gespeichert.';
$string['block:fehler_nummer_existiert'] = 'Ein Block mit dieser Nummer besteht bereits.';
$string['einsatz:kurs_oeffnen'] = 'Kurs «{$a}» öffnen';

$string['blocklk:uebersicht'] = 'Kompetenzabdeckung für Block {$a}';
$string['blocklk:einleitung'] = 'Hier wird festgelegt, welche Kompetenzen in diesem Ausbildungsblock vermittelt werden. Zur Auswahl steht der Kompetenzrahmen, der dem Beruf dieses Blocks zugeordnet ist — gegliedert wie im Bildungsplan.';
$string['blocklk:zuordnen'] = 'Kompetenzen zuordnen';
$string['blocklk:block_bearbeiten'] = 'Block bearbeiten';
$string['blocklk:kompetenz'] = 'Kompetenz';
$string['blocklk:auswahl_hinweis'] = 'Die Auswahl folgt der Gliederung des Bildungsplans. Wählbar ist eine ganze Handlungskompetenz — wenn der Block sie als Ganzes abdeckt — oder einzelne Leistungskriterien darunter. Für die Lückenanalyse ist beides gleichwertig, weil Leistungskriterien ohnehin auf ihre Handlungskompetenz zählen.';
$string['blocklk:ganze_hk'] = 'ganze Handlungskompetenz';
$string['blocklk:lk_aufklappen'] = '{$a} Leistungskriterien einzeln wählen';
$string['blocklk:suche'] = 'Kompetenz suchen';
$string['blocklk:suche_placeholder'] = 'Bezeichnung, Code oder Beschreibung…';
$string['blocklk:suchen'] = 'Suchen';
$string['blocklk:suche_zuruecksetzen'] = 'Filter zurücksetzen';
$string['blocklk:suche_keine_treffer'] = 'Keine Kompetenz gefunden für "{$a}". Gesucht wird in Bezeichnung, ID-Nummer und Beschreibung.';
$string['blocklk:bereits_zugeordnet'] = 'zugeordnet';
$string['blocklk:intensitaet'] = 'Intensität';
$string['blocklk:intensitaet_hinweis'] = '"Schwerpunkt" für Kompetenzen, die in diesem Block hauptsächlich vermittelt werden, "teilweise" für solche, die nur am Rand vorkommen. Die Intensität gilt für alle Kompetenzen, die jetzt gemeinsam hinzugefügt werden — für eine andere Intensität ein zweites Mal zuordnen.';
$string['blocklk:intensitaet_schwerpunkt'] = 'Schwerpunkt';
$string['blocklk:intensitaet_teilweise'] = 'teilweise';
$string['blocklk:hinzufuegen'] = 'Kompetenzen hinzufügen';
$string['blocklk:entfernen'] = 'Entfernen';
$string['blocklk:zugeordnete'] = 'Zugeordnete Kompetenzen ({$a})';
$string['blocklk:keine'] = 'Für diesen Block ist noch keine Kompetenz hinterlegt.';
$string['blocklk:kein_betrieb'] = 'Dieser Block ist nicht als betrieblicher Einsatz markiert. Zugeordnete Kompetenzen zählen deshalb nicht zur Kompetenzabdeckung der Lernenden.';
$string['blocklk:alle_zugeordnet'] = 'Alle Kompetenzen dieses Kompetenzrahmens sind diesem Block bereits zugeordnet.';
$string['blocklk:fehler_keine_auswahl'] = 'Mindestens eine Kompetenz auswählen.';
$string['blocklk:kompetenzen_aus'] = 'Die Kompetenzverwaltung ist auf dieser Moodle-Installation ausgeschaltet. Ohne sie gibt es keine Leistungskriterien, die einem Block zugeordnet werden könnten.';
$string['blocklk:kompetenzen_einschalten'] = 'Erweiterte Funktionen öffnen';
$string['blocklk:kein_beruf'] = 'Für diesen Block ist kein Beruf hinterlegt. Erst mit einem Beruf ist klar, aus welchem Kompetenzrahmen ausgewählt wird. Berufsübergreifende Blöcke wie Schule, üK oder Ferien brauchen keine Zuordnung.';
$string['blocklk:beruf_setzen'] = 'Beruf beim Block setzen';
$string['blocklk:kein_rahmen'] = 'Für den Beruf "{$a}" ist kein Kompetenzrahmen konfiguriert. Die Zuordnung ist erst möglich, wenn dem Beruf auf der Seite "Kompetenzrahmen je Beruf" ein Rahmen zugewiesen ist.';
$string['blocklk:rahmen_leer'] = 'Der Kompetenzrahmen "{$a}" enthält noch keine Kompetenzen. Er muss zuerst in der Kompetenzverwaltung von Moodle gefüllt werden.';
$string['blocklk:hinzugefuegt'] = 'Kompetenzabdeckung gespeichert ({$a} neu zugeordnet).';
$string['blocklk:entfernt'] = 'Leistungskriterium entfernt.';
$string['blocklk:zurueck'] = 'Zurück zu den Ausbildungsblöcken';

$string['berufrahmen:uebersicht'] = 'Kompetenzrahmen je Beruf';
$string['berufrahmen:einleitung'] = 'Ordnet jedem Beruf seinen Kompetenzrahmen zu. Er bestimmt, welche Leistungskriterien ein Ausbildungsblock anbietet und welche Lücken erscheinen; ein Beruf ohne Eintrag hat keinen Rahmen. Ein Rahmen nur mit den betrieblichen Kompetenzbereichen genügt. Den Lernenden muss er nicht zugewiesen werden - das geschieht beim ersten Kompetenznachweis automatisch.';
$string['berufrahmen:beruf'] = 'Beruf';
$string['berufrahmen_beruf'] = 'Beruf';
$string['berufrahmen_beruf_help'] = 'Die Liste kommt aus dem Profilfeld "Beruf": bei einem Auswahlfeld dessen Optionen, sonst die Werte, die im Profil tatsächlich vorkommen. Bereits zugeordnete Berufe fehlen - deren Rahmen wechselt man über "Entfernen".';
$string['berufrahmen:rahmen'] = 'Kompetenzrahmen';
$string['berufrahmen:hinzufuegen'] = 'Zuordnung hinzufügen';
$string['berufrahmen:entfernen'] = 'Entfernen';
$string['berufrahmen:keine'] = 'Es ist noch kein Beruf einem Kompetenzrahmen zugeordnet.';
$string['berufrahmen:keine_rahmen'] = 'Es sind keine Kompetenzrahmen vorhanden - core_competency zuerst einrichten.';
$string['berufrahmen:keine_berufe'] = 'Es steht kein Beruf zur Auswahl: im Profilfeld "Beruf" ist noch keiner hinterlegt, oder alle vorkommenden Berufe haben schon einen Rahmen.';
$string['berufrahmen:fehler_existiert'] = 'Dieser Beruf ist bereits einem Rahmen zugeordnet - zuerst entfernen, um die Zuordnung zu ändern.';
$string['berufrahmen:hinzugefuegt'] = 'Zuordnung gespeichert.';
$string['berufrahmen:entfernt'] = 'Zuordnung entfernt.';

$string['planimport:titel'] = 'Versetzungsplan-Import';
$string['planimport:beschreibung'] = 'Rückfallweg, falls der wöchentliche Webservice-Import nicht läuft oder ausserplanmässig etwas nachgezogen werden muss. Erwartet dieselbe CSV wie der Webservice (siehe docs/schnittstelle_versetzungsplan.md).';
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
$string['privacy:metadata:kohortenlink'] = 'Für jede laufende Verknüpfung mit einer globalen Gruppe wird gespeichert, welche/r Berufsbildner/in mit welcher globalen Gruppe synchronisiert wird.';
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
$string['privacy:pfad_kohortenlinks'] = 'Verknüpfungen mit globalen Gruppen';
$string['privacy:pfad_einsaetze'] = 'Betriebliche Einsätze';
$string['privacy:pfad_planimporte'] = 'Ausgeführte Versetzungsplan-Importe';
$string['privacy:pfad_aufbewahrung'] = 'Dokumentierte Aufbewahrungspflichten';
