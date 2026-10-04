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
 * Live-Suche in der Kompetenzauswahl eines Ausbildungsblocks.
 *
 * Eine Ergaenzung, keine Voraussetzung: ohne dieses Skript bleibt das
 * Suchformular ein gewoehnlicher GET-Filter, den der Server auswertet
 * (kompetenz_auswahl::filtere()). Mit Skript entfaellt der Seitenaufbau,
 * und angekreuzte Kompetenzen bleiben ueber mehrere Suchen hinweg stehen -
 * beim Serverfilter gehen sie mit jedem Neuladen verloren.
 *
 * Gesucht wird in data-suchtext, nicht im sichtbaren Text: dort ist die
 * Beschreibung auf 120 Zeichen gekuerzt. Das Attribut traegt denselben
 * Text, den auch der Serverfilter durchsucht.
 *
 * amd/build entsteht mit "npx grunt amd --root=local/berufsbildung" aus dem
 * Moodle-Verzeichnis; die CI prueft mit "moodle-plugin-ci grunt", dass er
 * zu amd/src passt.
 *
 * @module     local_berufsbildung/kompetenz_suche
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Verzoegerung in Millisekunden, bevor getippter Text filtert. */
const TIPPPAUSE = 120;

/**
 * Blendet ein Element ein oder aus.
 *
 * Ueber d-none statt des hidden-Attributs: Bootstrap setzt fuer manche
 * Elemente ein display, das [hidden] ueberschreibt.
 *
 * @param {Element} element
 * @param {boolean} zeigen
 */
const umschalten = (element, zeigen) => {
    element.classList.toggle('d-none', !zeigen);
};

/**
 * Merkt sich den anfaenglichen Aufklapp-Zustand, damit er beim Leeren
 * des Suchfelds wiederhergestellt werden kann.
 *
 * @param {Element} aufklapper
 */
const zustandMerken = (aufklapper) => {
    if (aufklapper.dataset.offenInitial === undefined) {
        aufklapper.dataset.offenInitial = aufklapper.open ? '1' : '0';
    }
};

/**
 * Haengt die Live-Suche an, wenn beide Formulare vorhanden sind.
 */
export const init = () => {
    const suchformular = document.querySelector('[data-region="berufsbildung-auswahl-suche"]');
    const auswahl = document.querySelector('[data-region="berufsbildung-auswahl"]');
    if (!suchformular || !auswahl) {
        return;
    }

    const feld = suchformular.querySelector('input[name="suche"]');
    if (!feld) {
        return;
    }

    const absenden = suchformular.querySelector('[data-region="suche-absenden"]');
    const keinetreffer = document.querySelector('[data-region="berufsbildung-auswahl-keine-treffer"]');
    const bereiche = Array.from(auswahl.querySelectorAll('[data-region="bereich"]'));

    bereiche.forEach((bereich) => {
        zustandMerken(bereich);
        bereich.querySelectorAll('[data-region="lk-aufklapper"]').forEach(zustandMerken);
    });

    /**
     * Blendet Bereiche, Handlungskompetenzen und Leistungskriterien nach
     * dem Suchtext ein oder aus.
     */
    const filtern = () => {
        const nadel = feld.value.trim().toLowerCase();
        const suchaktiv = nadel !== '';
        let sichtbar = 0;

        bereiche.forEach((bereich) => {
            let imBereich = 0;

            bereich.querySelectorAll('[data-region="hk"]').forEach((hk) => {
                // Trifft die Handlungskompetenz selbst, gehoeren alle ihre
                // Leistungskriterien dazu - dieselbe Regel wie im Serverfilter.
                const hktrifft = !suchaktiv || (hk.dataset.suchtext || '').indexOf(nadel) !== -1;
                let lktreffer = 0;

                hk.querySelectorAll('[data-region="lk"]').forEach((lk) => {
                    const trifft = hktrifft || (lk.dataset.suchtext || '').indexOf(nadel) !== -1;
                    umschalten(lk, trifft);
                    if (trifft && !hktrifft) {
                        lktreffer++;
                    }
                });

                const zeigen = hktrifft || lktreffer > 0;
                umschalten(hk, zeigen);
                if (zeigen) {
                    imBereich++;
                }

                // Aufgeklappt, solange gesucht wird: der Treffer kann in einem
                // Leistungskriterium liegen, und den zugeklappt zu lassen waere
                // das Gegenteil dessen, wofuer man sucht.
                const aufklapper = hk.querySelector('[data-region="lk-aufklapper"]');
                if (aufklapper) {
                    aufklapper.open = suchaktiv
                        ? zeigen
                        : aufklapper.dataset.offenInitial === '1';
                }
            });

            umschalten(bereich, imBereich > 0);
            bereich.open = suchaktiv ? imBereich > 0 : bereich.dataset.offenInitial === '1';
            sichtbar += imBereich;
        });

        if (keinetreffer) {
            umschalten(keinetreffer, sichtbar === 0);
        }
    };

    // Ohne Skript ist die Schaltflaeche der einzige Weg zum Filtern, mit
    // Skript filtert bereits das Tippen.
    if (absenden) {
        umschalten(absenden, false);
    }

    suchformular.addEventListener('submit', (ereignis) => {
        ereignis.preventDefault();
        filtern();
    });

    let zeitgeber = null;
    feld.addEventListener('input', () => {
        window.clearTimeout(zeitgeber);
        zeitgeber = window.setTimeout(filtern, TIPPPAUSE);
    });

    // Ein vom Server gesetzter Filter steht bereits im Feld; das Markup ist
    // dann schon gefiltert, und ein Lauf schadet nicht.
    if (feld.value.trim() !== '') {
        filtern();
    }
};
