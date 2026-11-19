import { useCallback, useEffect, useState } from 'react';
import { Alert, Platform, Pressable, StyleSheet, View } from 'react-native';
import { router, useFocusEffect, type Href } from 'expo-router';
import { adminApi, adminLogout, registerAdminPush, waited, type AdminPayment } from '@/lib/admin';
import { naira } from '@/lib/format';
import { useLoad } from '@/lib/useLoad';
import { C } from '@/lib/theme';
import { Avatar, Button, Card, Chips, Empty, ErrorBox, Header, Icon, IconBtn, Loading, Pill, Row, Screen, Small, T } from '@/components/ui';

type Status = 'pending' | 'paid' | 'rejected';
type AdminMe = { admin: { name: string; email: string }; pending: number; today: number; fee: number; push: boolean;
  bank: { bank: string; number: string; name: string } | null };

/** Admin home: payment receipts to confirm (refreshes every few seconds), today's total and the payment account. */
export default function AdminHome() {
  const [status, setStatus] = useState<Status>('pending');
  const [pushOk, setPushOk] = useState<boolean | null>(null);
  const me = useLoad(() => adminApi<AdminMe>('/me'));
  const list = useLoad(() => adminApi<{ items: AdminPayment[] }>(`/payments?status=${status}`));
  const { refetch: refetchList } = list;
  const { refetch: refetchMe } = me;

  useEffect(() => { refetchList(); }, [status, refetchList]);

  // live: re-check every 6 seconds while this screen is open
  useFocusEffect(useCallback(() => {
    const t = setInterval(() => { refetchList(); refetchMe(); }, 6000);
    return () => clearInterval(t);
  }, [refetchList, refetchMe]));

  useEffect(() => { registerAdminPush().then(setPushOk).catch(() => setPushOk(false)); }, []);

  const logout = () => {
    const go = () => void adminLogout().then(() => router.replace('/admin/login' as Href));
    if (Platform.OS === 'web') return go();
    Alert.alert('Log out of admin?', 'This phone will stop getting payment alerts.', [
      { text: 'Cancel', style: 'cancel' }, { text: 'Log out', style: 'destructive', onPress: go },
    ]);
  };

  const m = me.data;
  const items = list.data?.items ?? [];

  return (
    <Screen header={<Header title="Admin" right={<IconBtn name="log-out-outline" onPress={logout} />} />}
      refreshing={list.refreshing} onRefresh={() => { list.reload(); me.reload(); }}>
      {me.error && !m ? <ErrorBox message={me.error} onRetry={me.reload} /> : null}

      <View style={{ flexDirection: 'row', gap: 10 }}>
        <Card style={[st.stat, (m?.pending ?? 0) > 0 && { backgroundColor: C.amberl, borderColor: '#F6DDAA' }]}>
          <T style={{ fontSize: 24, fontWeight: '800' }}>{m?.pending ?? '–'}</T>
          <Small>To confirm</Small>
        </Card>
        <Card style={st.stat}>
          <T style={{ fontSize: 24, fontWeight: '800' }}>{m ? naira(m.today) : '–'}</T>
          <Small>Received today</Small>
        </Card>
      </View>

      {pushOk === false && (
        <Card style={{ marginTop: 10, backgroundColor: C.redl, borderColor: '#F7C9CB' }}>
          <Row gap={10}><Icon name="notifications-off" size={18} color={C.red} />
            <Small style={{ flex: 1, color: '#9B2C2F' }}>Alerts are off on this phone. Allow notifications for Enro to hear about new receipts instantly.</Small></Row>
        </Card>
      )}

      <Card style={{ marginTop: 10 }} onPress={() => router.push('/admin/bank' as Href)}>
        <Row>
          <View style={st.ic}><Icon name="business" size={19} color={C.p} /></View>
          <View style={{ flex: 1 }}>
            <Small>Users pay {m ? naira(m.fee) : ''} into</Small>
            {m?.bank
              ? <T style={{ fontWeight: '700', marginTop: 2 }}>{m.bank.number} · {m.bank.bank}{'\n'}<T style={{ fontWeight: '500', color: C.mut }}>{m.bank.name}</T></T>
              : <T style={{ fontWeight: '700', color: C.red, marginTop: 2 }}>No account yet — tap to add</T>}
          </View>
          <Pill text="Change" />
        </Row>
      </Card>

      <View style={{ marginVertical: 16 }}>
        <Chips<Status> value={status} onChange={setStatus} items={[['pending', 'To confirm'], ['paid', 'Confirmed'], ['rejected', 'Rejected']]} />
      </View>

      {list.loading ? <Loading /> : list.error && !list.data ? <ErrorBox message={list.error} onRetry={list.reload} /> :
        items.length === 0 ? (
          <Empty icon={status === 'pending' ? 'checkmark-done' : 'receipt-outline'}
            title={status === 'pending' ? 'All caught up' : 'Nothing here yet'}
            text={status === 'pending' ? 'New receipts appear here instantly.' : undefined} />
        ) : items.map((p) => {
          const late = (p.waiting_sec ?? 0) > 120;
          return (
            <Pressable key={p.id} onPress={() => router.push(`/admin/payment/${p.id}` as Href)} style={({ pressed }) => [st.item, pressed && { opacity: 0.85 }]}>
              <Avatar name={p.name} size={40} />
              <View style={{ flex: 1 }}>
                <T style={{ fontWeight: '700' }} numberOfLines={1}>{p.name}</T>
                <T numberOfLines={1} style={{ fontSize: 12.5, color: C.mut }}>{p.status === 'rejected' ? p.reject_reason : p.email}</T>
              </View>
              <View style={{ alignItems: 'flex-end', gap: 4 }}>
                <T style={{ fontWeight: '800' }}>{naira(p.amount)}</T>
                {p.status === 'pending' && p.waiting_sec != null
                  ? <Pill text={waited(p.waiting_sec)} tone={late ? 'red' : 'amber'} icon="time-outline" />
                  : <Small>{p.reviewed_by ?? ''}</Small>}
              </View>
            </Pressable>
          );
        })}

      {m && <Small style={{ textAlign: 'center', marginTop: 20 }}>Logged in as {m.admin.name}</Small>}
      {status !== 'pending' && <Button title="Back to receipts to confirm" variant="ghost" style={{ marginTop: 12 }} onPress={() => setStatus('pending')} />}
    </Screen>
  );
}

const st = StyleSheet.create({
  stat: { flex: 1, alignItems: 'center', paddingVertical: 16 },
  ic: { width: 40, height: 40, borderRadius: 12, backgroundColor: C.pl, alignItems: 'center', justifyContent: 'center' },
  item: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: C.white, borderWidth: 1, borderColor: C.line, borderRadius: 16, padding: 14, marginBottom: 10 },
});
