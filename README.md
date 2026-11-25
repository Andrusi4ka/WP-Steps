# WP Steps

A WordPress plugin for steps and timelines: write the steps once, pick a design (List, Timeline, Zigzag, Wave, Progress), colors and sizes. Every design adapts to the width it has and folds into one column on narrow blocks. Insert with `[wp_steps id="…"]` or the WPBakery element.

## Development

- Author: Relevant AS. Releases: tag `vX.Y.Z` (see CLAUDE.md "Release"); sites update from GitHub under Plugins, a git working copy never.
- `npm install` once, then `npm test`: it joins the parts first (`npm run build`, below), then PHP checks (`tests/settings.php`, no WordPress needed) and browser checks of every design on desktop, tablet and phone widths (`tests/browser.cjs`, Chrome through playwright-core).
- PHP: `C:/OSPanel/modules/PHP-8.3/php.exe` by default; `PHP_BIN` / `CHROME_BIN` override the paths.
- **Parts, not the joined files:** `assets/js/admin.js`, `assets/css/admin.css` and `assets/css/frontend.css` are built by `tools/build.cjs` from `assets/js/admin/`, `assets/css/admin/`, `assets/css/frontend/` (in name order). Edit the parts, then `npm run build`. The settings class keeps its constants; its methods are traits in `includes/settings/`.

## Adding a design

1. `includes/designs/class-wp-steps-design-{id}.php`: a subclass of `WP_Steps_Design` (name, one line about it, `supports()`, `stack_below()`, `step()`, `thumb()`).
2. `assets/css/designs/{id}.css`: its look; its fold with `@container wpst (max-width: …px)` at the width `stack_below()` returns.
3. Its id in `WP_Steps_Designs::BUILT_IN`, its checks in `tests/browser.cjs` (`FOLD`).

Another plugin can add designs through the `wp_steps_designs` filter.
