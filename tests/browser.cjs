/*
 * Browser checks of the designs (Chrome, through playwright-core): each design on a desktop, a
 * tablet and a phone screen (tests/page.php builds the page, no WordPress needed): no sideways
 * scroll, every step shown, the wide form above the design's fold width and one column below it.
 * Screenshots go to tests/*.png (not in git). node tests/browser.cjs
 */
const {chromium} = require('playwright-core');
const {execFileSync} = require('child_process');
const path = require('path');

const PHP = process.env.PHP_BIN || 'C:/OSPanel/modules/PHP-8.3/php.exe';
const CHROME = process.env.CHROME_BIN || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const WIDTHS = [1280, 820, 390];
// Body padding of tests/page.php on each side.
const PADDING = 24;
const ASIDE = {aside_title:'Side title', aside_text:'Side text of the step.'};
const steps = n => Array.from({length:n}, (_, i) => Object.assign({title:'Step title ' + (i + 1), text:'A short description of the step, long enough to wrap on a phone.', icon:'flag'}, i % 2 ? {} : ASIDE));
const FOLD = {list:0, timeline:600, zigzag:640, wave:720, progress:640};

let failed = 0, passed = 0;
function check(ok, what) {
    if (ok) { passed++; return; }
    failed++;
    console.log('FAIL: ' + what);
}
const near = (a, b) => Math.abs(a - b) < 2;

(async () => {
    const browser = await chromium.launch({executablePath: CHROME});
    const cases = [];
    for (const design of Object.keys(FOLD)) {
        cases.push({design, s:{design, steps:steps(5)}});
    }
    cases.push({design:'timeline', name:'timeline-alternate', s:{design:'timeline', alternate:true, marker:'number', steps:steps(4)}});
    cases.push({design:'list', name:'list-border', s:{design:'list', card_border_top:2, card_border_right:2, card_border_bottom:2, card_border_left:6, card_padding_left:40, card_margin_left:20, card_radius_tl:24, icon_style:'color', steps:steps(3)}});
    cases.push({design:'wave', name:'wave-eight', s:{design:'wave', steps:steps(8)}});
    // Progress (2026-11-25): numbers, from the left with dots, a bar without markers.
    cases.push({design:'progress', name:'progress-number', s:{design:'progress', marker:'number', show_icons:false, steps:steps(5)}});
    cases.push({design:'progress', name:'progress-left-dot', s:{design:'progress', marker:'dot', progress_align:'left', steps:steps(4)}});
    // Icon markers (2026-11-25) in each design with markers.
    cases.push({design:'progress', name:'progress-icon', s:{design:'progress', marker:'icon', steps:steps(4)}});
    cases.push({design:'timeline', name:'timeline-icon', s:{design:'timeline', marker:'icon', steps:steps(4)}});
    cases.push({design:'zigzag', name:'zigzag-icon', s:{design:'zigzag', marker:'icon', steps:steps(4)}});
    cases.push({design:'progress', name:'progress-bar', s:{design:'progress', marker:'none', line:'solid', line_width:6, progress_align:'left', steps:steps(4)}});
    // Icon position (2026-11-24): every place in every design with cards.
    for (const design of ['list', 'timeline', 'zigzag']) for (const pos of ['left', 'top', 'right', 'bottom']) cases.push({design, name:design + '-icon-' + pos, s:{design, icon_position:pos, steps:steps(3)}, ipos:pos});
    // Per device (2026-11-24): Left on larger screens, Top on phones.
    cases.push({design:'list', name:'list-icon-left-phone-top', s:{design:'list', icon_position:'left', icon_position_mobile:'top', steps:steps(3)}, ipos:{1280:'left', 820:'left', 390:'top'}});
    cases.push({design:'list', name:'list-icon-top-center', s:{design:'list', icon_position:'top', icon_align:'center', steps:steps(3)}, ipos:'top', ialign:'center'});
    cases.push({design:'timeline', name:'timeline-icon-left-bottom', s:{design:'timeline', icon_position:'left', icon_align:'end', steps:steps(3)}, ipos:'left', ialign:'end'});
    // A step's image box (2026-11-25): 16/9 (phones 1), Custom 40px (phones 120px); the image fills it.
    cases.push({design:'list', name:'list-image-box', s:{design:'list', steps:[{title:'A', media:'image', image:5, image_size:'ratio', image_ratio:'16/9', image_ratio_mobile:'1'}, {title:'B', media:'image', image:5, image_size:'custom', image_height:40, image_height_mobile:120}]}, imgbox:{1280:[16 / 9, 40], 820:[16 / 9, 40], 390:[1, 120]}});
    // A step's own Padding / Margin (2026-11-24): inside its card, no sideways scroll.
    cases.push({design:'zigzag', name:'zigzag-step-spacing', s:{design:'zigzag', steps:steps(4).map((x, i) => Object.assign({}, x, i === 1 ? {padding_left:40, padding_right:40, padding_linked:false, margin_top:10, margin_bottom:10, margin_linked:false} : {}))}});
    // Padding / Margin of the whole set (2026-11-24): no sideways scroll, the design folds by the width left.
    cases.push({design:'timeline', name:'timeline-spaced', s:{design:'timeline', use_padding:true, padding_left:40, padding_right:40, padding_linked:false, margin_left:10, margin_left_unit:'%', margin_right:30, margin_linked:false, steps:steps(4)}});
    for (const c of cases) {
        const html = execFileSync(PHP, [path.join(__dirname, 'page.php'), JSON.stringify(c.s)]).toString();
        for (const width of WIDTHS) {
            const page = await browser.newPage({viewport:{width, height:800}});
            const errors = [];
            page.on('pageerror', e => errors.push(e.message));
            await page.setContent(html);
            const name = (c.name || c.design) + '-' + width;
            const r = await page.evaluate(() => {
                const box = el => { const b = el.getBoundingClientRect(); return {left:b.left, top:b.top, right:b.right, bottom:b.bottom, width:b.width, height:b.height}; };
                // The block's content box (its Padding left out, 2026-11-24): what the design folds by.
                const inner = el => { const b = box(el), cs = getComputedStyle(el), l = parseFloat(cs.paddingLeft), r = parseFloat(cs.paddingRight); return Object.assign(b, {left:b.left + l, right:b.right - r, width:b.width - l - r}); };
                const all = sel => [...document.querySelectorAll(sel)].map(box);
                const visible = sel => [...document.querySelectorAll(sel)].map(el => getComputedStyle(el).display !== 'none');
                return {overflow:document.documentElement.scrollWidth - document.documentElement.clientWidth, steps:all('.wpst-step'),
                    cards:all('.wpst-step__card'), orbs:all('.wpst-step__orb'), curves:visible('.wpst-step__curve'), rails:all('.wpst-step__rail'), block:inner(document.querySelector('.wpst'))};
            });
            check(errors.length === 0, name + ': no script errors');
            check(r.overflow <= 0, name + ': no sideways scroll (' + r.overflow + 'px)');
            check(r.steps.every(b => b.width > 0 && b.height > 0 && b.left >= r.block.left - 1 && b.right <= r.block.right + 1), name + ': every step inside the block');
            const folded = r.block.width < FOLD[c.design];
            // Progress (2026-11-25): side by side, the markers on one line and each line reaching the next marker; folded, one column.
            if (c.design === 'progress') {
                const g = await page.evaluate(() => ({steps:[...document.querySelectorAll('.wpst-step')].map(s => { const b = s.getBoundingClientRect(); return [b.left, b.top]; }), marks:[...document.querySelectorAll('.wpst-step__marker')].map(m => { const b = m.getBoundingClientRect(); return [(b.left + b.right) / 2, (b.top + b.bottom) / 2]; }), links:[...document.querySelectorAll('.wpst-step__link')].map(l => { const b = l.getBoundingClientRect(); return [b.left, b.right, b.top, b.bottom]; })}));
                if (folded) check(g.steps.every(x => near(x[0], g.steps[0][0])), name + ': one column');
                else {
                    check(g.steps.every(x => near(x[1], g.steps[0][1])), name + ': in a row');
                    if (g.marks.length) {
                        check(g.marks.every(m => near(m[1], g.marks[0][1])), name + ': markers on one line');
                        check(g.links.every((l, i) => !g.marks[i + 1] || (Math.abs(l[0] - g.marks[i][0]) < 2 && Math.abs(l[1] - g.marks[i + 1][0]) < 2)), name + ': each line from its marker to the next');
                    } else check(g.links.every((l, i) => i === 0 || Math.abs(l[0] - g.links[i - 1][1]) < 1.5), name + ': the bar unbroken');
                }
            }
            // An image box (2026-11-25): its width Size, its height by the ratio or the height of this screen.
            if (c.imgbox) {
                const g = await page.evaluate(() => [...document.querySelectorAll('.wpst-step__icon')].map(i => { const r = i.getBoundingClientRect(), im = i.querySelector('img').getBoundingClientRect(); return [r.width, r.height, im.width, im.height]; }));
                const want = c.imgbox[width];
                check(g.length === 2 && Math.abs(g[0][1] - g[0][0] / want[0]) < 1.5 && Math.abs(g[1][1] - want[1]) < 1.5 && g.every(x => Math.abs(x[2] - x[0]) < 1.5 && Math.abs(x[3] - x[1]) < 1.5), name + ': image boxes ' + JSON.stringify(g.map(x => x.slice(0, 2).map(Math.round))));
            }
            // Icon position (2026-11-24): the icon beside, above or below its texts, as chosen, on every screen.
            if (c.ipos) {
                const ipos = typeof c.ipos === 'object' ? c.ipos[width] : c.ipos;
                const g = await page.evaluate(() => [...document.querySelectorAll('.wpst-step__card')].map(card => { const i = card.querySelector('.wpst-step__icon').getBoundingClientRect(), b = card.querySelector('.wpst-step__body').getBoundingClientRect(); return {il:i.left, ir:i.right, it:i.top, ib:i.bottom, bl:b.left, br:b.right, bt:b.top, bb:b.bottom}; }));
                if (c.ialign === 'center') { const m = await page.evaluate(() => [...document.querySelectorAll('.wpst-step__card')].map(card => { const i = card.querySelector('.wpst-step__icon').getBoundingClientRect(), k = card.getBoundingClientRect(); return Math.abs((i.left + i.right) / 2 - (k.left + k.right) / 2); })); check(m.every(d => d < 2), name + ': icon centred in its card'); }
                if (c.ialign === 'end') check(g.every(x => Math.abs(x.ib - x.bb) < 2), name + ': icon at the bottom of its texts');
                check(g.length > 0 && g.every(x => ipos === 'left' ? x.ir <= x.bl + 1 : ipos === 'right' ? x.il >= x.br - 1 : ipos === 'top' ? x.ib <= x.bt + 1 : x.it >= x.bb - 1), name + ': icon ' + ipos + ' of its texts');
            }
            if (c.design === 'zigzag') {
                const lefts = r.cards.map(b => Math.round(b.left));
                check(folded ? lefts.every(l => near(l, lefts[0])) : !near(lefts[0], lefts[1]), name + ': cards ' + (folded ? 'in one column' : 'from side to side'));
                check(r.curves.every(v => v === !folded), name + ': curves ' + (folded ? 'hidden' : 'shown'));
            }
            if (c.design === 'timeline') {
                const lefts = r.rails.map(b => Math.round(b.left));
                check(lefts.every(l => near(l, lefts[0])), name + ': one line down');
                check(folded ? lefts[0] - r.block.left < 80 : lefts[0] - r.block.left > 100, name + ': the line ' + (folded ? 'on the left' : 'after the side texts'));
                if (c.s.alternate && !folded) check(!near(r.cards[0].left, r.cards[1].left), name + ': cards from side to side');
            }
            if (c.design === 'wave') {
                check(folded ? r.orbs.every(b => near(b.left, r.orbs[0].left)) : r.orbs.every(b => near(b.top, r.orbs[0].top)), name + ': circles ' + (folded ? 'down the side' : 'on one line'));
                if (!folded) check(r.orbs.every((b, i) => i === 0 || b.left >= r.orbs[i - 1].right - 1), name + ': circles do not overlap');
            }
            if (c.design === 'list') {
                check(r.cards.every((b, i) => i === 0 || b.top > r.cards[i - 1].bottom), name + ': cards one under another');
            }
            await page.screenshot({path:path.join(__dirname, name + '.png'), fullPage:true});
            await page.close();
        }
    }
    await browser.close();
    console.log(passed + ' passed, ' + failed + ' failed');
    process.exit(failed ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
