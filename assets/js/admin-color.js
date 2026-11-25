/*
 * The editor's colours (admin.js), as in WP Banner: parsing and writing a colour value and the
 * colour picker (Pickr, with the palette, Default and the eyedropper). admin.js draws the fields.
 */
window.WPST_Color = (function () {
    'use strict';
    // "#rgb", "#rrggbb" or "rgba(r,g,b,a)" as {hex, alpha 0–100}; null for anything else.
    function parseColor(value) {
        value = String(value || '').replace(/\s+/g, '').toLowerCase();
        const m = /^rgba\((\d{1,3}),(\d{1,3}),(\d{1,3}),(\d*\.?\d+)\)$/.exec(value);
        if (m) return {hex:'#' + [m[1], m[2], m[3]].map(c => ('0' + Math.min(255, parseInt(c, 10)).toString(16)).slice(-2)).join(''), alpha:Math.round(Math.min(1, parseFloat(m[4])) * 100)};
        if (/^#[0-9a-f]{3}$/.test(value)) value = '#' + value[1] + value[1] + value[2] + value[2] + value[3] + value[3];
        return /^#[0-9a-f]{6}$/.test(value) ? {hex:value, alpha:100} : null;
    }
    function colorValue(hex, alpha) {
        return alpha >= 100 ? hex : 'rgba(' + [1, 3, 5].map(i => parseInt(hex.substr(i, 2), 16)).join(',') + ',' + (alpha / 100) + ')';
    }
    // The picker: one at a time, made on the first click of a swatch (in the body, as Pickr puts it).
    // The palette (WP_Steps_Palette): set by admin.js from its boot data (palette()).
    let PALETTE = [];
    let colorPicker = null;
    function closePicker() {
        if (!colorPicker) return;
        const root = colorPicker.getRoot();
        colorPicker.destroy();
        if (root && root.app) root.app.remove();
        if (colorPicker.wpstButton) colorPicker.wpstButton.setAttribute('aria-expanded', 'false');
        colorPicker = null;
    }
    // The eyedropper's icon: a pipette (pipette.svg, in the button's colour; the viewBox cropped to the drawing).
    const EYEDROPPER = '<svg viewBox="314 314 380 380" aria-hidden="true" focusable="false" fill="currentColor">'
        + '<rect x="480.78" y="406.64" width="177.31" height="63.87" rx="31.93" ry="31.93" transform="translate(476.9 -274.19) rotate(45)"/>'
        + '<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-miterlimit="10" stroke-width="20" d="M621.5,373.23c7.35-7.35,19.25-7.35,26.6,0"/>'
        + '<path d="M653.93,354.08c13.82,13.82,13.82,36.32,0,50.15l-194.31,194.31c-8.98,8.98-22.19,12.47-34.48,9.11l-11.21-3.06-57.7,57.7c-2.72,2.72-7.13,2.72-9.84,0-3.08-3.08-3.08-8.1,0-11.17l57.04-57.04-3.06-11.21c-3.37-12.29.13-25.5,9.11-34.48l194.31-194.31c13.82-13.82,36.32-13.82,50.15,0M668.07,339.94h0c-21.66-21.66-56.77-21.66-78.43,0l-194.31,194.31c-14.6,14.6-19.35,35.31-14.26,53.9l-48.82,48.82c-10.9,10.9-10.9,28.56,0,39.46h0c10.53,10.53,27.6,10.53,38.13,0l49.48-49.48c18.59,5.08,39.3.34,53.9-14.26l194.31-194.31c21.66-21.66,21.66-56.77,0-78.43h0Z"/>'
        + '</svg>';
    function openPicker(button) {
        if (!window.Pickr) return;
        if (colorPicker && colorPicker.wpstButton === button) return;
        closePicker();
        const box = button.closest('.wpst-color'), input = box.querySelector('input'), alpha = box.dataset.alpha === '1';
        let syncing = false;
        let opened = input.value;
        // The color shown: the field's, or (empty) the one it stands for.
        const now = () => parseColor(input.value) || parseColor(box.dataset.fallback) || parseColor(box.dataset.default) || {hex:'#000000', alpha:100};
        // What is chosen ('' for an empty field): into the swatch and its input (the preview's Update and saving take it as any other change).
        const store = value => {
            if (input.value === value) return;
            input.value = value;
            const c = now();
            button.firstChild.style.boxShadow = 'inset 0 0 0 60px ' + colorValue(c.hex, c.alpha);
            input.dispatchEvent(new Event('input', {bubbles:true}));
        };
        // Into Pickr (no event of its own), in the format it shows; Cancel goes back to it.
        const show = () => {
            const c = now(), shown = colorPicker.getColorRepresentation() || 'HEXA';
            syncing = true; colorPicker.setColor(colorValue(c.hex, c.alpha), true); colorPicker.setColorRepresentation(shown); syncing = false;
            colorPicker.applyColor(true);
        };
        // From Pickr: only a new color counts (its format buttons say "change" too).
        const fromPickr = color => {
            if (!color || syncing) return;
            const rgba = color.toRGBA();
            const hex = '#' + [0, 1, 2].map(i => ('0' + Math.max(0, Math.min(255, Math.round(rgba[i]))).toString(16)).slice(-2)).join('');
            const value = colorValue(hex, alpha ? Math.max(0, Math.min(100, Math.round(rgba[3] * 100))) : 100), c = now();
            // The color already shown (an empty field's too): nothing new.
            if (value !== colorValue(c.hex, c.alpha)) store(value);
        };
        const c = now();
        colorPicker = window.Pickr.create({
            el: button, useAsButton: true, theme: 'classic', appClass: 'wpst-pcr', position: 'bottom-start',
            default: colorValue(c.hex, c.alpha), defaultRepresentation: 'HEXA', lockOpacity: !alpha,
            swatches: PALETTE.map(entry => entry.color),
            i18n: {'ui:dialog':'Color picker', 'btn:toggle':'Choose a color', 'btn:swatch':'Color', 'btn:cancel':'Cancel', 'aria:btn:cancel':'Back to the color it had when opened',
                'aria:input':'Color value', 'aria:palette':'Color and brightness', 'aria:hue':'Hue', 'aria:opacity':'Opacity'},
            components: {preview:false, opacity:alpha, hue:true, interaction:{hex:true, rgba:true, hsla:true, input:true, cancel:true}}
        });
        colorPicker.wpstButton = button;
        // Pickr fits its window into the page, not the screen: near the bottom of the screen it would open
        // below it, cut off (its window does not scroll). Then above the swatch, where there is room.
        const place = colorPicker._rePositioningPicker;
        if (typeof place === 'function') colorPicker._rePositioningPicker = function () {
            place.apply(this, arguments);
            const box = this._root && this._root.app;
            if (!box) return;
            const r = box.getBoundingClientRect(), b = button.getBoundingClientRect();
            if (r.bottom > innerHeight - 8 && b.top - r.height - 8 >= 8) box.style.top = (b.top - r.height - 8) + 'px';
        };
        colorPicker.on('init', instance => {
            const root = instance.getRoot().app;
            // The palette's names on hover.
            root.querySelectorAll('.pcr-swatches > button').forEach((swatch, i) => { if (PALETTE[i]) { swatch.title = PALETTE[i].name; swatch.setAttribute('aria-label', PALETTE[i].name); } });
            // Default, after Cancel: red, as Pickr's own Clear (classic theme).
            const reset = document.createElement('input');
            reset.type = 'button'; reset.className = 'pcr-clear wpst-pcr__default'; reset.value = 'Default'; reset.setAttribute('aria-label', 'Back to the default color');
            reset.addEventListener('click', () => { store(box.dataset.default); show(); });
            root.querySelector('.pcr-interaction').append(reset);
            // The eyedropper (2026-11-09): beside the colour's value, where the browser has one (Chrome, Edge, Opera; not Firefox or
            // Safari): a colour from anywhere on the screen, e.g. the photo in the preview; the field's opacity kept; Esc: no change.
            if (window.EyeDropper) {
                const drop = document.createElement('button'), result = root.querySelector('.pcr-result'), line = document.createElement('span');
                drop.type = 'button'; drop.className = 'wpst-pcr__dropper'; drop.title = 'Pick a color from the screen'; drop.setAttribute('aria-label', 'Pick a color from the screen');
                drop.innerHTML = EYEDROPPER;
                line.className = 'wpst-pcr__break';
                drop.addEventListener('click', () => {
                    new window.EyeDropper().open().then(picked => {
                        const v = String(picked && picked.sRGBHex || ''), m = /^rgba?\((\d+),\s*(\d+),\s*(\d+)/.exec(v);
                        const c = m ? {hex:'#' + [m[1], m[2], m[3]].map(x => ('0' + Math.min(255, Number(x)).toString(16)).slice(-2)).join('')} : parseColor(v);
                        if (!c) return;
                        store(colorValue(c.hex, alpha ? now().alpha : 100)); show();
                    }).catch(() => { /* Esc, or the page left: nothing changes. */ });
                });
                result.after(drop, line);
            }
            instance.show();
        });
        colorPicker.on('show', () => { opened = input.value; show(); button.setAttribute('aria-expanded', 'true'); });
        colorPicker.on('hide', () => button.setAttribute('aria-expanded', 'false'));
        colorPicker.on('change', fromPickr);
        // Cancel: the field as it was when opened (an empty one empty again).
        colorPicker.on('cancel', instance => { store(opened); instance.hide(); });
    }
    function palette(list) { PALETTE = (Array.isArray(list) ? list : []).filter(entry => entry && /^#[0-9a-f]{6}$/.test(entry.color)); }
    return {parseColor, colorValue, openPicker, closePicker, palette};
}());
