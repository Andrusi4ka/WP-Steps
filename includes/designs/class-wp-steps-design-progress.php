<?php
/**
 * Progress (2026-11-25, user's request, from a picture of three steppers): the steps side by side
 * in equal columns, each with its marker on one line (a number, a ring, a dot; None: the line as
 * a bar across all of them), its icon above it (Show icons) and its texts below, centred or from
 * the left. Folds into a line down the side, the texts beside the markers, on narrow blocks.
 */
defined('ABSPATH') || exit;

class WP_Steps_Design_Progress extends WP_Steps_Design {
    public function id() { return 'progress'; }
    public function name() { return __('Progress', 'wp-steps'); }
    public function about() { return __('Steps in a row: a marker for each on one line, the icon above it, the texts under it.', 'wp-steps'); }
    public function supports() { return array('marker', 'icon_style', 'image_size', 'progress_align', 'show_icons'); }
    public function stack_below() { return 640; }

    public function step($p, $s) {
        // With a numbered marker the number is there; otherwise it is the texts' label.
        $label = $s['marker'] === 'number' ? '' : $p['label'];
        // The line: from this marker to the next; without markers (None) a bar across every step, the last one's too.
        $link = !$p['last'] || $s['marker'] === 'none' ? '<span class="wpst-step__link" aria-hidden="true"></span>' : '';
        return '<div class="wpst-step__top">' . (!empty($s['show_icons']) ? $p['icon'] : '') . '</div>'
            . '<div class="wpst-step__rail">' . $p['marker'] . $link . '</div>'
            . '<div class="wpst-step__body">' . $label . $p['title'] . $p['text'] . '</div>';
    }

    public function thumb() {
        return '<svg viewBox="0 0 64 44" aria-hidden="true" focusable="false"><path d="M8 16H56" stroke="currentColor" stroke-width="1.6" opacity=".45"/>'
            . '<g fill="currentColor"><circle cx="8" cy="16" r="4.5"/><circle cx="24" cy="16" r="4.5" opacity=".8"/><circle cx="40" cy="16" r="4.5" opacity=".6"/><circle cx="56" cy="16" r="4.5" opacity=".4"/>'
            . '<rect x="3" y="26" width="10" height="2.5" rx="1" opacity=".6"/><rect x="19" y="26" width="10" height="2.5" rx="1" opacity=".6"/><rect x="35" y="26" width="10" height="2.5" rx="1" opacity=".6"/><rect x="51" y="26" width="10" height="2.5" rx="1" opacity=".6"/>'
            . '<rect x="4" y="31" width="8" height="2" rx="1" opacity=".3"/><rect x="20" y="31" width="8" height="2" rx="1" opacity=".3"/><rect x="36" y="31" width="8" height="2" rx="1" opacity=".3"/><rect x="52" y="31" width="8" height="2" rx="1" opacity=".3"/></g></svg>';
    }
}
