<?php
/**
 * WP Steps → Settings (under "Add new"): what each role may do with the step sets, as checkboxes per action (2026-11-25,
 * user's request: as WP Swiper's WP_Swiper_Roles, the same as WP Banner's WPBN_Access). Roles that manage the site
 * (manage_options: Administrators) always may do all of it, and only they open this page. Unset, WP Steps is theirs only.
 *
 * The rights are the plugin's own capabilities, given at run time (user_has_cap) from the option, never written into the
 * roles: deleting the plugin leaves the roles as they were. The post type maps its capabilities to them (wp-steps.php).
 */
defined('ABSPATH') || exit;

class WP_Steps_Access {
    const OPTION = 'wp_steps_roles';
    const GROUP = 'wp_steps_roles';
    const PAGE = 'wp-steps-settings';
    // Who always may do everything and alone sets what the other roles may.
    const CAPABILITY = 'manage_options';
    // The plugin's capabilities (the post type's map to them).
    const EDIT = 'wp_steps_edit_sets';
    const CREATE = 'wp_steps_create_sets';
    const DELETE = 'wp_steps_delete_sets';
    const WPBAKERY = 'wp_steps_wpbakery';

    // rights() as worked out (asked for on every capability check: worked out once, again when saved).
    private static $rights = null;

    public static function init() {
        add_filter('user_has_cap', array(__CLASS__, 'grant'), 10, 4);
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_init', array(__CLASS__, 'register'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue'));
        add_filter('plugin_action_links_' . plugin_basename(WP_STEPS_FILE), array(__CLASS__, 'plugin_link'));
        foreach (array('add_option_', 'update_option_', 'delete_option_') as $hook) {
            add_action($hook . self::OPTION, array(__CLASS__, 'forget'));
        }
    }

    /**
     * The actions a role may be given, in the table's order: key => [capability, name, what it allows]. Add new, Delete
     * and the WPBakery element: with Edit steps only (sanitize()).
     */
    public static function actions() {
        return array(
            'edit'     => array(self::EDIT, __('Edit steps', 'wp-steps'), __('WP Steps in the menu: open any set of steps, change it and its preview.', 'wp-steps')),
            'create'   => array(self::CREATE, __('Add new', 'wp-steps'), __('New sets of steps. With Edit steps only.', 'wp-steps')),
            'delete'   => array(self::DELETE, __('Delete', 'wp-steps'), __('Sets of steps to the Trash and for good. With Edit steps only.', 'wp-steps')),
            'wpbakery' => array(self::WPBAKERY, __('WPBakery element', 'wp-steps'), __('Put a set of steps on a page with the WPBakery element (the sets to choose from). With Edit steps only. Pages show the steps to everyone anyway.', 'wp-steps')),
        );
    }

    // May the current user do this? action: edit, create, delete or wpbakery.
    public static function can($action) {
        $actions = self::actions();
        return isset($actions[$action]) && current_user_can($actions[$action][0]);
    }

    // Whether a role manages the site (all of WP Steps, always).
    public static function is_full($role) {
        $object = get_role($role);
        return $object && $object->has_cap(self::CAPABILITY);
    }

    // The rights set, as saved (sanitize()): role => [action => true].
    public static function rights() {
        if (self::$rights === null) {
            $saved = get_option(self::OPTION, array());
            self::$rights = is_array($saved) ? self::sanitize($saved) : array();
        }
        return self::$rights;
    }

    // The option saved, added or deleted: rights() works them out again.
    public static function forget() {
        self::$rights = null;
    }

    // What is kept: the site's roles that do not manage it, the known actions, on; the others only with Edit steps.
    public static function sanitize($value) {
        $clean = array();
        if (!is_array($value)) { return $clean; }
        $roles = wp_roles()->roles;
        foreach ($value as $role => $actions) {
            $role = (string) $role;
            if (!isset($roles[$role]) || self::is_full($role) || !is_array($actions)) { continue; }
            $on = array();
            foreach (array_keys(self::actions()) as $action) {
                if (!empty($actions[$action]) && is_scalar($actions[$action])) { $on[$action] = true; }
            }
            if (empty($on['edit'])) { unset($on['create'], $on['delete'], $on['wpbakery']); }
            if ($on) { $clean[$role] = $on; }
        }
        return $clean;
    }

    // user_has_cap: the plugin's capabilities, from the user's roles. Those who manage the site have them all.
    public static function grant($allcaps, $caps, $args, $user) {
        $actions = self::actions();
        if (!empty($allcaps[self::CAPABILITY])) {
            foreach ($actions as $action) { $allcaps[$action[0]] = true; }
            return $allcaps;
        }
        $roles = isset($user->roles) ? (array) $user->roles : array();
        if (!$roles) { return $allcaps; }
        $rights = self::rights();
        foreach ($roles as $role) {
            if (empty($rights[$role])) { continue; }
            foreach ($rights[$role] as $action => $on) { $allcaps[$actions[$action][0]] = true; }
        }
        return $allcaps;
    }

    public static function register() {
        register_setting(self::GROUP, self::OPTION, array('type' => 'array', 'sanitize_callback' => array(__CLASS__, 'sanitize'), 'default' => array(), 'show_in_rest' => false));
    }

    // WP Steps → Settings, right under "Add new"; site managers only.
    public static function menu() {
        add_submenu_page('edit.php?post_type=' . WP_Steps_Settings::POST_TYPE, __('WP Steps settings', 'wp-steps'), __('Settings', 'wp-steps'), self::CAPABILITY, self::PAGE, array(__CLASS__, 'render'), 2);
    }

    // "Settings" beside Deactivate under Plugins, for those who may open it.
    public static function plugin_link($links) {
        if (current_user_can(self::CAPABILITY)) {
            array_unshift($links, '<a href="' . esc_url(admin_url('edit.php?post_type=' . WP_Steps_Settings::POST_TYPE . '&page=' . self::PAGE)) . '">' . esc_html__('Settings', 'wp-steps') . '</a>');
        }
        return $links;
    }

    // Its script (Add new, Delete, WPBakery element only with Edit) and styles, on it only.
    public static function enqueue($hook) {
        if (strpos((string) $hook, self::PAGE) === false) { return; }
        wp_enqueue_style('wpst-roles', WP_STEPS_URL . 'assets/css/roles.css', array(), wp_steps_asset('assets/css/roles.css'));
        wp_enqueue_script('wpst-roles', WP_STEPS_URL . 'assets/js/roles.js', array(), wp_steps_asset('assets/js/roles.js'), true);
    }

    // The page: a table of the site's roles and the actions, saved by WordPress's options.php (its nonce and capability).
    public static function render() {
        if (!current_user_can(self::CAPABILITY)) { return; }
        $actions = self::actions();
        $rights = self::rights();
        ?>
        <div class="wrap wpst-roles">
            <h1><?php esc_html_e('WP Steps settings', 'wp-steps'); ?></h1>
            <?php settings_errors(); ?>
            <form method="post" action="options.php">
                <?php settings_fields(self::GROUP); ?>
                <h2><?php esc_html_e('Roles', 'wp-steps'); ?></h2>
                <p class="description"><?php esc_html_e('What each role may do with the sets of steps. Roles that manage the site (Administrators) may do all of it, and only they open this page. Visitors see the steps on the pages whatever is set here.', 'wp-steps'); ?></p>
                <table class="widefat striped wpst-roles__table">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('Role', 'wp-steps'); ?></th>
                            <?php foreach ($actions as $key => $action) : ?>
                                <?php // What it allows on hover only (no text under the names, as WP Swiper's). ?>
                                <th scope="col" class="wpst-roles__action" title="<?php echo esc_attr($action[2]); ?>"><?php echo esc_html($action[1]); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (wp_roles()->roles as $role => $data) : ?>
                            <?php $full = self::is_full($role); ?>
                            <tr data-role="<?php echo esc_attr($role); ?>"<?php echo $full ? ' class="is-full"' : ''; ?>>
                                <th scope="row">
                                    <?php echo esc_html(translate_user_role($data['name'])); ?>
                                    <?php if ($full) : ?>
                                        <span class="wpst-roles__always"><?php esc_html_e('always all', 'wp-steps'); ?></span>
                                    <?php endif; ?>
                                </th>
                                <?php foreach ($actions as $key => $action) : ?>
                                    <td class="wpst-roles__action">
                                        <?php
                                        printf(
                                            '<input type="checkbox" id="%1$s" data-action="%2$s" aria-label="%3$s"%4$s>',
                                            esc_attr('wpst-role-' . $role . '-' . $key),
                                            esc_attr($key),
                                            /* translators: 1: an action (Edit steps), 2: a role (Editor). */
                                            esc_attr(sprintf(__('%1$s: %2$s', 'wp-steps'), $action[1], translate_user_role($data['name']))),
                                            // Administrators: shown on, never sent (no name).
                                            $full ? ' checked disabled' : ' name="' . esc_attr(self::OPTION . '[' . $role . '][' . $key . ']') . '" value="1"' . (empty($rights[$role][$key]) ? '' : ' checked') // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped here.
                                        );
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
