<?php
/**
 * The steps on a page: the shortcode [wp_steps id=""], the markup and its stylesheets
 * (frontend.css for all designs, plus the chosen design's own).
 *
 * Markup: <div class="wpst wpst--{design} …" style="sizes and colors as CSS variables">
 * <ol class="wpst__steps"><li class="wpst-step" style="--wpst-c: the step's color">…</li></ol></div>.
 * The design writes what is inside each <li> (WP_Steps_Design::step()). No script: the designs
 * fold into one column by CSS container queries (on the block's width, not the screen's).
 */
defined('ABSPATH') || exit;

class WP_Steps_Render {
    const SHORTCODE = 'wp_steps';

    // The per-device sizes as CSS variables: key => variable name.
    const SIZE_VARS = array('gap' => 'gap', 'icon' => 'icon', 'title_size' => 'title', 'text_size' => 'text', 'number_size' => 'num', 'line_width' => 'lw');
    const COLOR_VARS = array('color_from' => 'from', 'color_to' => 'to', 'label_color' => 'label-color', 'line_color' => 'line-color');

    public static function init() {
        add_shortcode(self::SHORTCODE, array(__CLASS__, 'shortcode'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'register'), 5);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'early_assets'), 20);
    }

    public static function register() {
        if (wp_style_is('wp-steps', 'registered')) { return; }
        wp_register_style('wp-steps', WP_STEPS_URL . 'assets/css/frontend.css', array(), wp_steps_asset('assets/css/frontend.css'));
        foreach (WP_Steps_Designs::all() as $design) {
            wp_register_style('wp-steps-' . $design->id(), $design->css_url(), array('wp-steps'), $design->css_version());
        }
    }

    // Steps in the page's own content (also from WPBakery, which keeps its elements there as shortcodes): their styles in <head>.
    public static function early_assets() {
        $post = get_queried_object();
        if (!($post instanceof WP_Post) || strpos($post->post_content, '[' . self::SHORTCODE) === false) { return; }
        if (!preg_match_all('/\[' . self::SHORTCODE . '\b[^\]]*?\bid\s*=\s*["\']?(\d+)/', $post->post_content, $m)) { return; }
        foreach (array_unique(array_map('intval', $m[1])) as $id) {
            if (get_post_type($id) === WP_Steps_Settings::POST_TYPE) {
                wp_enqueue_style('wp-steps-' . WP_Steps_Designs::get(WP_Steps_Settings::get($id)['design'])->id());
            }
        }
    }

    public static function shortcode($atts) {
        $atts = shortcode_atts(array('id' => 0), $atts, self::SHORTCODE);
        $id = absint($atts['id']);
        $post = $id ? get_post($id) : null;
        if (!$post || $post->post_type !== WP_Steps_Settings::POST_TYPE || $post->post_status !== 'publish') { return ''; }
        $s = WP_Steps_Settings::get($id);
        return self::styles(WP_Steps_Designs::get($s['design'])->id()) . self::markup($s);
    }

    /**
     * The stylesheets. Before the page's <head> is printed they are queued; after it (steps in a widget,
     * a theme's block, a template) they are printed right before the steps, once a page.
     */
    public static function styles($design) {
        self::register();
        $handles = array('wp-steps', 'wp-steps-' . $design);
        if (!did_action('wp_head') || doing_action('wp_head')) {
            foreach ($handles as $handle) { wp_enqueue_style($handle); }
            return '';
        }
        ob_start();
        wp_styles()->do_items($handles);
        return (string) ob_get_clean();
    }

    // The steps' markup ('' without steps).
    public static function markup($s) {
        if (empty($s['steps'])) { return ''; }
        $design = WP_Steps_Designs::get($s['design']);
        $supports = $design->supports();
        $classes = array('wpst', 'wpst--' . $design->id(), 'wpst--line-' . $s['line']);
        // The icon's place in its card (2026-11-24): wpst--ipos-<left|top|right|bottom>; none: the design's own.
        // Per device (2026-11-24): wpst--ipos-<pos>-<d|t|m>, wpst--ialign-<v>-<d|t|m>, wpst--ig-<d|t|m> with --wpst-ig-<d|t|m>;
        // a device without them keeps the design's own (frontend.css: each in its screen range).
        $igap = array();
        if (in_array('icon_position', $supports, true)) {
            foreach (WP_Steps_Settings::icon_layout($s) as $device => list($pos, $align, $gap)) {
                $d = $device[0];
                if ($pos !== '') { $classes[] = 'wpst--ipos-' . $pos . '-' . $d; }
                if ($align !== '') { $classes[] = 'wpst--ialign-' . $align . '-' . $d; }
                if ($gap !== null) { $classes[] = 'wpst--ig-' . $d; $igap['--wpst-ig-' . $d] = (int) $gap . 'px'; }
            }
        }
        // Progress (2026-11-25): its texts from the left.
        if (in_array('progress_align', $supports, true) && $s['progress_align'] === 'left') { $classes[] = 'wpst--palign-left'; }
        // The step number's alignment (2026-11-25): wpst--lalign-<left|center|right>; none: the design's.
        if (!empty($s['label_align']) && in_array('label_align', $supports, true)) { $classes[] = 'wpst--lalign-' . $s['label_align']; }
        // Cards are always filled (a Border on top where set): much CSS reads wpst--card-filled.
        if (in_array('card_style', $supports, true)) { $classes[] = 'wpst--card-filled'; }
        if (in_array('marker', $supports, true)) { $classes[] = 'wpst--marker-' . $s['marker']; }
        foreach (array('shadow', 'arrow', 'alternate') as $key) {
            if ($s[$key] && in_array($key, $supports, true)) { $classes[] = 'wpst--' . $key; }
        }

        $vars = array('--wpst-n' => count($s['steps']));
        foreach (self::SIZE_VARS as $key => $name) { self::device_vars($vars, $name, WP_Steps_Settings::sizes($s, $key), 'px'); }
        // Zigzag's line corners (2026-11-24); the half rings (Wave): thickness and gap per device (2026-11-24: the set's again).
        if (in_array('line_radius', $supports, true)) { self::device_vars($vars, 'lr', WP_Steps_Settings::sizes($s, 'line_radius'), 'px'); }
        if (in_array('arc', $supports, true)) {
            foreach (array('arc' => 'arc', 'ring_gap' => 'rg') as $key => $name) { self::device_vars($vars, $name, WP_Steps_Settings::sizes($s, $key), 'px'); }
        }
        // The cards' shadow (2026-11-23): its parts as set.
        if (!empty($s['shadow'])) { $vars['--wpst-csh'] = WP_Steps_Settings::shadow_css($s['card_shadow_x'], $s['card_shadow_y'], $s['card_shadow_blur'], $s['card_shadow_spread'], $s['card_shadow_color']); }
        foreach (self::COLOR_VARS as $key => $name) {
            if ($s[$key] !== '') { $vars['--wpst-' . $name] = $s[$key]; }
        }
        // Padding / Margin of the whole set (2026-11-24, user's request: Settings → Custom), per device (frontend.css).
        foreach (array('padding' => 'pad', 'margin' => 'mg') as $kind => $name) {
            $sp = WP_Steps_Settings::spaces($s, $kind);
            if (!$sp) { continue; }
            $classes[] = 'wpst--' . $kind;
            self::device_vars($vars, $name, $sp);
        }
        if (!empty($s['css_class'])) { $classes[] = $s['css_class']; }
        $vars += $igap;
        // The cards' corners (2026-11-24): their Radius per device; switched off, square.
        $radii = in_array('card_radius', $supports, true) ? WP_Steps_Settings::card_radii($s) : '';
        // Their Border (2026-11-24): widths per device, its line and color (else the step's).
        $borders = in_array('card_border', $supports, true) ? WP_Steps_Settings::card_borders($s) : '';
        if ($borders) {
            $classes[] = 'wpst--border';
            self::device_vars($vars, 'cbw', $borders);
            $vars['--wpst-cbs'] = $s['card_border_style'];
            if ($s['card_border_color'] !== '') { $vars['--wpst-cbc'] = $s['card_border_color']; }
        }
        // The cards' padding / margin (2026-11-25): per side and device where set, a class and a value (frontend.css). They are in the
        // same box as the Border (the editor's boxModel('card'), shown where the design supports card_border), so the same check.
        if (in_array('card_border', $supports, true)) {
            foreach (array('padding' => 'p', 'margin' => 'm') as $kind => $k) {
                foreach (WP_Steps_Settings::SIDES as $side) {
                    foreach (WP_Steps_Settings::step_side($s, 'card_' . $kind . '_' . $side) as $device => $v) {
                        if ($v === null) { continue; }
                        $name = 'c' . $k . $side[0] . '-' . $device[0];
                        $classes[] = 'wpst--' . $name;
                        $vars['--wpst-' . $name] = $v;
                    }
                }
            }
        }
        if ($radii) { $classes[] = 'wpst--radius'; self::device_vars($vars, 'crr', $radii); }
        $style = '';
        foreach ($vars as $name => $value) { $style .= $name . ':' . $value . ';'; }

        $colors = WP_Steps_Settings::step_colors($s);
        $last = count($s['steps']) - 1;
        $items = '';
        foreach ($s['steps'] as $i => $step) {
            $p = self::parts($s, $i, $step, $i === $last);
            // Its icon (2026-11-23): its own style (else the set's) as wpst-step--icon-*, its circle size per device and its color.
            list(, $istyle, $ibg) = WP_Steps_Settings::icon_circle($s, $step);
            $ivars = '';
            foreach (array('' => 'icon_size', '-t' => 'icon_size_tablet', '-m' => 'icon_size_mobile') as $sfx => $key) { if (isset($step[$key]) && $step[$key] !== null) { $ivars .= ';--wpst-si' . $sfx . ':' . (int) $step[$key] . 'px'; } }
            if (!empty($step['icon_color'])) { $ivars .= ';--wpst-ic:' . $step['icon_color']; }
            if ($ibg !== '') { $ivars .= ';--wpst-ibg:' . $ibg; }
            // Its circle's corners per device (2026-11-23).
            $iradii = WP_Steps_Settings::icon_radii($step, in_array('arc', $supports, true) ? '50%' : '0');
            if ($iradii) { $ivars .= ';--wpst-ir:' . $iradii['desktop'] . ';--wpst-ir-t:' . $iradii['tablet'] . ';--wpst-ir-m:' . $iradii['mobile']; }
            // Its circle's shadow, as set in the step (2026-11-23).
            $ivars .= ';--wpst-ish:' . WP_Steps_Settings::icon_shadow_css($step);
            // Its Padding / Margin (2026-11-24): for each side and device that has one (its own or the larger screen's) a class
            // wpst-step--<p|m><t|r|b|l>-<d|t|m> and its value --wpst-<p|m><t|r|b|l>-<d|t|m> (frontend.css); elsewhere the design's own.
            $sclasses = '';
            foreach (array('padding' => 'p', 'margin' => 'm') as $kind => $k) {
                foreach (WP_Steps_Settings::SIDES as $side) {
                    foreach (WP_Steps_Settings::step_side($step, $kind . '_' . $side) as $device => $v) {
                        if ($v === null) { continue; }
                        $name = $k . $side[0] . '-' . $device[0];
                        $sclasses .= ' wpst-step--' . $name;
                        $ivars .= ';--wpst-' . $name . ':' . $v;
                    }
                }
            }
            // Its card's own Border and Radius (2026-11-25): resolved with the cards' per device.
            $bw = WP_Steps_Settings::step_box($s, $step, 'border');
            if ($bw) {
                $sclasses .= ' wpst-step--bw';
                $ivars .= ';--wpst-sbw:' . $bw['desktop'] . ';--wpst-sbw-t:' . $bw['tablet'] . ';--wpst-sbw-m:' . $bw['mobile'];
                if (!empty($step['border_style'])) { $ivars .= ';--wpst-sbs:' . $step['border_style']; }
                if (!empty($step['border_color'])) { $ivars .= ';--wpst-sbc:' . $step['border_color']; }
            }
            // Its card's own shadow (2026-11-25): on with its parts (else the cards'), or off; none set: the cards'.
            // Parts of its own while it follows the cards' shadow (on): on with them.
            if (in_array('shadow', $supports, true) && ($step['shadow'] ?? null) === null && !empty($s['shadow']) && WP_Steps_Settings::has_own($step, 'shadow_', array('x', 'y', 'blur', 'spread'))) { $step['shadow'] = true; }
            if (in_array('shadow', $supports, true) && isset($step['shadow']) && $step['shadow'] !== null) {
                if ($step['shadow']) { $sclasses .= ' wpst-step--csh'; $ivars .= ';--wpst-csh:' . WP_Steps_Settings::step_shadow_css($s, $step); }
                else { $sclasses .= ' wpst-step--csh-off'; }
            }
            // Its image's box (2026-11-25): Aspect ratio / Custom height, the image filling it (fit, position); where the design has cards.
            if (in_array('image_size', $supports, true) && !empty($step['image_size']) && WP_Steps_Settings::shows_image($step)) {
                $sclasses .= ' wpst-step--img-box wpst-step--img-' . $step['image_size'];
                foreach (array('' => '', '_tablet' => '-t', '_mobile' => '-m') as $sfx => $v) {
                    if ($step['image_size'] === 'ratio' && !empty($step['image_ratio' . $sfx])) { $ivars .= ';--wpst-iar' . $v . ':' . $step['image_ratio' . $sfx]; }
                    if ($step['image_size'] === 'custom' && isset($step['image_height' . $sfx]) && $step['image_height' . $sfx] !== null) { $ivars .= ';--wpst-ih' . $v . ':' . (int) $step['image_height' . $sfx] . 'px'; }
                }
                if (!empty($step['image_fit'])) { $ivars .= ';--wpst-ifit:' . $step['image_fit']; }
                if (!empty($step['image_position'])) { $ivars .= ';--wpst-ipos:' . $step['image_position']; }
            }
            $br = WP_Steps_Settings::step_box($s, $step, 'radius');
            if ($br) { $sclasses .= ' wpst-step--br'; $ivars .= ';--wpst-sbr:' . $br['desktop'] . ';--wpst-sbr-t:' . $br['tablet'] . ';--wpst-sbr-m:' . $br['mobile']; }
            // Icon size % per device (2026-11-23): -t / -m only where the step has one.
            foreach (array('' => '', '-t' => '_tablet', '-m' => '_mobile') as $sfx => $dev) {
                if (isset($step['icon_scale' . $dev]) && $step['icon_scale' . $dev] !== null) { $ivars .= ';--wpst-isc' . $sfx . ':' . ((int) $step['icon_scale' . $dev] / 100); }
            }
            $items .= '<li class="wpst-step wpst-step--icon-' . esc_attr($istyle) . (!empty($step['icon_color']) ? ' wpst-step--icon-tint' : '') . ($ibg !== '' ? ' wpst-step--icon-bg' : '')
                // Its circle's shadow (2026-11-23): the step's, else the cards' Shadow; only a filled circle casts one.
                . (self::icon_shadow_on($s, $step, $supports) ? ' wpst-step--icon-shadow' : '') . ($p['aside'] === '' ? ' wpst-step--no-aside' : '') . $sclasses . '" style="' . esc_attr('--wpst-c:' . $colors[$i] . $ivars) . '">' . self::with_cover($design->step($p, $s), self::cover($step, $i)) . '</li>';
        }
        return '<div' . (!empty($s['css_id']) ? ' id="' . esc_attr($s['css_id']) . '"' : '') . ' class="' . esc_attr(implode(' ', $classes)) . '" style="' . esc_attr($style) . '"><ol class="wpst__steps">' . $items . '</ol></div>';
    }

    // A value on each screen as CSS variables: --wpst-<name>, -t, -m ($values: [desktop, tablet, phone] or keyed by device).
    private static function device_vars(&$vars, $name, $values, $unit = '') {
        $values = array_values($values);
        foreach (array('', '-t', '-m') as $i => $sfx) { $vars['--wpst-' . $name . $sfx] = $values[$i] . $unit; }
    }

    // Does a step's circle cast a shadow? Its own switch, else the cards' Shadow — but only where the design has cards
    // (2026-11-25: in Wave / Progress the cards' switch is not shown, so it no longer decides for the icons there).
    public static function icon_shadow_on($s, $step, $supports) {
        $own = $step['icon_shadow'] ?? null;
        return $own === null ? (!empty($s['shadow']) && in_array('shadow', $supports, true)) : (bool) $own;
    }

    // The side title's / text's own size, weight and color per device (2026-11-24): --wpst-<at|ax><s|w|c>[-t|-m] where it has them
    // (frontend.css: the larger screen's, else the usual); ' style="…"' or ''.
    public static function aside_style($step, $k, $p) {
        $css = '';
        foreach (array('' => '', '_tablet' => '-t', '_mobile' => '-m') as $sfx => $v) {
            if (isset($step[$k . '_size' . $sfx]) && $step[$k . '_size' . $sfx] !== null) { $css .= '--wpst-' . $p . 's' . $v . ':' . (int) $step[$k . '_size' . $sfx] . 'px;'; }
            if (!empty($step[$k . '_weight' . $sfx])) { $css .= '--wpst-' . $p . 'w' . $v . ':' . (int) $step[$k . '_weight' . $sfx] . ';'; }
            if (!empty($step[$k . '_color' . $sfx])) { $css .= '--wpst-' . $p . 'c' . $v . ':' . $step[$k . '_color' . $sfx] . ';'; }
        }
        return $css === '' ? '' : ' style="' . esc_attr($css) . '"';
    }
    // The step as a link (2026-11-24): an empty link over it, named by its title (or Step N); '' without one.
    public static function cover($step, $i) {
        if (empty($step['link'])) { return ''; }
        $name = trim(wp_strip_all_tags((string) $step['title']));
        return '<a class="wpst-step__cover" href="' . esc_url($step['link']) . '"' . (!empty($step['link_new_tab']) ? ' target="_blank" rel="noopener noreferrer"' : '')
            . ' aria-label="' . esc_attr($name !== '' ? html_entity_decode($name, ENT_QUOTES, 'UTF-8') : 'Step ' . ($i + 1)) . '"></a>';
    }
    // The cover goes inside the step's card where it has one (the card is a layer of its own: a link in its text could not rise
    // over a cover outside it); without a card (Wave) over the whole step.
    public static function with_cover($html, $cover) {
        if ($cover === '') { return $html; }
        if (preg_match('#<div class="wpst-step__card"[^>]*>#', $html, $m, PREG_OFFSET_CAPTURE)) {
            $at = $m[0][1] + strlen($m[0][0]);
            return substr($html, 0, $at) . $cover . substr($html, $at);
        }
        return $html . $cover;
    }
    // A step's parts, for the design to arrange.
    public static function parts($s, $i, $step, $last) {
        $number = WP_Steps_Settings::number_text($s, $i);
        $word = $s['label_word'] !== '' ? '<span class="wpst-step__word">' . esc_html($s['label_word']) . '</span>' : '';
        $label = $s['show_label'] ? '<span class="wpst-step__label">' . $word . ($word !== '' ? ' ' : '') . '<span class="wpst-step__num">' . esc_html($number) . '</span></span>' : '';
        // Its Title (2026-11-23: from the editor, inline): its own tag (else the set's), size per device, weight, color, alignment.
        $tag = !empty($step['title_tag']) ? $step['title_tag'] : WP_Steps_Settings::TITLE_TAG;
        $own = '';
        foreach (array('' => 'title_size', '-t' => 'title_size_tablet', '-m' => 'title_size_mobile') as $sfx => $key) { if (isset($step[$key]) && $step[$key] !== null) { $own .= '--wpst-tts' . $sfx . ':' . (int) $step[$key] . 'px;'; } }
        if (!empty($step['title_weight'])) { $own .= 'font-weight:' . (int) $step['title_weight'] . ';'; }
        if (!empty($step['title_color'])) { $own .= '--title-c:' . $step['title_color'] . ';'; }
        if (!empty($step['title_align'])) { $own .= 'text-align:' . $step['title_align'] . ';'; }
        $title = $step['title'] !== '' ? '<' . $tag . ' class="wpst-step__title"' . ($own !== '' ? ' style="' . esc_attr($own) . '"' : '') . '>' . $step['title'] . '</' . $tag . '>' : '';
        // Its Text: from the editor as it is; written before it, its lines kept. Its own size (per device), weight and color.
        $own = '';
        foreach (array('' => 'text_size', '-t' => 'text_size_tablet', '-m' => 'text_size_mobile') as $sfx => $key) { if (isset($step[$key]) && $step[$key] !== null) { $own .= '--wpst-ts' . $sfx . ':' . (int) $step[$key] . 'px;'; } }
        if (!empty($step['text_weight'])) { $own .= 'font-weight:' . (int) $step['text_weight'] . ';'; }
        if (!empty($step['text_color'])) { $own .= '--text-c:' . $step['text_color'] . ';'; }
        $text = $step['text'] !== '' ? '<div class="wpst-step__text"' . ($own !== '' ? ' style="' . esc_attr($own) . '"' : '') . '>'
            . (WP_Steps_Settings::is_rich($step['text']) ? $step['text'] : nl2br($step['text'])) . '</div>' : '';
        $aside = '';
        $atag = !empty($step['aside_title_tag']) ? $step['aside_title_tag'] : 'p';
        if ($step['aside_title'] !== '' || $step['aside_text'] !== '') {
            $aside = '<div class="wpst-step__aside">'
                . ($step['aside_title'] !== '' ? '<' . $atag . ' class="wpst-step__aside-title"' . self::aside_style($step, 'aside_title', 'at') . '>' . $step['aside_title'] . '</' . $atag . '>' : '')
                . ($step['aside_text'] !== '' ? '<div class="wpst-step__aside-text"' . self::aside_style($step, 'aside_text', 'ax') . '>' . (WP_Steps_Settings::is_rich($step['aside_text']) ? $step['aside_text'] : nl2br($step['aside_text'])) . '</div>' : '') . '</div>';
        }
        $marker = '';
        $icon = self::icon($step);
        // Icon: the step's icon or image is its marker (none of its own: a dot), and is not drawn in its card / above the line too.
        if ($s['marker'] === 'icon') {
            $marker = '<span class="wpst-step__marker' . ($icon === '' ? ' wpst-step__marker--dot' : '') . '">' . $icon . '</span>';
            $icon = '';
        } elseif ($s['marker'] === 'number') {
            $marker = '<span class="wpst-step__marker">' . ($s['show_label'] ? $word : '') . '<span class="wpst-step__num">' . esc_html($number) . '</span></span>';
        } elseif ($s['marker'] !== 'none') {
            $marker = '<span class="wpst-step__marker"></span>';
        }
        return array('index' => $i, 'last' => $last, 'number' => $number, 'word' => $word, 'label' => $label,
            'icon' => $icon, 'title' => $title, 'text' => $text, 'aside' => $aside, 'marker' => $marker);
    }

    // The step's icon in its circle: its own image, or one of the plugin's icons; '' without one.
    public static function icon($step) {
        $inner = '';
        // Its image or its icon, as the step chose (2026-11-23: Icon | Image); an image chosen but missing: nothing.
        $image = WP_Steps_Settings::shows_image($step);
        if ($image && $step['image'] && function_exists('wp_get_attachment_image')) {
            $inner = wp_get_attachment_image($step['image'], 'medium', false, array('class' => 'wpst-step__img', 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async'));
        }
        if ($inner === '' && !$image) { $inner = WP_Steps_Icons::svg($step['icon']); }
        return $inner === '' ? '' : '<span class="wpst-step__icon" aria-hidden="true">' . $inner . '</span>';
    }
}
