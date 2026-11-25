/*
 * Built file: assets/js/admin.js is the parts in assets/js/admin/ joined in order by tools/build.cjs
 * (npm run build; npm test runs it first). Edit the parts, never admin.js. 01 opens the function, 13 closes it.
 *
 * WP Steps editor (the meta box of WP_Steps_Admin::editor()). One object holds the settings
 * (boot.settings, the same keys as WP_Steps_Settings); every change goes into it and, as JSON,
 * into the hidden #wpst-data that the post form saves (WP_Steps_Admin::save() sanitizes it).
 *
 * Look as WP Swiper and WP Banner: tiles across the top (Settings, the steps, Add step), the
 * menu of the chosen tile's blocks, the chosen block (grey cards), the preview. Settings a
 * design does not use are greyed, their values kept. Per-device sizes: the device icons by the
 * label switch the device for the whole editor and the preview; tablet / phone left empty take
 * the larger screen's value (a green dot: a value of its own).
 *
 * The preview (admin-ajax, WP_Steps_Admin::preview()) is drawn again on Update (lit while it is
 * behind the settings) and on its own when the design changes; a device only resizes it.
 */
(function () {
    'use strict';
    const app = document.getElementById('wpst-app');
    if (!app) return;
    const boot = JSON.parse(document.getElementById('wpst-boot').textContent);
    const $ = id => document.getElementById('wpst-' + id);
    const data = boot.settings;
    const DESIGNS = {};
    boot.designs.forEach(l => { DESIGNS[l.id] = l; });
    const ICONS = boot.icons;
    const images = boot.images && !Array.isArray(boot.images) ? boot.images : {};
    const DEVICES = {desktop:['desktop', 'Desktop'], tablet:['tablet', 'Tablet'], mobile:['smartphone', 'Mobile']};
    const UP = {tablet:'desktop', mobile:'tablet'};
    // The preview's screen widths (as the theme's breakpoints: tablet up to 1024px, phone up to 767px).
    const SCREENS = {desktop:1280, tablet:820, mobile:390};
    const {parseColor, colorValue, openPicker, closePicker} = window.WPST_Color;
    window.WPST_Color.palette(boot.palette);

    const store = {
        get(key) { try { return window.localStorage.getItem(key); } catch (e) { return null; } },
        set(key, value) { try { window.localStorage.setItem(key, value); } catch (e) { /* private window */ } }
    };
    let device = DEVICES[store.get('wpstDevice')] ? store.get('wpstDevice') : 'desktop';
    // -1: Settings; otherwise the step being edited.
    let selected = -1;
    const shown = {settings:'layout', step:'title'};
    let dirty = false, submitting = false;

    const escape = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[c]));
    const current = () => selected < 0 ? data : data.steps[selected];
    const design = () => DESIGNS[data.design] || boot.designs[0];
    // Does the chosen design use this setting? (Those not in boot.optional apply to all.)
    const uses = key => boot.optional.indexOf(key) === -1 || design().supports.indexOf(key) !== -1;

    function sync() { $('data').value = JSON.stringify(data); }
    function changed(redrawTiles) {
        sync();
        dirty = true;
        setStale(true);
        if (redrawTiles) drawTiles();
    }

