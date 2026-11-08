// Generates Enro's app icon, Android adaptive icon layers, splash and notification icon.
// Pure Node (no dependencies). Run: node scripts/generate-icons.js
const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

const OUT = path.join(__dirname, '..', 'assets');
const PURPLE_A = [0x6a, 0x4b, 0xff];
const PURPLE_B = [0x45, 0x27, 0xd9];
const GOLD = [0xff, 0xc2, 0x2e];

// ---------- PNG writer ----------
const CRC = new Int32Array(256).map((_, n) => {
  let c = n;
  for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
  return c;
});
function crc32(buf) {
  let c = -1;
  for (const b of buf) c = CRC[(c ^ b) & 0xff] ^ (c >>> 8);
  return (c ^ -1) >>> 0;
}
function chunk(type, data) {
  const len = Buffer.alloc(4); len.writeUInt32BE(data.length);
  const td = Buffer.concat([Buffer.from(type), data]);
  const crc = Buffer.alloc(4); crc.writeUInt32BE(crc32(td));
  return Buffer.concat([len, td, crc]);
}
function writePng(file, size, pixel) {
  const raw = Buffer.alloc(size * (size * 4 + 1));
  const S = 3; // 3x3 supersampling for smooth edges
  for (let y = 0; y < size; y++) {
    raw[y * (size * 4 + 1)] = 0;
    for (let x = 0; x < size; x++) {
      let r = 0, g = 0, b = 0, a = 0;
      for (let sy = 0; sy < S; sy++) for (let sx = 0; sx < S; sx++) {
        const p = pixel((x + (sx + 0.5) / S) / size, (y + (sy + 0.5) / S) / size);
        if (p) { r += p[0] * p[3]; g += p[1] * p[3]; b += p[2] * p[3]; a += p[3]; }
      }
      const o = y * (size * 4 + 1) + 1 + x * 4;
      if (a > 0) { raw[o] = r / a; raw[o + 1] = g / a; raw[o + 2] = b / a; }
      raw[o + 3] = Math.round((a / (S * S)) * 255);
    }
  }
  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(size, 0); ihdr.writeUInt32BE(size, 4);
  ihdr[8] = 8; ihdr[9] = 6; ihdr[10] = 0; ihdr[11] = 0; ihdr[12] = 0;
  const png = Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    chunk('IHDR', ihdr), chunk('IDAT', zlib.deflateSync(raw, { level: 9 })), chunk('IEND', Buffer.alloc(0)),
  ]);
  fs.writeFileSync(path.join(OUT, file), png);
  console.log('wrote', file);
}

// ---------- shapes (coordinates are 0..1) ----------
const mix = (a, b, t) => a.map((v, i) => v + (b[i] - v) * t);
const gradient = (u, v) => mix(PURPLE_A, PURPLE_B, Math.min(1, Math.max(0, (u + v) / 2)));
function inRoundRect(u, v, x0, y0, x1, y1, r) {
  if (u < x0 || u > x1 || v < y0 || v > y1) return false;
  const cx = Math.min(Math.max(u, x0 + r), x1 - r);
  const cy = Math.min(Math.max(v, y0 + r), y1 - r);
  return (u - cx) ** 2 + (v - cy) ** 2 <= r * r;
}
/** Letter "E" filling the box (cx, cy) with height h. */
function inE(u, v, cx, cy, h) {
  const w = h * 0.72, t = h * 0.2, r = t * 0.35;
  const x0 = cx - w / 2, y0 = cy - h / 2;
  return inRoundRect(u, v, x0, y0, x0 + t, y0 + h, r) ||
    inRoundRect(u, v, x0, y0, x0 + w, y0 + t, r) ||
    inRoundRect(u, v, x0, cy - t / 2, x0 + w * 0.85, cy + t / 2, r) ||
    inRoundRect(u, v, x0, y0 + h - t, x0 + w, y0 + h, r);
}
const coin = (u, v, cx, cy, rad) => (u - cx) ** 2 + (v - cy) ** 2 <= rad * rad;
const WHITE = [255, 255, 255, 1];

// Full icon (iOS / Play Store / legacy Android): purple rounded square, white E, gold coin
writePng('icon.png', 1024, (u, v) => {
  if (inE(u, v, 0.47, 0.52, 0.48)) return WHITE;
  if (coin(u, v, 0.72, 0.3, 0.085)) return [...GOLD, 1];
  return [...gradient(u, v), 1];
});

// Adaptive icon layers (Android crops to a circle/squircle; keep art inside the middle ~60%)
writePng('android-icon-background.png', 512, (u, v) => [...gradient(u, v), 1]);
writePng('android-icon-foreground.png', 1024, (u, v) => {
  if (inE(u, v, 0.48, 0.52, 0.3)) return WHITE;
  if (coin(u, v, 0.64, 0.38, 0.05)) return [...GOLD, 1];
  return null;
});
writePng('android-icon-monochrome.png', 1024, (u, v) => (inE(u, v, 0.5, 0.5, 0.3) ? WHITE : null));

// Splash logo (white tile with purple E) shown on the purple splash background
writePng('splash-icon.png', 512, (u, v) => {
  if (!inRoundRect(u, v, 0.08, 0.08, 0.92, 0.92, 0.24)) return null;
  if (inE(u, v, 0.5, 0.5, 0.42)) return [...PURPLE_B, 1];
  return WHITE;
});

// Android notification small icon: white silhouette on transparent, 96x96
writePng('notification-icon.png', 96, (u, v) => (inE(u, v, 0.5, 0.5, 0.62) ? WHITE : null));

// Web favicon
writePng('favicon.png', 64, (u, v) => {
  if (!inRoundRect(u, v, 0, 0, 1, 1, 0.22)) return null;
  if (inE(u, v, 0.5, 0.52, 0.56)) return WHITE;
  return [...gradient(u, v), 1];
});
