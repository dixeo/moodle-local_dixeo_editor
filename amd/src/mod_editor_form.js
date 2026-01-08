define(['jquery', 'core/templates', 'core/notification', 'core/ajax'], function($, Templates, Notification, Ajax) {
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
        init: function(cmid) {
            waitForEditorIframe()
                .then((iframeDoc) => {
                    this.editorDocument = iframeDoc;
                    this.setupEventListeners(cmid);
                })
                .catch(Notification.exception);
        },

        /**
         * Sets up all necessary event listeners after the editor iframe is ready.
         * @param {number} cmid - The ID of the module being edited.
         */
        setupEventListeners: function(cmid) {
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

            // Display confirm modal when clicking on the cancel button.
            cancelButton.addEventListener('click', function(event) {
                event.preventDefault();
                const userConfirmed = confirm("Are you sure you want to quit edit mode? All modifications will be lost.");
                if (userConfirmed) {
                    window.location.href = this.href;
                }
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

                // Disable buttons and inputs during processing.
                generateButton.disabled = true;
                applyButton.disabled = true;
                instructionsArea.disabled = true;
                this.disableEditor();

                // Show loader.
                loader.classList.replace('d-none', 'd-flex');

                // Call Dixeo web service using Moodle Ajax API.
                Ajax.call([{
                    methodname: 'local_dixeo_editor_regenerate_module_content',
                    args: {
                        cmid: cmid,
                        instructions: instructions
                    }
                }])[0]
                .then((response) => {
                    // Check if the response indicates failure.
                    if (!response.success) {
                        const errorMsg = response.error?.message || 'An unexpected error occurred. Please try again.';
                        throw new Error(errorMsg);
                    }
                    return response;
                })
                .then((response) => {
                    // Update the editor's content using response.data.content.
                    this.editorDocument.body.innerHTML = response.data.content;
                    const textarea = document.getElementById(SELECTORS.TEXTAREA);
                    if (textarea) {
                        // Trigger TinyMCE undo event safely if TinyMCE is present.
                        const tinymceInstance = (window.tinymce && typeof window.tinymce.get === 'function')
                            ? window.tinymce.get(textarea.id)
                            : null;
                        if (tinymceInstance && tinymceInstance.undoManager) {
                            tinymceInstance.focus();
                            tinymceInstance.undoManager.transact(() => {
                                tinymceInstance.setContent(response.data.content);
                            });
                            tinymceInstance.selection.setCursorLocation();
                            tinymceInstance.nodeChanged();
                        }
                    }

                    // Display success message.
                    Templates.renderForPromise('local_dixeo_editor/success_box', {})
                        .then(({html, js}) => {
                            Templates.appendNodeContents('#success_message_container', html, js);
                            successContainer.classList.replace('d-none', 'd-flex');
                        });
                })
                .catch((error) => {
                    Notification.exception(error);
                })
                .then(() => {
                    // Re-enable buttons and inputs.
                    generateButton.disabled = false;
                    applyButton.disabled = false;
                    instructionsArea.disabled = false;
                    this.enableEditor();

                    // Hide loader.
                    loader.classList.replace('d-flex', 'd-none');
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
        }
    };
});
