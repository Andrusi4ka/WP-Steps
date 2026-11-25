<?php
/**
 * WP_Steps_Settings, part: Spacing, borders and corners resolved per device for the render: the set's Padding / Margin,
 * the icon's place in the cards, the cards' and a step's own box (Margin, Border, Radius, Padding), the block's id and classes.
 * The class (includes/class-wp-steps-settings.php) holds the constants; called here as WP_Steps_Settings:: (the same class
 * as self::, and the editor can follow it).
 */
defined('ABSPATH') || exit;

trait WP_Steps_Settings_Box {
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

    // Padding and Margin keys: 0 on every side, px, linked; tablet / mobile null (the larger screen's).
    public static function space_defaults() {
        $out = array();
        foreach (array('padding', 'margin') as $kind) {
            $out[$kind . '_linked'] = true;
            foreach (WP_Steps_Settings::SIDES as $side) { $out[$kind . '_' . $side] = 0; $out[$kind . '_' . $side . '_unit'] = 'px'; $out[$kind . '_' . $side . '_tablet'] = null; $out[$kind . '_' . $side . '_mobile'] = null; }
        }
        return $out;
    }
    // The set's Padding or Margin per device: ['desktop' => 'T R B L', ...] with units; '' with the switch off or all 0.
    public static function spaces($s, $kind) {
        if (empty($s['use_padding']) || !WP_Steps_Settings::has_own($s, $kind . '_', WP_Steps_Settings::SIDES, true)) { return ''; }
        return WP_Steps_Settings::device_lists($s, $kind . '_', WP_Steps_Settings::SIDES, true);
    }
    // Sides or corners per device as one CSS value each: ['desktop' => 'T R B L', ...]; each its own (or its larger screen's) value
    // with its unit ($units: each part's _unit key, else px), 0 without one.
    public static function device_lists($s, $prefix, $parts, $units) {
        $out = array('desktop' => array(), 'tablet' => array(), 'mobile' => array());
        foreach ($parts as $p) {
            $key = $prefix . $p;
            $unit = $units ? ($s[$key . '_unit'] ?? 'px') : 'px';
            list($d, $t, $m) = WP_Steps_Settings::sizes($s, $key);
            foreach (array('desktop' => $d, 'tablet' => $t, 'mobile' => $m) as $device => $v) { $out[$device][] = $v ? ((int) $v) . $unit : '0'; }
        }
        return array_map(function ($list) { return implode(' ', $list); }, $out);
    }
    // The icon's place, alignment and gap on each device: ['desktop' => [pos, align, gap], ...]; pos '' = the design's own (then no
    // alignment), align '' = the usual one, gap null = the design's own.
    public static function icon_layout($s) {
        $out = array();
        $pos = $s['icon_position'] ?? '';
        $align = $s['icon_align'] ?? '';
        $gaps = WP_Steps_Settings::sizes($s, 'icon_gap');
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
        return WP_Steps_Settings::has_own($s, 'card_border_', WP_Steps_Settings::SIDES, true) ? WP_Steps_Settings::device_lists($s, 'card_border_', WP_Steps_Settings::SIDES, false) : '';
    }
    // The cards' corners per device ('tl tr br bl' with units), '' while all are 0 (square).
    public static function card_radii($s) {
        return WP_Steps_Settings::has_own($s, 'card_radius_', WP_Steps_Settings::CORNERS, true) ? WP_Steps_Settings::device_lists($s, 'card_radius_', WP_Steps_Settings::CORNERS, true) : '';
    }
    // A step's card border widths or corners per device ('T R B L' / 'tl tr br bl'): its own (or its larger screen's), else the cards';
    // '' when the step has none of its own.
    public static function step_box($s, $step, $kind) {
        $parts = $kind === 'border' ? WP_Steps_Settings::SIDES : WP_Steps_Settings::CORNERS;
        if (!WP_Steps_Settings::has_own($step, $kind . '_', $parts) && !($kind === 'border' && (!empty($step['border_style']) || !empty($step['border_color'])))) { return ''; }
        $out = array('desktop' => array(), 'tablet' => array(), 'mobile' => array());
        foreach ($parts as $p) {
            $own = WP_Steps_Settings::step_side($step, $kind . '_' . $p);
            list($d, $t, $m) = WP_Steps_Settings::sizes($s, 'card_' . $kind . '_' . $p);
            $u = $kind === 'border' ? 'px' : ($s['card_radius_' . $p . '_unit'] ?? 'px');
            foreach (array('desktop' => $d, 'tablet' => $t, 'mobile' => $m) as $device => $v) { $out[$device][] = $own[$device] !== null ? $own[$device] : ($v ? ((int) $v) . $u : '0'); }
        }
        return array_map(function ($x) { return implode(' ', $x); }, $out);
    }
    // Has any of these parts a value on some device ($above_zero: a value above 0)?
    public static function has_own($step, $prefix, $parts, $above_zero = false) {
        foreach ($parts as $p) {
            foreach (array('', '_tablet', '_mobile') as $sfx) {
                $v = $step[$prefix . $p . $sfx] ?? null;
                if ($above_zero ? !empty($v) : $v !== null) { return true; }
            }
        }
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
}
