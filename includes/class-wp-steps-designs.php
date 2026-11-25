<?php
/**
 * The designs there are (WP_Steps_Design): the plugin's own, plus any added through the
 * wp_steps_designs filter.
 */
defined('ABSPATH') || exit;

class WP_Steps_Designs {
    // The plugin's own, in the order the editor shows them.
    const BUILT_IN = array('list', 'timeline', 'zigzag', 'wave', 'progress');
    const DEFAULT_DESIGN = 'list';

    private static $all = null;

    /** @return WP_Steps_Design[] id => design */
    public static function all() {
        if (self::$all !== null) { return self::$all; }
        $list = array();
        foreach (self::BUILT_IN as $id) {
            require_once WP_STEPS_DIR . 'includes/designs/class-wp-steps-design-' . $id . '.php';
            $class = 'WP_Steps_Design_' . ucfirst($id);
            $list[] = new $class();
        }
        $list = apply_filters('wp_steps_designs', $list);
        self::$all = array();
        foreach (is_array($list) ? $list : array() as $design) {
            if ($design instanceof WP_Steps_Design && preg_match('/^[a-z0-9-]{1,40}$/', $design->id()) && !isset(self::$all[$design->id()])) {
                self::$all[$design->id()] = $design;
            }
        }
        return self::$all;
    }

    public static function exists($id) {
        return is_string($id) && isset(self::all()[$id]);
    }

    // The design with this id, or the default one.
    public static function get($id) {
        $all = self::all();
        if (is_string($id) && isset($all[$id])) { return $all[$id]; }
        return isset($all[self::DEFAULT_DESIGN]) ? $all[self::DEFAULT_DESIGN] : reset($all);
    }

    // For the editor: each one's id, name, line, the settings it uses, its fold width and drawing.
    public static function editor() {
        $out = array();
        foreach (self::all() as $design) {
            $out[] = array('id' => $design->id(), 'name' => $design->name(), 'about' => $design->about(),
                'supports' => array_values(array_intersect($design->supports(), WP_Steps_Settings::OPTIONAL)),
                'stack' => (int) $design->stack_below(), 'thumb' => $design->thumb());
        }
        return $out;
    }

    // Forget the list (tests that add designs through the filter).
    public static function reset() { self::$all = null; }
}
