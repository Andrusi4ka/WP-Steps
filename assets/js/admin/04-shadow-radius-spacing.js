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
    const cornerValue = (step, c, d) => inherit(step, 'icon_radius_' + c, d);
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
    const spaceVal = inherit;
    const spaceHint = (t, key) => { const v = device === 'desktop' ? null : spaceVal(t, key, UP[device]); return v == null || (t === data && Number(v) === 0) ? '' : v; };
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
