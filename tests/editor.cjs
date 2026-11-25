// The editor's step Text (2026-11-23, user's request: WordPress's editor as WP Swiper's caption, Font size per device, Font weight,
// Color under it): admin.js on tests/editor-page.php (no WordPress, nothing saved; wp.editor stands in), in the installed Chrome.
// What WordPress's own editor draws is checked by hand.
const fs = require('fs');
const os = require('os');
const path = require('path');
const assert = require('assert/strict');
const {execFileSync} = require('child_process');
const {chromium} = require('playwright-core');

(async () => {
    const browser = await chromium.launch({executablePath: process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe'});
    try {
        const page = await browser.newPage({viewport: {width: 1600, height: 1000}});
        const errors = [];
        page.on('pageerror', e => errors.push(e.message));
        const file = path.join(os.tmpdir(), 'wpst-editor-page.html');
        fs.writeFileSync(file, execFileSync(process.env.PHP_BIN || 'C:/OSPanel/modules/PHP-8.3/php.exe', [path.join(__dirname, 'editor-page.php')]));
        await page.goto('file:///' + file.replace(/\\/g, '/'));
        // The Settings (a step's window closed) and a step's window (2026-11-24).
        const toSettings = async () => { if (await page.locator('#wpst-modal').isVisible()) await page.click('#wpst-modal [data-modal-apply]'); };
        const openStep = async i => { await toSettings(); await page.click('[data-select="' + i + '"]'); };
        await openStep(0);
        const inits = () => page.evaluate(() => wpCalls.init.map(([id, cfg]) => [id, cfg.tinymce && cfg.tinymce.toolbar1, cfg.quicktags && cfg.quicktags.buttons]));
        const step = () => page.evaluate(() => JSON.parse(document.getElementById('wpst-data').value).steps[0]);
        // Side text only where the design shows it (2026-11-24): the test set is a List.
        assert.equal(await page.locator('#wpst-f-aside_title').count(), 0, 'List: no Side text');
        // Two tabs (2026-11-23): Title first, its editor without Paragraph / Heading and lists, its Tag under its fields.
        // The block in a frame with its name (2026-11-23, as WP Swiper's settings).
        // A step's window (2026-11-24, as WP Swiper's): its tile opens it, Step 1 in its head; the Settings stay behind it.
        assert.ok(await page.locator('#wpst-modal').isVisible() && (await page.locator('#wpst-modal-title').textContent()).startsWith('Step 1') && (await page.locator('fieldset.wpst-settings-box > legend').textContent()) === 'Layout', 'a step: its window; the Settings behind it');
        assert.deepEqual(await page.locator('[data-step-tab]').evaluateAll(b => b.map(x => x.textContent + ':' + x.getAttribute('aria-selected'))), ['Title:true', 'Text:false', 'Icon:false', 'Style:false', 'Link:false'], 'tabs Title, Text, Icon, Style, Link (no Side text in a List), Title shown');
        assert.deepEqual(await inits(), [['wpst-f-title', 'bold,italic,underline,forecolor,alignleft,aligncenter,alignright,link,unlink,removeformat,undo,redo', 'strong,em,link']], 'the Title\'s editor');
        assert.ok(await page.locator('#wpst-f-title_size').count() === 1 && await page.locator('#wpst-f-title_weight').count() === 1 && await page.locator('[data-field="title_color"] .wpst-color__swatch').count() === 1 && await page.locator('[data-color-reset="title_color"]').count() === 0 && await page.locator('#wpst-f-text').count() === 0, 'its Font size, Font weight, Color; no Text there');
        assert.equal(await page.locator('#wpst-f-title_size').getAttribute('placeholder'), '22', 'its size\'s placeholder: the usual title size');
        // Tag: no Default (2026-11-23); without a tag of its own it shows the one in effect (the set's H3).
        assert.ok(await page.locator('#wpst-f-title_tag option[value=""]').count() === 0 && await page.inputValue('#wpst-f-title_tag') === 'h3', 'Tag: no Default, the tag in effect shown');
        await page.selectOption('#wpst-f-title_tag', 'h2');await page.selectOption('#wpst-f-title_weight', '800');
        // The editor's title: its words and alignment kept apart, the tile shows its words.
        await page.evaluate(() => { const cfg = wpCalls.init[0][1].tinymce, h = []; cfg.setup({on: (e, fn) => h.push(fn), isDirty: () => true, getContent: () => '<p style="text-align: center;">Our <strong>plan</strong></p>'}); h.forEach(fn => fn()); });
        const t = await step();
        assert.ok(t.title === 'Our <strong>plan</strong>' && t.title_align === 'center' && t.title_tag === 'h2' && t.title_weight === '800', 'stored: words, alignment, tag, weight (' + JSON.stringify([t.title, t.title_align]) + ')');
        assert.equal((await page.locator('[data-select="0"] .wpst-tile__label').textContent()).trim(), 'Our plan', 'its tile: the words');
        // Its words are read without running anything in it (2026-11-25: DOMParser; an element's innerHTML loads <img onerror>).
        const setTitle = html => page.evaluate(content => { const cfg = wpCalls.init[0][1].tinymce, h = []; cfg.setup({on: (e, fn) => h.push(fn), isDirty: () => true, getContent: () => content}); h.forEach(fn => fn()); }, html);
        await setTitle('<p><img src="x" onerror="window.wpstRan = 1">Safe</p>');
        await page.waitForTimeout(300);
        assert.ok((await page.locator('[data-select="0"] .wpst-tile__label').textContent()).trim() === 'Safe' && await page.evaluate(() => window.wpstRan) === undefined, 'a title\'s words read without running its HTML');
        await setTitle('<p style="text-align: center;">Our <strong>plan</strong></p>');
        await page.click('[data-step-tab="text"]');
        assert.deepEqual((await inits()).slice(1), [['wpst-f-text', 'formatselect,bold,italic,underline,forecolor,alignleft,aligncenter,alignright,bullist,numlist,link,unlink,removeformat,undo,redo', 'strong,em,link,ul,ol,li']], 'Text tab: the Title\'s editor removed, the Text\'s started');
        assert.equal((await page.evaluate(() => wpCalls.remove)).join(), 'wpst-f-title', 'the Title\'s editor removed');
        assert.ok((await page.locator('#wpst-f-text').inputValue()).length > 0, 'its text in the box');
        const size = page.locator('#wpst-f-text_size');
        assert.equal(await size.getAttribute('placeholder'), '15', 'Font size: Sizes\' text size as its placeholder');
        await size.fill('18');
        assert.equal((await step()).text_size, 18, 'typed: its own size');
        assert.equal(await page.locator('[data-field="text_size"] .wpst-devices__btn[data-device="desktop"].has-value').count(), 1, 'the desktop icon marked');
        await page.click('[data-field="text_size"] [data-device="tablet"]');
        assert.equal(await page.locator('#wpst-f-text_size_tablet').getAttribute('placeholder'), '18', 'Tablet: the step\'s desktop size as its placeholder');
        assert.equal((await page.evaluate(() => wpCalls.remove)).length, 2, 'redrawn: the editor removed first, then started again');
        await page.click('[data-field="text_size_tablet"] [data-device="desktop"]');
        await page.fill('#wpst-f-text_size', '');
        assert.equal((await step()).text_size, null, 'emptied: none (as the other steps)');
        await page.selectOption('#wpst-f-text_weight', '600');
        assert.equal((await step()).text_weight, '600', 'Font weight');
        // Its Color: a swatch alone (2026-11-23: no reset, no "Automatic": no usual text color in the Settings).
        assert.ok(await page.locator('[data-color-reset="text_color"]').count() === 0 && await page.locator('[data-field="text_color"] .wpst-color__auto').count() === 0, 'Color: no reset, no "Automatic"');
        await page.evaluate(() => { const i = document.querySelector('[data-key="text_color"]'); i.value = '#112233'; i.dispatchEvent(new Event('input', {bubbles:true})); });
        assert.equal((await step()).text_color, '#112233', 'a color set');
        // The step's own Color (its Color block) keeps the reset icon: back to its place between the first and the last color.
        await page.click('[data-step-tab="color"]');
        const reset = page.locator('[data-color-reset="color"]');
        assert.ok(await reset.isDisabled() && await reset.locator('svg').count() === 1, 'Step color: a greyed reset icon');
        await page.evaluate(() => { const i = document.querySelector('[data-key="color"]'); i.value = '#112233'; i.dispatchEvent(new Event('input', {bubbles:true})); });
        assert.ok((await step()).color === '#112233' && !(await reset.isDisabled()), 'a color set: the reset icon active');
        await reset.click();
        assert.ok((await step()).color === '' && await reset.isDisabled(), 'reset: automatic again');
        await page.click('[data-step-tab="title"]');
        await toSettings();await page.click('[data-block="layout"]');
        assert.ok(await page.locator('[data-field="text_color"]').count() === 0 && await page.locator('[data-field="title_color"]').count() === 0 && await page.locator('[data-field="label_color"]').count() === 0, 'Settings → Layout: no Text, Titles or Step number color');
        // The Settings' own automatic colors keep the word (the step number's, now under Numbers).
        await page.click('[data-block="numbering"]');
        assert.equal(await page.locator('.wpst-color__auto').count(), 0, 'Numbers: no "Automatic" beside its color (2026-11-24)');
        await page.click('[data-block="layout"]');
        assert.ok(await page.locator('[data-field="title_size"]').count() === 0 && await page.locator('[data-field="text_size"]').count() === 0, 'Settings → Cards: no Title or Text size');
        await openStep(0);await page.click('[data-step-tab="text"]');
        // The visual editor's text reaches the step only once it is edited (isDirty), not as it starts.
        const stored = await page.evaluate(() => {
            const cfg = wpCalls.init[wpCalls.init.length - 1][1].tinymce, handlers = [];
            let dirty = false;
            cfg.setup({on: (ev, fn) => handlers.push(fn), isDirty: () => dirty, getContent: () => '<p>Edited</p>'});
            handlers.forEach(fn => fn());
            const before = JSON.parse(document.getElementById('wpst-data').value).steps[0].text;
            dirty = true;
            handlers.forEach(fn => fn());
            return [before, JSON.parse(document.getElementById('wpst-data').value).steps[0].text];
        });
        assert.ok(stored[0] !== '<p>Edited</p>' && stored[1] === '<p>Edited</p>', 'the visual editor\'s text stored once edited (' + JSON.stringify(stored) + ')');
        // The icon as a select (2026-11-23): closed, the icon chosen and its name; open, a search and all the icons; keys.
        await page.click('[data-step-tab="icon"]');
        const sel = page.locator('#wpst-icon-select'), panel = page.locator('.wpst-iconselect__panel');
        assert.ok(await panel.isHidden() && await sel.getAttribute('aria-expanded') === 'false', 'closed at first');
        await sel.click();
        assert.ok(await panel.isVisible() && await page.locator('.wpst-iconselect__search').evaluate(el => el === document.activeElement), 'open: the search focused');
        assert.equal(await page.locator('.wpst-iconselect .wpst-icon').count(), 101, 'None and the 100 icons');
        await page.keyboard.type('mon');
        assert.deepEqual(await page.locator('.wpst-iconselect .wpst-icon:not([hidden])').evaluateAll(b => b.map(x => x.dataset.icon)), ['money', 'monitor', 'diamond'], 'search by name (and by its key: diamond is Quality)');
        await page.keyboard.press('Enter');
        assert.ok((await step()).icon === 'money' && (await page.locator('.wpst-iconselect__name').textContent()) === 'Money' && await panel.isHidden(), 'Enter: the first found chosen, the select shows it, closed');
        await sel.click();
        await page.keyboard.press('ArrowDown');await page.keyboard.press('ArrowRight');
        assert.equal(await page.evaluate(() => document.activeElement.dataset.icon), await page.evaluate(() => Object.keys(JSON.parse(document.getElementById('wpst-boot').textContent).icons)[0]), 'arrows: down into the grid, right to the next icon');
        await page.keyboard.press('Escape');
        assert.ok(await panel.isHidden() && await sel.evaluate(el => el === document.activeElement), 'Esc: closed, back on the select');
        await sel.click();await page.click('#wpst-modal-title');
        assert.ok(await panel.isHidden(), 'a click outside closes it');
        // Beside the select (2026-11-23): Filled / Outline / None (no Style select), the one in effect pressed (the set's White circle:
        // Filled); under it Size, Icon size, then Gap, Icon color and Background, no reset.
        assert.ok(await page.locator('#wpst-f-icon_style').count() === 0 && await page.locator('#wpst-f-icon_size').count() === 1 && await page.locator('[data-field="icon_color"] .wpst-color__swatch').count() === 1 && await page.locator('[data-field="icon_bg"] .wpst-color__swatch').count() === 1 && await page.locator('[data-color-reset]').count() === 0, 'Icon: no Style; Size, Icon color, Background (no reset)');
        assert.deepEqual(await page.locator('.wpst-icon-head [data-choice="icon_shape"]').evaluateAll(b => b.map(x => x.textContent + ':' + x.getAttribute('aria-pressed'))), ['Filled:true', 'Outline:false', 'None:false'], 'Filled / Outline / None beside the select, Filled in effect');
        // Gap and Thickness only in Wave (2026-11-24: not drawn elsewhere); this set is a List.
        assert.ok(await page.locator('#wpst-f-ring_width').count() === 0 && await page.locator('#wpst-f-ring_gap').count() === 0 && await page.locator('#wpst-f-icon_scale').getAttribute('placeholder') === '44', 'List: no Gap or Thickness; Content size 44%');
        await page.fill('#wpst-f-icon_scale', '60');
        assert.equal((await step()).icon_scale, 60, 'Icon size stored');
        await page.click('[data-field="icon_scale"] [data-device="tablet"]');
        assert.ok(await page.locator('#wpst-f-icon_scale_tablet').getAttribute('placeholder') === '60' && await page.locator('#wpst-f-icon_size_tablet').count() === 1, 'Tablet: Size and Content size of its own, Content size following Desktop');
        await page.click('[data-field="icon_scale_tablet"] [data-device="desktop"]');
        await page.fill('#wpst-f-icon_scale', '');
        assert.equal((await step()).icon_scale, null, 'emptied: the usual');
        await page.fill('#wpst-f-icon_size', '70');
        // Radius above Shadow (2026-11-23, WP Banner's box): linked, one corner sets all four; a unit; the link.
        assert.ok(await page.locator('[data-iradius-corner]').count() === 4 && await page.locator('#wpst-f-icon_use_radius').isChecked(), 'Radius: on by default, its corners (a circle)');
        await page.click('label:has(#wpst-f-icon_use_radius)');
        assert.ok((await step()).icon_use_radius === false && await page.locator('[data-iradius-corner]').count() === 0, 'Radius off: a square, no corners');
        assert.ok(await page.evaluate(() => !!(document.querySelector('[data-field=icon_use_radius]').compareDocumentPosition(document.getElementById('wpst-f-icon_shadow')) & Node.DOCUMENT_POSITION_FOLLOWING)) , 'Radius above Shadow');
        await page.click('label:has(#wpst-f-icon_use_radius)');
        assert.ok((await step()).icon_use_radius === true && await page.locator('[data-iradius-corner]').count() === 4, 'Radius switched on: its corners');
        await page.fill('[data-iradius-corner="tl"]', '0');
        assert.ok((await step()).icon_radius_br === 0 && (await step()).icon_radius_tr === 0, 'linked: one corner sets all four');
        await page.click('[data-iradius-link]');await page.fill('[data-iradius-corner="br"]', '20');await page.click('[data-iradius-unit="br"][data-unit="px"]');
        assert.ok((await step()).icon_radius_linked === false && (await step()).icon_radius_br === 20 && (await step()).icon_radius_br_unit === 'px' && (await step()).icon_radius_tl === 0, 'unlinked: one corner in px on its own');
        // Shadow under the colors (2026-11-23): the set's in effect until switched; no tips on the two colors.
        assert.ok(await page.locator('.wpst-icon-shadow #wpst-f-icon_shadow').count() === 1 && await page.locator('[data-field="icon_color"] .wpst-tip').count() === 0 && await page.locator('[data-field="icon_bg"] .wpst-tip').count() === 0, 'Icon: Shadow under the colors, no tips on them');
        assert.ok(await page.locator('.wpst-icon-shadow .wpst-row4 input[type=number]').count() === 4 && await page.locator('#wpst-f-icon_shadow_y').getAttribute('placeholder') === '8' && await page.locator('[data-field="icon_shadow_color"]').count() === 1, 'Shadow on: Horizontal, Vertical, Blur, Spread and Color under it');
        await page.click('label:has(#wpst-f-icon_shadow)');
        assert.equal(typeof (await step()).icon_shadow, 'boolean', 'Shadow switched: the step own value');
        await page.click('[data-choice="icon_shape"][data-value="none"]');
        assert.ok((await step()).icon_size === 70 && (await step()).icon_shape === 'none' && await page.locator('[data-field="icon_bg"]').count() === 0, 'None: stored, Background greyed');
        // None in a design without half rings (this List): Radius greyed; in Wave it stays, for the half ring (2026-11-24).
        assert.equal(await page.locator('[data-field="icon_use_radius"]').count(), 0, 'None in a List: no Radius');
        await toSettings();await page.click('#wpst-design-button');await page.click('#wpst-design-select [data-design="wave"]');
        await openStep(0);await page.click('[data-step-tab="icon"]');
        assert.ok(await page.locator('[data-field="icon_use_radius"]').count() === 1 && await page.locator('[data-iradius-corner]').count() === 4, 'None in Wave: Radius and its corners (the half ring)');
        assert.ok(await page.locator('#wpst-f-ring_gap').count() === 0 && await page.locator('#wpst-f-ring_width').count() === 0, 'Wave: no Gap or Thickness in the step (Settings)');
        await toSettings();await page.click('[data-block="lines"]');
        assert.equal(await page.locator('#wpst-f-arc').count(), 0, 'Wave: no Half rings in Line and markers');
        await page.click('[data-block="layout"]');
        assert.ok(await page.locator('#wpst-f-arc').count() === 1 && await page.locator('#wpst-f-ring_gap').count() === 1, 'Wave: Layout → Half rings, Thickness and Gap');
        await page.fill('#wpst-f-ring_gap', '12');
        assert.equal(await page.evaluate(() => JSON.parse(document.getElementById('wpst-data').value).ring_gap), 12, 'Gap: the set\'s');
        await toSettings();await page.click('#wpst-design-button');await page.click('#wpst-design-select [data-design="list"]');
        await openStep(0);await page.click('[data-step-tab="icon"]');
        await page.click('[data-choice="icon_shape"][data-value="outline"]');
        assert.ok((await step()).icon_shape === 'outline' && await page.locator('[data-field="icon_bg"]').count() === 1, 'Outline: Background (its ring) again');
        assert.equal(await page.locator('[data-field="icon_shadow"]').count(), 0, 'Outline: no shadow');
        await toSettings();await page.click('[data-block="layout"]');
        assert.ok(await page.locator('[data-field="icon_style"]').count() === 0 && (await page.locator('#wpst-block-title').textContent()) === 'Layout', 'Settings → Layout: no icon style');
        await page.click('[data-block="layout"]');
        assert.equal(await page.locator('[data-field="icon"]').count(), 0, 'Settings → Cards: no Icon circle');
        await page.click('[data-block="lines"]');
        assert.equal(await page.locator('#wpst-f-arc').count(), 0, 'Settings → Line and markers in a List: no Half rings');
        // Icon | Image first (2026-11-23): Image shows the image picker where the icon select was, Icon color greyed, Content size 52%.
        await openStep(0);await page.click('[data-step-tab="icon"]');
        assert.deepEqual(await page.locator('[data-choice="media"]').evaluateAll(b => b.map(x => x.textContent + ':' + x.getAttribute('aria-pressed'))), ['Icon:true', 'Image:false'], 'Icon | Image, Icon in effect');
        await page.click('[data-choice="media"][data-value="image"]');
        assert.ok((await step()).media === 'image' && await page.locator('.wpst-icon-head .wpst-imagepick #wpst-image-pick').count() === 1 && await page.locator('#wpst-icon-select').count() === 0, 'Image: its picker where the icon select was');
        assert.ok(await page.locator('[data-field="icon_color"]').count() === 0 && await page.locator('#wpst-f-icon_scale').getAttribute('placeholder') === '52' && await page.locator('[data-field="icon_bg"]').count() === 1, 'Image: Icon color greyed, Content size 52%, the rest the same');
        assert.equal(await page.locator('text=Your own image').count(), 0, 'no separate image card');
        await page.click('[data-choice="media"][data-value="icon"]');
        assert.equal(await page.locator('#wpst-icon-select').count(), 1, 'Icon again: the select');
        await toSettings();
        // The design's select over the menu (2026-11-24): the one in use; open, its tiles; Esc closes.
        assert.ok((await page.locator('#wpst-design-button').textContent()).startsWith('Design') && (await page.locator('#wpst-design-button').textContent()).includes('List') && await page.locator('[data-block="design"]').count() === 0, 'Design: a select over the menu (List), no Design block');
        await page.click('#wpst-design-button');
        assert.ok(await page.locator('.wpst-designselect__panel').isVisible() && await page.locator('#wpst-design-select [data-design]').count() === 5, 'open: the five designs');
        await page.keyboard.press('Escape');
        assert.ok(await page.locator('.wpst-designselect__panel').isHidden(), 'Esc: closed');
        // The Settings show only what the design uses (2026-11-24): List, then Wave and Zigzag.
        await toSettings();await page.click('[data-block="layout"]');
        const seen = async () => page.evaluate(() => ({cards:!!document.querySelector('[data-block="layout"]'), alt:!!document.getElementById('wpst-f-alternate')}));
        let v = await seen();
        assert.ok(v.cards && !v.alt, 'List: Cards in the menu, no Cards from side to side');
        await page.click('[data-block="lines"]');
        assert.ok(await page.locator('#wpst-f-arrow').count() === 1 && await page.locator('#wpst-f-marker').count() === 0, 'List: Arrows, no Marker');
        assert.equal(await page.locator('#wpst-block-settings, #wpst-settings').locator('[data-field="line_color"]').count(), 1, 'Line and markers: the line color with the line (2026-11-24)');
        await page.click('[data-block="numbering"]');
        assert.equal(await page.locator('#wpst-settings [data-field="label_color"]').count(), 1, 'Numbers: the step number color with the number (2026-11-24)');
        await page.click('[data-block="style"]');
        assert.ok(await page.locator('[data-block="colors"]').count() === 0 && await page.locator('#wpst-f-color_from').count() === 1 && await page.locator('[data-field="label_color"]').count() === 0 && await page.locator('[data-field="line_color"]').count() === 0, 'Step colors in Style → Cards (no Colors block); no line or step number color there');
        await page.click('[data-block="layout"]');
        assert.ok(await page.locator('[data-field="number_size"]').count() === 0 && await page.locator('[data-block="sizes"]').count() === 0 && await page.locator('#wpst-f-gap').count() === 1, 'List: no Sizes block; Layout with Space between steps, no Number size');
        await page.click('[data-block="lines"]');
        assert.equal(await page.locator('#wpst-f-gap').count(), 0, 'Line and markers: no Space between steps (in Layout)');
        await page.click('[data-block="layout"]');
        await page.click('[data-block="layout"]');await page.click('#wpst-design-button');await page.click('#wpst-design-select [data-design="wave"]');
        assert.ok(await page.locator('[data-block="layout"]').count() === 1 && (await page.locator('#wpst-block-title').textContent()) === 'Layout' && await page.locator('#wpst-f-gap').count() === 1 && await page.locator('#wpst-f-card_style').count() === 0 && await page.locator('#wpst-f-icon_position').count() === 0, 'Wave: no Cards in the menu; Line and markers with Space between steps');
        await page.click('#wpst-design-button');await page.click('#wpst-design-select [data-design="zigzag"]');await page.click('[data-block="style"]');
        assert.ok(await page.locator('[data-field="number_size"]').count() === 0 && await page.locator('.wpst-box').count() === 1, 'Zigzag: Cards, no Number size there');
        await page.click('[data-block="numbering"]');
        assert.ok(await page.locator('[data-field="number_size"]').count() === 1, 'Zigzag: Number size in Numbers');
        await page.click('[data-block="lines"]');
        assert.ok(await page.locator('#wpst-f-marker').count() === 1 && await page.locator('#wpst-f-arrow').count() === 0, 'Zigzag: Marker, no Arrows');
        assert.equal(await page.locator('#wpst-f-line_radius').count(), 1, 'Zigzag: Line → Corner radius');
        await page.click('#wpst-design-button');await page.click('#wpst-design-select [data-design="list"]');
        // The step's window (2026-11-24): Esc and the backdrop close it; Timeline adds Side text; a new step opens in it.
        await openStep(1);
        await page.keyboard.press('Escape');
        assert.ok(await page.locator('#wpst-modal').isHidden() && !(await page.evaluate(() => document.body.classList.contains('wpst-modal-open'))), 'Esc: closed');
        await openStep(1);await page.mouse.click(5, 5);
        assert.ok(await page.locator('#wpst-modal').isVisible(), 'the backdrop: nothing (as WP Swiper)');
        // Cancel puts the step back as it was; Apply keeps it.
        await page.click('[data-step-tab="icon"]');
        const sizeBefore = await page.evaluate(() => JSON.parse(document.getElementById('wpst-data').value).steps[1].icon_size);
        await page.fill('#wpst-f-icon_size', '111');
        // Closed with a change (2026-11-25): asked first; No keeps the window and the change, Yes puts the step back.
        let asked = '';
        page.once('dialog', d => { asked = d.type(); d.dismiss(); });
        await page.click('#wpst-modal [data-modal-close]');
        assert.ok(asked === 'confirm' && await page.locator('#wpst-modal').isVisible() && await page.inputValue('#wpst-f-icon_size') === '111', 'Cancel with a change: asked; No keeps it open');
        page.once('dialog', d => d.dismiss());
        await page.keyboard.press('Escape');
        assert.ok(await page.locator('#wpst-modal').isVisible(), 'Esc with a change: asked too');
        page.once('dialog', d => d.accept());
        await page.click('#wpst-modal [data-modal-close]');
        assert.ok(await page.locator('#wpst-modal').isHidden() && (await page.evaluate(() => JSON.parse(document.getElementById('wpst-data').value).steps[1].icon_size)) === sizeBefore, 'Cancel: the step as it was');
        await openStep(1);await page.click('[data-step-tab="icon"]');await page.fill('#wpst-f-icon_size', '111');await page.click('[data-modal-apply]');
        assert.equal(await page.evaluate(() => JSON.parse(document.getElementById('wpst-data').value).steps[1].icon_size), 111, 'Apply: kept');
        // A fixed height, as WP Swiper's: the same for a short tab and a long one.
        await openStep(1);await page.click('[data-step-tab="color"]');
        const h1 = await page.locator('.wpst-modal__box').evaluate(el => el.offsetHeight);
        await page.click('[data-step-tab="icon"]');
        assert.equal(await page.locator('.wpst-modal__box').evaluate(el => el.offsetHeight), h1, 'the same height from tab to tab');
        await toSettings();
        await page.click('#wpst-design-button');await page.click('#wpst-design-select [data-design="timeline"]');
        await openStep(0);
        assert.ok(await page.locator('[data-step-tab="aside"]').count() === 1, 'Timeline: a Side text tab');
        await page.click('[data-step-tab="aside"]');
        assert.equal(await page.locator('#wpst-f-aside_title').count(), 1, 'its fields');
        // Its two fields in WordPress's editors (2026-11-24): both started at once, the title's without paragraphs or lists; they store.
        const asideInits = await page.evaluate(() => wpCalls.init.filter(([id]) => /aside/.test(id)).map(([id, cfg]) => id + ':' + cfg.tinymce.toolbar1));
        assert.ok(asideInits.length === 2 && asideInits[0].startsWith('wpst-f-aside_title:bold') && !asideInits[0].includes('bullist') && asideInits[1].startsWith('wpst-f-aside_text:formatselect'), 'Side text: two editors');
        await page.evaluate(() => { wpCalls.init.filter(([id]) => /aside/.test(id)).forEach(([id, cfg], i) => { const h = []; cfg.tinymce.setup({on: (e, fn) => h.push(fn), isDirty: () => true, getContent: () => i ? '<p>Side <em>text</em></p>' : '<p>Side <strong>title</strong></p>'}); h.forEach(fn => fn()); }); });
        const as = await step();
        assert.ok(as.aside_title === 'Side <strong>title</strong>' && as.aside_text === '<p>Side <em>text</em></p>' && await page.locator('[data-step-tab="aside"].has-value').count() === 1, 'Side text: stored, its tab marked');
        // Its Tag, and size / weight / color per device (2026-11-24).
        await page.selectOption('#wpst-f-aside_title_tag', 'h4');await page.selectOption('#wpst-f-aside_text_weight', '600');
        await page.click('[data-field="aside_title_size"] [data-device="tablet"]');
        assert.equal(await page.locator('#wpst-f-aside_text_weight_tablet option').first().textContent(), 'As Desktop (Semibold 600)', 'Tablet: weight follows Desktop');
        await page.selectOption('#wpst-f-aside_title_weight_tablet', '300');await page.fill('#wpst-f-aside_title_size_tablet', '18');
        const at = await step();
        assert.ok(at.aside_title_tag === 'h4' && at.aside_text_weight === '600' && at.aside_title_weight_tablet === '300' && at.aside_title_size_tablet === 18, 'Side text: Tag, weight and size per device stored');
        await page.click('[data-field="aside_title_size_tablet"] [data-device="desktop"]');
        await toSettings();await page.click('#wpst-design-button');await page.click('#wpst-design-select [data-design="list"]');
        const count = await page.evaluate(() => JSON.parse(document.getElementById('wpst-data').value).steps.length);
        await page.click('#wpst-add');
        assert.ok(await page.locator('#wpst-modal').isVisible() && (await page.locator('#wpst-modal-title').textContent()).startsWith('Step ' + (count + 1)) && await page.locator('[data-step-tab="title"][aria-selected=true]').count() === 1, 'Add step: its window, on Title');
        await toSettings();
        // The Link tab (2026-11-24): the address and Open in a new tab; a dot while it has one.
        await openStep(0);await page.click('[data-step-tab="link"]');
        await page.fill('#wpst-f-link', 'https://example.test/');await page.click('label:has(#wpst-f-link_new_tab)');
        assert.ok((await step()).link === 'https://example.test/' && (await step()).link_new_tab === true, 'Link: stored with Open in a new tab');
        await page.click('[data-step-tab="color"]');
        assert.equal(await page.locator('[data-step-tab="link"].has-value').count(), 1, 'its tab marked');
        await toSettings();
        // Custom (2026-11-24): Padding/Margin behind its switch, linked sides, Backspace 0, units; Element ID and classes.
        await toSettings();await page.click('[data-block="custom"]');
        assert.ok((await page.locator('#wpst-block-title').textContent()) === 'Custom' && await page.locator('.wpst-spacing').count() === 0, 'Custom: Padding/Margin off, no sides');
        await page.click('label:has(#wpst-f-use_padding)');
        assert.equal(await page.locator('.wpst-spacing [data-pad-side]').count(), 4, 'on: the sides');
        const set = () => page.evaluate(() => JSON.parse(document.getElementById('wpst-data').value));
        await page.fill('[data-pad-side="top"]', '20');
        let cs = await set();
        assert.ok(cs.use_padding === true && cs.padding_top === 20 && cs.padding_left === 20 && cs.margin_top === 0, 'Padding: linked, all four sides');
        await page.click('[data-margin-link]');await page.fill('[data-margin-side="bottom"]', '40');
        cs = await set();
        assert.ok(cs.margin_bottom === 40 && cs.margin_top === 0 && cs.margin_linked === false, 'Margin: unlinked, one side');
        await page.click('[data-margin-unit="bottom"][data-unit="%"]');
        cs = await set();
        assert.ok(cs.margin_bottom_unit === '%' && cs.margin_bottom === 40, 'a unit per side, the value within its largest');
        await page.fill('[data-pad-side="top"]', '');
        assert.equal((await set()).padding_right, 0, 'Backspace on Desktop: 0');
        await page.fill('#wpst-f-css_id', 'my-steps');await page.fill('#wpst-f-css_class', 'one two');
        cs = await set();
        assert.ok(cs.css_id === 'my-steps' && cs.css_class === 'one two', 'Element ID and Extra class name');
        // A step's Style tab (2026-11-25): its card's box (Margin, Border, Radius, Padding; empty = as the cards), then its color.
        await openStep(0);await page.click('[data-step-tab="color"]');
        assert.ok((await page.locator('[data-step-tab="color"]').textContent()).trim() === 'Style' && await page.locator('.wpst-box input[data-box="step"]').count() === 16 && await page.locator('[data-field="color"]').count() === 1, 'Style: the box (4 × margin, border, radius, padding), then Step color');
        await page.fill('[data-box-kind="margin"][data-box-part="bottom"]', '24');
        let st = await step();
        assert.ok(st.margin_bottom === 24 && st.margin_top === 24 && st.padding_top === null, 'Margin: linked, a step\'s own');
        await page.fill('[data-box-kind="margin"][data-box-part="bottom"]', '');
        assert.equal((await step()).margin_top, null, 'Backspace: as the cards again');
        await page.fill('input[data-box-kind="border"][data-box-part="left"]', '4');await page.selectOption('#wpst-f-border_style', 'dashed');
        st = await step();
        assert.ok(st.border_left === 4 && st.border_top === 4 && st.border_style === 'dashed' && (await page.locator('.wpst-box__ring--border').evaluate(el => el.style.borderStyle)) === 'dashed', 'Border: a step\'s own, its line; the ring drawn');
        await page.fill('[data-box-kind="padding"][data-box-part="left"]', '30');
        // Its card's shadow (2026-11-25): the cards' switch shown; its own parts, the cards' as placeholders.
        const cardsShadow = await page.evaluate(() => JSON.parse(document.getElementById('wpst-data').value).shadow);
        assert.equal(await page.locator('#wpst-f-shadow').isChecked(), !!cardsShadow, 'Shadow: the cards\' switch until set');
        if (!cardsShadow) await page.click('label:has(#wpst-f-shadow)');
        assert.ok((await page.locator('#wpst-f-shadow_y').getAttribute('placeholder')) !== null, 'Shadow: its parts, the cards\' as placeholders');
        await page.fill('#wpst-f-shadow_y', '6');
        assert.ok((await step()).shadow === true && (await step()).shadow_y === 6, 'Shadow: a step\'s own');
        await toSettings();
        // Layout (2026-11-24): Icon position, Auto by default.
        await page.click('#wpst-design-button');await page.click('#wpst-design-select [data-design="list"]');await page.click('[data-block="layout"]');
        assert.ok((await page.locator('#wpst-block-title').textContent()) === 'Layout' && (await page.locator('#wpst-f-icon_position').inputValue()) === '', 'Layout: Icon position, Auto');
        await page.selectOption('#wpst-f-icon_position', 'top');
        assert.equal(await page.evaluate(() => JSON.parse(document.getElementById('wpst-data').value).icon_position), 'top', 'Icon position: stored');
        // Alignment beside it (2026-11-24): Left / Center / Right above the texts, Left shown; Top / Middle / Bottom beside; reset on a new position.
        const opts = () => page.locator('#wpst-f-icon_align option').evaluateAll(o => o.map(x => x.textContent));
        assert.ok(JSON.stringify(await opts()) === JSON.stringify(['Left', 'Center', 'Right']) && (await page.locator('#wpst-f-icon_align').inputValue()) === 'start', 'Top: Left / Center / Right, Left');
        await page.selectOption('#wpst-f-icon_align', 'end');
        assert.equal(await page.evaluate(() => JSON.parse(document.getElementById('wpst-data').value).icon_align), 'end', 'Alignment: stored');
        await page.selectOption('#wpst-f-icon_position', 'left');
        assert.ok(JSON.stringify(await opts()) === JSON.stringify(['Top', 'Middle', 'Bottom']) && (await page.locator('#wpst-f-icon_align').inputValue()) === 'center' && (await page.evaluate(() => JSON.parse(document.getElementById('wpst-data').value).icon_align)) === '', 'Left: Top / Middle / Bottom, back to Middle');
        // Per device (2026-11-24): on Tablet the first choice follows Desktop; its own place and alignment; the gap.
        await page.click('[data-field="icon_position"] [data-device="tablet"]');
        assert.equal(await page.locator('#wpst-f-icon_position option').first().textContent(), 'As Desktop (Left)', 'Tablet: As Desktop (Left)');
        await page.selectOption('#wpst-f-icon_position', 'top');
        let dd = await page.evaluate(() => JSON.parse(document.getElementById('wpst-data').value));
        assert.ok(dd.icon_position_tablet === 'top' && dd.icon_align_tablet === 'start' && dd.icon_position === 'left', 'Tablet: its own place, its alignment the usual one for it');
        await page.fill('#wpst-f-icon_gap_tablet', '18');
        assert.equal(await page.evaluate(() => JSON.parse(document.getElementById('wpst-data').value).icon_gap_tablet), 18, 'Gap on Tablet');
        await page.selectOption('#wpst-f-icon_position', '');
        assert.equal(await page.evaluate(() => JSON.parse(document.getElementById('wpst-data').value).icon_position_tablet), null, 'Tablet: back to As Desktop');
        await page.click('[data-field="icon_position_tablet"] [data-device="desktop"]');
        await page.selectOption('#wpst-f-icon_position', '');
        assert.equal(await page.locator('#wpst-f-icon_align').count(), 0, 'Auto: no Alignment');
        // Style → Cards → the box (2026-11-25): no switches, no Filled / Outline; border and radius 0, linked; padding empty; the links.
        await page.click('#wpst-design-button');await page.click('#wpst-design-select [data-design="list"]');await page.click('[data-block="style"]');
        assert.ok(await page.locator('#wpst-f-card_use_border').count() === 0 && await page.locator('#wpst-f-card_use_radius').count() === 0 && await page.locator('#wpst-f-card_style').count() === 0 && await page.locator('.wpst-box input[data-box="card"]').count() === 16 && (await page.locator('#wpst-f-card_border_top').inputValue()) === '' && (await page.locator('#wpst-f-card_border_top').getAttribute('placeholder')) === '' && (await page.locator('#wpst-f-card_padding_top').inputValue()) === '', 'Cards: one box, no switches');
        assert.ok(await page.locator('.wpst-box__line').isHidden(), 'Border line / color hidden without a border');
        await page.fill('#wpst-f-card_radius_tl', '30');await page.fill('#wpst-f-card_border_top', '5');await page.selectOption('#wpst-f-card_border_style', 'dashed');
        assert.ok(await page.locator('.wpst-box__line').isVisible(), 'shown once a border is set');
        let cb = await page.evaluate(() => JSON.parse(document.getElementById('wpst-data').value));
        assert.ok(cb.card_radius_tl === 30 && cb.card_radius_br === 30 && cb.card_border_top === 5 && cb.card_border_left === 5 && cb.card_border_style === 'dashed' && (await page.locator('.wpst-box__ring--border').evaluate(el => el.style.borderStyle)) === 'dashed', 'Cards: radius and border linked, Line; the ring drawn');
        await page.click('[data-box-link="card"][data-box-kind="padding"]');await page.fill('#wpst-f-card_padding_left', '40');
        cb = await page.evaluate(() => JSON.parse(document.getElementById('wpst-data').value));
        assert.ok(cb.card_padding_linked === false && cb.card_padding_left === 40 && cb.card_padding_top === null, 'Cards: padding unlinked, one side');
        await page.click('[data-box-unit="card"][data-box-kind="radius"][data-box-part="tl"][data-unit="%"]');
        cb = await page.evaluate(() => JSON.parse(document.getElementById('wpst-data').value));
        assert.ok(cb.card_radius_tl_unit === '%' && cb.card_radius_br_unit === '%' && cb.card_radius_tl === 30, 'Cards: radius unit, linked, within its largest');
        // Style (2026-11-24): everything of the cards (Layout keeps Space between steps and Icon); in Wave the step colors alone.
        await page.click('[data-block="layout"]');
        assert.ok(await page.locator('.wpst-box').count() === 0 && await page.locator('#wpst-f-color_from').count() === 0 && await page.locator('#wpst-f-gap').count() === 1, 'Layout: no card settings');
        await page.click('#wpst-design-button');await page.click('#wpst-design-select [data-design="wave"]');await page.click('[data-block="style"]');
        assert.ok((await page.locator('#wpst-block-title').textContent()) === 'Style' && await page.locator('#wpst-f-color_from').count() === 1 && await page.locator('.wpst-box').count() === 0, 'Wave: Style with the step colors only');
        // A per-device size emptied on Desktop (2026-11-25): its smallest value, the field empty.
        await page.click('[data-block="layout"]');await page.fill('#wpst-f-gap', '');
        assert.ok((await page.evaluate(() => JSON.parse(document.getElementById('wpst-data').value).gap)) === 0 && (await page.locator('#wpst-f-gap').inputValue()) === '', 'Space between steps emptied: 0');
        // Numbers → Alignment (2026-11-25): Left / Center / Right, no Auto; the design's shown (Wave: Center); not in Zigzag.
        await page.click('[data-block="numbering"]');
        assert.ok(JSON.stringify(await page.locator('#wpst-f-label_align option').evaluateAll(o => o.map(x => x.textContent))) === JSON.stringify(['Left', 'Center', 'Right']) && (await page.locator('#wpst-f-label_align').inputValue()) === 'center', 'Wave: Center shown');
        await page.selectOption('#wpst-f-label_align', 'right');
        assert.equal(await page.evaluate(() => JSON.parse(document.getElementById('wpst-data').value).label_align), 'right', 'Alignment stored');
        await page.click('#wpst-design-button');await page.click('#wpst-design-select [data-design="zigzag"]');await page.click('[data-block="numbering"]');
        assert.equal(await page.locator('#wpst-f-label_align').count(), 0, 'Zigzag: no Alignment');
        // An image's box (2026-11-25): Size Default / Aspect ratio / Custom; fit and position for those two; not in Wave.
        await page.click('#wpst-design-button');await page.click('#wpst-design-select [data-design="list"]');
        await openStep(0);await page.click('[data-step-tab="icon"]');await page.click('[data-choice="media"][data-value="image"]');
        assert.ok((await page.locator('#wpst-f-image_size').inputValue()) === '' && await page.locator('#wpst-f-image_fit').count() === 0, 'Image: Size Default, no fit');
        await page.selectOption('#wpst-f-image_size', 'ratio');
        assert.ok(await page.locator('#wpst-f-image_ratio').count() === 1 && (await page.locator('#wpst-f-image_ratio').getAttribute('placeholder')) === '16/9' && await page.locator('#wpst-f-image_fit').count() === 1 && (await page.locator('#wpst-f-image_position option').count()) === 9 && await page.locator('#wpst-f-icon_scale').count() === 0, 'Aspect ratio: its field, fit, the position grid; no Content size');
        await page.fill('#wpst-f-image_ratio', '4/3');await page.selectOption('#wpst-f-image_position', 'right bottom');await page.selectOption('#wpst-f-image_fit', 'contain');
        let im = await step();
        assert.ok(im.image_size === 'ratio' && im.image_ratio === '4/3' && im.image_position === 'right bottom' && im.image_fit === 'contain', 'Image box stored');
        await page.selectOption('#wpst-f-image_size', 'custom');
        assert.equal(await page.locator('#wpst-f-image_height').count(), 1, 'Custom: Height');
        await toSettings();await page.click('#wpst-design-button');await page.click('#wpst-design-select [data-design="wave"]');
        await openStep(0);await page.click('[data-step-tab="icon"]');
        assert.equal(await page.locator('#wpst-f-image_size').count(), 0, 'Wave: no image Size');
        await toSettings();
        // Progress (2026-11-25): Layout → Text alignment and Show icons; Markers with Number size for numbers.
        await page.click('#wpst-design-button');await page.click('#wpst-design-select [data-design="progress"]');await page.click('[data-block="layout"]');
        assert.ok(await page.locator('#wpst-f-progress_align').count() === 1 && await page.locator('#wpst-f-show_icons').count() === 1 && await page.locator('#wpst-f-icon_position').count() === 0, 'Progress: Text alignment, Show icons; no card Icon');
        await page.click('[data-block="lines"]');await page.click('[data-choice="marker"][data-value="number"]');
        assert.equal(await page.locator('#wpst-f-number_size').count(), 1, 'Progress: Number size for numbered markers');
        await page.click('#wpst-design-button');await page.click('#wpst-design-select [data-design="list"]');await page.click('[data-block="layout"]');
        assert.equal(await page.locator('#wpst-f-progress_align').count(), 0, 'List: no Text alignment');
        assert.deepEqual(errors, [], 'no script errors');
        console.log('Editor checks passed: the Title and Text tabs, their editors, Font size per device, Font weight, Color, Tag.');
    } finally {
        await browser.close();
    }
})().catch(e => { console.error(e); process.exitCode = 1; });
