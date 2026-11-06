import { useCallback, useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import { router, useFocusEffect } from 'expo-router';
import { LinearGradient } from 'expo-linear-gradient';
import { api } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { fmt, greeting, naira } from '@/lib/format';
import type { Task } from '@/lib/types';
import { C } from '@/lib/theme';
import { AdBanner } from '@/components/ads';
import { TaskCard } from '@/components/TaskCard';
import { Avatar, Card, H2, Icon, IconBtn, Pill, Row, Screen, Small, T } from '@/components/ui';

export default function Home() {
  const { me, config, refresh } = useAuth();
  const [tasks, setTasks] = useState<Task[]>([]);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    await Promise.all([
      refresh().catch(() => {}),
      api<{ items: Task[] }>('/tasks').then((r) => setTasks(r.items)).catch(() => {}),
    ]);
  }, [refresh]);

  useFocusEffect(useCallback(() => { load(); }, [load]));

  const onRefresh = async () => { setRefreshing(true); await load(); setRefreshing(false); };

  const u = me?.user;
  const streak = me?.streak.streak ?? 0;
  const checked = me?.streak.checked_today ?? false;
  const quick = tasks.filter((t) => t.available).slice(0, 3);
  // position in the 7-day cycle: days already done this cycle
  const doneInCycle = streak % 7 === 0 && streak > 0 && checked ? 7 : streak % 7;

  return (
    <Screen refreshing={refreshing} onRefresh={onRefresh}>
      <Row style={{ paddingTop: 6 }}>
        <Avatar name={u?.name ?? '?'} />
        <View style={{ flex: 1 }}>
          <Small>{greeting()}</Small>
          <T style={{ fontSize: 17, fontWeight: '700' }}>{u?.name.split(' ')[0] ?? ''} 👋</T>
        </View>
        <IconBtn name="notifications-outline" dot={(me?.unread_notifications ?? 0) > 0} onPress={() => router.push('/notifications')} />
      </Row>

      <LinearGradient colors={['#6A4BFF', '#4527D9']} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={st.hero}>
        <View style={st.circle} />
        <View style={{ flexDirection: 'row', gap: 10 }}>
          <View style={st.bx}>
            <Row gap={5}><Icon name="logo-bitcoin" size={13} color="rgba(255,255,255,.8)" /><T style={st.bxL}>Coins</T></Row>
            <T style={st.bxV}>{fmt(u?.coins ?? 0)}</T>
            <T style={st.bxS}>≈ {naira(Math.floor((u?.coins ?? 0) / config.coins_per_naira))} airtime</T>
          </View>
          <View style={st.bx}>
            <Row gap={5}><Icon name="cash-outline" size={13} color="rgba(255,255,255,.8)" /><T style={st.bxL}>Cash</T></Row>
            <T style={st.bxV}>{naira(u?.cash ?? 0)}</T>
            <T style={st.bxS}>Withdrawable</T>
          </View>
        </View>
        <View style={{ flexDirection: 'row', gap: 10, marginTop: 10 }}>
          <Pressable style={st.act} onPress={() => router.push({ pathname: '/wallet', params: { tab: 'airtime' } })}>
            <Icon name="phone-portrait-outline" size={16} color={C.p} /><T style={st.actT}>Airtime/Data</T>
          </Pressable>
          <Pressable style={st.act} onPress={() => router.push({ pathname: '/wallet', params: { tab: 'cash' } })}>
            <Icon name="business-outline" size={16} color={C.p} /><T style={st.actT}>Withdraw</T>
          </Pressable>
        </View>
      </LinearGradient>

      <Card style={{ marginTop: 16 }} onPress={() => { const d = tasks.find((t) => t.type === 'daily'); if (d) router.push(`/task/${d.id}`); }}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
          <Row gap={6}><Icon name="flame" size={18} color="#FF7A1A" /><T style={{ fontWeight: '700', fontSize: 15 }}>{streak}-day streak</T></Row>
          <Pill text={checked ? 'Checked in today' : 'Tap to check in'} tone={checked ? 'cash' : 'gold'} />
        </View>
        <View style={st.streak}>
          {Array.from({ length: 7 }).map((_, i) => {
            const done = i < doneInCycle;
            const next = !checked && i === doneInCycle;
            return (
              <View key={i} style={{ alignItems: 'center', gap: 5 }}>
                <View style={[st.sd, done && { backgroundColor: C.goldl }, next && { backgroundColor: C.p }]}>
                  {done ? <Icon name="checkmark" size={15} color={C.gold} />
                    : <T style={{ fontSize: 10, fontWeight: '800', color: next ? C.white : '#A7A3BB' }}>{i === 6 ? `+${config.streak_bonus}` : 'Day'}</T>}
                </View>
                <Small style={{ fontSize: 11 }}>{i + 1}</Small>
              </View>
            );
          })}
        </View>
      </Card>

      <AdBanner />

      <H2 style={{ fontSize: 17, marginTop: 8 }}>Choose how to earn</H2>
      <View style={{ flexDirection: 'row', gap: 12, marginTop: 12 }}>
        <Pressable style={{ flex: 1 }} onPress={() => router.push('/tasks')}>
          <LinearGradient colors={['#7B5CFF', '#4527D9']} style={st.cat}>
            <Icon name="list" size={26} color={C.white} />
            <T style={st.catT}>Tasks</T>
            <T style={st.catS}>Quick tasks that pay coins</T>
            <View style={st.tag}><T style={st.tagT}>→ Airtime & data</T></View>
          </LinearGradient>
        </Pressable>
        <Pressable style={{ flex: 1 }} onPress={() => router.push('/special')}>
          <LinearGradient colors={['#1FC37E', '#0B8A55']} style={st.cat}>
            <Icon name="star" size={26} color={C.white} />
            <T style={st.catT}>Special Tasks</T>
            <T style={st.catS}>Bigger tasks, proof required</T>
            <View style={st.tag}><T style={st.tagT}>→ Real cash</T></View>
          </LinearGradient>
        </Pressable>
      </View>

      {quick.length > 0 && (
        <>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 24, marginBottom: 12 }}>
            <H2 style={{ fontSize: 17 }}>Quick tasks</H2>
            <Pressable onPress={() => router.push('/tasks')}><T style={{ color: C.p, fontWeight: '700', fontSize: 13 }}>See all</T></Pressable>
          </View>
          {quick.map((t) => <TaskCard key={t.id} task={t} />)}
        </>
      )}

      <Card style={{ marginTop: 8, backgroundColor: C.pl, borderColor: '#DCD5FF' }} onPress={() => router.push('/referral')}>
        <Row>
          <View style={{ width: 44, height: 44, borderRadius: 13, backgroundColor: C.white, alignItems: 'center', justifyContent: 'center' }}><Icon name="gift" size={22} color={C.p} /></View>
          <View style={{ flex: 1 }}>
            <T style={{ fontWeight: '700' }}>Invite friends, earn {config.ref_bonus_referrer} coins</T>
            <Small>When a friend completes {config.ref_unlock_tasks} tasks</Small>
          </View>
          <Icon name="chevron-forward" size={18} color={C.p} />
        </Row>
      </Card>
    </Screen>
  );
}

const st = StyleSheet.create({
  hero: { borderRadius: 22, padding: 18, marginTop: 16, overflow: 'hidden' },
  circle: { position: 'absolute', width: 200, height: 200, borderRadius: 100, backgroundColor: 'rgba(255,255,255,.08)', right: -60, top: -80 },
  bx: { flex: 1, backgroundColor: 'rgba(255,255,255,.13)', borderRadius: 14, padding: 12 },
  bxL: { color: 'rgba(255,255,255,.8)', fontSize: 11.5, fontWeight: '600' },
  bxV: { color: C.white, fontSize: 22, fontWeight: '800', marginTop: 4 },
  bxS: { color: 'rgba(255,255,255,.75)', fontSize: 11 },
  act: { flex: 1, height: 40, borderRadius: 11, backgroundColor: C.white, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6 },
  actT: { color: C.p, fontWeight: '700', fontSize: 13 },
  streak: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 12 },
  sd: { width: 36, height: 36, borderRadius: 18, backgroundColor: '#F0EEF6', alignItems: 'center', justifyContent: 'center' },
  cat: { borderRadius: 18, padding: 16, minHeight: 156, gap: 6 },
  catT: { color: C.white, fontSize: 16, fontWeight: '800' },
  catS: { color: 'rgba(255,255,255,.85)', fontSize: 12, lineHeight: 17 },
  tag: { marginTop: 'auto', alignSelf: 'flex-start', backgroundColor: 'rgba(255,255,255,.22)', paddingHorizontal: 8, paddingVertical: 3, borderRadius: 99 },
  tagT: { color: C.white, fontSize: 11, fontWeight: '700' },
});
