import { useEffect, useRef, useState } from 'react';
import { AppState, Pressable, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import * as Updates from 'expo-updates';
import { C } from '@/lib/theme';
import { Icon, T } from './ui';

/**
 * Over-the-air updates (EAS Update).
 * Checks when the app starts and whenever it comes back to the foreground (at most every 10 minutes),
 * downloads new versions quietly, then shows a bar with a Restart button. The user decides when to restart,
 * so an update never interrupts a task in progress. If they ignore it, it applies on the next launch anyway.
 */
const CHECK_EVERY_MS = 10 * 60 * 1000;

export function UpdateManager() {
  const { isUpdatePending } = Updates.useUpdates();
  const [dismissed, setDismissed] = useState(false);
  const [restarting, setRestarting] = useState(false);
  const lastCheck = useRef(0);
  const busy = useRef(false);
  const insets = useSafeAreaInsets();

  useEffect(() => {
    if (__DEV__ || !Updates.isEnabled) return;
    const check = async () => {
      if (busy.current || Date.now() - lastCheck.current < CHECK_EVERY_MS) return;
      busy.current = true;
      lastCheck.current = Date.now();
      try {
        const result = await Updates.checkForUpdateAsync();
        if (result.isAvailable) await Updates.fetchUpdateAsync(); // flips isUpdatePending → bar appears
      } catch {
        // offline or update server unreachable: try again later
      } finally {
        busy.current = false;
      }
    };
    check();
    const sub = AppState.addEventListener('change', (s) => { if (s === 'active') check(); });
    return () => sub.remove();
  }, []);

  if (!isUpdatePending || dismissed) return null;

  const restart = async () => {
    setRestarting(true);
    try {
      await Updates.reloadAsync();
    } catch {
      setRestarting(false);
    }
  };

  return (
    <View pointerEvents="box-none" style={[st.wrap, { top: insets.top + 8 }]}>
      <View style={st.bar}>
        <View style={st.icon}><Icon name="sparkles" size={18} color={C.white} /></View>
        <View style={{ flex: 1 }}>
          <T style={{ color: C.white, fontWeight: '700' }}>New version ready</T>
          <T style={{ color: 'rgba(255,255,255,.75)', fontSize: 12 }}>Restart to get the latest Enro.</T>
        </View>
        <Pressable onPress={restart} style={st.btn} disabled={restarting}>
          <T style={{ color: C.p, fontWeight: '800', fontSize: 13 }}>{restarting ? 'Restarting…' : 'Restart'}</T>
        </Pressable>
        <Pressable onPress={() => setDismissed(true)} hitSlop={10} accessibilityLabel="Later">
          <Icon name="close" size={18} color="rgba(255,255,255,.7)" />
        </Pressable>
      </View>
    </View>
  );
}

const st = StyleSheet.create({
  wrap: { position: 'absolute', left: 12, right: 12, zIndex: 50 },
  bar: {
    flexDirection: 'row', alignItems: 'center', gap: 10, backgroundColor: C.ink, borderRadius: 16, padding: 12,
    shadowColor: '#000', shadowOpacity: 0.25, shadowRadius: 14, shadowOffset: { width: 0, height: 6 }, elevation: 8,
  },
  icon: { width: 34, height: 34, borderRadius: 10, backgroundColor: C.p, alignItems: 'center', justifyContent: 'center' },
  btn: { backgroundColor: C.white, borderRadius: 10, paddingHorizontal: 14, paddingVertical: 8 },
});
