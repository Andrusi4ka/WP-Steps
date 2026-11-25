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
    // Its words (2026-11-25: read through DOMParser, which runs nothing: an element's innerHTML would load an <img onerror>).
    const plainTitle = html => (new DOMParser().parseFromString(String(html || ''), 'text/html').body.textContent || '').trim();
    // Font size, Font weight and Color of the title or the text (title_* / text_*).
    const WEIGHT_LIST = [['', 'Default'], ['300', 'Light 300'], ['400', 'Regular 400'], ['500', 'Medium 500'], ['600', 'Semibold 600'], ['700', 'Bold 700'], ['800', 'Extra bold 800']];
    const WEIGHT_NAMES = Object.fromEntries(WEIGHT_LIST);
    function styleRow(kind) {
        const fallback = kind === 'title' ? '#1f2937' : '#4b5563';
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
        const usualColor = title ? stepColors()[selected] : '#6b7280';
        return '<div class="wpst-row3">' + stepSize(k + '_size', 'Font size', {min:title ? 10 : 10, max:title ? 80 : 40, usual:usualSize, tip:'Empty: the larger screen\'s, else the usual (its placeholder).'})
            + select(dkey(k + '_weight', device), 'Font weight', weights, {shown:step[dkey(k + '_weight', device)] || ''})
            + color(dkey(k + '_color', device), 'Color', {def:'', fallback:(device === 'desktop' ? '' : up(k + '_color')) || usualColor}) + '</div>';
    }
    // A step's own size on the device edited (Font size under its Text): empty = its larger screen's, then Sizes' (its placeholder).
    function stepInherited(base, d, usual) {
        const step = current();
        const up = UP[d] ? inherit(step, base, UP[d]) : null;
        if (up != null) return up;
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
