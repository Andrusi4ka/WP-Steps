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
        // Closed without Apply after a change (Cancel, ×, Esc): asked first (2026-11-25, user's request); No keeps it open.
        if (!apply && stepBefore !== null && JSON.stringify(data.steps[was]) !== stepBefore
            && !window.confirm('Close without applying? The changes to this step will be lost.')) return;
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

