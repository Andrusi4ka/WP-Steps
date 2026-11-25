<?php
/**
 * Zigzag: the cards from side to side, a marker beside each, a curved line from marker to
 * marker and the step's large number on the other side.
 */
defined('ABSPATH') || exit;

class WP_Steps_Design_Zigzag extends WP_Steps_Design {
    public function id() { return 'zigzag'; }
    public function name() { return __('Zigzag', 'wp-steps'); }
    public function about() { return __('Cards from side to side with a large number and a curved line between the markers.', 'wp-steps'); }
    public function supports() { return array('icon_position', 'card_style', 'card_radius', 'card_border', 'shadow', 'icon_style', 'marker', 'line_radius', 'image_size'); }
    public function stack_below() { return 640; }

    public function step($p, $s) {
        return '<div class="wpst-step__card"><div class="wpst-step__body">' . $p['title'] . $p['text'] . '</div>' . $p['icon'] . '</div>'
            . '<div class="wpst-step__point">' . $p['marker'] . '</div>'
            . '<div class="wpst-step__big">' . $p['label'] . '</div>'
            . ($p['last'] ? '' : '<span class="wpst-step__curve" aria-hidden="true"><i></i><i></i></span>');
    }

    public function thumb() {
        return '<svg viewBox="0 0 64 44" aria-hidden="true" focusable="false"><path d="M45 8c0 7-26 7-26 14s26 7 26 14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-dasharray="0 3" opacity=".7"/>'
            . '<g fill="currentColor"><rect x="3" y="3" width="35" height="10" rx="5" opacity=".4"/><rect x="26" y="17" width="35" height="10" rx="5" opacity=".65"/><rect x="3" y="31" width="35" height="10" rx="5" opacity=".9"/>'
            . '<circle cx="45" cy="8" r="2.6"/><circle cx="19" cy="22" r="2.6"/><circle cx="45" cy="36" r="2.6"/>'
            . '<rect x="51" y="5" width="9" height="6" rx="1" opacity=".5"/><rect x="4" y="19" width="9" height="6" rx="1" opacity=".5"/><rect x="51" y="33" width="9" height="6" rx="1" opacity=".5"/></g></svg>';
    }
}
