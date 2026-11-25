<?php
/**
 * Deleting the plugin (Plugins → Delete; not deactivating it, not an update) deletes its step sets and their settings, who may
 * do what (WP Steps → Settings), the "What's new" state and the update checker's stored state. Images stay in the media library.
 */
defined('WP_UNINSTALL_PLUGIN') || exit;

$wp_steps_ids = get_posts(array('post_type' => 'wp_steps', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids'));
foreach ($wp_steps_ids as $wp_steps_id) {
    wp_delete_post($wp_steps_id, true);
}
// WP_Steps_Access, WP_Steps_Whats_New, Plugin Update Checker (its option is named after the slug).
foreach (array('wp_steps_roles', 'wp_steps_version', 'wp_steps_updated_from') as $wp_steps_option) {
    delete_option($wp_steps_option);
}
delete_site_option('external_updates-wp-steps');
delete_metadata('user', 0, 'wp_steps_seen_version', '', true);
