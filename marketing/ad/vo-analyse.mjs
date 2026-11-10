// Decodes vo.mp3 in Chrome and finds speech segments (separated by pauses) → vo-segments.json
import { writeFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { launch, goto } from './cdp.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
writeFileSync(join(HERE, '_vo.html'), '<!doctype html><meta charset="utf-8"><body></body>');
const cdp = await launch({ port: 9344 });
await goto(cdp, 'file:///' + join(HERE, '_vo.html').split('\\').join('/'));
const res = await cdp.evaluate(`(async () => {
  const buf = await (await fetch('vo.mp3')).arrayBuffer();
  const ctx = new OfflineAudioContext(1, 48000, 48000);
  const audio = await ctx.decodeAudioData(buf);
  const ch = audio.getChannelData(0), sr = audio.sampleRate, hop = Math.round(sr * 0.01);
  const rms = [];
  for (let i = 0; i + hop <= ch.length; i += hop) { let s = 0; for (let j = i; j < i + hop; j++) s += ch[j] * ch[j]; rms.push(Math.sqrt(s / hop)); }
  const peak = Math.max(...rms);
  const db = rms.map(v => 20 * Math.log10(v / peak + 1e-9));
  // speech = above -38 dB relative to peak; pauses = silence lasting >= 160 ms
  const thr = -38, minPause = 16;
  const segs = []; let start = null, quiet = 0;
  db.forEach((d, i) => {
    if (d > thr) { if (start === null) start = i; quiet = 0; }
    else if (start !== null) { quiet++; if (quiet >= minPause) { segs.push([start, i - quiet + 1]); start = null; quiet = 0; } }
  });
  if (start !== null) segs.push([start, db.length]);
  return { duration: audio.duration, sampleRate: sr, channels: audio.numberOfChannels, segs: segs.map(([a, b]) => [+(a / 100).toFixed(2), +(b / 100).toFixed(2)]) };
})()`);
cdp.close();
writeFileSync(join(HERE, 'vo-segments.json'), JSON.stringify(res, null, 1));
console.log(`duration ${res.duration.toFixed(2)}s, ${res.sampleRate} Hz, ${res.channels} ch, ${res.segs.length} speech segments`);
let prev = 0;
console.log(res.segs.map(([a, b]) => { const gap = (a - prev).toFixed(2); prev = b; return `${a.toFixed(2)}-${b.toFixed(2)} (gap ${gap})`; }).join('\n'));
