<?php
/**
 * The data: one post (type wp_steps) per set of steps, its settings in one post meta.
 * Everything that is saved or previewed goes through sanitize().
 *
 * Per-device sizes, as in WP Swiper and WP Banner: the desktop value under the key itself,
 * tablet and phone under key_tablet / key_mobile; null there = the larger screen's value.
 */
defined('ABSPATH') || exit;

require_once __DIR__ . '/settings/trait-wp-steps-settings-text.php';
require_once __DIR__ . '/settings/trait-wp-steps-settings-icon.php';
require_once __DIR__ . '/settings/trait-wp-steps-settings-defaults.php';
require_once __DIR__ . '/settings/trait-wp-steps-settings-box.php';
require_once __DIR__ . '/settings/trait-wp-steps-settings-sanitize.php';

class WP_Steps_Settings {
    // The methods, by subject, in includes/settings/ (2026-11-25: a smaller file to read).
    use WP_Steps_Settings_Text, WP_Steps_Settings_Icon, WP_Steps_Settings_Defaults, WP_Steps_Settings_Box, WP_Steps_Settings_Sanitize;

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
        'card_border_style' => array('solid', 'dashed', 'dotted', 'double'),
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
    );

    const SWITCHES = array('shadow', 'arrow', 'alternate', 'show_label', 'use_padding', 'show_icons');
    // Padding / Margin of the whole set (2026-11-24, user's request, as WP Banner's): each side's largest per unit.
    const SIDES = array('top', 'right', 'bottom', 'left');
    const SPACE_MAX = array('px' => 500, '%' => 50);

    // Colors; '' = automatic (see frontend.css).
    // (The set's title_color, text_color, title_tag, icon_style and card_style were removed 2026-11-25: no field in the Settings for
    // them any more; their values went into each step once. A step's own Tag / colors / circle are the way to change them.)
    const COLORS = array('color_from', 'color_to', 'label_color', 'line_color');
    // A step's title element when it has no Tag of its own.
    const TITLE_TAG = 'h3';

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
    const TITLE_TAGS = array('strong' => array(), 'b' => array(), 'em' => array(), 'i' => array(), 'u' => array(), 'br' => array(),
        'span' => array('style' => true), 'a' => array('href' => true, 'target' => true, 'rel' => true));
    const TITLE_ELEMENTS = array('', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div');
    const ALIGNS = array('', 'left', 'center', 'right');
    const ICON_STYLES = array('', 'white', 'color', 'plain');
    const ICON_SHAPES = array('', 'filled', 'outline', 'none');
    const RADIUS_MAX = array('px' => 200, '%' => 50);
    const CORNERS = array('tl', 'tr', 'br', 'bl');
    // A circle's shadow as drawn before it could be set: x, y, blur, spread, color.
    const ICON_SHADOW = array(0, 8, 18, -8, 'rgba(15,23,42,0.5)');

    // A switch as sent: missing = $default; false, '', 0 and 'false' = off.
    public static function flag($data, $key, $default = true) {
        return array_key_exists($key, $data) ? (!empty($data[$key]) && $data[$key] !== 'false') : $default;
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
