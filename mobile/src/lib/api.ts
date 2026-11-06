// Base URL of the PHP backend, e.g. http://192.168.1.20:8080 (your PC's LAN IP while testing on a phone).
// Set it in mobile/.env as EXPO_PUBLIC_API_URL.
export const BASE_URL = (process.env.EXPO_PUBLIC_API_URL ?? 'http://localhost:8080').replace(/\/$/, '');
const API_URL = BASE_URL + '/api';

let token: string | null = null;
let onUnauthorized: ((message: string) => void) | null = null;

// Screens opened straight from a link can mount before the saved login is restored.
// Requests wait for this until AuthProvider has finished starting up.
let markReady: () => void = () => {};
const ready = new Promise<void>((resolve) => { markReady = resolve; });
export function setApiReady() {
  markReady();
}

export function setApiToken(t: string | null) {
  token = t;
}

export function setOnUnauthorized(fn: (message: string) => void) {
  onUnauthorized = fn;
}

export class ApiError extends Error {
  status: number;
  data: Record<string, any>;
  constructor(message: string, status: number, data: Record<string, any> = {}) {
    super(message);
    this.status = status;
    this.data = data;
  }
}

type Options = { method?: 'GET' | 'POST' | 'PUT' | 'DELETE'; body?: unknown; form?: FormData; boot?: boolean };

export async function api<T = any>(path: string, opts: Options = {}): Promise<T> {
  if (!opts.boot) await ready;
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (token) headers.Authorization = `Bearer ${token}`;
  let body: BodyInit | undefined;
  if (opts.form) {
    body = opts.form as unknown as BodyInit;
  } else if (opts.body !== undefined) {
    headers['Content-Type'] = 'application/json';
    body = JSON.stringify(opts.body);
  }

  let res: Response;
  try {
    res = await fetch(API_URL + path, { method: opts.method ?? (body ? 'POST' : 'GET'), headers, body });
  } catch {
    throw new ApiError("Can't reach Enro. Check your internet connection.", 0);
  }
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    const message = data.error ?? 'Something went wrong. Please try again.';
    if (token && (res.status === 401 || data.banned)) onUnauthorized?.(message);
    throw new ApiError(message, res.status, data);
  }
  return data as T;
}

export function errMsg(e: unknown): string {
  return e instanceof Error ? e.message : 'Something went wrong';
}
