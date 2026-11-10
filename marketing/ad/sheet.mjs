// Contact sheet of stills/*.jpg for review:  node sheet.mjs out.jpg [cols] [width]
import { readdirSync, writeFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { launch, goto } from './cdp.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const out = process.argv[2] || 'sheet.jpg', cols = Number(process.argv[3] || 3), w = Number(process.argv[4] || 640);
const files = readdirSync(join(HERE, 'stills')).filter((f) => f.endsWith('.jpg'))
  .sort((a, b) => parseFloat(a.slice(1)) - parseFloat(b.slice(1)));
const h = Math.round((w * 9) / 16);
const rows = Math.ceil(files.length / cols);
const html = `<body style="margin:0;background:#222;display:grid;grid-template-columns:repeat(${cols},${w}px);gap:6px;padding:6px;font:600 18px sans-serif;color:#fff">
${files.map((f) => `<div style="position:relative"><img src="stills/${f}" style="width:${w}px;height:${h}px;display:block"><span style="position:absolute;left:8px;top:6px;background:#000a;padding:2px 8px;border-radius:6px">${f.slice(1, -4)}s</span></div>`).join('')}</body>`;
writeFileSync(join(HERE, '_sheet.html'), html);
const W = cols * (w + 6) + 6, H = rows * (h + 6) + 6;
const cdp = await launch({ port: 9341, width: W, height: H });
await goto(cdp, 'file:///' + join(HERE, '_sheet.html').split('\\').join('/'));
await new Promise((r) => setTimeout(r, 800));
const { data } = await cdp.send('Page.captureScreenshot', { format: 'jpeg', quality: 88 });
writeFileSync(join(HERE, out), Buffer.from(data, 'base64'));
cdp.close();
console.log('wrote', out, files.length, 'stills');
