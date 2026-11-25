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
