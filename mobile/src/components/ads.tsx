import { useEffect, useState } from 'react';
import { Modal, Platform, Pressable, StyleSheet, View } from 'react-native';
import { WebView } from 'react-native-webview';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuth } from '@/lib/auth';
import { C } from '@/lib/theme';
import { Icon, Small, T } from './ui';

/*
 * Ads = Adsterra Smartlink only (Adsterra's banner/native/social-bar codes are for websites, not in-app WebViews).
 * The Smartlink is used in two places:
 *  - "Watch ad" tasks without their own link open it in the in-app browser (see task/[id].tsx)
 *  - this full-screen ad after every N task claims (N and the link are set in Admin → Adsterra ads)
 */

/** True when the admin has a Smartlink set and the full-screen ad switched on. */
export function useSmartlinkAd(): boolean {
  const { config } = useAuth();
  return config.ads.interstitial.enabled && !!config.ads.smartlink;
}

/** Full-screen Smartlink after a task claim. The close button unlocks after 5 seconds. */
export function SmartlinkAd({ visible, onClose }: { visible: boolean; onClose: () => void }) {
  return (
    <Modal visible={visible} animationType="fade" onRequestClose={() => {}} statusBarTranslucent>
      {/* mounted only while open, so the countdown starts fresh every time */}
      {visible && <SmartlinkBody onClose={onClose} />}
    </Modal>
  );
}

function SmartlinkBody({ onClose }: { onClose: () => void }) {
  const { config } = useAuth();
  const insets = useSafeAreaInsets();
  const [left, setLeft] = useState(5);
  const url = config.ads.smartlink;

  useEffect(() => {
    const t = setInterval(() => setLeft((l) => Math.max(0, l - 1)), 1000);
    return () => clearInterval(t);
  }, []);

  return (
    <View style={[st.ovl, { paddingTop: insets.top + 12, paddingBottom: insets.bottom + 16 }]}>
      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
        <Small style={{ color: '#8F8AB0' }}>Sponsored · your coins are added when you close this</Small>
        <Pressable onPress={left === 0 ? onClose : undefined} style={st.x} hitSlop={10} accessibilityLabel="Close ad">
          {left > 0 ? <T style={{ color: C.white, fontWeight: '800' }}>{left}</T> : <Icon name="close" size={20} color={C.white} />}
        </Pressable>
      </View>
      <View style={{ flex: 1, borderRadius: 16, overflow: 'hidden', backgroundColor: '#1D1A2B' }}>
        {Platform.OS !== 'web' && url ? (
          <WebView source={{ uri: url }} javaScriptEnabled domStorageEnabled setSupportMultipleWindows={false} />
        ) : (
          <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', gap: 6 }}>
            <Icon name="megaphone-outline" size={40} color="#8F8AB0" />
            <T style={{ color: '#8F8AB0', fontWeight: '700' }}>Sponsored content</T>
          </View>
        )}
      </View>
    </View>
  );
}

const st = StyleSheet.create({
  ovl: { flex: 1, backgroundColor: '#0E0C17', paddingHorizontal: 16 },
  x: { width: 36, height: 36, borderRadius: 18, backgroundColor: 'rgba(255,255,255,.15)', alignItems: 'center', justifyContent: 'center' },
});
