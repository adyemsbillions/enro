import { useEffect, useState } from 'react';
import { Modal, Platform, Pressable, StyleSheet, View } from 'react-native';
import { WebView } from 'react-native-webview';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import * as WebBrowser from 'expo-web-browser';
import { useAuth } from '@/lib/auth';
import { BASE_URL } from '@/lib/api';
import { C } from '@/lib/theme';
import { Icon, Small, T } from './ui';

/*
 * Adsterra has no native SDK, so ad codes pasted in Admin → Adsterra ads are rendered in a small WebView.
 * Clicks open in the phone's browser so users don't get stuck inside an ad.
 */

const wrap = (code: string) => `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<style>html,body{margin:0;padding:0;background:transparent;display:flex;justify-content:center;align-items:center;min-height:100%;overflow:hidden}</style>
</head><body>${code}</body></html>`;

function AdWeb({ html, height }: { html: string; height: number }) {
  if (Platform.OS === 'web') return <Placeholder label="Ad (shows on phone)" height={height} />;
  return (
    <WebView
      originWhitelist={['*']}
      source={{ html: wrap(html), baseUrl: BASE_URL }}
      style={{ height, backgroundColor: 'transparent' }}
      scrollEnabled={false}
      javaScriptEnabled
      setSupportMultipleWindows={false}
      onShouldStartLoadWithRequest={(req) => {
        // first load is our own HTML; anything the user taps opens outside
        if (req.url === 'about:blank' || req.url.startsWith(BASE_URL) || !req.isTopFrame) return true;
        WebBrowser.openBrowserAsync(req.url).catch(() => {});
        return false;
      }}
    />
  );
}

function Placeholder({ label, height }: { label: string; height: number }) {
  return (
    <View style={[st.ph, { height }]}>
      <Small style={{ fontWeight: '700', color: '#8C86AE' }}>{label}</Small>
    </View>
  );
}

/** 320×50 banner. Renders nothing when switched off in admin. */
export function AdBanner({ style }: { style?: object }) {
  const { config } = useAuth();
  const b = config.ads.banner;
  if (!b.enabled) return null;
  return (
    <View style={[{ marginVertical: 12, alignItems: 'center' }, style]}>
      {b.html ? <AdWeb html={b.html} height={56} /> : <Placeholder label="AD · Adsterra banner 320×50" height={56} />}
    </View>
  );
}

/** Native banner for the task list. */
export function AdNative() {
  const { config } = useAuth();
  const n = config.ads.native;
  if (!n.enabled) return null;
  return (
    <View style={{ marginBottom: 10 }}>
      {n.html ? <AdWeb html={n.html} height={110} /> : <Placeholder label="AD · Adsterra native banner" height={90} />}
    </View>
  );
}

/** Full-screen ad shown after a task claim. Close button unlocks after 5 seconds. */
export function Interstitial({ visible, onClose }: { visible: boolean; onClose: () => void }) {
  return (
    <Modal visible={visible} animationType="fade" onRequestClose={() => {}} statusBarTranslucent>
      {/* mounted only while open, so the countdown starts fresh every time */}
      {visible && <InterstitialBody onClose={onClose} />}
    </Modal>
  );
}

function InterstitialBody({ onClose }: { onClose: () => void }) {
  const { config } = useAuth();
  const insets = useSafeAreaInsets();
  const [left, setLeft] = useState(5);
  const { html } = config.ads.interstitial;
  const url = config.ads.smartlink;

  useEffect(() => {
    const t = setInterval(() => setLeft((l) => Math.max(0, l - 1)), 1000);
    return () => clearInterval(t);
  }, []);

  return (
      <View style={[st.ovl, { paddingTop: insets.top + 12, paddingBottom: insets.bottom + 16 }]}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
          <Small style={{ color: '#8F8AB0' }}>Advertisement</Small>
          <Pressable onPress={left === 0 ? onClose : undefined} style={st.x} hitSlop={10}>
            {left > 0 ? <T style={{ color: C.white, fontWeight: '800' }}>{left}</T> : <Icon name="close" size={20} color={C.white} />}
          </Pressable>
        </View>
        <View style={{ flex: 1, borderRadius: 16, overflow: 'hidden', backgroundColor: '#1D1A2B' }}>
          {Platform.OS !== 'web' && (html || url) ? (
            <WebView
              source={html ? { html: wrap(html), baseUrl: BASE_URL } : { uri: url }}
              originWhitelist={['*']}
              javaScriptEnabled
              setSupportMultipleWindows={false}
            />
          ) : (
            <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', gap: 6 }}>
              <Icon name="megaphone-outline" size={40} color="#8F8AB0" />
              <T style={{ color: '#8F8AB0', fontWeight: '700' }}>Adsterra full-screen ad</T>
              <Small style={{ color: '#6D688C' }}>Add a code or Smartlink in Admin → Adsterra ads</Small>
            </View>
          )}
        </View>
      </View>
  );
}

const st = StyleSheet.create({
  ph: { alignSelf: 'stretch', borderWidth: 1.5, borderStyle: 'dashed', borderColor: '#C8C2E6', borderRadius: 12, backgroundColor: '#FAF9FE', alignItems: 'center', justifyContent: 'center' },
  ovl: { flex: 1, backgroundColor: '#0E0C17', paddingHorizontal: 16 },
  x: { width: 36, height: 36, borderRadius: 18, backgroundColor: 'rgba(255,255,255,.15)', alignItems: 'center', justifyContent: 'center' },
});
