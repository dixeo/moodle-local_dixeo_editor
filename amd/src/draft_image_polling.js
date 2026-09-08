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
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Shared draft-image status polling for Dixeo content editors.
 *
 * @module     local_dixeo_editor/draft_image_polling
 * @copyright  2026 Dixeo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'local_dixeo/content_image_pending'], function(Ajax, ContentImagePending) {
    'use strict';

    const POLL_INTERVAL_MS = 3000;

    /**
     * @param {string} url
     * @param {string} rev
     * @returns {string}
     */
    const appendImageRev = (url, rev) => {
        if (!url || !rev) {
            return url;
        }
        const cleaned = url.replace(/([?&])rev=[^&]*/g, '$1').replace(/[?&]$/, '');
        const separator = cleaned.includes('?') ? '&' : '?';
        return cleaned + separator + 'rev=' + encodeURIComponent(rev);
    };

    /**
     * Locate a draft placeholder img in the TinyMCE iframe / body.
     *
     * @param {Document} doc
     * @param {object|null} editor
     * @param {string} placeholderid
     * @param {string} filename
     * @returns {HTMLImageElement|null}
     */
    const findPlaceholderImg = (doc, editor, placeholderid, filename) => {
        const roots = [];
        if (doc) {
            roots.push(doc);
        }
        if (editor && typeof editor.getBody === 'function') {
            const body = editor.getBody();
            if (body && body.ownerDocument && body.ownerDocument !== doc) {
                roots.push(body.ownerDocument);
            } else if (body) {
                roots.push(body);
            }
        }

        const selectors = [
            'img[data-dixeo-img-gen="' + placeholderid + '"]',
            'img[src*="' + filename + '"]',
            'img[data-mce-src*="' + filename + '"]',
        ];

        for (let r = 0; r < roots.length; r++) {
            const root = roots[r];
            for (let s = 0; s < selectors.length; s++) {
                const found = root.querySelector(selectors[s]);
                if (found) {
                    return found;
                }
            }
        }

        if (editor && editor.dom && typeof editor.dom.select === 'function') {
            for (let s = 0; s < selectors.length; s++) {
                const list = editor.dom.select(selectors[s]);
                if (list && list.length) {
                    return list[0];
                }
            }
        }
        return null;
    };

    /**
     * Apply a terminal draft-image status item inside TinyMCE / iframe DOM.
     *
     * @param {object} item Status payload from get_editor_draft_image_status
     * @param {function(): (Document|null)} getDoc
     * @param {function(): (object|null)} getEditor TinyMCE editor instance or null
     * @returns {boolean}
     */
    const updatePlaceholder = (item, getDoc, getEditor) => {
        const editor = typeof getEditor === 'function' ? getEditor() : null;
        let doc = typeof getDoc === 'function' ? getDoc() : null;
        if (!doc && editor && typeof editor.getDoc === 'function') {
            doc = editor.getDoc();
        }
        if (!doc) {
            return false;
        }
        const filename = 'dixeo-gen-' + item.placeholderid + '.png';
        const img = findPlaceholderImg(doc, editor, item.placeholderid, filename);
        if (!img) {
            return false;
        }

        let nextUrl = item.imageurl || '';
        if (item.contenthash && nextUrl) {
            nextUrl = appendImageRev(nextUrl, item.contenthash);
        }
        const nextClass = item.imgclass || 'img-fluid';

        if (editor && editor.dom) {
            if (nextUrl) {
                editor.dom.setAttrib(img, 'src', nextUrl);
                editor.dom.setAttrib(img, 'data-mce-src', nextUrl);
            }
            editor.dom.setAttrib(img, 'class', nextClass);
            if (item.contenthash) {
                editor.dom.setAttrib(img, 'data-dixeo-contenthash', item.contenthash);
            } else {
                editor.dom.setAttrib(img, 'data-dixeo-contenthash', null);
            }
            if (typeof editor.nodeChanged === 'function') {
                editor.nodeChanged();
            }
            if (typeof editor.save === 'function') {
                editor.save();
            }
        } else {
            if (nextUrl) {
                img.setAttribute('src', nextUrl);
                img.setAttribute('data-mce-src', nextUrl);
            }
            img.setAttribute('class', nextClass);
            if (item.contenthash) {
                img.setAttribute('data-dixeo-contenthash', item.contenthash);
            } else {
                img.removeAttribute('data-dixeo-contenthash');
            }
        }

        const stillPendingOrFailed = nextClass.indexOf('dixeo-img-gen-pending') !== -1 ||
            nextClass.indexOf('dixeo-img-gen-failed') !== -1;
        if (stillPendingOrFailed) {
            // MutationObserver only watches childList; class/src changes need a refresh
            // so the failed label/shimmer host updates in the iframe.
            ContentImagePending.refresh(doc);
        } else {
            const liveImg = findPlaceholderImg(doc, editor, item.placeholderid, filename);
            ContentImagePending.clearImageHost(liveImg || img);
        }
        return true;
    };

    /**
     * Start polling draft placeholders. Mutates host.pendingPlaceholderIds / host.imagePollTimer.
     *
     * @param {object} host Editor instance holding pendingPlaceholderIds + imagePollTimer
     * @param {object} opts
     * @param {number} opts.cmid
     * @param {number} opts.sessionid
     * @param {number} [opts.slideid]
     * @param {string[]} opts.placeholderIds
     * @param {function(): (Document|null)} opts.getDoc
     * @param {function(): (object|null)} opts.getEditor
     */
    const start = (host, opts) => {
        host.pendingPlaceholderIds = (opts.placeholderIds || []).slice();
        if (host.imagePollTimer) {
            window.clearInterval(host.imagePollTimer);
            host.imagePollTimer = null;
        }

        const pollOnce = () => {
            if (!host.pendingPlaceholderIds.length) {
                if (host.imagePollTimer) {
                    window.clearInterval(host.imagePollTimer);
                    host.imagePollTimer = null;
                }
                return;
            }
            Ajax.call([{
                methodname: 'local_dixeo_editor_get_editor_draft_image_status',
                args: {
                    cmid: opts.cmid,
                    sessionid: opts.sessionid,
                    placeholderids: host.pendingPlaceholderIds,
                    slideid: opts.slideid || 0,
                },
            }])[0].then((response) => {
                if (!response.success || !response.data || !response.data.items) {
                    return undefined;
                }
                response.data.items.forEach((item) => {
                    if (item.status === 'pending' || item.status === 'processing') {
                        return;
                    }
                    if (!updatePlaceholder(item, opts.getDoc, opts.getEditor)) {
                        return;
                    }
                    host.pendingPlaceholderIds = host.pendingPlaceholderIds.filter(
                        (id) => id !== item.placeholderid
                    );
                });
                return undefined;
            }).catch(() => {
                // Keep polling on transient errors.
            });
        };

        pollOnce();
        host.imagePollTimer = window.setInterval(pollOnce, POLL_INTERVAL_MS);
    };

    return {
        start,
        updatePlaceholder,
        POLL_INTERVAL_MS,
    };
});
