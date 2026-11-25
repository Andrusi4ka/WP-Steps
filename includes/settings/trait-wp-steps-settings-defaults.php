<?php
/**
 * WP_Steps_Settings, part: The usual values: a new set (defaults()), a new step (step_defaults(), step_space_defaults()),
 * the sample steps of a new set.
 * The class (includes/class-wp-steps-settings.php) holds the constants; called here as WP_Steps_Settings:: (the same class
 * as self::, and the editor can follow it).
 */
defined('ABSPATH') || exit;

trait WP_Steps_Settings_Defaults {
    public static function defaults() {
        $d = array(
            'design'        => WP_Steps_Designs::DEFAULT_DESIGN,
            'steps'         => WP_Steps_Settings::sample_steps(),
            'icon_position' => '',
            'icon_align'    => '',
            'label_align'   => '',
            'progress_align' => 'center',
            // Progress: the steps' icons above the line (2026-11-25).
            'show_icons'    => true,
            // Per device (2026-11-24, user's request): tablet / phone null = the larger screen's; 'auto' there = the design's own.
            'icon_position_tablet' => null, 'icon_position_mobile' => null, 'icon_align_tablet' => null, 'icon_align_mobile' => null,
            // The cards' box (2026-11-25, user's request: Margin, Border, Radius and Padding in one block, no switches): border widths px
            // and corners (px | %) per device, 0 on Desktop (no border, square); padding / margin per side and device, none = the design's
            // own; tablet / phone null = the larger screen's. The border's line and color ('' = the step's color).
            'card_border_top' => 0, 'card_border_top_tablet' => null, 'card_border_top_mobile' => null, 'card_border_right' => 0, 'card_border_right_tablet' => null, 'card_border_right_mobile' => null, 'card_border_bottom' => 0, 'card_border_bottom_tablet' => null, 'card_border_bottom_mobile' => null, 'card_border_left' => 0, 'card_border_left_tablet' => null, 'card_border_left_mobile' => null,
            'card_border_linked' => true, 'card_border_style' => 'solid', 'card_border_color' => '',
            'card_radius_tl' => 0, 'card_radius_tl_tablet' => null, 'card_radius_tl_mobile' => null, 'card_radius_tl_unit' => 'px', 'card_radius_tr' => 0, 'card_radius_tr_tablet' => null, 'card_radius_tr_mobile' => null, 'card_radius_tr_unit' => 'px', 'card_radius_br' => 0, 'card_radius_br_tablet' => null, 'card_radius_br_mobile' => null, 'card_radius_br_unit' => 'px', 'card_radius_bl' => 0, 'card_radius_bl_tablet' => null, 'card_radius_bl_mobile' => null, 'card_radius_bl_unit' => 'px',
            'card_radius_linked' => true,
            'card_padding_top' => null, 'card_padding_top_tablet' => null, 'card_padding_top_mobile' => null, 'card_padding_top_unit' => 'px', 'card_padding_right' => null, 'card_padding_right_tablet' => null, 'card_padding_right_mobile' => null, 'card_padding_right_unit' => 'px', 'card_padding_bottom' => null, 'card_padding_bottom_tablet' => null, 'card_padding_bottom_mobile' => null, 'card_padding_bottom_unit' => 'px', 'card_padding_left' => null, 'card_padding_left_tablet' => null, 'card_padding_left_mobile' => null, 'card_padding_left_unit' => 'px', 'card_padding_linked' => true,
            'card_margin_top' => null, 'card_margin_top_tablet' => null, 'card_margin_top_mobile' => null, 'card_margin_top_unit' => 'px', 'card_margin_right' => null, 'card_margin_right_tablet' => null, 'card_margin_right_mobile' => null, 'card_margin_right_unit' => 'px', 'card_margin_bottom' => null, 'card_margin_bottom_tablet' => null, 'card_margin_bottom_mobile' => null, 'card_margin_bottom_unit' => 'px', 'card_margin_left' => null, 'card_margin_left_tablet' => null, 'card_margin_left_mobile' => null, 'card_margin_left_unit' => 'px', 'card_margin_linked' => true,
            'shadow'        => true,
            // The cards' shadow (2026-11-23, user's request: as WP Swiper's / WP Banner's): as it was drawn before.
            'card_shadow_x' => 0, 'card_shadow_y' => 14, 'card_shadow_blur' => 28, 'card_shadow_spread' => -16, 'card_shadow_color' => 'rgba(15,23,42,0.45)',
            'marker'        => 'ring',
            'line'          => 'dotted',
            'arrow'         => true,
            'alternate'     => false,
            'show_label'    => true,
            'label_word'    => 'Step',
            'number_format' => '01',
            'color_from'    => '#17a2b8',
            'color_to'      => '#34466b',
            'label_color'   => '',
            'line_color'    => '',
            // Settings → Custom (2026-11-24, user's request): Padding/Margin behind its switch (as WP Banner's), the block's own id and classes.
            'use_padding'   => false,
        ) + WP_Steps_Settings::space_defaults() + array('css_id' => '', 'css_class' => '');
        foreach (WP_Steps_Settings::DEVICE as $key => $default) {
            $d[$key] = $default;
            $d[$key . '_tablet'] = null;
            $d[$key . '_mobile'] = null;
        }
        return $d;
    }

    public static function step_defaults() {
        // text_size (+ _tablet, _mobile), text_weight, text_color (2026-11-23, user's request, under the Text editor as WP Swiper's):
        // the step's own; null / '' = the usual (the set's text_size, the design's grey / white).
        // The title's own (2026-11-23, user's request: a tab of its own with the editor): title_align, title_size (+ _tablet, _mobile),
        // title_weight, title_color, title_tag; '' / null = the usual (WP_Steps_Settings::TITLE_TAG, the set's title_size, the design's color).
        return array('title' => '', 'title_align' => '', 'title_size' => null, 'title_size_tablet' => null, 'title_size_mobile' => null, 'title_weight' => '', 'title_color' => '', 'title_tag' => '',
            'text' => '', 'text_size' => null, 'text_size_tablet' => null, 'text_size_mobile' => null, 'text_weight' => '', 'text_color' => '',
            'aside_title' => '', 'aside_text' => '',
            // The side title's tag and the side title's / text's font size, weight and color per device (2026-11-24, user's request):
            // '' / null = the larger screen's, else the usual (the title's size × .95, 600, the step's color; the text's × .92, the grey).
            'aside_title_tag' => '', 'aside_title_size' => null, 'aside_title_size_tablet' => null, 'aside_title_size_mobile' => null, 'aside_title_weight' => '', 'aside_title_weight_tablet' => '', 'aside_title_weight_mobile' => '', 'aside_title_color' => '', 'aside_title_color_tablet' => '', 'aside_title_color_mobile' => '',
            'aside_text_size' => null, 'aside_text_size_tablet' => null, 'aside_text_size_mobile' => null, 'aside_text_weight' => '', 'aside_text_weight_tablet' => '', 'aside_text_weight_mobile' => '', 'aside_text_color' => '', 'aside_text_color_tablet' => '', 'aside_text_color_mobile' => '',
            'icon' => '', 'image' => 0, 'color' => '',
            // The icon's own (2026-11-23, user's request: everything about the icon in the step's Icon block): its style (white circle,
            // color circle, icon only: only read for steps saved with one, no field), circle size per device and icon color; null = the set's size.
            'icon_style' => '', 'icon_size' => null, 'icon_size_tablet' => null, 'icon_size_mobile' => null, 'icon_color' => '',
            // icon_scale: the icon in its circle, % of it (null: 44%, an image 52%, icon only 50% of Size). 2026-11-23, user's request.
            // The circle (2026-11-23, user's idea, instead of the Style select): icon_shape filled / outline / none and icon_bg (its fill
            // or ring); '' = as the step's old style gives (icon_circle()), else a filled white circle.
            // What it shows (2026-11-23, user's request: Icon | Image first in the block): '' = its image if it has one, else its icon.
            'media' => '',
            // The whole step as a link (2026-11-24, user's request: its window's Link tab).
            'link' => '', 'link_new_tab' => false,
            'icon_shape' => '', 'icon_bg' => '',
            // The circle's shadow (2026-11-23, user's request: in the step): null = the cards' Shadow where the design has cards, else none.
            'icon_shadow' => null,
            // Its offsets, blur, spread and color: null / '' = as it was drawn before (ICON_SHADOW).
            'icon_shadow_x' => null, 'icon_shadow_y' => null, 'icon_shadow_blur' => null, 'icon_shadow_spread' => null, 'icon_shadow_color' => '',
            // Its card's shadow (2026-11-25, user's request: as the cards' in the Settings): null = the cards' switch; its parts null / ''
            // = the cards'.
            // Its image's box (2026-11-25, user's request: as Woodmart's Image element): image_size '' (a square: Size, the image inside at
            // Content size) | ratio (Size wide, image_ratio* tall, e.g. 16/9) | custom (Size wide, image_height* px); the image filling it by
            // image_fit (cover | contain | fill | none) at image_position; per device: '' / null = the larger screen's.
            'image_size' => '', 'image_ratio' => '', 'image_ratio_tablet' => '', 'image_ratio_mobile' => '', 'image_height' => null, 'image_height_tablet' => null, 'image_height_mobile' => null, 'image_fit' => '', 'image_position' => '',
            'shadow' => null, 'shadow_x' => null, 'shadow_y' => null, 'shadow_blur' => null, 'shadow_spread' => null, 'shadow_color' => '',
            // The circle's corners (2026-11-23, user's request: WP Banner's Radius box): per corner and device, null = the larger
            // screen's, else 50 % (a circle); each corner's unit px or %, one for every device; linked: set together.
            'icon_radius_tl' => null, 'icon_radius_tl_tablet' => null, 'icon_radius_tl_mobile' => null, 'icon_radius_tl_unit' => '%', 'icon_radius_tr' => null, 'icon_radius_tr_tablet' => null, 'icon_radius_tr_mobile' => null, 'icon_radius_tr_unit' => '%', 'icon_radius_br' => null, 'icon_radius_br_tablet' => null, 'icon_radius_br_mobile' => null, 'icon_radius_br_unit' => '%', 'icon_radius_bl' => null, 'icon_radius_bl_tablet' => null, 'icon_radius_bl_mobile' => null, 'icon_radius_bl_unit' => '%', 'icon_radius_linked' => true,
            // Behind its switch (2026-11-23, user's request, as WP Banner's Radius): on, the corners (none of its own: a circle); off
            // (2026-11-24, user's choice), no rounding: a square, the corners kept.
            'icon_use_radius' => true,
            'icon_scale' => null, 'icon_scale_tablet' => null, 'icon_scale_mobile' => null)
            // Its Padding (inside its card; Wave: its texts) and Margin (around it), per side and device (2026-11-24, user's request:
            // the window's Style tab, WP Banner's widget): null = the larger screen's, else the design's own.
            + WP_Steps_Settings::step_space_defaults();
    }
    public static function step_space_defaults() {
        $out = array();
        foreach (array('padding', 'margin') as $kind) {
            foreach (WP_Steps_Settings::SIDES as $side) { $out[$kind . '_' . $side] = null; $out[$kind . '_' . $side . '_tablet'] = null; $out[$kind . '_' . $side . '_mobile'] = null; $out[$kind . '_' . $side . '_unit'] = 'px'; }
            $out[$kind . '_linked'] = true;
        }
        // Its card's Border and Radius (2026-11-25, user's request: the same box as the cards'): none = the cards'.
        foreach (WP_Steps_Settings::SIDES as $side) { $out['border_' . $side] = null; $out['border_' . $side . '_tablet'] = null; $out['border_' . $side . '_mobile'] = null; }
        $out['border_linked'] = true; $out['border_style'] = ''; $out['border_color'] = '';
        foreach (WP_Steps_Settings::CORNERS as $c) { $out['radius_' . $c] = null; $out['radius_' . $c . '_tablet'] = null; $out['radius_' . $c . '_mobile'] = null; $out['radius_' . $c . '_unit'] = 'px'; }
        $out['radius_linked'] = true;
        return $out;
    }

    // A new set's steps: something to look at in the preview right away.
    public static function sample_steps() {
        $steps = array();
        foreach (array(array('Plan', 'lightbulb'), array('Prepare', 'clock'), array('Agree', 'team'), array('Reach the goal', 'target')) as $s) {
            $steps[] = array_merge(WP_Steps_Settings::step_defaults(), array('title' => $s[0], 'icon' => $s[1],
                'text' => 'A short description of this step: what happens and what comes out of it.'));
        }
        return $steps;
    }
}
