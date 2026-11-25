<?php
/**
 * Plugin Name:       WP Steps
 * Plugin URI:        https://github.com/Andrusi4ka/WP-Steps
 * Description:       Steps and timelines: a list of steps drawn in one of several designs (list, timeline, zigzag, wave, progress), with icons, numbers and colors running from the first step to the last. Adapts to the width it has. Insert with a shortcode or the WPBakery element.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Relevant AS
 * Author URI:        https://relevant.no/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-steps
 * Domain Path:       /languages
 * Update URI:        https://github.com/Andrusi4ka/WP-Steps
 */
// "Update URI": updates come from this plugin's GitHub releases (wp_steps_updates()), never from wordpress.org.
defined('ABSPATH') || exit;

define('WP_STEPS_VERSION', '1.0.0');
define('WP_STEPS_REPOSITORY', 'https://github.com/Andrusi4ka/WP-Steps/');
define('WP_STEPS_FILE', __FILE__);
define('WP_STEPS_DIR', plugin_dir_path(__FILE__));
define('WP_STEPS_URL', plugin_dir_url(__FILE__));

// An asset's version: the plugin's plus the file's time, so browsers fetch it again when it changes.
function wp_steps_asset($file) {
    $time = @filemtime(WP_STEPS_DIR . $file);
    return $time ? WP_STEPS_VERSION . '.' . $time : WP_STEPS_VERSION;
}

require_once WP_STEPS_DIR . 'includes/class-wp-steps-access.php';
require_once WP_STEPS_DIR . 'includes/class-wp-steps-icons.php';
require_once WP_STEPS_DIR . 'includes/class-wp-steps-design.php';
require_once WP_STEPS_DIR . 'includes/class-wp-steps-designs.php';
require_once WP_STEPS_DIR . 'includes/class-wp-steps-settings.php';
require_once WP_STEPS_DIR . 'includes/class-wp-steps-render.php';
require_once WP_STEPS_DIR . 'includes/class-wp-steps-palette.php';
require_once WP_STEPS_DIR . 'includes/class-wp-steps-admin.php';
require_once WP_STEPS_DIR . 'includes/class-wp-steps-wpbakery.php';
require_once WP_STEPS_DIR . 'includes/class-wp-steps-whats-new.php';

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
        // The plugin's own capabilities (WP Steps → Settings, per role; WP_Steps_Access), as WP Swiper's: administrators always.
        // Any set, whoever made it.
        'capabilities' => array_merge(
            array_fill_keys(array('edit_posts', 'edit_others_posts', 'edit_private_posts', 'edit_published_posts', 'read_private_posts'), WP_Steps_Access::EDIT),
            array_fill_keys(array('create_posts', 'publish_posts'), WP_Steps_Access::CREATE),
            array_fill_keys(array('delete_posts', 'delete_others_posts', 'delete_private_posts', 'delete_published_posts'), WP_Steps_Access::DELETE)
        ),
    ));
    // The settings kept with each revision (WordPress 6.4+), so an earlier version can be restored.
    if (function_exists('wp_post_revision_meta_keys')) {
        register_post_meta(WP_Steps_Settings::POST_TYPE, WP_Steps_Settings::META, array(
            'type' => 'object', 'single' => true, 'revisions_enabled' => true, 'show_in_rest' => false,
            'auth_callback' => function () { return WP_Steps_Access::can('edit'); },
        ));
    }
});

// Whether this copy is a git working copy (a ".git" folder in it): an update replaces the whole folder and would wipe the work
// in progress (it did so to WP Swiper on 2026-11-04), so such a copy is never offered updates.
function wp_steps_is_working_copy() {
    return (bool) apply_filters('wp_steps_is_working_copy', file_exists(WP_STEPS_DIR . '.git'));
}

/**
 * Updates from GitHub, as WP Swiper's and WP Banner's: a release (tag "vX.Y.Z" with the wp-steps.zip built by
 * .github/workflows/release.yml) shows up under Plugins like any other update, with "View details" (readme.txt) and
 * "Check for updates". Not for a git working copy (wp_steps_is_working_copy()).
 */
function wp_steps_updates() {
    $library = WP_STEPS_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
    // Only where WordPress looks for updates: the admin and its cron.
    if ((!is_admin() && !wp_doing_cron()) || !file_exists($library) || wp_steps_is_working_copy()) { return; }
    require_once $library;
    $checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(WP_STEPS_REPOSITORY, WP_STEPS_FILE, 'wp-steps');
    // The release's zip, which holds just the plugin (not the repository's dev files).
    $checker->getVcsApi()->enableReleaseAssets('/wp-steps\.zip($|[?&#])/i');
    $checker->setBranch('main');
}
add_action('plugins_loaded', 'wp_steps_updates');

WP_Steps_Access::init();
WP_Steps_Render::init();
WP_Steps_Admin::init();
WP_Steps_WPBakery::init();
WP_Steps_Whats_New::init();
