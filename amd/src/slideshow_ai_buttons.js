// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Inject an "Edit with AI" button into each slide row of mod_slideshow's
 * slides management page. Each button points to the Dixeo content editor
 * scoped to (cmid, slideid).
 *
 * @module     local_dixeo_editor/slideshow_ai_buttons
 * @copyright  2026 Edunao SAS (contact@edunao.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/url', 'core/str'], function(Url, Str) {
    'use strict';

    var CONTENT_EDITOR_PATH = '/local/dixeo_editor/content.php';

    /**
     * Build the button element for a given slide.
     *
     * @param {number} cmid
     * @param {number} slideid
     * @param {string} label
     * @returns {HTMLAnchorElement}
     */
    function buildButton(cmid, slideid, label) {
        var a = document.createElement('a');
        a.className = 'dixeo-ai-edit btn btn-default';
        a.title = label;
        a.setAttribute('aria-label', label);
        a.href = Url.relativeUrl(CONTENT_EDITOR_PATH, {cmid: cmid, slideid: slideid});
        a.innerHTML = '<i class="fa fa-magic" aria-hidden="true"></i>';
        return a;
    }

    /**
     * Inject one button per slide row that doesn't already have one.
     *
     * @param {number} cmid
     * @param {string} label
     */
    function injectButtons(cmid, label) {
        var rows = document.querySelectorAll('#slide-list .slide-item');
        rows.forEach(function(row) {
            var actions = row.querySelector('.actions');
            if (!actions || actions.querySelector('.dixeo-ai-edit')) {
                return;
            }
            var slideid = parseInt(row.getAttribute('data-id'), 10);
            if (!slideid) {
                return;
            }
            actions.insertBefore(buildButton(cmid, slideid, label), actions.firstChild);
        });
    }

    return {
        /**
         * Entry point.
         *
         * @param {number} cmid The slideshow course module ID.
         */
        init: function(cmid) {
            if (!cmid) {
                return;
            }

            Str.get_string('editcontent', 'local_dixeo_editor').then(function(label) {
                injectButtons(cmid, label);

                // Watch for dynamically-added slide rows (sortable / added slides).
                var list = document.getElementById('slide-list');
                if (list && typeof MutationObserver !== 'undefined') {
                    var observer = new MutationObserver(function() {
                        injectButtons(cmid, label);
                    });
                    observer.observe(list, {childList: true, subtree: false});
                }
                return null;
            }).catch(function() {
                // Silent — missing string shouldn't break the slides page.
            });
        }
    };
});
