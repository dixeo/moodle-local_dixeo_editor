define(['core_user/repository'], function(Repository) {
    'use strict';

    var PREF_NAME = 'local_dixeo_editor_content_panel_state';
    var MOBILE_MQ = '(max-width: 768px)';
    var DEBOUNCE_MS = 500;
    var MIN_PANEL_FR = 0.2;
    var MAX_PANEL_FR = 0.75;
    var MIN_PANEL_BOTTOM_PX = 270;
    var MIN_EDITOR_BOTTOM_PX = 200;
    var SPLITTER_THICKNESS_PX = 8;
    /** Match content_editor_ai_panel min sizes so float panel never exceeds the window. */
    var MIN_FLOAT_PANEL_W = 360;
    var MIN_FLOAT_PANEL_H = 270;
    var FLOAT_VIEWPORT_MARGIN = 8;

    var DOCK_ROW_MODES = ['dixeo-editor-dock-row--left', 'dixeo-editor-dock-row--right', 'dixeo-editor-dock-row--bottom'];

    var DEFAULTS = {
        v: 1,
        mode: 'float',
        ratio: 0.35,
        x: null,
        y: null,
        w: 600,
        h: 370
    };

    /**
     * @param {string} json
     * @returns {Object}
     */
    function parseState(json) {
        try {
            var o = (typeof json === 'string' && json) ? JSON.parse(json) : {};
            return Object.assign({}, DEFAULTS, o);
        } catch (e) {
            return Object.assign({}, DEFAULTS);
        }
    }

    /**
     * @param {Object} editor Dixeo content Editor instance
     * @constructor
     */
    function Layout(editor) {
        this.editor = editor;
        this.state = parseState(editor.layoutInitialJson || '');
        this.layoutRoot = null;
        this.editorSection = null;
        this.panelHost = null;
        this.splitter = null;
        this.mainEl = null;
        this.saveTimer = null;
        this.splitPointerId = null;
        this.splitStart = null;
        this._splitterListenersBound = false;
        this.boundWinResize = this.onWindowResize.bind(this);
        this.boundSplitMove = this.onSplitMove.bind(this);
        this.boundSplitEnd = this.onSplitEnd.bind(this);
    }

    Layout.prototype.isMobile = function() {
        return window.matchMedia(MOBILE_MQ).matches;
    };

    Layout.prototype.getEffectiveMode = function() {
        if (this.isMobile()) {
            return 'bottom';
        }
        var m = this.state.mode;
        if (m === 'left' || m === 'right' || m === 'bottom' || m === 'float') {
            return m;
        }
        return 'float';
    };

    Layout.prototype.isDocked = function() {
        var mode = this.getEffectiveMode();
        return mode === 'left' || mode === 'right' || mode === 'bottom';
    };

    Layout.prototype.scheduleSave = function() {
        var self = this;
        if (this.saveTimer) {
            window.clearTimeout(this.saveTimer);
        }
        this.saveTimer = window.setTimeout(function() {
            self.saveTimer = null;
            self.persist();
        }, DEBOUNCE_MS);
    };

    Layout.prototype.persist = function() {
        var payload = JSON.stringify(this.state);
        Repository.setUserPreference(PREF_NAME, payload).catch(function() {
            // Non-fatal: layout still works for the session.
        });
    };

    Layout.prototype.onFloatGeometryChange = function() {
        if (this.isDocked()) {
            return;
        }
        var p = this.editor.dom.panel;
        if (!p) {
            return;
        }
        var r = p.getBoundingClientRect();
        this.state.x = Math.round(r.left);
        this.state.y = Math.round(r.top);
        this.state.w = Math.round(r.width);
        this.state.h = Math.round(r.height);
        this.scheduleSave();
    };

    Layout.prototype.setBodyClasses = function() {
        var body = document.body;
        var docked = this.isDocked();
        var mode = this.getEffectiveMode();
        body.classList.toggle('dixeo-editor-layout-docked', docked);
        body.classList.toggle('dixeo-editor-layout-dock-left', docked && mode === 'left');
        body.classList.toggle('dixeo-editor-layout-dock-right', docked && mode === 'right');
        body.classList.toggle('dixeo-editor-layout-dock-bottom', docked && mode === 'bottom');
        body.classList.toggle('dixeo-editor-layout-float', !docked);
        body.classList.toggle('dixeo-editor-layout-mobile', this.isMobile());
    };

    Layout.prototype.updateDisplayMenu = function() {
        var menu = document.getElementById('dixeo_editor_display_menu');
        if (menu) {
            menu.classList.toggle('d-none', this.isMobile());
        }
        var effective = this.getEffectiveMode();
        document.querySelectorAll('.dixeo-display-mode-opt').forEach(function(btn) {
            var m = btn.getAttribute('data-display-mode');
            var active = m === effective;
            btn.classList.toggle('active', active);
            btn.disabled = active;
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    };

    Layout.prototype.clearDockFlexStyles = function() {
        if (this.editorSection) {
            this.editorSection.style.flex = '';
        }
        if (this.panelHost) {
            this.panelHost.style.flex = '';
        }
    };

    Layout.prototype.clearPanelInlineGeometry = function() {
        var p = this.editor.dom.panel;
        if (!p) {
            return;
        }
        p.style.position = '';
        p.style.left = '';
        p.style.top = '';
        p.style.right = '';
        p.style.bottom = '';
        p.style.width = '';
        p.style.height = '';
        p.style.maxWidth = '';
        p.style.maxHeight = '';
    };

    Layout.prototype.applyFloatLayout = function() {
        var root = this.layoutRoot;
        if (!root || !this.splitter || !this.editorSection || !this.panelHost) {
            return;
        }
        root.classList.remove('dixeo-editor-layout-mode-docked', 'dixeo-editor-dock-row');
        DOCK_ROW_MODES.forEach(function(c) {
            root.classList.remove(c);
        });
        root.classList.add('dixeo-editor-layout-mode-float');

        this.splitter.classList.add('d-none');
        this.splitter.classList.remove('dixeo-editor-dock-splitter--vertical', 'dixeo-editor-dock-splitter--horizontal');
        this.splitter.setAttribute('aria-hidden', 'true');

        this.clearDockFlexStyles();

        var fab = this.editor.dom.fab;
        var closeBtn = this.editor.dom.panelClose;
        fab.classList.remove('d-none');
        closeBtn.classList.remove('d-none');
        this.clearPanelInlineGeometry();
        var p = this.editor.dom.panel;
        var s = this.state;
        if (s.x !== null && s.x !== undefined && s.y !== null && s.y !== undefined) {
            p.style.position = 'fixed';
            p.style.left = s.x + 'px';
            p.style.top = s.y + 'px';
            p.style.right = 'auto';
            p.style.bottom = 'auto';
        }
        if (s.w) {
            p.style.width = s.w + 'px';
        }
        if (s.h) {
            p.style.height = s.h + 'px';
        }
        this.editor.panel.setLayoutDocked(false);
        this.setBodyClasses();
        this.updateDisplayMenu();
        this.clampFloatPanelToViewport();
    };

    /**
     * Clamp width/height only for a closed panel without saved x/y coordinates.
     *
     * @param {HTMLElement} panel
     * @param {number} effMinW
     * @param {number} effMinH
     * @param {number} maxW
     * @param {number} maxH
     * @returns {boolean} True when handled.
     */
    Layout.prototype.clampClosedSizeOnlyFloatPanel = function(panel, effMinW, effMinH, maxW, maxH) {
        var sw = this.state.w || DEFAULTS.w;
        var sh = this.state.h || DEFAULTS.h;
        var wOnly = Math.max(effMinW, Math.min(sw, maxW));
        var hOnly = Math.max(effMinH, Math.min(sh, maxH));
        if (wOnly === sw && hOnly === sh) {
            return true;
        }
        this.state.w = wOnly;
        this.state.h = hOnly;
        panel.style.width = wOnly + 'px';
        panel.style.height = hOnly + 'px';
        this.scheduleSave();
        return true;
    };

    /**
     * Compute clamped float panel geometry inside the viewport.
     *
     * @param {number} w
     * @param {number} h
     * @param {number} left
     * @param {number} top
     * @param {number} vw
     * @param {number} vh
     * @param {number} m
     * @param {number} effMinW
     * @param {number} effMinH
     * @param {number} maxW
     * @param {number} maxH
     * @returns {{w2: number, h2: number, left2: number, top2: number}}
     */
    Layout.prototype.computeClampedFloatPosition = function(w, h, left, top, vw, vh, m, effMinW, effMinH, maxW, maxH) {
        var w2 = Math.max(effMinW, Math.min(w, maxW));
        var h2 = Math.max(effMinH, Math.min(h, maxH));
        var left2;
        var top2;
        if (vw - w2 <= m * 2) {
            left2 = Math.round((vw - w2) / 2);
        } else {
            left2 = Math.round(Math.min(Math.max(m, left), vw - w2 - m));
        }
        if (vh - h2 <= m * 2) {
            top2 = Math.round((vh - h2) / 2);
        } else {
            top2 = Math.round(Math.min(Math.max(m, top), vh - h2 - m));
        }
        return {w2: w2, h2: h2, left2: left2, top2: top2};
    };

    /**
     * Keep the floating AI panel inside the window (position + size). Updates state when changed.
     * Applies inline styles whenever geometry changes so closed panels stay in sync for the next open.
     */
    Layout.prototype.clampFloatPanelToViewport = function() {
        if (this.getEffectiveMode() !== 'float' || !this.editor || !this.editor.dom || !this.editor.dom.panel) {
            return;
        }
        var p = this.editor.dom.panel;
        var vw = window.innerWidth;
        var vh = window.innerHeight;
        var m = FLOAT_VIEWPORT_MARGIN;
        var maxW = Math.max(m * 2, vw - m * 2);
        var maxH = Math.max(m * 2, vh - m * 2);
        var effMinW = Math.min(MIN_FLOAT_PANEL_W, maxW);
        var effMinH = Math.min(MIN_FLOAT_PANEL_H, maxH);

        var closed = p.classList.contains('d-none');
        var hasSavedPosition = this.state.x !== null && this.state.x !== undefined &&
            this.state.y !== null && this.state.y !== undefined;

        if (closed && !hasSavedPosition) {
            this.clampClosedSizeOnlyFloatPanel(p, effMinW, effMinH, maxW, maxH);
            return;
        }

        var w;
        var h;
        var left;
        var top;

        if (!closed) {
            var rect = p.getBoundingClientRect();
            w = Math.round(rect.width);
            h = Math.round(rect.height);
            left = Math.round(rect.left);
            top = Math.round(rect.top);
        } else {
            w = this.state.w || DEFAULTS.w;
            h = this.state.h || DEFAULTS.h;
            left = Math.round(this.state.x);
            top = Math.round(this.state.y);
        }

        var clamped = this.computeClampedFloatPosition(w, h, left, top, vw, vh, m, effMinW, effMinH, maxW, maxH);
        if (clamped.w2 === w && clamped.h2 === h && clamped.left2 === left && clamped.top2 === top) {
            return;
        }

        p.style.position = 'fixed';
        p.style.right = 'auto';
        p.style.bottom = 'auto';
        p.style.left = clamped.left2 + 'px';
        p.style.top = clamped.top2 + 'px';
        p.style.width = clamped.w2 + 'px';
        p.style.height = clamped.h2 + 'px';

        this.state.x = clamped.left2;
        this.state.y = clamped.top2;
        this.state.w = clamped.w2;
        this.state.h = clamped.h2;
        this.scheduleSave();
    };

    Layout.prototype.clampRatio = function(r) {
        return Math.min(MAX_PANEL_FR, Math.max(MIN_PANEL_FR, r));
    };

    Layout.prototype.clampBottomDockRatio = function(r) {
        if (!this.layoutRoot) {
            return this.clampRatio(r);
        }
        var h = this.layoutRoot.getBoundingClientRect().height;
        if (!h || h < SPLITTER_THICKNESS_PX + 1) {
            return this.clampRatio(r);
        }
        var minFr = Math.max(MIN_PANEL_FR, MIN_PANEL_BOTTOM_PX / h);
        var maxFr = Math.min(MAX_PANEL_FR, (h - SPLITTER_THICKNESS_PX - MIN_EDITOR_BOTTOM_PX) / h);
        if (maxFr < minFr) {
            maxFr = minFr;
        }
        return Math.min(maxFr, Math.max(minFr, r));
    };

    Layout.prototype.ensureSplitterListeners = function() {
        if (this._splitterListenersBound || !this.splitter) {
            return;
        }
        this._splitterListenersBound = true;
        var self = this;
        this.splitter.addEventListener('pointerdown', function(e) {
            if (!self.isDocked() || e.button !== 0) {
                return;
            }
            e.preventDefault();
            self.splitPointerId = e.pointerId;
            self.splitter.setPointerCapture(e.pointerId);
            var mode = self.getEffectiveMode();
            var isHorizontal = mode === 'bottom';
            var rowRect = self.layoutRoot.getBoundingClientRect();
            var panelRect = self.panelHost.getBoundingClientRect();
            self.splitStart = {
                clientPos: isHorizontal ? e.clientY : e.clientX,
                rowSize: isHorizontal ? rowRect.height : rowRect.width,
                panelSize: isHorizontal ? panelRect.height : panelRect.width,
                isHorizontal: isHorizontal
            };
            document.body.classList.add('dixeo-editor-splitting');
        });
        this.splitter.addEventListener('pointermove', this.boundSplitMove);
        this.splitter.addEventListener('pointerup', this.boundSplitEnd);
        this.splitter.addEventListener('pointercancel', this.boundSplitEnd);
    };

    Layout.prototype.onSplitMove = function(e) {
        if (e.pointerId !== this.splitPointerId || !this.splitStart) {
            return;
        }
        var sh = this.splitStart;
        var rowRect = this.layoutRoot.getBoundingClientRect();
        var rowSize = sh.isHorizontal ? rowRect.height : rowRect.width;
        var delta = (sh.isHorizontal ? e.clientY : e.clientX) - sh.clientPos;
        var mode = this.getEffectiveMode();
        var newPanelSize;
        if (mode === 'left') {
            newPanelSize = sh.panelSize + delta;
        } else if (mode === 'right') {
            newPanelSize = sh.panelSize - delta;
        } else {
            newPanelSize = sh.panelSize - delta;
        }
        var fr = rowSize > 0 ? newPanelSize / rowSize : this.state.ratio;
        if (mode === 'bottom') {
            fr = this.clampBottomDockRatio(fr);
        } else {
            fr = this.clampRatio(fr);
        }
        this.state.ratio = fr;
        this.applyDockSizes();
    };

    Layout.prototype.onSplitEnd = function(e) {
        if (e.pointerId !== this.splitPointerId) {
            return;
        }
        this.splitPointerId = null;
        this.splitStart = null;
        document.body.classList.remove('dixeo-editor-splitting');
        try {
            this.splitter.releasePointerCapture(e.pointerId);
        } catch (err) {
            // Ignore.
        }
        this.scheduleSave();
    };

    Layout.prototype.applyDockSizes = function() {
        if (!this.layoutRoot || !this.editorSection || !this.panelHost) {
            return;
        }
        var mode = this.getEffectiveMode();
        var fr = mode === 'bottom' ? this.clampBottomDockRatio(this.state.ratio) : this.clampRatio(this.state.ratio);
        this.state.ratio = fr;
        this.panelHost.style.flex = '0 0 ' + Math.round(fr * 100) + '%';
        this.editorSection.style.flex = '1 1 0';
    };

    Layout.prototype.applyDockLayout = function(mode) {
        var panel = document.getElementById('dixeo_editor_panel');
        var fab = document.getElementById('dixeo_editor_fab');
        var backdrop = document.getElementById('dixeo_editor_panel_backdrop');
        if (!this.layoutRoot || !this.splitter || !this.editorSection || !this.panelHost || !panel) {
            this.applyFloatLayout();
            return;
        }

        var root = this.layoutRoot;
        root.classList.remove('dixeo-editor-layout-mode-float');
        DOCK_ROW_MODES.forEach(function(c) {
            root.classList.remove(c);
        });
        root.classList.add('dixeo-editor-layout-mode-docked', 'dixeo-editor-dock-row', 'dixeo-editor-dock-row--' + mode);

        this.splitter.classList.remove('d-none', 'dixeo-editor-dock-splitter--vertical', 'dixeo-editor-dock-splitter--horizontal');
        if (mode === 'bottom') {
            this.splitter.classList.add('dixeo-editor-dock-splitter--horizontal');
            this.splitter.setAttribute('aria-orientation', 'horizontal');
        } else {
            this.splitter.classList.add('dixeo-editor-dock-splitter--vertical');
            this.splitter.setAttribute('aria-orientation', 'vertical');
        }
        this.splitter.setAttribute('aria-hidden', 'false');

        fab.classList.add('d-none');
        this.editor.dom.panelClose.classList.add('d-none');

        this.clearPanelInlineGeometry();
        panel.classList.remove('d-none');
        panel.setAttribute('aria-hidden', 'false');
        this.editor.panel.isOpen = true;
        this.editor.dom.fab.setAttribute('aria-expanded', 'true');
        backdrop.classList.add('d-none');

        this.applyDockSizes();
        this.ensureSplitterListeners();

        this.editor.panel.setLayoutDocked(true);
        this.setBodyClasses();
        this.updateDisplayMenu();
    };

    Layout.prototype.applyEffectiveMode = function() {
        var mode = this.getEffectiveMode();
        if (mode === 'float') {
            this.applyFloatLayout();
        } else {
            this.applyDockLayout(mode);
        }
    };

    Layout.prototype.setMode = function(mode, saveDesktopMode) {
        if (this.isMobile()) {
            if (saveDesktopMode) {
                this.state.ratio = this.clampRatio(this.state.ratio);
                this.scheduleSave();
            }
            this.applyDockLayout('bottom');
            return;
        }
        if (saveDesktopMode) {
            this.state.mode = mode;
        }
        this.applyEffectiveMode();
        this.scheduleSave();
    };

    Layout.prototype.bindDisplayMenu = function() {
        var self = this;
        document.querySelectorAll('.dixeo-display-mode-opt').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var m = btn.getAttribute('data-display-mode');
                if (!m || btn.disabled) {
                    return;
                }
                self.setMode(m, true);
            });
        });
    };

    Layout.prototype.onWindowResize = function() {
        this.applyEffectiveMode();
        if (this.layoutRoot && this.getEffectiveMode() === 'bottom') {
            this.state.ratio = this.clampBottomDockRatio(this.state.ratio);
            this.applyDockSizes();
        }
        this.updateDisplayMenu();
    };

    Layout.prototype.init = function() {
        this.layoutRoot = document.getElementById('dixeo_editor_layout_root');
        this.editorSection = document.getElementById('dixeo_editor_editor_section');
        this.panelHost = document.getElementById('dixeo_editor_dock_panel_host');
        this.splitter = document.getElementById('dixeo_editor_dock_splitter');
        this.mainEl = document.querySelector('#region-main [role="main"]') ||
            document.querySelector('#page-content [role="main"]') ||
            document.querySelector('[role="main"]');

        this.bindDisplayMenu();
        window.addEventListener('resize', this.boundWinResize);
        this.state.ratio = this.clampRatio(this.state.ratio);

        if (!this.layoutRoot || !this.editorSection || !this.panelHost || !this.splitter) {
            this.updateDisplayMenu();
            return;
        }

        this.ensureSplitterListeners();
        this.applyEffectiveMode();
        if (this.getEffectiveMode() === 'bottom') {
            this.state.ratio = this.clampBottomDockRatio(this.state.ratio);
            this.applyDockSizes();
        }
        this.updateDisplayMenu();
    };

    return {
        /**
         * @param {Object} editor
         * @returns {Layout}
         */
        create: function(editor) {
            var layout = new Layout(editor);
            layout.init();
            return layout;
        }
    };
});
