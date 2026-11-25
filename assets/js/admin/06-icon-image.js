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
        const style = step.icon_style || 'white', shape = step.icon_shape || (style === 'plain' ? 'none' : 'filled');
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

