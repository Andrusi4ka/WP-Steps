<?php
/**
 * The editor's page without WordPress, for tests/editor.cjs (2026-11-23: the step's Text editor): the plugin's own markup
 * (WP_Steps_Admin::editor()) with admin.js, a stand-in for WordPress's wp.editor that records what it is asked. Nothing saved.
 */
require __DIR__ . '/stubs.php';
function wp_nonce_field() {}
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function esc_attr__($text) { return esc_attr($text); }
function get_post_meta() { return ''; }
function admin_url($path = '') { return 'https://example.test/wp-admin/' . $path; }
function wp_create_nonce() { return 'n'; }
function get_template() { return 'test'; }
function get_option() { return array(); }
require WP_STEPS_DIR . 'includes/class-wp-steps-palette.php';
require WP_STEPS_DIR . 'includes/class-wp-steps-admin.php';
ob_start();
WP_Steps_Admin::editor((object) array('ID' => 1));
$app = ob_get_clean();
$base = 'file:///' . str_replace('\\', '/', dirname(__DIR__)) . '/assets/';
echo '<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="' . $base . 'vendor/pickr/classic.min.css"><link rel="stylesheet" href="' . $base . 'css/admin.css"></head><body>'
    . '<script>window.wpCalls={init:[],remove:[]};window.tinymce={};window.wp={editor:{initialize:function(id,cfg){wpCalls.init.push([id,cfg]);},remove:function(id){wpCalls.remove.push(id);}}};</script>'
    . $app
    . '<script src="' . $base . 'vendor/pickr/pickr.min.js"></script><script src="' . $base . 'js/admin-color.js"></script><script src="' . $base . 'js/admin.js"></script></body></html>';
