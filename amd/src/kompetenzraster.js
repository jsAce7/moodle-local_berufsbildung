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
 * Leistungskriterien einer Rasterzelle in einem Dialog.
 *
 * Eine Ergaenzung, keine Voraussetzung: ohne dieses Skript klappt das
 * native <details> die Liste in der Zelle auf. Mit Skript oeffnet ein
 * Klick auf die Zahl ("2 von 12 LK vorgekommen") einen Dialog mit
 * demselben Inhalt - bei zehn und mehr LK waere die Zeile des Rasters
 * sonst unuebersichtlich.
 *
 * amd/build entsteht mit "npx grunt amd --root=local/berufsbildung" aus dem
 * Moodle-Verzeichnis; die CI prueft mit "moodle-plugin-ci grunt", dass er
 * zu amd/src passt.
 *
 * @module     local_berufsbildung/kompetenzraster
 * @copyright  2026 jsAce7
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';

/** Nur einmal je Seite anhaengen, auch wenn mehrere Raster darauf stehen. */
let angehaengt = false;

/**
 * Haengt den Dialog an die Koepfe der LK-Listen.
 */
export const init = () => {
    if (angehaengt) {
        return;
    }
    angehaengt = true;

    document.addEventListener('click', (ereignis) => {
        const kopf = ereignis.target.closest('[data-region="raster-lk-kopf"]');
        if (!kopf) {
            return;
        }

        const liste = kopf.closest('[data-region="raster-lk"]');
        const inhalt = liste ? liste.querySelector('[data-region="raster-lk-inhalt"]') : null;
        if (!inhalt) {
            return;
        }

        // Das <details> bleibt zu; der Inhalt erscheint im Dialog.
        ereignis.preventDefault();

        Modal.create({
            title: liste.dataset.titel,
            body: '<div class="local-berufsbildung-raster-lkdialog">' + inhalt.innerHTML + '</div>',
            large: true,
            show: true,
            removeOnClose: true,
            returnElement: kopf,
        });
    });
};
