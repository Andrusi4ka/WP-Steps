/*
 * WP Steps editor (the meta box of WP_Steps_Admin::editor()). One object holds the settings
 * (boot.settings, the same keys as WP_Steps_Settings); every change goes into it and, as JSON,
 * into the hidden #wpst-data that the post form saves (WP_Steps_Admin::save() sanitizes it).
 *
 * Look as WP Swiper and WP Banner: tiles across the top (Settings, the steps, Add step), the
 * menu of the chosen tile's blocks, the chosen block (grey cards), the preview. Settings a
 * design does not use are greyed, their values kept. Per-device sizes: the device icons by the
 * label switch the device for the whole editor and the preview; tablet / phone left empty take
 * the larger screen's value (a green dot: a value of its own).
 *
 * The preview (admin-ajax, WP_Steps_Admin::preview()) is drawn again on Update (lit while it is
 * behind the settings) and on its own when the design changes; a device only resizes it.
 */
(function () {
    'use strict';
    const app = document.getElementById('wpst-app');
    if (!app) return;
    const boot = JSON.parse(document.getElementById('wpst-boot').textContent);
    const $ = id => document.getElementById('wpst-' + id);
    const data = boot.settings;
    const DESIGNS = {};
    boot.designs.forEach(l => { DESIGNS[l.id] = l; });
    const ICONS = boot.icons;
    const images = boot.images && !Array.isArray(boot.images) ? boot.images : {};
    const DEVICES = {desktop:['desktop', 'Desktop'], tablet:['tablet', 'Tablet'], mobile:['smartphone', 'Mobile']};
    const UP = {tablet:'desktop', mobile:'tablet'};
    // The preview's screen widths (as the theme's breakpoints: tablet up to 1024px, phone up to 767px).
    const SCREENS = {desktop:1280, tablet:820, mobile:390};
    const {parseColor, colorValue, openPicker, closePicker} = window.WPST_Color;
    window.WPST_Color.palette(boot.palette);

    const store = {
        get(key) { try { return window.localStorage.getItem(key); } catch (e) { return null; } },
        set(key, value) { try { window.localStorage.setItem(key, value); } catch (e) { /* private window */ } }
    };
    let device = DEVICES[store.get('wpstDevice')] ? store.get('wpstDevice') : 'desktop';
    // -1: Settings; otherwise the step being edited.
    let selected = -1;
    const shown = {settings:'layout', step:'title'};
    let dirty = false, submitting = false;

    const escape = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[c]));
    const current = () => selected < 0 ? data : data.steps[selected];
    const design = () => DESIGNS[data.design] || boot.designs[0];
    // Does the chosen design use this setting? (Those not in boot.optional apply to all.)
    const uses = key => boot.optional.indexOf(key) === -1 || design().supports.indexOf(key) !== -1;

    function sync() { $('data').value = JSON.stringify(data); }
    function changed(redrawTiles) {
        sync();
        dirty = true;
        setStale(true);
        if (redrawTiles) drawTiles();
    }

    /* ---------- Colors of the steps (as WP_Steps_Settings::step_colors()) ---------- */
    function stepColors() {
        const n = data.steps.length, a = parseColor(data.color_from) || {hex:'#000000', alpha:100}, b = parseColor(data.color_to) || a;
        const rgb = c => [1, 3, 5].map(i => parseInt(c.hex.substr(i, 2), 16)).concat(c.alpha);
        const from = rgb(a), to = rgb(b);
        return data.steps.map((step, i) => {
            if (step.color) return step.color;
            const t = n > 1 ? i / (n - 1) : 0, mix = from.map((v, k) => v + (to[k] - v) * t);
            return colorValue('#' + mix.slice(0, 3).map(v => ('0' + Math.round(v).toString(16)).slice(-2)).join(''), Math.round(mix[3]));
        });
    }
    const autoColor = i => {
        const own = data.steps[i].color;
        data.steps[i].color = '';
        const color = stepColors()[i];
        data.steps[i].color = own;
        return color;
    };
    const numberText = i => data.number_format === '01' ? String(i + 1).padStart(2, '0') : String(i + 1);

    /* ---------- Fields ---------- */
    const tip = text => '<span class="wpst-tip" tabindex="0" data-tip="' + escape(text) + '" aria-label="' + escape(text) + '"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span></span>';
    const card = (title, body) => body ? '<div class="wpst-card"><p class="wpst-subheading">' + title + '</p>' + body + '</div>' : '';
    const note = text => text ? '<p class="description">' + text + '</p>' : '';
    // The field's "i": its help, and why it is greyed (one "i" for both).
    const tips = (o, off) => off ? tip((o.tip ? o.tip + ' ' : '') + 'The ' + design().name + ' design does not use this.') : (o.tip ? tip(o.tip) : '');
    // A field: o.uses (an optional setting: greyed when the design does not use it), o.tip, o.note.
    // The Settings' fields the design does not use are not drawn (2026-11-24, user's request: shown only where they apply; their
    // values kept); a step's are greyed (Gap, Thickness outside Wave).
    // 2026-11-24, user's request: a step's too, and fields off because of another choice (o.disabled), are not drawn either.
    const hiddenHere = o => !!(o && ((o.uses && !uses(o.uses)) || o.disabled));
    // A row of fields: not drawn when none of them is.
    const row = (cls, inner) => inner ? '<div class="' + cls + '">' + inner + '</div>' : '';
    function field(key, label, control, o, extraHead) {
        o = o || {};
        if (hiddenHere(o)) return '';
        const off = o.uses && !uses(o.uses);
        return '<div class="wpst-field' + (off || o.disabled ? ' is-disabled' : '') + '" data-field="' + key + '"><div class="wpst-field__head"><label for="wpst-f-' + key + '">' + label + '</label>' + tips(o, off) + (extraHead || '') + '</div>'
            + control + note(o.note) + '</div>';
    }
    const value = key => current()[key];
    function textField(key, label, o) {
        return field(key, label, '<input type="text" id="wpst-f-' + key + '" data-key="' + key + '" value="' + escape(value(key)) + '"' + (o && o.placeholder ? ' placeholder="' + escape(o.placeholder) + '"' : '') + '>', o);
    }
    function textArea(key, label, o) {
        return field(key, label, '<textarea id="wpst-f-' + key + '" data-key="' + key + '" rows="3">' + escape(value(key)) + '</textarea>', o);
    }
    // o.shown: the value shown when the field has none of its own (the one in effect).
    function select(key, label, options, o) {
        const now = o && o.shown !== undefined ? String(o.shown) : String(value(key));
        // Options as [value, text] pairs keep their order (an object puts number-like keys such as '300' before '').
        return field(key, label, '<select id="wpst-f-' + key + '" data-key="' + key + '">' + (Array.isArray(options) ? options : Object.entries(options)).map(([v, text]) =>
            '<option value="' + escape(v) + '"' + (now === v ? ' selected' : '') + '>' + escape(text) + '</option>').join('') + '</select>', o);
    }
    // Buttons side by side, one pressed (as WP Banner's Color / Gradient).
    function choice(key, label, options, o) {
        return field(key, label, '<div class="wpst-choice" role="group" id="wpst-f-' + key + '">' + Object.entries(options).map(([v, text]) =>
            '<button type="button" data-choice="' + key + '" data-value="' + escape(v) + '" aria-pressed="' + (String(value(key)) === v) + '">' + escape(text) + '</button>').join('') + '</div>', o);
    }
    function toggle(key, label, o) {
        o = o || {};
        if (hiddenHere(o)) return '';
        const off = o.uses && !uses(o.uses), on = o.checked !== undefined ? o.checked : value(key);
        return '<div class="wpst-field wpst-field--toggle' + (off || o.disabled ? ' is-disabled' : '') + '" data-field="' + key + '"><label class="wpst-toggle"><input type="checkbox" id="wpst-f-' + key + '" data-key="' + key + '" data-type="bool"' + (on ? ' checked' : '') + '>'
            + '<span class="wpst-toggle__track" aria-hidden="true"></span><span class="wpst-toggle__label">' + label + '</span></label>'
            + tips(o, off) + note(o.note) + '</div>';
    }
    // A slider beside its number (one value for all screens).
    function number(key, label, o) {
        const [min, max] = boot.limits[key], v = escape(value(key));
        return field(key, label, '<div class="wpst-field__row"><input type="range" data-range-for="' + key + '" min="' + min + '" max="' + (o.to || max) + '" step="' + (o.step || 1) + '" value="' + v + '" aria-label="' + escape(label) + '">'
            + '<span class="wpst-number"><input type="number" id="wpst-f-' + key + '" data-key="' + key + '" data-type="number" min="' + min + '" max="' + max + '" step="' + (o.step || 1) + '" value="' + v + '"><span>px</span></span></div>', o);
    }
    // The size on the device being edited; tablet / phone empty: the larger screen's (its placeholder).
    const dkey = (base, d) => d === 'desktop' ? base : base + '_' + d;
    function eff(base, d) {
        while (d !== 'desktop' && (data[dkey(base, d)] === null || data[dkey(base, d)] === undefined)) d = UP[d];
        return data[dkey(base, d)];
    }
    function deviceIcons(base) {
        const own = {desktop: Number(data[base]) !== Number(boot.defaults[base]), tablet: data[base + '_tablet'] != null, mobile: data[base + '_mobile'] != null};
        return '<span class="wpst-devices" role="group" aria-label="Device">' + Object.entries(DEVICES).map(([d, [icon, name]]) =>
            '<button type="button" class="wpst-devices__btn' + (d === device ? ' is-active' : '') + (own[d] ? ' has-value' : '') + '" data-device="' + d + '" aria-pressed="' + (d === device) + '" aria-label="' + name + '" title="' + name + '"><span class="dashicons dashicons-' + icon + '" aria-hidden="true"></span></button>').join('') + '</span>';
    }
    function respNumber(base, label, o) {
        const [min, max] = boot.limits[base], key = dkey(base, device), own = data[key], shownValue = eff(base, device);
        // No reset button (2026-11-24, user's request: set by hand always); tablet / phone emptied follow the larger screen.
        return field(key, label, '<div class="wpst-field__row"><input type="range" data-range-for="' + key + '" min="' + min + '" max="' + (o.to || max) + '" value="' + escape(shownValue) + '" aria-label="' + escape(label) + '">'
            + '<span class="wpst-number"><input type="number" id="wpst-f-' + key + '" data-key="' + key + '" data-base="' + base + '" data-type="number"' + (device === 'desktop' ? '' : ' data-inherit="1"') + ' min="' + min + '" max="' + max + '" value="' + (own == null || (device === 'desktop' && Number(own) === min) ? '' : escape(own)) + '" placeholder="' + (Number(shownValue) === min ? '' : escape(shownValue)) + '"><span>px</span></span></div>', o, deviceIcons(base));
    }
    // The reset icon: WP Banner's (its assets/images/reset.svg), in the button's color.
    const RESET_ICON = '<svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 693.59 679.92" fill="currentColor"><path fill-rule="evenodd" d="M449.46,679.92c-11.95,0-21.67-9.72-21.67-21.67v-160c0-11.95,9.72-21.67,21.67-21.67s21.67,9.72,21.67,21.67v108.57l8.13-6.25c30.32-22.87,68.09-51.35,100.68-88.03,45.8-51.53,68.06-104.44,68.06-161.75,0-173.9-129.48-277.42-249.79-300-5.99-1.12-11.17-4.51-14.61-9.54-3.44-5.03-4.71-11.09-3.59-17.07,2.02-10.78,11.43-18.6,22.37-18.6,1.41,0,2.84.13,4.24.4,77.21,14.5,148.31,54.46,200.2,112.53,56.76,63.53,86.76,143.85,86.76,232.28,0,37.15-7.95,73.47-23.64,107.97-13.06,28.72-31.87,57-55.93,84.07-30.46,34.27-65.05,62.08-94.87,84.77l-11.8,8.98h102.11c11.95,0,21.67,9.72,21.67,21.67s-9.72,21.67-21.67,21.67h-160ZM295.79,675.16c-1.3,0-2.62-.11-3.92-.34-78.22-13.54-150.45-53.15-203.39-111.51C30.59,499.49,0,418.51,0,329.12c0-37.15,7.95-73.48,23.64-107.97,13.06-28.72,31.87-57,55.93-84.07,30.47-34.29,65.06-62.09,94.87-84.77l11.8-8.98h-102.11c-11.95,0-21.67-9.72-21.67-21.67S72.18,0,84.13,0h160c11.95,0,21.67,9.72,21.67,21.67v160c0,11.95-9.72,21.67-21.67,21.67s-21.67-9.72-21.67-21.67v-108.44l-8.15,6.13c-30.35,22.89-68.09,51.36-100.67,88.02-45.79,51.53-68.05,104.44-68.05,161.75,0,176.42,131.69,279.58,254.05,300.77,6,1.04,11.24,4.35,14.74,9.33,3.51,4.98,4.87,11.02,3.83,17.03-1.9,10.96-11.33,18.91-22.43,18.91Z"/></svg>';
    /*
     * A color (WP_Steps_Settings::color()): a swatch that opens Pickr with the palette, opacity, Cancel and Default.
     * o.def '' = automatic: the field stays empty and its swatch shows o.fallback, the color it stands for.
     */
    function color(key, label, o) {
        const v = value(key) || '', c = parseColor(v) || parseColor(o.fallback) || parseColor(o.def) || {hex:'#000000', alpha:100};
        return field(key, label, '<span class="wpst-color" data-alpha="1" data-default="' + escape(o.def) + '" data-fallback="' + escape(o.fallback || '') + '">'
            + '<input type="hidden" data-key="' + key + '" value="' + escape(v) + '">'
            + '<button type="button" class="wpst-color__swatch" id="wpst-f-' + key + '" aria-expanded="false"><span class="wpst-color__chip" aria-hidden="true" style="box-shadow:inset 0 0 0 60px ' + escape(colorValue(c.hex, c.alpha)) + '"></span></button>'
            + (o.def === '' && o.auto !== false ? (selected >= 0 && o.reset === false ? '' : selected >= 0
                // A step's (2026-11-23, user's request): WP Banner's reset icon instead of "Automatic", greyed while it is automatic.
                ? '<button type="button" class="wpst-icon-button wpst-color__reset" data-color-reset="' + key + '" title="Reset: automatic" aria-label="' + escape(label) + ' — Reset: automatic"' + (v ? '' : ' disabled') + '>' + RESET_ICON + '</button>'
                // The Settings': no "Automatic" word any more (2026-11-24, user's request); the swatch shows the color in effect.
                : '') : '') + '</span>', o);
    }

    /*
     * A step's Title and Text, each in WordPress's editor, in tabs of their own (2026-11-23, user's requests: as WP Swiper's caption
     * editor; then a Title tab with the same editor and its Tag). Text: Paragraph / Heading, bold, italic, underline, text color
     * (the palette), alignment, bulleted and numbered lists, link / unlink, clear, undo, redo; Visual and Code. Title: the same
     * but Paragraph / Heading and the lists (its Tag sets its element, which holds no paragraphs: WP_Steps_Settings::rich_title()).
     * One editor at a time, the shown tab's: started when the Content block is drawn, removed before it is drawn again. A text
     * written before it (plain lines) opens with its line breaks; a title opens in a paragraph with its alignment.
     */
    const wpEditor = () => window.wp && window.wp.editor && window.wp.editor.initialize ? window.wp.editor : null;
    // The editors running (2026-11-24: the Side text tab has two).
    let editorOn = [];
    const EDITORS = {
        title: {toolbar:'bold,italic,underline,forecolor,alignleft,aligncenter,alignright,link,unlink,removeformat,undo,redo', quicktags:'strong,em,link', height:70},
        text: {toolbar:'formatselect,bold,italic,underline,forecolor,alignleft,aligncenter,alignright,bullist,numlist,link,unlink,removeformat,undo,redo', quicktags:'strong,em,link,ul,ol,li', height:120},
        // Side text (2026-11-24, user's request): its title as the Title's editor (no alignment: none is kept for it), its text as the Text's.
        aside_title: {toolbar:'bold,italic,underline,forecolor,link,unlink,removeformat,undo,redo', quicktags:'strong,em,link', height:60},
        aside_text: {toolbar:'formatselect,bold,italic,underline,forecolor,alignleft,aligncenter,alignright,bullist,numlist,link,unlink,removeformat,undo,redo', quicktags:'strong,em,link,ul,ol,li', height:110}
    };
    // A title or text as HTML for the editor.
    function editorHtml(key) {
        const step = current(), t = step[key] || '';
        if (key === 'title') return '<p' + (step.title_align ? ' style="text-align: ' + step.title_align + '"' : '') + '>' + t + '</p>';
        if (key === 'aside_title') return '<p>' + t + '</p>';
        return /<(p|h[1-6]|ul|ol)[\s>]/i.test(t) ? t : t.replace(/\r?\n/g, '<br>');
    }
    function editorField(key, label) {
        const id = 'wpst-f-' + key;
        // No heading over it (user's request: the tab names it); its label kept for screen readers. The Side text tab's two: named.
        const head = /^aside_/.test(key) ? '<div class="wpst-field__head"><label for="' + id + '">' + label + '</label></div>' : '<label class="screen-reader-text" for="' + id + '">' + label + '</label>';
        return '<div class="wpst-field wpst-field--editor" data-field="' + key + '">' + head
            + '<textarea id="' + id + '" data-key="' + key + '" data-editor="' + key + '" class="wp-editor-area" rows="' + (/title$/.test(key) ? 3 : 6) + '">' + escape(editorHtml(key)) + '</textarea></div>';
    }
    function stopEditor() {
        if (!editorOn.length) return;
        const on = editorOn;
        editorOn = [];
        on.forEach(key => { try { wpEditor().remove('wpst-f-' + key); } catch (e) { /* not started */ } });
    }
    // The editor's HTML into the step (a title: its words and alignment, as the server keeps them; its tile shows it).
    function storeEditor(key, html) {
        const step = selected >= 0 ? data.steps[selected] : null;
        if (!step) return;
        // The side title: its inline words (paragraphs as line breaks), as the server keeps them.
        if (key === 'aside_title') {
            const words = html.replace(/<\/(?:p|div|h[1-6]|li)>\s*(?:<\/?(?:ul|ol)\b[^>]*>\s*)*<(?:p|div|h[1-6]|li)\b[^>]*>/gi, '<br>').replace(/<\/?(?:p|div|h[1-6]|ul|ol|li)\b[^>]*>/gi, '').trim();
            if (step.aside_title === words) return;
            step.aside_title = words;
            changed(false);
            markTab(key);
            return;
        }
        if (key === 'title') {
            const m = /<(?:p|div|h[1-6])\b[^>]*text-align:\s*(left|center|right)/i.exec(html);
            const words = html.replace(/<\/(?:p|div|h[1-6]|li)>\s*(?:<\/?(?:ul|ol)\b[^>]*>\s*)*<(?:p|div|h[1-6]|li)\b[^>]*>/gi, '<br>').replace(/<\/?(?:p|div|h[1-6]|ul|ol|li)\b[^>]*>/gi, '').trim();
            if (step.title === words && step.title_align === (m ? m[1].toLowerCase() : '')) return;
            step.title = words;
            step.title_align = m ? m[1].toLowerCase() : '';
            changed(true);
            drawModalHead();
            markTab(key);
            return;
        }
        if (step[key] === html) return;
        step[key] = html;
        changed(false);
        markTab(key);
    }
    function markTab(key) {
        if (/^aside_/.test(key)) key = 'aside';
        const tab = document.querySelector('#wpst-modal-tabs [data-step-tab="' + key + '"]');
        if (tab) tab.classList.toggle('has-value', tabFilled(key));
    }
    function startEditor() {
        const boxes = [...$('settings').querySelectorAll('[data-editor]')];
        if (!wpEditor() || !boxes.length || editorOn.length) return;
        const map = Array.isArray(boot.textColors) ? boot.textColors : [];
        boxes.forEach(box => {
        const key = box.dataset.editor, o = EDITORS[key];
        editorOn.push(key);
        wpEditor().initialize(box.id, {
            tinymce: window.tinymce ? Object.assign(map.length ? {forecolor_map:map, forecolor_cols:8, forecolor_rows:Math.ceil((map.length / 2 + 1) / 8)} : {}, {
                toolbar1: o.toolbar, toolbar2: '', height: o.height,
                block_formats: 'Paragraph=p;Heading 1=h1;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6',
                setup: editor => editor.on('input change keyup undo redo ExecCommand', () => { if (editor.isDirty()) storeEditor(key, editor.getContent()); })
            }) : false,
            quicktags: {buttons: o.quicktags},
            mediaButtons: false
        });
        });
    }
    // Has the step's title or text any words (its tab's dot)?
    const tabFilled = key => key === 'aside' ? !!(plainTitle(current().aside_title) || plainTitle(current().aside_text)) : !!plainTitle(current()[key]);
    // A title as plain words (its tile, the question before deleting it).
    const plainTitle = html => { const box = document.createElement('div'); box.innerHTML = html || ''; return box.textContent.trim(); };
    // Font size, Font weight and Color of the title or the text (title_* / text_*).
    const WEIGHT_LIST = [['', 'Default'], ['300', 'Light 300'], ['400', 'Regular 400'], ['500', 'Medium 500'], ['600', 'Semibold 600'], ['700', 'Bold 700'], ['800', 'Extra bold 800']];
    const WEIGHT_NAMES = Object.fromEntries(WEIGHT_LIST);
    function styleRow(kind) {
        const fallback = kind === 'title' ? (data.title_color || '#1f2937') : (data.text_color || '#4b5563');
        return '<div class="wpst-row3">' + stepSize(kind + '_size', 'Font size')
            + select(kind + '_weight', 'Font weight', WEIGHT_LIST)
            + color(kind + '_color', 'Color', {def:'', reset:false, fallback, tip:'Until one is chosen: white on filled cards, ' + (kind === 'title' ? 'dark' : 'grey') + ' otherwise.'}) + '</div>';
    }
    /*
     * The side title's / text's Font size, weight and color on the device edited (2026-11-24, user's request): on tablet / phone empty
     * = the larger screen's (the weight's first choice and the color's swatch say which), else the usual.
     */
    function asideRow(k) {
        const step = current(), up = base => { for (let d = UP[device]; d; d = UP[d]) { const v = step[dkey(base, d)]; if (v) return v; } return ''; };
        const title = k === 'aside_title', usualSize = Math.round(Number(eff(title ? 'title_size' : 'text_size', device)) * (title ? .95 : .92));
        const wUp = device === 'desktop' ? '' : up(k + '_weight');
        const weights = device === 'desktop' ? WEIGHT_LIST : [['', 'As ' + DEVICES[UP[device]][1] + ' (' + (WEIGHT_NAMES[wUp] || 'Default') + ')']].concat(WEIGHT_LIST.filter(([v]) => v));
        const usualColor = title ? stepColors()[selected] : (data.text_color || '#6b7280');
        return '<div class="wpst-row3">' + stepSize(k + '_size', 'Font size', {min:title ? 10 : 10, max:title ? 80 : 40, usual:usualSize, tip:'Empty: the larger screen\'s, else the usual (its placeholder).'})
            + select(dkey(k + '_weight', device), 'Font weight', weights, {shown:step[dkey(k + '_weight', device)] || ''})
            + color(dkey(k + '_color', device), 'Color', {def:'', fallback:(device === 'desktop' ? '' : up(k + '_color')) || usualColor}) + '</div>';
    }
    // A step's own size on the device edited (Font size under its Text): empty = its larger screen's, then Sizes' (its placeholder).
    function stepInherited(base, d, usual) {
        const step = current();
        for (let up = UP[d]; up; up = UP[up]) { if (step[dkey(base, up)] != null) return step[dkey(base, up)]; }
        return usual !== undefined ? usual : eff(base === 'icon_size' ? 'icon' : base, d);
    }
    function stepIcons(base) {
        const step = current();
        return '<span class="wpst-devices" role="group" aria-label="Device">' + Object.entries(DEVICES).map(([d, [icon, name]]) =>
            '<button type="button" class="wpst-devices__btn' + (d === device ? ' is-active' : '') + (step[dkey(base, d)] != null ? ' has-value' : '') + '" data-device="' + d + '" aria-pressed="' + (d === device) + '" aria-label="' + name + '" title="' + name + '"><span class="dashicons dashicons-' + icon + '" aria-hidden="true"></span></button>').join('') + '</span>';
    }
    function stepSize(base, label, o) {
        o = o || {};
        const [min, max] = o.max !== undefined ? [o.min, o.max] : boot.limits[base === 'icon_size' ? 'icon' : base], key = dkey(base, device), own = current()[key];
        return field(key, label, '<span class="wpst-number"><input type="number" id="wpst-f-' + key + '" data-key="' + key + '" data-type="number" data-own-size="' + base + '"' + (o.usual !== undefined ? ' data-usual="' + o.usual + '"' : '') + ' min="' + min + '" max="' + max + '" value="' + (own == null ? '' : escape(own)) + '" placeholder="' + escape(stepInherited(base, device, o.usual)) + '"><span>' + (o.unit || 'px') + '</span></span>',
            {uses:o.uses, disabled:o.disabled, tip:o.tip || 'Empty: the usual size (its placeholder), or this step\'s size on the larger screen.'}, stepIcons(base));
    }

    /*
     * The step's icon as a select (2026-11-23, user's request): the icon chosen, its name and an arrow; open, a panel with a search
     * (by name) and the grid of all the icons, None first. Arrows move in the grid, Enter chooses, Esc or a click outside closes.
     * A choice: the click handler on [data-icon] (stores it, redraws the block, so the panel closes).
     */
    /*
     * A shadow's parts (2026-11-23, user's request: as WP Swiper's): Horizontal, Vertical, Blur radius, Spread radius (px) in a row,
     * Color under them. prefix: card_shadow (the set's) or icon_shadow (the step's: empty = as before, its placeholder).
     */
    const SHADOW_DEFAULTS = {icon_shadow: [0, 8, 18, -8, 'rgba(15,23,42,0.5)']};
    function shadowFields(prefix) {
        const parts = [['x', 'Horizontal (px)', -100, 100], ['y', 'Vertical (px)', -100, 100], ['blur', 'Blur radius (px)', 0, 100], ['spread', 'Spread radius (px)', -50, 50]];
        // A step's card shadow: the cards' parts as its placeholders (2026-11-25).
        const usual = prefix === 'shadow' ? [data.card_shadow_x, data.card_shadow_y, data.card_shadow_blur, data.card_shadow_spread, data.card_shadow_color] : SHADOW_DEFAULTS[prefix], o = current();
        return '<div class="wpst-shadow"><div class="wpst-row4">' + parts.map(([p, label, min, max], i) => {
            const key = prefix + '_' + p, v = o[key];
            return field(key, label, '<input type="number" id="wpst-f-' + key + '" data-key="' + key + '" data-type="number" min="' + min + '" max="' + max + '" step="1" value="' + (v == null ? '' : escape(v)) + '"' + (usual ? ' placeholder="' + usual[i] + '"' : '') + '>');
        }).join('')
            // Its Color in the same row (2026-11-24: the step's window fits without a scroll).
            + color(prefix + '_color', 'Color', {def:usual ? '' : boot.defaults.card_shadow_color, reset:false, fallback:usual ? usual[4] : boot.defaults.card_shadow_color}) + '</div></div>';
    }
    /*
     * The circle's corners as WP Banner's Radius box: a field at each corner (its number, px and % beside it), the link in the
     * middle (the four together), the frame drawn with those corners; per device (empty: the larger screen's, else a circle).
     */
    const CORNERS = ['tl', 'tr', 'br', 'bl'], CORNER_NAMES = {tl:'Top left', tr:'Top right', br:'Bottom right', bl:'Bottom left'}, RADIUS_MAX = {px:200, '%':50};
    const LINK_ICON = '<svg aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><g fill="currentColor"><path d="M689.24,165.78c39.47-39.47,103.68-39.47,143.15,0,39.47,39.47,39.47,103.68,0,143.15l-285.65,285.65,60.41,60.41,285.65-285.65c72.9-72.9,72.9-191.08,0-263.98h0c-72.9-72.9-191.08-72.9-263.98,0l-99.16,99.16,60.41,60.41,99.16-99.16Z"/><path d="M399.65,741.67l-99.16,99.16c-39.47,39.47-103.68,39.47-143.15,0-39.47-39.47-39.47-103.68,0-143.15l284.33-284.33-60.41-60.41L96.93,637.26c-72.9,72.9-72.9,191.08,0,263.98h0c72.9,72.9,191.08,72.9,263.98,0l99.16-99.16-60.41-60.41Z"/><path d="M157.35,308.93c-39.47-39.47-39.47-103.68,0-143.15,39.47-39.47,103.68-39.47,143.15,0l285.65,285.65,60.41-60.41L360.91,105.37c-72.9-72.9-191.08-72.9-263.98,0h0c-72.9,72.9-72.9,191.08,0,263.98l99.16,99.16,60.41-60.41-99.16-99.16Z"/><path d="M733.24,598.52l99.16,99.16c39.47,39.47,39.47,103.68,0,143.15s-103.68,39.47-143.15,0l-284.33-284.33-60.41,60.41,284.33,284.33c72.9,72.9,191.08,72.9,263.98,0h0c72.9-72.9,72.9-191.08,0-263.98l-99.16-99.16-60.41,60.41Z"/></g></svg>';
    // A corner on a device: its own, else the larger screen's, else null (a circle).
    function cornerValue(step, c, d) {
        for (let dv = d; dv; dv = UP[dv]) { const v = step[dkey('icon_radius_' + c, dv)]; if (v != null) return v; }
        return null;
    }
    const cornerCss = (step, c, d) => { const v = cornerValue(step, c, d); return v == null ? (uses('arc') ? '50%' : '0') : v + (step['icon_radius_' + c + '_unit'] || '%'); };
    function radiusBox(off) {
        const step = current(), linked = step.icon_radius_linked !== false;
        const corner = c => {
            const unit = step['icon_radius_' + c + '_unit'] || '%', own = step[dkey('icon_radius_' + c, device)], up = device === 'desktop' ? null : cornerValue(step, c, UP[device]);
            return '<span class="wpst-radius-input wpst-radius-box__corner wpst-radius-box__corner--' + c + '"><input type="number" id="wpst-f-icon_radius_' + c + '" data-iradius-corner="' + c + '" min="0" max="' + RADIUS_MAX[unit] + '" step="1" value="' + (own == null ? '' : escape(own)) + '" placeholder="' + (up != null ? up : unit === '%' && uses('arc') ? 50 : '') + '" aria-label="' + CORNER_NAMES[c] + ' radius (' + unit + ')">'
                + '<span class="wpst-unit-pick" role="group" aria-label="' + CORNER_NAMES[c] + ' radius unit">' + Object.keys(RADIUS_MAX).map(u => '<button type="button" data-iradius-unit="' + c + '" data-unit="' + u + '" aria-pressed="' + (u === unit) + '">' + u + '</button>').join('') + '</span></span>';
        };
        const text = linked ? 'The corners are set together: click to set each on its own' : 'Each corner on its own: click to set them together';
        const icons = '<span class="wpst-devices" role="group" aria-label="Device">' + Object.entries(DEVICES).map(([d, [icon, name]]) =>
            '<button type="button" class="wpst-devices__btn' + (d === device ? ' is-active' : '') + (CORNERS.some(c => step[dkey('icon_radius_' + c, d)] != null) ? ' has-value' : '') + '" data-device="' + d + '" aria-pressed="' + (d === device) + '" aria-label="' + name + '" title="' + name + '"><span class="dashicons dashicons-' + icon + '" aria-hidden="true"></span></button>').join('') + '</span>';
        return '<div class="wpst-field wpst-field--radius' + (off ? ' is-disabled' : '') + '" data-field="icon_radius"><div class="wpst-field__head"><span class="wpst-field__label">Corners</span>' + tip('50% on each a circle, 0 a square. Empty: the larger screen\'s, else a circle.') + icons + '</div>'
            + '<div class="wpst-radius-box"><span class="wpst-radius-box__frame" aria-hidden="true" style="border-radius:' + CORNERS.map(c => { const v = cornerValue(step, c, device), u = step['icon_radius_' + c + '_unit'] || '%'; return v == null ? (uses('arc') ? '50%' : '0') : u === '%' ? v + '%' : Math.min(40, v / 2) + 'px'; }).join(' ') + '"></span>'
            + CORNERS.map(corner).join('') + '<button type="button" class="wpst-radius-box__link" data-iradius-link aria-pressed="' + linked + '" aria-label="' + text + '" title="' + text + '">' + LINK_ICON + '</button></div></div>';
    }
    /*
     * Padding / Margin of the whole set (2026-11-24, user's request: WP Banner's widget): Margin's box around Padding's, each side's
     * number with px / % beside it (one unit per side for every device), each box its own link (Padding's in the middle, Margin's
     * in its corner), per device (tablet / phone empty: the larger screen's; Desktop emptied: 0). The rings show what is set.
     */
    const SPACE_MAX = {px:500, '%':50}, SIDES = ['top', 'right', 'bottom', 'left'], SIDE_NAMES = {top:'Top', right:'Right', bottom:'Bottom', left:'Left'};
    const SPACES = {padding:{name:'Padding', attr:'pad'}, margin:{name:'Margin', attr:'margin'}};
    // The Settings' (data: Desktop always a value) or a step's (2026-11-24: none anywhere = the design's own).
    const spaceVal = (t, key, d) => { for (let dv = d; dv; dv = UP[dv]) { const v = t[dkey(key, dv)]; if (v != null) return v; } return null; };
    const spaceHint = (t, key) => { const v = t === data ? (device === 'desktop' ? null : spaceVal(t, key, UP[device])) : device === 'desktop' ? null : spaceVal(t, key, UP[device]); return v == null || (t === data && Number(v) === 0) ? '' : v; };
    const spaceOwn = (t, dv) => ['padding', 'margin'].some(kind => SIDES.some(s => t === data && dv === 'desktop' ? Number(t[kind + '_' + s]) !== 0 || (t[kind + '_' + s + '_unit'] || 'px') !== 'px' : t[dkey(kind + '_' + s, dv)] != null));
    function spacingField() {
        const t = current();
        const side = (kind, s) => {
            const a = SPACES[kind].attr, own = t[dkey(kind + '_' + s, device)], unit = t[kind + '_' + s + '_unit'] || 'px';
            return '<span class="wpst-radius-input wpst-spacing__side wpst-spacing__side--' + kind + '-' + s + '"><input type="number" id="wpst-f-' + kind + '_' + s + '" data-' + a + '-side="' + s + '" min="0" max="' + SPACE_MAX[unit] + '" step="1" value="' + (own == null || (t === data && device === 'desktop' && Number(own) === 0) ? '' : escape(own)) + '" placeholder="' + escape(spaceHint(t, kind + '_' + s)) + '" aria-label="' + SIDE_NAMES[s] + ' ' + kind + ' (' + unit + ')">'
                + '<span class="wpst-unit-pick" role="group" aria-label="' + SIDE_NAMES[s] + ' ' + kind + ' unit">' + Object.keys(SPACE_MAX).map(u => '<button type="button" data-' + a + '-unit="' + s + '" data-unit="' + u + '" aria-pressed="' + (u === unit) + '">' + u + '</button>').join('') + '</span></span>';
        };
        const link = kind => {
            const linked = t[kind + '_linked'] !== false, text = SPACES[kind].name + ': ' + (linked ? 'the sides are set together: click to set each on its own' : 'each side on its own: click to set them together');
            return '<button type="button" class="wpst-spacing__link wpst-spacing__link--' + kind + '" data-' + SPACES[kind].attr + '-link aria-pressed="' + linked + '" aria-label="' + text + '" title="' + text + '">' + LINK_ICON + '</button>';
        };
        const ring = kind => '<span class="wpst-spacing__ring wpst-spacing__ring--' + kind + '" aria-hidden="true"></span><span class="wpst-spacing__name wpst-spacing__name--' + kind + '" aria-hidden="true">' + SPACES[kind].name + '</span>';
        const icons = '<span class="wpst-devices" role="group" aria-label="Device">' + Object.entries(DEVICES).map(([d, [icon, name]]) =>
            '<button type="button" class="wpst-devices__btn' + (d === device ? ' is-active' : '') + (spaceOwn(t, d) ? ' has-value' : '') + '" data-device="' + d + '" aria-pressed="' + (d === device) + '" aria-label="' + name + '" title="' + name + '"><span class="dashicons dashicons-' + icon + '" aria-hidden="true"></span></button>').join('') + '</span>';
        return '<div class="wpst-field wpst-field--spacing" data-field="spacing"><div class="wpst-field__head"><span class="wpst-field__label">Sides</span>' + icons + '</div>'
            + '<div class="wpst-spacing">' + ring('margin') + ring('padding') + '<span class="wpst-spacing__core" aria-hidden="true"></span>'
            + SIDES.map(x => side('padding', x)).join('') + link('padding') + SIDES.map(x => side('margin', x)).join('') + link('margin') + '</div></div>';
    }
    // The rings' sides drawn as the spaces are on the device edited (2–6px; none: no line), the fields' values and the device dots.
    function refreshSpacing() {
        const box = $('settings').querySelector('.wpst-spacing'), t = current();
        if (!box || !t) return;
        Object.keys(SPACES).forEach(kind => {
            const a = SPACES[kind].attr, ring = box.querySelector('.wpst-spacing__ring--' + kind);
            SIDES.forEach(s => {
                const v = Number(spaceVal(t, kind + '_' + s, device)) || 0, px = t[kind + '_' + s + '_unit'] === '%' ? v * 6 : v, input = box.querySelector('[data-' + a + '-side="' + s + '"]'), own = t[dkey(kind + '_' + s, device)];
                ring.style['border' + s[0].toUpperCase() + s.slice(1) + 'Width'] = (px ? Math.min(6, Math.max(2, px / 8)) : 0) + 'px';
                if (input && document.activeElement !== input) input.value = own == null || (t === data && device === 'desktop' && Number(own) === 0) ? '' : own;
                if (input) input.placeholder = spaceHint(t, kind + '_' + s);
            });
        });
        box.closest('.wpst-field').querySelectorAll('.wpst-devices__btn').forEach(b => b.classList.toggle('has-value', spaceOwn(t, b.dataset.device)));
    }
    /*
     * One box for a card's Margin, Border, Radius and Padding (2026-11-25, user's request: in one block, as wide as it needs), as a
     * browser's inspector draws it: Margin's ring outside, Border's (its corners: Radius) inside it, Padding's inside that, the content in
     * the middle; under it the four links and the border's Line | Color. Per device (tablet / phone empty: the larger screen's).
     * scope 'card' (Style → Cards): keys card_<kind>_<part>; border and radius have a value on Desktop (emptied 0), padding / margin
     * none = the design's own. scope 'step' (a step's Style tab): keys <kind>_<part>, none = the cards'.
     */
    const BOX = {margin:{name:'Margin', parts:SIDES, units:SPACE_MAX}, border:{name:'Border', parts:SIDES, units:{px:20}}, radius:{name:'Radius', parts:CORNERS, units:RADIUS_MAX}, padding:{name:'Padding', parts:SIDES, units:SPACE_MAX}};
    const boxTarget = scope => scope === 'card' ? data : current();
    const boxKey = (scope, kind, part) => (scope === 'card' ? 'card_' : '') + kind + '_' + part;
    const boxFixed = (scope, kind) => scope === 'card' && (kind === 'border' || kind === 'radius');
    const boxUnit = (scope, kind, part) => kind === 'border' ? 'px' : (boxTarget(scope)[boxKey(scope, kind, part) + '_unit'] || 'px');
    function boxVal(scope, kind, part, d) { const t = boxTarget(scope), k = boxKey(scope, kind, part); for (let dv = d; dv; dv = UP[dv]) { const v = t[dkey(k, dv)]; if (v != null) return v; } return null; }
    // In effect on a device: its own (or its larger screen's), a step's else the cards'; [value, unit].
    function boxEff(scope, kind, part, d) {
        const v = boxVal(scope, kind, part, d);
        if (v != null) return [v, boxUnit(scope, kind, part)];
        if (scope === 'step') { const c = boxVal('card', kind, part, d); if (c != null) return [c, boxUnit('card', kind, part)]; }
        return [null, 'px'];
    }
    // The placeholder: the larger screen's, a step's else the cards'; empty for the design's own.
    function boxHint(scope, kind, part) {
        if (boxFixed(scope, kind) && device === 'desktop') return '';
        const up = device === 'desktop' ? null : boxVal(scope, kind, part, UP[device]);
        // Nothing shown where nothing is set: an inherited 0 is no hint (2026-11-25, user's report: tablets showed 0).
        if (up != null) return Number(up) === 0 ? '' : up;
        if (scope === 'step') { const c = boxVal('card', kind, part, device); if (c != null) return Number(c) === 0 ? '' : c; }
        return '';
    }
    const boxOwn = (scope, d) => Object.keys(BOX).some(kind => BOX[kind].parts.some(p => { const t = boxTarget(scope), k = boxKey(scope, kind, p); return boxFixed(scope, kind) && d === 'desktop' ? Number(t[k]) !== 0 : t[dkey(k, d)] != null; }));
    const boxCap = s => s[0].toUpperCase() + s.slice(1);
    // Is a border drawn on some device (a step's own or the cards')?
    const boxHasBorder = scope => SIDES.some(p => Object.keys(DEVICES).some(d => (Number(boxEff(scope, 'border', p, d)[0]) || 0) > 0));
    function boxModel(scope) {
        const t = boxTarget(scope);
        const input = (kind, part) => {
            const k = boxKey(scope, kind, part), own = t[dkey(k, device)], unit = boxUnit(scope, kind, part), units = Object.keys(BOX[kind].units), name = BOX[kind].name + ' ' + (CORNER_NAMES[part] || SIDE_NAMES[part]).toLowerCase();
            return '<span class="wpst-radius-input wpst-box__in wpst-box__in--' + kind + '-' + part + '"><input type="number" id="wpst-f-' + k + '" data-box="' + scope + '" data-box-kind="' + kind + '" data-box-part="' + part + '" min="0" max="' + BOX[kind].units[unit] + '" step="1" value="' + (own == null || (boxFixed(scope, kind) && device === 'desktop' && Number(own) === 0) ? '' : escape(own)) + '" placeholder="' + escape(boxHint(scope, kind, part)) + '" aria-label="' + name + ' (' + unit + ')">'
                + (units.length > 1 ? '<span class="wpst-unit-pick" role="group" aria-label="' + name + ' unit">' + units.map(u => '<button type="button" data-box-unit="' + scope + '" data-box-kind="' + kind + '" data-box-part="' + part + '" data-unit="' + u + '" aria-pressed="' + (u === unit) + '">' + u + '</button>').join('') + '</span>' : '<span class="wpst-box__px">px</span>') + '</span>';
        };
        const ring = kind => '<span class="wpst-box__ring wpst-box__ring--' + kind + '" aria-hidden="true"></span><span class="wpst-box__name wpst-box__name--' + kind + '" aria-hidden="true">' + BOX[kind].name + '</span>';
        const links = '<div class="wpst-box__links" role="group" aria-label="Set the sides together">' + Object.keys(BOX).map(kind => {
            const linked = t[boxKey(scope, kind, 'linked')] !== false, text = BOX[kind].name + ': ' + (linked ? 'set together: click to set each on its own' : 'each on its own: click to set them together');
            return '<button type="button" class="wpst-box__link" data-box-link="' + scope + '" data-box-kind="' + kind + '" aria-pressed="' + linked + '" title="' + text + '" aria-label="' + text + '">' + LINK_ICON + '<span>' + BOX[kind].name + '</span></button>';
        }).join('') + '</div>';
        const styles = scope === 'card' ? [['solid', 'Solid'], ['dashed', 'Dashed'], ['dotted', 'Dotted'], ['double', 'Double']]
            : [['', 'As cards (' + boxCap(data.card_border_style || 'solid') + ')'], ['solid', 'Solid'], ['dashed', 'Dashed'], ['dotted', 'Dotted'], ['double', 'Double']];
        const line = row('wpst-row2 wpst-row2--line', select(scope === 'card' ? 'card_border_style' : 'border_style', 'Border line', styles, scope === 'card' ? undefined : {shown:t.border_style || ''})
            + color(scope === 'card' ? 'card_border_color' : 'border_color', 'Border color', scope === 'card' ? {def:'', fallback:stepColors()[0], auto:false} : {def:'', fallback:data.card_border_color || stepColors()[selected]}));
        return '<div class="wpst-field wpst-field--box" data-field="' + scope + '_box"><div class="wpst-field__head"><span class="wpst-field__label">' + (scope === 'card' ? 'Box' : 'Box (empty: as the cards)') + '</span>' + devIcons(d => boxOwn(scope, d)) + '</div>'
            + '<div class="wpst-box" data-box-scope="' + scope + '">' + ring('margin') + ring('border') + ring('padding') + '<span class="wpst-box__core" aria-hidden="true"></span>'
            + SIDES.map(x => input('margin', x)).join('') + SIDES.map(x => input('border', x)).join('') + CORNERS.map(c => input('radius', c)).join('') + SIDES.map(x => input('padding', x)).join('') + '</div>'
            // Border line | Border color only while some border is set (2026-11-25, user's request); refreshBox() shows / hides it as typed.
            + links + '</div><div class="wpst-box__line"' + (boxHasBorder(scope) ? '' : ' hidden') + '>' + line + '</div>';
    }
    // The fields' values and hints, the rings drawn as set (margin / padding 2–6px, the border as it is, its corners) and the device dots.
    function refreshBox() {
        const box = $('settings').querySelector('.wpst-box');
        if (!box) return;
        const scope = box.dataset.boxScope, t = boxTarget(scope);
        if (!t) return;
        box.querySelectorAll('input[data-box]').forEach(inp => {
            const own = t[dkey(boxKey(scope, inp.dataset.boxKind, inp.dataset.boxPart), device)];
            if (document.activeElement !== inp) inp.value = own == null || (boxFixed(scope, inp.dataset.boxKind) && device === 'desktop' && Number(own) === 0) ? '' : own;
            inp.placeholder = boxHint(scope, inp.dataset.boxKind, inp.dataset.boxPart);
        });
        const px = (kind, p) => { const [v, u] = boxEff(scope, kind, p, device); const n = Number(v) || 0; return u === '%' ? n * 6 : n; };
        ['margin', 'padding'].forEach(kind => { const r = box.querySelector('.wpst-box__ring--' + kind); SIDES.forEach(x => { const n = px(kind, x); r.style['border' + boxCap(x) + 'Width'] = (n ? Math.min(6, Math.max(2, n / 8)) : 0) + 'px'; }); });
        const b = box.querySelector('.wpst-box__ring--border');
        b.style.borderStyle = (scope === 'step' && t.border_style) || data.card_border_style || 'solid';
        b.style.borderColor = (scope === 'step' && t.border_color) || data.card_border_color || stepColors()[Math.max(0, selected)];
        SIDES.forEach(x => { b.style['border' + boxCap(x) + 'Width'] = Math.min(8, px('border', x)) + 'px'; });
        // A sign, not the real size (2026-11-25, user's request: the real one ran under the corner fields): a rounded corner 6px, else square.
        b.style.borderRadius = CORNERS.map(c => (Number(boxEff(scope, 'radius', c, device)[0]) || 0) > 0 ? '6px' : '0').join(' ');
        box.closest('.wpst-field').querySelectorAll('.wpst-devices__btn').forEach(btn => btn.classList.toggle('has-value', boxOwn(scope, btn.dataset.device)));
        const lineRow = $('settings').querySelector('.wpst-box__line');
        if (lineRow) lineRow.hidden = !boxHasBorder(scope);
    }
    /*
     * An image's box (2026-11-25): Size Default (a square, the image inside at Content size) | Aspect ratio (per device, 16/9 by
     * default) | Custom (a height per device); for those two Object-fit and Object position (a 3 × 3 grid).
     */
    const IMAGE_POSITIONS = ['left top', 'center top', 'right top', 'left center', 'center center', 'right center', 'left bottom', 'center bottom', 'right bottom'];
    function imageBoxFields(step) {
        const mode = step.image_size || '';
        let out = '<div class="wpst-row2">' + select('image_size', 'Size', [['', 'Default'], ['ratio', 'Aspect ratio'], ['custom', 'Custom']], {shown:mode});
        if (mode === 'ratio') {
            const key = dkey('image_ratio', device), up = (() => { for (let d = UP[device]; d; d = UP[d]) { if (step[dkey('image_ratio', d)]) return step[dkey('image_ratio', d)]; } return '16/9'; })();
            out += field(key, 'Aspect ratio', '<input type="text" id="wpst-f-image_ratio" data-key="' + key + '" value="' + escape(step[key] || '') + '" placeholder="' + escape(up) + '">', {}, devIcons(d => !!step[dkey('image_ratio', d)]));
        } else if (mode === 'custom') {
            out += stepSize('image_height', 'Height', {min:1, max:2000, usual:stepInherited('icon_size', device === 'desktop' ? 'tablet' : device, undefined) || ''});
        }
        out += '</div>';
        if (!mode) return out;
        const pos = step.image_position || 'center center';
        return out + '<div class="wpst-row2 wpst-row2--top">' + select('image_fit', 'Object-fit', [['cover', 'Cover'], ['contain', 'Contain'], ['fill', 'Fill'], ['none', 'None']], {shown:step.image_fit || 'cover'})
            // A select (2026-11-25, user's request; it was a 3 × 3 grid).
            + select('image_position', 'Object position', IMAGE_POSITIONS.map(p => [p, p.split(' ').map(w => w[0].toUpperCase() + w.slice(1)).join(' ')]), {shown:pos}) + '</div>';
    }
    // What the step shows: its choice, else its image when it has one (as WP_Steps_Settings::shows_image()).
    const mediaOf = step => step.media || (step.image ? 'image' : 'icon');
    function imagePicker(image) {
        return '<div class="wpst-imagepick"><span class="wpst-imagepick__thumb" aria-hidden="true">' + (image ? '<img src="' + escape(image.url) + '" alt="">' : '<span class="dashicons dashicons-format-image"></span>') + '</span>'
            + '<span class="wpst-imagepick__name">' + (image ? escape(image.name) : 'No image') + '</span>'
            // Icons, not words (user's request): a plus to choose one, a pencil to replace it; their names on hover.
            + '<button type="button" class="wpst-icon-button" id="wpst-image-pick" title="' + (image ? 'Replace image' : 'Choose image') + '" aria-label="' + (image ? 'Replace image' : 'Choose image') + '"><span class="dashicons dashicons-' + (image ? 'edit' : 'plus-alt2') + '" aria-hidden="true"></span></button>'
            + (image ? '<button type="button" class="wpst-icon-button wpst-icon-button--danger" id="wpst-image-remove" title="Remove image" aria-label="Remove image"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>' : '') + '</div>';
    }
    function iconCircle(step) {
        const style = step.icon_style || data.icon_style || 'white', shape = step.icon_shape || (style === 'plain' ? 'none' : 'filled');
        const own = stepColors()[selected], colored = shape === 'filled' && style === 'color';
        // None: the icon in the step's color too, in every design and on any cards (2026-11-24, user's request; frontend.css).
        const bare = own;
        return {shape, style, bg: shape === 'outline' ? own : colored ? own : '#ffffff', icon: shape === 'none' ? bare : colored && !step.icon_bg ? '#ffffff' : own};
    }
    function iconSelect(step, image) {
        const own = ICONS[step.icon];
        const shown = image ? '<img src="' + escape(image.url) + '" alt="">' : own ? own[1] : '<span class="wpst-icon__none">None</span>';
        const name = image ? 'Your image' : own ? own[0] : 'None';
        const option = (key, label, inner) => '<button type="button" role="option" class="wpst-icon" data-icon="' + key + '" data-name="' + escape((label + ' ' + key).toLowerCase()) + '" aria-selected="' + (step.icon === key) + '" aria-pressed="' + (step.icon === key) + '" title="' + escape(label) + '" aria-label="' + escape(label) + '" tabindex="-1">' + inner + '</button>';
        return '<div class="wpst-iconselect' + (image ? ' is-covered' : '') + '">'
            + '<button type="button" class="wpst-iconselect__button" id="wpst-icon-select" aria-haspopup="listbox" aria-expanded="false" aria-label="Icon: ' + escape(name) + '">'
            + '<span class="wpst-iconselect__icon" aria-hidden="true">' + shown + '</span><span class="wpst-iconselect__name">' + escape(name) + '</span><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>'
            + '<div class="wpst-iconselect__panel" hidden><input type="search" class="wpst-iconselect__search" placeholder="Search icons" aria-label="Search icons">'
            + '<div class="wpst-icons" role="listbox" aria-label="Icons">' + option('', 'None', '<span class="wpst-icon__none">None</span>')
            + Object.entries(ICONS).map(([key, [label, svg]]) => option(key, label, svg)).join('') + '</div>'
            + '<p class="wpst-iconselect__empty" hidden>No icons found.</p></div></div>';
    }
    const iconPanel = () => $('settings').querySelector('.wpst-iconselect__panel');
    const iconOptions = () => [...$('settings').querySelectorAll('.wpst-iconselect .wpst-icon')].filter(o => !o.hidden);
    function openIcons(open) {
        const panel = iconPanel(), button = $('icon-select');
        if (!panel || !button) return;
        panel.hidden = !open;
        button.setAttribute('aria-expanded', String(open));
        if (open) {
            const search = panel.querySelector('.wpst-iconselect__search'), chosen = panel.querySelector('.wpst-icon[aria-selected=true]');
            search.value = ''; filterIcons('');
            if (chosen) chosen.scrollIntoView({block:'nearest'});
            search.focus();
        }
    }
    function filterIcons(query) {
        const q = query.trim().toLowerCase();
        let any = false;
        $('settings').querySelectorAll('.wpst-iconselect .wpst-icon').forEach(o => { o.hidden = q !== '' && o.dataset.name.indexOf(q) === -1; any = any || !o.hidden; });
        const empty = $('settings').querySelector('.wpst-iconselect__empty');
        if (empty) empty.hidden = any;
    }
    // Arrows in the grid: left / right one, up / down a row (the options on the row above / below, nearest across).
    function moveInIcons(from, key) {
        const list = iconOptions(), i = list.indexOf(from);
        if (!list.length) return;
        if (i < 0) { list[0].focus(); return; }
        if (key === 'ArrowLeft' || key === 'ArrowRight') { const to = list[i + (key === 'ArrowRight' ? 1 : -1)]; if (to) to.focus(); return; }
        const top = from.offsetTop, left = from.offsetLeft, down = key === 'ArrowDown';
        const row = list.filter(o => down ? o.offsetTop > top : o.offsetTop < top);
        if (!row.length) { if (!down) iconPanel().querySelector('.wpst-iconselect__search').focus(); return; }
        const y = down ? Math.min(...row.map(o => o.offsetTop)) : Math.max(...row.map(o => o.offsetTop));
        const near = row.filter(o => o.offsetTop === y).sort((a, b) => Math.abs(a.offsetLeft - left) - Math.abs(b.offsetLeft - left))[0];
        near.focus();
    }

    /*
     * The icon's place and alignment per device (2026-11-24): Desktop '' = Auto; tablet / phone null = the larger screen's (the first
     * option says which), 'auto' = Auto there. iconPos(d): the place in effect ('' for Auto).
     */
    function devValue(base, d) { for (let dv = d; dv; dv = UP[dv]) { const v = data[dkey(base, dv)]; if (v != null) return v; } return ''; }
    const iconPos = d => { const v = devValue('icon_position', d); return v === 'auto' ? '' : v; };
    function devIcons(own) {
        return '<span class="wpst-devices" role="group" aria-label="Device">' + Object.entries(DEVICES).map(([d, [icon, name]]) =>
            '<button type="button" class="wpst-devices__btn' + (d === device ? ' is-active' : '') + (own(d) ? ' has-value' : '') + '" data-device="' + d + '" aria-pressed="' + (d === device) + '" aria-label="' + name + '" title="' + name + '"><span class="dashicons dashicons-' + icon + '" aria-hidden="true"></span></button>').join('') + '</span>';
    }
    function devSelect(base, label, options, usual, o) {
        const key = dkey(base, device), own = data[key], names = Object.assign({'':'Auto', auto:'Auto'}, options);
        const auto = base === 'icon_position' ? {} : null;
        let list;
        if (device === 'desktop') list = Object.assign(auto ? {'':'Auto'} : {}, options);
        else { const up = devValue(base, UP[device]) || usual; list = Object.assign({'': 'As ' + DEVICES[UP[device]][1] + ' (' + (names[up] || up) + ')'}, auto ? {auto:'Auto'} : {}, options); }
        const now = device === 'desktop' ? (own || usual) : (own == null ? '' : own);
        return field(key, label, '<select id="wpst-f-' + base + '" data-key="' + key + '" data-dev-select="' + base + '">' + Object.entries(list).map(([v, text]) =>
            '<option value="' + escape(v) + '"' + (String(now) === v ? ' selected' : '') + '>' + escape(text) + '</option>').join('') + '</select>', o, devIcons(d => d === 'desktop' ? !!data[base] : data[dkey(base, d)] != null));
    }
    // The steps' colors, first and last side by side, the ones between on a bar (2026-11-24, user's request: in Layout → Cards;
    // the Colors block is gone; in Wave, without cards, a card of their own in Layout).
    const stepColorFields = () => '<div class="wpst-row2 wpst-row2--colors">' + color('color_from', 'First step', {def:boot.defaults.color_from})
        + color('color_to', 'Last step', {def:boot.defaults.color_to}) + '</div>'
        + '<div class="wpst-gradient" aria-hidden="true">' + stepColors().map(c => '<span style="background:' + escape(c) + '"></span>').join('') + '</div>'
        + note('The steps between take the colors in between. A step can have a color of its own (its Style tab).');
    // Does the design have cards (not Wave)? The Steps block's Cards card then.
    const hasCards = () => ['icon_position', 'card_style', 'card_radius', 'card_border', 'shadow', 'alternate'].some(uses);

    /*
     * The design in use, over the menu (2026-11-24, user's request: always in sight; a select as the icons'): its thumbnail and
     * name; open, the designs' tiles (their description on hover). Arrows move, Enter chooses, Esc or a click outside closes.
     */
    function drawDesign() {
        const box = $('design-select'), l = design();
        if (!box) return;
        box.innerHTML = '<button type="button" class="wpst-designselect__button" id="wpst-design-button" aria-haspopup="listbox" aria-expanded="false" aria-label="Design: ' + escape(l.name) + '">'
            + '<span class="wpst-designselect__thumb" aria-hidden="true">' + l.thumb + '</span><span class="wpst-designselect__text"><small>Design</small>' + escape(l.name) + '</span><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>'
            + '<div class="wpst-designselect__panel" hidden><div class="wpst-designs" role="listbox" aria-label="Designs">' + boot.designs.map(item =>
                '<button type="button" role="option" class="wpst-design" data-design="' + item.id + '" aria-selected="' + (item.id === data.design) + '" aria-pressed="' + (item.id === data.design) + '" title="' + escape(item.about + (item.stack ? ' Below ' + item.stack + ' px it folds into one column.' : '')) + '">'
                + '<span class="wpst-design__thumb">' + item.thumb + '</span><span class="wpst-design__name">' + escape(item.name) + '</span></button>').join('') + '</div></div>';
    }
    function openDesigns(open) {
        const box = $('design-select'), panel = box && box.querySelector('.wpst-designselect__panel');
        if (!panel) return;
        panel.hidden = !open;
        $('design-button').setAttribute('aria-expanded', String(open));
        if (open) { const now = panel.querySelector('[aria-selected=true]') || panel.querySelector('.wpst-design'); now.focus(); }
    }
    function chooseDesign(id) {
        if (data.design !== id) {
            data.design = id;
            changed(true);
            drawMenu();
            drawBlock();
            // A new design: the preview at once (as WP Swiper's Effect).
            refresh();
        }
        drawDesign();
        $('design-button').focus();
    }

    /* ---------- Blocks ---------- */
    const SETTINGS_BLOCKS = [
        // Cards: not in the menu where the design has none (Wave).
        // Layout (2026-11-24, user's request: was Cards; the icon's place first).
        ['layout', 'Layout', 'id-alt'],
        ['style', 'Style', 'admin-appearance'],
        ['lines', 'Line and markers', 'editor-insertmore'],
        ['numbering', 'Numbers', 'editor-ol'],
        // Custom (2026-11-24, user's request): Padding/Margin of the whole set, its id and classes.
        ['custom', 'Custom', 'admin-generic']
    ];
    // A step's tabs in its window (2026-11-24): Title, Text, Icon, Color, and Side text where the design shows it (Timeline).
    const STEP_BLOCKS = [['title', 'Title'], ['text', 'Text'], ['icon', 'Icon'], ['color', 'Style'], ['link', 'Link'], ['aside', 'Side text']];
    const stepTabs = () => STEP_BLOCKS.filter(b => b[0] !== 'aside' || uses('aside'));

    const blocks = {
        layout() {
            // Card Icon (2026-11-24, user's request): Icon position and its Alignment side by side (beside the texts Top / Middle / Bottom,
            // above or below them Left / Center / Right; none with Auto), then the Gap to the texts; all per device.
            const pos = iconPos(device), across = pos === 'top' || pos === 'bottom';
            const align = pos && uses('icon_position') ? devSelect('icon_align', 'Alignment', across ? {start:'Left', center:'Center', end:'Right'} : {start:'Top', center:'Middle', end:'Bottom'}, across ? 'start' : 'center') : '';
            // Space between steps first (2026-11-24, user's request: moved here from Line and markers), so Layout is in every design's menu.
            // Progress (2026-11-25): its texts centred or from the left, the icons shown or not.
            return card('Layout', respNumber('gap', 'Space between steps', {to:120})
                + select('progress_align', 'Text alignment', [['center', 'Center'], ['left', 'Left']], {uses:'progress_align'})
                + toggle('show_icons', 'Show icons', {uses:'show_icons'}))
                + (uses('icon_position') ? card('Icon', row('wpst-row2', devSelect('icon_position', 'Position', {left:'Left', top:'Top', right:'Right', bottom:'Bottom'}, '') + align)
                + respNumber('icon_gap', 'Gap', {to:100})) : '')
                // Wave's half rings (2026-11-24, user's request: moved here from Line and markers).
                + (uses('arc') ? card('Half rings', respNumber('arc', 'Thickness', {to:30}) + respNumber('ring_gap', 'Gap', {to:30, tip:'Between each circle and its half ring.'})) : '');
        },
        // Style (2026-11-24, user's request): everything that was in Layout → Cards (in Wave, without cards, the step colors).
        style() {
            return (hasCards() ? card('Cards', stepColorFields() + toggle('alternate', 'Cards from side to side', {uses:'alternate', tip:'The cards on the left and on the right of the line in turn, the side texts opposite them.'})
                // Margin, Border, Radius and Padding of the cards in one box (2026-11-25, user's request; no switches: 0 is none).
                + (uses('card_border') ? boxModel('card') : '')
                + toggle('shadow', 'Shadow', {uses:'shadow'})
                + (data.shadow && uses('shadow') ? shadowFields('card_shadow') : '')
                + note('Each step\'s icon, its style, size and color: open the step (its tile above), then Icon.')) : card('Step colors', stepColorFields()));
        },
        lines() {
            return card('Line', choice('line', 'Style', {dotted:'Dotted', dashed:'Dashed', solid:'Solid', none:'None'})
                + respNumber('line_width', 'Width', {to:8})
                // Zigzag: the corners of the line between markers (2026-11-24).
                + respNumber('line_radius', 'Corner radius', {to:100, uses:'line_radius'})
                // Its color here, with the line (2026-11-24, user's request; it was under Colors).
                // Color and Arrows in one row (2026-11-25, user's request).
                + row('wpst-row2 wpst-row2--color-toggle', color('line_color', 'Color', {def:'', fallback:stepColors()[0]}) + toggle('arrow', 'Arrows', {uses:'arrow'})))
                + card('Markers', choice('marker', 'Marker on the line', {ring:'Ring', dot:'Dot', number:'Number', icon:'Icon', none:'None'}, {uses:'marker', tip:'Number: a circle with the step\'s number (and the word set under Numbers).'})
                + ((data.design === 'timeline' || data.design === 'progress') && data.marker === 'number' ? respNumber('number_size', 'Number size', {to:120, tip:'The numbered marker on the line.'}) : ''));
        },
        numbering() {
            return card('Numbers', (data.design === 'zigzag' ? respNumber('number_size', 'Number size', {to:120, tip:'The large number beside each card.'}) : '')
                + toggle('show_label', 'Show the step number')
                // Its alignment (2026-11-25, user's request: as the texts; no Auto: the design's shown until one is chosen).
                + (data.show_label ? select('label_align', 'Alignment', [['left', 'Left'], ['center', 'Center'], ['right', 'Right']], {uses:'label_align', shown:data.label_align || (data.design === 'wave' ? 'center' : 'left')}) : '')
                + (data.show_label ? textField('label_word', 'Word before the number', {placeholder:'Step', tip:'For example Step, Stage, Phase. Empty: the number alone.'})
                + choice('number_format', 'Number', {'01':'01, 02, 03', '1':'1, 2, 3'})
                // Its color here, with the number (2026-11-24, user's request; it was under Colors → Texts).
                + color('label_color', 'Color', {def:'', fallback:stepColors()[0], tip:'"Step 01" above the title, or the word over the large number (Zigzag). Automatic: the step\'s color (grey in Zigzag).'}) : ''));
        },
        custom() {
            // No tooltips in this block (2026-11-24, user's request).
            return card('Custom', toggle('use_padding', 'Padding/Margin')
                + (data.use_padding ? spacingField() : ''))
                + card('Attributes', textField('css_id', 'Element ID', {placeholder:'my-steps'})
                + textField('css_class', 'Extra class name', {placeholder:'my-class'}));
        },
        title() {
            return card('Title', editorField('title', 'Title') + styleRow('title')
                // No Default (user's request): a step without a tag of its own shows the one it has (the set's).
                + select('title_tag', 'Tag', {h1:'H1', h2:'H2', h3:'H3', h4:'H4', h5:'H5', h6:'H6', p:'Paragraph', div:'Div'}, {shown:current().title_tag || String(data.title_tag || 'h3'), tip:'For search engines and screen readers; the look stays the same.'}));
        },
        text() {
            return card('Text', editorField('text', 'Text') + styleRow('text'));
        },
        // The whole step as a link (2026-11-24, user's request).
        link() {
            return card('Link', textField('link', 'Link', {placeholder:'https://'})
                + toggle('link_new_tab', 'Open in a new tab'));
        },
        aside() {
            // Under each editor its Font size, weight and color per device (the size's device icons switch all three), the title's Tag.
            return card('Side text', editorField('aside_title', 'Title') + asideRow('aside_title')
                + select('aside_title_tag', 'Tag', {h1:'H1', h2:'H2', h3:'H3', h4:'H4', h5:'H5', h6:'H6', p:'Paragraph', div:'Div'}, {shown:current().aside_title_tag || 'p'})
                + editorField('aside_text', 'Text') + asideRow('aside_text'));
        },
        icon() {
            const step = current(), media = mediaOf(step), image = media === 'image' && step.image ? images[step.image] : null;
            const c = iconCircle(step), shape = c.shape, style = shape === 'none' ? 'plain' : c.style;
            // First: an icon or an image (2026-11-23, user's request); everything under it is for either.
            const kinds = '<div class="wpst-choice wpst-media-choice" role="group" aria-label="Icon or image">' + [['icon', 'Icon'], ['image', 'Image']].map(([v, t]) =>
                '<button type="button" data-choice="media" data-value="' + v + '" aria-pressed="' + (media === v) + '">' + t + '</button>').join('') + '</div>';
            // The circle beside the select (2026-11-23, user's idea): Filled / Outline / None, the one in effect pressed.
            const shapes = '<div class="wpst-choice wpst-icon-shape" role="group" aria-label="Circle">' + [['filled', 'Filled'], ['outline', 'Outline'], ['none', 'None']].map(([v, t]) =>
                '<button type="button" data-choice="icon_shape" data-value="' + v + '" aria-pressed="' + (shape === v) + '">' + t + '</button>').join('') + '</div>';
            // An image's box (2026-11-25, user's request: as Woodmart's Image element; not in Wave, its half ring follows a circle).
            const imgBox = media === 'image' && uses('image_size') && !!step.image_size;
            return card('Icon', kinds + '<div class="wpst-icon-head">' + (media === 'image' ? imagePicker(image) : iconSelect(step, null)) + shapes + '</div>'
                + (media === 'image' && uses('image_size') ? imageBoxFields(step) : '')
                // Size and Icon size, then Gap and Color (2026-11-23, user's request); all but Color per device.
                + '<div class="wpst-row2">' + stepSize('icon_size', imgBox ? 'Width' : 'Size')
                + stepSize('icon_scale', 'Content size', {disabled:imgBox, unit:'%', min:20, max:100, usual:media === 'image' ? 52 : style === 'plain' ? 50 : 44, tip:'The icon or image in its circle, % of the circle: larger, the circle around it narrower. With None, % of Size. Empty: the larger screen\'s, else the usual (its placeholder).'}) + '</div>'
                // The icon's color and the circle's (fill or ring), no reset. (Wave's half rings: Settings → Line and markers.)
                + row('wpst-row2 wpst-row2--colors', color('icon_color', 'Icon color', {def:'', reset:false, fallback:c.icon, disabled:media === 'image'})
                + color('icon_bg', 'Background', {def:'', reset:false, fallback:c.bg, disabled:shape === 'none'}))
                // Its corners (2026-11-23, user's request: WP Banner's Radius box), above Shadow.
                // With None no circle, but in Wave the half ring still takes these corners (2026-11-24, user's report): there it stays.
                + toggle('icon_use_radius', 'Radius', {disabled:shape === 'none' && !uses('arc'), tip:'On: the corners below (none set: a circle). Off: no rounding, a square.'})
                + (step.icon_use_radius && (shape !== 'none' || uses('arc')) ? radiusBox(false) : '')
                // The circle's shadow (2026-11-23): the set's until switched here; only a filled circle has one.
                + row('wpst-icon-shadow', toggle('icon_shadow', 'Shadow', {checked:step.icon_shadow == null ? !!data.shadow : step.icon_shadow, disabled:shape !== 'filled'})
                + (shape === 'filled' && (step.icon_shadow == null ? data.shadow : step.icon_shadow) ? shadowFields('icon_shadow') : '')));
        },
        color() {
            const step = current();
            // Style (2026-11-24, user's request: was Color): its Padding / Margin, always open, then its color.
            // Its card's box (2026-11-25): Margin, Border, Radius, Padding; empty = as the cards.
            // Its card's shadow as the cards' (2026-11-25, user's request): not set, the cards' switch; its parts empty, the cards'.
            const shadowOn = step.shadow == null ? !!data.shadow : step.shadow;
            return card('Style', boxModel('step') + (uses('shadow') ? toggle('shadow', 'Shadow', {checked:shadowOn}) + (shadowOn ? shadowFields('shadow') : '') : '') + color('color', 'Step color', {def:'', fallback:autoColor(selected), tip:'Not set: its place between the first and the last step\'s colors (Settings → Colors). The arrows set it back.'}));
        }
    };

    /* ---------- Drawing ---------- */
    function drawTiles() {
        const colors = stepColors();
        // No Settings tile (2026-11-24): the Settings are always there; a step's tile opens its window.
        let html = '';
        data.steps.forEach((step, i) => {
            const image = mediaOf(step) === 'image' && step.image ? images[step.image] : null;
            const icon = image ? '<img src="' + escape(image.url) + '" alt="">' : (ICONS[step.icon] ? ICONS[step.icon][1] : '<b>' + numberText(i) + '</b>');
            html += '<div class="wpst-tile wpst-tile--step' + (i === selected ? ' is-active' : '') + '" draggable="true" data-index="' + i + '" role="listitem">'
                + '<button type="button" class="wpst-tile__select" data-select="' + i + '" aria-current="' + (i === selected) + '" title="Alt + arrows: move">'
                + '<span class="wpst-tile__thumb" aria-hidden="true"><span class="wpst-tile__circle" style="background:' + escape(colors[i]) + '">' + icon + '</span><span class="wpst-tile__number">' + (i + 1) + '</span></span>'
                + '<span class="wpst-tile__label">' + (plainTitle(step.title) ? escape(plainTitle(step.title)) : 'Step ' + (i + 1)) + '</span></button>'
                + '<span class="wpst-tile__tools"><button type="button" class="wpst-icon-button" data-clone="' + i + '" title="Duplicate" aria-label="Duplicate step ' + (i + 1) + '"' + (data.steps.length >= boot.maxSteps ? ' disabled' : '') + '><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button>'
                + '<button type="button" class="wpst-icon-button wpst-icon-button--danger" data-delete="' + i + '" title="Delete" aria-label="Delete step ' + (i + 1) + '"' + (data.steps.length < 2 ? ' disabled' : '') + '><span class="dashicons dashicons-trash" aria-hidden="true"></span></button></span></div>';
        });
        html += '<button type="button" class="wpst-add" id="wpst-add"' + (data.steps.length >= boot.maxSteps ? ' disabled title="At most ' + boot.maxSteps + ' steps"' : '') + '><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>Add step</button>';
        $('tiles').innerHTML = html;
    }
    function drawMenu() {
        const list = SETTINGS_BLOCKS, which = 'settings';
        if (!list.some(b => b[0] === shown[which])) shown[which] = list[0][0];
        $('menu').innerHTML = list.map(([id, name, icon]) =>
            '<button type="button" class="wpst-menu-item" data-block="' + id + '" aria-current="' + (shown[which] === id) + '"><span class="dashicons dashicons-' + icon + '" aria-hidden="true"></span><span class="wpst-menu-item__title">' + name + '</span></button>').join('');
    }
    function drawBlock(keepFocus) {
        closePicker();
        const which = selected < 0 ? 'settings' : 'step';
        const focused = keepFocus && document.activeElement && document.activeElement.id;
        stopEditor();
        $('settings').innerHTML = blocks[shown[which]]();
        // Its name in the frame's legend; a first card of the same name loses its own heading (no doubled titles, as WP Banner's).
        const entry = (selected < 0 ? SETTINGS_BLOCKS : STEP_BLOCKS).find(b => b[0] === shown[which]), name = entry ? entry[1] : '';
        if ($('block-title') && selected < 0) $('block-title').textContent = name;
        const head = $('settings').querySelector('.wpst-card > .wpst-subheading');
        if (head && head.textContent.trim().toLowerCase() === name.toLowerCase()) head.remove();
        refreshSpacing();
        refreshBox();
        startEditor();
        $('settings').querySelectorAll('.wpst-field.is-disabled input, .wpst-field.is-disabled textarea, .wpst-field.is-disabled select, .wpst-field.is-disabled button').forEach(el => { el.disabled = true; });
        if (focused && document.getElementById(focused)) document.getElementById(focused).focus();
    }
    function drawAll() { drawDesign(); drawTiles(); drawMenu(); drawBlock(); }

    /*
     * A step's window (2026-11-24, user's request: as WP Swiper's slide window): its tile opens it; the settings box moves into it
     * (its listeners with it) and shows the step's tabs; closed, it goes back and shows the Settings again.
     */
    const modal = $('modal'), modalBody = $('modal-body'), settingsHome = $('settings').parentNode;
    // The step as it was when its window opened (Cancel puts it back).
    let stepBefore = null;
    function drawModalHead() {
        if (selected < 0) return;
        const step = data.steps[selected], image = mediaOf(step) === 'image' && step.image ? images[step.image] : null;
        $('modal-thumb').innerHTML = '<span class="wpst-tile__circle" style="background:' + escape(stepColors()[selected]) + '">' + (image ? '<img src="' + escape(image.url) + '" alt="">' : ICONS[step.icon] ? ICONS[step.icon][1] : '<b>' + numberText(selected) + '</b>') + '</span>';
        $('modal-title').innerHTML = 'Step ' + (selected + 1) + (plainTitle(step.title) ? ' <span class="wpst-modal__name">' + escape(plainTitle(step.title)) + '</span>' : '');
    }
    function drawModalTabs() {
        const tabs = stepTabs();
        if (!tabs.some(b => b[0] === shown.step)) shown.step = tabs[0][0];
        $('modal-tabs').innerHTML = tabs.map(([id, name]) => '<button type="button" role="tab" class="wpst-tab' + (((id === 'title' || id === 'text' || id === 'aside') && tabFilled(id)) || (id === 'link' && current() && current().link) ? ' has-value' : '') + '" data-step-tab="' + id + '" aria-selected="' + (shown.step === id) + '">' + name + '<span class="wpst-tab__dot" aria-hidden="true"></span></button>').join('');
    }
    function choose(index) {
        if (index < 0) { closeStep(true); return; }
        selected = index;
        if (modal.hidden) {
            stepBefore = JSON.stringify(data.steps[index]);
            modalBody.appendChild($('settings'));
            modal.hidden = false;
            document.body.classList.add('wpst-modal-open');
        }
        drawModalHead(); drawModalTabs(); drawTiles(); drawBlock();
        const tab = $('modal-tabs').querySelector('[aria-selected=true]'); if (tab) tab.focus();
    }
    // apply: keep what was done in the window; else (Cancel, the close button, Esc) the step as it was when it opened.
    function closeStep(apply) {
        if (modal.hidden) return;
        const was = selected;
        stopEditor(); closePicker();
        if (!apply && stepBefore !== null && JSON.stringify(data.steps[was]) !== stepBefore) { data.steps[was] = JSON.parse(stepBefore); changed(true); }
        stepBefore = null;
        selected = -1;
        settingsHome.appendChild($('settings'));
        modal.hidden = true;
        document.body.classList.remove('wpst-modal-open');
        drawTiles(); drawMenu(); drawBlock();
        const tile = $('tiles').querySelector('[data-select="' + was + '"]'); if (tile) tile.focus();
    }
    modal.addEventListener('click', event => {
        if (event.target.closest('[data-modal-apply]')) { closeStep(true); return; }
        if (event.target.closest('[data-modal-close]')) { closeStep(false); return; }
        const tab = event.target.closest('[data-step-tab]');
        if (tab && shown.step !== tab.dataset.stepTab) { shown.step = tab.dataset.stepTab; drawModalTabs(); drawBlock(); }
    });
    // Esc closes it (unless something in it took the key first: the icon panel, a picker).
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !modal.hidden && !event.defaultPrevented && !document.querySelector('.pcr-app.visible')) closeStep(false); });

    /* ---------- Changes ---------- */
    function setValue(key, raw, type) {
        const target = current();
        let v = raw;
        if (type === 'bool') v = !!raw;
        else if (type === 'number') {
            if (raw === '' || raw === null) v = (selected < 0 && /_(tablet|mobile)$/.test(key)) || (selected >= 0 && /^((text|title|icon|aside_title|aside_text)_size|icon_scale|icon_shadow_|shadow_|image_height)/.test(key)) ? null : target[key];
            else v = Number(raw);
        }
        target[key] = v;
    }
    const settingsBox = $('settings');
    settingsBox.addEventListener('input', event => {
        const el = event.target;
        if (el.dataset.rangeFor) {
            const number = settingsBox.querySelector('[data-key="' + el.dataset.rangeFor + '"]');
            number.value = el.value;
            setValue(number.dataset.key, el.value, 'number');
            afterInput(number);
            return;
        }
        if (!el.dataset.key) return;
        if (el.dataset.type === 'bool') return;
        if (el.dataset.type === 'number') {
            const range = settingsBox.querySelector('[data-range-for="' + el.dataset.key + '"]');
            if (range && el.value !== '') range.value = el.value;
        }
        // A per-device size emptied on Desktop (Backspace, 2026-11-25, user's request): its smallest value (0 where it may be), the field
        // left empty with it as the placeholder; on tablet / phone empty = the larger screen's, as before.
        const raw = el.dataset.base && el.value === '' && device === 'desktop' && boot.limits[el.dataset.base] ? String(boot.limits[el.dataset.base][0]) : el.value;
        setValue(el.dataset.key, raw, el.dataset.type);
        afterInput(el);
    });
    // After typing: the tiles (a title, a color) and what shows the values (dots, Reset, the gradient, Automatic).
    function afterInput(el) {
        const key = el.dataset.key;
        // A step's shadow part typed while it follows the cards' (shown on): it is the step's own from now on (2026-11-25).
        if (selected >= 0 && /^shadow_/.test(key) && current().shadow == null) current().shadow = true;
        if (/border_(color|style)$/.test(key)) refreshBox();
        changed(selected >= 0 ? key === 'title' || key === 'color' : /^color_|number_format/.test(key));
        const box = el.closest('.wpst-field');
        if (el.dataset.ownSize && box) { const icons = box.querySelector('.wpst-devices'); if (icons) icons.outerHTML = stepIcons(el.dataset.ownSize); }
        const base = box && box.querySelector('[data-base]') ? box.querySelector('[data-base]').dataset.base : null;
        if (base) {
            const icons = box.querySelector('.wpst-devices');
            if (icons) icons.outerHTML = deviceIcons(base);
            const range = box.querySelector('[data-range-for]');
            if (range && el.value === '') range.value = eff(base, device);
        }
        if (el.type === 'hidden') {
            const reset = el.parentNode.querySelector('.wpst-color__reset');
            if (reset) reset.disabled = !el.value;
            const bar = settingsBox.querySelector('.wpst-gradient');
            if (bar) bar.innerHTML = stepColors().map(c => '<span style="background:' + escape(c) + '"></span>').join('');
        }
    }
    settingsBox.addEventListener('input', event => { if (event.target.classList.contains('wpst-iconselect__search')) filterIcons(event.target.value); });
    // A corner typed: all four while linked; empty: none (the larger screen's, else a circle). The frame and the others follow.
    settingsBox.addEventListener('input', event => {
        const input = event.target.closest && event.target.closest('[data-iradius-corner]'), step = input && current();
        if (!step) return;
        const c0 = input.dataset.iradiusCorner, unit = step['icon_radius_' + c0 + '_unit'] || '%';
        const value = input.value === '' ? null : Math.max(0, Math.min(RADIUS_MAX[unit], Math.round(Number(input.value) || 0)));
        (step.icon_radius_linked !== false ? CORNERS : [c0]).forEach(c => { step[dkey('icon_radius_' + c, device)] = value; });
        const box = input.closest('.wpst-radius-box');
        CORNERS.forEach(c => { const other = box.querySelector('[data-iradius-corner="' + c + '"]'); if (other !== input) other.value = step[dkey('icon_radius_' + c, device)] == null ? '' : step[dkey('icon_radius_' + c, device)]; });
        box.querySelector('.wpst-radius-box__frame').style.borderRadius = CORNERS.map(c => { const v = cornerValue(step, c, device), u = step['icon_radius_' + c + '_unit'] || '%'; return v == null ? (uses('arc') ? '50%' : '0') : u === '%' ? v + '%' : Math.min(40, v / 2) + 'px'; }).join(' ');
        const field = box.closest('.wpst-field');
        field.querySelectorAll('.wpst-devices__btn').forEach(b => b.classList.toggle('has-value', CORNERS.some(c => step[dkey('icon_radius_' + c, b.dataset.device)] != null)));
        changed(false);
    });
    settingsBox.addEventListener('input', event => {
        const input = event.target.closest && event.target.closest('[data-pad-side], [data-margin-side]');
        if (!input) return;
        const t = current(), kind = input.dataset.marginSide ? 'margin' : 'padding', s0 = input.dataset.marginSide || input.dataset.padSide;
        let v = null;
        if (input.value === '') { if (device === 'desktop' && t === data) v = 0; }
        else { const n = Number(input.value); if (!Number.isFinite(n)) return; v = Math.max(0, Math.min(SPACE_MAX[t[kind + '_' + s0 + '_unit']] || 500, Math.round(n))); }
        (t[kind + '_linked'] !== false ? SIDES : [s0]).forEach(s => { t[dkey(kind + '_' + s, device)] = v; });
        refreshSpacing(); changed(false);
    });
    settingsBox.addEventListener('focusout', event => { if (event.target.matches && event.target.matches('[data-pad-side], [data-margin-side]')) setTimeout(refreshSpacing); });
    // The box (2026-11-25): a value typed (all of its kind while linked; emptied: the cards' border / radius on Desktop 0, else none).
    settingsBox.addEventListener('input', event => {
        const input = event.target.closest && event.target.closest('input[data-box]');
        if (!input) return;
        const scope = input.dataset.box, kind = input.dataset.boxKind, p0 = input.dataset.boxPart, t = boxTarget(scope);
        let v = null;
        if (input.value === '') { if (boxFixed(scope, kind) && device === 'desktop') v = 0; }
        else { const n = Number(input.value); if (!Number.isFinite(n)) return; v = Math.max(0, Math.min(BOX[kind].units[boxUnit(scope, kind, p0)] || 500, Math.round(n))); }
        (t[boxKey(scope, kind, 'linked')] !== false ? BOX[kind].parts : [p0]).forEach(p => { t[dkey(boxKey(scope, kind, p), device)] = v; });
        refreshBox(); changed(false);
    });
    settingsBox.addEventListener('focusout', event => { if (event.target.matches && event.target.matches('input[data-box]')) setTimeout(refreshBox); });
    settingsBox.addEventListener('keydown', event => {
        const panel = iconPanel();
        if (!panel || panel.hidden || !panel.contains(event.target)) return;
        if (event.key === 'Escape') { event.preventDefault(); openIcons(false); $('icon-select').focus(); return; }
        if (!/^Arrow(Up|Down|Left|Right)$/.test(event.key)) {
            if (event.key === 'Enter' && event.target.classList.contains('wpst-iconselect__search')) { event.preventDefault(); const first = iconOptions()[0]; if (first) first.click(); }
            return;
        }
        if (event.target.classList.contains('wpst-iconselect__search')) { if (event.key === 'ArrowDown') { event.preventDefault(); const first = iconOptions()[0]; if (first) first.focus(); } return; }
        event.preventDefault();
        moveInIcons(event.target, event.key);
    });
    // The design select: open / close, choose, keys, a click outside.
    $('design-select').addEventListener('click', event => {
        if (event.target.closest('#wpst-design-button')) { openDesigns($('design-select').querySelector('.wpst-designselect__panel').hidden); return; }
        const item = event.target.closest('[data-design]');
        if (item) chooseDesign(item.dataset.design);
    });
    $('design-select').addEventListener('keydown', event => {
        const panel = $('design-select').querySelector('.wpst-designselect__panel');
        if (!panel || panel.hidden) return;
        if (event.key === 'Escape') { event.preventDefault(); openDesigns(false); $('design-button').focus(); return; }
        if (!/^Arrow(Up|Down|Left|Right)$/.test(event.key)) return;
        event.preventDefault();
        const list = [...panel.querySelectorAll('.wpst-design')], i = list.indexOf(document.activeElement), step = /Right|Down/.test(event.key) ? 1 : -1;
        const to = list[Math.max(0, Math.min(list.length - 1, (i < 0 ? 0 : i + step)))];
        if (to) to.focus();
    });
    document.addEventListener('mousedown', event => { const panel = $('design-select') && $('design-select').querySelector('.wpst-designselect__panel'); if (panel && !panel.hidden && !event.target.closest('#wpst-design-select')) openDesigns(false); });
    document.addEventListener('mousedown', event => { const panel = iconPanel(); if (panel && !panel.hidden && !event.target.closest('.wpst-iconselect')) openIcons(false); });
    settingsBox.addEventListener('change', event => {
        const el = event.target;
        if (el.dataset.type === 'bool') {
            setValue(el.dataset.key, el.checked, 'bool');
            changed(false);
            // Some switches show or hide the fields under them.
            if (['show_label', 'shadow', 'icon_shadow', 'icon_use_radius', 'use_padding'].indexOf(el.dataset.key) !== -1) drawBlock(true);
        } else if (el.tagName === 'SELECT' && el.dataset.key) {
            setValue(el.dataset.key, el.value);
            if (/border_style$/.test(el.dataset.key)) refreshBox();
            if (el.dataset.key === 'image_size') { changed(false); drawBlock(true); return; }
            // The icon's place / alignment per device (2026-11-24): '' on tablet / phone = the larger screen's (null); a new place sets the
            // alignment on that device back to the usual one for it (its choices change).
            if (el.dataset.devSelect) {
                if (device !== 'desktop' && el.value === '') data[el.dataset.key] = null;
                if (el.dataset.devSelect === 'icon_position') { const p = iconPos(device); data[dkey('icon_align', device)] = device === 'desktop' ? '' : (p ? (p === 'top' || p === 'bottom' ? 'start' : 'center') : null); }
                changed(false); drawBlock(true); return;
            }
            changed(false);
        }
    });
    settingsBox.addEventListener('click', event => {
        const swatch = event.target.closest('.wpst-color__swatch');
        if (swatch) { openPicker(swatch); return; }
        // The box: a part's unit (all of its kind while linked), values kept within its largest; a kind's link (linking: all as the first).
        const boxUnitPick = event.target.closest('[data-box-unit]');
        if (boxUnitPick) {
            const scope = boxUnitPick.dataset.boxUnit, kind = boxUnitPick.dataset.boxKind, p0 = boxUnitPick.dataset.boxPart, unit = boxUnitPick.dataset.unit, t = boxTarget(scope);
            (t[boxKey(scope, kind, 'linked')] !== false ? BOX[kind].parts : [p0]).forEach(p => {
                const k = boxKey(scope, kind, p);
                t[k + '_unit'] = unit;
                Object.keys(DEVICES).forEach(d => { if (t[dkey(k, d)] != null) t[dkey(k, d)] = Math.min(BOX[kind].units[unit], t[dkey(k, d)]); });
            });
            changed(false); drawBlock(true); return;
        }
        const boxLink = event.target.closest('[data-box-link]');
        if (boxLink) {
            const scope = boxLink.dataset.boxLink, kind = boxLink.dataset.boxKind, t = boxTarget(scope), lk = boxKey(scope, kind, 'linked');
            t[lk] = t[lk] === false;
            if (t[lk]) { const first = boxKey(scope, kind, BOX[kind].parts[0]), v = t[dkey(first, device)], u = t[first + '_unit']; BOX[kind].parts.forEach(p => { const k = boxKey(scope, kind, p); t[dkey(k, device)] = v; if (kind !== 'border') t[k + '_unit'] = u || 'px'; }); }
            changed(false); drawBlock(true); return;
        }
        const unitPick = event.target.closest('[data-iradius-unit]');
        if (unitPick) {
            const step = current(), unit = unitPick.dataset.unit, c0 = unitPick.dataset.iradiusUnit;
            (step.icon_radius_linked !== false ? CORNERS : [c0]).forEach(c => {
                step['icon_radius_' + c + '_unit'] = unit;
                Object.keys(DEVICES).forEach(d => { const k = dkey('icon_radius_' + c, d); if (step[k] != null) step[k] = Math.min(RADIUS_MAX[unit], step[k]); });
            });
            changed(false); drawBlock(true); return;
        }
        // A side's unit (all four while linked), its values kept within the unit's largest.
        const spaceUnit = event.target.closest('[data-pad-unit], [data-margin-unit]');
        if (spaceUnit) {
            const t = current(), kind = spaceUnit.dataset.marginUnit ? 'margin' : 'padding', s0 = spaceUnit.dataset.marginUnit || spaceUnit.dataset.padUnit, unit = spaceUnit.dataset.unit;
            (t[kind + '_linked'] !== false ? SIDES : [s0]).forEach(s => {
                t[kind + '_' + s + '_unit'] = unit;
                Object.keys(DEVICES).forEach(d => { const k = dkey(kind + '_' + s, d); if (t[k] != null) t[k] = Math.min(SPACE_MAX[unit], t[k]); });
            });
            changed(false); drawBlock(true); return;
        }
        // A box's link: linking gives every side the top one's value and unit on the device edited.
        const spaceLink = event.target.closest('[data-pad-link], [data-margin-link]');
        if (spaceLink) {
            const t = current(), kind = spaceLink.hasAttribute('data-margin-link') ? 'margin' : 'padding';
            t[kind + '_linked'] = t[kind + '_linked'] === false;
            if (t[kind + '_linked']) { const v = t[dkey(kind + '_top', device)], u = t[kind + '_top_unit'] || 'px'; SIDES.forEach(s => { t[dkey(kind + '_' + s, device)] = v; t[kind + '_' + s + '_unit'] = u; }); }
            changed(false); drawBlock(true); return;
        }
        if (event.target.closest('[data-iradius-link]')) {
            const step = current();
            step.icon_radius_linked = step.icon_radius_linked === false;
            if (step.icon_radius_linked) { const v = step[dkey('icon_radius_tl', device)], u = step.icon_radius_tl_unit || '%'; CORNERS.forEach(c => { step[dkey('icon_radius_' + c, device)] = v; step['icon_radius_' + c + '_unit'] = u; }); }
            changed(false); drawBlock(true); return;
        }
        const colorReset = event.target.closest('[data-color-reset]');
        if (colorReset) {
            closePicker();
            const box = colorReset.closest('.wpst-color'), input = box.querySelector('input'), c = parseColor(box.dataset.fallback) || {hex:'#000000', alpha:100};
            input.value = '';
            box.querySelector('.wpst-color__chip').style.boxShadow = 'inset 0 0 0 60px ' + colorValue(c.hex, c.alpha);
            input.dispatchEvent(new Event('input', {bubbles:true}));
            return;
        }
        const pick = event.target.closest('[data-choice]');
        if (pick) {
            current()[pick.dataset.choice] = pick.dataset.value;
            pick.parentNode.querySelectorAll('[data-choice]').forEach(b => b.setAttribute('aria-pressed', String(b === pick)));
            changed(pick.dataset.choice === 'number_format');
            if (pick.dataset.choice === 'marker') drawBlock(true);
            if (pick.dataset.choice === 'icon_shape' || pick.dataset.choice === 'media') { if (pick.dataset.choice === 'media') changed(true); drawBlock(true); }
            return;
        }
        const dev = event.target.closest('[data-device]');
        if (dev) { setDevice(dev.dataset.device); return; }
        if (event.target.closest('#wpst-icon-select')) { openIcons(iconPanel().hidden); return; }
        const icon = event.target.closest('[data-icon]');
        if (icon) {
            current().icon = icon.dataset.icon;
            changed(true);
            drawBlock();
            return;
        }
        if (event.target.closest('#wpst-image-pick')) { pickImage(); return; }
        if (event.target.closest('#wpst-image-remove')) {
            current().image = 0;
            changed(true);
            drawBlock();
        }
    });
    // Alt + arrows on a step tile: move the step.
    $('tiles').addEventListener('keydown', event => {
        const tile = event.target.closest('.wpst-tile--step');
        if (!tile || !event.altKey || (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight')) return;
        event.preventDefault();
        const from = Number(tile.dataset.index), to = from + (event.key === 'ArrowLeft' ? -1 : 1);
        if (to < 0 || to >= data.steps.length) return;
        moveStep(from, to);
        const button = $('tiles').querySelector('[data-select="' + to + '"]');
        if (button) button.focus();
    });

    function pickImage() {
        if (!window.wp || !wp.media) return;
        const frame = wp.media({title:'Step image', library:{type:'image'}, button:{text:'Use this image'}, multiple:false});
        const step = current();
        frame.on('select', () => {
            const a = frame.state().get('selection').first().toJSON();
            images[a.id] = {url: (a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url), name: a.filename || a.title || ''};
            step.image = a.id;
            changed(true);
            drawBlock();
        });
        frame.open();
    }

    /* ---------- Tiles: choose, add, duplicate, delete, reorder ---------- */
    $('tiles').addEventListener('click', event => {
        const sel = event.target.closest('[data-select]');
        if (sel) { choose(Number(sel.dataset.select)); return; }
        const add = event.target.closest('#wpst-add');
        if (add) {
            if (data.steps.length >= boot.maxSteps) return;
            const names = Object.keys(ICONS), used = data.steps.map(s => s.icon);
            data.steps.push(Object.assign({}, boot.step, {title:'New step', text:'A short description of this step.', icon:names.find(n => used.indexOf(n) === -1) || names[0]}));
            shown.step = 'title';
            changed(false);
            drawTiles();
            choose(data.steps.length - 1);
            return;
        }
        const clone = event.target.closest('[data-clone]');
        if (clone) {
            const i = Number(clone.dataset.clone);
            data.steps.splice(i + 1, 0, JSON.parse(JSON.stringify(data.steps[i])));
            changed(false);
            drawAll();
            return;
        }
        const del = event.target.closest('[data-delete]');
        if (del) {
            const i = Number(del.dataset.delete), step = data.steps[i];
            if (data.steps.length < 2) return;
            if ((step.title || step.text) && !window.confirm('Delete step ' + (i + 1) + (plainTitle(step.title) ? ' “' + plainTitle(step.title) + '”' : '') + '?')) return;
            data.steps.splice(i, 1);
            changed(false);
            drawAll();
        }
    });
    function moveStep(from, to) {
        const [step] = data.steps.splice(from, 1);
        data.steps.splice(to, 0, step);
        changed(false);
        drawAll();
    }
    let dragFrom = -1;
    const tiles = $('tiles');
    tiles.addEventListener('dragstart', event => {
        const tile = event.target.closest('.wpst-tile--step');
        if (!tile) return;
        dragFrom = Number(tile.dataset.index);
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(dragFrom));
        tile.classList.add('is-dragging');
    });
    const clearDrop = () => tiles.querySelectorAll('.is-drop-before, .is-drop-after').forEach(t => t.classList.remove('is-drop-before', 'is-drop-after'));
    function dropPlace(event) {
        const tile = event.target.closest('.wpst-tile--step');
        if (!tile || dragFrom < 0) return null;
        const r = tile.getBoundingClientRect();
        return {tile, index:Number(tile.dataset.index), after:event.clientX > r.left + r.width / 2};
    }
    tiles.addEventListener('dragover', event => {
        const place = dropPlace(event);
        if (!place) return;
        event.preventDefault();
        clearDrop();
        place.tile.classList.add(place.after ? 'is-drop-after' : 'is-drop-before');
    });
    tiles.addEventListener('drop', event => {
        const place = dropPlace(event);
        clearDrop();
        if (!place) return;
        event.preventDefault();
        let to = place.index + (place.after ? 1 : 0);
        if (to > dragFrom) to--;
        if (to !== dragFrom) moveStep(dragFrom, to);
    });
    tiles.addEventListener('dragend', () => {
        dragFrom = -1;
        clearDrop();
        tiles.querySelectorAll('.is-dragging').forEach(t => t.classList.remove('is-dragging'));
    });

    $('menu').addEventListener('click', event => {
        const item = event.target.closest('[data-block]');
        if (!item) return;
        shown[selected < 0 ? 'settings' : 'step'] = item.dataset.block;
        drawMenu();
        drawBlock();
    });

    /* ---------- Device and preview ---------- */
    const frame = $('frame'), stage = $('stage');
    function setDevice(d) {
        if (!DEVICES[d]) return;
        device = d;
        store.set('wpstDevice', d);
        document.querySelectorAll('[data-preview-device]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.previewDevice === d)));
        // Every block redraws for the device chosen (2026-11-24: it was Sizes only, gone since; per-device fields are in most blocks now).
        drawBlock();
        fit();
    }
    document.querySelectorAll('[data-preview-device]').forEach(b => b.addEventListener('click', () => setDevice(b.dataset.previewDevice)));
    function fit() {
        const width = SCREENS[device], scale = Math.min(1, stage.clientWidth / width);
        frame.style.width = width + 'px';
        frame.style.transform = 'scale(' + scale + ')';
        // The page's own height (the body's: the document is at least as tall as the frame, which would never shrink).
        const doc = frame.contentDocument;
        const height = doc && doc.body ? Math.max(120, Math.ceil(doc.body.getBoundingClientRect().height)) : 400;
        frame.style.height = height + 'px';
        stage.style.height = Math.ceil(height * scale) + 'px';
        $('screen').textContent = DEVICES[device][1] + ': ' + width + ' px wide' + (scale < 1 ? ', shown at ' + Math.round(scale * 100) + '%' : '') + '. The preview has the plugin\'s styles only; on the site the steps take the theme\'s fonts.';
    }
    let watcher = null;
    frame.addEventListener('load', () => {
        fit();
        // The height again when it changes inside (images loading, a new width).
        if (watcher) watcher.disconnect();
        if (window.ResizeObserver && frame.contentDocument && frame.contentDocument.body) {
            watcher = new ResizeObserver(() => fit());
            watcher.observe(frame.contentDocument.body);
        }
    });
    if (window.ResizeObserver) new ResizeObserver(() => fit()).observe(stage);
    function setStale(on) { $('refresh').classList.toggle('is-stale', on); }
    let request = 0;
    function refresh() {
        const mine = ++request, body = new FormData();
        body.append('action', boot.action);
        body.append('nonce', boot.nonce);
        body.append('post_id', boot.postId);
        body.append('settings', JSON.stringify(data));
        setStale(false);
        stage.classList.add('is-loading');
        fetch(boot.endpoint, {method:'POST', body, credentials:'same-origin'}).then(r => r.ok ? r.text() : Promise.reject(r.status)).then(html => {
            if (mine !== request) return;
            frame.srcdoc = html;
        }).catch(() => {
            if (mine === request) frame.srcdoc = '<p style="font:14px sans-serif;color:#b32d2e;padding:16px">The preview could not be loaded. Save the steps and reload the page.</p>';
        }).finally(() => { if (mine === request) stage.classList.remove('is-loading'); });
    }
    $('refresh').addEventListener('click', refresh);

    /* ---------- Saving ---------- */
    const form = document.getElementById('post');
    if (form) form.addEventListener('submit', event => {
        sync();
        if ($('data').value.length > boot.maxData) {
            event.preventDefault();
            window.alert('The steps are too large to be saved. Remove some steps or shorten their texts.');
            return;
        }
        submitting = true;
    });
    window.addEventListener('beforeunload', event => {
        if (dirty && !submitting) { event.preventDefault(); event.returnValue = ''; }
    });

    sync();
    drawAll();
    setDevice(device);
    refresh();
}());
