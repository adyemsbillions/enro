// Renders audio.html's soundtrack to audio.f32 (interleaved stereo float32, 48 kHz) for render.mjs.
// Also writes soundtrack.wav so you can listen to it on its own.
import { writeFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { launch, goto } from './cdp.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const cdp = await launch({ port: 9342 });
await goto(cdp, 'file:///' + join(HERE, 'audio.html').split('\\').join('/'));
cdp.on('Runtime.exceptionThrown', (p) => console.log('PAGE ERROR:', p.exceptionDetails.exception?.description || p.exceptionDetails.text));
cdp.on('Runtime.consoleAPICalled', (p) => console.log('page:', p.args.map((a) => a.value ?? a.description).join(' ')));
const info = await cdp.evaluate('make()');
const parts = [];
for (let off = 0; off < info.bytes; off += 4 * 1024 * 1024) {
  parts.push(Buffer.from(await cdp.evaluate(`chunk(${off}, ${4 * 1024 * 1024})`), 'base64'));
}
cdp.close();
const f32 = Buffer.concat(parts);
writeFileSync(join(HERE, 'audio.f32'), f32);

// 16-bit WAV copy for listening
const samples = f32.length / 4;
const wav = Buffer.alloc(44 + samples * 2);
wav.write('RIFF', 0); wav.writeUInt32LE(36 + samples * 2, 4); wav.write('WAVE', 8); wav.write('fmt ', 12);
wav.writeUInt32LE(16, 16); wav.writeUInt16LE(1, 20); wav.writeUInt16LE(2, 22); wav.writeUInt32LE(48000, 24);
wav.writeUInt32LE(48000 * 4, 28); wav.writeUInt16LE(4, 32); wav.writeUInt16LE(16, 34); wav.write('data', 36); wav.writeUInt32LE(samples * 2, 40);
for (let i = 0; i < samples; i++) wav.writeInt16LE(Math.max(-32768, Math.min(32767, Math.round(f32.readFloatLE(i * 4) * 32767))), 44 + i * 2);
writeFileSync(join(HERE, 'soundtrack.wav'), wav);
console.log(`audio: ${(samples / 2 / 48000).toFixed(1)}s, raw peak ${info.peak.toFixed(2)} (normalised)`);
