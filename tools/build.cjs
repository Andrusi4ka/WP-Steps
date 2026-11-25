/*
 * Joins the parts of the large assets into the files WordPress loads (2026-11-25, user's request: smaller files, easy to read):
 *   assets/js/admin/*.js        → assets/js/admin.js       (one function: 01 opens it, 13 closes it)
 *   assets/css/admin/*.css      → assets/css/admin.css
 *   assets/css/frontend/*.css   → assets/css/frontend.css
 * The parts in the order of their names (01-, 02- …), nothing added between them.
 *
 * node tools/build.cjs          writes the joined files (npm run build; npm test runs it first)
 * node tools/build.cjs --check  writes nothing, exits 1 if a joined file is not its parts
 */
'use strict';
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const BUNDLES = [
    ['assets/js/admin', '.js', 'assets/js/admin.js'],
    ['assets/css/admin', '.css', 'assets/css/admin.css'],
    ['assets/css/frontend', '.css', 'assets/css/frontend.css'],
];
const check = process.argv.includes('--check');
let stale = 0;

for (const [dir, ext, out] of BUNDLES) {
    const parts = fs.readdirSync(path.join(ROOT, dir)).filter(f => f.endsWith(ext)).sort();
    const joined = parts.map(f => fs.readFileSync(path.join(ROOT, dir, f), 'utf8')).join('');
    const file = path.join(ROOT, out);
    const before = fs.existsSync(file) ? fs.readFileSync(file, 'utf8') : null;
    if (before === joined) continue;
    if (check) { console.error(out + ' is not its parts (' + dir + '): run npm run build'); stale++; continue; }
    fs.writeFileSync(file, joined);
    console.log(out + ' ← ' + parts.length + ' parts');
}
if (stale) process.exit(1);
