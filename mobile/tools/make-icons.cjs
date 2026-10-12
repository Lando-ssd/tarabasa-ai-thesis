// Makes the phone app's icon, splash and in-app owl from the website's real logo (public/images/logo.png).
// Run from mobile/:  node tools/make-icons.cjs
// The logo is a blue rounded tile with the owl on it. The owl is cut out of the blue (a colour key), then drawn
// on a blue gradient that matches the tile, at the sizes Android and the splash screen need.
const path = require('path');
const Jimp = require('jimp-compact');

const LOGO = path.join(__dirname, '..', '..', 'public', 'images', 'logo.png');
const OUT = path.join(__dirname, '..', 'assets');
const SCALE = 3; // work at 3x so edges stay smooth after the cut

const smooth = (v) => Math.max(0, Math.min(1, v));

function gradient(size) {
  // Top, middle and bottom colours measured on the logo's own tile.
  const stops = [[0, [2, 152, 237]], [0.5, [0, 98, 207]], [1, [0, 69, 198]]];
  const img = new Jimp(size, size, 0x000000ff);
  for (let y = 0; y < size; y++) {
    const t = y / (size - 1);
    const i = t < 0.5 ? 0 : 1;
    const [t0, c0] = stops[i];
    const [t1, c1] = stops[i + 1];
    const k = (t - t0) / (t1 - t0);
    const c = c0.map((v, n) => Math.round(v + (c1[n] - v) * k));
    for (let x = 0; x < size; x++) img.setPixelColor(Jimp.rgbaToInt(c[0], c[1], c[2], 255), x, y);
  }
  return img;
}

async function cutOutOwl() {
  const logo = await Jimp.read(LOGO);
  // The owl sits well inside the tile; this box avoids the tile's white rounded corners.
  const box = { x: 88, y: 74, w: 304, h: 364 };
  const crop = logo.clone().crop(box.x, box.y, box.w, box.h).resize(box.w * SCALE, box.h * SCALE, Jimp.RESIZE_BICUBIC);
  crop.scan(0, 0, crop.bitmap.width, crop.bitmap.height, function (x, y, idx) {
    const r = this.bitmap.data[idx], g = this.bitmap.data[idx + 1], b = this.bitmap.data[idx + 2];
    // Blue background: much more blue than red, and clearly more blue than green (the book's teal page is not).
    const m = Math.min(smooth((b - g - 28) / 40), smooth((b - r - 85) / 60));
    // Pull the edge in a little so no bluish halo is left around the owl.
    this.bitmap.data[idx + 3] = Math.round(255 * smooth((1 - m - 0.35) / 0.5));
  });
  // Keep only the owl itself: drop any loose specks that are not joined to the biggest shape.
  const W = crop.bitmap.width, H = crop.bitmap.height, data = crop.bitmap.data;
  const label = new Int32Array(W * H);
  const sizes = [0];
  for (let start = 0; start < W * H; start++) {
    if (label[start] || data[start * 4 + 3] <= 40) continue;
    const id = sizes.length;
    let count = 0;
    const stack = [start];
    label[start] = id;
    while (stack.length) {
      const p = stack.pop();
      count++;
      const x = p % W, y = (p / W) | 0;
      for (const [nx, ny] of [[x + 1, y], [x - 1, y], [x, y + 1], [x, y - 1]]) {
        if (nx < 0 || ny < 0 || nx >= W || ny >= H) continue;
        const q = ny * W + nx;
        if (!label[q] && data[q * 4 + 3] > 40) { label[q] = id; stack.push(q); }
      }
    }
    sizes.push(count);
  }
  const biggest = sizes.indexOf(Math.max(...sizes.slice(1)));
  for (let p = 0; p < W * H; p++) if (label[p] !== biggest) data[p * 4 + 3] = 0;
  // Trim the empty margin so the owl can be placed by its real size.
  let minX = crop.bitmap.width, minY = crop.bitmap.height, maxX = 0, maxY = 0;
  crop.scan(0, 0, crop.bitmap.width, crop.bitmap.height, function (x, y, idx) {
    if (this.bitmap.data[idx + 3] > 40) { if (x < minX) minX = x; if (x > maxX) maxX = x; if (y < minY) minY = y; if (y > maxY) maxY = y; }
  });
  crop.crop(minX, minY, maxX - minX + 1, maxY - minY + 1);
  return crop;
}

function place(owl, size, heightShare, dy = 0) {
  const o = owl.clone();
  const h = Math.round(size * heightShare);
  o.resize(Jimp.AUTO, h, Jimp.RESIZE_BICUBIC);
  const canvas = new Jimp(size, size, 0x00000000);
  canvas.composite(o, Math.round((size - o.bitmap.width) / 2), Math.round((size - h) / 2 + dy));
  return canvas;
}

(async () => {
  const owl = await cutOutOwl();
  console.log('owl cut out:', owl.bitmap.width, 'x', owl.bitmap.height);

  // Android adaptive icon: foreground (owl on transparent), background (gradient), monochrome (silhouette).
  const fg = place(owl, 1024, 0.5);
  await fg.writeAsync(path.join(OUT, 'android-icon-foreground.png'));
  await gradient(1024).writeAsync(path.join(OUT, 'android-icon-background.png'));
  const mono = fg.clone();
  mono.scan(0, 0, mono.bitmap.width, mono.bitmap.height, function (x, y, idx) {
    const a = this.bitmap.data[idx + 3];
    this.bitmap.data[idx] = 255; this.bitmap.data[idx + 1] = 255; this.bitmap.data[idx + 2] = 255;
    this.bitmap.data[idx + 3] = a > 40 ? 255 : 0;
  });
  await mono.writeAsync(path.join(OUT, 'android-icon-monochrome.png'));

  // The general icon (full square, the system rounds the corners): gradient with the owl on it.
  const icon = gradient(1024);
  icon.composite(place(owl, 1024, 0.68), 0, 0);
  await icon.writeAsync(path.join(OUT, 'icon.png'));

  // Splash: the owl alone, the splash screen draws it on white.
  const splash = place(owl, 1024, 0.86);
  await splash.writeAsync(path.join(OUT, 'splash-icon.png'));

  // Browser tab icon, and the owl used inside the app.
  const fav = gradient(96);
  fav.composite(place(owl, 96, 0.8), 0, 0);
  await fav.writeAsync(path.join(OUT, 'favicon.png'));
  const inApp = owl.clone().resize(Jimp.AUTO, 512, Jimp.RESIZE_BICUBIC);
  await inApp.writeAsync(path.join(OUT, 'owl.png'));
  console.log('done');
})();
