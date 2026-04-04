define([
    'core/ajax',
    'core/templates',
    'core/notification',
    'core/str',
    'local_dixeo_editor/content_editor_ai_panel',
    'local_dixeo_editor/content_editor_layout'
], function(Ajax, Templates, Notification, Str, ContentEditorAIPanel, LayoutModule) {
    'use strict';

    var SELECTORS = {
        editorIframe: 'iframe[id^="id_modulecontent_i"]',
        editorTextarea: 'id_modulecontent',
        saveButton: 'save_button',
        pageCancelButton: 'cancel_button',
        panelCloseButton: 'panel_close_button',
        panelCancelGenerationButton: 'panel_cancel_generation_button',
        generateButton: 'generate_button',
        promptTextarea: 'instructions',
        undoButton: 'undo_button',
        redoButton: 'redo_button',
        fab: 'dixeo_editor_fab',
        panel: 'dixeo_editor_panel',
        panelBackdrop: 'dixeo_editor_panel_backdrop',
        panelClose: 'dixeo_editor_panel_close',
        successContainer: 'success_message_container',
        editorUndoButton: 'button[data-mce-name="undo"]',
        editorRedoButton: 'button[data-mce-name="redo"]'
    };

    var Editor = {
        cmid: 0,
        activeJobId: null,
        generationToken: 0,
        pollingTimer: null,
        panel: null,
        initialPanelOpened: false,
        editorUndoButton: null,
        editorRedoButton: null,
        undoRedoSyncTimer: null,
        strings: {},
        layoutInitialJson: '',
        layoutRef: null,

        init: function(cmid, layoutJson) {
            this.cmid = cmid;
            this.layoutInitialJson = typeof layoutJson === 'string' ? layoutJson : '';
            this.cacheDom();
            this.loadStrings().then(function(strings) {
                Editor.strings = strings;
                Editor.setupUi();
                Editor.bindEditorUndoRedoBridge();
            }).catch(function(error) {
                Notification.exception(error);
            });
        },

        cacheDom: function() {
            this.dom = {
                saveButton: document.getElementById(SELECTORS.saveButton),
                pageCancelButton: document.getElementById(SELECTORS.pageCancelButton),
                panelCloseButton: document.getElementById(SELECTORS.panelCloseButton),
                panelCancelGenerationButton: document.getElementById(SELECTORS.panelCancelGenerationButton),
                generateButton: document.getElementById(SELECTORS.generateButton),
                promptTextarea: document.getElementById(SELECTORS.promptTextarea),
                undoButton: document.getElementById(SELECTORS.undoButton),
                redoButton: document.getElementById(SELECTORS.redoButton),
                fab: document.getElementById(SELECTORS.fab),
                panel: document.getElementById(SELECTORS.panel),
                panelBackdrop: document.getElementById(SELECTORS.panelBackdrop),
                panelClose: document.getElementById(SELECTORS.panelClose),
                successContainer: document.getElementById(SELECTORS.successContainer)
            };

            if (!this.dom.generateButton || !this.dom.promptTextarea ||
                !this.dom.panel || !this.dom.fab || !this.dom.panelBackdrop) {
                throw new Error('Content editor UI initialization failed: missing required elements.');
            }

            this.dom.generateLogo = this.dom.generateButton.querySelector('.dixeo-generate-logo');
            this.dom.generateSpinner = this.dom.generateButton.querySelector('.fa-spinner');
            this.dom.generateLabel = this.dom.generateButton.querySelector('.generate-label');
        },

        loadStrings: function() {
            return Str.get_strings([
                {key: 'generate', component: 'local_dixeo_editor'},
                {key: 'generating', component: 'local_dixeo_editor'},
                {key: 'cancelmodeconfirm', component: 'local_dixeo_editor'},
                {key: 'unexpectederror', component: 'local_dixeo_editor'}
            ]).then(function(values) {
                return {
                    generate: values[0],
                    generating: values[1],
                    cancelModeConfirm: values[2],
                    unexpectedError: values[3]
                };
            });
        },

        setupUi: function() {
            var self = this;
            this.panel = new ContentEditorAIPanel({
                fab: this.dom.fab,
                panel: this.dom.panel,
                backdrop: this.dom.panelBackdrop,
                closeButton: this.dom.panelClose,
                generateButton: this.dom.generateButton,
                generateLogo: this.dom.generateLogo,
                generateSpinner: this.dom.generateSpinner,
                generateLabel: this.dom.generateLabel,
                panelCancelGenerationButton: this.dom.panelCancelGenerationButton,
                onFloatGeometryChange: function() {
                    if (self.layoutRef) {
                        self.layoutRef.onFloatGeometryChange();
                    }
                },
                onPanelOpen: function() {
                    if (self.layoutRef && typeof self.layoutRef.clampFloatPanelToViewport === 'function') {
                        self.layoutRef.clampFloatPanelToViewport();
                    }
                }
            });
            this.panel.init();
            this.panel.setGenerateVisualState(false, this.strings.generate, this.strings.generating);
            this.setPanelActionButtonsMode(false);

            this.layoutRef = LayoutModule.create(this);

            this.bindPromptMenus();
            this.bindActionButtons();

            // Wait for TinyMCE toolbar buttons to become available.
            this.dom.undoButton.disabled = true;
            this.dom.redoButton.disabled = true;
        },

        bindPromptMenus: function() {
            var self = this;
            document.querySelectorAll('.ai-prompt-option').forEach(function(button) {
                button.addEventListener('click', function() {
                    self.dom.promptTextarea.value = button.dataset.prompt || '';
                    self.dom.promptTextarea.focus();
                });
            });
        },

        bindActionButtons: function() {
            var self = this;

            this.dom.generateButton.addEventListener('click', function() {
                self.startGeneration();
            });

            this.dom.undoButton.addEventListener('click', function() {
                self.clickEditorToolbarButton(self.editorUndoButton);
            });
            this.dom.redoButton.addEventListener('click', function() {
                self.clickEditorToolbarButton(self.editorRedoButton);
            });

            this.dom.pageCancelButton.addEventListener('click', function(event) {
                if (self.activeJobId) {
                    event.preventDefault();
                    return;
                }
                event.preventDefault();
                var shouldLeave = window.confirm(self.strings.cancelModeConfirm);
                if (shouldLeave) {
                    window.location.href = self.dom.pageCancelButton.href;
                }
            });

            this.dom.panelCloseButton.addEventListener('click', function() {
                if (self.layoutRef && self.layoutRef.isDocked()) {
                    return;
                }
                self.panel.close();
            });

            this.dom.panelCancelGenerationButton.addEventListener('click', function() {
                self.cancelGeneration();
            });
        },

        /**
         * Toggle visibility between panel Close and Cancel buttons.
         *
         * @param {boolean} isGenerating
         */
        setPanelActionButtonsMode: function(isGenerating) {
            this.dom.panelCloseButton.classList.toggle('d-none', isGenerating);
            this.dom.panelCancelGenerationButton.classList.toggle('d-none', !isGenerating);
        },

        /**
         * Wait for TinyMCE toolbar Undo/Redo and keep proxy buttons synced.
         *
         * @param {number} timeoutMs
         */
        bindEditorUndoRedoBridge: function(timeoutMs) {
            var self = this;
            var timeout = timeoutMs || 30000;
            var startedAt = Date.now();

            (function waitForButtons() {
                self.refreshEditorUndoRedoButtons();
                if (self.editorUndoButton && self.editorRedoButton) {
                    self.startUndoRedoObserver();
                    self.syncUndoRedoAvailability();
                    if (!self.initialPanelOpened) {
                        self.panel.open();
                        self.initialPanelOpened = true;
                    }
                    return;
                }
                if (Date.now() - startedAt > timeout) {
                    self.dom.undoButton.disabled = true;
                    self.dom.redoButton.disabled = true;
                    return;
                }
                window.setTimeout(waitForButtons, 300);
            }());
        },

        /**
         * Refresh references to TinyMCE toolbar undo/redo buttons.
         */
        refreshEditorUndoRedoButtons: function() {
            this.editorUndoButton = document.querySelector(SELECTORS.editorUndoButton);
            this.editorRedoButton = document.querySelector(SELECTORS.editorRedoButton);
        },

        /**
         * Whether a TinyMCE toolbar button is disabled.
         *
         * @param {HTMLElement|null} button
         * @returns {boolean}
         */
        isToolbarButtonDisabled: function(button) {
            if (!button) {
                return true;
            }
            if (button.disabled) {
                return true;
            }
            if (button.getAttribute('aria-disabled') === 'true') {
                return true;
            }
            return button.classList.contains('tox-tbtn--disabled');
        },

        /**
         * Mirror TinyMCE undo/redo availability to panel buttons.
         */
        syncUndoRedoAvailability: function() {
            if (this.activeJobId) {
                this.dom.undoButton.disabled = true;
                this.dom.redoButton.disabled = true;
                return;
            }

            this.dom.undoButton.disabled = this.isToolbarButtonDisabled(this.editorUndoButton);
            this.dom.redoButton.disabled = this.isToolbarButtonDisabled(this.editorRedoButton);
        },

        /**
         * Trigger click on TinyMCE toolbar button when enabled.
         *
         * @param {HTMLElement|null} button
         */
        clickEditorToolbarButton: function(button) {
            if (this.activeJobId) {
                return;
            }
            if (this.isToolbarButtonDisabled(button)) {
                this.syncUndoRedoAvailability();
                return;
            }
            button.click();
            this.syncUndoRedoAvailability();
        },

        /**
         * Observe TinyMCE toolbar state and sync panel buttons in real time.
         */
        startUndoRedoObserver: function() {
            var self = this;
            if (this.undoRedoSyncTimer) {
                window.clearInterval(this.undoRedoSyncTimer);
            }

            this.undoRedoSyncTimer = window.setInterval(function() {
                self.refreshEditorUndoRedoButtons();
                self.syncUndoRedoAvailability();
            }, 400);
        },

        getEditorIframeDocument: function() {
            var iframe = document.querySelector(SELECTORS.editorIframe);
            if (iframe && iframe.contentDocument && iframe.contentDocument.body) {
                return iframe.contentDocument;
            }
            return null;
        },

        setEditorContent: function(content) {
            var textarea = document.getElementById(SELECTORS.editorTextarea);
            var editor = (window.tinymce && textarea) ? window.tinymce.get(textarea.id) : null;
            if (editor) {
                editor.setContent(content || '');
                if (typeof editor.save === 'function') {
                    editor.save();
                }
            } else {
                var iframeDoc = this.getEditorIframeDocument();
                if (iframeDoc) {
                    iframeDoc.body.innerHTML = content || '';
                }
                if (textarea) {
                    textarea.value = content || '';
                }
            }
        },

        setWholeUiLocked: function(locked) {
            var isLocked = Boolean(locked);
            this.panel.setLocked(isLocked);
            this.panel.setPanelControlsEnabled(!isLocked);
            this.dom.panelCancelGenerationButton.disabled = false;

            this.dom.saveButton.disabled = isLocked;
            this.dom.pageCancelButton.setAttribute('aria-disabled', isLocked ? 'true' : 'false');
            this.dom.promptTextarea.disabled = isLocked;
            if (isLocked) {
                this.dom.undoButton.disabled = true;
                this.dom.redoButton.disabled = true;
            } else {
                this.syncUndoRedoAvailability();
            }

            this.setEditorEnabled(!isLocked);
        },

        setEditorEnabled: function(enabled) {
            var textarea = document.getElementById(SELECTORS.editorTextarea);
            var iframe = document.querySelector(SELECTORS.editorIframe);
            var editor = (window.tinymce && textarea) ? window.tinymce.get(textarea.id) : null;

            if (editor) {
                if (typeof editor.setMode === 'function') {
                    editor.setMode(enabled ? 'design' : 'readonly');
                } else if (editor.mode && typeof editor.mode.set === 'function') {
                    editor.mode.set(enabled ? 'design' : 'readonly');
                } else {
                    var body = typeof editor.getBody === 'function' ? editor.getBody() : null;
                    if (body) {
                        body.setAttribute('contenteditable', enabled ? 'true' : 'false');
                    }
                }
            } else {
                var doc = this.getEditorIframeDocument();
                if (doc && doc.body) {
                    doc.body.setAttribute('contenteditable', enabled ? 'true' : 'false');
                }
            }

            if (textarea) {
                textarea.disabled = !enabled;
            }
            if (iframe) {
                iframe.setAttribute('tabindex', enabled ? '0' : '-1');
            }

            document.querySelectorAll('#fitem_id_modulecontent button').forEach(function(button) {
                button.disabled = !enabled;
            });
        },

        startGeneration: function() {
            var self = this;
            var instructions = (this.dom.promptTextarea.value || '').trim();
            if (!instructions) {
                return;
            }

            this.generationToken++;
            var token = this.generationToken;

            this.panel.setGenerateVisualState(true, this.strings.generate, this.strings.generating);
            this.setPanelActionButtonsMode(true);
            this.setWholeUiLocked(true);

            Ajax.call([{
                methodname: 'local_dixeo_editor_start_regenerate_module_content',
                args: {
                    cmid: this.cmid,
                    instructions: instructions
                }
            }])[0].then(function(response) {
                if (token !== self.generationToken) {
                    return;
                }
                if (!response.success || !response.data || !response.data.jobid) {
                    throw new Error(
                        response.error && response.error.message
                            ? response.error.message
                            : self.strings.unexpectedError
                    );
                }
                self.activeJobId = response.data.jobid;
                self.pollJob(token);
            }).catch(function(error) {
                self.unlockAfterGeneration();
                Notification.exception(error);
            });
        },

        pollJob: function(token) {
            var self = this;
            if (!this.activeJobId || token !== this.generationToken) {
                return;
            }

            Ajax.call([{
                methodname: 'local_dixeo_editor_get_regenerate_module_content_status',
                args: {
                    cmid: this.cmid,
                    jobid: this.activeJobId
                }
            }])[0].then(function(response) {
                if (token !== self.generationToken) {
                    return;
                }
                if (!response.success) {
                    throw new Error(
                        response.error && response.error.message
                            ? response.error.message
                            : self.strings.unexpectedError
                    );
                }

                var status = response.data.status;
                if (status === 'completed') {
                    self.applyGeneratedContent(response.data.content || '');
                    self.activeJobId = null;
                    self.unlockAfterGeneration();
                    self.showSuccess();
                    self.dom.promptTextarea.value = '';
                    return;
                }

                if (status === 'failed' || status === 'cancelled') {
                    self.activeJobId = null;
                    self.unlockAfterGeneration();
                    if (status === 'cancelled') {
                        return;
                    }
                    throw new Error(response.data.errormessage || self.strings.unexpectedError);
                }

                self.pollingTimer = window.setTimeout(function() {
                    self.pollJob(token);
                }, 1500);
            }).catch(function(error) {
                if (token !== self.generationToken) {
                    return;
                }
                self.activeJobId = null;
                self.unlockAfterGeneration();
                Notification.exception(error);
            });
        },

        cancelGeneration: function() {
            var self = this;
            var jobid = this.activeJobId;

            // Ignore future responses from any in-flight poll when user cancels.
            this.generationToken++;

            if (this.pollingTimer) {
                window.clearTimeout(this.pollingTimer);
                this.pollingTimer = null;
            }

            if (!jobid) {
                this.unlockAfterGeneration();
                return;
            }

            Ajax.call([{
                methodname: 'local_dixeo_editor_cancel_regenerate_module_content',
                args: {
                    cmid: this.cmid,
                    jobid: jobid
                }
            }])[0].then(function() {
                self.activeJobId = null;
                self.unlockAfterGeneration();
            }).catch(function() {
                // Fallback behavior requested: ignore late response and return UI to normal.
                self.activeJobId = null;
                self.unlockAfterGeneration();
            });
        },

        unlockAfterGeneration: function() {
            this.setWholeUiLocked(false);
            this.panel.setGenerateVisualState(false, this.strings.generate, this.strings.generating);
            this.setPanelActionButtonsMode(false);
            this.activeJobId = null;
        },

        applyGeneratedContent: function(content) {
            this.setEditorContent(content);
            this.refreshEditorUndoRedoButtons();
            this.syncUndoRedoAvailability();
        },

        showSuccess: function() {
            var self = this;
            Templates.renderForPromise('local_dixeo_editor/success_box', {}).then(function(rendered) {
                self.dom.successContainer.innerHTML = rendered.html;
                if (rendered.js) {
                    Templates.runTemplateJS(rendered.js);
                }
                self.dom.successContainer.classList.remove('d-none');
                window.setTimeout(function() {
                    var container = self.dom.successContainer;
                    if (!container || !container.isConnected) {
                        return;
                    }
                    var closeBtn = container.querySelector('button[data-dismiss="alert"], button.close');
                    if (closeBtn) {
                        closeBtn.click();
                    }
                    window.setTimeout(function() {
                        if (container && container.isConnected) {
                            container.classList.add('d-none');
                            container.innerHTML = '';
                        }
                    }, 300);
                }, 5000);
            }).catch(function(error) {
                Notification.exception(error);
            });
        }
    };

    return {
        init: function(cmid, layoutJson) {
            Editor.init(cmid, layoutJson);
        }
    };
});
