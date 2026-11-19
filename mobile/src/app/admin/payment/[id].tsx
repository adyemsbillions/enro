import { useEffect, useState } from 'react';
import { Alert, Image, Linking, Platform, Pressable, StyleSheet, TextInput, View } from 'react-native';
import { router, useLocalSearchParams, type Href } from 'expo-router';
import { errMsg } from '@/lib/api';
import { adminApi, getAdminToken, receiptSource, waited, type AdminPayment } from '@/lib/admin';
import { naira } from '@/lib/format';
import { useLoad } from '@/lib/useLoad';
import { C } from '@/lib/theme';
import { useToast } from '@/components/toast';
import { Button, Card, ErrorBox, ErrorNote, Header, Icon, Loading, Pill, Row, Screen, Small, T } from '@/components/ui';

const REASONS = ['Payment not received', 'Wrong amount', 'Receipt not clear'];

export default function AdminPaymentScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const toast = useToast();
  const { data, error, reload } = useLoad(() => adminApi<{ payment: AdminPayment & { joined: string; earlier_receipts: number } }>(`/payments/${id}`));
  const [token, setToken] = useState<string | null>(null);
  const [rejecting, setRejecting] = useState(false);
  const [reason, setReason] = useState('');
  const [busy, setBusy] = useState<'ok' | 'no' | null>(null);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => { getAdminToken().then(setToken); }, []);

  const p = data?.payment;
  if (!p) return <Screen header={<Header title="Receipt" />}>{error ? <ErrorBox message={error} onRetry={reload} /> : <Loading />}</Screen>;
  const open = p.status === 'pending';

  const act = async (kind: 'ok' | 'no') => {
    setErr(null);
    setBusy(kind);
    try {
      if (kind === 'ok') await adminApi(`/payments/${p.id}/approve`, { method: 'POST' });
      else await adminApi(`/payments/${p.id}/reject`, { body: { reason } });
      toast(kind === 'ok' ? `Confirmed — ${p.name.split(' ')[0]} is unlocked` : 'Rejected — user asked to upload again');
      if (router.canGoBack()) router.back();
      else router.replace('/admin' as Href);
    } catch (e) {
      setErr(errMsg(e));
    } finally {
      setBusy(null);
    }
  };

  const confirm = () => {
    const msg = `Only confirm if ${naira(p.amount)} from ${p.name} is in your account.`;
    if (Platform.OS === 'web') return void act('ok');
    Alert.alert('Confirm payment?', msg, [{ text: 'Cancel', style: 'cancel' }, { text: 'Confirm', onPress: () => void act('ok') }]);
  };

  return (
    <Screen header={<Header title="Receipt" />}>
      <Card style={{ padding: 0, overflow: 'hidden' }}>
        {token && p.has_receipt
          ? <Image source={receiptSource(p.id, token)} style={st.img} resizeMode="contain" />
          : <View style={[st.img, { alignItems: 'center', justifyContent: 'center' }]}><Small>No receipt image</Small></View>}
      </Card>

      <Card style={{ marginTop: 12 }}>
        <Row>
          <View style={{ flex: 1 }}>
            <T style={{ fontWeight: '800', fontSize: 17 }}>{p.name}</T>
            <Small>{p.email}{p.phone ? ` · ${p.phone}` : ''}</Small>
          </View>
          <T style={{ fontWeight: '800', fontSize: 20, color: C.cash }}>{naira(p.amount)}</T>
        </Row>
        <View style={st.meta}>
          <Meta label="Sent" value={open && p.waiting_sec != null ? `${waited(p.waiting_sec)} ago` : new Date(p.created_at.replace(' ', 'T')).toLocaleString()} />
          <Meta label="Reference" value={p.reference} />
          <Meta label="Joined" value={new Date(data.payment.joined.replace(' ', 'T')).toLocaleDateString()} />
          {data.payment.earlier_receipts > 0 && <Meta label="Earlier receipts" value={String(data.payment.earlier_receipts)} warn />}
        </View>
        {!open && (
          <Row gap={8} style={{ marginTop: 12 }}>
            <Pill text={p.status === 'paid' ? 'Confirmed' : 'Rejected'} tone={p.status === 'paid' ? 'cash' : 'red'} />
            <Small style={{ flex: 1 }}>{p.reviewed_by ? `by ${p.reviewed_by}` : ''}{p.reject_reason ? ` · ${p.reject_reason}` : ''}</Small>
          </Row>
        )}
      </Card>

      {open && (
        <View style={{ marginTop: 16 }}>
          {err && <ErrorNote text={err} />}
          {!rejecting ? (
            <>
              <Button title="Confirm payment" icon="checkmark-circle" variant="cash" onPress={confirm} loading={busy === 'ok'} disabled={busy === 'no'} />
              <Button title="Reject" icon="close-circle-outline" variant="danger" style={{ marginTop: 10 }} onPress={() => setRejecting(true)} disabled={!!busy} />
            </>
          ) : (
            <Card>
              <T style={{ fontWeight: '700' }}>Why are you rejecting it?</T>
              <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 10 }}>
                {REASONS.map((r) => (
                  <Pressable key={r} onPress={() => setReason(r)} style={[st.chip, reason === r && st.chipOn]}>
                    <T style={{ fontSize: 13, fontWeight: '600', color: reason === r ? C.white : C.ink }}>{r}</T>
                  </Pressable>
                ))}
              </View>
              <TextInput value={reason} onChangeText={setReason} placeholder="Or type a reason" placeholderTextColor="#A9A6B9" style={st.inp} />
              <Button title="Reject receipt" variant="danger" style={{ marginTop: 12 }} onPress={() => act('no')} loading={busy === 'no'} />
              <Button title="Cancel" variant="ghost" style={{ marginTop: 8 }} onPress={() => setRejecting(false)} />
            </Card>
          )}
        </View>
      )}

      {p.has_receipt && token && Platform.OS === 'web' && (
        <Pressable onPress={() => Linking.openURL(receiptSource(p.id, token).uri)} style={{ marginTop: 14, alignSelf: 'center' }}>
          <Row gap={6}><Icon name="open-outline" size={15} color={C.p} /><T style={{ color: C.p, fontWeight: '700' }}>Open full image</T></Row>
        </Pressable>
      )}
    </Screen>
  );
}

function Meta({ label, value, warn }: { label: string; value: string; warn?: boolean }) {
  return (
    <View style={{ flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 6 }}>
      <Small>{label}</Small>
      <T style={{ fontWeight: '600', fontSize: 13.5, color: warn ? C.amber : C.ink }}>{value}</T>
    </View>
  );
}

const st = StyleSheet.create({
  img: { width: '100%', height: 420, backgroundColor: '#F2EFFF' },
  meta: { marginTop: 12, borderTopWidth: 1, borderTopColor: C.line, paddingTop: 6 },
  chip: { paddingHorizontal: 12, paddingVertical: 8, borderRadius: 20, borderWidth: 1, borderColor: C.line, backgroundColor: C.white },
  chipOn: { backgroundColor: C.red, borderColor: C.red },
  inp: { marginTop: 12, borderWidth: 1.5, borderColor: C.line, borderRadius: 14, paddingHorizontal: 12, height: 46, fontSize: 14, color: C.ink, backgroundColor: C.white },
});
