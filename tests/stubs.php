<?php
/**
 * Just enough of WordPress for the plugin's settings and markup to run without it
 * (tests/settings.php, tests/page.php): no database, nothing saved.
 */
define('ABSPATH', __DIR__ . '/');
define('WP_STEPS_DIR', dirname(__DIR__) . '/');
define('WP_STEPS_URL', 'https://example.test/wp-content/plugins/wp-steps/');
define('WP_STEPS_VERSION', 'test');

$GLOBALS['wpst_filters'] = array();
function add_filter($name, $callback) { $GLOBALS['wpst_filters'][$name][] = $callback; }
function apply_filters($name, $value) {
    foreach ($GLOBALS['wpst_filters'][$name] ?? array() as $callback) { $value = $callback($value); }
    return $value;
}
function remove_all_filters($name) { unset($GLOBALS['wpst_filters'][$name]); }
function remove_filter($name, $callback) { $GLOBALS['wpst_filters'][$name] = array_filter($GLOBALS['wpst_filters'][$name] ?? array(), function ($c) use ($callback) { return $c !== $callback; }); }
function wp_strip_all_tags($text) { return trim(strip_tags(preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', (string) $text))); }
function __($text) { return $text; }
function esc_html__($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function sanitize_text_field($text) { return trim(preg_replace('/\s+/', ' ', strip_tags((string) $text))); }
// Only the tags allowed, their attributes dropped except those allowed; "javascript:" links dropped.
function wp_kses($html, $allowed) {
    $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', (string) $html);
    return preg_replace_callback('#<(/?)([a-z0-9]+)([^>]*)>#i', function ($m) use ($allowed) {
        $tag = strtolower($m[2]);
        if (!isset($allowed[$tag])) { return ''; }
        if ($m[1] === '/') { return '</' . $tag . '>'; }
        $attrs = '';
        preg_match_all('/([a-z-]+)\s*=\s*"([^"]*)"/i', $m[3], $found, PREG_SET_ORDER);
        foreach ($found as $a) {
            $name = strtolower($a[1]);
            if (empty($allowed[$tag][$name])) { continue; }
            if ($name === 'href' && preg_match('/^\s*javascript:/i', $a[2])) { continue; }
            $attrs .= ' ' . $name . '="' . esc_attr($a[2]) . '"';
        }
        return '<' . $tag . $attrs . '>';
    }, $html);
}
function esc_url_raw($url, $protocols = null) { return preg_match('#^\s*javascript:#i', (string) $url) ? '' : str_replace(array('"', '<', '>', ' '), array('%22', '%3C', '%3E', '%20'), trim((string) $url)); }
if (!function_exists('esc_url')) { function esc_url($url) { return htmlspecialchars(esc_url_raw($url), ENT_QUOTES, 'UTF-8'); } }
function wp_steps_asset($file) { return 'test'; }

require WP_STEPS_DIR . 'includes/class-wp-steps-icons.php';
require WP_STEPS_DIR . 'includes/class-wp-steps-design.php';
require WP_STEPS_DIR . 'includes/class-wp-steps-designs.php';
require WP_STEPS_DIR . 'includes/class-wp-steps-settings.php';
require WP_STEPS_DIR . 'includes/class-wp-steps-render.php';
