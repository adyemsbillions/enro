import { useEffect, useState } from 'react';
import { ActivityIndicator, StyleSheet, View } from 'react-native';
import { Redirect } from 'expo-router';
import { LinearGradient } from 'expo-linear-gradient';
import { useAuth } from '@/lib/auth';
import { getItem } from '@/lib/storage';
import { T } from '@/components/ui';
import { C } from '@/lib/theme';

/** Splash: decides where to go once the saved login has been checked. */
export default function Splash() {
  const { ready, token } = useAuth();
  const [seen, setSeen] = useState<boolean | null>(null);
  const [minTime, setMinTime] = useState(false);

  useEffect(() => {
    getItem('enro_onboarded').then((v) => setSeen(v === '1'));
    const t = setTimeout(() => setMinTime(true), 900);
    return () => clearTimeout(t);
  }, []);

  if (ready && seen !== null && minTime) {
    if (token) return <Redirect href="/(tabs)" />;
    return <Redirect href={seen ? '/login' : '/onboarding'} />;
  }

  return (
    <LinearGradient colors={['#6A4BFF', '#3B1FC7']} style={st.wrap}>
      <View style={st.logo}><T style={st.logoT}>E</T></View>
      <T style={st.name}>Enro</T>
      <T style={st.tag}>Do tasks. Earn airtime, data & cash.</T>
      <ActivityIndicator color={C.white} style={{ position: 'absolute', bottom: 70 }} />
    </LinearGradient>
  );
}

const st = StyleSheet.create({
  wrap: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 14 },
  logo: { width: 88, height: 88, borderRadius: 26, backgroundColor: C.white, alignItems: 'center', justifyContent: 'center' },
  logoT: { color: C.p, fontSize: 44, fontWeight: '800' },
  name: { color: C.white, fontSize: 34, fontWeight: '800', letterSpacing: -1 },
  tag: { color: 'rgba(255,255,255,.8)', fontSize: 14 },
});
