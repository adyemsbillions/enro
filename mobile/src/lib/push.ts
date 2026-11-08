import { Platform } from 'react-native';
import * as Notifications from 'expo-notifications';
import { api } from './api';
import { getItem, removeItem, setItem } from './storage';
import type { AppConfig, Me } from './types';

/*
 * Push notifications.
 * - Remote pushes come straight from our PHP backend via Firebase (FCM). The app sends its native device token to /me/push-token.
 * - If Firebase isn't set up (no google-services.json in the build, or not configured on the server),
 *   the app falls back to a local evening "check in" reminder so users still get nudged.
 */

const TOKEN_KEY = 'enro_push_token';
const LOCAL_REMINDER_ID = 'enro-checkin-reminder';

Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowBanner: true,
    shouldShowList: true,
    shouldPlaySound: true,
    shouldSetBadge: false,
  }),
});

let channelReady: Promise<void> | null = null;
function ensureChannel() {
  if (Platform.OS !== 'android') return Promise.resolve();
  channelReady ??= Notifications.setNotificationChannelAsync('default', {
    name: 'Rewards & updates',
    // eslint-disable-next-line import/namespace -- exported via a nested `export *` the lint resolver can't follow; tsc verifies it
    importance: Notifications.AndroidImportance.HIGH,
    vibrationPattern: [0, 200, 120, 200],
    lightColor: '#5B3DF5',
  }).then(() => undefined);
  return channelReady;
}

/** Ask for permission (Android 13+ shows a prompt). Returns true when allowed. */
export async function ensurePermission(): Promise<boolean> {
  if (Platform.OS === 'web') return false;
  await ensureChannel();
  const current = await Notifications.getPermissionsAsync();
  if (current.granted) return true;
  if (!current.canAskAgain) return false;
  const asked = await Notifications.requestPermissionsAsync();
  return asked.granted;
}

/** Register this phone for remote pushes. Returns false if Firebase isn't available in this build. */
export async function registerRemotePush(): Promise<boolean> {
  if (Platform.OS === 'web') return false;
  try {
    if (!(await ensurePermission())) return false;
    const { data } = await Notifications.getDevicePushTokenAsync();
    const token = typeof data === 'string' ? data : JSON.stringify(data);
    await api('/me/push-token', { body: { token, platform: Platform.OS } });
    await setItem(TOKEN_KEY, token);
    return true;
  } catch {
    // most likely: the APK was built without google-services.json
    return false;
  }
}

/** Keep the server in sync if Firebase rotates the token. Returns an unsubscribe function. */
export function watchTokenChanges(): () => void {
  if (Platform.OS === 'web') return () => {};
  const sub = Notifications.addPushTokenListener(({ data }) => {
    const token = typeof data === 'string' ? data : JSON.stringify(data);
    api('/me/push-token', { body: { token, platform: Platform.OS } })
      .then(() => setItem(TOKEN_KEY, token))
      .catch(() => {});
  });
  return () => sub.remove();
}

/** On logout: stop this phone receiving the old account's notifications. */
export async function unregisterPush(): Promise<void> {
  const token = await getItem(TOKEN_KEY);
  if (token) await api('/me/push-token/remove', { body: { token } }).catch(() => {});
  await removeItem(TOKEN_KEY);
  await cancelLocalReminder();
}

async function cancelLocalReminder() {
  if (Platform.OS === 'web') return;
  await Notifications.cancelScheduledNotificationAsync(LOCAL_REMINDER_ID).catch(() => {});
}

/**
 * Local fallback: one reminder at the evening hour, today if the user hasn't checked in yet, otherwise tomorrow.
 * Re-planned every time the app opens, so it never nags someone who already checked in.
 */
export async function planLocalReminder(config: AppConfig, me: Me | null, serverHandlesIt: boolean): Promise<void> {
  if (Platform.OS === 'web') return;
  await cancelLocalReminder();
  if (serverHandlesIt || !config.push.evening_enabled || !me) return;
  if (!(await Notifications.getPermissionsAsync()).granted) return;

  const at = new Date();
  at.setHours(config.push.evening_hour, 0, 0, 0);
  if (me.streak.checked_today || at.getTime() <= Date.now()) at.setDate(at.getDate() + 1);

  const streak = me.streak.streak;
  await ensureChannel();
  await Notifications.scheduleNotificationAsync({
    identifier: LOCAL_REMINDER_ID,
    content: {
      title: streak >= 2 && !me.streak.checked_today ? `Don't lose your ${streak}-day streak 🔥` : 'Your daily coins are waiting 🎯',
      body: 'Check in on Enro and complete a few tasks to earn airtime & data.',
      data: { screen: 'tasks' },
    },
    trigger: { type: Notifications.SchedulableTriggerInputTypes.DATE, date: at, channelId: 'default' },
  });
}

/** Map a notification's data to an app route. */
export function routeFor(data: Record<string, unknown> | undefined): string | null {
  if (!data) return null;
  const screen = String(data.screen ?? '');
  const id = data.id != null ? String(data.id) : '';
  switch (screen) {
    case 'home': return '/(tabs)';
    case 'tasks': return '/tasks';
    case 'special': return '/special';
    case 'wallet': return '/wallet';
    case 'referral': return '/referral';
    case 'history': return '/history';
    case 'notifications': return '/notifications';
    case 'task': return id ? `/task/${id}` : '/tasks';
    case 'special_task': return id ? `/special/${id}` : '/special';
    default: return null;
  }
}
