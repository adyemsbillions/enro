// Renders ad.html frame by frame in headless Chrome and encodes an MP4 (H.264 + AAC) with WebCodecs.
//   node render.mjs stills 1.5,9.2,41     → stills/*.jpg for quick review
//   node render.mjs video out.mp4         → full video
//   node render.mjs video out.mp4 30 40   → only seconds 30–40 (preview)
import { writeFileSync, mkdirSync, existsSync, readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { launch, goto } from './cdp.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const [mode, arg, fromS, toS] = process.argv.slice(2);
const pageUrl = 'file:///' + join(HERE, 'ad.html').split('\\').join('/');

const cdp = await launch({ port: 9340, width: 1920, height: 1080 });
cdp.on('Runtime.exceptionThrown', (p) => console.log('PAGE ERROR:', p.exceptionDetails.exception?.description || p.exceptionDetails.text));
await goto(cdp, pageUrl);
const fontsOk = await cdp.evaluate('ready()');
if (!fontsOk) console.log('WARNING: web fonts did not load — check the internet connection');
const FPS = await cdp.evaluate('FPS');
const DURATION = await cdp.evaluate('DURATION');

const grab = async (t, quality = 95) => {
  await cdp.evaluate(`render(${t}); new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)))`);
  const { data } = await cdp.send('Page.captureScreenshot', { format: 'jpeg', quality, optimizeForSpeed: false });
  return data;
};

if (mode === 'stills') {
  mkdirSync(join(HERE, 'stills'), { recursive: true });
  for (const t of arg.split(',').map(Number)) {
    writeFileSync(join(HERE, 'stills', `t${t}.jpg`), Buffer.from(await grab(t), 'base64'));
    console.log('still', t);
  }
  cdp.close();
  process.exit(0);
}

// ---- video: inject the encoder into the same page (it draws nothing on screen) ----
const muxer = readFileSync(join(HERE, 'node_modules/mp4-muxer/build/mp4-muxer.js'), 'utf8');
await cdp.evaluate(muxer + '; true');
const audioFile = join(HERE, 'audio.f32');
const hasAudio = existsSync(audioFile);
const start = Number(fromS || 0), end = Number(toS || DURATION);
const frames = Math.round((end - start) * FPS);

await cdp.evaluate(`(async () => {
  const { Muxer, ArrayBufferTarget } = Mp4Muxer;
  window.__target = new ArrayBufferTarget();
  window.__muxer = new Muxer({
    target: __target,
    video: { codec: 'avc', width: 1920, height: 1080, frameRate: ${FPS} },
    ${hasAudio ? "audio: { codec: 'aac', numberOfChannels: 2, sampleRate: 48000 }," : ''}
    fastStart: 'in-memory',
  });
  window.__venc = new VideoEncoder({ output: (c, m) => __muxer.addVideoChunk(c, m), error: (e) => { window.__err = String(e); } });
  __venc.configure({ codec: 'avc1.640028', width: 1920, height: 1080, bitrate: 5e6, framerate: ${FPS}, latencyMode: 'quality', avc: { format: 'avc' } });
  window.__frame = async (b64, i) => {
    const blob = await (await fetch('data:image/jpeg;base64,' + b64)).blob();
    const bmp = await createImageBitmap(blob);
    const f = new VideoFrame(bmp, { timestamp: Math.round(i * 1e6 / ${FPS}), duration: Math.round(1e6 / ${FPS}) });
    __venc.encode(f, { keyFrame: i % (${FPS} * 2) === 0 });
    f.close(); bmp.close();
    while (__venc.encodeQueueSize > 4) await new Promise(r => setTimeout(r, 5));
    return window.__err || 'ok';
  };
  return true;
})()`);

const t0 = Date.now();
for (let i = 0; i < frames; i++) {
  const b64 = await grab(start + i / FPS, 93);
  const r = await cdp.evaluate(`__frame(${JSON.stringify(b64)}, ${i})`);
  if (r !== 'ok') throw new Error('encoder: ' + r);
  if (i % 150 === 0) console.log(`frame ${i}/${frames}  (${((Date.now() - t0) / 1000).toFixed(0)}s)`);
}

if (hasAudio) {
  // interleaved stereo float32 at 48 kHz, cut to the same range
  const all = new Float32Array(readFileSync(audioFile).buffer.slice(0));
  const a = all.subarray(Math.round(start * 48000) * 2, Math.round(end * 48000) * 2);
  const CH = 48000; // 1 s per chunk
  await cdp.evaluate(`(() => {
    window.__aenc = new AudioEncoder({ output: (c, m) => __muxer.addAudioChunk(c, m), error: (e) => { window.__err = String(e); } });
    __aenc.configure({ codec: 'mp4a.40.2', sampleRate: 48000, numberOfChannels: 2, bitrate: 192000 });
    return true; })()`);
  for (let s = 0; s * 2 < a.length; s += CH) {
    const part = a.subarray(s * 2, Math.min(a.length, (s + CH) * 2));
    const b64 = Buffer.from(part.buffer, part.byteOffset, part.byteLength).toString('base64');
    await cdp.evaluate(`(() => {
      const bin = atob(${JSON.stringify(b64)}); const u8 = new Uint8Array(bin.length); for (let i = 0; i < bin.length; i++) u8[i] = bin.charCodeAt(i);
      const inter = new Float32Array(u8.buffer); const n = inter.length / 2; const planar = new Float32Array(inter.length);
      for (let i = 0; i < n; i++) { planar[i] = inter[i * 2]; planar[n + i] = inter[i * 2 + 1]; }
      __aenc.encode(new AudioData({ format: 'f32-planar', sampleRate: 48000, numberOfFrames: n, numberOfChannels: 2, timestamp: Math.round(${s} * 1e6 / 48000), data: planar }));
      return true; })()`);
  }
  await cdp.evaluate('__aenc.flush().then(() => true)');
}

await cdp.evaluate('__venc.flush().then(() => { __muxer.finalize(); return true; })');
const size = await cdp.evaluate('__target.buffer.byteLength');
const chunks = [];
for (let off = 0; off < size; off += 4 * 1024 * 1024) {
  const b64 = await cdp.evaluate(`(() => { const u8 = new Uint8Array(__target.buffer, ${off}, Math.min(${4 * 1024 * 1024}, ${size} - ${off}));
    let s = ''; for (let i = 0; i < u8.length; i += 32768) s += String.fromCharCode.apply(null, u8.subarray(i, i + 32768)); return btoa(s); })()`);
  chunks.push(Buffer.from(b64, 'base64'));
}
const out = join(HERE, arg || 'enro-ad.mp4');
writeFileSync(out, Buffer.concat(chunks));
console.log(`wrote ${out}  ${(size / 1e6).toFixed(1)} MB  ${frames} frames  in ${((Date.now() - t0) / 1000).toFixed(0)}s`);
cdp.close();
