<?php
/**
 * The WPBakery Page Builder element (as WP Swiper's and WP Banner's): the [wp_steps] shortcode
 * with a list of the step sets and, under it, "Edit steps" and "Add steps" (new tabs).
 */
defined('ABSPATH') || exit;

class WP_Steps_WPBakery {
    // The buttons under the list (a param type of its own).
    const EDIT_PARAM = 'wp_steps_edit';
    // The list again (wpbakery.js, back from adding a set): its AJAX action.
    const LIST_ACTION = 'wp_steps_vc_list';

    public static function init() {
        add_action('vc_before_init', array(__CLASS__, 'map'));
        add_action('wp_ajax_' . self::LIST_ACTION, array(__CLASS__, 'ajax_list'));
    }

    public static function map() {
        if (!WP_Steps_Access::can('wpbakery')) { return; }
        if (function_exists('vc_add_shortcode_param')) {
            vc_add_shortcode_param(self::EDIT_PARAM, array(__CLASS__, 'edit_param'), WP_STEPS_URL . 'assets/js/wpbakery.js?ver=' . rawurlencode(wp_steps_asset('assets/js/wpbakery.js')));
        }
        if (function_exists('vc_lean_map')) {
            vc_lean_map(WP_Steps_Render::SHORTCODE, array(__CLASS__, 'settings'));
        }
    }

    // With Woodmart: its own "Theme elements" tab; otherwise WPBakery's "Content".
    private static function category() {
        if (function_exists('woodmart_get_tab_title_category_for_wpb')) {
            return woodmart_get_tab_title_category_for_wpb(esc_html__('Theme elements', 'woodmart')); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- the theme's own tab name.
        }
        return __('Content', 'wp-steps');
    }

    public static function settings() {
        return array(
            'name'        => __('WP Steps', 'wp-steps'),
            'base'        => WP_Steps_Render::SHORTCODE,
            'icon'        => WP_STEPS_URL . 'assets/images/wpbakery-icon.svg',
            'category'    => self::category(),
            'weight'      => -100,
            'description' => __('Steps or a timeline', 'wp-steps'),
            'params'      => array(
                array(
                    'type'        => 'dropdown',
                    'heading'     => __('Steps', 'wp-steps'),
                    'param_name'  => 'id',
                    'value'       => self::options(),
                    'admin_label' => true,
                    'description' => sprintf(
                        /* translators: %s: link to the list of step sets */
                        __('Create and edit them under %s.', 'wp-steps'),
                        '<a href="' . esc_url(admin_url('edit.php?post_type=' . WP_Steps_Settings::POST_TYPE)) . '" target="_blank">WP Steps</a>'
                    ),
                ),
                array('type' => self::EDIT_PARAM, 'heading' => '', 'param_name' => self::EDIT_PARAM),
            ),
        );
    }

    // "Edit steps" (wpbakery.js points it at the set chosen) and "Add steps"; the field is empty, so nothing goes into the shortcode.
    public static function edit_param($settings, $value) {
        unset($value);
        $name = isset($settings['param_name']) ? $settings['param_name'] : self::EDIT_PARAM;
        $type = get_post_type_object(WP_Steps_Settings::POST_TYPE);
        $add = '';
        if ($type && current_user_can($type->cap->create_posts)) {
            $add = sprintf(' <a class="button wpst-vc-edit__add" href="%1$s" target="_blank" rel="noopener">%2$s</a>', esc_url(admin_url('post-new.php?post_type=' . WP_Steps_Settings::POST_TYPE)), esc_html__('Add steps', 'wp-steps'));
        }
        $list = add_query_arg(array('action' => self::LIST_ACTION, '_wpnonce' => wp_create_nonce(self::LIST_ACTION)), admin_url('admin-ajax.php'));
        return sprintf(
            '<div class="wpst-vc-edit" data-url="%1$s" data-list="%6$s"><a class="button wpst-vc-edit__link" href="#" target="_blank" rel="noopener" hidden>%2$s</a>%7$s<p class="description wpst-vc-edit__note" style="margin:6px 0 0">%5$s</p><input type="hidden" name="%3$s" class="wpb_vc_param_value %3$s %4$s" value=""></div>',
            esc_url(admin_url('post.php?action=edit&post=')),
            esc_html__('Edit steps', 'wp-steps'),
            esc_attr($name),
            esc_attr(self::EDIT_PARAM),
            esc_html__('Opens in a new tab. When you come back, steps you published there are chosen in the list.', 'wp-steps'),
            esc_url($list),
            $add // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where it is built.
        );
    }

    /** @return array [ [ 'id' => int, 'label' => string ] ] the published sets, by title. */
    public static function items() {
        $list = array();
        foreach (get_posts(array('post_type' => WP_Steps_Settings::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC')) as $item) {
            $title = $item->post_title !== '' ? $item->post_title : __('(no title)', 'wp-steps');
            $list[] = array('id' => (int) $item->ID, 'label' => sprintf('%s (#%d)', $title, $item->ID));
        }
        return $list;
    }

    private static function options() {
        $options = array(__('— Select steps —', 'wp-steps') => '');
        foreach (self::items() as $item) { $options[$item['label']] = (string) $item['id']; }
        return $options;
    }

    public static function ajax_list() {
        check_ajax_referer(self::LIST_ACTION);
        if (!WP_Steps_Access::can('wpbakery')) { wp_send_json_error(null, 403); }
        wp_send_json_success(self::items());
    }
}
