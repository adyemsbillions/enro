import { useState } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import { router } from 'expo-router';
import { api } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { naira } from '@/lib/format';
import { useLoad } from '@/lib/useLoad';
import type { SpecialTask } from '@/lib/types';
import { C } from '@/lib/theme';
import { UnlockCard } from '@/components/UnlockCard';
import { Card, Chips, Empty, ErrorBox, Header, Icon, IconBox, Loading, Pill, ProgressBar, Row, Screen, Small, T } from '@/components/ui';

type Filter = 'available' | 'pending' | 'done';

function statusPill(t: SpecialTask) {
  if (t.my_status === 'pending') return <Pill text="Pending review" tone="amber" />;
  if (t.my_status === 'approved') return <Pill text="Paid" tone="cash" icon="checkmark" />;
  if (t.my_status === 'rejected') return <Pill text="Rejected — retry" tone="red" />;
  return null;
}

export default function Special() {
  const { me, config } = useAuth();
  const [filter, setFilter] = useState<Filter>('available');
  const { data, error, refreshing, reload, loading } = useLoad(() => api<{ items: SpecialTask[]; unlocked: boolean; unlock_fee: number }>('/special-tasks'));

  const locked = !!data && !data.unlocked && data.unlock_fee > 0;
  const items = (data?.items ?? []).filter((t) =>
    filter === 'available' ? (!t.my_status || t.my_status === 'rejected')
      : filter === 'pending' ? t.my_status === 'pending' : t.my_status === 'approved');

  return (
    <Screen
      header={<Header title="Special Tasks" back={false} right={<Pill text={naira(me?.user.cash ?? 0)} tone="cash" icon="cash-outline" />} />}
      refreshing={refreshing} onRefresh={reload}
    >
      <Card style={{ backgroundColor: C.cashl, borderColor: '#BDEBD3' }}>
        <Row>
          <View style={{ width: 44, height: 44, borderRadius: 13, backgroundColor: C.white, alignItems: 'center', justifyContent: 'center' }}><Icon name="shield-checkmark" size={22} color={C.cash} /></View>
          <View style={{ flex: 1 }}>
            <T style={{ fontWeight: '700' }}>Earn real cash</T>
            <Small style={{ color: '#2D7A57' }}>Submit proof · reviewed within 24h · withdraw from {naira(config.min_withdraw)}</Small>
          </View>
        </Row>
      </Card>

      {locked && <View style={{ marginTop: 12 }}><UnlockCard fee={data.unlock_fee} onUnlocked={reload} /></View>}

      <View style={{ marginVertical: 16 }}>
        <Chips<Filter> value={filter} onChange={setFilter} items={[['available', 'Available'], ['pending', 'Pending'], ['done', 'Completed']]} />
      </View>

      {loading ? <Loading /> : error && !data ? <ErrorBox message={error} onRetry={reload} /> :
        items.length === 0 ? (
          <Empty icon="star-outline"
            title={filter === 'available' ? 'No special tasks right now' : filter === 'pending' ? 'Nothing waiting for review' : 'No completed special tasks yet'}
            text={filter === 'available' ? 'New paid tasks drop often. Turn on notifications so you don\'t miss them.' : undefined} />
        ) : items.map((t) => {
          if (locked && !t.my_status) return <LockedCard key={t.id} />;
          const pct = (t.taken / Math.max(1, t.slots)) * 100;
          return (
            <Pressable key={t.id} onPress={() => router.push(`/special/${t.id}`)} style={({ pressed }) => [st.card, pressed && { opacity: 0.85 }]}>
              <Row>
                <IconBox name="star" tone="cash" />
                <View style={{ flex: 1 }}>
                  <T style={{ fontWeight: '700', lineHeight: 19 }}>{t.title}</T>
                  <Small style={{ marginTop: 2 }}>{t.eta ? `${t.eta} · ` : ''}Proof required</Small>
                </View>
                <Pill text={naira(t.reward)} tone="cash" />
              </Row>
              <View style={{ marginTop: 12 }}>
                <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginBottom: 6 }}>
                  <Small>{Math.max(0, t.slots - t.taken).toLocaleString()} slots left</Small>
                  {statusPill(t) ?? <Small>{Math.round(pct)}% taken</Small>}
                </View>
                <ProgressBar pct={pct} />
              </View>
            </Pressable>
          );
        })}
    </Screen>
  );
}

/** A task the user hasn't paid to see yet: nothing real is shown, only blurred-out shapes and a lock. */
function LockedCard() {
  return (
    <View style={st.card}>
      <View style={{ opacity: 0.55 }}>
        <Row>
          <View style={[st.blur, { width: 44, height: 44, borderRadius: 13 }]} />
          <View style={{ flex: 1, gap: 8 }}>
            <View style={[st.blur, { height: 13, width: '82%' }]} />
            <View style={[st.blur, { height: 10, width: '48%' }]} />
          </View>
          <View style={[st.blur, { width: 62, height: 26, borderRadius: 13 }]} />
        </Row>
        <View style={[st.blur, { height: 8, marginTop: 16 }]} />
      </View>
      <View style={st.lock}>
        <View style={st.lockPill}><Icon name="lock-closed" size={13} color={C.white} /><T style={{ color: C.white, fontWeight: '700', fontSize: 12 }}>Locked</T></View>
      </View>
    </View>
  );
}

const st = StyleSheet.create({
  blur: { backgroundColor: '#DCD9EA', borderRadius: 6 },
  lock: { position: 'absolute', top: 0, left: 0, right: 0, bottom: 0, alignItems: 'center', justifyContent: 'center' },
  lockPill: { flexDirection: 'row', alignItems: 'center', gap: 6, backgroundColor: 'rgba(22,20,31,.78)', paddingHorizontal: 12, paddingVertical: 7, borderRadius: 20 },
  card: { backgroundColor: C.white, borderWidth: 1, borderColor: C.line, borderRadius: 16, padding: 14, marginBottom: 10 },
});
