<?php
/**
 * Timeline: one line down the side, each step's marker (a number, a ring, a dot) on it, the
 * step's card on one side and a side text on the other; or the cards from side to side.
 */
defined('ABSPATH') || exit;

class WP_Steps_Design_Timeline extends WP_Steps_Design {
    public function id() { return 'timeline'; }
    public function name() { return __('Timeline', 'wp-steps'); }
    public function about() { return __('A line with a marker for each step: a side text on one side, the card on the other.', 'wp-steps'); }
    public function supports() { return array('icon_position', 'card_style', 'card_radius', 'card_border', 'shadow', 'icon_style', 'marker', 'alternate', 'aside', 'label_align', 'image_size'); }
    public function stack_below() { return 600; }

    public function step($p, $s) {
        // With a numbered marker the number is there; otherwise it is the card's label.
        $label = $s['marker'] === 'number' ? '' : $p['label'];
        return '<div class="wpst-step__side">' . $p['aside'] . '</div>'
            . '<div class="wpst-step__rail">' . $p['marker'] . '</div>'
            . '<div class="wpst-step__card"><div class="wpst-step__body">' . $label . $p['title'] . $p['text'] . '</div>' . $p['icon'] . '</div>';
    }

    public function thumb() {
        return '<svg viewBox="0 0 64 44" aria-hidden="true" focusable="false"><path d="M24 1v42" stroke="currentColor" stroke-width="2" opacity=".45"/>'
            . '<g fill="currentColor"><rect x="32" y="2" width="29" height="11" rx="3" opacity=".35"/><rect x="32" y="16.5" width="29" height="11" rx="3" opacity=".6"/><rect x="32" y="31" width="29" height="11" rx="3" opacity=".9"/>'
            . '<circle cx="24" cy="7.5" r="4.5"/><circle cx="24" cy="22" r="4.5"/><circle cx="24" cy="36.5" r="4.5"/>'
            . '<rect x="4" y="5" width="13" height="2.5" rx="1" opacity=".5"/><rect x="7" y="9" width="10" height="2" rx="1" opacity=".3"/>'
            . '<rect x="4" y="19.5" width="13" height="2.5" rx="1" opacity=".5"/><rect x="7" y="23.5" width="10" height="2" rx="1" opacity=".3"/>'
            . '<rect x="4" y="34" width="13" height="2.5" rx="1" opacity=".5"/><rect x="7" y="38" width="10" height="2" rx="1" opacity=".3"/></g></svg>';
    }
}
