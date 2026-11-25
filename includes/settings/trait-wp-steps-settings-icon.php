<?php
/**
 * WP_Steps_Settings, part: A step's icon or image: shown or not, its circle, corners, shadows, an image box's aspect ratio;
 * also a step's card shadow (step_shadow_css()).
 * The class (includes/class-wp-steps-settings.php) holds the constants; called here as WP_Steps_Settings:: (the same class
 * as self::, and the editor can follow it).
 */
defined('ABSPATH') || exit;

trait WP_Steps_Settings_Icon {
    // Does the step show its image (its choice; not chosen: when it has one)?
    public static function shows_image($step) {
        $media = $step['media'] ?? '';
        return $media === 'image' || ($media === '' && !empty($step['image']));
    }

    // A step's circle corners per device as CSS border-radius ('' where the step has none of its own anywhere: a circle).
    // $round: a corner none set (2026-11-25: '0' outside Wave, where icons are square by default; '50%' in Wave).
    public static function icon_radii($step, $round = '50%') {
        // Off: square corners on every screen.
        if (isset($step['icon_use_radius']) && !$step['icon_use_radius']) { return array('desktop' => '0', 'tablet' => '0', 'mobile' => '0'); }
        $own = false;
        foreach (WP_Steps_Settings::CORNERS as $c) { foreach (array('', '_tablet', '_mobile') as $d) { $own = $own || (isset($step['icon_radius_' . $c . $d]) && $step['icon_radius_' . $c . $d] !== null); } }
        if (!$own) { return ''; }
        $out = array();
        foreach (array('desktop' => array(''), 'tablet' => array('_tablet', ''), 'mobile' => array('_mobile', '_tablet', '')) as $device => $chain) {
            $parts = array();
            foreach (WP_Steps_Settings::CORNERS as $c) {
                $v = null;
                foreach ($chain as $d) { if (isset($step['icon_radius_' . $c . $d]) && $step['icon_radius_' . $c . $d] !== null) { $v = $step['icon_radius_' . $c . $d]; break; } }
                $parts[] = $v === null ? $round : (int) $v . ($step['icon_radius_' . $c . '_unit'] ?? '%');
            }
            $out[$device] = implode(' ', $parts);
        }
        return $out;
    }

    // A box-shadow from its parts.
    public static function shadow_css($x, $y, $blur, $spread, $color) {
        return (int) $x . 'px ' . (int) $y . 'px ' . (int) $blur . 'px ' . (int) $spread . 'px ' . $color;
    }
    // A step's shadow from its parts <prefix>_x, _y, _blur, _spread, _color; a part it has none of (null / ''): $usual's (same order).
    public static function own_shadow_css($step, $prefix, $usual) {
        $parts = array();
        foreach (array('x', 'y', 'blur', 'spread', 'color') as $i => $part) {
            $v = $step[$prefix . '_' . $part] ?? null;
            $parts[] = $v === null || $v === '' ? $usual[$i] : $v;
        }
        return WP_Steps_Settings::shadow_css($parts[0], $parts[1], $parts[2], $parts[3], $parts[4]);
    }
    // A step's card shadow: its parts, else the cards' (2026-11-25).
    public static function step_shadow_css($s, $step) {
        return WP_Steps_Settings::own_shadow_css($step, 'shadow', array($s['card_shadow_x'], $s['card_shadow_y'], $s['card_shadow_blur'], $s['card_shadow_spread'], $s['card_shadow_color']));
    }
    // A step's circle shadow: its parts, else ICON_SHADOW's.
    public static function icon_shadow_css($step) {
        return WP_Steps_Settings::own_shadow_css($step, 'icon_shadow', WP_Steps_Settings::ICON_SHADOW);
    }
    // An aspect ratio as CSS takes it: '16/9', '1.5' (16:9 and 16 / 9 read too); else ''.
    public static function ratio($value) {
        $value = str_replace(array(':', ' '), array('/', ''), trim((string) $value));
        if (!preg_match('#^(\d{1,4}(?:\.\d{1,3})?)(?:/(\d{1,4}(?:\.\d{1,3})?))?$#', $value, $m) || (float) $m[1] <= 0 || (isset($m[2]) && (float) $m[2] <= 0)) { return ''; }
        return isset($m[2]) ? $m[1] . '/' . $m[2] : $m[1];
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
}
