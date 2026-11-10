// Checks a rendered MP4 plays in Chrome: prints duration/size and saves frames at given times.
//   node verify.mjs enro-ad.mp4 1.5,40.6
import { writeFileSync, mkdirSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { launch, goto } from './cdp.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const file = process.argv[2], times = (process.argv[3] || '1').split(',').map(Number);
writeFileSync(join(HERE, '_verify.html'), `<!doctype html><body style="margin:0"><video id="v" src="${file}" preload="auto"></video><canvas id="c"></canvas></body>`);
const cdp = await launch({ port: 9343, width: 800, height: 600 });
await goto(cdp, 'file:///' + join(HERE, '_verify.html').split('\\').join('/'));
const meta = await cdp.evaluate(`new Promise((res, rej) => { const v = document.getElementById('v');
  const done = () => res({ duration: v.duration, w: v.videoWidth, h: v.videoHeight });
  if (v.readyState >= 1) done(); else { v.onloadedmetadata = done; v.onerror = () => rej('cannot decode: ' + (v.error && v.error.message)); } })`);
console.log('video:', JSON.stringify(meta));
mkdirSync(join(HERE, 'stills'), { recursive: true });
for (const t of times) {
  const d = await cdp.evaluate(`(async () => { const v = document.getElementById('v'), c = document.getElementById('c');
    await new Promise(r => { v.onseeked = r; v.currentTime = ${t}; });
    c.width = 1920; c.height = 1080; c.getContext('2d').drawImage(v, 0, 0); return c.toDataURL('image/jpeg', 0.92); })()`);
  writeFileSync(join(HERE, 'stills', `v${t}.jpg`), Buffer.from(d.split(',')[1], 'base64'));
}
cdp.close();
console.log('frames saved to stills/v*.jpg');
