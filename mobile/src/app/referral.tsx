import { Pressable, Share, StyleSheet, View } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import * as Clipboard from 'expo-clipboard';
import { api } from '@/lib/api';
import { fmt } from '@/lib/format';
import { useLoad } from '@/lib/useLoad';
import { C } from '@/lib/theme';
import { useToast } from '@/components/toast';
import { Button, Card, ErrorBox, H2, Header, Icon, Loading, Row, Screen, Small, T } from '@/components/ui';

type Ref = { code: string; invited: number; qualified: number; coins_earned: number; bonus_referrer: number; bonus_new: number; unlock_tasks: number };

export default function Referral() {
  const toast = useToast();
  const { data, error, reload } = useLoad(() => api<Ref>('/referrals'));

  if (!data) return <Screen header={<Header title="Refer & earn" />}>{error ? <ErrorBox message={error} onRetry={reload} /> : <Loading />}</Screen>;

  const message = `Join me on Enro and earn airtime, data and cash by doing simple tasks. Use my code ${data.code} when you sign up to get ${data.bonus_new} bonus coins!`;

  const copy = async () => {
    await Clipboard.setStringAsync(data.code);
    toast('Code copied');
  };

  return (
    <Screen header={<Header title="Refer & earn" />}>
      <LinearGradient colors={['#6A4BFF', '#4527D9']} style={st.hero}>
        <Icon name="gift" size={44} color={C.white} />
        <H2 style={{ color: C.white, marginTop: 12, textAlign: 'center' }}>Get {data.bonus_referrer} coins per friend</H2>
        <T style={{ color: 'rgba(255,255,255,.85)', fontSize: 13, marginTop: 6, textAlign: 'center' }}>
          Your friend gets {data.bonus_new} coins too, after completing {data.unlock_tasks} tasks.
        </T>
        <View style={st.code}>
          <T style={{ color: C.white, fontSize: 18, fontWeight: '800', letterSpacing: 1 }}>{data.code}</T>
          <Pressable onPress={copy} style={st.copy}><Icon name="copy-outline" size={14} color={C.p} /><T style={{ color: C.p, fontWeight: '700', fontSize: 13 }}>Copy</T></Pressable>
        </View>
      </LinearGradient>

      <Button title="Share invite" icon="share-social-outline" style={{ marginTop: 16 }} onPress={() => Share.share({ message })} />

      <View style={{ flexDirection: 'row', gap: 10, marginTop: 16 }}>
        {[[data.invited, 'Invited'], [data.qualified, 'Qualified'], [data.coins_earned, 'Coins earned']].map(([v, l]) => (
          <Card key={l} style={{ flex: 1, alignItems: 'center', padding: 12 }}>
            <T style={{ fontSize: 20, fontWeight: '800' }}>{fmt(Number(v))}</T>
            <Small>{l}</Small>
          </Card>
        ))}
      </View>

      <Card style={{ marginTop: 16 }}>
        <T style={{ fontWeight: '700', fontSize: 15 }}>How it works</T>
        {['Share your code with friends', 'They sign up and verify their phone', `When they finish ${data.unlock_tasks} tasks, you both get coins`].map((s, i) => (
          <Row key={i} gap={10} style={{ marginTop: 10 }}>
            <View style={st.num}><T style={{ color: C.p, fontWeight: '800', fontSize: 12 }}>{i + 1}</T></View>
            <T style={{ flex: 1 }}>{s}</T>
          </Row>
        ))}
      </Card>
    </Screen>
  );
}

const st = StyleSheet.create({
  hero: { borderRadius: 22, padding: 24, alignItems: 'center' },
  code: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', alignSelf: 'stretch', marginTop: 16, padding: 12, borderRadius: 14,
    borderWidth: 1.5, borderStyle: 'dashed', borderColor: 'rgba(255,255,255,.5)', backgroundColor: 'rgba(255,255,255,.15)' },
  copy: { flexDirection: 'row', gap: 4, alignItems: 'center', backgroundColor: C.white, borderRadius: 10, paddingHorizontal: 12, paddingVertical: 7 },
  num: { width: 24, height: 24, borderRadius: 12, backgroundColor: C.pl, alignItems: 'center', justifyContent: 'center' },
});
