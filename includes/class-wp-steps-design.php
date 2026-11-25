<?php
/**
 * A design: how the steps are drawn. Each one is a class of its own (includes/designs/) with
 * a stylesheet of its own (assets/css/designs/{id}.css, loaded only where it is used). The
 * steps' data and the common settings (colors, sizes, numbering) are the same for all of
 * them; what a design does not use is greyed in the editor (supports()).
 *
 * A new design: a subclass here, its CSS, its entry in WP_Steps_Designs::all() (or the
 * wp_steps_designs filter) and its tests.
 */
defined('ABSPATH') || exit;

abstract class WP_Steps_Design {
    // Its key in the settings: lowercase letters, digits and dashes.
    abstract public function id();
    // Its name in the editor.
    abstract public function name();
    // One line on what it looks like.
    abstract public function about();

    /**
     * One step's markup inside its <li>.
     *
     * @param array $p The step's parts (WP_Steps_Render::parts()): index, last, number, word,
     *                 label, icon, title, text, aside, marker, empty_aside.
     * @param array $s The settings.
     */
    abstract public function step($p, $s);

    /**
     * The settings of WP_Steps_Settings::OPTIONAL it uses. The others are greyed in the editor
     * while it is chosen (their values kept).
     */
    public function supports() { return array(); }

    // The block width (px) below which it folds into one column; 0: never. Its CSS uses the same number.
    public function stack_below() { return 0; }

    // A small drawing of it for the editor's design tiles (an SVG, 64 × 44).
    public function thumb() { return ''; }

    // Its stylesheet's address and version.
    public function css_url() { return WP_STEPS_URL . 'assets/css/designs/' . $this->id() . '.css'; }
    public function css_version() { return wp_steps_asset('assets/css/designs/' . $this->id() . '.css'); }
}
