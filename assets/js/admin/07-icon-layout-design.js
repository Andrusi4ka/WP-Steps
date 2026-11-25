    /*
     * The icon's place and alignment per device (2026-11-24): Desktop '' = Auto; tablet / phone null = the larger screen's (the first
     * option says which), 'auto' = Auto there. iconPos(d): the place in effect ('' for Auto).
     */
    const devValue = (base, d) => { const v = inherit(data, base, d); return v == null ? '' : v; };
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

