<?php
/**
 * The plugin's own line icons: drawn on a 24 × 24 grid, in the color of the text around them
 * (stroke: currentColor), so a step's icon takes its step's color or white. A step can also use
 * an image of its own (the media library) instead.
 */
defined('ABSPATH') || exit;

class WP_Steps_Icons {
    // name => [label, the drawing inside the <svg>].
    const ICONS = array(
        'lightbulb' => array('Idea', '<path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 0 0-3.6 10.8c.6.5 1 1.2 1.1 2V16h5v-.2c.1-.8.5-1.5 1.1-2A6 6 0 0 0 12 3z"/>'),
        'target'    => array('Target', '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.2"/><path d="M12 12l7-7M16 5h3v3"/>'),
        'clock'     => array('Clock', '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'),
        'cycle'     => array('Cycle', '<path d="M20 12a8 8 0 0 1-14 5.3M4 12a8 8 0 0 1 14-5.3"/><path d="M18 3v4h-4M6 21v-4h4"/>'),
        'team'      => array('Team', '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.6 3.2-6 6.5-6s5.9 2.4 6.5 6"/><circle cx="17" cy="9" r="2.5"/><path d="M16.5 14c2.6.2 4.4 2.2 5 5"/>'),
        'user'      => array('Person', '<circle cx="12" cy="8" r="4"/><path d="M4 21c.8-4 4-6.5 8-6.5s7.2 2.5 8 6.5"/>'),
        'search'    => array('Search', '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>'),
        'pie'       => array('Pie chart', '<path d="M12 3a9 9 0 1 0 9 9h-9z"/><path d="M15 2.6V9h6.4A9 9 0 0 0 15 2.6z"/>'),
        'chart'     => array('Bar chart', '<path d="M4 20h16"/><rect x="5.5" y="11" width="3" height="6"/><rect x="10.5" y="6" width="3" height="11"/><rect x="15.5" y="13" width="3" height="4"/>'),
        'briefcase' => array('Briefcase', '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M3 12.5h18"/>'),
        'settings'  => array('Settings', '<circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="6.5"/><path d="M12 2.5v3M12 18.5v3M2.5 12h3M18.5 12h3M5.3 5.3l2.1 2.1M16.6 16.6l2.1 2.1M5.3 18.7l2.1-2.1M16.6 7.4l2.1-2.1"/>'),
        'globe'     => array('Globe', '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3c2.5 2.5 3.8 5.5 3.8 9s-1.3 6.5-3.8 9c-2.5-2.5-3.8-5.5-3.8-9S9.5 5.5 12 3z"/>'),
        'home'      => array('Home', '<path d="M3 11 12 3.5 21 11"/><path d="M5 9.5V20h14V9.5M10 20v-5h4v5"/>'),
        'money'     => array('Money', '<circle cx="12" cy="12" r="9"/><path d="M15 8.5c-.6-.9-1.7-1.5-3-1.5-1.7 0-3 .9-3 2.2 0 3 6 1.6 6 4.6 0 1.3-1.3 2.2-3 2.2-1.4 0-2.6-.6-3.2-1.6M12 5.5V7M12 16v1.5"/>'),
        'rocket'    => array('Launch', '<path d="M9 15 6.5 12.5c1.5-4.5 5.5-8.5 13-9-.5 7.5-4.5 11.5-9 13z"/><circle cx="15" cy="9" r="1.5"/><path d="M7.5 13.5 4 13l3-4h4M10.5 16.5 11 20l4-3v-4"/><path d="M6 18c-1 .3-1.7 1-2 2 1-.3 1.7-1 2-2z"/>'),
        'flag'      => array('Flag', '<path d="M5 21V4"/><path d="M5 4h11l-2 4 2 4H5"/>'),
        'check'     => array('Done', '<circle cx="12" cy="12" r="9"/><path d="m8 12.5 2.5 2.5L16 9.5"/>'),
        'star'      => array('Star', '<path d="m12 3.5 2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8L3.5 9.7l5.9-.9z"/>'),
        'trophy'    => array('Trophy', '<path d="M8 4h8v5a4 4 0 0 1-8 0z"/><path d="M8 6H5a3 3 0 0 0 3 4M16 6h3a3 3 0 0 1-3 4M12 13v4M8.5 20h7M10 17h4"/>'),
        'chat'      => array('Chat', '<path d="M4 5h16v11H9l-5 4z"/><path d="M8 9.5h8M8 12.5h5"/>'),
        'mail'      => array('Mail', '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6.5 8.5-6.5"/>'),
        'phone'     => array('Phone', '<path d="M5 3.5h3.5l1.5 4-2.2 1.4a11 11 0 0 0 5.3 5.3l1.4-2.2 4 1.5V17a2 2 0 0 1-2 2A15 15 0 0 1 3 5.5a2 2 0 0 1 2-2z"/>'),
        'calendar'  => array('Calendar', '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>'),
        'pin'       => array('Place', '<path d="M12 21s-6.5-6-6.5-11a6.5 6.5 0 0 1 13 0c0 5-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.5"/>'),
        'heart'     => array('Heart', '<path d="M12 20s-7.5-4.6-7.5-10A4.3 4.3 0 0 1 12 7.3 4.3 4.3 0 0 1 19.5 10c0 5.4-7.5 10-7.5 10z"/>'),
        'shield'    => array('Shield', '<path d="M12 3 5 6v5.5c0 4.5 3 7.8 7 9.5 4-1.7 7-5 7-9.5V6z"/><path d="m9 12 2 2 4-4"/>'),
        'layers'    => array('Layers', '<path d="m12 4 9 4.5-9 4.5-9-4.5z"/><path d="m3 13 9 4.5 9-4.5M3 16.5 12 21l9-4.5"/>'),
        'document'  => array('Document', '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 12h6M9 15.5h6"/>'),
        'pencil'    => array('Pencil', '<path d="M4 20l1-4L16 5l3 3L8 19z"/><path d="m14 7 3 3"/>'),
        'cart'      => array('Cart', '<path d="M3 4h2.5l2 11h10.5l2-8H6.5"/><circle cx="9" cy="19" r="1.5"/><circle cx="17" cy="19" r="1.5"/>'),
        'truck'     => array('Delivery', '<path d="M3 6h11v10H3zM14 9h4l3 3.5V16h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>'),
        'lock'      => array('Lock', '<rect x="5" y="10.5" width="14" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>'),
        'cloud'     => array('Cloud', '<path d="M7 18.5h10a4 4 0 0 0 .5-8 6 6 0 0 0-11.4 1.5A3.3 3.3 0 0 0 7 18.5z"/>'),
        'leaf'      => array('Leaf', '<path d="M5 19C5 10 10 5 20 4c-1 10-6 15-15 15z"/><path d="M5 19 13 11"/>'),
        'bolt'      => array('Energy', '<path d="M13 2.5 5 13.5h6l-1 8 8-11h-6z"/>'),
        'code'      => array('Code', '<path d="m8 7-5 5 5 5M16 7l5 5-5 5"/>'),
        'eye'       => array('Eye', '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>'),
        'education' => array('Education', '<path d="m2.5 9 9.5-4.5L21.5 9 12 13.5z"/><path d="M6.5 11v4.5c1.5 1.5 3.4 2.2 5.5 2.2s4-.7 5.5-2.2V11M21.5 9v5"/>'),
        // 62 more (2026-11-23, user's request: to 100), drawn the same way.
        'handshake'  => array('Agreement', '<path d="M3 8l3-1 3.5 2M21 8l-3-1-5 3-2.8 2a1.4 1.4 0 0 0 1.7 2.2l2.6-1.6 4.5 3.9"/><path d="M3 8v6.5l2 .5 5.3 4.4a1.4 1.4 0 0 0 2-1.9l1.2 1a1.4 1.4 0 0 0 2-1.9l.6.5a1.4 1.4 0 0 0 1.9-2L21 14V8"/>'),
        'signature'  => array('Contract', '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/><path d="M7 17c1.5-2 2.5-2 3 0s1.5 1 2.5-.5 1.5-.5 2 .5"/>'),
        'clipboard'  => array('Checklist', '<path d="M9 4H7a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2"/><rect x="9" y="2.5" width="6" height="3" rx="1"/><path d="m8.5 12 2 2 4-4M8.5 17h7"/>'),
        'list'       => array('List', '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1"/><circle cx="4.5" cy="12" r="1"/><circle cx="4.5" cy="18" r="1"/>'),
        'gift'       => array('Gift', '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M5 12v8h14v-8M12 8v12"/><path d="M12 8C10.5 4.5 7 4 7 6.2 7 8 12 8 12 8s5 0 5-1.8C17 4 13.5 4.5 12 8z"/>'),
        'megaphone'  => array('Announcement', '<path d="M3 10v4a1 1 0 0 0 1 1h3l8 4V5L7 9H4a1 1 0 0 0-1 1z"/><path d="M7 15l1.5 5h2.5L10 16"/><path d="M18.5 9a4 4 0 0 1 0 6"/>'),
        'bell'       => array('Notification', '<path d="M6 16v-5a6 6 0 0 1 12 0v5l1.5 2h-15z"/><path d="M10 20.5a2 2 0 0 0 4 0"/>'),
        'camera'     => array('Camera', '<path d="M4 8h3l1.5-2.5h7L17 8h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13.5" r="3.5"/>'),
        'image'      => array('Image', '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="m3 17 5-5 4 4 3-3 6 6"/>'),
        'play'       => array('Video', '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M10 9v6l5-3z"/>'),
        'microphone' => array('Microphone', '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5.5 11a6.5 6.5 0 0 0 13 0M12 17.5V21M9 21h6"/>'),
        'headset'    => array('Support', '<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="13" width="4" height="6" rx="1.5"/><rect x="17" y="13" width="4" height="6" rx="1.5"/><path d="M19 19c0 1.5-2 2.5-5 2.5h-1.5"/>'),
        'laptop'     => array('Laptop', '<rect x="4.5" y="5" width="15" height="10.5" rx="1.5"/><path d="M2 19h20M3 19l1.5-3.5h15L21 19"/>'),
        'smartphone' => array('Smartphone', '<rect x="7" y="2.5" width="10" height="19" rx="2"/><path d="M11 18.5h2"/>'),
        'monitor'    => array('Monitor', '<rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M9 20h6M12 16v4"/>'),
        'database'   => array('Database', '<ellipse cx="12" cy="5.5" rx="7" ry="2.5"/><path d="M5 5.5v13c0 1.4 3.1 2.5 7 2.5s7-1.1 7-2.5v-13M5 12c0 1.4 3.1 2.5 7 2.5s7-1.1 7-2.5"/>'),
        'wifi'       => array('Wi-Fi', '<path d="M2.5 9a14 14 0 0 1 19 0M5.5 12.5a9.5 9.5 0 0 1 13 0M8.5 16a5 5 0 0 1 7 0"/><circle cx="12" cy="19.2" r="1"/>'),
        'link'       => array('Link', '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>'),
        'download'   => array('Download', '<path d="M12 3v12M7 10l5 5 5-5"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>'),
        'upload'     => array('Upload', '<path d="M12 15V3M7 8l5-5 5 5"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>'),
        'share'      => array('Share', '<circle cx="18" cy="5.5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="18.5" r="2.5"/><path d="m8.2 10.8 7.6-4M8.2 13.2l7.6 4"/>'),
        'key'        => array('Key', '<circle cx="7.5" cy="15.5" r="4.5"/><path d="M10.7 12.3 20 3M16 7l3 3M14 9l2 2"/>'),
        'tag'        => array('Price', '<path d="M3 12.6V4a1 1 0 0 1 1-1h8.6a1 1 0 0 1 .7.3l7.4 7.4a1 1 0 0 1 0 1.4l-8.6 8.6a1 1 0 0 1-1.4 0l-7.4-7.4a1 1 0 0 1-.3-.7z"/><circle cx="8" cy="8" r="1.5"/>'),
        'percent'    => array('Discount', '<path d="M19 5 5 19"/><circle cx="7" cy="7" r="2.5"/><circle cx="17" cy="17" r="2.5"/>'),
        'card'       => array('Payment card', '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M2.5 10h19M6 15h4"/>'),
        'wallet'     => array('Wallet', '<path d="M19 7V5.5A1.5 1.5 0 0 0 17.5 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h14a1 1 0 0 0 1-1v-3"/><path d="M3 6a2 2 0 0 0 2 2h14a1 1 0 0 1 1 1v3"/><path d="M21 12h-4a2 2 0 0 0 0 4h4z"/>'),
        'bank'       => array('Bank', '<path d="M3 9.5 12 4l9 5.5z"/><path d="M5 10v8M9.5 10v8M14.5 10v8M19 10v8M3 20.5h18"/>'),
        'calculator' => array('Calculator', '<rect x="5" y="2.5" width="14" height="19" rx="2"/><rect x="8" y="5.5" width="8" height="3.5" rx=".5"/><path d="M8.5 13h.01M12 13h.01M15.5 13h.01M8.5 17h.01M12 17h.01M15.5 17h.01"/>'),
        'receipt'    => array('Receipt', '<path d="M5 3v18l2.3-1.5L9.7 21l2.3-1.5 2.3 1.5 2.4-1.5L19 21V3l-2.3 1.5L14.3 3 12 4.5 9.7 3 7.3 4.5z"/><path d="M9 9h6M9 13h6"/>'),
        'package'    => array('Package', '<path d="m12 3 8.5 4.5v9L12 21l-8.5-4.5v-9z"/><path d="M3.5 7.5 12 12l8.5-4.5M12 12v9M7.8 5.2l8.5 4.6"/>'),
        'building'   => array('Office', '<rect x="5" y="3" width="14" height="18" rx="1"/><path d="M9 7h1.5M13.5 7H15M9 11h1.5M13.5 11H15M9 15h1.5M13.5 15H15M10.5 21v-3h3v3"/>'),
        'factory'    => array('Factory', '<path d="M3 21V10l5 3v-3l5 3v-3l5 3V4h3v17z"/><path d="M7 17h2M12 17h2"/>'),
        'wrench'     => array('Repair', '<path d="M14.5 6.5a4 4 0 0 1 5.2-1.7l-2.6 2.6.3 2.2 2.2.3 2.6-2.6a4 4 0 0 1-5.4 5l-7.6 7.6a2 2 0 0 1-2.8-2.8l7.6-7.6a4 4 0 0 1 .5-3z"/>'),
        'puzzle'     => array('Puzzle', '<path d="M3 7h4a2.5 2.5 0 1 1 5 0h4v4a2.5 2.5 0 1 1 0 5v5H3z"/>'),
        'compass'    => array('Compass', '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5z"/>'),
        'map'        => array('Map', '<path d="M3 6.5 9 4l6 2.5L21 4v13.5L15 20l-6-2.5L3 20z"/><path d="M9 4v13.5M15 6.5V20"/>'),
        'route'      => array('Route', '<circle cx="6" cy="18" r="2.5"/><circle cx="18" cy="6" r="2.5"/><path d="M8.5 18H16a3 3 0 0 0 0-6H8a3 3 0 0 1 0-6h7.5"/>'),
        'car'        => array('Car', '<path d="M5 16H3.5v-3.5L6 7.5a2 2 0 0 1 1.8-1h8.4a2 2 0 0 1 1.8 1l2.5 5V16H19"/><path d="M3.5 12h17"/><circle cx="7.5" cy="16.5" r="2"/><circle cx="16.5" cy="16.5" r="2"/><path d="M9.5 16h5"/>'),
        'plane'      => array('Flight', '<path d="M21 4.5c-.8-.8-2.2-.6-3 .2l-3.5 3.5L6 6 4 8l7 3.5-3.5 3.5L5 14.5 3.5 16l4 1.5L9 21.5l1.5-1.5-.5-2.5 3.5-3.5L17 20.5l2-2-2.2-8.5 3.5-3.5c.8-.8 1-2.2.2-3z"/>'),
        'hourglass'  => array('Waiting', '<path d="M6 3h12M6 21h12"/><path d="M7 3c0 5 5 6 5 9s-5 4-5 9M17 3c0 5-5 6-5 9s5 4 5 9"/>'),
        'thumbsup'   => array('Approved', '<path d="M7 10v11H4a1 1 0 0 1-1-1v-9a1 1 0 0 1 1-1z"/><path d="M7 10l4-7a2.5 2.5 0 0 1 2.5 3L13 10h5.5a2 2 0 0 1 2 2.4l-1.4 7a2 2 0 0 1-2 1.6H7"/>'),
        'smile'      => array('Satisfied', '<circle cx="12" cy="12" r="9"/><path d="M8 14a4.5 4.5 0 0 0 8 0M9 9.5h.01M15 9.5h.01"/>'),
        'medal'      => array('Medal', '<circle cx="12" cy="15" r="5.5"/><circle cx="12" cy="15" r="2.3"/><path d="M8.5 10.7 5.5 3h4l2.5 6M15.5 10.7l3-7.7h-4L12 9"/>'),
        'crown'      => array('Premium', '<path d="M3 7l4.5 4L12 4l4.5 7L21 7l-2 11H5z"/><path d="M5 21h14"/>'),
        'diamond'    => array('Quality', '<path d="M6 3h12l3.5 6L12 21 2.5 9z"/><path d="M2.5 9h19M9 3 7.5 9 12 21l4.5-12L15 3"/>'),
        'sun'        => array('Sun', '<circle cx="12" cy="12" r="4"/><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8"/>'),
        'seedling'   => array('Growth', '<path d="M12 21v-9"/><path d="M12 12C12 7.5 9 5 4.5 5c0 4.5 3 7 7.5 7z"/><path d="M12 14c0-4 2.5-6.5 7-6.5 0 4-2.5 6.5-7 6.5z"/><path d="M8 21h8"/>'),
        'trending'   => array('Progress', '<path d="m3 17 6-6 4 4 8-8"/><path d="M15 7h6v6"/>'),
        'funnel'     => array('Filter', '<path d="M3 4h18l-7 8.5V19l-4 2v-8.5z"/>'),
        'sliders'    => array('Adjust', '<path d="M4 6h9M17 6h3M4 12h3M11 12h9M4 18h11M19 18h1"/><circle cx="15" cy="6" r="2"/><circle cx="9" cy="12" r="2"/><circle cx="17" cy="18" r="2"/>'),
        'magnet'     => array('Attract', '<path d="M5 3v8a7 7 0 0 0 14 0V3h-4.5v8a2.5 2.5 0 0 1-5 0V3z"/><path d="M5 7h4.5M14.5 7H19"/>'),
        'flask'      => array('Research', '<path d="M9.5 3v6L4 19a1.5 1.5 0 0 0 1.3 2h13.4a1.5 1.5 0 0 0 1.3-2L14.5 9V3"/><path d="M8 3h8M6.5 15h11"/>'),
        'book'       => array('Book', '<path d="M12 6.5C10 5 7 4.5 3 5v14c4-.5 7 0 9 1.5 2-1.5 5-2 9-1.5V5c-4-.5-7 0-9 1.5z"/><path d="M12 6.5v14"/>'),
        'coffee'     => array('Break', '<path d="M4 9h13v5a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5z"/><path d="M17 10.5h1.5a2.5 2.5 0 0 1 0 5h-2"/><path d="M8 3c-.8 1 .8 2 0 3M12 3c-.8 1 .8 2 0 3M4 21.5h13"/>'),
        'bag'        => array('Shopping', '<path d="M5 8h14l-1 13H6z"/><path d="M9 10V6a3 3 0 0 1 6 0v4"/>'),
        'store'      => array('Shop', '<path d="M3.5 9 5 4h14l1.5 5"/><path d="M3.5 9a2.8 2.8 0 0 0 5.7 0 2.8 2.8 0 0 0 5.6 0 2.8 2.8 0 0 0 5.7 0"/><path d="M5 11.5V20h14v-8.5M10 20v-5h4v5"/>'),
        'help'       => array('Question', '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6v.6M12 17h.01"/>'),
        'info'       => array('Info', '<circle cx="12" cy="12" r="9"/><path d="M12 11v5.5M12 7.5h.01"/>'),
        'warning'    => array('Warning', '<path d="M12 3.5 2.5 20h19z"/><path d="M12 10v4.5M12 17.5h.01"/>'),
        'userplus'   => array('Sign up', '<circle cx="10" cy="8" r="4"/><path d="M3 21c.7-4 3.5-6.5 7-6.5 1.5 0 2.8.4 3.9 1.2M18 14v6M15 17h6"/>'),
        'send'       => array('Send', '<path d="M21 3 10 14M21 3l-6.5 18-4.5-7-7-4.5z"/>'),
        'folder'     => array('Folder', '<path d="M3 6a1 1 0 0 1 1-1h5l2 2.5h9a1 1 0 0 1 1 1V19a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/>'),
    );

    public static function exists($name) {
        return is_string($name) && isset(self::ICONS[$name]);
    }

    // The icon as an inline SVG ('' for an unknown name).
    public static function svg($name) {
        if (!self::exists($name)) { return ''; }
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . self::ICONS[$name][1] . '</svg>';
    }

    // For the editor: name => [label, svg].
    public static function editor() {
        $list = array();
        foreach (self::ICONS as $name => $icon) { $list[$name] = array($icon[0], self::svg($name)); }
        return $list;
    }
}
