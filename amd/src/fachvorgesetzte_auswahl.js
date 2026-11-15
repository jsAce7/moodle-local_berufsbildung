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
 * Personensuche fuer das Feld "Fachvorgesetzte" eines Ausbildungsblocks -
 * Transport fuer das Ajax-Autocomplete von moodleform.
 *
 * Eigene Suche statt core_user/form_user_selector: jene verlangt
 * moodle/user:viewalldetails, das die Ausbildungsplanung nicht haben soll.
 *
 * @module     local_berufsbildung/fachvorgesetzte_auswahl
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/**
 * Sucht Personen.
 *
 * @param {string} selector Selektor des Felds
 * @param {string} query Suchtext
 * @param {Function} success Erfolg
 * @param {Function} failure Fehler
 */
export const transport = (selector, query, success, failure) => {
    if (query.trim().length < 2) {
        success([]);
        return;
    }
    Ajax.call([{
        methodname: 'local_berufsbildung_fachvorgesetzte_suchen',
        args: {suche: query},
    }])[0].then(success).catch(failure);
};

/**
 * Macht aus den Treffern die Optionen des Felds.
 *
 * @param {string} selector Selektor des Felds
 * @param {Array} treffer Aus dem Webservice
 * @returns {Array}
 */
export const processResults = (selector, treffer) => treffer.map((person) => ({
    value: person.id,
    label: `${person.name} (${person.email})`,
}));
