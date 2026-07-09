define(['jquery', 'core/templates', 'core/notification', 'core/ajax', 'core/str'], function($, Templates, Notification, Ajax, Str) {
    // Note: Do NOT resolve DOM elements at module load time.
    // Moodle may load this AMD module before the template markup is present.
    // Always query inside init/setup to avoid stale null references.

    // Timing constants for editor initialization polling.
    const EDITOR_LOAD_TIMEOUT_MS = 60000;
    const EDITOR_POLL_INTERVAL_MS = 500;

    // DOM Selectors - centralized for maintainability.
    const SELECTORS = {
        EDITOR_IFRAME: 'iframe[id^="id_modulecontent_i"]',
        GENERATE_BUTTON: 'generate_button',
        TOOLBAR_BUTTONS: '#fitem_id_modulecontent button',
        TEXTAREA: 'id_modulecontent'
    };

    /**
     * Finds the TinyMCE iframe matching the `id_content_i*` pattern.
     * @returns {HTMLIFrameElement|null} The iframe element if found, otherwise null.
     */
    function findEditorIframe() {
        const iframes = document.querySelectorAll(SELECTORS.EDITOR_IFRAME);
        return iframes.length > 0 ? iframes[0] : null;
    }

    /**
     * Submit the editor form when the apply button lives outside the form markup.
     */
    function submitModEditorForm() {
        const form = document.getElementById('mod_editor_form');
        if (!form) {
            return;
        }
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    }

    /**
     * Waits for the TinyMCE editor iframe to load and returns the iframe's document.
     * @param {number} timeout - Maximum time to wait in milliseconds.
     * @param {number} interval - Interval between each check in milliseconds.
     * @returns {Promise} - Resolves with the iframe's document or rejects with an error.
     */
    function waitForEditorIframe(timeout = EDITOR_LOAD_TIMEOUT_MS, interval = EDITOR_POLL_INTERVAL_MS) {
        return new Promise((resolve, reject) => {
            const startTime = Date.now();

            const check = () => {
                const iframe = findEditorIframe();
                if (iframe && iframe.contentDocument && iframe.contentDocument.body) {
                    resolve(iframe.contentDocument);
                } else {
                    if (Date.now() - startTime > timeout) {
                        reject(new Error('Editor iframe was not loaded within the expected time.'));
                    } else {
                        setTimeout(check, interval);
                    }
                }
            };

            check();
        });
    }

    /**
     * Clone the parent page's theme stylesheet into the TinyMCE iframe so
     * FontAwesome (and any other content-relevant CSS) renders in-editor.
     *
     * @param {Document} iframeDoc - The TinyMCE iframe's document.
     */
    function injectThemeStylesIntoEditor(iframeDoc) {
        if (!iframeDoc || !iframeDoc.head || iframeDoc.querySelector('link[data-dixeo-theme]')) {
            return;
        }
        const themeLinks = document.querySelectorAll('link[rel="stylesheet"][href*="/theme/styles.php"]');
        themeLinks.forEach(link => {
            const clone = iframeDoc.createElement('link');
            clone.rel = 'stylesheet';
            clone.href = link.href;
            clone.setAttribute('data-dixeo-theme', '1');
            iframeDoc.head.appendChild(clone);
        });
    }

    /**
     * Moves the cursor to the end of the given textarea.
     *
     * This function ensures that when a user interacts with the textarea,
     * the cursor is positioned at the very end of the current text content.
     * It supports modern browsers using `setSelectionRange`, and falls back
     * to a simple focus for older browsers.
     *
     * @param {HTMLTextAreaElement} textarea - The textarea element to modify.
     */
    function moveCursorToEnd(textarea) {
        if (textarea.setSelectionRange) {
            const length = textarea.value.length;
            textarea.focus();
            textarea.setSelectionRange(length, length);
        } else { // For older browsers
            textarea.focus();
        }
    }

    return {
        init: function(cmid, slideid, sessionid) {
            this.sessionId = sessionid || 0;
            this.pendingPlaceholderIds = [];
            this.imagePollTimer = null;
            return waitForEditorIframe()
                .then((iframeDoc) => {
                    this.editorDocument = iframeDoc;
                    injectThemeStylesIntoEditor(iframeDoc);
                    this.setupEventListeners(cmid, slideid);
                    return undefined;
                })
                .catch(Notification.exception);
        },

        /**
         * Sets up all necessary event listeners after the editor iframe is ready.
         * @param {number} cmid - The ID of the module being edited.
         * @param {number|null} slideid - Slide row ID (slideshow only), null otherwise.
         */
        setupEventListeners: function(cmid, slideid) {
            this.cmid = cmid;
            this.slideid = slideid || 0;
            // Resolve DOM elements now that the template is on the page.
            const generateButton = document.getElementById(SELECTORS.GENERATE_BUTTON);
            const applyButton = document.getElementById('apply_button');
            const cancelButton = document.getElementById('cancel_button');
            const loader = document.getElementById('loader');
            const instructionsArea = document.getElementById('instructions');
            const successContainer = document.getElementById('success_message_container');

            if (!generateButton || !applyButton || !cancelButton || !loader || !instructionsArea || !successContainer) {
                // Fail fast with a clear message if the UI is not fully present.
                Notification.exception(new Error('Editor UI initialisation failed: missing DOM elements.'));
                return;
            }

            // Listen for the success alert being closed (delegate for dynamically added alerts).
            $(document).on('closed.bs.alert', '#success_message_container .alert', () => {
                successContainer.classList.replace('d-flex', 'd-none');
            });

            applyButton.addEventListener('click', () => {
                submitModEditorForm();
            });

            // Display confirm modal when clicking on the cancel button.
            cancelButton.addEventListener('click', (event) => {
                event.preventDefault();
                const cancelUrl = cancelButton.href;
                Promise.all([
                    Str.get_string('cancelmodeconfirm', 'local_dixeo_editor'),
                    Str.get_string('yes', 'moodle'),
                    Str.get_string('no', 'moodle')
                ]).then((strings) => {
                    Notification.confirm(
                        '',
                        strings[0],
                        strings[1],
                        strings[2],
                        () => {
                            this.discardSessionAndLeave(cancelUrl);
                        },
                        () => undefined
                    );
                    return undefined;
                }).catch(Notification.exception);
            });

            // Handle AI tag button clicks.
            document.querySelectorAll('.ai-tag-button').forEach(btn => {
                btn.addEventListener('click', () => {
                    instructionsArea.value = btn.dataset.prompt;
                    moveCursorToEnd(instructionsArea); // Move cursor to end
                    generateButton.disabled = !instructionsArea.value.trim();
                });
            });

            // Update generate button enabled state based on instructions input.
            instructionsArea.addEventListener('input', () => {
                generateButton.disabled = !instructionsArea.value.trim();
            });

            // Initially disable generate button if instructions are empty.
            generateButton.disabled = !instructionsArea.value.trim();

            // Allow enter key to submit.
            instructionsArea.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    generateButton.click();
                }
            });

            // Handle generate button click.
            generateButton.addEventListener('click', (e) => {
                e.preventDefault();

                const instructions = instructionsArea.value.trim();
                const editorModule = this;

                // Disable buttons and inputs during processing.
                generateButton.disabled = true;
                applyButton.disabled = true;
                instructionsArea.disabled = true;
                editorModule.disableEditor();

                // Show loader.
                loader.classList.replace('d-none', 'd-flex');

                const finishGeneration = () => {
                    generateButton.disabled = false;
                    applyButton.disabled = false;
                    instructionsArea.disabled = false;
                    editorModule.enableEditor();
                    loader.classList.replace('d-flex', 'd-none');
                };

                // Call Dixeo web service using Moodle Ajax API. Send the live
                // editor HTML so unsaved draft images survive the round-trip.
                return Ajax.call([{
                    methodname: 'local_dixeo_editor_regenerate_module_content',
                    args: {
                        cmid: cmid,
                        instructions: instructions,
                        sessionid: this.sessionId,
                        slideid: slideid || 0,
                        drafthtml: this.getEditorContent()
                    }
                }])[0]
                .then((response) => {
                    if (!response.success) {
                        const errorMsg = response.error?.message || 'An unexpected error occurred. Please try again.';
                        throw new Error(errorMsg);
                    }
                    return response;
                })
                .then(async(response) => {
                    this.applyEditorContent(response.data.content || '');
                    if (response.data.pendingplaceholderids && response.data.pendingplaceholderids.length) {
                        this.startDraftImagePolling(response.data.pendingplaceholderids, cmid, slideid);
                    }

                    const textarea = document.getElementById(SELECTORS.TEXTAREA);
                    const tinymceInstance = (textarea && window.tinymce && typeof window.tinymce.get === 'function')
                        ? window.tinymce.get(textarea.id)
                        : null;
                    if (tinymceInstance && typeof tinymceInstance.save === 'function') {
                        tinymceInstance.save();
                    }

                    const rendered = await Templates.renderForPromise('local_dixeo_editor/success_box', {});
                    Templates.appendNodeContents('#success_message_container', rendered.html, rendered.js);
                    successContainer.classList.replace('d-none', 'd-flex');
                    return undefined;
                })
                .catch((error) => {
                    Notification.exception(error);
                    return undefined;
                })
                .then(() => {
                    finishGeneration();
                    return undefined;
                });
            });
        },

        /**
         * Sets the editor enabled/disabled state.
         * Controls contenteditable, toolbar buttons, iframe tabindex, and textarea.
         * @param {boolean} enabled - True to enable, false to disable.
         */
        setEditorEnabled: function(enabled) {
            // Set contenteditable on the editor body.
            if (this.editorDocument && this.editorDocument.body) {
                this.editorDocument.body.setAttribute('contenteditable', enabled ? 'true' : 'false');
            }

            // Set toolbar buttons state.
            const toolbarButtons = document.querySelectorAll(SELECTORS.TOOLBAR_BUTTONS);
            toolbarButtons.forEach(button => {
                button.disabled = !enabled;
            });

            // Set iframe tabindex for keyboard accessibility.
            const iframe = findEditorIframe();
            if (iframe) {
                iframe.setAttribute('tabindex', enabled ? '0' : '-1');
            }

            // Set textarea state.
            const textarea = document.getElementById(SELECTORS.TEXTAREA);
            if (textarea) {
                textarea.disabled = !enabled;
            }
        },

        /**
         * Disables the editor by setting contenteditable to false and disabling toolbar buttons.
         */
        disableEditor: function() {
            this.setEditorEnabled(false);
        },

        /**
         * Enables the editor by setting contenteditable to true and enabling toolbar buttons.
         */
        enableEditor: function() {
            this.setEditorEnabled(true);
        },

        /**
         * Current editor HTML, preferring TinyMCE's normalized content.
         * @returns {string}
         */
        getEditorContent: function() {
            const textarea = document.getElementById(SELECTORS.TEXTAREA);
            const tinymceInstance = (textarea && window.tinymce && typeof window.tinymce.get === 'function')
                ? window.tinymce.get(textarea.id)
                : null;
            if (tinymceInstance && typeof tinymceInstance.getContent === 'function') {
                return tinymceInstance.getContent();
            }
            if (this.editorDocument && this.editorDocument.body) {
                return this.editorDocument.body.innerHTML;
            }
            return textarea ? textarea.value : '';
        },

        applyEditorContent: function(content) {
            const textarea = document.getElementById(SELECTORS.TEXTAREA);
            const tinymceInstance = (textarea && window.tinymce && typeof window.tinymce.get === 'function')
                ? window.tinymce.get(textarea.id)
                : null;
            if (!tinymceInstance) {
                // Do not inject AI HTML via iframe innerHTML; TinyMCE must be ready.
                throw new Error('Editor is not ready. Please try again.');
            }
            if (tinymceInstance.undoManager) {
                tinymceInstance.focus();
                tinymceInstance.undoManager.transact(() => {
                    tinymceInstance.setContent(content || '');
                });
                tinymceInstance.selection.setCursorLocation();
                tinymceInstance.nodeChanged();
            } else {
                tinymceInstance.setContent(content || '');
            }
        },

        startDraftImagePolling: function(placeholderIds, cmid, slideid) {
            this.pendingPlaceholderIds = placeholderIds.slice();
            if (this.imagePollTimer) {
                window.clearInterval(this.imagePollTimer);
            }
            this.imagePollTimer = window.setInterval(() => {
                if (!this.pendingPlaceholderIds.length) {
                    window.clearInterval(this.imagePollTimer);
                    this.imagePollTimer = null;
                    return;
                }
                Ajax.call([{
                    methodname: 'local_dixeo_editor_get_editor_draft_image_status',
                    args: {
                        cmid: cmid,
                        sessionid: this.sessionId,
                        placeholderids: this.pendingPlaceholderIds,
                        slideid: slideid || 0
                    }
                }])[0].then((response) => {
                    if (!response.success || !response.data || !response.data.items) {
                        return undefined;
                    }
                    response.data.items.forEach((item) => {
                        if (item.status === 'pending' || item.status === 'processing') {
                            return;
                        }
                        if (!this.updatePlaceholderInEditor(item)) {
                            return;
                        }
                        this.pendingPlaceholderIds = this.pendingPlaceholderIds.filter((id) => id !== item.placeholderid);
                    });
                    return undefined;
                }).catch(() => {
                    // Keep polling on transient errors.
                });
            }, 3000);
        },

        updatePlaceholderInEditor: function(item) {
            const doc = this.editorDocument;
            if (!doc) {
                return false;
            }
            let img = doc.querySelector('img[data-dixeo-img-gen="' + item.placeholderid + '"]');
            if (!img) {
                const filename = 'dixeo-gen-' + item.placeholderid + '.png';
                img = doc.querySelector('img[src*="' + filename + '"]');
            }
            if (!img) {
                return false;
            }
            if (item.imageurl) {
                img.setAttribute('src', item.imageurl);
            }
            img.setAttribute('class', item.imgclass || 'img-fluid');
            const textarea = document.getElementById(SELECTORS.TEXTAREA);
            if (textarea && window.tinymce) {
                const editor = window.tinymce.get(textarea.id);
                if (editor && typeof editor.save === 'function') {
                    editor.save();
                }
            }
            return true;
        },

        discardSessionAndLeave: function(targetUrl) {
            if (!this.sessionId) {
                window.location.href = targetUrl;
                return undefined;
            }
            return Ajax.call([{
                methodname: 'local_dixeo_editor_discard_editor_session',
                args: {
                    cmid: this.cmid || 0,
                    sessionid: this.sessionId,
                    slideid: this.slideid || 0
                }
            }])[0].catch(() => {
                // Still leave the page if discard fails.
                return undefined;
            }).then(() => {
                window.location.href = targetUrl;
                return undefined;
            });
        }
    };
});
