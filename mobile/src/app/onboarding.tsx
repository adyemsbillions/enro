import { useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import { router } from 'expo-router';
import { LinearGradient } from 'expo-linear-gradient';
import { setItem } from '@/lib/storage';
import { Button, H1, Icon, Screen, Sub, T, type IconName } from '@/components/ui';
import { C } from '@/lib/theme';

const SLIDES: { icon: IconName; colors: [string, string]; title: string; text: string; chips: [string, IconName, string][] }[] = [
  { icon: 'checkmark-done', colors: ['#7B5CFF', '#4527D9'], title: 'Complete simple tasks',
    text: 'Watch short ads, visit pages and follow sponsors. Every task earns you coins.',
    chips: [['+20 coins', 'logo-bitcoin', C.gold], ['Task done', 'checkmark-circle', C.cash]] },
  { icon: 'phone-portrait', colors: ['#FFB020', '#E07B00'], title: 'Turn coins into airtime & data',
    text: 'Redeem for MTN, Glo, Airtel or 9mobile airtime and data bundles.',
    chips: [['MTN ₦500', 'call', '#C89600'], ['1GB data', 'wifi', C.p]] },
  { icon: 'cash', colors: ['#1FC37E', '#0B8A55'], title: 'Special tasks pay real cash',
    text: 'Take on bigger tasks, upload proof and withdraw cash straight to your bank.',
    chips: [['+₦250', 'cash', C.cash], ['Paid to your bank', 'business', C.p]] },
];

export default function Onboarding() {
  const [i, setI] = useState(0);
  const sl = SLIDES[i];
  const last = i === SLIDES.length - 1;

  const finish = async (to: '/login' | '/signup') => {
    await setItem('enro_onboarded', '1');
    router.replace(to);
  };

  return (
    <Screen scroll={false} pad={false}>
      <View style={{ flex: 1, paddingHorizontal: 24, paddingBottom: 24 }}>
        <View style={{ height: 44, alignItems: 'flex-end', justifyContent: 'center' }}>
          {!last && <Pressable onPress={() => finish('/login')} hitSlop={10}><T style={{ color: C.mut, fontWeight: '700' }}>Skip</T></Pressable>}
        </View>
        <View style={st.illus}>
          <LinearGradient colors={sl.colors} style={st.big}>
            <Icon name={sl.icon} size={92} color={C.white} />
          </LinearGradient>
          {sl.chips.map(([label, icon, color], k) => (
            <View key={label} style={[st.chip, k === 0 ? { top: '18%', left: 0 } : { bottom: '20%', right: 0 }]}>
              <Icon name={icon} size={16} color={color} /><T style={{ fontWeight: '800' }}>{label}</T>
            </View>
          ))}
        </View>
        <H1 style={{ textAlign: 'center' }}>{sl.title}</H1>
        <Sub style={{ textAlign: 'center', marginTop: 10, paddingHorizontal: 6 }}>{sl.text}</Sub>
        <View style={st.dots}>{SLIDES.map((_, k) => <View key={k} style={[st.dot, k === i && st.dotOn]} />)}</View>
        <Button title={last ? 'Get started' : 'Next'} onPress={() => (last ? finish('/signup') : setI(i + 1))} />
        {last && <Button title="I already have an account" variant="ghost" style={{ marginTop: 12 }} onPress={() => finish('/login')} />}
      </View>
    </Screen>
  );
}

const st = StyleSheet.create({
  illus: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  big: { width: 200, height: 200, borderRadius: 56, alignItems: 'center', justifyContent: 'center', transform: [{ rotate: '-6deg' }] },
  chip: { position: 'absolute', flexDirection: 'row', gap: 6, alignItems: 'center', backgroundColor: C.white, borderRadius: 14, paddingHorizontal: 12, paddingVertical: 9,
    shadowColor: '#1E1450', shadowOpacity: 0.15, shadowRadius: 16, shadowOffset: { width: 0, height: 8 }, elevation: 6 },
  dots: { flexDirection: 'row', gap: 6, justifyContent: 'center', marginVertical: 22 },
  dot: { width: 8, height: 8, borderRadius: 4, backgroundColor: '#D6D2E6' },
  dotOn: { width: 24, backgroundColor: C.p },
});
