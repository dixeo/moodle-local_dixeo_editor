define([], function() {
    'use strict';

    /**
     * Floating AI panel UI controller.
     *
     * @param {Object} dom Relevant DOM references.
     * @constructor
     */
    function ContentEditorAIPanel(dom) {
        this.dom = dom;
        this.isOpen = false;
        this.isLocked = false;
        this.isDragging = false;
        this.dragOffsetX = 0;
        this.dragOffsetY = 0;
        this.isResizing = false;
        this.resizeDirection = '';
        this.resizeStartClientX = 0;
        this.resizeStartClientY = 0;
        this.resizeStartRect = null;
        this.activeResizeHandle = null;
        this.activeResizePointerId = null;
        this.minWidth = 360;
        this.minHeight = 320;
    }

    ContentEditorAIPanel.prototype.init = function() {
        var self = this;
        this.setupDrag();
        this.setupResize();

        this.dom.fab.addEventListener('click', function() {
            if (self.isLocked) {
                return;
            }
            self.toggle();
        });

        this.dom.closeButton.addEventListener('click', function() {
            if (self.isLocked) {
                return;
            }
            self.close();
        });

        this.dom.backdrop.addEventListener('click', function() {
            if (self.isLocked) {
                return;
            }
            self.close();
        });
    };

    /**
     * Enable dragging using the panel header.
     */
    ContentEditorAIPanel.prototype.setupDrag = function() {
        var self = this;
        var header = this.dom.panel.querySelector('header');
        if (!header) {
            return;
        }

        header.addEventListener('mousedown', function(event) {
            // Ignore drag start from interactive controls.
            if (event.target.closest('button, a, input, textarea, select')) {
                return;
            }
            if (self.isLocked) {
                return;
            }

            var rect = self.dom.panel.getBoundingClientRect();
            self.isDragging = true;
            self.dragOffsetX = event.clientX - rect.left;
            self.dragOffsetY = event.clientY - rect.top;

            // Switch from anchored (right/bottom) to explicit coordinates.
            self.dom.panel.style.right = 'auto';
            self.dom.panel.style.bottom = 'auto';
            self.dom.panel.style.left = rect.left + 'px';
            self.dom.panel.style.top = rect.top + 'px';

            document.body.classList.add('dixeo-editor-dragging');
            self.dom.panel.classList.add('dixeo-editor-panel-dragging');
            event.preventDefault();
        });

        document.addEventListener('mousemove', function(event) {
            if (!self.isDragging) {
                return;
            }
            var maxLeft = Math.max(0, window.innerWidth - self.dom.panel.offsetWidth);
            var maxTop = Math.max(0, window.innerHeight - self.dom.panel.offsetHeight);
            var left = Math.min(Math.max(0, event.clientX - self.dragOffsetX), maxLeft);
            var top = Math.min(Math.max(0, event.clientY - self.dragOffsetY), maxTop);
            self.dom.panel.style.left = left + 'px';
            self.dom.panel.style.top = top + 'px';
        });

        document.addEventListener('mouseup', function() {
            if (!self.isDragging) {
                return;
            }
            self.isDragging = false;
            document.body.classList.remove('dixeo-editor-dragging');
            self.dom.panel.classList.remove('dixeo-editor-panel-dragging');
        });
    };

    /**
     * Enable corner resize handles.
     */
    ContentEditorAIPanel.prototype.setupResize = function() {
        var self = this;
        var handles = [
            {dir: 'tl', cursor: 'nwse-resize'},
            {dir: 'tr', cursor: 'nesw-resize'},
            {dir: 'bl', cursor: 'nesw-resize'},
            {dir: 'br', cursor: 'nwse-resize'},
            {dir: 'l', cursor: 'ew-resize'},
            {dir: 'r', cursor: 'ew-resize'},
            {dir: 'b', cursor: 'ns-resize'}
        ];

        handles.forEach(function(meta) {
            var handle = document.createElement('span');
            handle.className = 'dixeo-editor-resize-handle dixeo-editor-resize-' + meta.dir;
            handle.setAttribute('aria-hidden', 'true');
            handle.style.cursor = meta.cursor;
            self.dom.panel.appendChild(handle);

            handle.addEventListener('pointerdown', function(event) {
                if (self.isLocked) {
                    return;
                }
                self.beginResize(event.clientX, event.clientY, meta.dir, handle, event.pointerId);
                event.preventDefault();
                event.stopPropagation();
            });
        });

        var moveResize = function(clientX, clientY) {
            if (!self.isResizing || !self.resizeStartRect) {
                return;
            }

            var dx = clientX - self.resizeStartClientX;
            var dy = clientY - self.resizeStartClientY;
            var rect = self.resizeStartRect;

            var left = rect.left;
            var top = rect.top;
            var width = rect.width;
            var height = rect.height;

            if (self.resizeDirection.indexOf('r') !== -1) {
                width = rect.width + dx;
                width = Math.max(self.minWidth, Math.min(width, window.innerWidth - left));
            }
            if (self.resizeDirection.indexOf('l') !== -1) {
                var nextLeft = rect.left + dx;
                var maxLeft = rect.left + rect.width - self.minWidth;
                left = Math.max(0, Math.min(nextLeft, maxLeft));
                width = rect.width - (left - rect.left);
                width = Math.min(width, window.innerWidth - left);
            }
            if (self.resizeDirection.indexOf('b') !== -1) {
                height = rect.height + dy;
                height = Math.max(self.minHeight, Math.min(height, window.innerHeight - top));
            }
            if (self.resizeDirection.indexOf('t') !== -1) {
                var nextTop = rect.top + dy;
                var maxTop = rect.top + rect.height - self.minHeight;
                top = Math.max(0, Math.min(nextTop, maxTop));
                height = rect.height - (top - rect.top);
                height = Math.min(height, window.innerHeight - top);
            }

            self.dom.panel.style.left = left + 'px';
            self.dom.panel.style.top = top + 'px';
            self.dom.panel.style.width = width + 'px';
            self.dom.panel.style.height = height + 'px';
        };

        window.addEventListener('mousemove', function(event) {
            moveResize(event.clientX, event.clientY);
        });

        window.addEventListener('pointermove', function(event) {
            moveResize(event.clientX, event.clientY);
        });

        var endResize = function() {
            if (!self.isResizing) {
                return;
            }
            self.isResizing = false;
            self.resizeDirection = '';
            self.resizeStartRect = null;
            self.dom.panel.classList.remove('dixeo-editor-panel-resizing');
            document.body.classList.remove('dixeo-editor-resizing');
            document.body.classList.remove('dixeo-editor-resizing-active');
            if (self.activeResizeHandle && typeof self.activeResizeHandle.releasePointerCapture === 'function') {
                try {
                    if (self.activeResizeHandle.hasPointerCapture(self.activeResizePointerId)) {
                        self.activeResizeHandle.releasePointerCapture(self.activeResizePointerId);
                    }
                } catch (e) {
                    // Ignore capture-release issues.
                }
            }
            self.activeResizeHandle = null;
            self.activeResizePointerId = null;
        };

        window.addEventListener('mouseup', endResize);
        window.addEventListener('pointerup', endResize);
        window.addEventListener('pointercancel', endResize);
    };

    /**
     * Start resize gesture.
     *
     * @param {number} clientX Pointer x.
     * @param {number} clientY Pointer y.
     * @param {string} direction Handle direction.
     * @param {HTMLElement|null} handle Resize handle element.
     * @param {number|null} pointerId Pointer id.
     */
    ContentEditorAIPanel.prototype.beginResize = function(clientX, clientY, direction, handle, pointerId) {
        var rect = this.dom.panel.getBoundingClientRect();
        this.isResizing = true;
        this.resizeDirection = direction;
        this.resizeStartClientX = clientX;
        this.resizeStartClientY = clientY;
        this.resizeStartRect = rect;
        this.activeResizeHandle = handle || null;
        this.activeResizePointerId = typeof pointerId === 'number' ? pointerId : null;

        this.dom.panel.style.right = 'auto';
        this.dom.panel.style.bottom = 'auto';
        this.dom.panel.style.left = rect.left + 'px';
        this.dom.panel.style.top = rect.top + 'px';
        this.dom.panel.style.width = rect.width + 'px';
        this.dom.panel.style.height = rect.height + 'px';
        this.dom.panel.classList.add('dixeo-editor-panel-resizing');

        if (this.activeResizeHandle &&
            this.activeResizePointerId !== null &&
            typeof this.activeResizeHandle.setPointerCapture === 'function') {
            try {
                this.activeResizeHandle.setPointerCapture(this.activeResizePointerId);
            } catch (e) {
                // Ignore capture issues; mousemove/pointermove fallback still active.
            }
        }

        document.body.classList.add('dixeo-editor-resizing');
        document.body.classList.add('dixeo-editor-resizing-active');
    };

    ContentEditorAIPanel.prototype.open = function() {
        this.isOpen = true;
        this.dom.panel.classList.remove('d-none');
        this.dom.panel.setAttribute('aria-hidden', 'false');
        this.dom.fab.setAttribute('aria-expanded', 'true');
    };

    ContentEditorAIPanel.prototype.close = function() {
        this.isOpen = false;
        this.dom.panel.classList.add('d-none');
        this.dom.backdrop.classList.add('d-none');
        this.dom.panel.setAttribute('aria-hidden', 'true');
        this.dom.fab.setAttribute('aria-expanded', 'false');
    };

    ContentEditorAIPanel.prototype.toggle = function() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    };

    /**
     * Lock/unlock AI panel interactions.
     *
     * @param {boolean} locked Lock state.
     */
    ContentEditorAIPanel.prototype.setLocked = function(locked) {
        this.isLocked = Boolean(locked);
        this.dom.panel.classList.toggle('is-locked', this.isLocked);
        this.dom.backdrop.classList.toggle('is-locked', this.isLocked);
        this.dom.backdrop.classList.toggle('d-none', !this.isLocked);
        // Header close button is kept disabled while locked.
        this.dom.closeButton.disabled = this.isLocked;
    };

    /**
     * Switch generate button visuals between idle and generating.
     *
     * @param {boolean} generating Generating state.
     * @param {string} generateText Idle text.
     * @param {string} generatingText Loading text.
     */
    ContentEditorAIPanel.prototype.setGenerateVisualState = function(generating, generateText, generatingText) {
        this.dom.generateLogo.classList.toggle('d-none', generating);
        this.dom.generateSpinner.classList.toggle('d-none', !generating);
        this.dom.generateLabel.textContent = generating ? generatingText : generateText;
        this.dom.generateButton.disabled = generating;
    };

    /**
     * Enable/disable all panel interactive elements except explicit exclusions.
     *
     * @param {boolean} enabled Enabled state.
     */
    ContentEditorAIPanel.prototype.setPanelControlsEnabled = function(enabled) {
        var controls = this.dom.panel.querySelectorAll('button, textarea');
        controls.forEach(function(control) {
            control.disabled = !enabled;
        });

        // Header close button remains controlled by lock state.
        this.dom.closeButton.disabled = this.isLocked;

        // Keep panel Cancel action available even while locked.
        if (this.dom.panelCancelGenerationButton) {
            this.dom.panelCancelGenerationButton.disabled = false;
        }
    };

    return ContentEditorAIPanel;
});
