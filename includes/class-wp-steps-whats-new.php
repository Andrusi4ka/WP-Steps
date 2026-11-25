<?php
/**
 * "What's new" after an update (2026-11-25, user's request, as WP Swiper's and WP Banner's): the changes of the versions
 * since the one a user last saw, from the plugin's own readme.txt (its Changelog), in a window on the plugin's screens and,
 * on the Dashboard, a notice that opens it. Once for each user who can edit the steps; not after a first install.
 */
defined('ABSPATH') || exit;

class WP_Steps_Whats_New {
    // The version the site ran last (an update shows when it changes).
    const OPTION_VERSION = 'wp_steps_version';
    // The version updated from: the changes after it are new. '' unknown (sets made before this option): the current one's only.
    const OPTION_FROM = 'wp_steps_updated_from';
    // Per user: the version whose changes they have seen.
    const META_SEEN = 'wp_steps_seen_version';
    const AJAX = 'wp_steps_whats_new_seen';

    // Versions to show (entries()), per user ID, once worked out.
    private static $entries = array();

    public static function init() {
        add_action('admin_init', array(__CLASS__, 'track_version'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue'));
        add_action('admin_notices', array(__CLASS__, 'notice'));
        add_action('admin_footer', array(__CLASS__, 'render_modal'));
        add_action('wp_ajax_' . self::AJAX, array(__CLASS__, 'ajax_seen'));
    }

    /**
     * A new version: remember it and the one before. A first install (no version stored, no sets yet) has nothing new to
     * show; a site with sets but no version stored ran a version before this option, which one is unknown.
     */
    public static function track_version() {
        $stored = get_option(self::OPTION_VERSION);
        if ($stored === WP_STEPS_VERSION) { return; }
        $from = $stored === false ? (self::has_sets() ? '' : WP_STEPS_VERSION) : (string) $stored;
        update_option(self::OPTION_VERSION, WP_STEPS_VERSION);
        update_option(self::OPTION_FROM, $from);
        self::$entries = array();
    }

    private static function has_sets() {
        return (bool) get_posts(array('post_type' => WP_Steps_Settings::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => true));
    }

    // The Changelog of a readme.txt: version => its items (text), newest first.
    public static function parse_changelog($readme) {
        $readme = str_replace("\r\n", "\n", (string) $readme);
        if (!preg_match('/^==\s*Changelog\s*==\s*$(.*?)(?=^==[^=]|\z)/ims', $readme, $section)) { return array(); }
        $versions = array();
        $current = null;
        foreach (explode("\n", $section[1]) as $line) {
            if (preg_match('/^=\s*v?(\d+(?:\.\d+)*)\s*=\s*$/', trim($line), $match)) {
                $current = $match[1];
                $versions[$current] = array();
            } elseif ($current !== null && preg_match('/^\s*[*-]\s+(.+)$/', $line, $match)) {
                $versions[$current][] = trim($match[1]);
            } elseif ($current !== null && trim($line) !== '' && $versions[$current]) {
                // A wrapped item: its next line.
                $versions[$current][count($versions[$current]) - 1] .= ' ' . trim($line);
            }
        }
        return array_filter($versions);
    }

    // The versions after $from up to $to, newest first; $from '': $to only.
    public static function between($changelog, $from, $to) {
        $out = array();
        foreach ($changelog as $version => $items) {
            $version = (string) $version;
            $newer = $from === '' ? version_compare($version, $to, '==') : version_compare($version, $from, '>');
            if ($newer && version_compare($version, $to, '<=')) { $out[$version] = $items; }
        }
        uksort($out, function ($a, $b) { return version_compare($b, $a); });
        return $out;
    }

    // What to show the current user: the versions since the one they saw (else the one the site was updated from). None for
    // those who cannot edit the steps, after a first install or once seen.
    public static function entries() {
        $user = get_current_user_id();
        if (!isset(self::$entries[$user])) { self::$entries[$user] = self::work_out(); }
        return self::$entries[$user];
    }

    private static function work_out() {
        if (!WP_Steps_Access::can('edit')) { return array(); }
        $seen = (string) get_user_meta(get_current_user_id(), self::META_SEEN, true);
        $from = $seen !== '' ? $seen : (string) get_option(self::OPTION_FROM, WP_STEPS_VERSION);
        if ($from !== '' && version_compare($from, WP_STEPS_VERSION, '>=')) { return array(); }
        $readme = WP_STEPS_DIR . 'readme.txt';
        $text = is_readable($readme) ? (string) file_get_contents($readme) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
        return self::between(self::parse_changelog($text), $from, WP_STEPS_VERSION);
    }

    // The window opens by itself on the plugin's screens; the Dashboard has the notice that opens it. 'plugin', 'dashboard' or ''.
    private static function place() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen) { return ''; }
        if ($screen->post_type === WP_Steps_Settings::POST_TYPE) { return 'plugin'; }
        return $screen->id === 'dashboard' ? 'dashboard' : '';
    }

    public static function enqueue() {
        if (!self::place() || !self::entries()) { return; }
        wp_enqueue_style('wpst-whats-new', WP_STEPS_URL . 'assets/css/whats-new.css', array(), wp_steps_asset('assets/css/whats-new.css'));
        wp_enqueue_script('wpst-whats-new', WP_STEPS_URL . 'assets/js/whats-new.js', array('jquery'), wp_steps_asset('assets/js/whats-new.js'), true);
        wp_localize_script('wpst-whats-new', 'wpstWhatsNew', array(
            'ajaxUrl'  => admin_url('admin-ajax.php'),
            'action'   => self::AJAX,
            'nonce'    => wp_create_nonce(self::AJAX),
            'autoOpen' => self::place() === 'plugin',
        ));
    }

    public static function notice() {
        if (self::place() !== 'dashboard' || !self::entries()) { return; }
        printf(
            '<div class="notice notice-info is-dismissible wpst-news-notice"><p>%s <button type="button" class="button-link wpst-news-open">%s</button></p></div>',
            /* translators: %s: plugin version. */
            esc_html(sprintf(__('WP Steps has been updated to %s.', 'wp-steps'), WP_STEPS_VERSION)),
            esc_html__('See what\'s new', 'wp-steps')
        );
    }

    public static function render_modal() {
        $entries = self::place() ? self::entries() : array();
        if (!$entries) { return; }
        ?>
        <div class="wpst-news" role="dialog" aria-modal="true" aria-labelledby="wpst-news-title" hidden>
            <div class="wpst-news__backdrop"></div>
            <div class="wpst-news__box">
                <div class="wpst-news__head">
                    <h2 class="wpst-news__title" id="wpst-news-title">
                        <?php
                        /* translators: %s: plugin version. */
                        echo esc_html(sprintf(__('What\'s new in WP Steps %s', 'wp-steps'), WP_STEPS_VERSION));
                        ?>
                    </h2>
                    <button type="button" class="wpst-news__close" aria-label="<?php esc_attr_e('Close', 'wp-steps'); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
                </div>
                <div class="wpst-news__body">
                    <?php foreach ($entries as $version => $items) : ?>
                        <?php // The version's heading only when there are several. ?>
                        <?php if (count($entries) > 1) : ?>
                            <h3 class="wpst-news__version"><?php echo esc_html($version); ?></h3>
                        <?php endif; ?>
                        <ul class="wpst-news__list">
                            <?php foreach ($items as $item) : ?>
                                <li><?php echo esc_html($item); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>
                </div>
                <div class="wpst-news__foot">
                    <a class="wpst-news__all" href="<?php echo esc_url(WP_STEPS_REPOSITORY . 'releases'); ?>" target="_blank" rel="noopener"><?php esc_html_e('All releases', 'wp-steps'); ?></a>
                    <button type="button" class="button button-primary wpst-news__done"><?php esc_html_e('Got it', 'wp-steps'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    // Seen: not again for this user until the next version.
    public static function mark_seen() {
        update_user_meta(get_current_user_id(), self::META_SEEN, WP_STEPS_VERSION);
        unset(self::$entries[get_current_user_id()]);
    }

    public static function ajax_seen() {
        check_ajax_referer(self::AJAX);
        if (!is_user_logged_in()) { wp_send_json_error(null, 403); }
        self::mark_seen();
        wp_send_json_success();
    }
}
