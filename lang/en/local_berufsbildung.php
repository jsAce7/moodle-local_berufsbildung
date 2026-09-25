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
 * Language file (English).
 *
 * @package    local_berufsbildung
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Vocational training';
$string['settings:einstellungen'] = 'Settings';
$string['admin:uebersicht'] = 'Vocational training administration';
$string['admin:organisation'] = 'Assignments and groups';
$string['admin:planung'] = 'Training planning';
$string['admin:lernbegleitung'] = 'Learning support';
$string['admin:system'] = 'Settings and data protection';
$string['berufsbildung:managezuordnung'] = 'Manage assignments between trainers and trainees';
$string['berufsbildung:viewzuordnung'] = 'View the assignment overview without changing it';
$string['settings:profilefield_beruf'] = 'Profile field: occupation';
$string['settings:profilefield_beruf_desc'] = 'Custom profile field holding the occupation (e.g. \'AU_EFZ\').';
$string['settings:profilefield_jahrgang'] = 'Profile field: cohort year';
$string['settings:profilefield_jahrgang_desc'] = 'Custom profile field holding the year training started. "2026" is enough; a combined field such as "AU 2026" is recognised too — only the year is read from it.';
$string['settings:profilefield_none'] = '— no custom profile fields exist yet —';
$string['settings:startmonat'] = 'Training start month';
$string['settings:startmonat_desc'] = 'Month in which all training programmes start (1 = January, 12 = December). Default: 8 (August).';
$string['settings:lehrdauer_semester'] = 'Training length in semesters (default)';
$string['settings:lehrdauer_semester_desc'] = 'Number of semesters until completion, unless overridden for a specific occupation below under "Training length per occupation". Default: 8 (four years).';
$string['settings:beruf_dauer'] = 'Training length per occupation';
$string['settings:beruf_dauer_desc'] = 'Overrides the training length for individual occupations, one line per occupation in the format CODE=semesters, e.g. "PM_EFZ=6" for a three-year programme. Occupations not listed here use the default length above.';
$string['settings:beruf_wahlpflicht_hk'] = 'Elective competency areas per occupation';
$string['settings:beruf_wahlpflicht_hk_desc'] = 'These competencies are not shown as missing in the gap analysis and appear green instead of yellow in the competency grid. One line per occupation in the format CODE=COMPETENCY-ID,COMPETENCY-ID, e.g. "AU_EFZ=a.04,a.05". Use the idnumber of a competency, not of a top-level area. The framework prefix may be left out: "a.04" also matches a competency whose idnumber is "7777BE a.04".';
$string['settings:versetzungsplan_schwelle_prozent'] = 'Threshold for incomplete deliveries (%)';
$string['settings:versetzungsplan_schwelle_prozent_desc'] = 'If a delivery contains significantly fewer people than the previous one, it is rejected instead of processed. Default: 20 (a drop of more than 20% counts as incomplete).';
$string['settings:versetzungsplan_alterung_tage'] = 'Aging warning after (days)';
$string['settings:versetzungsplan_alterung_tage_desc'] = 'If the last successful rotation-plan import is older than this, a warning is shown. Not a block, just a reminder that pre-filled data may be based on a stale plan.';
$string['settings:retention_monate'] = 'Retention period after training completion (months)';
$string['settings:retention_monate_desc'] = 'How many months after the computed completion date a person\'s assignments are deleted for good — and with them their learning journal. Exception: a documented retention obligation. Default: 12.';
$string['role:berufsbildner'] = 'Trainer';
$string['role:berufsbildner_desc'] = 'Assigned in a trainee\'s user context once an assignment exists. Carries no capabilities of its own - those come from the plugins built on top.';
$string['role:planung'] = 'Training planning';
$string['role:planung_desc'] = 'Site-wide role for maintaining the training blocks and their competency coverage. Assigned automatically to every person who has at least one current assignment as a trainer, and withdrawn again as soon as the last of them has ended. Deliberately separate from the "Trainer" role: that one hangs off an individual trainee\'s user context, stays in place after the assignment ends so historical access can still be checked against a reference date, and must not carry site-wide rights.';

$string['nav:meine_lehre'] = 'My apprenticeship';
$string['nav:meine_lernenden'] = 'My trainees';
$string['teilnahmeart:uek_extern'] = 'External · inter-company courses only';
$string['teilnahmeart:uek_extern_setzen'] = 'Mark as external (inter-company courses only)';
$string['teilnahmeart:lehre_setzen'] = 'Manage as regular apprenticeship';
$string['teilnahmeart:gespeichert'] = 'Participation type saved.';
$string['faelligkeiten:titel'] = 'Due dates';
$string['faelligkeiten:ich'] = 'Me';
$string['faelligkeiten:leer'] = 'There are currently no due dates.';
$string['faelligkeiten:ueberfaellig'] = 'Overdue';
$string['faelligkeiten:ueberfaellig_anzahl'] = '{$a} overdue';
$string['form:ausbildungsstand'] = '{$a->beruf}, year {$a->lehrjahr} (semester {$a->semester})';
$string['form:keine_taetigkeiten'] = 'No activities recorded.';
$string['luecken:titel'] = 'Required competency areas not yet trained';
$string['luecken:titel_anzahl'] = '{$a} still open';
$string['luecken:keine'] = 'All required competency areas in the framework are already covered.';
$string['meine_lernenden:keine_lernenden'] = 'You currently have no assigned trainees.';
$string['meine_lernenden:keine_treffer'] = 'No trainees found. Please adjust the search or profession filter.';
$string['meine_lernenden:taetigkeiten_anzahl'] = '{$a} activities';
$string['meine_lernenden:profil_oeffnen'] = 'Open profile and learning journal';
$string['meine_lernenden:suche_placeholder'] = 'Search by name…';
$string['meine_lernenden:beruf_alle'] = 'All professions';
$string['meine_lernenden:filtern'] = 'Filter';
$string['meine_lernenden:zusammenfassung'] = '{$a->anzahl} trainees · {$a->luecken} with open gaps';
$string['meine_lernenden:bloecke'] = 'Manage training blocks';
$string['meine_lehre:kein_ausbildungsstand'] = 'No occupation or cohort year is set on your profile.';
$string['meine_lehre:uek_extern'] = 'You participate externally in inter-company courses only. This site does not manage an apprenticeship or learning journal for you.';
$string['meine_lehre:vor_beginn'] = 'Your apprenticeship starts on {$a}.';
$string['meine_lehre:abgeschlossen'] = '{$a->beruf} — training completed on {$a->datum}.';
$string['luecken:zusammenfassung'] = '{$a->abgedeckt} of {$a->soll} covered';
$string['luecken:bereich_abdeckung'] = '{$a->abgedeckt} of {$a->soll}';
$string['raster:titel'] = 'Competency areas';
$string['raster:zusammenfassung'] = '{$a->abgedeckt} of {$a->soll} required competency areas are covered by the plan';
$string['raster:bereich_abdeckung'] = '{$a->abgedeckt} of {$a->soll}';
$string['raster:horizont'] = 'The rotation plan on file runs until {$a}.';
$string['raster:pflicht'] = 'Required';
$string['raster:wahlpflicht'] = 'Elective';
$string['raster:status_abgedeckt'] = 'already encountered';
$string['raster:status_eingeplant'] = 'scheduled later';
$string['raster:status_offen'] = 'not in the plan';
$string['raster:legende_pflicht'] = 'Required competency area';
$string['raster:legende_wahlpflicht'] = 'Elective competency area';
$string['raster:legende_abgedeckt'] = 'already occurred in a placement';
$string['raster:legende_eingeplant'] = 'scheduled later in the plan on file';
$string['raster:legende_offen'] = 'no marker: not contained in the plan on file';
$string['stepper:lehrjahr'] = 'Year {$a}';
$string['einsatz:zeitraum'] = '{$a->von} to {$a->bis}';
$string['einsatz:kw'] = 'Week {$a}';
$string['einsatz:kw_spanne'] = 'Weeks {$a->von}–{$a->bis}';
$string['einsatz:aktuell'] = 'current';
$string['einsatz:aktuell_titel'] = 'Current placement';
$string['einsatz:timeline_titel'] = 'Rotation plan';
$string['einsatz:gruppe_anzahl'] = '{$a} placements';
$string['einsatz:gruppe_anzahl_eins'] = '1 placement';
$string['einsatz:unbekannter_block'] = 'Unnamed training block';
$string['nachweis:titel'] = 'Activities';
$string['nachweis:semester'] = 'Semester {$a}';
$string['nachweis:ohne_semester'] = 'Outside the apprenticeship period';
$string['error:keinezustaendigkeit'] = 'Not responsible for this trainee.';
$string['error:aufbewahrunggrundleer'] = 'The retention obligation needs a reason.';

$string['zuordnung:uebersicht'] = 'Assignments';
$string['zuordnung:neue_zuordnung'] = 'New assignment';
$string['zuordnung:anlegen'] = 'Create assignment';
$string['zuordnung:beenden'] = 'End assignment';
$string['zuordnung:berufsbildner'] = 'Trainer';
$string['zuordnung:lernende'] = 'Trainees';
$string['zuordnung_lernende'] = 'Trainees';
$string['zuordnung_lernende_help'] = 'Multiple selection possible. An assignment already running with another trainer is ended automatically on save, on the day before the "valid from" date - not overwritten.';
$string['zuordnung:kohorten'] = 'Cohorts';
$string['zuordnung_kohorten'] = 'Cohorts';
$string['zuordnung_kohorten_help'] = 'All members of the selected cohorts are assigned as well - as a one-time snapshot. Later changes to the cohort have no effect; use a cohort link for that.';
$string['zuordnung:beruf'] = 'Occupation';
$string['zuordnung_beruf'] = 'Occupation';
$string['zuordnung_beruf_help'] = 'Leave empty: the occupation comes from the profile, resolved separately for each person. Only needed if the profile holds no occupation, or a different one should apply here.';
$string['zuordnung:rolle'] = 'Role';
$string['zuordnung:herkunft'] = 'Origin';
$string['zuordnung:herkunft_manuell'] = 'Manual';
$string['zuordnung:herkunft_kohorte'] = 'Cohort: {$a}';
$string['zuordnung_rolle'] = 'Role';
$string['zuordnung_rolle_help'] = 'Within a role, a trainee only ever has one running assignment - a new one with the same role ends the previous one automatically. Different roles may run at the same time.

"Hauptverantwortlich" (primary) is the normal case. "Stellvertretung" (deputy) is an additional responsibility for the same person, e.g. during a holiday absence.

Both roles are equivalent for visibility today; the distinction is purely organisational.';
$string['zuordnung:rolle_hauptverantwortlich'] = 'Hauptverantwortlich (primary)';
$string['zuordnung:rolle_stellvertretung'] = 'Stellvertretung (deputy)';
$string['zuordnung:gueltig_von'] = 'Valid from';
$string['zuordnung:gueltig_bis'] = 'Valid until';
$string['zuordnung:fehler_gleiche_person'] = 'Trainer and trainee cannot be the same person.';
$string['zuordnung:fehler_keine_lernenden'] = 'Select at least one trainee or cohort.';
$string['zuordnung:fehler_enddatum'] = 'The end date must not be before the start date.';
$string['zuordnung:fehler_ueberschneidung'] = 'This assignment would overlap an existing assignment with the same role.';
$string['zuordnung:loeschen'] = 'Delete assignment';
$string['zuordnung:geloescht'] = 'The incorrect assignment has been deleted.';
$string['zuordnung:loeschen_bestaetigung'] = 'The assignment “{$a}” will be permanently deleted. This is intended only for incorrectly recorded assignments. Continue?';
$string['zuordnung:angelegt'] = '{$a} assignment(s) created.';
$string['zuordnung:beendet_erfolgreich'] = 'Assignment ended.';
$string['zuordnung:wiedereroeffnet'] = 'Assignment is active again.';
$string['zuordnung:beenden_beschreibung'] = 'Edit the "valid until" date for {$a->berufsbildner} and {$a->lernende}. Leave it empty to make the assignment active (again).';
$string['zuordnung:bearbeiten'] = 'Edit end date';
$string['zuordnung:speichern'] = 'Save';
$string['zuordnung_gueltig_bis'] = 'Valid until';
$string['zuordnung_gueltig_bis_help'] = 'Leave empty (checkbox off) to make an ended assignment active again - use this to correct a wrongly set end date without having to create the assignment anew.';
$string['zuordnung:status'] = 'Status';
$string['zuordnung:status_laufend'] = 'active';
$string['zuordnung:status_beendet'] = 'ended';
$string['zuordnung:lehrjahr'] = 'Training year';

$string['task:sync_kohorten'] = 'Synchronise cohort assignments';
$string['task:sync_role_assignments'] = 'Synchronise trainer role assignments';
$string['task:zuordnung_retention'] = 'Delete assignments after the retention period expires';
$string['kohortenlink:uebersicht'] = 'Cohort links';
$string['kohortenlink:neu'] = 'New link';
$string['kohortenlink:anlegen'] = 'Create link';
$string['kohortenlink:kohorte'] = 'Cohort';
$string['kohortenlink:beschreibung'] = 'All current and future members of the selected cohort are continuously assigned to this trainer. The hourly sync picks up new and departed members - unlike the one-time selection on the assignments page.';
$string['kohortenlink:angelegt'] = 'Link created. {$a->erzeugt} assignment(s) created immediately.';
$string['kohortenlink:aktiv'] = 'Active';
$string['kohortenlink:status_aktiv'] = 'active';
$string['kohortenlink:status_inaktiv'] = 'inactive';
$string['kohortenlink:deaktivieren'] = 'Deactivate';
$string['kohortenlink:aktivieren'] = 'Activate';
$string['kohortenlink:loeschen'] = 'Delete';
$string['kohortenlink:loeschen_bestaetigung'] = 'The cohort link with ID {$a} will be permanently deleted. Continue?';
$string['kohortenlink:geloescht'] = 'Cohort link deleted.';
$string['kohortenlink:loeschen_mit_zuordnungen'] = 'This link has already created assignments and therefore cannot be deleted. Deactivate it instead, so the origin of those assignments is preserved.';
$string['kohortenlink:deaktiviert_hinweis'] = 'Deactivated: existing assignments are left untouched, new or departed members are simply no longer tracked.';
$string['kohortenlink:keine_kohorten'] = 'No cohorts exist yet. Create one under Users ▸ Cohorts first.';
$string['kohortenlink:keine_links'] = 'No cohort links exist yet.';
$string['kohortenlink:fehler_existiert'] = 'A link for this combination of cohort, trainer and role already exists.';

$string['import:titel'] = 'CSV import';
$string['import:beschreibung'] = 'For the school-year changeover, when a whole cohort year is reassigned. First row as header, columns: berufsbildner, lernende (each the Moodle username), beruf (empty = from profile), gueltig_von (YYYY-MM-DD). After the preview, only error-free rows are imported; rows with errors are reported individually.';
$string['import:datei'] = 'CSV file';
$string['import:trennzeichen'] = 'Delimiter';
$string['import:kodierung'] = 'Encoding';
$string['import:vorschau_anzeigen'] = 'Show preview';
$string['import:vorschau'] = 'Import preview';
$string['import:vorschau_fehlerhinweis'] = '{$a} row(s) with errors - these will be skipped on confirmation, the rest will be imported.';
$string['import:zeile'] = 'Row';
$string['import:status'] = 'Status';
$string['import:zeile_ok'] = 'ready';
$string['import:fehler'] = 'Error';
$string['import:bestaetigen'] = 'Confirm import';
$string['import:abbrechen'] = 'Cancel';
$string['import:abgeschlossen'] = '{$a->angelegt} assignment(s) created, {$a->fehler} row(s) skipped.';
$string['import:keine_zeilen'] = 'The file contains no data rows.';
$string['import:fehler_datei'] = 'The file could not be read: {$a}';
$string['import:fehler_abgelaufen'] = 'The import has expired or was already completed. Please upload the file again.';
$string['import:fehlende_spalten'] = 'Missing column(s) in the first row: {$a}';
$string['import:fehler_berufsbildner_fehlt'] = 'Trainer is missing.';
$string['import:fehler_lernende_fehlt'] = 'Trainee is missing.';
$string['import:fehler_person_nicht_gefunden'] = 'No user account found with the username "{$a}".';
$string['import:fehler_datum'] = 'Invalid date "{$a}" (expected: YYYY-MM-DD).';

$string['berufsbildung:manageblocks'] = 'Manage training blocks and their competency coverage';
$string['berufsbildung:importplan'] = 'Import the rotation plan';
$string['berufsbildung:manageaufbewahrung'] = 'Manage documented retention obligations';

$string['bloecke:uebersicht'] = 'Training blocks';
$string['bloecke:einleitung'] = 'Training blocks are the stations of the rotation plan. Use "Assign competencies" to define which performance criteria are taught in each block - this drives the pre-selections in the plugins built on top and the learners\' gap analysis.';
$string['bloecke:neu'] = 'New block';
$string['bloecke:keine_bloecke'] = 'No training blocks exist yet. They are created automatically on the next rotation-plan import, or manually here.';
$string['block:nummer'] = 'Number';
$string['block:name'] = 'Name';
$string['block:beruf'] = 'Occupation';
$string['block:beruf_leer'] = 'cross-occupation';
$string['block:beruf_leer_label'] = 'Cross-occupation';
$string['block_beruf'] = 'Occupation';
$string['block_beruf_help'] = 'Determines which competency framework can be picked from when assigning competencies to this block. Leave empty for cross-occupation blocks such as school, inter-company courses, holidays or military service.';
$string['block:ist_betrieb'] = 'Company placement';
$string['block_ist_betrieb'] = 'Company placement';
$string['block_ist_betrieb_help'] = 'Only company placements count towards competency coverage. Deselect this for school and inter-company blocks (e.g. üK).';
$string['block:aktiv'] = 'Active';
$string['block:kurs'] = 'Moodle course';
$string['block:kein_kurs'] = 'No course linked';
$string['block_kurs'] = 'Moodle course';
$string['block_kurs_help'] = 'Optional course shown in "My apprenticeship" while a learner is in a current placement for this block. This link does not change enrolments or access rights.';
$string['block:bearbeiten'] = 'Edit';
$string['block:anlegen'] = 'Create block';
$string['block:speichern'] = 'Save';
$string['block:kompetenzen'] = 'Performance criteria';
$string['block:aktionen'] = 'Actions';
$string['block:angelegt'] = 'Block saved.';
$string['block:fehler_nummer_existiert'] = 'A block with this number already exists.';
$string['einsatz:kurs_oeffnen'] = 'Open course "{$a}"';

$string['blocklk:uebersicht'] = 'Competency coverage for block {$a}';
$string['blocklk:einleitung'] = 'This is where you define which competencies are taught in this training block. The choices offered come from the competency framework assigned to this block\'s occupation, laid out as in the official curriculum.';
$string['blocklk:zuordnen'] = 'Assign competencies';
$string['blocklk:block_bearbeiten'] = 'Edit block';
$string['blocklk:kompetenz'] = 'Competency';
$string['blocklk:auswahl_hinweis'] = 'The selection follows the structure of the official curriculum. You can pick a whole competency area — if the block covers it as a whole — or individual performance criteria below it. For the gap analysis both are equivalent, because performance criteria count towards their competency area anyway.';
$string['blocklk:ganze_hk'] = 'whole competency area';
$string['blocklk:lk_aufklappen'] = 'Pick from {$a} performance criteria';
$string['blocklk:suche'] = 'Search competencies';
$string['blocklk:suche_placeholder'] = 'Name, code or description…';
$string['blocklk:suchen'] = 'Search';
$string['blocklk:suche_zuruecksetzen'] = 'Clear filter';
$string['blocklk:suche_keine_treffer'] = 'No competency found. The search covers name, ID number and description.';
$string['blocklk:bereits_zugeordnet'] = 'assigned';
$string['blocklk:intensitaet'] = 'Intensity';
$string['blocklk:intensitaet_hinweis'] = 'Use "main focus" for competencies mainly taught in this block and "partial" for those only touched on. The intensity applies to all competencies added together now — assign a second time for a different intensity.';
$string['blocklk:intensitaet_schwerpunkt'] = 'main focus';
$string['blocklk:intensitaet_teilweise'] = 'partial';
$string['blocklk:hinzufuegen'] = 'Add competencies';
$string['blocklk:entfernen'] = 'Remove';
$string['blocklk:zugeordnete'] = 'Assigned competencies ({$a})';
$string['blocklk:keine'] = 'No competency has been assigned to this block yet.';
$string['blocklk:kein_betrieb'] = 'This block is not marked as a company placement. Competencies assigned here therefore do not count towards the learners\' competency coverage.';
$string['blocklk:alle_zugeordnet'] = 'All competencies of this competency framework are already assigned to this block.';
$string['blocklk:fehler_keine_auswahl'] = 'Select at least one competency.';
$string['blocklk:kompetenzen_aus'] = 'Competency management is switched off on this Moodle site. Without it there are no performance criteria that could be assigned to a block.';
$string['blocklk:kompetenzen_einschalten'] = 'Open advanced features';
$string['blocklk:kein_beruf'] = 'No occupation is set for this block. Only with an occupation is it clear which competency framework to pick from. Cross-occupation blocks such as school, inter-company courses or holidays need no assignment.';
$string['blocklk:beruf_setzen'] = 'Set the occupation on the block';
$string['blocklk:kein_rahmen'] = 'No competency framework is configured for occupation "{$a}". Assignment is only possible once the occupation has a framework on the "Competency framework per occupation" page.';
$string['blocklk:rahmen_leer'] = 'The competency framework "{$a}" does not contain any competencies yet. It has to be filled in Moodle\'s competency management first.';
$string['blocklk:hinzugefuegt'] = 'Competency coverage saved ({$a} newly assigned).';
$string['blocklk:entfernt'] = 'Performance criterion removed.';
$string['blocklk:zurueck'] = 'Back to training blocks';

$string['berufrahmen:uebersicht'] = 'Competency framework per occupation';
$string['berufrahmen:einleitung'] = 'Maps each occupation to its competency framework. It decides which performance criteria a training block offers and which gaps appear; an occupation without an entry has no framework. A framework holding only the workplace competency areas is sufficient. It does not need to be assigned to trainees - that happens automatically with the first piece of evidence.';
$string['berufrahmen:beruf'] = 'Occupation';
$string['berufrahmen_beruf'] = 'Occupation';
$string['berufrahmen_beruf_help'] = 'The list comes from the "Occupation" profile field: its options if it is a menu field, otherwise the values that actually occur in profiles. Occupations already mapped are left out - change their framework via "Remove".';
$string['berufrahmen:rahmen'] = 'Competency framework';
$string['berufrahmen:hinzufuegen'] = 'Add mapping';
$string['berufrahmen:entfernen'] = 'Remove';
$string['berufrahmen:keine'] = 'No occupation is mapped to a competency framework yet.';
$string['berufrahmen:keine_rahmen'] = 'No competency frameworks exist - set up core_competency first.';
$string['berufrahmen:keine_berufe'] = 'There is no occupation to choose from: none is stored in the "Occupation" profile field yet, or every occupation that occurs already has a framework.';
$string['berufrahmen:fehler_existiert'] = 'This occupation is already mapped to a framework - remove it first to change the mapping.';
$string['berufrahmen:hinzugefuegt'] = 'Mapping saved.';
$string['berufrahmen:entfernt'] = 'Mapping removed.';

$string['planimport:titel'] = 'Rotation-plan import';
$string['planimport:beschreibung'] = 'Fallback if the weekly webservice import is not running, or if something needs to be applied outside the schedule. Expects the same CSV as the webservice (see docs/schnittstelle_versetzungsplan.md).';
$string['planimport:datei'] = 'CSV file';
$string['planimport:testlauf'] = 'Dry run (only check, write nothing)';
$string['planimport:rueckgang_bestaetigt'] = 'Confirm drop (override the incomplete-delivery guard)';
$string['planimport:hochladen'] = 'Upload';
$string['planimport:ergebnis'] = 'Result';
$string['planimport:status'] = 'Status';
$string['planimport:status_ok'] = 'ok';
$string['planimport:status_mit_warnungen'] = 'with warnings';
$string['planimport:status_abgewiesen'] = 'rejected';
$string['planimport:status_fehlgeschlagen'] = 'failed';
$string['planimport:unveraendert'] = 'Unchanged compared to the last successful import - nothing was written.';
$string['planimport:zeilen_gelesen'] = 'Rows read';
$string['planimport:personen_verarbeitet'] = 'People processed';
$string['planimport:ausserhalb_geltungsbereich'] = 'Without an active assignment (skipped, not logged)';
$string['planimport:einsaetze_erzeugt'] = 'Placements created';
$string['planimport:protokoll'] = 'Log';
$string['planimport:abgewiesen_hinweis'] = 'This delivery was rejected because significantly fewer people were processed than in the last successful import. If intended, confirm below and upload again.';
$string['planimport:letzter_import'] = 'Last successful import';
$string['planimport:noch_nie'] = 'never';
$string['planimport:alterung_hinweis'] = 'The last successful rotation-plan import was more than {$a} days ago. The pre-filled data in the education report may be based on a stale plan.';
$string['planimport:fehler_datei'] = 'The file could not be read: {$a}';

$string['aufbewahrung:uebersicht'] = 'Retention obligations';
$string['aufbewahrung:neu'] = 'New retention obligation';
$string['aufbewahrung:bearbeiten'] = 'Edit';
$string['aufbewahrung:lernende'] = 'Trainee';
$string['aufbewahrung:grund'] = 'Reason';
$string['aufbewahrung:gueltig_von'] = 'Valid from';
$string['aufbewahrung:gueltig_bis'] = 'Valid until';
$string['aufbewahrung:unbefristet'] = 'indefinite';
$string['aufbewahrung:keine_eintraege'] = 'No retention obligations are documented.';
$string['aufbewahrung:speichern'] = 'Save';
$string['aufbewahrung:gespeichert'] = 'Retention obligation saved.';

$string['ws:import_versetzungsplan'] = 'Import rotation plan';
$string['ws:import_versetzungsplan_desc'] = 'Accepts a rotation-plan CSV and processes it per docs/schnittstelle_versetzungsplan.md.';

$string['privacy:metadata:zuordnung'] = 'For each assignment between a trainer and a trainee, who is responsible for whom and for which period is stored.';
$string['privacy:metadata:zuordnung:berufsbildnerid'] = 'The trainer of this assignment.';
$string['privacy:metadata:zuordnung:lernendeid'] = 'The trainee of this assignment.';
$string['privacy:metadata:zuordnung:beruf'] = 'The occupation this assignment applies to.';
$string['privacy:metadata:zuordnung:rolle'] = 'The role within the assignment, e.g. hauptverantwortlich.';
$string['privacy:metadata:zuordnung:gueltig_von'] = 'Start of the assignment.';
$string['privacy:metadata:zuordnung:gueltig_bis'] = 'End of the assignment, if ended.';
$string['privacy:metadata:kohortenlink'] = 'For each ongoing cohort link, which trainer is synchronised with which cohort is stored.';
$string['privacy:metadata:kohortenlink:berufsbildnerid'] = 'The trainer of this link.';
$string['privacy:metadata:kohortenlink:rolle'] = 'The role used to create assignments from this link.';
$string['privacy:metadata:kohortenlink:beruf'] = 'The occupation used to create assignments from this link.';
$string['privacy:metadata:einsatz'] = 'For each company placement from the rotation plan, which person was in which training block and when is stored.';
$string['privacy:metadata:einsatz:userid'] = 'The trainee of this placement.';
$string['privacy:metadata:einsatz:von'] = 'Start of the placement.';
$string['privacy:metadata:einsatz:bis'] = 'End of the placement.';
$string['privacy:metadata:planimport'] = 'For each rotation-plan import run, who executed it is logged.';
$string['privacy:metadata:planimport:ausgefuehrt_von'] = 'The account that executed the import (service account or manual upload).';
$string['privacy:metadata:planimport:quelle'] = 'Origin of the import (webservice or manual upload).';
$string['privacy:metadata:planimport:zeitpunkt'] = 'Time of the import.';
$string['privacy:metadata:aufbewahrung'] = 'For each documented retention obligation, the affected person, the reason, and the period it applies to are stored.';
$string['privacy:metadata:aufbewahrung:lernendeid'] = 'The affected trainee.';
$string['privacy:metadata:aufbewahrung:grund'] = 'The reason for the retention obligation.';
$string['privacy:metadata:aufbewahrung:gueltig_von'] = 'Start of the retention obligation.';
$string['privacy:metadata:aufbewahrung:gueltig_bis'] = 'End of the retention obligation, if time-limited.';
$string['privacy:pfad_zuordnungen_lernende'] = 'Assignments as trainee';
$string['privacy:pfad_zuordnungen_berufsbildner'] = 'Assignments as trainer';
$string['privacy:pfad_kohortenlinks'] = 'Cohort links';
$string['privacy:pfad_einsaetze'] = 'Company placements';
$string['privacy:pfad_planimporte'] = 'Executed rotation-plan imports';
$string['privacy:pfad_aufbewahrung'] = 'Documented retention obligations';
