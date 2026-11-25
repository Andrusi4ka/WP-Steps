    /* ---------- Blocks ---------- */
    const SETTINGS_BLOCKS = [
        // Cards: not in the menu where the design has none (Wave).
        // Layout (2026-11-24, user's request: was Cards; the icon's place first).
        ['layout', 'Layout', 'id-alt'],
        ['style', 'Style', 'admin-appearance'],
        ['lines', 'Line and markers', 'editor-insertmore'],
        ['numbering', 'Numbers', 'editor-ol'],
        // Custom (2026-11-24, user's request): Padding/Margin of the whole set, its id and classes.
        ['custom', 'Custom', 'admin-generic']
    ];
    // A step's tabs in its window (2026-11-24): Title, Text, Icon, Color, and Side text where the design shows it (Timeline).
    const STEP_BLOCKS = [['title', 'Title'], ['text', 'Text'], ['icon', 'Icon'], ['color', 'Style'], ['link', 'Link'], ['aside', 'Side text']];
    const stepTabs = () => STEP_BLOCKS.filter(b => b[0] !== 'aside' || uses('aside'));

    // A circle's shadow: its own switch, else the cards' Shadow where the design has cards (2026-11-25; WP_Steps_Render::icon_shadow_on()).
    const iconShadowOn = step => step.icon_shadow == null ? !!data.shadow && uses('shadow') : !!step.icon_shadow;

    const blocks = {
        layout() {
            // Card Icon (2026-11-24, user's request): Icon position and its Alignment side by side (beside the texts Top / Middle / Bottom,
            // above or below them Left / Center / Right; none with Auto), then the Gap to the texts; all per device.
            const pos = iconPos(device), across = pos === 'top' || pos === 'bottom';
            const align = pos && uses('icon_position') ? devSelect('icon_align', 'Alignment', across ? {start:'Left', center:'Center', end:'Right'} : {start:'Top', center:'Middle', end:'Bottom'}, across ? 'start' : 'center') : '';
            // Space between steps first (2026-11-24, user's request: moved here from Line and markers), so Layout is in every design's menu.
            // Progress (2026-11-25): its texts centred or from the left, the icons shown or not.
            return card('Layout', respNumber('gap', 'Space between steps', {to:120})
                + select('progress_align', 'Text alignment', [['center', 'Center'], ['left', 'Left']], {uses:'progress_align'})
                + toggle('show_icons', 'Show icons', {uses:'show_icons'}))
                + (uses('icon_position') ? card('Icon', row('wpst-row2', devSelect('icon_position', 'Position', {left:'Left', top:'Top', right:'Right', bottom:'Bottom'}, '') + align)
                + respNumber('icon_gap', 'Gap', {to:100})) : '')
                // Wave's half rings (2026-11-24, user's request: moved here from Line and markers).
                + (uses('arc') ? card('Half rings', respNumber('arc', 'Thickness', {to:30}) + respNumber('ring_gap', 'Gap', {to:30, tip:'Between each circle and its half ring.'})) : '');
        },
        // Style (2026-11-24, user's request): everything that was in Layout → Cards (in Wave, without cards, the step colors).
        style() {
            return (hasCards() ? card('Cards', stepColorFields() + toggle('alternate', 'Cards from side to side', {uses:'alternate', tip:'The cards on the left and on the right of the line in turn, the side texts opposite them.'})
                // Margin, Border, Radius and Padding of the cards in one box (2026-11-25, user's request; no switches: 0 is none).
                + (uses('card_border') ? boxModel('card') : '')
                + toggle('shadow', 'Shadow', {uses:'shadow'})
                + (data.shadow && uses('shadow') ? shadowFields('card_shadow') : '')
                + note('Each step\'s icon, its style, size and color: open the step (its tile above), then Icon.')) : card('Step colors', stepColorFields()));
        },
        lines() {
            return card('Line', choice('line', 'Style', {dotted:'Dotted', dashed:'Dashed', solid:'Solid', none:'None'})
                + respNumber('line_width', 'Width', {to:8})
                // Zigzag: the corners of the line between markers (2026-11-24).
                + respNumber('line_radius', 'Corner radius', {to:100, uses:'line_radius'})
                // Its color here, with the line (2026-11-24, user's request; it was under Colors).
                // Color and Arrows in one row (2026-11-25, user's request).
                + row('wpst-row2 wpst-row2--color-toggle', color('line_color', 'Color', {def:'', fallback:stepColors()[0]}) + toggle('arrow', 'Arrows', {uses:'arrow'})))
                + card('Markers', choice('marker', 'Marker on the line', {ring:'Ring', dot:'Dot', number:'Number', icon:'Icon', none:'None'}, {uses:'marker', tip:'Number: a circle with the step\'s number (and the word set under Numbers).'})
                + ((data.design === 'timeline' || data.design === 'progress') && data.marker === 'number' ? respNumber('number_size', 'Number size', {to:120, tip:'The numbered marker on the line.'}) : ''));
        },
        numbering() {
            return card('Numbers', (data.design === 'zigzag' ? respNumber('number_size', 'Number size', {to:120, tip:'The large number beside each card.'}) : '')
                + toggle('show_label', 'Show the step number')
                // Its alignment (2026-11-25, user's request: as the texts; no Auto: the design's shown until one is chosen).
                + (data.show_label ? select('label_align', 'Alignment', [['left', 'Left'], ['center', 'Center'], ['right', 'Right']], {uses:'label_align', shown:data.label_align || (data.design === 'wave' ? 'center' : 'left')}) : '')
                + (data.show_label ? textField('label_word', 'Word before the number', {placeholder:'Step', tip:'For example Step, Stage, Phase. Empty: the number alone.'})
                + choice('number_format', 'Number', {'01':'01, 02, 03', '1':'1, 2, 3'})
                // Its color here, with the number (2026-11-24, user's request; it was under Colors → Texts).
                + color('label_color', 'Color', {def:'', fallback:stepColors()[0], tip:'"Step 01" above the title, or the word over the large number (Zigzag). Automatic: the step\'s color (grey in Zigzag).'}) : ''));
        },
        custom() {
            // No tooltips in this block (2026-11-24, user's request).
            return card('Custom', toggle('use_padding', 'Padding/Margin')
                + (data.use_padding ? spacingField() : ''))
                + card('Attributes', textField('css_id', 'Element ID', {placeholder:'my-steps'})
                + textField('css_class', 'Extra class name', {placeholder:'my-class'}));
        },
        title() {
            return card('Title', editorField('title', 'Title') + styleRow('title')
                // No Default (user's request): a step without a tag of its own shows the one it has (the set's).
                + select('title_tag', 'Tag', {h1:'H1', h2:'H2', h3:'H3', h4:'H4', h5:'H5', h6:'H6', p:'Paragraph', div:'Div'}, {shown:current().title_tag || 'h3', tip:'For search engines and screen readers; the look stays the same.'}));
        },
        text() {
            return card('Text', editorField('text', 'Text') + styleRow('text'));
        },
        // The whole step as a link (2026-11-24, user's request).
        link() {
            return card('Link', textField('link', 'Link', {placeholder:'https://'})
                + toggle('link_new_tab', 'Open in a new tab'));
        },
        aside() {
            // Under each editor its Font size, weight and color per device (the size's device icons switch all three), the title's Tag.
            return card('Side text', editorField('aside_title', 'Title') + asideRow('aside_title')
                + select('aside_title_tag', 'Tag', {h1:'H1', h2:'H2', h3:'H3', h4:'H4', h5:'H5', h6:'H6', p:'Paragraph', div:'Div'}, {shown:current().aside_title_tag || 'p'})
                + editorField('aside_text', 'Text') + asideRow('aside_text'));
        },
        icon() {
            const step = current(), media = mediaOf(step), image = media === 'image' && step.image ? images[step.image] : null;
            const c = iconCircle(step), shape = c.shape, style = shape === 'none' ? 'plain' : c.style;
            // First: an icon or an image (2026-11-23, user's request); everything under it is for either.
            const kinds = '<div class="wpst-choice wpst-media-choice" role="group" aria-label="Icon or image">' + [['icon', 'Icon'], ['image', 'Image']].map(([v, t]) =>
                '<button type="button" data-choice="media" data-value="' + v + '" aria-pressed="' + (media === v) + '">' + t + '</button>').join('') + '</div>';
            // The circle beside the select (2026-11-23, user's idea): Filled / Outline / None, the one in effect pressed.
            const shapes = '<div class="wpst-choice wpst-icon-shape" role="group" aria-label="Circle">' + [['filled', 'Filled'], ['outline', 'Outline'], ['none', 'None']].map(([v, t]) =>
                '<button type="button" data-choice="icon_shape" data-value="' + v + '" aria-pressed="' + (shape === v) + '">' + t + '</button>').join('') + '</div>';
            // An image's box (2026-11-25, user's request: as Woodmart's Image element; not in Wave, its half ring follows a circle).
            const imgBox = media === 'image' && uses('image_size') && !!step.image_size;
            return card('Icon', kinds + '<div class="wpst-icon-head">' + (media === 'image' ? imagePicker(image) : iconSelect(step, null)) + shapes + '</div>'
                + (media === 'image' && uses('image_size') ? imageBoxFields(step) : '')
                // Size and Icon size, then Gap and Color (2026-11-23, user's request); all but Color per device.
                + '<div class="wpst-row2">' + stepSize('icon_size', imgBox ? 'Width' : 'Size')
                + stepSize('icon_scale', 'Content size', {disabled:imgBox, unit:'%', min:20, max:100, usual:media === 'image' ? 52 : style === 'plain' ? 50 : 44, tip:'The icon or image in its circle, % of the circle: larger, the circle around it narrower. With None, % of Size. Empty: the larger screen\'s, else the usual (its placeholder).'}) + '</div>'
                // The icon's color and the circle's (fill or ring), no reset. (Wave's half rings: Settings → Line and markers.)
                + row('wpst-row2 wpst-row2--colors', color('icon_color', 'Icon color', {def:'', reset:false, fallback:c.icon, disabled:media === 'image'})
                + color('icon_bg', 'Background', {def:'', reset:false, fallback:c.bg, disabled:shape === 'none'}))
                // Its corners (2026-11-23, user's request: WP Banner's Radius box), above Shadow.
                // With None no circle, but in Wave the half ring still takes these corners (2026-11-24, user's report): there it stays.
                + toggle('icon_use_radius', 'Radius', {disabled:shape === 'none' && !uses('arc'), tip:'On: the corners below (none set: a circle). Off: no rounding, a square.'})
                + (step.icon_use_radius && (shape !== 'none' || uses('arc')) ? radiusBox(false) : '')
                // The circle's shadow (2026-11-23): the set's until switched here; only a filled circle has one.
                + row('wpst-icon-shadow', toggle('icon_shadow', 'Shadow', {checked:iconShadowOn(step), disabled:shape !== 'filled'})
                + (shape === 'filled' && iconShadowOn(step) ? shadowFields('icon_shadow') : '')));
        },
        color() {
            const step = current();
            // Style (2026-11-24, user's request: was Color): its Padding / Margin, always open, then its color.
            // Its card's box (2026-11-25): Margin, Border, Radius, Padding; empty = as the cards.
            // Its card's shadow as the cards' (2026-11-25, user's request): not set, the cards' switch; its parts empty, the cards'.
            const shadowOn = step.shadow == null ? !!data.shadow : step.shadow;
            return card('Style', boxModel('step') + (uses('shadow') ? toggle('shadow', 'Shadow', {checked:shadowOn}) + (shadowOn ? shadowFields('shadow') : '') : '') + color('color', 'Step color', {def:'', fallback:autoColor(selected), tip:'Not set: its place between the first and the last step\'s colors (Settings → Colors). The arrows set it back.'}));
        }
    };

