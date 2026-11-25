    // Alt + arrows on a step tile: move the step.
    $('tiles').addEventListener('keydown', event => {
        const tile = event.target.closest('.wpst-tile--step');
        if (!tile || !event.altKey || (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight')) return;
        event.preventDefault();
        const from = Number(tile.dataset.index), to = from + (event.key === 'ArrowLeft' ? -1 : 1);
        if (to < 0 || to >= data.steps.length) return;
        moveStep(from, to);
        const button = $('tiles').querySelector('[data-select="' + to + '"]');
        if (button) button.focus();
    });

    function pickImage() {
        if (!window.wp || !wp.media) return;
        const frame = wp.media({title:'Step image', library:{type:'image'}, button:{text:'Use this image'}, multiple:false});
        const step = current();
        frame.on('select', () => {
            const a = frame.state().get('selection').first().toJSON();
            images[a.id] = {url: (a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url), name: a.filename || a.title || ''};
            step.image = a.id;
            changed(true);
            drawBlock();
        });
        frame.open();
    }

    /* ---------- Tiles: choose, add, duplicate, delete, reorder ---------- */
    $('tiles').addEventListener('click', event => {
        const sel = event.target.closest('[data-select]');
        if (sel) { choose(Number(sel.dataset.select)); return; }
        const add = event.target.closest('#wpst-add');
        if (add) {
            if (data.steps.length >= boot.maxSteps) return;
            const names = Object.keys(ICONS), used = data.steps.map(s => s.icon);
            data.steps.push(Object.assign({}, boot.step, {title:'New step', text:'A short description of this step.', icon:names.find(n => used.indexOf(n) === -1) || names[0]}));
            shown.step = 'title';
            changed(false);
            drawTiles();
            choose(data.steps.length - 1);
            return;
        }
        const clone = event.target.closest('[data-clone]');
        if (clone) {
            const i = Number(clone.dataset.clone);
            data.steps.splice(i + 1, 0, JSON.parse(JSON.stringify(data.steps[i])));
            changed(false);
            drawAll();
            return;
        }
        const del = event.target.closest('[data-delete]');
        if (del) {
            const i = Number(del.dataset.delete), step = data.steps[i];
            if (data.steps.length < 2) return;
            if ((step.title || step.text) && !window.confirm('Delete step ' + (i + 1) + (plainTitle(step.title) ? ' “' + plainTitle(step.title) + '”' : '') + '?')) return;
            data.steps.splice(i, 1);
            changed(false);
            drawAll();
        }
    });
    function moveStep(from, to) {
        const [step] = data.steps.splice(from, 1);
        data.steps.splice(to, 0, step);
        changed(false);
        drawAll();
    }
    let dragFrom = -1;
    const tiles = $('tiles');
    tiles.addEventListener('dragstart', event => {
        const tile = event.target.closest('.wpst-tile--step');
        if (!tile) return;
        dragFrom = Number(tile.dataset.index);
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(dragFrom));
        tile.classList.add('is-dragging');
    });
    const clearDrop = () => tiles.querySelectorAll('.is-drop-before, .is-drop-after').forEach(t => t.classList.remove('is-drop-before', 'is-drop-after'));
    function dropPlace(event) {
        const tile = event.target.closest('.wpst-tile--step');
        if (!tile || dragFrom < 0) return null;
        const r = tile.getBoundingClientRect();
        return {tile, index:Number(tile.dataset.index), after:event.clientX > r.left + r.width / 2};
    }
    tiles.addEventListener('dragover', event => {
        const place = dropPlace(event);
        if (!place) return;
        event.preventDefault();
        clearDrop();
        place.tile.classList.add(place.after ? 'is-drop-after' : 'is-drop-before');
    });
    tiles.addEventListener('drop', event => {
        const place = dropPlace(event);
        clearDrop();
        if (!place) return;
        event.preventDefault();
        let to = place.index + (place.after ? 1 : 0);
        if (to > dragFrom) to--;
        if (to !== dragFrom) moveStep(dragFrom, to);
    });
    tiles.addEventListener('dragend', () => {
        dragFrom = -1;
        clearDrop();
        tiles.querySelectorAll('.is-dragging').forEach(t => t.classList.remove('is-dragging'));
    });

    $('menu').addEventListener('click', event => {
        const item = event.target.closest('[data-block]');
        if (!item) return;
        shown[selected < 0 ? 'settings' : 'step'] = item.dataset.block;
        drawMenu();
        drawBlock();
    });

