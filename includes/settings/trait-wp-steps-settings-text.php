<?php
/**
 * WP_Steps_Settings, part: Texts from the editors: a step's Text and Title (rich_text(), rich_title()), plain texts
 * written before the editors (is_rich()), links (link(), noopener()).
 * The class (includes/class-wp-steps-settings.php) holds the constants; called here as WP_Steps_Settings:: (the same class
 * as self::, and the editor can follow it).
 */
defined('ABSPATH') || exit;

trait WP_Steps_Settings_Text {
    public static function rich_text($html) {
        $html = preg_replace_callback('#<(p|h[1-6]|li)\b([^>]*)>#i', function ($m) {
            return '<' . strtolower($m[1]) . (preg_match('/text-align\s*:\s*(left|center|right|justify)/i', $m[2], $a) ? ' style="text-align: ' . strtolower($a[1]) . '"' : '') . '>';
        }, (string) $html);
        return WP_Steps_Settings::clean_html($html, WP_Steps_Settings::RICH_TAGS);
    }
    // Only these tags (and RICH_STYLES), links in another tab with noopener; '' when no words are left (only tags, spaces).
    public static function clean_html($html, $tags) {
        $only = function () { return WP_Steps_Settings::RICH_STYLES; };
        add_filter('safe_style_css', $only, 999);
        $html = trim(wp_kses($html, $tags));
        remove_filter('safe_style_css', $only, 999);
        $html = WP_Steps_Settings::noopener($html);
        $text = preg_replace('/[\s\x{00A0}]+/u', '', html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES, 'UTF-8'));
        return (string) $text === '' ? '' : $html;
    }
    // Written before the editor (plain lines): its line breaks kept (WP_Steps_Render). With paragraphs or headings: as it is.
    public static function is_rich($html) { return (bool) preg_match('#<(p|h[1-6]|ul|ol)[\s>]#i', (string) $html); }

    // A step's link: http(s), mailto:, tel:, a page's own path or #anchor; anything else (javascript: and the like) none.
    public static function link($value) {
        $value = trim((string) $value);
        if ($value === '') { return ''; }
        if (!preg_match('#^(https?://|mailto:|tel:|/|\#)#i', $value)) { $value = preg_match('#^[a-z][a-z0-9+.-]*:#i', $value) ? '' : 'https://' . $value; }
        return $value === '' ? '' : esc_url_raw($value, array('http', 'https', 'mailto', 'tel'));
    }

    /*
     * A step's Title from the editor: one line of words (the Tag decides its element, which holds no paragraphs): bold, italic,
     * underline, a color, links, line breaks. Its paragraphs become line breaks, the first one's alignment its own (title_align).
     * A title written before it (plain text) is escaped. Returns [html, align or null when it says none].
     */
    public static function rich_title($html) {
        $html = trim((string) $html);
        $align = null;
        if (preg_match('#<(p|h[1-6]|div)\b#i', $html)) {
            $align = preg_match('#<(?:p|h[1-6]|div)\b[^>]*text-align\s*:\s*(left|center|right)#i', $html, $m) ? strtolower($m[1]) : '';
            $html = preg_replace('#</(?:p|h[1-6]|div|li)>\s*(?:</?(?:ul|ol)\b[^>]*>\s*)*<(?:p|h[1-6]|div|li)\b[^>]*>#i', '<br>', $html);
            $html = trim(preg_replace('#</?(?:p|h[1-6]|div|ul|ol|li)\b[^>]*>#i', '', $html));
        } elseif (strpos($html, '<') === false) {
            $html = htmlspecialchars($html, ENT_NOQUOTES, 'UTF-8', false);
        }
        return array(WP_Steps_Settings::clean_html($html, WP_Steps_Settings::TITLE_TAGS), $align);
    }
    // A link opening in another tab: "noopener" in its rel.
    public static function noopener($html) {
        return preg_replace_callback('#<a\s[^>]*\btarget="[^"]*"[^>]*>#i', function ($m) {
            if (preg_match('#\brel="([^"]*)"#i', $m[0], $rel)) {
                return preg_match('#(^|\s)noopener(\s|$)#i', $rel[1]) ? $m[0] : str_replace($rel[0], 'rel="' . trim($rel[1] . ' noopener') . '"', $m[0]);
            }
            return substr($m[0], 0, -1) . ' rel="noopener">';
        }, $html);
    }
}
