<?php
/**
 * WP_Steps_Settings, part: Cleaning what is saved or previewed: sanitize() for a set, step() for each step, and the numbers
 * and per-device sizes they read.
 * The class (includes/class-wp-steps-settings.php) holds the constants; called here as WP_Steps_Settings:: (the same class
 * as self::, and the editor can follow it).
 */
defined('ABSPATH') || exit;

trait WP_Steps_Settings_Sanitize {
    // A number within LIMITS[$key], else $fallback.
    private static function number($value, $key, $fallback) {
        $n = WP_Steps_Settings::own_size($value, $key);
        return $n === null ? $fallback : $n;
    }

    public static function sanitize($data) {
        $d = WP_Steps_Settings::defaults();
        $data = is_array($data) ? $data : array();
        $s = array();
        $s['design'] = isset($data['design']) && WP_Steps_Designs::exists($data['design']) ? $data['design'] : $d['design'];
        foreach (WP_Steps_Settings::CHOICES as $key => $list) {
            $s[$key] = isset($data[$key]) && in_array((string) $data[$key], $list, true) ? (string) $data[$key] : $d[$key];
        }
        foreach (WP_Steps_Settings::SWITCHES as $key) {
            $s[$key] = array_key_exists($key, $data) ? !empty($data[$key]) : $d[$key];
        }
        foreach (WP_Steps_Settings::COLORS as $key) {
            $s[$key] = array_key_exists($key, $data) ? WP_Steps_Settings::color($data[$key]) : $d[$key];
        }
        $s['card_shadow_color'] = array_key_exists('card_shadow_color', $data) ? WP_Steps_Settings::color($data['card_shadow_color']) : $d['card_shadow_color'];
        if ($s['card_shadow_color'] === '') { $s['card_shadow_color'] = $d['card_shadow_color']; }
        // The first and last step's colors are always there.
        foreach (array('color_from', 'color_to') as $key) { if ($s[$key] === '') { $s[$key] = $d[$key]; } }
        foreach (array('card_shadow_x', 'card_shadow_y', 'card_shadow_blur', 'card_shadow_spread') as $key) {
            $s[$key] = WP_Steps_Settings::number($data[$key] ?? null, $key, $d[$key]);
        }
        foreach (WP_Steps_Settings::DEVICE as $key => $default) {
            $s[$key] = WP_Steps_Settings::number($data[$key] ?? null, $key, $default);
            foreach (array('tablet', 'mobile') as $device) {
                $value = $data[$key . '_' . $device] ?? null;
                $s[$key . '_' . $device] = ($value === null || $value === '') ? null : WP_Steps_Settings::number($value, $key, null);
            }
        }
        $word = isset($data['label_word']) && is_scalar($data['label_word']) ? sanitize_text_field((string) $data['label_word']) : $d['label_word'];
        $s['label_word'] = function_exists('mb_substr') ? mb_substr($word, 0, 30) : substr($word, 0, 30);
        // Padding / Margin (each side in its unit, within its largest; Desktop 0 by default, tablet / mobile null = the larger screen's).
        foreach (array('padding', 'margin') as $kind) {
            $s[$kind . '_linked'] = WP_Steps_Settings::flag($data, $kind . '_linked');
            foreach (WP_Steps_Settings::SIDES as $side) {
                $key = $kind . '_' . $side;
                $unit = isset($data[$key . '_unit']) && isset(WP_Steps_Settings::SPACE_MAX[(string) $data[$key . '_unit']]) ? (string) $data[$key . '_unit'] : 'px';
                $s[$key] = WP_Steps_Settings::own_number($data[$key] ?? null, 0, WP_Steps_Settings::SPACE_MAX[$unit]) ?? 0;
                $s[$key . '_unit'] = $unit;
                $s[$key . '_tablet'] = WP_Steps_Settings::own_number($data[$key . '_tablet'] ?? null, 0, WP_Steps_Settings::SPACE_MAX[$unit]);
                $s[$key . '_mobile'] = WP_Steps_Settings::own_number($data[$key . '_mobile'] ?? null, 0, WP_Steps_Settings::SPACE_MAX[$unit]);
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
        foreach (WP_Steps_Settings::CORNERS as $c) {
            $key = 'card_radius_' . $c;
            $unit = isset($data[$key . '_unit']) && isset(WP_Steps_Settings::RADIUS_MAX[(string) $data[$key . '_unit']]) ? (string) $data[$key . '_unit'] : 'px';
            $s[$key . '_unit'] = $unit;
            $s[$key] = array_key_exists($key, $data) ? (WP_Steps_Settings::own_number($data[$key], 0, WP_Steps_Settings::RADIUS_MAX[$unit]) ?? 0) : $d[$key];
            $s[$key . '_tablet'] = WP_Steps_Settings::own_number($data[$key . '_tablet'] ?? null, 0, WP_Steps_Settings::RADIUS_MAX[$unit]);
            $s[$key . '_mobile'] = WP_Steps_Settings::own_number($data[$key . '_mobile'] ?? null, 0, WP_Steps_Settings::RADIUS_MAX[$unit]);
        }
        // The cards' Border: each side 0–20px, Desktop always a value (emptied 0), tablet / phone null = the larger screen's; its color.
        foreach (WP_Steps_Settings::SIDES as $side) {
            $key = 'card_border_' . $side;
            $s[$key] = array_key_exists($key, $data) ? (WP_Steps_Settings::own_number($data[$key], 0, 20) ?? 0) : $d[$key];
            $s[$key . '_tablet'] = WP_Steps_Settings::own_number($data[$key . '_tablet'] ?? null, 0, 20);
            $s[$key . '_mobile'] = WP_Steps_Settings::own_number($data[$key . '_mobile'] ?? null, 0, 20);
        }
        $s['card_border_linked'] = WP_Steps_Settings::flag($data, 'card_border_linked');
        $s['card_border_color'] = array_key_exists('card_border_color', $data) ? WP_Steps_Settings::color($data['card_border_color']) : '';
        $s['card_radius_linked'] = WP_Steps_Settings::flag($data, 'card_radius_linked');
        // The cards' padding / margin (2026-11-25): each side in its unit, none = the design's own.
        foreach (array('padding', 'margin') as $kind) {
            foreach (WP_Steps_Settings::SIDES as $side) {
                $key = 'card_' . $kind . '_' . $side;
                $unit = isset($data[$key . '_unit']) && isset(WP_Steps_Settings::SPACE_MAX[(string) $data[$key . '_unit']]) ? (string) $data[$key . '_unit'] : 'px';
                $s[$key . '_unit'] = $unit;
                foreach (array('', '_tablet', '_mobile') as $sfx) { $s[$key . $sfx] = WP_Steps_Settings::own_number($data[$key . $sfx] ?? null, 0, WP_Steps_Settings::SPACE_MAX[$unit]); }
            }
            $s['card_' . $kind . '_linked'] = WP_Steps_Settings::flag($data, 'card_' . $kind . '_linked');
        }
        $s['css_id'] = WP_Steps_Settings::css_id($data['css_id'] ?? '');
        $s['css_class'] = WP_Steps_Settings::css_classes($data['css_class'] ?? '');
        $s['steps'] = array();
        if (isset($data['steps']) && is_array($data['steps'])) {
            foreach (array_slice(array_values($data['steps']), 0, WP_Steps_Settings::MAX_STEPS) as $step) {
                $s['steps'][] = WP_Steps_Settings::step($step);
            }
        } else {
            $s['steps'] = $d['steps'];
        }
        return $s;
    }

    public static function step($data) {
        $data = is_array($data) ? $data : array();
        $text = function ($key) use ($data) { return isset($data[$key]) && is_scalar($data[$key]) ? (string) $data[$key] : ''; };
        $title = WP_Steps_Settings::rich_title($text('title'));
        $out = array(
            'title'       => $title[0],
            'title_align' => $title[1] !== null ? $title[1] : (in_array($text('title_align'), WP_Steps_Settings::ALIGNS, true) ? $text('title_align') : ''),
            'title_size'  => WP_Steps_Settings::own_size($data['title_size'] ?? null, 'title_size'),
            'title_size_tablet' => WP_Steps_Settings::own_size($data['title_size_tablet'] ?? null, 'title_size'),
            'title_size_mobile' => WP_Steps_Settings::own_size($data['title_size_mobile'] ?? null, 'title_size'),
            'title_weight' => in_array($text('title_weight'), WP_Steps_Settings::WEIGHTS, true) ? $text('title_weight') : '',
            'title_color' => WP_Steps_Settings::color($text('title_color')),
            'title_tag'   => in_array($text('title_tag'), WP_Steps_Settings::TITLE_ELEMENTS, true) ? $text('title_tag') : '',
            'text'        => WP_Steps_Settings::rich_text($text('text')),
            'text_size'   => WP_Steps_Settings::own_size($data['text_size'] ?? null),
            'text_size_tablet' => WP_Steps_Settings::own_size($data['text_size_tablet'] ?? null),
            'text_size_mobile' => WP_Steps_Settings::own_size($data['text_size_mobile'] ?? null),
            'text_weight' => in_array($text('text_weight'), WP_Steps_Settings::WEIGHTS, true) ? $text('text_weight') : '',
            'text_color'  => WP_Steps_Settings::color($text('text_color')),
            // Side text in WordPress's editors (2026-11-24, user's request: as Title and Text): its title inline words, its text a text.
            'aside_title' => WP_Steps_Settings::rich_title($text('aside_title'))[0],
            'aside_text'  => WP_Steps_Settings::rich_text($text('aside_text')),
            'icon'        => WP_Steps_Icons::exists($text('icon')) ? $text('icon') : '',
            'image'       => isset($data['image']) && is_numeric($data['image']) ? max(0, (int) $data['image']) : 0,
            'color'       => WP_Steps_Settings::color($text('color')),
            'icon_style'  => in_array($text('icon_style'), WP_Steps_Settings::ICON_STYLES, true) ? $text('icon_style') : '',
            'icon_size'   => WP_Steps_Settings::own_size($data['icon_size'] ?? null, 'icon'),
            'icon_size_tablet' => WP_Steps_Settings::own_size($data['icon_size_tablet'] ?? null, 'icon'),
            'icon_size_mobile' => WP_Steps_Settings::own_size($data['icon_size_mobile'] ?? null, 'icon'),
            'icon_color'  => WP_Steps_Settings::color($text('icon_color')),
            'media'       => in_array($text('media'), array('', 'icon', 'image'), true) ? $text('media') : '',
            'link'        => WP_Steps_Settings::link($text('link')),
            'link_new_tab' => WP_Steps_Settings::flag($data, 'link_new_tab', false),
            'icon_shape'  => in_array($text('icon_shape'), WP_Steps_Settings::ICON_SHAPES, true) ? $text('icon_shape') : '',
            'icon_bg'     => WP_Steps_Settings::color($text('icon_bg')),
            'icon_shadow' => !isset($data['icon_shadow']) || $data['icon_shadow'] === '' ? null : (!empty($data['icon_shadow']) && $data['icon_shadow'] !== 'false'),
            'icon_shadow_x' => WP_Steps_Settings::own_number($data['icon_shadow_x'] ?? null, -100, 100),
            'icon_shadow_y' => WP_Steps_Settings::own_number($data['icon_shadow_y'] ?? null, -100, 100),
            'icon_shadow_blur' => WP_Steps_Settings::own_number($data['icon_shadow_blur'] ?? null, 0, 100),
            'icon_shadow_spread' => WP_Steps_Settings::own_number($data['icon_shadow_spread'] ?? null, -50, 50),
            'icon_shadow_color' => WP_Steps_Settings::color($text('icon_shadow_color')),
            'image_size' => in_array($text('image_size'), array('', 'ratio', 'custom'), true) ? $text('image_size') : '',
            'image_ratio' => WP_Steps_Settings::ratio($text('image_ratio')), 'image_ratio_tablet' => WP_Steps_Settings::ratio($text('image_ratio_tablet')), 'image_ratio_mobile' => WP_Steps_Settings::ratio($text('image_ratio_mobile')),
            'image_height' => WP_Steps_Settings::own_number($data['image_height'] ?? null, 1, 2000), 'image_height_tablet' => WP_Steps_Settings::own_number($data['image_height_tablet'] ?? null, 1, 2000), 'image_height_mobile' => WP_Steps_Settings::own_number($data['image_height_mobile'] ?? null, 1, 2000),
            'image_fit' => in_array($text('image_fit'), array('', 'cover', 'contain', 'fill', 'none'), true) ? $text('image_fit') : '',
            'image_position' => in_array($text('image_position'), array('', 'left top', 'center top', 'right top', 'left center', 'center center', 'right center', 'left bottom', 'center bottom', 'right bottom'), true) ? $text('image_position') : '',
            'shadow' => !isset($data['shadow']) || $data['shadow'] === '' ? null : (!empty($data['shadow']) && $data['shadow'] !== 'false'),
            'shadow_x' => WP_Steps_Settings::own_number($data['shadow_x'] ?? null, -100, 100),
            'shadow_y' => WP_Steps_Settings::own_number($data['shadow_y'] ?? null, -100, 100),
            'shadow_blur' => WP_Steps_Settings::own_number($data['shadow_blur'] ?? null, 0, 100),
            'shadow_spread' => WP_Steps_Settings::own_number($data['shadow_spread'] ?? null, -50, 50),
            'shadow_color' => WP_Steps_Settings::color($text('shadow_color')),
            'icon_scale'  => WP_Steps_Settings::own_number($data['icon_scale'] ?? null, 20, 100),
            // On by default (2026-11-24: off is no rounding, a square, as WP Banner's Radius).
            'icon_use_radius' => WP_Steps_Settings::flag($data, 'icon_use_radius'),
            'icon_radius_linked' => WP_Steps_Settings::flag($data, 'icon_radius_linked'),
            'icon_scale_tablet' => WP_Steps_Settings::own_number($data['icon_scale_tablet'] ?? null, 20, 100),
            'icon_scale_mobile' => WP_Steps_Settings::own_number($data['icon_scale_mobile'] ?? null, 20, 100),
        );
        // The circle's corners: each in its unit (% by default), within its largest; empty: none.
        foreach (WP_Steps_Settings::CORNERS as $c) {
            $key = 'icon_radius_' . $c;
            $unit = in_array($text($key . '_unit'), array('px', '%'), true) ? $text($key . '_unit') : '%';
            $out[$key . '_unit'] = $unit;
            foreach (array('', '_tablet', '_mobile') as $sfx) { $out[$key . $sfx] = WP_Steps_Settings::own_number($data[$key . $sfx] ?? null, 0, WP_Steps_Settings::RADIUS_MAX[$unit]); }
        }
        // Padding / Margin: each side in its unit, within its largest; empty: none (the design's own).
        foreach (array('padding', 'margin') as $kind) {
            foreach (WP_Steps_Settings::SIDES as $side) {
                $key = $kind . '_' . $side;
                $unit = isset($data[$key . '_unit']) && isset(WP_Steps_Settings::SPACE_MAX[(string) $data[$key . '_unit']]) ? (string) $data[$key . '_unit'] : 'px';
                $out[$key . '_unit'] = $unit;
                foreach (array('', '_tablet', '_mobile') as $suffix) { $out[$key . $suffix] = WP_Steps_Settings::own_number($data[$key . $suffix] ?? null, 0, WP_Steps_Settings::SPACE_MAX[$unit]); }
            }
            $out[$kind . '_linked'] = WP_Steps_Settings::flag($data, $kind . '_linked');
        }
        // Its card's Border (0–20px) and Radius (px | %) per side / corner and device, its line and color; none = the cards'.
        foreach (WP_Steps_Settings::SIDES as $side) { foreach (array('', '_tablet', '_mobile') as $sfx) { $out['border_' . $side . $sfx] = WP_Steps_Settings::own_number($data['border_' . $side . $sfx] ?? null, 0, 20); } }
        $out['border_linked'] = WP_Steps_Settings::flag($data, 'border_linked');
        $out['border_style'] = in_array($text('border_style'), WP_Steps_Settings::CHOICES['card_border_style'], true) ? $text('border_style') : '';
        $out['border_color'] = WP_Steps_Settings::color($text('border_color'));
        foreach (WP_Steps_Settings::CORNERS as $c) {
            $unit = isset($data['radius_' . $c . '_unit']) && isset(WP_Steps_Settings::RADIUS_MAX[(string) $data['radius_' . $c . '_unit']]) ? (string) $data['radius_' . $c . '_unit'] : 'px';
            $out['radius_' . $c . '_unit'] = $unit;
            foreach (array('', '_tablet', '_mobile') as $sfx) { $out['radius_' . $c . $sfx] = WP_Steps_Settings::own_number($data['radius_' . $c . $sfx] ?? null, 0, WP_Steps_Settings::RADIUS_MAX[$unit]); }
        }
        $out['radius_linked'] = WP_Steps_Settings::flag($data, 'radius_linked');
        // The side text's style (2026-11-24): tag; size, weight and color per device.
        $out['aside_title_tag'] = in_array($text('aside_title_tag'), WP_Steps_Settings::TITLE_ELEMENTS, true) ? $text('aside_title_tag') : '';
        foreach (array('aside_title' => 'title_size', 'aside_text' => 'text_size') as $k => $limit) {
            foreach (array('', '_tablet', '_mobile') as $sfx) {
                $out[$k . '_size' . $sfx] = WP_Steps_Settings::own_size($data[$k . '_size' . $sfx] ?? null, $limit);
                $out[$k . '_weight' . $sfx] = in_array($text($k . '_weight' . $sfx), WP_Steps_Settings::WEIGHTS, true) ? $text($k . '_weight' . $sfx) : '';
                $out[$k . '_color' . $sfx] = WP_Steps_Settings::color($text($k . '_color' . $sfx));
            }
        }
        // In step_defaults()' order (some keys are worked out after others, e.g. a corner's unit before its values).
        return array_replace(WP_Steps_Settings::step_defaults(), $out);
    }

    // A step's own size in px within LIMITS[$key] (the Text size's by default), null without one.
    public static function own_size($value, $key = 'text_size') {
        return WP_Steps_Settings::own_number($value, WP_Steps_Settings::LIMITS[$key][0], WP_Steps_Settings::LIMITS[$key][1]);
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
}
