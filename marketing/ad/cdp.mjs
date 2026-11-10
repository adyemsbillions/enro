// Tiny Chrome DevTools Protocol driver (no dependencies; Node has fetch + WebSocket built in).
import { spawn } from 'node:child_process';
import { mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';

export async function launch({ port = 9333, width = 1080, height = 1920 } = {}) {
  const profile = mkdtempSync(join(tmpdir(), 'enro-cdp-'));
  const proc = spawn(CHROME, [
    '--headless=new', `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`,
    '--allow-file-access-from-files', '--autoplay-policy=no-user-gesture-required',
    '--hide-scrollbars', '--mute-audio', `--window-size=${width},${height}`, '--force-device-scale-factor=1',
    'about:blank',
  ], { stdio: 'ignore' });
  let info;
  for (let i = 0; i < 60; i++) {
    try { info = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json(); if (info.length) break; } catch {}
    await new Promise((r) => setTimeout(r, 250));
  }
  const page = info.find((t) => t.type === 'page');
  const ws = new WebSocket(page.webSocketDebuggerUrl);
  await new Promise((r) => ws.addEventListener('open', r, { once: true }));
  let id = 0;
  const pending = new Map();
  const handlers = new Map();
  ws.addEventListener('message', (ev) => {
    const msg = JSON.parse(ev.data);
    if (msg.method && handlers.has(msg.method)) handlers.get(msg.method).forEach((fn) => fn(msg.params));
    if (msg.id && pending.has(msg.id)) {
      const { resolve, reject } = pending.get(msg.id);
      pending.delete(msg.id);
      msg.error ? reject(new Error(JSON.stringify(msg.error))) : resolve(msg.result);
    }
  });
  const send = (method, params = {}) => new Promise((resolve, reject) => {
    const mid = ++id;
    pending.set(mid, { resolve, reject });
    ws.send(JSON.stringify({ id: mid, method, params }));
  });
  /** Run JS in the page and return its (awaited) value. */
  const evaluate = async (expression) => {
    const r = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
    if (r.exceptionDetails) throw new Error(r.exceptionDetails.exception?.description || r.exceptionDetails.text);
    return r.result.value;
  };
  await send('Page.enable');
  await send('Runtime.enable');
  await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: false });
  const close = () => { try { ws.close(); } catch {} proc.kill(); };
  const on = (method, fn) => handlers.set(method, [...(handlers.get(method) || []), fn]);
  return { send, evaluate, close, on };
}

export async function goto(cdp, url) {
  await cdp.send('Page.navigate', { url });
  for (let i = 0; i < 200; i++) {
    if ((await cdp.evaluate('document.readyState')) === 'complete') return;
    await new Promise((r) => setTimeout(r, 100));
  }
}
