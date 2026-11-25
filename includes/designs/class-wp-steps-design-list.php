<?php
/**
 * List: the steps one under another, each a card with its icon, linked by a line (an arrow).
 */
defined('ABSPATH') || exit;

class WP_Steps_Design_List extends WP_Steps_Design {
    public function id() { return 'list'; }
    public function name() { return __('List', 'wp-steps'); }
    public function about() { return __('Cards one under another, linked by a line or arrows.', 'wp-steps'); }
    public function supports() { return array('icon_position', 'card_style', 'card_radius', 'card_border', 'shadow', 'icon_style', 'arrow', 'label_align', 'image_size'); }

    public function step($p, $s) {
        return '<div class="wpst-step__card">' . $p['icon'] . '<div class="wpst-step__body">' . $p['label'] . $p['title'] . $p['text'] . '</div></div>'
            . ($p['last'] ? '' : '<span class="wpst-step__link" aria-hidden="true"></span>');
    }

    public function thumb() {
        return '<svg viewBox="0 0 64 44" aria-hidden="true" focusable="false"><g fill="currentColor"><rect x="10" y="2" width="44" height="10" rx="5" opacity=".35"/><rect x="10" y="17" width="44" height="10" rx="5" opacity=".6"/><rect x="10" y="32" width="44" height="10" rx="5" opacity=".9"/></g>'
            . '<g fill="#fff"><circle cx="15" cy="7" r="3.2"/><circle cx="15" cy="22" r="3.2"/><circle cx="15" cy="37" r="3.2"/></g>'
            . '<path d="M32 12v4M32 27v4" stroke="currentColor" stroke-width="1.5"/></svg>';
    }
}
