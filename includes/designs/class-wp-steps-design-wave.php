<?php
/**
 * Wave: the steps side by side, each icon in a circle with a half ring around it, below and
 * above in turn, so the rings make a wave; the texts below and above in turn. Folds into a
 * wave down the side (texts beside the circles) on narrow blocks.
 */
defined('ABSPATH') || exit;

class WP_Steps_Design_Wave extends WP_Steps_Design {
    public function id() { return 'wave'; }
    public function name() { return __('Wave', 'wp-steps'); }
    public function about() { return __('Circles side by side with half rings that make a wave; texts below and above in turn.', 'wp-steps'); }
    public function supports() { return array('icon_style', 'arc', 'label_align'); }
    public function stack_below() { return 720; }

    public function step($p, $s) {
        // Without an icon the circle shows the step's number.
        $icon = $p['icon'] !== '' ? $p['icon'] : '<span class="wpst-step__icon wpst-step__icon--number" aria-hidden="true">' . esc_html($p['number']) . '</span>';
        return '<div class="wpst-step__orb"><span class="wpst-step__arc" aria-hidden="true"></span>' . $icon
            . ($p['last'] ? '' : '<span class="wpst-step__link" aria-hidden="true"></span>') . '</div>'
            . '<div class="wpst-step__body">' . $p['label'] . $p['title'] . $p['text'] . '</div>';
    }

    public function thumb() {
        return '<svg viewBox="0 0 64 44" aria-hidden="true" focusable="false"><g fill="none" stroke="currentColor" stroke-width="2.6">'
            . '<path d="M3 20a7 7 0 0 0 14 0" opacity=".4"/><path d="M17 20a7 7 0 0 1 14 0" opacity=".6"/><path d="M31 20a7 7 0 0 0 14 0" opacity=".8"/><path d="M45 20a7 7 0 0 1 14 0"/></g>'
            . '<g fill="currentColor"><circle cx="10" cy="20" r="4"/><circle cx="24" cy="20" r="4"/><circle cx="38" cy="20" r="4"/><circle cx="52" cy="20" r="4"/>'
            . '<rect x="5" y="32" width="10" height="2.5" rx="1" opacity=".5"/><rect x="19" y="5" width="10" height="2.5" rx="1" opacity=".5"/><rect x="33" y="32" width="10" height="2.5" rx="1" opacity=".5"/><rect x="47" y="5" width="10" height="2.5" rx="1" opacity=".5"/></g></svg>';
    }
}
