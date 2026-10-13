// How the hosted phone preview is made (from the mobile folder):
//   1. EXPO_PUBLIC_DEMO=1 npx expo export --platform web --output-dir <a NEW empty folder, for example D:/tb-web3>
//   2. node tools/preview/make-preview.cjs <that folder> <preview folder>/app
//   3. copy tools/preview/TaraBasa-Phone-Preview.html into <preview folder>, then publish it as an Artifact with every file
//      under <preview folder>/app listed as supporting files (7 files, about 3 MB). The page draws the app in a frame.
//
// Builds the app folder of the hosted preview from the web export:
//  - copies only what the app needs (its page, its two scripts, its sound files),
//  - turns every root-relative path into a relative one, so it works from any address,
//  - embeds the seven font files the app uses straight into the script (a locked-down page may refuse fonts from files).
const fs = require('fs');
const path = require('path');

const src = process.argv[2];   // the web export, for example D:/tb-web2
const dst = process.argv[3];   // the app folder inside the preview

const USED_FONTS = [
  'Baloo2_700Bold', 'Baloo2_800ExtraBold',
  'BarlowSemiCondensed_500Medium', 'BarlowSemiCondensed_600SemiBold', 'BarlowSemiCondensed_700Bold',
  'Lexend_400Regular', 'Lexend_500Medium',
];

function copyFile(rel) {
  const from = path.join(src, rel), to = path.join(dst, rel);
  fs.mkdirSync(path.dirname(to), { recursive: true });
  fs.copyFileSync(from, to);
}
function walk(dir, out = []) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) walk(p, out); else out.push(p);
  }
  return out;
}

// 1. what the app needs
copyFile('index.html');
for (const f of walk(path.join(src, '_expo'))) copyFile(path.relative(src, f));
const appAssets = path.join(src, 'assets', 'assets');
if (fs.existsSync(appAssets)) for (const f of walk(appAssets)) copyFile(path.relative(src, f));

// 2. relative paths
function rewrite(file, pairs) {
  let t = fs.readFileSync(file, 'utf8');
  for (const [re, to] of pairs) t = t.replace(re, to);
  fs.writeFileSync(file, t);
}
rewrite(path.join(dst, 'index.html'), [
  [/(src|href)="\/(_expo)/g, '$1="$2'],
  [/<link rel="icon"[^>]*\/>/g, ''],
]);
const jsDir = path.join(dst, '_expo', 'static', 'js', 'web');
const bundles = fs.readdirSync(jsDir).filter((f) => f.endsWith('.js'));
for (const f of bundles) {
  rewrite(path.join(jsDir, f), [
    [/(["'`])\/assets\//g, '$1assets/'],
    [/(["'`])\/_expo\//g, '$1_expo/'],
  ]);
}

// 3. the fonts the app really uses, embedded
const fontFiles = walk(path.join(src, 'assets', 'node_modules')).filter((f) => f.endsWith('.ttf'));
let embedded = 0;
for (const name of USED_FONTS) {
  const file = fontFiles.find((f) => path.basename(f).startsWith(name + '.'));
  if (!file) { console.error('font file not found for', name); process.exit(1); }
  const b64 = fs.readFileSync(file).toString('base64');
  const rel = 'assets/' + path.relative(path.join(src, 'assets'), file).split(path.sep).join('/');
  let hit = 0;
  for (const f of bundles) {
    const p = path.join(jsDir, f);
    let t = fs.readFileSync(p, 'utf8');
    const needle = '"' + rel + '"';
    if (t.includes(needle)) {
      t = t.split(needle).join('"data:font/ttf;base64,' + b64 + '"');
      fs.writeFileSync(p, t);
      hit++;
    }
  }
  if (!hit) { console.error('font reference not found in the bundle for', name, rel); process.exit(1); }
  embedded++;
}
console.log('embedded', embedded, 'fonts; files in', dst + ':', walk(dst).length);
