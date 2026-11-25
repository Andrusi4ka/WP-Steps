<?php
/**
 * A test page with steps (for tests/browser.cjs): php tests/page.php '<settings as JSON>'
 * prints a whole HTML page with the plugin's stylesheets inlined (no WordPress needed).
 */
require __DIR__ . '/stubs.php';
// Any image a step names: a grey 4:3 picture (2026-11-25: the image box is measured in tests/browser.cjs).
if (!function_exists('wp_get_attachment_image')) {
    function wp_get_attachment_image($id, $size = 'thumbnail', $icon = false, $attr = array()) { return '<img src="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22300%22%3E%3Crect width=%22400%22 height=%22300%22 fill=%22%23999%22/%3E%3C/svg%3E" class="' . ($attr['class'] ?? '') . '" alt="">'; }
}

$data = json_decode($argv[1] ?? '{}', true);
$s = WP_Steps_Settings::sanitize(is_array($data) ? $data : array());
$design = WP_Steps_Designs::get($s['design']);
$css = file_get_contents(WP_STEPS_DIR . 'assets/css/frontend.css') . file_get_contents(WP_STEPS_DIR . 'assets/css/designs/' . $design->id() . '.css');
echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'
    . 'body{margin:0;padding:32px 24px;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#fff}'
    . $css . '</style></head><body>' . WP_Steps_Render::markup($s) . '</body></html>';
