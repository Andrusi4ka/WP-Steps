<?php
/**
 * Deleting the plugin (Plugins → Delete) deletes its step sets and their settings.
 * Deactivating it keeps everything.
 */
defined('WP_UNINSTALL_PLUGIN') || exit;

$wp_steps_ids = get_posts(array('post_type' => 'wp_steps', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids'));
foreach ($wp_steps_ids as $wp_steps_id) {
    wp_delete_post($wp_steps_id, true);
}
