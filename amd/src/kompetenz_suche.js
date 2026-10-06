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
 * Mehrere Begriffe, durch Komma, Semikolon oder Zeilenumbruch getrennt,
 * gelten als "oder" - wie kompetenz_auswahl::suchbegriffe(). Eine aus
 * Excel eingefuegte Spalte kommt mit Zeilenumbruechen, die ein einzeiliges
 * Feld sonst verschluckt; beim Einfuegen werden sie zu Kommas.
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
 * Zerlegt die Eingabe in klein geschriebene Suchbegriffe - getrennt an
 * Komma, Semikolon, Tabulator und Zeilenumbruch, nicht am Leerzeichen:
 * ein LK-Code wie "MEM 11 05 1-2" enthaelt selbst welche.
 *
 * @param {string} eingabe
 * @returns {Array<{text: string, nadel: string}>} Begriff wie eingegeben und klein geschrieben, ohne Doppelte
 */
const suchbegriffe = (eingabe) => {
    const gesehen = new Set();
    const begriffe = [];
    eingabe.split(/[,;\t\r\n]+/).forEach((teil) => {
        const text = teil.trim();
        const nadel = text.toLowerCase();
        if (nadel !== '' && !gesehen.has(nadel)) {
            gesehen.add(nadel);
            begriffe.push({text, nadel});
        }
    });

    return begriffe;
};

/**
 * Passt ein Element mit data-suchtext auf einen der Begriffe?
 *
 * @param {Element} element
 * @param {string[]} nadeln
 * @returns {boolean}
 */
const trifft = (element, nadeln) => {
    const suchtext = element.dataset.suchtext || '';
    return nadeln.some((nadel) => suchtext.indexOf(nadel) !== -1);
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
    const nichtgefunden = document.querySelector('[data-region="berufsbildung-auswahl-nicht-gefunden"]');
    const nichtgefundenliste = nichtgefunden
        ? nichtgefunden.querySelector('[data-region="nicht-gefunden-liste"]')
        : null;
    const durchsuchbar = Array.from(auswahl.querySelectorAll('[data-suchtext]'));
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
        const begriffe = suchbegriffe(feld.value);
        const nadeln = begriffe.map((begriff) => begriff.nadel);
        const suchaktiv = nadeln.length > 0;
        let sichtbar = 0;

        bereiche.forEach((bereich) => {
            let imBereich = 0;

            // Trifft der Bereich oder die Handlungskompetenz selbst, gehoert
            // alles darunter dazu - dieselbe Regel wie im Serverfilter.
            const bereichtrifft = !suchaktiv || trifft(bereich, nadeln);

            bereich.querySelectorAll('[data-region="hk"]').forEach((hk) => {
                const hktrifft = bereichtrifft || trifft(hk, nadeln);
                let lktreffer = 0;

                hk.querySelectorAll('[data-region="lk"]').forEach((lk) => {
                    const lktrifft = hktrifft || trifft(lk, nadeln);
                    umschalten(lk, lktrifft);
                    if (lktrifft && !hktrifft) {
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

        // Begriffe ohne jeden Treffer, nur neben anderen Treffern - findet
        // gar nichts, sagt das bereits keinetreffer.
        if (nichtgefunden && nichtgefundenliste) {
            const fehlend = begriffe
                .filter((begriff) => !durchsuchbar.some((element) => trifft(element, [begriff.nadel])))
                .map((begriff) => begriff.text);
            nichtgefundenliste.textContent = fehlend.join(', ');
            umschalten(nichtgefunden, fehlend.length > 0 && sichtbar > 0);
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

    feld.addEventListener('paste', (ereignis) => {
        const text = ereignis.clipboardData ? ereignis.clipboardData.getData('text') : '';
        if (!/[\t\r\n]/.test(text)) {
            return;
        }

        ereignis.preventDefault();
        const liste = text.split(/[\t\r\n]+/)
            .map((teil) => teil.trim())
            .filter((teil) => teil !== '')
            .join(', ');
        feld.setRangeText(liste, feld.selectionStart, feld.selectionEnd, 'end');
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
