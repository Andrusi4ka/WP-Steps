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
