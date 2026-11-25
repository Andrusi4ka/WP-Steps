<?php
/**
 * Plugin Name:       WP Steps
 * Plugin URI:        https://github.com/Andrusi4ka/WP-Steps
 * Description:       Steps and timelines: a list of steps drawn in one of several designs (list, timeline, zigzag, wave, progress), with icons, numbers and colors running from the first step to the last. Adapts to the width it has. Insert with a shortcode or the WPBakery element.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Andrii Boiko
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-steps
 * Domain Path:       /languages
 * Update URI:        https://github.com/Andrusi4ka/WP-Steps
 */
// "Update URI": never updated from wordpress.org (a plugin there with the same folder name would otherwise replace it).
defined('ABSPATH') || exit;

define('WP_STEPS_VERSION', '0.1.0');
define('WP_STEPS_FILE', __FILE__);
define('WP_STEPS_DIR', plugin_dir_path(__FILE__));
define('WP_STEPS_URL', plugin_dir_url(__FILE__));

// An asset's version: the plugin's plus the file's time, so browsers fetch it again when it changes.
function wp_steps_asset($file) {
    $time = @filemtime(WP_STEPS_DIR . $file);
    return $time ? WP_STEPS_VERSION . '.' . $time : WP_STEPS_VERSION;
}

require_once WP_STEPS_DIR . 'includes/class-wp-steps-icons.php';
require_once WP_STEPS_DIR . 'includes/class-wp-steps-design.php';
require_once WP_STEPS_DIR . 'includes/class-wp-steps-designs.php';
require_once WP_STEPS_DIR . 'includes/class-wp-steps-settings.php';
require_once WP_STEPS_DIR . 'includes/class-wp-steps-render.php';
require_once WP_STEPS_DIR . 'includes/class-wp-steps-palette.php';
require_once WP_STEPS_DIR . 'includes/class-wp-steps-admin.php';
require_once WP_STEPS_DIR . 'includes/class-wp-steps-wpbakery.php';

add_action('init', function () {
    load_plugin_textdomain('wp-steps', false, dirname(plugin_basename(__FILE__)) . '/languages');
    register_post_type(WP_Steps_Settings::POST_TYPE, array(
        'labels' => array(
            'name'          => 'WP Steps',
            'singular_name' => __('Steps', 'wp-steps'),
            'all_items'     => __('All steps', 'wp-steps'),
            'add_new'       => __('Add new', 'wp-steps'),
            'add_new_item'  => __('Add steps', 'wp-steps'),
            'edit_item'     => __('Edit steps', 'wp-steps'),
            'not_found'     => __('No steps yet.', 'wp-steps'),
        ),
        'public'       => false,
        'show_ui'      => true,
        'show_in_rest' => false,
        'supports'     => array('title', 'revisions'),
        'menu_icon'    => 'dashicons-editor-ol',
        'map_meta_cap' => true,
        'capability_type' => 'post',
    ));
    // The settings kept with each revision (WordPress 6.4+), so an earlier version can be restored.
    if (function_exists('wp_post_revision_meta_keys')) {
        register_post_meta(WP_Steps_Settings::POST_TYPE, WP_Steps_Settings::META, array(
            'type' => 'object', 'single' => true, 'revisions_enabled' => true, 'show_in_rest' => false,
            'auth_callback' => function () { return current_user_can('edit_posts'); },
        ));
    }
});

WP_Steps_Render::init();
WP_Steps_Admin::init();
WP_Steps_WPBakery::init();
