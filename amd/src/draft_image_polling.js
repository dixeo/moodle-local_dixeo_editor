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
     * Apply a terminal draft-image status item inside TinyMCE / iframe DOM.
     *
     * @param {object} item Status payload from get_editor_draft_image_status
     * @param {function(): (Document|null)} getDoc
     * @param {function(): (object|null)} getEditor TinyMCE editor instance or null
     * @returns {boolean}
     */
    const updatePlaceholder = (item, getDoc, getEditor) => {
        const doc = typeof getDoc === 'function' ? getDoc() : null;
        if (!doc) {
            return false;
        }
        const filename = 'dixeo-gen-' + item.placeholderid + '.png';
        let img = doc.querySelector('img[data-dixeo-img-gen="' + item.placeholderid + '"]');
        if (!img) {
            img = doc.querySelector('img[src*="' + filename + '"], img[data-mce-src*="' + filename + '"]');
        }
        if (!img) {
            return false;
        }

        let nextUrl = item.imageurl || '';
        if (item.contenthash && nextUrl) {
            nextUrl = appendImageRev(nextUrl, item.contenthash);
        }
        const nextClass = item.imgclass || 'img-fluid';
        const editor = typeof getEditor === 'function' ? getEditor() : null;

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

        if (nextClass.indexOf('dixeo-img-gen-pending') === -1 &&
                nextClass.indexOf('dixeo-img-gen-failed') === -1) {
            let liveImg = doc.querySelector(
                'img[data-dixeo-img-gen="' + item.placeholderid + '"]'
            );
            if (!liveImg) {
                liveImg = doc.querySelector(
                    'img[src*="' + filename + '"], img[data-mce-src*="' + filename + '"]'
                );
            }
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
