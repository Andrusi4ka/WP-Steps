<?php
/**
 * The editor (a meta box on the wp_steps edit screen, drawn by assets/js/admin.js), saving, the
 * preview (admin-ajax wp_steps_preview: a page with just the steps) and the Shortcode box.
 * The look follows WP Swiper and WP Banner: tiles across the top (Settings, the steps, Add step),
 * under them the menu of blocks, the chosen block and the preview.
 */
defined('ABSPATH') || exit;

class WP_Steps_Admin {
    // The settings as sent, at most (bytes).
    const MAX_DATA = 300000;
    const PREVIEW_ACTION = 'wp_steps_preview';

    public static function init() {
        $type = WP_Steps_Settings::POST_TYPE;
        add_action('add_meta_boxes_' . $type, array(__CLASS__, 'boxes'));
        add_action('save_post_' . $type, array(__CLASS__, 'save'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
        add_action('admin_head', array(__CLASS__, 'colors'));
        add_action('wp_ajax_' . self::PREVIEW_ACTION, array(__CLASS__, 'preview'));
        add_filter('manage_' . $type . '_posts_columns', function ($columns) {
            $columns['wp_steps_shortcode'] = __('Shortcode', 'wp-steps');
            return $columns;
        });
        add_action('manage_' . $type . '_posts_custom_column', function ($column, $id) {
            if ($column === 'wp_steps_shortcode') { echo '<code>[wp_steps id="' . absint($id) . '"]</code>'; }
        }, 10, 2);
    }

    public static function boxes() {
        add_meta_box('wpst-editor', __('Steps editor', 'wp-steps'), array(__CLASS__, 'editor'), WP_Steps_Settings::POST_TYPE, 'normal', 'high');
        add_meta_box('wpst-shortcode', __('Shortcode', 'wp-steps'), array(__CLASS__, 'shortcode_box'), WP_Steps_Settings::POST_TYPE, 'side', 'high');
    }

    public static function shortcode_box($post) {
        echo '<input type="text" class="wpst-shortcode" readonly value="' . esc_attr('[wp_steps id="' . absint($post->ID) . '"]') . '" onclick="this.select()" aria-label="' . esc_attr__('Shortcode', 'wp-steps') . '" style="width:100%">';
        echo '<p class="description">' . esc_html__('Paste it into any page, or use the “WP Steps” element in WPBakery.', 'wp-steps') . '</p>';
    }

    private static function screen_is_ours() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        return $screen && $screen->post_type === WP_Steps_Settings::POST_TYPE && $screen->base === 'post';
    }

    /*
     * WordPress's own blue on this screen whatever the admin color scheme (2026-11-24, user's report: the buttons changed color on
     * hover; as Signatur's signatur_admin_colors()): the theme color variables, stronger than body.admin-color-<scheme>.
     */
    public static function colors() {
        if (!self::screen_is_ours()) { return; }
        echo '<style id="wpst-admin-colors">body.wp-admin.wp-core-ui{--wp-admin-theme-color:#2271b1;--wp-admin-theme-color--rgb:34,113,177;'
            . '--wp-admin-theme-color-darker-10:#135e96;--wp-admin-theme-color-darker-10--rgb:19,94,150;--wp-admin-theme-color-darker-20:#0a4b78;--wp-admin-theme-color-darker-20--rgb:10,75,120}</style>';
    }

    public static function assets() {
        if (!self::screen_is_ours()) { return; }
        wp_enqueue_media();
        // WordPress's editor for each step's Text (2026-11-23, as WP Swiper's caption editor).
        wp_enqueue_editor();
        // Pickr 1.9.1 (MIT), as in WP Swiper and WP Banner: every color field's picker.
        wp_enqueue_style('wpst-pickr', WP_STEPS_URL . 'assets/vendor/pickr/classic.min.css', array(), '1.9.1');
        wp_enqueue_script('wpst-pickr', WP_STEPS_URL . 'assets/vendor/pickr/pickr.min.js', array(), '1.9.1', true);
        wp_enqueue_style('wpst-admin', WP_STEPS_URL . 'assets/css/admin.css', array('wpst-pickr'), wp_steps_asset('assets/css/admin.css'));
        wp_enqueue_script('wpst-admin-color', WP_STEPS_URL . 'assets/js/admin-color.js', array('wpst-pickr'), wp_steps_asset('assets/js/admin-color.js'), true);
        wp_enqueue_script('wpst-admin', WP_STEPS_URL . 'assets/js/admin.js', array('wpst-admin-color'), wp_steps_asset('assets/js/admin.js'), true);
    }

    // Everything admin.js starts from.
    public static function boot($post) {
        $s = WP_Steps_Settings::get($post->ID);
        $images = array();
        foreach ($s['steps'] as $step) {
            if ($step['image'] && !isset($images[$step['image']])) { $images[$step['image']] = self::image($step['image']); }
        }
        $defaults = WP_Steps_Settings::defaults();
        unset($defaults['steps']);
        return array(
            'settings'  => $s,
            'defaults'  => $defaults,
            'step'      => WP_Steps_Settings::step_defaults(),
            'designs'   => WP_Steps_Designs::editor(),
            'optional'  => WP_Steps_Settings::OPTIONAL,
            'limits'    => WP_Steps_Settings::LIMITS,
            'maxSteps'  => WP_Steps_Settings::MAX_STEPS,
            'maxData'   => self::MAX_DATA,
            'icons'     => WP_Steps_Icons::editor(),
            'images'    => $images,
            'palette'   => WP_Steps_Palette::colors(),
            'textColors' => WP_Steps_Palette::tinymce_map(),
            'endpoint'  => admin_url('admin-ajax.php'),
            'action'    => self::PREVIEW_ACTION,
            'nonce'     => wp_create_nonce(self::PREVIEW_ACTION),
            'postId'    => (int) $post->ID,
        );
    }

    // An image of the media library for the editor: its address (small) and its file name.
    public static function image($id) {
        $url = wp_get_attachment_image_url($id, 'thumbnail');
        $file = get_attached_file($id);
        return array('url' => $url ? $url : '', 'name' => $file ? wp_basename($file) : '');
    }

    public static function editor($post) {
        wp_nonce_field('wpst_save', 'wpst_nonce');
        $boot = self::boot($post);
        echo '<div id="wpst-app"><script type="application/json" id="wpst-boot">' . wp_json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
        echo '<input type="hidden" name="wpst_data" id="wpst-data" value="' . esc_attr(wp_json_encode($boot['settings'])) . '">';
        // The tiles: Settings, then the steps in their order (drag to reorder), then Add step.
        echo '<fieldset class="wpst-section"><legend>' . esc_html__('Steps', 'wp-steps') . '</legend>';
        echo '<p class="description">' . esc_html__('Click a step to edit it in its window, drag it to change the order. Under them: the settings of all steps.', 'wp-steps') . '</p>';
        echo '<div class="wpst-strip" id="wpst-tiles"></div></fieldset>';
        // Left: the design in use, always in sight (a select as the icons', 2026-11-24, user's request), then the menu.
        echo '<div class="wpst-workspace"><div class="wpst-side"><div class="wpst-designselect" id="wpst-design-select"></div><nav class="wpst-menu" id="wpst-menu" aria-label="' . esc_attr__('Settings', 'wp-steps') . '"></nav></div>';
        // The block chosen in the menu, framed with its name (as WP Swiper's settings; 2026-11-23, user's request).
        echo '<fieldset class="wpst-section wpst-settings-box"><legend id="wpst-block-title"></legend><div class="wpst-settings" id="wpst-settings"></div></fieldset>';
        echo '<div class="wpst-preview"><div class="wpst-toolbar"><span class="wpst-preview-title">' . esc_html__('Preview', 'wp-steps') . '</span>';
        echo '<div class="wpst-preview-devices" role="group" aria-label="' . esc_attr__('Preview device', 'wp-steps') . '">';
        foreach (array('desktop' => array('desktop', __('Desktop', 'wp-steps')), 'tablet' => array('tablet', __('Tablet', 'wp-steps')), 'mobile' => array('smartphone', __('Mobile', 'wp-steps'))) as $device => $d) {
            echo '<button type="button" class="wpst-device-button" data-preview-device="' . esc_attr($device) . '" aria-pressed="false" aria-label="' . esc_attr($d[1]) . '" title="' . esc_attr($d[1]) . '"><span class="dashicons dashicons-' . esc_attr($d[0]) . '" aria-hidden="true"></span></button>';
        }
        echo '</div><button type="button" class="button" id="wpst-refresh">' . esc_html__('Update', 'wp-steps') . '</button></div>';
        echo '<div class="wpst-stage" id="wpst-stage"><iframe id="wpst-frame" title="' . esc_attr__('Steps preview', 'wp-steps') . '" sandbox="allow-same-origin"></iframe></div>';
        echo '<p class="description wpst-preview-note" id="wpst-screen"></p></div></div>';
        // A step's window (2026-11-24, user's request: as WP Swiper's slide window): its tiles above open it; its settings in tabs.
        echo '<div class="wpst-modal" id="wpst-modal" role="dialog" aria-modal="true" aria-labelledby="wpst-modal-title" hidden><div class="wpst-modal__backdrop"></div>'
            . '<div class="wpst-modal__box"><div class="wpst-modal__head"><span class="wpst-modal__thumb" id="wpst-modal-thumb" aria-hidden="true"></span><h2 class="wpst-modal__title" id="wpst-modal-title"></h2>'
            . '<button type="button" class="wpst-modal__close" data-modal-close aria-label="' . esc_attr__('Close', 'wp-steps') . '"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></div>'
            . '<div class="wpst-tabs wpst-modal__tabs" id="wpst-modal-tabs" role="tablist"></div><div class="wpst-modal__body" id="wpst-modal-body"></div>'
            // Cancel / Apply, as WP Swiper's (2026-11-24): Cancel (and the close button, Esc) puts the step back as it was.
            . '<div class="wpst-modal__foot"><button type="button" class="button" data-modal-close>' . esc_html__('Cancel', 'wp-steps') . '</button><button type="button" class="button button-primary" data-modal-apply>' . esc_html__('Apply', 'wp-steps') . '</button></div></div></div>';
        echo '</div>';
    }

    public static function save($id) {
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($id) || !current_user_can('edit_post', $id)) { return; }
        if (!isset($_POST['wpst_nonce']) || !is_string($_POST['wpst_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wpst_nonce'])), 'wpst_save')) { return; }
        if (!isset($_POST['wpst_data']) || !is_string($_POST['wpst_data'])) { return; }
        $json = wp_unslash($_POST['wpst_data']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON, sanitized below.
        if (strlen($json) > self::MAX_DATA) { return; }
        $data = json_decode($json, true);
        if (!is_array($data)) { return; }
        update_post_meta($id, WP_Steps_Settings::META, wp_slash(WP_Steps_Settings::sanitize($data)));
    }

    // The preview: a page with only the steps (the settings as they are in the editor, not saved), at the width the editor gives the frame.
    public static function preview() {
        check_ajax_referer(self::PREVIEW_ACTION, 'nonce');
        $id = isset($_POST['post_id']) && is_scalar($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if (!$id || get_post_type($id) !== WP_Steps_Settings::POST_TYPE || !current_user_can('edit_post', $id)) { wp_die('Forbidden', '', array('response' => 403)); }
        $json = isset($_POST['settings']) && is_string($_POST['settings']) ? wp_unslash($_POST['settings']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON, sanitized below.
        if ($json === '' || strlen($json) > self::MAX_DATA) { wp_die('Invalid', '', array('response' => 400)); }
        $s = WP_Steps_Settings::sanitize(json_decode($json, true));
        nocache_headers();
        header('Content-Type: text/html; charset=' . get_option('blog_charset'));
        echo self::preview_page($s); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
        exit;
    }

    public static function preview_page($s) {
        $design = WP_Steps_Designs::get($s['design']);
        $links = '<link rel="stylesheet" href="' . esc_url(WP_STEPS_URL . 'assets/css/frontend.css?ver=' . wp_steps_asset('assets/css/frontend.css')) . '">'
            . '<link rel="stylesheet" href="' . esc_url($design->css_url() . '?ver=' . $design->css_version()) . '">';
        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' . $links
            . '<style>html{scrollbar-width:none}body{margin:0;padding:40px 24px;background:#fff;font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif}</style></head><body>'
            . WP_Steps_Render::markup($s) . '</body></html>';
    }
}
