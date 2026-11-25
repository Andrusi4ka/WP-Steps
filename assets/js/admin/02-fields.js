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
    // A value of t (the Settings' data or a step) on device d: its own there, else the larger screen's; null without any.
    // (2026-11-25: one walk for every per-device value, was six copies.)
    function inherit(t, base, d) {
        for (let dv = d; dv; dv = UP[dv]) { const v = t[dkey(base, dv)]; if (v != null) return v; }
        return null;
    }
    const eff = (base, d) => inherit(data, base, d);
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
