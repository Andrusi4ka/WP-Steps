    /* ---------- Device and preview ---------- */
    const frame = $('frame'), stage = $('stage');
    function setDevice(d) {
        if (!DEVICES[d]) return;
        device = d;
        store.set('wpstDevice', d);
        document.querySelectorAll('[data-preview-device]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.previewDevice === d)));
        // Every block redraws for the device chosen (2026-11-24: it was Sizes only, gone since; per-device fields are in most blocks now).
        drawBlock();
        fit();
    }
    document.querySelectorAll('[data-preview-device]').forEach(b => b.addEventListener('click', () => setDevice(b.dataset.previewDevice)));
    function fit() {
        const width = SCREENS[device], scale = Math.min(1, stage.clientWidth / width);
        frame.style.width = width + 'px';
        frame.style.transform = 'scale(' + scale + ')';
        // The page's own height (the body's: the document is at least as tall as the frame, which would never shrink).
        const doc = frame.contentDocument;
        const height = doc && doc.body ? Math.max(120, Math.ceil(doc.body.getBoundingClientRect().height)) : 400;
        frame.style.height = height + 'px';
        stage.style.height = Math.ceil(height * scale) + 'px';
        $('screen').textContent = DEVICES[device][1] + ': ' + width + ' px wide' + (scale < 1 ? ', shown at ' + Math.round(scale * 100) + '%' : '') + '. The preview has the plugin\'s styles only; on the site the steps take the theme\'s fonts.';
    }
    let watcher = null;
    frame.addEventListener('load', () => {
        fit();
        // The height again when it changes inside (images loading, a new width).
        if (watcher) watcher.disconnect();
        if (window.ResizeObserver && frame.contentDocument && frame.contentDocument.body) {
            watcher = new ResizeObserver(() => fit());
            watcher.observe(frame.contentDocument.body);
        }
    });
    if (window.ResizeObserver) new ResizeObserver(() => fit()).observe(stage);
    function setStale(on) { $('refresh').classList.toggle('is-stale', on); }
    let request = 0;
    function refresh() {
        const mine = ++request, body = new FormData();
        body.append('action', boot.action);
        body.append('nonce', boot.nonce);
        body.append('post_id', boot.postId);
        body.append('settings', JSON.stringify(data));
        setStale(false);
        stage.classList.add('is-loading');
        fetch(boot.endpoint, {method:'POST', body, credentials:'same-origin'}).then(r => r.ok ? r.text() : Promise.reject(r.status)).then(html => {
            if (mine !== request) return;
            frame.srcdoc = html;
        }).catch(() => {
            if (mine === request) frame.srcdoc = '<p style="font:14px sans-serif;color:#b32d2e;padding:16px">The preview could not be loaded. Save the steps and reload the page.</p>';
        }).finally(() => { if (mine === request) stage.classList.remove('is-loading'); });
    }
    $('refresh').addEventListener('click', refresh);

    /* ---------- Saving ---------- */
    const form = document.getElementById('post');
    if (form) form.addEventListener('submit', event => {
        sync();
        if ($('data').value.length > boot.maxData) {
            event.preventDefault();
            window.alert('The steps are too large to be saved. Remove some steps or shorten their texts.');
            return;
        }
        submitting = true;
    });
    window.addEventListener('beforeunload', event => {
        if (dirty && !submitting) { event.preventDefault(); event.returnValue = ''; }
    });

    sync();
    drawAll();
    setDevice(device);
    refresh();
}());
