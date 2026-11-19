import { Platform } from 'react-native';
import { router, type Href } from 'expo-router';
import * as Notifications from 'expo-notifications';
import { api, ApiError, BASE_URL } from './api';
import { ensurePermission } from './push';
import { getItem, removeItem, setItem } from './storage';

/*
 * Admin screens in the app (confirm unlock-fee receipts, change the payment account).
 * Admins log in with their web admin email + password; the admin token is kept apart from the user login,
 * so the same phone can be used as a normal user too.
 */

const KEY = 'enro_admin_token';
const PUSH_KEY = 'enro_admin_push';
let cached: string | null | undefined;

export async function getAdminToken(): Promise<string | null> {
  if (cached === undefined) cached = await getItem(KEY);
  return cached;
}

/** Calls an /admin endpoint with the admin token. A 401 logs the admin out and opens the admin login. */
export async function adminApi<T = any>(path: string, opts: { method?: 'GET' | 'POST'; body?: unknown } = {}): Promise<T> {
  const t = await getAdminToken();
  if (!t) {
    router.replace('/admin/login' as Href);
    throw new ApiError('Please log in', 401);
  }
  try {
    return await api<T>('/admin' + path, { ...opts, auth: t });
  } catch (e) {
    if (e instanceof ApiError && e.status === 401) {
      await clearAdmin();
      router.replace('/admin/login' as Href);
    }
    throw e;
  }
}

export async function adminLogin(email: string, password: string): Promise<void> {
  const r = await api<{ token: string }>('/admin/login', { body: { email, password }, auth: null });
  cached = r.token;
  await setItem(KEY, r.token);
}

async function clearAdmin() {
  cached = null;
  await removeItem(KEY);
  await removeItem(PUSH_KEY);
}

export async function adminLogout(): Promise<void> {
  const token = await getItem(PUSH_KEY);
  if (token) await adminApi('/push-token/remove', { body: { token } }).catch(() => {});
  await clearAdmin();
}

/** Registers this phone for "new receipt" alerts. Returns false if notifications are off or unavailable. */
export async function registerAdminPush(): Promise<boolean> {
  if (Platform.OS === 'web') return false;
  try {
    if (!(await ensurePermission())) return false;
    const { data } = await Notifications.getDevicePushTokenAsync();
    const token = typeof data === 'string' ? data : JSON.stringify(data);
    const r = await adminApi<{ push: boolean }>('/push-token', { body: { token, platform: Platform.OS } });
    await setItem(PUSH_KEY, token);
    return r.push;
  } catch {
    return false;
  }
}

/** Image source for a receipt (the server only sends it with the admin token). */
export function receiptSource(id: number, token: string) {
  return { uri: `${BASE_URL}/api/admin/payments/${id}/receipt`, headers: { Authorization: `Bearer ${token}`, 'X-Auth-Token': token } };
}

/** "45s", "3m 10s", "2h 5m" */
export function waited(sec: number): string {
  if (sec < 60) return `${sec}s`;
  if (sec < 3600) return `${Math.floor(sec / 60)}m ${sec % 60}s`;
  return `${Math.floor(sec / 3600)}h ${Math.floor((sec % 3600) / 60)}m`;
}

export type AdminPayment = {
  id: number; user_id: number; name: string; email: string; phone: string; amount: number; status: 'pending' | 'paid' | 'rejected' | string;
  reference: string; reject_reason: string | null; has_receipt: boolean; created_at: string; reviewed_at: string | null;
  reviewed_by: string | null; waiting_sec: number | null;
};
