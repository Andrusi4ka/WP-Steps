<?php
/**
 * The data: one post (type wp_steps) per set of steps, its settings in one post meta.
 * Everything that is saved or previewed goes through sanitize().
 *
 * Per-device sizes, as in WP Swiper and WP Banner: the desktop value under the key itself,
 * tablet and phone under key_tablet / key_mobile; null there = the larger screen's value.
 */
defined('ABSPATH') || exit;

class WP_Steps_Settings {
    const POST_TYPE = 'wp_steps';
    const META = '_wp_steps_settings';
    const MAX_STEPS = 30;

    // Settings only some designs use (WP_Steps_Design::supports()); the rest apply to all.
    const OPTIONAL = array('icon_position', 'card_style', 'card_radius', 'card_border', 'shadow', 'icon_style', 'marker', 'arrow', 'alternate', 'aside', 'arc', 'line_radius', 'label_align', 'image_size', 'progress_align', 'show_icons');

    // Per-device sizes in px: their desktop defaults. Tablet and phone follow the larger screen until
    // they are given a value of their own (as WP Swiper: no defaults of their own).
    // arc / ring_gap: Wave's half rings, their thickness and their gap from the circle (2026-11-24, user's request: back in the
    // Settings, the step's window keeps only what every design uses).
    const DEVICE = array('gap' => 40, 'icon' => 96, 'title_size' => 22, 'text_size' => 15, 'number_size' => 56, 'arc' => 14, 'ring_gap' => 8,
        // The line's width per device (2026-11-24, user's request).
        'line_width' => 3,
        // The gap between the icon and the texts in a card (2026-11-24, user's request; no Auto: 28px ≈ the designs' own before).
        'icon_gap' => 28,
        // Zigzag's line between markers: the radius of its corners (2026-11-24, user's choice: down from a marker, across the middle of
        // the gap, down to the next, with rounded corners instead of the flat S of two quarter ellipses).
        'line_radius' => 24);

    // Numbers: [min, max].
    const LIMITS = array(
        // A shadow's offsets, blur and spread (px), as WP Swiper's (2026-11-23).
        'card_shadow_x' => array(-100, 100), 'card_shadow_y' => array(-100, 100), 'card_shadow_blur' => array(0, 100), 'card_shadow_spread' => array(-50, 50),
        'gap' => array(0, 200), 'icon' => array(24, 240), 'title_size' => array(10, 80), 'text_size' => array(10, 40),
        'number_size' => array(16, 160), 'line_width' => array(1, 12), 'arc' => array(0, 40), 'ring_gap' => array(0, 40), 'icon_gap' => array(0, 200), 'line_radius' => array(0, 200),
    );

    // Lists to choose from: the first is the default.
    const CHOICES = array(
        // Always filled (2026-11-24, user's request: the Filled / Outline buttons removed; a Border instead).
        'card_style'    => array('filled'),
        'card_border_style' => array('solid', 'dashed', 'dotted', 'double'),
        'icon_style'    => array('white', 'color', 'plain'),
        // icon (2026-11-25, user's choice): the step's own icon or image (its Icon tab) on the line instead of a marker.
        'marker'        => array('ring', 'dot', 'number', 'none', 'icon'),
        'line'          => array('dotted', 'dashed', 'solid', 'none'),
        'number_format' => array('01', '1'),
        // The icon in its card (2026-11-24, user's request: Layout → Icon position): '' = where the design puts it.
        'icon_position' => array('', 'left', 'top', 'right', 'bottom'),
        // Its alignment along the other side (2026-11-24, user's request): Left / Right → top, middle, bottom; Top / Bottom → left, center,
        // right (the texts too); '' = middle beside, left above / below.
        'icon_align'    => array('', 'start', 'center', 'end'),
        // The step number's alignment (2026-11-25, user's request; List, Timeline, Wave): '' = the design's (left; Wave centre).
        'label_align'   => array('', 'left', 'center', 'right'),
        // Progress (2026-11-25): its marker and texts centred in each column, or from the left.
        'progress_align' => array('center', 'left'),
        'title_tag'     => array('h3', 'h2', 'h4', 'h5', 'h6', 'p'),
    );

    // No Frame any more (removed 2026-11-24 at the user's word; a saved frame / frame_bg is dropped by sanitize()).
    const SWITCHES = array('shadow', 'arrow', 'alternate', 'show_label', 'use_padding', 'show_icons');
    // Padding / Margin of the whole set (2026-11-24, user's request, as WP Banner's): each side's largest per unit.
    const SIDES = array('top', 'right', 'bottom', 'left');
    const SPACE_MAX = array('px' => 500, '%' => 50);

    // Colors; '' = automatic (see frontend.css).
    const COLORS = array('color_from', 'color_to', 'title_color', 'text_color', 'label_color', 'line_color');

    // What a step's text may hold.
    /*
     * A step's Text from WordPress's editor (2026-11-23, user's request: as WP Swiper's caption editor, copied from WP Banner's
     * Text layer): paragraphs, headings and list items (aligned), bold, italic, underline, a color (a span), line breaks,
     * bulleted and numbered lists, links (one opening in another tab gets "noopener").
     * Only the styles in RICH_STYLES. Only tags or the editor's empty paragraph: ''.
     */
    const RICH_TAGS = array('p' => array('style' => true), 'h1' => array('style' => true), 'h2' => array('style' => true), 'h3' => array('style' => true),
        'h4' => array('style' => true), 'h5' => array('style' => true), 'h6' => array('style' => true), 'br' => array(), 'strong' => array(), 'b' => array(),
        'em' => array(), 'i' => array(), 'u' => array(), 'span' => array('style' => true), 'a' => array('href' => true, 'target' => true, 'rel' => true),
        'ul' => array(), 'ol' => array(), 'li' => array('style' => true));
    const RICH_STYLES = array('color', 'text-decoration', 'text-decoration-line', 'text-align');
    // The editor's font weights; '' = as the other steps.
    const WEIGHTS = array('', '300', '400', '500', '600', '700', '800');
    public static function rich_text($html) {
        $html = preg_replace_callback('#<(p|h[1-6]|li)\b([^>]*)>#i', function ($m) {
            return '<' . strtolower($m[1]) . (preg_match('/text-align\s*:\s*(left|center|right|justify)/i', $m[2], $a) ? ' style="text-align: ' . strtolower($a[1]) . '"' : '') . '>';
        }, (string) $html);
        $only = function () { return WP_Steps_Settings::RICH_STYLES; };
        add_filter('safe_style_css', $only, 999);
        $html = trim(wp_kses($html, self::RICH_TAGS));
        remove_filter('safe_style_css', $only, 999);
        $html = self::noopener($html);
        $text = preg_replace('/[\s\x{00A0}]+/u', '', html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES, 'UTF-8'));
        return (string) $text === '' ? '' : $html;
    }
    // Written before the editor (plain lines): its line breaks kept (WP_Steps_Render). With paragraphs or headings: as it is.
    public static function is_rich($html) { return (bool) preg_match('#<(p|h[1-6]|ul|ol)[\s>]#i', (string) $html); }
    /*
     * A step's Title from the editor: one line of words (the Tag decides its element, which holds no paragraphs): bold, italic,
     * underline, a color, links, line breaks. Its paragraphs become line breaks, the first one's alignment its own (title_align).
     * A title written before it (plain text) is escaped. Returns [html, align or null when it says none].
     */
    const TITLE_TAGS = array('strong' => array(), 'b' => array(), 'em' => array(), 'i' => array(), 'u' => array(), 'br' => array(),
        'span' => array('style' => true), 'a' => array('href' => true, 'target' => true, 'rel' => true));
    const TITLE_ELEMENTS = array('', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div');
    const ALIGNS = array('', 'left', 'center', 'right');
    const ICON_STYLES = array('', 'white', 'color', 'plain');
    const ICON_SHAPES = array('', 'filled', 'outline', 'none');
    // A step's link: http(s), mailto:, tel:, a page's own path or #anchor; anything else (javascript: and the like) none.
    public static function link($value) {
        $value = trim((string) $value);
        if ($value === '') { return ''; }
        if (!preg_match('#^(https?://|mailto:|tel:|/|\#)#i', $value)) { $value = preg_match('#^[a-z][a-z0-9+.-]*:#i', $value) ? '' : 'https://' . $value; }
        return $value === '' ? '' : esc_url_raw($value, array('http', 'https', 'mailto', 'tel'));
    }
    // Does the step show its image (its choice; not chosen: when it has one)?
    public static function shows_image($step) {
        $media = $step['media'] ?? '';
        return $media === 'image' || ($media === '' && !empty($step['image']));
    }
    const RADIUS_MAX = array('px' => 200, '%' => 50);
    const CORNERS = array('tl', 'tr', 'br', 'bl');
    // A step's circle corners per device as CSS border-radius ('' where the step has none of its own anywhere: a circle).
    // $round: a corner none set (2026-11-25: '0' outside Wave, where icons are square by default; '50%' in Wave).
    public static function icon_radii($step, $round = '50%') {
        // Off: square corners on every screen.
        if (isset($step['icon_use_radius']) && !$step['icon_use_radius']) { return array('desktop' => '0', 'tablet' => '0', 'mobile' => '0'); }
        $own = false;
        foreach (self::CORNERS as $c) { foreach (array('', '_tablet', '_mobile') as $d) { $own = $own || (isset($step['icon_radius_' . $c . $d]) && $step['icon_radius_' . $c . $d] !== null); } }
        if (!$own) { return ''; }
        $out = array();
        foreach (array('desktop' => array(''), 'tablet' => array('_tablet', ''), 'mobile' => array('_mobile', '_tablet', '')) as $device => $chain) {
            $parts = array();
            foreach (self::CORNERS as $c) {
                $v = null;
                foreach ($chain as $d) { if (isset($step['icon_radius_' . $c . $d]) && $step['icon_radius_' . $c . $d] !== null) { $v = $step['icon_radius_' . $c . $d]; break; } }
                $parts[] = $v === null ? $round : (int) $v . ($step['icon_radius_' . $c . '_unit'] ?? '%');
            }
            $out[$device] = implode(' ', $parts);
        }
        return $out;
    }
    // A circle's shadow as drawn before it could be set: x, y, blur, spread, color.
    const ICON_SHADOW = array(0, 8, 18, -8, 'rgba(15,23,42,0.5)');
    // A box-shadow from its parts.
    public static function shadow_css($x, $y, $blur, $spread, $color) {
        return (int) $x . 'px ' . (int) $y . 'px ' . (int) $blur . 'px ' . (int) $spread . 'px ' . $color;
    }
    // A step's circle shadow (its own parts, else ICON_SHADOW's).
    // An aspect ratio as CSS takes it: '16/9', '1.5' (16:9 and 16 / 9 read too); else ''.
    public static function ratio($value) {
        $value = str_replace(array(':', ' '), array('/', ''), trim((string) $value));
        if (!preg_match('#^(\d{1,4}(?:\.\d{1,3})?)(?:/(\d{1,4}(?:\.\d{1,3})?))?$#', $value, $m) || (float) $m[1] <= 0 || (isset($m[2]) && (float) $m[2] <= 0)) { return ''; }
        return isset($m[2]) ? $m[1] . '/' . $m[2] : $m[1];
    }
    // A step's card shadow: its parts, else the cards' (2026-11-25).
    public static function step_shadow_css($s, $step) {
        $part = function ($key) use ($s, $step) { return isset($step[$key]) && $step[$key] !== null && $step[$key] !== '' ? $step[$key] : $s['card_' . $key]; };
        return self::shadow_css($part('shadow_x'), $part('shadow_y'), $part('shadow_blur'), $part('shadow_spread'), $part('shadow_color'));
    }
    public static function icon_shadow_css($step) {
        $d = self::ICON_SHADOW;
        $part = function ($key, $i) use ($step, $d) { return isset($step[$key]) && $step[$key] !== null && $step[$key] !== '' ? $step[$key] : $d[$i]; };
        return self::shadow_css($part('icon_shadow_x', 0), $part('icon_shadow_y', 1), $part('icon_shadow_blur', 2), $part('icon_shadow_spread', 3), $part('icon_shadow_color', 4));
    }
    /*
     * A step's circle as drawn: [shape, class for the old styles' CSS (white / color / plain / outline), its fill or ring color
     * ('' = the class's own: white, the step's color), the icon's color ('' = the class's own)]. Not chosen, they come from the old
     * style (the step's, else the set's): White circle = filled white, Color circle = filled in the step's color, Icon only = none.
     */
    public static function icon_circle($s, $step) {
        $style = !empty($step['icon_style']) ? $step['icon_style'] : ($s['icon_style'] ?? 'white');
        $shape = !empty($step['icon_shape']) ? $step['icon_shape'] : ($style === 'plain' ? 'none' : 'filled');
        $class = $shape === 'none' ? 'plain' : ($shape === 'outline' ? 'outline' : ($style === 'color' && empty($step['icon_bg']) ? 'color' : 'white'));
        return array($shape, $class, $shape === 'none' ? '' : ($step['icon_bg'] ?? ''), $step['icon_color'] ?? '');
    }
    public static function rich_title($html) {
        $html = trim((string) $html);
        $align = null;
        if (preg_match('#<(p|h[1-6]|div)\b#i', $html)) {
            $align = preg_match('#<(?:p|h[1-6]|div)\b[^>]*text-align\s*:\s*(left|center|right)#i', $html, $m) ? strtolower($m[1]) : '';
            $html = preg_replace('#</(?:p|h[1-6]|div|li)>\s*(?:</?(?:ul|ol)\b[^>]*>\s*)*<(?:p|h[1-6]|div|li)\b[^>]*>#i', '<br>', $html);
            $html = trim(preg_replace('#</?(?:p|h[1-6]|div|ul|ol|li)\b[^>]*>#i', '', $html));
        } elseif (strpos($html, '<') === false) {
            $html = htmlspecialchars($html, ENT_NOQUOTES, 'UTF-8', false);
        }
        $only = function () { return WP_Steps_Settings::RICH_STYLES; };
        add_filter('safe_style_css', $only, 999);
        $html = trim(wp_kses($html, self::TITLE_TAGS));
        remove_filter('safe_style_css', $only, 999);
        $html = self::noopener($html);
        $text = preg_replace('/[\s\x{00A0}]+/u', '', html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES, 'UTF-8'));
        return array((string) $text === '' ? '' : $html, $align);
    }
    // A link opening in another tab: "noopener" in its rel.
    public static function noopener($html) {
        return preg_replace_callback('#<a\s[^>]*\btarget="[^"]*"[^>]*>#i', function ($m) {
            if (preg_match('#\brel="([^"]*)"#i', $m[0], $rel)) {
                return preg_match('#(^|\s)noopener(\s|$)#i', $rel[1]) ? $m[0] : str_replace($rel[0], 'rel="' . trim($rel[1] . ' noopener') . '"', $m[0]);
            }
            return substr($m[0], 0, -1) . ' rel="noopener">';
        }, $html);
    }
    const TEXT_TAGS = array('strong' => array(), 'b' => array(), 'em' => array(), 'i' => array(), 'br' => array(),
        'a' => array('href' => true, 'target' => true, 'rel' => true));

    public static function defaults() {
        $d = array(
            'design'        => WP_Steps_Designs::DEFAULT_DESIGN,
            'steps'         => self::sample_steps(),
            'icon_position' => '',
            'icon_align'    => '',
            'label_align'   => '',
            'progress_align' => 'center',
            // Progress: the steps' icons above the line (2026-11-25).
            'show_icons'    => true,
            // Per device (2026-11-24, user's request): tablet / phone null = the larger screen's; 'auto' there = the design's own.
            'icon_position_tablet' => null, 'icon_position_mobile' => null, 'icon_align_tablet' => null, 'icon_align_mobile' => null,
            'card_style'    => 'filled',
            // The cards' box (2026-11-25, user's request: Margin, Border, Radius and Padding in one block, no switches): border widths px
            // and corners (px | %) per device, 0 on Desktop (no border, square); padding / margin per side and device, none = the design's
            // own; tablet / phone null = the larger screen's. The border's line and color ('' = the step's color).
            'card_border_top' => 0, 'card_border_top_tablet' => null, 'card_border_top_mobile' => null, 'card_border_right' => 0, 'card_border_right_tablet' => null, 'card_border_right_mobile' => null, 'card_border_bottom' => 0, 'card_border_bottom_tablet' => null, 'card_border_bottom_mobile' => null, 'card_border_left' => 0, 'card_border_left_tablet' => null, 'card_border_left_mobile' => null,
            'card_border_linked' => true, 'card_border_style' => 'solid', 'card_border_color' => '',
            'card_radius_tl' => 0, 'card_radius_tl_tablet' => null, 'card_radius_tl_mobile' => null, 'card_radius_tl_unit' => 'px', 'card_radius_tr' => 0, 'card_radius_tr_tablet' => null, 'card_radius_tr_mobile' => null, 'card_radius_tr_unit' => 'px', 'card_radius_br' => 0, 'card_radius_br_tablet' => null, 'card_radius_br_mobile' => null, 'card_radius_br_unit' => 'px', 'card_radius_bl' => 0, 'card_radius_bl_tablet' => null, 'card_radius_bl_mobile' => null, 'card_radius_bl_unit' => 'px',
            'card_radius_linked' => true,
            'card_padding_top' => null, 'card_padding_top_tablet' => null, 'card_padding_top_mobile' => null, 'card_padding_top_unit' => 'px', 'card_padding_right' => null, 'card_padding_right_tablet' => null, 'card_padding_right_mobile' => null, 'card_padding_right_unit' => 'px', 'card_padding_bottom' => null, 'card_padding_bottom_tablet' => null, 'card_padding_bottom_mobile' => null, 'card_padding_bottom_unit' => 'px', 'card_padding_left' => null, 'card_padding_left_tablet' => null, 'card_padding_left_mobile' => null, 'card_padding_left_unit' => 'px', 'card_padding_linked' => true,
            'card_margin_top' => null, 'card_margin_top_tablet' => null, 'card_margin_top_mobile' => null, 'card_margin_top_unit' => 'px', 'card_margin_right' => null, 'card_margin_right_tablet' => null, 'card_margin_right_mobile' => null, 'card_margin_right_unit' => 'px', 'card_margin_bottom' => null, 'card_margin_bottom_tablet' => null, 'card_margin_bottom_mobile' => null, 'card_margin_bottom_unit' => 'px', 'card_margin_left' => null, 'card_margin_left_tablet' => null, 'card_margin_left_mobile' => null, 'card_margin_left_unit' => 'px', 'card_margin_linked' => true,
            'shadow'        => true,
            // The cards' shadow (2026-11-23, user's request: as WP Swiper's / WP Banner's): as it was drawn before.
            'card_shadow_x' => 0, 'card_shadow_y' => 14, 'card_shadow_blur' => 28, 'card_shadow_spread' => -16, 'card_shadow_color' => 'rgba(15,23,42,0.45)',
            'icon_style'    => 'white',
            'marker'        => 'ring',
            'line'          => 'dotted',
            'arrow'         => true,
            'alternate'     => false,
            'show_label'    => true,
            'label_word'    => 'Step',
            'number_format' => '01',
            'title_tag'     => 'h3',
            'color_from'    => '#17a2b8',
            'color_to'      => '#34466b',
            'title_color'   => '',
            'text_color'    => '',
            'label_color'   => '',
            'line_color'    => '',
            // Settings → Custom (2026-11-24, user's request): Padding/Margin behind its switch (as WP Banner's), the block's own id and classes.
            'use_padding'   => false,
        ) + self::space_defaults() + array('css_id' => '', 'css_class' => '');
        foreach (self::DEVICE as $key => $default) {
            $d[$key] = $default;
            $d[$key . '_tablet'] = null;
            $d[$key . '_mobile'] = null;
        }
        return $d;
    }

    public static function step_defaults() {
        // text_size (+ _tablet, _mobile), text_weight, text_color (2026-11-23, user's request, under the Text editor as WP Swiper's):
        // the step's own; null / '' = as the other steps (Sizes, Colors).
        // The title's own (2026-11-23, user's request: a tab of its own with the editor): title_align, title_size (+ _tablet, _mobile),
        // title_weight, title_color, title_tag; '' / null = as the other steps (the set's title_tag, its usual size and color).
        return array('title' => '', 'title_align' => '', 'title_size' => null, 'title_size_tablet' => null, 'title_size_mobile' => null, 'title_weight' => '', 'title_color' => '', 'title_tag' => '',
            'text' => '', 'text_size' => null, 'text_size_tablet' => null, 'text_size_mobile' => null, 'text_weight' => '', 'text_color' => '',
            'aside_title' => '', 'aside_text' => '',
            // The side title's tag and the side title's / text's font size, weight and color per device (2026-11-24, user's request):
            // '' / null = the larger screen's, else the usual (the title's size × .95, 600, the step's color; the text's × .92, the grey).
            'aside_title_tag' => '', 'aside_title_size' => null, 'aside_title_size_tablet' => null, 'aside_title_size_mobile' => null, 'aside_title_weight' => '', 'aside_title_weight_tablet' => '', 'aside_title_weight_mobile' => '', 'aside_title_color' => '', 'aside_title_color_tablet' => '', 'aside_title_color_mobile' => '',
            'aside_text_size' => null, 'aside_text_size_tablet' => null, 'aside_text_size_mobile' => null, 'aside_text_weight' => '', 'aside_text_weight_tablet' => '', 'aside_text_weight_mobile' => '', 'aside_text_color' => '', 'aside_text_color_tablet' => '', 'aside_text_color_mobile' => '',
            'icon' => '', 'image' => 0, 'color' => '',
            // The icon's own (2026-11-23, user's request: everything about the icon in the step's Icon block): its style (white circle,
            // color circle, icon only), circle size per device and icon color; '' / null = the set's (no longer in Settings).
            'icon_style' => '', 'icon_size' => null, 'icon_size_tablet' => null, 'icon_size_mobile' => null, 'icon_color' => '',
            // icon_scale: the icon in its circle, % of it (null: 44%, an image 52%, icon only 50% of Size). 2026-11-23, user's request.
            // The circle (2026-11-23, user's idea, instead of the Style select): icon_shape filled / outline / none and icon_bg (its fill
            // or ring); '' = as the set's old style gives (icon_circle()). icon_style stays, read for steps saved with one.
            // What it shows (2026-11-23, user's request: Icon | Image first in the block): '' = its image if it has one, else its icon.
            'media' => '',
            // The whole step as a link (2026-11-24, user's request: its window's Link tab).
            'link' => '', 'link_new_tab' => false,
            'icon_shape' => '', 'icon_bg' => '',
            // The circle's shadow (2026-11-23, user's request: in the step): null = the set's Shadow (which now is the cards' only).
            'icon_shadow' => null,
            // Its offsets, blur, spread and color: null / '' = as it was drawn before (ICON_SHADOW).
            'icon_shadow_x' => null, 'icon_shadow_y' => null, 'icon_shadow_blur' => null, 'icon_shadow_spread' => null, 'icon_shadow_color' => '',
            // Its card's shadow (2026-11-25, user's request: as the cards' in the Settings): null = the cards' switch; its parts null / ''
            // = the cards'.
            // Its image's box (2026-11-25, user's request: as Woodmart's Image element): image_size '' (a square: Size, the image inside at
            // Content size) | ratio (Size wide, image_ratio* tall, e.g. 16/9) | custom (Size wide, image_height* px); the image filling it by
            // image_fit (cover | contain | fill | none) at image_position; per device: '' / null = the larger screen's.
            'image_size' => '', 'image_ratio' => '', 'image_ratio_tablet' => '', 'image_ratio_mobile' => '', 'image_height' => null, 'image_height_tablet' => null, 'image_height_mobile' => null, 'image_fit' => '', 'image_position' => '',
            'shadow' => null, 'shadow_x' => null, 'shadow_y' => null, 'shadow_blur' => null, 'shadow_spread' => null, 'shadow_color' => '',
            // The circle's corners (2026-11-23, user's request: WP Banner's Radius box): per corner and device, null = the larger
            // screen's, else 50 % (a circle); each corner's unit px or %, one for every device; linked: set together.
            'icon_radius_tl' => null, 'icon_radius_tl_tablet' => null, 'icon_radius_tl_mobile' => null, 'icon_radius_tl_unit' => '%', 'icon_radius_tr' => null, 'icon_radius_tr_tablet' => null, 'icon_radius_tr_mobile' => null, 'icon_radius_tr_unit' => '%', 'icon_radius_br' => null, 'icon_radius_br_tablet' => null, 'icon_radius_br_mobile' => null, 'icon_radius_br_unit' => '%', 'icon_radius_bl' => null, 'icon_radius_bl_tablet' => null, 'icon_radius_bl_mobile' => null, 'icon_radius_bl_unit' => '%', 'icon_radius_linked' => true,
            // Behind its switch (2026-11-23, user's request, as WP Banner's Radius): on, the corners (none of its own: a circle); off
            // (2026-11-24, user's choice), no rounding: a square, the corners kept.
            'icon_use_radius' => true,
            'icon_scale' => null, 'icon_scale_tablet' => null, 'icon_scale_mobile' => null)
            // Its Padding (inside its card; Wave: its texts) and Margin (around it), per side and device (2026-11-24, user's request:
            // the window's Style tab, WP Banner's widget): null = the larger screen's, else the design's own.
            + self::step_space_defaults();
    }
    public static function step_space_defaults() {
        $out = array();
        foreach (array('padding', 'margin') as $kind) {
            foreach (self::SIDES as $side) { $out[$kind . '_' . $side] = null; $out[$kind . '_' . $side . '_tablet'] = null; $out[$kind . '_' . $side . '_mobile'] = null; $out[$kind . '_' . $side . '_unit'] = 'px'; }
            $out[$kind . '_linked'] = true;
        }
        // Its card's Border and Radius (2026-11-25, user's request: the same box as the cards'): none = the cards'.
        foreach (self::SIDES as $side) { $out['border_' . $side] = null; $out['border_' . $side . '_tablet'] = null; $out['border_' . $side . '_mobile'] = null; }
        $out['border_linked'] = true; $out['border_style'] = ''; $out['border_color'] = '';
        foreach (array('tl', 'tr', 'br', 'bl') as $c) { $out['radius_' . $c] = null; $out['radius_' . $c . '_tablet'] = null; $out['radius_' . $c . '_mobile'] = null; $out['radius_' . $c . '_unit'] = 'px'; }
        $out['radius_linked'] = true;
        return $out;
    }
    // A step's Padding or Margin side on each device: ['desktop' => '12px'|null, 'tablet' => …, 'mobile' => …] (the larger screen's when
    // it has none; null: the design's own).
    public static function step_side($step, $key) {
        $unit = $step[$key . '_unit'] ?? 'px';
        $out = array();
        $last = null;
        foreach (array('desktop' => '', 'tablet' => '_tablet', 'mobile' => '_mobile') as $device => $suffix) {
            $v = $step[$key . $suffix] ?? null;
            $last = $v === null ? $last : (int) $v;
            $out[$device] = $last === null ? null : ($last ? $last . $unit : '0');
        }
        return $out;
    }

    // A new set's steps: something to look at in the preview right away.
    public static function sample_steps() {
        $steps = array();
        foreach (array(array('Plan', 'lightbulb'), array('Prepare', 'clock'), array('Agree', 'team'), array('Reach the goal', 'target')) as $s) {
            $steps[] = array_merge(self::step_defaults(), array('title' => $s[0], 'icon' => $s[1],
                'text' => 'A short description of this step: what happens and what comes out of it.'));
        }
        return $steps;
    }

    // The saved settings of a post (the defaults for a post without them).
    public static function get($id) {
        $saved = get_post_meta((int) $id, self::META, true);
        return is_array($saved) ? self::sanitize($saved) : self::defaults();
    }

    // "#rrggbb" or "rgba(r,g,b,a)", lowercase; '' for anything else.
    public static function color($value) {
        $value = strtolower(preg_replace('/\s+/', '', (string) $value));
        if (preg_match('/^#([0-9a-f]{3})$/', $value, $m)) { $value = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2]; }
        if (preg_match('/^#[0-9a-f]{6}$/', $value)) { return $value; }
        if (preg_match('/^rgba\((\d{1,3}),(\d{1,3}),(\d{1,3}),(0|1|0?\.\d+|1\.0+)\)$/', $value, $m) && max($m[1], $m[2], $m[3]) <= 255) {
            return 'rgba(' . (int) $m[1] . ',' . (int) $m[2] . ',' . (int) $m[3] . ',' . (float) $m[4] . ')';
        }
        return '';
    }

    // Padding and Margin keys: 0 on every side, px, linked; tablet / mobile null (the larger screen's).
    public static function space_defaults() {
        $out = array();
        foreach (array('padding', 'margin') as $kind) {
            $out[$kind . '_linked'] = true;
            foreach (self::SIDES as $side) { $out[$kind . '_' . $side] = 0; $out[$kind . '_' . $side . '_unit'] = 'px'; $out[$kind . '_' . $side . '_tablet'] = null; $out[$kind . '_' . $side . '_mobile'] = null; }
        }
        return $out;
    }
    // The set's Padding or Margin per device: ['desktop' => 'T R B L', ...] with units; '' with the switch off or all 0.
    public static function spaces($s, $kind) {
        if (empty($s['use_padding'])) { return ''; }
        $out = array();
        $any = false;
        foreach (self::SIDES as $side) {
            $key = $kind . '_' . $side;
            list($desktop, $tablet, $mobile) = self::sizes($s, $key);
            $unit = $s[$key . '_unit'] ?? 'px';
            foreach (array('desktop' => $desktop, 'tablet' => $tablet, 'mobile' => $mobile) as $device => $v) { $any = $any || $v > 0; $out[$device][] = ((int) $v) . ($v ? $unit : ''); }
        }
        if (!$any) { return ''; }
        return array_map(function ($sides) { return implode(' ', $sides); }, $out);
    }
    // The icon's place, alignment and gap on each device: ['desktop' => [pos, align, gap], ...]; pos '' = the design's own (then no
    // alignment), align '' = the usual one, gap null = the design's own.
    public static function icon_layout($s) {
        $out = array();
        $pos = $s['icon_position'] ?? '';
        $align = $s['icon_align'] ?? '';
        $gaps = self::sizes($s, 'icon_gap');
        foreach (array('desktop' => '', 'tablet' => '_tablet', 'mobile' => '_mobile') as $device => $suffix) {
            if ($suffix !== '') {
                $pos = $s['icon_position' . $suffix] ?? $pos;
                $align = $s['icon_align' . $suffix] ?? $align;
            }
            $p = $pos === 'auto' ? '' : $pos;
            $out[$device] = array($p, $p === '' ? '' : $align, $gaps[array_search($device, array('desktop', 'tablet', 'mobile'), true)]);
        }
        return $out;
    }
    // The cards' Border widths per device ('T R B L' in px), '' while all are 0.
    public static function card_borders($s) {
        if (!self::any_set($s, 'card_border_', self::SIDES)) { return ''; }
        $out = array('desktop' => array(), 'tablet' => array(), 'mobile' => array());
        foreach (self::SIDES as $side) {
            list($d, $t, $m) = self::sizes($s, 'card_border_' . $side);
            foreach (array('desktop' => $d, 'tablet' => $t, 'mobile' => $m) as $device => $v) { $out[$device][] = $v ? ((int) $v) . 'px' : '0'; }
        }
        return array_map(function ($w) { return implode(' ', $w); }, $out);
    }
    // Is any of these parts above 0 on some device?
    public static function any_set($s, $prefix, $parts) {
        foreach ($parts as $p) { foreach (array('', '_tablet', '_mobile') as $sfx) { if (!empty($s[$prefix . $p . $sfx])) { return true; } } }
        return false;
    }
    // The cards' corners per device ('tl tr br bl' with units), '' while all are 0 (square).
    public static function card_radii($s) {
        if (!self::any_set($s, 'card_radius_', array('tl', 'tr', 'br', 'bl'))) { return ''; }
        $out = array('desktop' => array(), 'tablet' => array(), 'mobile' => array());
        foreach (array('tl', 'tr', 'br', 'bl') as $c) {
            $key = 'card_radius_' . $c;
            list($d, $t, $m) = self::sizes($s, $key);
            $u = $s[$key . '_unit'] ?? 'px';
            foreach (array('desktop' => $d, 'tablet' => $t, 'mobile' => $m) as $device => $v) { $out[$device][] = $v ? ((int) $v) . $u : '0'; }
        }
        return array_map(function ($c) { return implode(' ', $c); }, $out);
    }
    // A step's card border widths or corners per device ('T R B L' / 'tl tr br bl'): its own (or its larger screen's), else the cards';
    // '' when the step has none of its own.
    public static function step_box($s, $step, $kind) {
        $parts = $kind === 'border' ? self::SIDES : array('tl', 'tr', 'br', 'bl');
        if (!self::has_own($step, $kind . '_', $parts) && !($kind === 'border' && (!empty($step['border_style']) || !empty($step['border_color'])))) { return ''; }
        $out = array('desktop' => array(), 'tablet' => array(), 'mobile' => array());
        foreach ($parts as $p) {
            $own = self::step_side($step, $kind . '_' . $p);
            list($d, $t, $m) = self::sizes($s, 'card_' . $kind . '_' . $p);
            $u = $kind === 'border' ? 'px' : ($s['card_radius_' . $p . '_unit'] ?? 'px');
            foreach (array('desktop' => $d, 'tablet' => $t, 'mobile' => $m) as $device => $v) { $out[$device][] = $own[$device] !== null ? $own[$device] : ($v ? ((int) $v) . $u : '0'); }
        }
        return array_map(function ($x) { return implode(' ', $x); }, $out);
    }
    public static function has_own($step, $prefix, $parts) {
        foreach ($parts as $p) { foreach (array('', '_tablet', '_mobile') as $sfx) { if (isset($step[$prefix . $p . $sfx]) && $step[$prefix . $p . $sfx] !== null) { return true; } } }
        return false;
    }
    // An id: a letter first, then letters, digits, - and _ (a leading # dropped); else none.
    public static function css_id($value) {
        $value = is_scalar($value) ? ltrim(trim((string) $value), '#') : '';
        return preg_match('/^[A-Za-z][\w-]{0,79}$/', $value) ? $value : '';
    }
    // Class names, separated by spaces or commas, each cleaned (a leading . dropped), no repeats.
    public static function css_classes($value) {
        $classes = array();
        foreach (preg_split('/[\s,]+/', is_scalar($value) ? (string) $value : '') as $class) {
            $class = preg_replace('/[^A-Za-z0-9_-]/', '', ltrim($class, '.'));
            if ($class !== '' && !preg_match('/^[0-9-]/', $class) && !in_array($class, $classes, true)) { $classes[] = $class; }
        }
        return substr(implode(' ', $classes), 0, 200);
    }

    private static function number($value, $key, $fallback) {
        if (!is_numeric($value)) { return $fallback; }
        list($min, $max) = self::LIMITS[$key];
        return max($min, min($max, (int) round((float) $value)));
    }

    public static function sanitize($data) {
        $d = self::defaults();
        $data = is_array($data) ? $data : array();
        $s = array();
        // design: named layout until 2026-11-24 (the user's word: the plugin is installed nowhere yet); a set saved before reads it once.
        if (!isset($data['design']) && isset($data['layout'])) { $data['design'] = $data['layout']; }
        $s['design'] = isset($data['design']) && WP_Steps_Designs::exists($data['design']) ? $data['design'] : $d['design'];
        foreach (self::CHOICES as $key => $list) {
            $s[$key] = isset($data[$key]) && in_array((string) $data[$key], $list, true) ? (string) $data[$key] : $d[$key];
        }
        foreach (self::SWITCHES as $key) {
            $s[$key] = array_key_exists($key, $data) ? !empty($data[$key]) : $d[$key];
        }
        foreach (self::COLORS as $key) {
            $s[$key] = array_key_exists($key, $data) ? self::color($data[$key]) : $d[$key];
        }
        $s['card_shadow_color'] = array_key_exists('card_shadow_color', $data) ? self::color($data['card_shadow_color']) : $d['card_shadow_color'];
        if ($s['card_shadow_color'] === '') { $s['card_shadow_color'] = $d['card_shadow_color']; }
        // The first and last step's colors are always there.
        foreach (array('color_from', 'color_to') as $key) { if ($s[$key] === '') { $s[$key] = $d[$key]; } }
        foreach (array('card_shadow_x', 'card_shadow_y', 'card_shadow_blur', 'card_shadow_spread') as $key) {
            $s[$key] = self::number($data[$key] ?? null, $key, $d[$key]);
        }
        foreach (self::DEVICE as $key => $default) {
            $s[$key] = self::number($data[$key] ?? null, $key, $default);
            foreach (array('tablet', 'mobile') as $device) {
                $value = $data[$key . '_' . $device] ?? null;
                $s[$key . '_' . $device] = ($value === null || $value === '') ? null : self::number($value, $key, null);
            }
        }
        $word = isset($data['label_word']) && is_scalar($data['label_word']) ? sanitize_text_field((string) $data['label_word']) : $d['label_word'];
        $s['label_word'] = function_exists('mb_substr') ? mb_substr($word, 0, 30) : substr($word, 0, 30);
        // Padding / Margin (each side in its unit, within its largest; Desktop 0 by default, tablet / mobile null = the larger screen's).
        foreach (array('padding', 'margin') as $kind) {
            $s[$kind . '_linked'] = array_key_exists($kind . '_linked', $data) ? (!empty($data[$kind . '_linked']) && $data[$kind . '_linked'] !== 'false') : true;
            foreach (self::SIDES as $side) {
                $key = $kind . '_' . $side;
                $unit = isset($data[$key . '_unit']) && isset(self::SPACE_MAX[(string) $data[$key . '_unit']]) ? (string) $data[$key . '_unit'] : 'px';
                $s[$key] = self::own_number($data[$key] ?? null, 0, self::SPACE_MAX[$unit]) ?? 0;
                $s[$key . '_unit'] = $unit;
                $s[$key . '_tablet'] = self::own_number($data[$key . '_tablet'] ?? null, 0, self::SPACE_MAX[$unit]);
                $s[$key . '_mobile'] = self::own_number($data[$key . '_mobile'] ?? null, 0, self::SPACE_MAX[$unit]);
            }
        }
        // The icon's place, alignment and gap on tablet / phone (and the gap on Desktop): null = the larger screen's.
        foreach (array('tablet', 'mobile') as $device) {
            $v = $data['icon_position_' . $device] ?? null;
            $s['icon_position_' . $device] = in_array($v, array('auto', 'left', 'top', 'right', 'bottom'), true) ? $v : null;
            $v = $data['icon_align_' . $device] ?? null;
            $s['icon_align_' . $device] = in_array($v, array('start', 'center', 'end'), true) ? $v : null;
        }
        // The cards' corners: each in its unit, within its largest; Desktop always a value (emptied 0), tablet / phone null = the larger screen's.
        foreach (array('tl', 'tr', 'br', 'bl') as $c) {
            $key = 'card_radius_' . $c;
            $unit = isset($data[$key . '_unit']) && isset(self::RADIUS_MAX[(string) $data[$key . '_unit']]) ? (string) $data[$key . '_unit'] : 'px';
            $s[$key . '_unit'] = $unit;
            $s[$key] = array_key_exists($key, $data) ? (self::own_number($data[$key], 0, self::RADIUS_MAX[$unit]) ?? 0) : $d[$key];
            $s[$key . '_tablet'] = self::own_number($data[$key . '_tablet'] ?? null, 0, self::RADIUS_MAX[$unit]);
            $s[$key . '_mobile'] = self::own_number($data[$key . '_mobile'] ?? null, 0, self::RADIUS_MAX[$unit]);
        }
        // The cards' Border: each side 0–20px, Desktop always a value (emptied 0), tablet / phone null = the larger screen's; its color.
        foreach (self::SIDES as $side) {
            $key = 'card_border_' . $side;
            $s[$key] = array_key_exists($key, $data) ? (self::own_number($data[$key], 0, 20) ?? 0) : $d[$key];
            $s[$key . '_tablet'] = self::own_number($data[$key . '_tablet'] ?? null, 0, 20);
            $s[$key . '_mobile'] = self::own_number($data[$key . '_mobile'] ?? null, 0, 20);
        }
        $s['card_border_linked'] = !array_key_exists('card_border_linked', $data) || (!empty($data['card_border_linked']) && $data['card_border_linked'] !== 'false');
        $s['card_border_color'] = array_key_exists('card_border_color', $data) ? self::color($data['card_border_color']) : '';
        $s['card_radius_linked'] = !array_key_exists('card_radius_linked', $data) || (!empty($data['card_radius_linked']) && $data['card_radius_linked'] !== 'false');
        // A set saved while Border / Radius had switches (2026-11-24): switched off, none.
        foreach (array('card_use_border' => array('card_border_', self::SIDES), 'card_use_radius' => array('card_radius_', array('tl', 'tr', 'br', 'bl'))) as $switch => list($prefix, $parts)) {
            if (array_key_exists($switch, $data) && (empty($data[$switch]) || $data[$switch] === 'false')) { foreach ($parts as $p) { $s[$prefix . $p] = 0; $s[$prefix . $p . '_tablet'] = null; $s[$prefix . $p . '_mobile'] = null; } }
        }
        // The cards' padding / margin (2026-11-25): each side in its unit, none = the design's own.
        foreach (array('padding', 'margin') as $kind) {
            foreach (self::SIDES as $side) {
                $key = 'card_' . $kind . '_' . $side;
                $unit = isset($data[$key . '_unit']) && isset(self::SPACE_MAX[(string) $data[$key . '_unit']]) ? (string) $data[$key . '_unit'] : 'px';
                $s[$key . '_unit'] = $unit;
                foreach (array('', '_tablet', '_mobile') as $sfx) { $s[$key . $sfx] = self::own_number($data[$key . $sfx] ?? null, 0, self::SPACE_MAX[$unit]); }
            }
            $s['card_' . $kind . '_linked'] = !array_key_exists('card_' . $kind . '_linked', $data) || (!empty($data['card_' . $kind . '_linked']) && $data['card_' . $kind . '_linked'] !== 'false');
        }
        $s['css_id'] = self::css_id($data['css_id'] ?? '');
        $s['css_class'] = self::css_classes($data['css_class'] ?? '');
        $s['steps'] = array();
        if (isset($data['steps']) && is_array($data['steps'])) {
            foreach (array_slice(array_values($data['steps']), 0, self::MAX_STEPS) as $step) {
                $s['steps'][] = self::step($step);
            }
        } else {
            $s['steps'] = $d['steps'];
        }
        return $s;
    }

    public static function step($data) {
        $data = is_array($data) ? $data : array();
        $text = function ($key) use ($data) { return isset($data[$key]) && is_scalar($data[$key]) ? (string) $data[$key] : ''; };
        $title = self::rich_title($text('title'));
        $out = array(
            'title'       => $title[0],
            'title_align' => $title[1] !== null ? $title[1] : (in_array($text('title_align'), self::ALIGNS, true) ? $text('title_align') : ''),
            'title_size'  => self::own_size($data['title_size'] ?? null, 'title_size'),
            'title_size_tablet' => self::own_size($data['title_size_tablet'] ?? null, 'title_size'),
            'title_size_mobile' => self::own_size($data['title_size_mobile'] ?? null, 'title_size'),
            'title_weight' => in_array($text('title_weight'), self::WEIGHTS, true) ? $text('title_weight') : '',
            'title_color' => self::color($text('title_color')),
            'title_tag'   => in_array($text('title_tag'), self::TITLE_ELEMENTS, true) ? $text('title_tag') : '',
            'text'        => self::rich_text($text('text')),
            'text_size'   => self::own_size($data['text_size'] ?? null),
            'text_size_tablet' => self::own_size($data['text_size_tablet'] ?? null),
            'text_size_mobile' => self::own_size($data['text_size_mobile'] ?? null),
            'text_weight' => in_array($text('text_weight'), self::WEIGHTS, true) ? $text('text_weight') : '',
            'text_color'  => self::color($text('text_color')),
            // Side text in WordPress's editors (2026-11-24, user's request: as Title and Text): its title inline words, its text a text.
            'aside_title' => self::rich_title($text('aside_title'))[0],
            'aside_text'  => self::rich_text($text('aside_text')),
            'icon'        => WP_Steps_Icons::exists($text('icon')) ? $text('icon') : '',
            'image'       => isset($data['image']) && is_numeric($data['image']) ? max(0, (int) $data['image']) : 0,
            'color'       => self::color($text('color')),
            'icon_style'  => in_array($text('icon_style'), self::ICON_STYLES, true) ? $text('icon_style') : '',
            'icon_size'   => self::own_size($data['icon_size'] ?? null, 'icon'),
            'icon_size_tablet' => self::own_size($data['icon_size_tablet'] ?? null, 'icon'),
            'icon_size_mobile' => self::own_size($data['icon_size_mobile'] ?? null, 'icon'),
            'icon_color'  => self::color($text('icon_color')),
            'media'       => in_array($text('media'), array('', 'icon', 'image'), true) ? $text('media') : '',
            'link'        => self::link($text('link')),
            'link_new_tab' => !empty($data['link_new_tab']) && $data['link_new_tab'] !== 'false',
            'icon_shape'  => in_array($text('icon_shape'), self::ICON_SHAPES, true) ? $text('icon_shape') : '',
            'icon_bg'     => self::color($text('icon_bg')),
            'icon_shadow' => !isset($data['icon_shadow']) || $data['icon_shadow'] === '' ? null : (!empty($data['icon_shadow']) && $data['icon_shadow'] !== 'false'),
            'icon_shadow_x' => self::own_number($data['icon_shadow_x'] ?? null, -100, 100),
            'icon_shadow_y' => self::own_number($data['icon_shadow_y'] ?? null, -100, 100),
            'icon_shadow_blur' => self::own_number($data['icon_shadow_blur'] ?? null, 0, 100),
            'icon_shadow_spread' => self::own_number($data['icon_shadow_spread'] ?? null, -50, 50),
            'icon_shadow_color' => self::color($text('icon_shadow_color')),
            'image_size' => in_array($text('image_size'), array('', 'ratio', 'custom'), true) ? $text('image_size') : '',
            'image_ratio' => self::ratio($text('image_ratio')), 'image_ratio_tablet' => self::ratio($text('image_ratio_tablet')), 'image_ratio_mobile' => self::ratio($text('image_ratio_mobile')),
            'image_height' => self::own_number($data['image_height'] ?? null, 1, 2000), 'image_height_tablet' => self::own_number($data['image_height_tablet'] ?? null, 1, 2000), 'image_height_mobile' => self::own_number($data['image_height_mobile'] ?? null, 1, 2000),
            'image_fit' => in_array($text('image_fit'), array('', 'cover', 'contain', 'fill', 'none'), true) ? $text('image_fit') : '',
            'image_position' => in_array($text('image_position'), array('', 'left top', 'center top', 'right top', 'left center', 'center center', 'right center', 'left bottom', 'center bottom', 'right bottom'), true) ? $text('image_position') : '',
            'shadow' => !isset($data['shadow']) || $data['shadow'] === '' ? null : (!empty($data['shadow']) && $data['shadow'] !== 'false'),
            'shadow_x' => self::own_number($data['shadow_x'] ?? null, -100, 100),
            'shadow_y' => self::own_number($data['shadow_y'] ?? null, -100, 100),
            'shadow_blur' => self::own_number($data['shadow_blur'] ?? null, 0, 100),
            'shadow_spread' => self::own_number($data['shadow_spread'] ?? null, -50, 50),
            'shadow_color' => self::color($text('shadow_color')),
            'icon_scale'  => self::own_number($data['icon_scale'] ?? null, 20, 100),
            'icon_radius_tl_unit' => ($ru = in_array($text('icon_radius_tl_unit'), array('px', '%'), true) ? $text('icon_radius_tl_unit') : '%'),
            'icon_radius_tl' => self::own_number($data['icon_radius_tl'] ?? null, 0, self::RADIUS_MAX[$ru]),
            'icon_radius_tl_tablet' => self::own_number($data['icon_radius_tl_tablet'] ?? null, 0, self::RADIUS_MAX[$ru]),
            'icon_radius_tl_mobile' => self::own_number($data['icon_radius_tl_mobile'] ?? null, 0, self::RADIUS_MAX[$ru]),
            'icon_radius_tr_unit' => ($ru = in_array($text('icon_radius_tr_unit'), array('px', '%'), true) ? $text('icon_radius_tr_unit') : '%'),
            'icon_radius_tr' => self::own_number($data['icon_radius_tr'] ?? null, 0, self::RADIUS_MAX[$ru]),
            'icon_radius_tr_tablet' => self::own_number($data['icon_radius_tr_tablet'] ?? null, 0, self::RADIUS_MAX[$ru]),
            'icon_radius_tr_mobile' => self::own_number($data['icon_radius_tr_mobile'] ?? null, 0, self::RADIUS_MAX[$ru]),
            'icon_radius_br_unit' => ($ru = in_array($text('icon_radius_br_unit'), array('px', '%'), true) ? $text('icon_radius_br_unit') : '%'),
            'icon_radius_br' => self::own_number($data['icon_radius_br'] ?? null, 0, self::RADIUS_MAX[$ru]),
            'icon_radius_br_tablet' => self::own_number($data['icon_radius_br_tablet'] ?? null, 0, self::RADIUS_MAX[$ru]),
            'icon_radius_br_mobile' => self::own_number($data['icon_radius_br_mobile'] ?? null, 0, self::RADIUS_MAX[$ru]),
            'icon_radius_bl_unit' => ($ru = in_array($text('icon_radius_bl_unit'), array('px', '%'), true) ? $text('icon_radius_bl_unit') : '%'),
            'icon_radius_bl' => self::own_number($data['icon_radius_bl'] ?? null, 0, self::RADIUS_MAX[$ru]),
            'icon_radius_bl_tablet' => self::own_number($data['icon_radius_bl_tablet'] ?? null, 0, self::RADIUS_MAX[$ru]),
            'icon_radius_bl_mobile' => self::own_number($data['icon_radius_bl_mobile'] ?? null, 0, self::RADIUS_MAX[$ru]),
            // A step saved before the switch: on where it has a corner of its own.
            // On by default and for steps saved before it (2026-11-24: off is now no rounding, a square, as WP Banner's Radius).
            'icon_use_radius' => !array_key_exists('icon_use_radius', $data) || (!empty($data['icon_use_radius']) && $data['icon_use_radius'] !== 'false'),
            'icon_radius_linked' => !array_key_exists('icon_radius_linked', $data) || (!empty($data['icon_radius_linked']) && $data['icon_radius_linked'] !== 'false'),
            'icon_scale_tablet' => self::own_number($data['icon_scale_tablet'] ?? null, 20, 100),
            'icon_scale_mobile' => self::own_number($data['icon_scale_mobile'] ?? null, 20, 100),
        );
        // Padding / Margin: each side in its unit, within its largest; empty: none (the design's own).
        foreach (array('padding', 'margin') as $kind) {
            foreach (self::SIDES as $side) {
                $key = $kind . '_' . $side;
                $unit = isset($data[$key . '_unit']) && isset(self::SPACE_MAX[(string) $data[$key . '_unit']]) ? (string) $data[$key . '_unit'] : 'px';
                $out[$key . '_unit'] = $unit;
                foreach (array('', '_tablet', '_mobile') as $suffix) { $out[$key . $suffix] = self::own_number($data[$key . $suffix] ?? null, 0, self::SPACE_MAX[$unit]); }
            }
            $out[$kind . '_linked'] = !array_key_exists($kind . '_linked', $data) || (!empty($data[$kind . '_linked']) && $data[$kind . '_linked'] !== 'false');
        }
        // Its card's Border (0–20px) and Radius (px | %) per side / corner and device, its line and color; none = the cards'.
        foreach (self::SIDES as $side) { foreach (array('', '_tablet', '_mobile') as $sfx) { $out['border_' . $side . $sfx] = self::own_number($data['border_' . $side . $sfx] ?? null, 0, 20); } }
        $out['border_linked'] = !array_key_exists('border_linked', $data) || (!empty($data['border_linked']) && $data['border_linked'] !== 'false');
        $out['border_style'] = in_array($text('border_style'), self::CHOICES['card_border_style'], true) ? $text('border_style') : '';
        $out['border_color'] = self::color($text('border_color'));
        foreach (array('tl', 'tr', 'br', 'bl') as $c) {
            $unit = isset($data['radius_' . $c . '_unit']) && isset(self::RADIUS_MAX[(string) $data['radius_' . $c . '_unit']]) ? (string) $data['radius_' . $c . '_unit'] : 'px';
            $out['radius_' . $c . '_unit'] = $unit;
            foreach (array('', '_tablet', '_mobile') as $sfx) { $out['radius_' . $c . $sfx] = self::own_number($data['radius_' . $c . $sfx] ?? null, 0, self::RADIUS_MAX[$unit]); }
        }
        $out['radius_linked'] = !array_key_exists('radius_linked', $data) || (!empty($data['radius_linked']) && $data['radius_linked'] !== 'false');
        // The side text's style (2026-11-24): tag; size, weight and color per device.
        $out['aside_title_tag'] = in_array($text('aside_title_tag'), self::TITLE_ELEMENTS, true) ? $text('aside_title_tag') : '';
        foreach (array('aside_title' => 'title_size', 'aside_text' => 'text_size') as $k => $limit) {
            foreach (array('', '_tablet', '_mobile') as $sfx) {
                $out[$k . '_size' . $sfx] = self::own_size($data[$k . '_size' . $sfx] ?? null, $limit);
                $out[$k . '_weight' . $sfx] = in_array($text($k . '_weight' . $sfx), self::WEIGHTS, true) ? $text($k . '_weight' . $sfx) : '';
                $out[$k . '_color' . $sfx] = self::color($text($k . '_color' . $sfx));
            }
        }
        // In step_defaults()' order (some keys are worked out after others, e.g. a corner's unit before its values).
        return array_replace(self::step_defaults(), $out);
    }

    // A step's own text size in px (the Text size's limits), null without one.
    public static function own_size($value, $key = 'text_size') {
        if ($value === null || $value === '' || !is_numeric($value)) { return null; }
        list($min, $max) = self::LIMITS[$key];
        return max($min, min($max, (int) round((float) $value)));
    }
    // A step's own whole number within [min, max], null without one.
    public static function own_number($value, $min, $max) {
        if ($value === null || $value === '' || !is_numeric($value)) { return null; }
        return max($min, min($max, (int) round((float) $value)));
    }
    // A per-device size on each device: [desktop, tablet, phone] (null = the larger screen's).
    public static function sizes($s, $key) {
        $desktop = $s[$key];
        $tablet = $s[$key . '_tablet'] ?? null;
        $tablet = $tablet === null ? $desktop : $tablet;
        $mobile = $s[$key . '_mobile'] ?? null;
        return array($desktop, $tablet, $mobile === null ? $tablet : $mobile);
    }

    // Each step's color: its own, or its place between the first and the last color.
    public static function step_colors($s) {
        $n = count($s['steps']);
        $from = self::rgb($s['color_from']);
        $to = self::rgb($s['color_to']);
        $colors = array();
        foreach ($s['steps'] as $i => $step) {
            if ($step['color'] !== '') { $colors[] = $step['color']; continue; }
            $t = $n > 1 ? $i / ($n - 1) : 0;
            $mix = array();
            for ($c = 0; $c < 4; $c++) { $mix[] = $from[$c] + ($to[$c] - $from[$c]) * $t; }
            $colors[] = $mix[3] >= 1 ? sprintf('#%02x%02x%02x', round($mix[0]), round($mix[1]), round($mix[2]))
                : 'rgba(' . (int) round($mix[0]) . ',' . (int) round($mix[1]) . ',' . (int) round($mix[2]) . ',' . round($mix[3], 3) . ')';
        }
        return $colors;
    }

    // A color (as color() gives it) as [r, g, b, alpha 0–1].
    public static function rgb($color) {
        if (preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/', $color, $m)) { return array(hexdec($m[1]), hexdec($m[2]), hexdec($m[3]), 1); }
        if (preg_match('/^rgba\((\d+),(\d+),(\d+),([\d.]+)\)$/', $color, $m)) { return array((int) $m[1], (int) $m[2], (int) $m[3], (float) $m[4]); }
        return array(0, 0, 0, 1);
    }

    // A step's number as shown: "01" or "1".
    public static function number_text($s, $index) {
        return $s['number_format'] === '01' ? sprintf('%02d', $index + 1) : (string) ($index + 1);
    }
}
