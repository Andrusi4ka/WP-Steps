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
    const boxVal = (scope, kind, part, d) => inherit(boxTarget(scope), boxKey(scope, kind, part), d);
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
