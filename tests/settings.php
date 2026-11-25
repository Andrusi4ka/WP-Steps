<?php
/**
 * PHP checks of the settings and the markup, without WordPress (tests/stubs.php):
 * php tests/settings.php — prints each failure, exits 1 if any.
 */
require __DIR__ . '/stubs.php';

$failed = 0;
$passed = 0;
function check($ok, $what) {
    global $failed, $passed;
    if ($ok) { $passed++; return; }
    $failed++;
    echo "FAIL: $what\n";
}

// Defaults and sanitizing.
$d = WP_Steps_Settings::defaults();
check($d['design'] === 'list' && count($d['steps']) === 4, 'defaults: list design, four sample steps');
check(WP_Steps_Settings::sanitize(array()) === WP_Steps_Settings::sanitize(WP_Steps_Settings::defaults()), 'empty input gives the defaults');
check(WP_Steps_Settings::sanitize('nonsense')['design'] === 'list', 'non-array input');
$s = WP_Steps_Settings::sanitize(array('design' => 'nope', 'card_style' => 'glass', 'marker' => 'number', 'line' => 'solid'));
check($s['design'] === 'list' && $s['card_style'] === 'filled', 'unknown design and choice fall back to defaults');
check($s['marker'] === 'number' && $s['line'] === 'solid', 'known choices kept');
foreach (WP_Steps_Designs::BUILT_IN as $id) {
    check(WP_Steps_Settings::sanitize(array('design' => $id))['design'] === $id, "design $id accepted");
}

// Numbers: limits, per device, inheritance.
$s = WP_Steps_Settings::sanitize(array('gap' => 9999, 'icon' => '50.4', 'title_size' => 'big', 'gap_tablet' => '', 'gap_mobile' => '-5', 'icon_tablet' => 70));
check($s['gap'] === 200, 'gap clamped to its maximum');
check($s['icon'] === 50, 'number rounded');
check($s['title_size'] === 22, 'not a number: the default');
check($s['gap_tablet'] === null && $s['gap_mobile'] === 0, 'tablet empty = inherit; mobile clamped to its minimum');
check(WP_Steps_Settings::sizes($s, 'icon') === array(50, 70, 70), 'phone inherits the tablet size');
check(WP_Steps_Settings::sizes($s, 'gap') === array(200, 200, 0), 'tablet inherits the desktop size');

// Colors.
check(WP_Steps_Settings::color('#ABC') === '#aabbcc', 'short hex expanded');
check(WP_Steps_Settings::color('rgba(10, 20, 30, .5)') === 'rgba(10,20,30,0.5)', 'rgba normalized');
check(WP_Steps_Settings::color('red') === '' && WP_Steps_Settings::color('rgba(300,0,0,1)') === '' && WP_Steps_Settings::color('#12345g') === '', 'invalid colors refused');
check(WP_Steps_Settings::color('#fff;background:url(x)') === '', 'CSS injection refused');
$s = WP_Steps_Settings::sanitize(array('color_from' => 'bad', 'title_color' => 'bad'));
check($s['color_from'] === $d['color_from'] && $s['title_color'] === '', 'first color never empty; an automatic color may be');

// Step colors: from → to, own color kept.
$s = WP_Steps_Settings::sanitize(array('color_from' => '#000000', 'color_to' => '#ffffff', 'steps' => array(array('title' => 'a'), array('title' => 'b', 'color' => '#ff0000'), array('title' => 'c'))));
check(WP_Steps_Settings::step_colors($s) === array('#000000', '#ff0000', '#ffffff'), 'step colors run from the first to the last; own kept');
$s = WP_Steps_Settings::sanitize(array('color_from' => '#000000', 'color_to' => '#ffffff', 'steps' => array(array(), array(), array())));
check(WP_Steps_Settings::step_colors($s)[1] === '#808080', 'middle step mixed');
$s = WP_Steps_Settings::sanitize(array('steps' => array(array('title' => 'only'))));
check(WP_Steps_Settings::step_colors($s) === array($d['color_from']), 'a single step takes the first color');

// Steps: text cleaned, limits.
$s = WP_Steps_Settings::sanitize(array('steps' => array(array('title' => '<b>Bold</b> title', 'text' => 'Hi <script>alert(1)</script><strong>there</strong> <a href="javascript:x" onclick="y">link</a> <img src=x onerror=1>', 'icon' => 'nope', 'image' => '12', 'color' => 'blue'))));
$step = $s['steps'][0];
check($step['title'] === '<b>Bold</b> title', 'title: bold kept (the Title editor, 2026-11-23)');
check(strpos($step['text'], '<script') === false && strpos($step['text'], '<strong>there</strong>') !== false, 'text: script dropped, strong kept');
check(strpos($step['text'], 'javascript') === false && strpos($step['text'], 'onclick') === false && strpos($step['text'], '<img') === false, 'text: dangerous link and attributes dropped');
check($step['icon'] === '' && $step['image'] === 12 && $step['color'] === '', 'unknown icon, image id, invalid color');
$many = array_fill(0, 50, array('title' => 'x'));
check(count(WP_Steps_Settings::sanitize(array('steps' => $many))['steps']) === WP_Steps_Settings::MAX_STEPS, 'at most MAX_STEPS steps');
check(WP_Steps_Settings::sanitize(array('steps' => array()))['steps'] === array(), 'all steps removed stays empty');
check(WP_Steps_Settings::sanitize(array('label_word' => str_repeat('a', 60)))['label_word'] === str_repeat('a', 30), 'label word shortened');

// Numbers.
$s = WP_Steps_Settings::sanitize(array());
check(WP_Steps_Settings::number_text($s, 0) === '01' && WP_Steps_Settings::number_text($s, 11) === '12', 'number 01 format');
$s['number_format'] = '1';
check(WP_Steps_Settings::number_text($s, 0) === '1', 'number 1 format');

// Markup for every design.
foreach (WP_Steps_Designs::all() as $id => $design) {
    $s = WP_Steps_Settings::sanitize(array('design' => $id));
    $html = WP_Steps_Render::markup($s);
    check(strpos($html, '<div class="wpst wpst--' . $id . ' ') === 0, "$id: wrapper class");
    check(substr_count($html, '<li class="wpst-step') === 4, "$id: one item per step");
    check(strpos($html, '--wpst-gap-m:40px') !== false && strpos($html, '--wpst-icon-t:96px') !== false, "$id: per-device sizes as variables (tablet, phone follow desktop)");
    check(strpos($html, '<ol class="wpst__steps">') !== false, "$id: an ordered list");
    check(file_exists(WP_STEPS_DIR . 'assets/css/designs/' . $id . '.css'), "$id: its stylesheet exists");
    check($design->name() !== '' && $design->about() !== '' && strpos($design->thumb(), '<svg') === 0, "$id: name, line and thumbnail");
    check(array_diff($design->supports(), WP_Steps_Settings::OPTIONAL) === array(), "$id: supports only optional settings");
    $stack = $design->stack_below();
    if ($stack) {
        $css = file_get_contents(WP_STEPS_DIR . 'assets/css/designs/' . $id . '.css');
        check(strpos($css, '@container wpst (max-width:' . $stack . 'px)') !== false, "$id: its CSS folds at stack_below() ($stack px)");
    }
}
check(WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('steps' => array()))) === '', 'no steps: no markup');

// Only the options the design uses become classes.
$list = WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'list', 'marker' => 'number', 'alternate' => true)));
check(strpos($list, 'wpst--marker-') === false && strpos($list, 'wpst--alternate') === false, 'list: no marker or alternate class');
check(strpos($list, 'wpst--card-filled') !== false && strpos($list, 'wpst--arrow') !== false, 'list: card and arrow classes');
$timeline = WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'timeline', 'marker' => 'number', 'alternate' => true)));
check(strpos($timeline, 'wpst--marker-number') !== false && strpos($timeline, 'wpst--alternate') !== false, 'timeline: marker and alternate classes');
check(substr_count($timeline, 'wpst-step__marker') === 4 && strpos($timeline, '<span class="wpst-step__num">01</span>') !== false, 'timeline: numbered markers');
$wave = WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'wave', 'arc' => 20)));
check(strpos($wave, '--wpst-arc:20px') !== false && strpos($wave, 'wpst--card-') === false, 'wave: ring thickness, no card classes');
check(substr_count($wave, 'wpst-step__link') === 3, 'wave: a line between each two steps');
$zig = WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'zigzag')));
check(substr_count($zig, 'wpst-step__curve') === 3, 'zigzag: a curve between each two steps');

// Escaping in the markup.
$s = WP_Steps_Settings::sanitize(array('label_word' => 'A "B" & C', 'steps' => array(array('title' => 'A & B', 'aside_title' => '<i>x</i>', 'text' => "line1\nline2"))));
$html = WP_Steps_Render::markup($s);
check(strpos($html, 'A &quot;B&quot; &amp; C') !== false, 'label word escaped');
check(strpos($html, 'A &amp; B') !== false, 'title escaped');
check(strpos($html, "line1<br />\nline2") !== false, 'new lines become line breaks');
// The Text editor (2026-11-23): paragraphs and headings kept as written (aligned), its own size per device, weight and color.
$rich = WP_Steps_Settings::step(array('text' => '<h2 style="text-align: center; font-size: 90px">Head</h2><p>One <u>two</u> <span style="color: #ff0000">red</span></p><script>x</script><img src=x>',
    'text_size' => '18', 'text_size_mobile' => 999, 'text_size_tablet' => '', 'text_weight' => '600', 'text_color' => '#112233'));
check(strpos($rich['text'], '<h2 style="text-align: center">Head</h2>') === 0 && strpos($rich['text'], '<u>two</u>') !== false && strpos($rich['text'], '<script') === false && strpos($rich['text'], '<img') === false, 'Text editor: headings aligned, underline kept, script and image dropped');
check($rich['text_size'] === 18 && $rich['text_size_tablet'] === null && $rich['text_size_mobile'] === WP_Steps_Settings::LIMITS['text_size'][1] && $rich['text_weight'] === '600' && $rich['text_color'] === '#112233', 'its own size (within the limits, empty = none), weight and color');
$list = WP_Steps_Settings::step(array('text' => '<ul><li style="text-align: right; color: red">A</li></ul><ol><li>B</li></ol><p><a href="https://example.test" target="_blank">L</a></p>'))['text'];
check(strpos($list, '<ul><li style="text-align: right">A</li></ul><ol><li>B</li></ol>') === 0 && strpos($list, 'target="_blank" rel="noopener"') !== false && WP_Steps_Settings::is_rich('<ul><li>x</li></ul>'), 'Text editor: lists (items aligned) and links kept, a new tab with noopener');
// The Title tab (2026-11-23): one line of words from the editor; its paragraphs as line breaks, the first one's alignment its own.
$t = WP_Steps_Settings::step(array('title' => '<p style="text-align: right;">One <em>two</em></p><p>Three</p><ul><li>x</li></ul>', 'title_align' => 'left', 'title_tag' => 'div', 'title_size' => 30, 'title_size_mobile' => 2, 'title_weight' => '300', 'title_color' => '#abcdef'));
check($t['title'] === 'One <em>two</em><br>Three<br>x' && $t['title_align'] === 'right' && $t['title_tag'] === 'div' && $t['title_size'] === 30 && $t['title_size_mobile'] === WP_Steps_Settings::LIMITS['title_size'][0], 'Title: words and line breaks, its alignment from the editor, its tag, size (within limits)');
$t2 = WP_Steps_Settings::step(array('title' => 'Plain & simple', 'title_align' => 'center', 'title_tag' => 'span'));
check($t2['title'] === 'Plain &amp; simple' && $t2['title_align'] === 'center' && $t2['title_tag'] === '', 'a plain title escaped, its saved alignment kept; an unknown tag: Default');
$tHtml = WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('title_tag' => 'h3', 'steps' => array($t))));
check(strpos($tHtml, '<div class="wpst-step__title" style="--wpst-tts:30px;--wpst-tts-m:10px;font-weight:300;--title-c:#abcdef;text-align:right;">One <em>two</em><br>Three<br>x</div>') !== false, 'its markup: its own tag, size, weight, color and alignment');
check(strpos(WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('title_tag' => 'h4', 'steps' => array(array('title' => 'T'))))), '<h4 class="wpst-step__title">T</h4>') !== false, 'no tag of its own: the set\'s');
// The icon's own (2026-11-23): its style on the step (else the set's), its circle size per device and its color.
$ic = WP_Steps_Settings::sanitize(array('icon_style' => 'white', 'steps' => array(array('title' => 'A', 'icon' => 'clock', 'icon_style' => 'plain', 'icon_size' => 70, 'icon_size_mobile' => 5, 'icon_color' => '#ff0000'), array('title' => 'B', 'icon' => 'clock', 'icon_style' => 'nope'))));
check($ic['steps'][0]['icon_style'] === 'plain' && $ic['steps'][0]['icon_size_mobile'] === WP_Steps_Settings::LIMITS['icon'][0] && $ic['steps'][1]['icon_style'] === '', 'Icon: its style, size within limits; an unknown style: Default');
$icHtml = WP_Steps_Render::markup($ic);
check(strpos($icHtml, '<li class="wpst-step wpst-step--icon-plain wpst-step--icon-tint') !== false && strpos($icHtml, '--wpst-si:70px;--wpst-si-m:24px;--wpst-ic:#ff0000') !== false && strpos($icHtml, '<li class="wpst-step wpst-step--icon-white') !== false, 'its markup: its style (the second step: the set style), size and color');
$fcss = file_get_contents(WP_STEPS_DIR . 'assets/css/frontend.css') . implode('', array_map('file_get_contents', glob(WP_STEPS_DIR . 'assets/css/designs/*.css')));
check(strpos($fcss, '.wpst--icon-') === false && strpos($fcss, '--icon:var(--wpst-si-m,var(--wpst-si-t,var(--wpst-si,var(--wpst-icon-m))))') !== false, 'CSS: the icon styles on the step, its size per device');
// Icon size % and Ring gap (2026-11-23): within limits, on the step as variables; the CSS reads them.
$sc = WP_Steps_Settings::step(array('icon_scale' => 150, 'ring_gap' => '12', 'ring_width' => 5));
check($sc['icon_scale'] === 100 && !array_key_exists('ring_gap', $sc) && !array_key_exists('ring_width', $sc) && WP_Steps_Settings::step(array())['icon_scale'] === null, 'Icon size: within limits, none by default; no ring keys in a step (2026-11-24: the set\'s)');
check(strpos(WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('steps' => array(array('title' => 'A', 'icon_scale' => 60))))), ';--wpst-isc:0.6') !== false, 'its variable on the step');
check(strpos(file_get_contents(WP_STEPS_DIR . 'assets/css/designs/wave.css'), '--ring:var(--wpst-rg,8px)') !== false && strpos(file_get_contents(WP_STEPS_DIR . 'assets/css/frontend.css'), 'width:calc(var(--isf,.44)*100%)') !== false, 'CSS: the gap and the icon size read them, 8px and 44% as before');
check(strpos(WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('steps' => array(array('title' => 'A', 'icon_scale_mobile' => 80))))), ';--wpst-isc-m:0.8') !== false, 'Icon size per device: the phone one');
// Settings → Custom (2026-11-24): Padding/Margin per device behind its switch, the block's id and classes.
$cu = WP_Steps_Settings::sanitize(array('use_padding' => true, 'padding_top' => 20, 'padding_right' => 999, 'padding_right_unit' => '%', 'padding_bottom_mobile' => 4, 'margin_left_unit' => 'em', 'css_id' => '#1bad id', 'css_class' => '.one, two two <x>'));
check($cu['padding_right'] === 50 && $cu['padding_right_unit'] === '%' && $cu['margin_left_unit'] === 'px' && $cu['padding_top_tablet'] === null && $cu['css_id'] === '' && $cu['css_class'] === 'one two x', 'Custom: within limits, units, a bad id dropped, classes cleaned');
$cuh = WP_Steps_Render::markup($cu);
check(strpos($cuh, 'wpst--padding') !== false && strpos($cuh, 'wpst--margin') === false && strpos($cuh, '--wpst-pad:20px 50% 0 0;--wpst-pad-t:20px 50% 0 0;--wpst-pad-m:20px 50% 4px 0;') !== false && strpos($cuh, ' one two x"') !== false, 'its variables per device, only where set; the classes on the block');
check(strpos(WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('css_id' => 'my-steps', 'padding_top' => 20))), '<div id="my-steps" class="wpst ') !== false && strpos(WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('padding_top' => 20))), 'wpst--padding') === false, 'the id on the block; switched off: no padding');
// The line's width per device (2026-11-24): -t / -m following the larger screen, within limits; the CSS per screen.
check(strpos(WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('line_width' => 5, 'line_width_mobile' => 99))), '--wpst-lw:5px;--wpst-lw-t:5px;--wpst-lw-m:12px;') !== false && strpos(file_get_contents(WP_STEPS_DIR . 'assets/css/frontend.css'), '--lw:var(--wpst-lw-m,var(--wpst-lw-t,var(--wpst-lw,3px)))') !== false, 'Line width per device');
// A step's Padding / Margin (2026-11-24): none by default; a class and a value per side and device where it has one (inherited down).
$sp = WP_Steps_Settings::step(array('padding_left' => 30, 'padding_left_unit' => '%', 'margin_bottom_tablet' => 999));
check($sp['padding_left'] === 30 && $sp['padding_left_unit'] === '%' && $sp['margin_bottom_tablet'] === 500 && WP_Steps_Settings::step(array())['padding_top'] === null, 'step spacing: within limits, none by default');
$sph = WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('steps' => array(array('title' => 'A', 'padding_left' => 30, 'padding_left_unit' => '%', 'margin_bottom_tablet' => 8), array('title' => 'B')))));
check(strpos($sph, 'wpst-step--pl-d wpst-step--pl-t wpst-step--pl-m wpst-step--mb-t wpst-step--mb-m"') !== false && strpos($sph, ';--wpst-pl-d:30%;--wpst-pl-t:30%;--wpst-pl-m:30%;--wpst-mb-t:8px;--wpst-mb-m:8px') !== false && substr_count($sph, 'wpst-step--pl-d') === 1, 'step spacing: classes and values, the larger screen\'s below, none on Desktop where only the tablet has one');
check(strpos(file_get_contents(WP_STEPS_DIR . 'assets/css/frontend.css'), '.wpst .wpst-step.wpst-step--mb-t .wpst-step__card.wpst-step__card,.wpst.wpst--wave .wpst-step.wpst-step--mb-t .wpst-step__body.wpst-step__body{margin-bottom:var(--wpst-mb-t)}') !== false, 'step spacing: its CSS');
// Renamed layout → design (2026-11-24): a set saved with the old key keeps its design.
check(WP_Steps_Settings::sanitize(array('layout' => 'wave'))['design'] === 'wave' && !array_key_exists('layout', WP_Steps_Settings::sanitize(array('layout' => 'wave'))), 'old key layout read as design');
// Icon position (2026-11-24): a class where set and the design has cards; Auto none; not in Wave; anything else Auto.
check(strpos(WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'list', 'icon_position' => 'top'))), 'wpst--ipos-top') !== false && strpos(WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'wave', 'icon_position' => 'top'))), 'wpst--ipos') === false && WP_Steps_Settings::sanitize(array('icon_position' => 'middle'))['icon_position'] === '' && strpos(WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'list'))), 'wpst--ipos') === false, 'Icon position');
// Icon alignment (2026-11-24): a class only with a position; anything else none.
check(strpos(WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'list', 'icon_position' => 'top', 'icon_align' => 'center'))), 'wpst--ipos-top-d wpst--ialign-center-d wpst--ig-d wpst--ipos-top-t wpst--ialign-center-t wpst--ig-t wpst--ipos-top-m wpst--ialign-center-m wpst--ig-m') !== false && strpos(WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'list', 'icon_align' => 'center'))), 'wpst--ialign') === false && WP_Steps_Settings::sanitize(array('icon_align' => 'middle'))['icon_align'] === '', 'Icon alignment');
// The icon per device (2026-11-24): tablet / phone follow the larger screen, 'auto' there is the design's own; the gap per device.
$il = WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'list', 'icon_position' => 'left', 'icon_align' => 'end', 'icon_position_tablet' => 'top', 'icon_align_tablet' => 'center', 'icon_position_mobile' => 'auto', 'icon_gap_tablet' => 12, 'icon_position_wrong' => 1, 'icon_align_mobile' => 'middle')));
check(strpos($il, 'wpst--ipos-left-d wpst--ialign-end-d wpst--ig-d wpst--ipos-top-t wpst--ialign-center-t wpst--ig-t wpst--ig-m') !== false, 'icon per device: classes (phones Auto: no place)');
check(strpos($il, '--wpst-ig-d:28px;--wpst-ig-t:12px;--wpst-ig-m:12px') !== false && strpos($il, 'wpst--ipos-left-m') === false, 'icon per device: Auto on phones; the gap 28px on Desktop, the tablet\'s below');
check(WP_Steps_Settings::sanitize(array('icon_align_mobile' => 'middle', 'icon_gap' => 999))['icon_align_mobile'] === null && WP_Steps_Settings::sanitize(array('icon_gap' => 999))['icon_gap'] === 200 && WP_Steps_Settings::sanitize(array('icon_gap' => ''))['icon_gap'] === 28, 'icon per device: cleaned; the gap always a value');
// The cards' box (2026-11-25): no switches; border and radius 0 by default (none, square); per side / corner and device, units, limits.
check(strpos(WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'list'))), 'wpst--radius') === false && !array_key_exists('card_use_radius', WP_Steps_Settings::defaults()) && WP_Steps_Settings::defaults()['card_radius_tl'] === 0, 'card radius: none by default, no switch');
$cr = WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'list', 'card_radius_tl' => 16, 'card_radius_tr' => 300, 'card_radius_br' => 10, 'card_radius_br_unit' => '%', 'card_radius_bl_mobile' => 4)));
check(strpos($cr, 'wpst--radius') !== false && strpos($cr, '--wpst-crr:16px 200px 10% 0;--wpst-crr-t:16px 200px 10% 0;--wpst-crr-m:16px 200px 10% 4px;') !== false, 'card radius: per corner and device, units, within limits');
check(strpos(WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'wave', 'card_radius_tl' => 9))), 'wpst--radius') === false && WP_Steps_Settings::sanitize(array('card_radius_tl' => ''))['card_radius_tl'] === 0, 'card radius: not in Wave; emptied 0');
check(WP_Steps_Settings::sanitize(array('card_use_radius' => false, 'card_radius_tl' => 16))['card_radius_tl'] === 0 && WP_Steps_Settings::sanitize(array('card_use_border' => false, 'card_border_top' => 2))['card_border_top'] === 0, 'card box: a set saved with a switch off keeps none');
// Zigzag's line corners (2026-11-24): per device, only in Zigzag; the CSS reads them.
check(strpos(WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'zigzag', 'line_radius_mobile' => 8))), '--wpst-lr:24px;--wpst-lr-t:24px;--wpst-lr-m:8px') !== false && strpos(WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'list'))), '--wpst-lr') === false && strpos(file_get_contents(WP_STEPS_DIR . 'assets/css/designs/zigzag.css'), 'border-bottom-right-radius:var(--lr)') !== false, 'Zigzag line corners');
// The cards' Border (2026-11-25): none by default; per side and device, its line and color; not in Wave. Their padding / margin; a step's own.
$cb = WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'list', 'card_border_top' => 2, 'card_border_right' => 2, 'card_border_bottom' => 2, 'card_border_left' => 99, 'card_border_top_mobile' => 0, 'card_border_style' => 'dotted', 'card_border_color' => '#ff0000')));
check(strpos($cb, 'wpst--border') !== false && strpos($cb, '--wpst-cbw:2px 2px 2px 20px;--wpst-cbw-t:2px 2px 2px 20px;--wpst-cbw-m:0 2px 2px 20px;--wpst-cbs:dotted;--wpst-cbc:#ff0000') !== false, 'card border: widths per device within limits, line, color');
check(strpos(WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'list'))), 'wpst--border') === false && strpos(WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'wave', 'card_border_top' => 3))), 'wpst--border') === false && WP_Steps_Settings::sanitize(array('card_style' => 'outline'))['card_style'] === 'filled', 'card border: none by default, not in Wave; cards always filled');
$cpm = WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'list', 'card_padding_left' => 40, 'card_margin_top_tablet' => 5, 'card_margin_top_unit' => '%')));
check(strpos($cpm, 'wpst--cpl-d') !== false && strpos($cpm, '--wpst-cpl-d:40px;') !== false && strpos($cpm, '--wpst-cmt-t:5%;--wpst-cmt-m:5%') !== false && strpos($cpm, 'wpst--cmt-d') === false, 'cards\' padding / margin: per side and device where set');
$sbx = WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'list', 'card_border_top' => 2, 'card_border_right' => 2, 'card_border_bottom' => 2, 'card_border_left' => 2, 'card_radius_tl' => 8, 'steps' => array(array('title' => 'A', 'border_left' => 6, 'border_color' => '#00ff00', 'radius_br_mobile' => 30, 'radius_br_unit' => '%'), array('title' => 'B')))));
check(strpos($sbx, 'wpst-step--bw wpst-step--br') !== false && strpos($sbx, ';--wpst-sbw:2px 2px 2px 6px;--wpst-sbw-t:2px 2px 2px 6px;--wpst-sbw-m:2px 2px 2px 6px;--wpst-sbc:#00ff00;--wpst-sbr:8px 0 0 0;--wpst-sbr-t:8px 0 0 0;--wpst-sbr-m:8px 0 30% 0') !== false && substr_count($sbx, 'wpst-step--bw') === 1, 'a step\'s own border / radius with the cards\' per device');
// Side text in editors (2026-11-24): its title inline words (paragraphs to line breaks, no blocks), its text as a text; old plain lines kept.
$as = WP_Steps_Settings::step(array('aside_title' => '<p>Side <strong>T</strong></p><p>2</p>', 'aside_text' => '<p>A <em>b</em></p><script>x</script>'));
check($as['aside_title'] === 'Side <strong>T</strong><br>2' && strpos($as['aside_text'], '<p>A <em>b</em></p>') === 0 && strpos($as['aside_text'], 'script') === false, 'side text: cleaned');
$ash = WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'timeline', 'steps' => array(array('title' => 'A', 'aside_title' => 'Side <strong>T</strong>', 'aside_text' => "one\ntwo")))));
check(strpos($ash, '<p class="wpst-step__aside-title">Side <strong>T</strong></p>') !== false && strpos($ash, 'one<br />') !== false, 'side text: rendered, old plain lines kept');
// Side text style (2026-11-24): the title's tag; size, weight and color per device on each.
$ast = WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'timeline', 'steps' => array(array('title' => 'A', 'aside_title' => 'T', 'aside_text' => '<p>X</p>', 'aside_title_tag' => 'h4', 'aside_title_size' => 30, 'aside_title_weight_mobile' => '300', 'aside_text_color_tablet' => '#ff0000', 'aside_text_weight' => '999')))));
check(strpos($ast, '<h4 class="wpst-step__aside-title" style="--wpst-ats:30px;--wpst-atw-m:300;">T</h4>') !== false && strpos($ast, '<div class="wpst-step__aside-text" style="--wpst-axc-t:#ff0000;">') !== false, 'side text: tag, own size / weight / color per device; a wrong weight dropped');
check(strpos(file_get_contents(WP_STEPS_DIR . 'assets/css/frontend.css'), 'font-weight:var(--wpst-atw-m,var(--wpst-atw-t,var(--wpst-atw,600)))') !== false, 'side text: its CSS per screen');
// Half rings' Thickness may be 0 (2026-11-24, user's request: no ring).
check(WP_Steps_Settings::sanitize(array('arc' => 0))['arc'] === 0 && strpos(WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'wave', 'arc' => 0))), '--wpst-arc:0px') !== false, 'Thickness 0');
// Icons square by default outside Wave, a circle in Wave (2026-11-25); no ring inside a color icon.
$css = file_get_contents(WP_STEPS_DIR . 'assets/css/frontend.css');
check(strpos($css, 'border-radius:var(--irad,0)') !== false && strpos(file_get_contents(WP_STEPS_DIR . 'assets/css/designs/wave.css'), '.wpst--wave .wpst-step__icon{border-radius:var(--irad,50%)}') !== false && strpos($css, 'inset 0 0 0 3px rgba(255,255,255,.6)') === false, 'icons square outside Wave');
check(strpos(WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'list', 'steps' => array(array('title' => 'A', 'icon_radius_tl' => 10))))), '--wpst-ir:10% 0 0 0') !== false && strpos(WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'wave', 'steps' => array(array('title' => 'A', 'icon_radius_tl' => 10))))), '--wpst-ir:10% 50% 50% 50%') !== false, 'a corner none set: square outside Wave, round in Wave');
// A step's card shadow (2026-11-25): on with its parts (else the cards'), off, or the cards' when not set.
$ssh = WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'list', 'shadow' => false, 'steps' => array(array('title' => 'A', 'shadow' => true, 'shadow_y' => 4, 'shadow_color' => '#ff0000'), array('title' => 'B', 'shadow' => false), array('title' => 'C')))));
check(strpos($ssh, 'wpst-step--csh') !== false && strpos($ssh, '--wpst-csh:0px 4px 28px -16px #ff0000') !== false && substr_count($ssh, 'wpst-step--csh-off') === 1 && substr_count($ssh, 'wpst-step--csh ') + substr_count($ssh, 'wpst-step--csh"') === 1, 'a step\'s shadow: on, off, the cards\'');
// The step number's alignment (2026-11-25): a class where chosen, in List / Timeline / Wave only.
check(strpos(WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'list', 'label_align' => 'center'))), 'wpst--lalign-center') !== false && strpos(WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'zigzag', 'label_align' => 'center'))), 'wpst--lalign') === false && WP_Steps_Settings::sanitize(array('label_align' => 'middle'))['label_align'] === '', 'Numbers alignment');
// List's arrow centred on its line at any width (2026-11-25): its middle at the border's middle.
check(strpos(file_get_contents(WP_STEPS_DIR . 'assets/css/designs/list.css'), 'bottom:-1px;left:calc(var(--lw)/-2)') !== false, 'List arrow centred');
// A step's image box (2026-11-25): ratio / custom per device, fit, position; cleaned; not in Wave; nothing without an image.
check(WP_Steps_Settings::ratio('16:9') === '16/9' && WP_Steps_Settings::ratio(' 4 / 3 ') === '4/3' && WP_Steps_Settings::ratio('1.5') === '1.5' && WP_Steps_Settings::ratio('0/3') === '' && WP_Steps_Settings::ratio('x') === '', 'ratio cleaned');
$ib = WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'list', 'steps' => array(array('title' => 'A', 'media' => 'image', 'image' => 5, 'image_size' => 'ratio', 'image_ratio' => '16:9', 'image_ratio_mobile' => '1', 'image_fit' => 'contain', 'image_position' => 'left top'), array('title' => 'B', 'media' => 'image', 'image' => 5, 'image_size' => 'custom', 'image_height_tablet' => 120)))));
check(strpos($ib, 'wpst-step--img-box wpst-step--img-ratio') !== false && strpos($ib, ';--wpst-iar:16/9;--wpst-iar-m:1;--wpst-ifit:contain;--wpst-ipos:left top') !== false && strpos($ib, 'wpst-step--img-custom') !== false && strpos($ib, ';--wpst-ih-t:120px') !== false, 'image box: classes and values');
check(strpos(WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'wave', 'steps' => array(array('title' => 'A', 'media' => 'image', 'image' => 5, 'image_size' => 'ratio'))))), 'img-box') === false && strpos(WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'list', 'steps' => array(array('title' => 'A', 'media' => 'icon', 'image_size' => 'ratio'))))), 'img-box') === false, 'image box: not in Wave, not for an icon');
// Progress (2026-11-25): a marker and a line for each step, the bar for the last one too without markers, icons shown or not, left.
$pg = WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'progress', 'marker' => 'number')));
check(strpos($pg, 'wpst--progress') !== false && substr_count($pg, 'wpst-step__marker') === 4 && substr_count($pg, 'wpst-step__link') === 3 && strpos($pg, 'wpst-step__label') === false && strpos($pg, 'wpst-step__icon') !== false, 'progress: number markers, three lines, icons');
$pg2 = WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'progress', 'marker' => 'none', 'show_icons' => false, 'progress_align' => 'left')));
check(substr_count($pg2, 'wpst-step__link') === 4 && strpos($pg2, 'wpst-step__icon') === false && strpos($pg2, 'wpst--palign-left') !== false && strpos($pg2, 'wpst-step__label') !== false, 'progress: a bar to the end, no icons, from the left');
// Icon markers (2026-11-25): the step's icon on the line, not in its card too; a dot for a step without one.
$mi = WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'timeline', 'marker' => 'icon', 'steps' => array(array('title' => 'A', 'icon' => 'flag'), array('title' => 'B', 'icon' => '')))));
check(strpos($mi, 'wpst--marker-icon') !== false && substr_count($mi, 'wpst-step__icon') === 1 && preg_match('#<span class="wpst-step__marker"><span class="wpst-step__icon#', $mi) && strpos($mi, 'wpst-step__marker wpst-step__marker--dot') !== false, 'icon markers');
// No Max width (2026-11-24, user's word): no key, no variable, no CSS limit.
check(!array_key_exists('max_width', WP_Steps_Settings::sanitize(array('max_width' => 900))) && strpos(WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array())), '--wpst-max') === false && strpos(file_get_contents(WP_STEPS_DIR . 'assets/css/frontend.css'), 'max-width:var(--wpst-max') === false, 'No Max width');
// Wave's half rings, the set's per device (2026-11-24): thickness and gap within limits, -t / -m following the larger screen; none elsewhere.
$hr = WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'wave', 'arc' => 99, 'arc_mobile' => 3, 'ring_gap' => 6, 'ring_gap_tablet' => 2)));
check(strpos($hr, '--wpst-arc:40px;--wpst-arc-t:40px;--wpst-arc-m:3px;--wpst-rg:6px;--wpst-rg-t:2px;--wpst-rg-m:2px;') !== false, 'Half rings: per device, within limits');
check(strpos(WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('design' => 'list'))), '--wpst-rg') === false && WP_Steps_Settings::defaults()['ring_gap'] === 8 && WP_Steps_Settings::defaults()['arc'] === 14, 'not in a List; 8px and 14px by default');
// The circle (2026-11-23): Filled / Outline / None and its color; not chosen, from the old style (the step's, else the set's).
$cs = array('icon_style' => 'color');
check(WP_Steps_Settings::icon_circle($cs, WP_Steps_Settings::step(array()))[1] === 'color' && WP_Steps_Settings::icon_circle(array('icon_style' => 'plain'), WP_Steps_Settings::step(array()))[0] === 'none'
    && WP_Steps_Settings::icon_circle($cs, WP_Steps_Settings::step(array('icon_bg' => '#000000')))[1] === 'white' && WP_Steps_Settings::icon_circle($cs, WP_Steps_Settings::step(array('icon_shape' => 'outline')))[1] === 'outline'
    && WP_Steps_Settings::icon_circle($cs, WP_Steps_Settings::step(array('icon_shape' => 'none', 'icon_bg' => '#000000')))[2] === '', 'Circle: from the old style; an own fill: a white circle class with it; Outline; None: no fill');
$csHtml = WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('icon_style' => 'white', 'steps' => array(array('title' => 'A', 'icon_shape' => 'outline', 'icon_bg' => '#123456')))));
check(strpos($csHtml, 'wpst-step--icon-outline') !== false && strpos($csHtml, 'wpst-step--icon-bg') !== false && strpos($csHtml, '--wpst-ibg:#123456') !== false, 'its markup: Outline with its ring color');
$sh = WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('shadow' => true, 'steps' => array(array('title' => 'A'), array('title' => 'B', 'icon_shadow' => false), array('title' => 'C', 'icon_shadow' => '')))));
check(substr_count($sh, 'wpst-step--icon-shadow') === 2, 'Circle shadow: the set Shadow until the step has its own (off on B)');
check(WP_Steps_Settings::step(array())['icon_shadow'] === null && WP_Steps_Settings::step(array('icon_shadow' => 'false'))['icon_shadow'] === false, 'none of its own by default');
// Shadows' parts (2026-11-23, as WP Swiper's): the cards' on the block, a circle's on its step; as before by default.
$shp = WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('shadow' => true, 'card_shadow_x' => 3, 'card_shadow_y' => 500, 'card_shadow_color' => '#000000', 'steps' => array(array('title' => 'A', 'icon_shadow_blur' => 4, 'icon_shadow_color' => 'rgba(0,0,0,0.2)'), array('title' => 'B')))));
check(strpos($shp, '--wpst-csh:3px 100px 28px -16px #000000') !== false && strpos($shp, '--wpst-ish:0px 8px 4px -8px rgba(0,0,0,0.2)') !== false && strpos($shp, '--wpst-ish:0px 8px 18px -8px rgba(15,23,42,0.5)') !== false, 'Shadows: the parts of the cards (within limits), of one step, another as before');
// Radius (2026-11-23): per corner and device, the larger screen followed, else a circle; px within 200, % within 50.
$rs = WP_Steps_Settings::step(array('icon_radius_tl' => 12, 'icon_radius_tl_unit' => 'px', 'icon_radius_br_mobile' => 90, 'icon_radius_br_unit' => '%'));
check($rs['icon_radius_br_mobile'] === 50 && $rs['icon_radius_linked'] === true && WP_Steps_Settings::icon_radii(WP_Steps_Settings::step(array())) === '', 'Radius: % within 50; linked by default; none of its own: a circle (nothing)');
check(WP_Steps_Settings::icon_radii($rs) === array('desktop' => '12px 50% 50% 50%', 'tablet' => '12px 50% 50% 50%', 'mobile' => '12px 50% 50% 50%'), 'its corners per device');
$rs2 = WP_Steps_Settings::step(array('icon_radius_br_mobile' => 10));
check(WP_Steps_Settings::icon_radii($rs2)['mobile'] === '50% 50% 10% 50%' && WP_Steps_Settings::icon_radii($rs2)['desktop'] === '50% 50% 50% 50%', 'a phone corner of its own');
check(strpos(WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('steps' => array($rs)))), ';--wpst-ir:12px 0 0 0;--wpst-ir-t:') !== false, 'its markup');
// Behind its switch: on for a step saved with corners before it; switched off, a circle (the corners kept).
$off = WP_Steps_Settings::step(array('icon_use_radius' => false, 'icon_radius_tl' => 5));
check($rs['icon_use_radius'] === true && $off['icon_radius_tl'] === 5 && WP_Steps_Settings::icon_radii($off) === array('desktop' => '0', 'tablet' => '0', 'mobile' => '0') && WP_Steps_Settings::step(array())['icon_use_radius'] === true && WP_Steps_Settings::icon_radii(WP_Steps_Settings::step(array())) === '', 'Radius switch: on by default (a circle); off: square corners, its own kept (2026-11-24)');
// Icon | Image (2026-11-23): the step's choice; not chosen, its image when it has one (as before).
check(WP_Steps_Settings::shows_image(array('image' => 5)) && !WP_Steps_Settings::shows_image(array('image' => 5, 'media' => 'icon')) && WP_Steps_Settings::shows_image(array('media' => 'image')) && !WP_Steps_Settings::shows_image(array()), 'Icon or image: chosen, else the image when there is one');
check(strpos(WP_Steps_Render::icon(array('icon' => 'clock', 'image' => 0, 'media' => 'image')), 'svg') === false && strpos(WP_Steps_Render::icon(array('icon' => 'clock', 'image' => 7, 'media' => 'icon')), '<svg') !== false && WP_Steps_Settings::step(array('media' => 'video'))['media'] === '', 'Image chosen without one: nothing; Icon chosen: the icon though an image is kept; an unknown choice: as before');
// The half ring's thickness in the step (2026-11-24): per device, within the old limits; wave.css reads it, else the set's.
check(strpos(file_get_contents(WP_STEPS_DIR . 'assets/css/designs/wave.css'), '--arcw:var(--wpst-arc-m,var(--wpst-arc-t,var(--wpst-arc,14px)))') !== false, 'Thickness: the set\'s per screen');
// No Frame any more (2026-11-24): a set saved with one loses it.
$nf = WP_Steps_Settings::sanitize(array('frame' => true, 'frame_bg' => '#000000'));
check(!isset($nf['frame']) && !isset($nf['frame_bg']) && strpos(WP_Steps_Render::markup($nf), 'wpst--frame') === false && strpos(WP_Steps_Render::markup($nf), 'frame-bg') === false, 'Frame: gone, dropped from saved sets');
// A step as a link (2026-11-24): its address cleaned (no javascript:, a bare domain https), a cover over the step, a new tab with noopener.
check(WP_Steps_Settings::link('javascript:alert(1)') === '' && WP_Steps_Settings::link('example.test/a') === 'https://example.test/a' && WP_Steps_Settings::link('#contact') === '#contact' && WP_Steps_Settings::link('') === '', 'Link: cleaned');
$lk = WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('steps' => array(array('title' => 'Call <b>us</b>', 'link' => 'https://example.test/x', 'link_new_tab' => true), array('title' => 'B')))));
check(substr_count($lk, 'wpst-step__cover') === 1 && preg_match('#<div class="wpst-step__card"><a class="wpst-step__cover" href="https://example.test/x" target="_blank" rel="noopener noreferrer" aria-label="Call us"></a>#', $lk), 'its cover: over the step, in a new tab, named by its title; none on a step without a link');
check(WP_Steps_Settings::step(array('icon_shape' => 'square'))['icon_shape'] === '', 'an unknown circle: as before');
check(WP_Steps_Settings::step(array('text_weight' => '950'))['text_weight'] === '' && WP_Steps_Settings::step(array('text' => '<p> &nbsp;</p>'))['text'] === '', 'an unknown weight: Default; an empty paragraph: no text');
$richHtml = WP_Steps_Render::markup(WP_Steps_Settings::sanitize(array('steps' => array($rich))));
check(strpos($richHtml, '<div class="wpst-step__text" style="--wpst-ts:18px;--wpst-ts-m:40px;font-weight:600;--text-c:#112233;"><h2 style="text-align: center">Head</h2>') !== false && strpos($richHtml, '<br') === false, 'its markup: as written, its own size, weight and color on the text');
check(strpos(file_get_contents(WP_STEPS_DIR . 'assets/css/frontend.css'), 'font-size:var(--wpst-ts-m,var(--wpst-ts-t,var(--wpst-ts,var(--text))))') !== false, 'frontend.css: a phone takes the step\'s phone size, else its larger screen\'s, else Sizes\'');
check(strpos($html, 'wpst-step--no-aside') === false, 'a step with a side text');
$s = WP_Steps_Settings::sanitize(array('title_tag' => 'h2', 'steps' => array(array('title' => 'T'))));
check(strpos(WP_Steps_Render::markup($s), '<h2 class="wpst-step__title">T</h2>') !== false, 'title tag');
$s = WP_Steps_Settings::sanitize(array('show_label' => false, 'steps' => array(array('title' => 'T'))));
check(strpos(WP_Steps_Render::markup($s), 'wpst-step__label') === false, 'number hidden');
$s = WP_Steps_Settings::sanitize(array('steps' => array(array('title' => 'T', 'icon' => 'flag'))));
check(strpos(WP_Steps_Render::markup($s), '<span class="wpst-step__icon" aria-hidden="true"><svg') !== false, 'icon drawn');

// Icons.
foreach (WP_Steps_Icons::ICONS as $name => $icon) {
    check(preg_match('/^[a-z]+$/', $name) && $icon[0] !== '' && strpos($icon[1], '<') === 0 && strpos($icon[1], 'script') === false, "icon $name");
}
check(WP_Steps_Icons::svg('nope') === '', 'unknown icon: nothing');

// A design added through the filter.
class WP_Steps_Test_Design extends WP_Steps_Design {
    public function id() { return 'test-grid'; }
    public function name() { return 'Grid'; }
    public function about() { return 'Test'; }
    public function supports() { return array('marker', 'not-a-setting'); }
    public function step($p, $s) { return '<div class="grid">' . $p['title'] . '</div>'; }
}
add_filter('wp_steps_designs', function ($list) { $list[] = new WP_Steps_Test_Design(); $list[] = 'junk'; return $list; });
WP_Steps_Designs::reset();
check(WP_Steps_Designs::exists('test-grid') && count(WP_Steps_Designs::all()) === count(WP_Steps_Designs::BUILT_IN) + 1, 'a design added through the filter');
check(WP_Steps_Settings::sanitize(array('design' => 'test-grid'))['design'] === 'test-grid', 'the added design can be chosen');
$editor = WP_Steps_Designs::editor();
check(end($editor)['supports'] === array('marker'), 'editor: only known optional settings listed');
remove_all_filters('wp_steps_designs');
WP_Steps_Designs::reset();

echo "$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
