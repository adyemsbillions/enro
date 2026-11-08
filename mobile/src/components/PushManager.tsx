import { useEffect, useRef, useState } from 'react';
import { Platform } from 'react-native';
import { router, type Href } from 'expo-router';
import * as Notifications from 'expo-notifications';
import { useAuth } from '@/lib/auth';
import { planLocalReminder, registerRemotePush, routeFor, watchTokenChanges } from '@/lib/push';

/**
 * Lives at the app root. Once logged in it registers the phone for pushes, opens the right screen
 * when a notification is tapped (including when the tap launched the app), refreshes balances when a
 * push arrives in the foreground, and plans the local reminder fallback.
 */
export function PushManager() {
  const { ready, token, me, config, refresh } = useAuth();
  const [remoteOk, setRemoteOk] = useState<boolean | null>(null);
  const handled = useRef<string | null>(null);
  const lastResponse = Notifications.useLastNotificationResponse();

  // register after login
  useEffect(() => {
    if (!ready || !token || Platform.OS === 'web') return;
    let stop = () => {};
    registerRemotePush().then((ok) => {
      setRemoteOk(ok);
      if (ok) stop = watchTokenChanges();
    });
    return () => stop();
  }, [ready, token]);

  // local fallback reminder whenever account state changes (e.g. after checking in)
  useEffect(() => {
    // wait until registration has finished so we know whether the server will send reminders
    if (!ready || !token || !me || remoteOk === null || Platform.OS === 'web') return;
    const serverHandlesIt = config.push.server && remoteOk;
    planLocalReminder(config, me, serverHandlesIt).catch(() => {});
  }, [ready, token, me, config, remoteOk]);

  // a push arriving while the app is open → update balances / unread badge
  useEffect(() => {
    if (Platform.OS === 'web') return;
    const sub = Notifications.addNotificationReceivedListener(() => { refresh().catch(() => {}); });
    return () => sub.remove();
  }, [refresh]);

  // tap → open the screen the notification points at
  useEffect(() => {
    if (!ready || !token || !lastResponse) return;
    if (lastResponse.actionIdentifier !== Notifications.DEFAULT_ACTION_IDENTIFIER) return;
    const id = lastResponse.notification.request.identifier;
    if (handled.current === id) return;
    handled.current = id;
    const route = routeFor(lastResponse.notification.request.content.data as Record<string, unknown>);
    if (route) setTimeout(() => router.push(route as Href), 300);
  }, [ready, token, lastResponse]);

  return null;
}
