import { useEffect, useState } from 'react';
import { Modal, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { api, errMsg } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { fmt, naira } from '@/lib/format';
import { useLoad } from '@/lib/useLoad';
import type { WalletOptions } from '@/lib/types';
import { C, NETWORKS } from '@/lib/theme';
import { Button, Card, Chips, ErrorBox, ErrorNote, Field, H2, Header, Icon, IconBox, IconBtn, Loading, Row, Screen, Small, Sub, T, Tabs } from '@/components/ui';

type Done = { icon: 'checkmark-circle' | 'time'; title: string; text: string };

export default function Wallet() {
  const params = useLocalSearchParams<{ tab?: string }>();
  const { me } = useAuth();
  const [tab, setTab] = useState<'airtime' | 'cash'>(params.tab === 'cash' ? 'cash' : 'airtime');
  const [done, setDone] = useState<Done | null>(null);
  const { data, error, reload, loading } = useLoad(() => api<WalletOptions>('/wallet/options'));

  // switch tab when opened from Home with ?tab=cash / ?tab=airtime
  const [lastParam, setLastParam] = useState(params.tab);
  if (params.tab !== lastParam) {
    setLastParam(params.tab);
    if (params.tab === 'cash' || params.tab === 'airtime') setTab(params.tab);
  }

  return (
    <Screen header={<Header title="Wallet" back={false} right={<IconBtn name="time-outline" onPress={() => router.push('/history')} />} />}>
      <View style={{ flexDirection: 'row', gap: 10 }}>
        <Card style={[st.bal, { backgroundColor: C.goldl, borderColor: '#F8E3A8' }]}>
          <Row gap={5}><Icon name="logo-bitcoin" size={14} color={C.amber} /><Small style={{ color: C.amber, fontWeight: '700' }}>Coins</Small></Row>
          <T style={st.balV}>{fmt(me?.user.coins ?? 0)}</T>
          <Small>For airtime & data</Small>
        </Card>
        <Card style={[st.bal, { backgroundColor: C.cashl, borderColor: '#BDEBD3' }]}>
          <Row gap={5}><Icon name="cash-outline" size={14} color={C.cash} /><Small style={{ color: C.cash, fontWeight: '700' }}>Cash</Small></Row>
          <T style={st.balV}>{naira(me?.user.cash ?? 0)}</T>
          <Small>For bank withdrawal</Small>
        </Card>
      </View>

      <View style={{ marginTop: 16 }}>
        <Tabs value={tab} onChange={setTab} items={[['airtime', 'Airtime & Data'], ['cash', 'Withdraw cash']]} />
      </View>

      {loading ? <Loading /> : !data ? <ErrorBox message={error ?? ''} onRetry={reload} /> :
        tab === 'airtime' ? <Redeem opts={data} onDone={setDone} /> : <Withdraw opts={data} onDone={setDone} />}

      <Modal visible={!!done} transparent animationType="slide" onRequestClose={() => setDone(null)}>
        <Pressable style={st.sheetBg} onPress={() => setDone(null)}>
          <Pressable onPress={() => {}} style={st.sheet}>
            <View style={{ alignSelf: 'center' }}><IconBox name={done?.icon ?? 'checkmark-circle'} tone="cash" size={64} /></View>
            <H2 style={{ textAlign: 'center', marginTop: 16 }}>{done?.title}</H2>
            <Sub style={{ textAlign: 'center', marginTop: 8 }}>{done?.text}</Sub>
            <Button title="Done" style={{ marginTop: 20 }} onPress={() => setDone(null)} />
          </Pressable>
        </Pressable>
      </Modal>
    </Screen>
  );
}

function Redeem({ opts, onDone }: { opts: WalletOptions; onDone: (d: Done) => void }) {
  const { me, setBalances } = useAuth();
  const [kind, setKind] = useState<'airtime' | 'data'>('airtime');
  const [network, setNetwork] = useState('mtn');
  const [phone, setPhone] = useState(me?.user.phone ?? '');
  const [choice, setChoice] = useState<number | null>(null); // airtime amount or data plan id
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const coins = me?.user.coins ?? 0;

  const options = kind === 'airtime'
    ? opts.airtime.map((a) => ({ key: a.amount, label: naira(a.amount), meta: '', coins: a.coins }))
    : (opts.data_plans[network] ?? []).map((p) => ({ key: p.id, label: p.name, meta: p.validity, coins: p.coins }));
  const sel = options.find((o) => o.key === choice);

  const redeem = async () => {
    if (!sel) return;
    setError(null);
    setLoading(true);
    try {
      const body = kind === 'airtime' ? { type: 'airtime', network, phone, amount: sel.key } : { type: 'data', network, phone, plan_id: sel.key };
      const r = await api<{ coins: number; message: string }>('/wallet/redeem', { body });
      setBalances({ coins: r.coins });
      setChoice(null);
      onDone({ icon: 'checkmark-circle', title: `${kind === 'airtime' ? 'Airtime' : 'Data'} on its way!`,
        text: `${sel.label} ${NETWORKS[network].label} ${kind} for ${phone}. ${fmt(sel.coins)} coins used. ${r.message}` });
    } catch (e) {
      setError(errMsg(e));
    } finally {
      setLoading(false);
    }
  };

  return (
    <View style={{ marginTop: 16 }}>
      <Chips value={kind} onChange={(k) => { setKind(k); setChoice(null); }} items={[['airtime', 'Airtime'], ['data', 'Data bundle']]} />
      <T style={st.label}>Network</T>
      <View style={{ flexDirection: 'row', gap: 8 }}>
        {opts.networks.map((n) => {
          const net = NETWORKS[n];
          return (
            <Pressable key={n} onPress={() => { setNetwork(n); if (kind === 'data') setChoice(null); }} style={[st.net, network === n && st.on]}>
              <View style={[st.netDot, { backgroundColor: net.bg }]}><T style={{ color: net.fg, fontSize: 10, fontWeight: '800' }}>{net.short}</T></View>
              <T style={{ fontSize: 12, fontWeight: '700' }}>{net.label}</T>
            </Pressable>
          );
        })}
      </View>
      <View style={{ marginTop: 16 }}>
        <Field label="Phone number" icon="call-outline" value={phone} onChangeText={setPhone} keyboardType="phone-pad" placeholder="0803 123 4567" />
      </View>
      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
        <T style={{ fontSize: 14, fontWeight: '700' }}>{kind === 'airtime' ? 'Amount' : 'Bundle'}</T>
        <Small>{fmt(opts.coins_per_naira * 100)} coins = ₦100</Small>
      </View>
      {options.length === 0 ? <Sub style={{ marginTop: 10 }}>No bundles for this network yet.</Sub> : (
        <View style={st.grid}>
          {options.map((o) => {
            const short = o.coins > coins;
            return (
              <Pressable key={o.key} onPress={() => setChoice(o.key)} style={[st.amt, choice === o.key && st.on, short && { opacity: 0.45 }]}>
                <T style={{ fontSize: 17, fontWeight: '800' }}>{o.label}</T>
                <Small>{o.meta ? `${o.meta} · ` : ''}{fmt(o.coins)} coins</Small>
              </Pressable>
            );
          })}
        </View>
      )}
      <View style={{ marginTop: 20 }}>
        {error && <ErrorNote text={error} />}
        <Button title={sel ? (sel.coins > coins ? `Need ${fmt(sel.coins - coins)} more coins` : `Redeem for ${fmt(sel.coins)} coins`) : 'Choose an option'}
          onPress={redeem} loading={loading} disabled={!sel || sel.coins > coins || phone.replace(/\D/g, '').length < 10} />
      </View>
    </View>
  );
}

function Withdraw({ opts, onDone }: { opts: WalletOptions; onDone: (d: Done) => void }) {
  const { me, setBalances } = useAuth();
  const [bank, setBank] = useState<{ name: string; code: string } | null>(null);
  const [picking, setPicking] = useState(false);
  const [acct, setAcct] = useState('');
  const [amount, setAmount] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const cash = me?.user.cash ?? 0;
  const a = Number(amount) || 0;
  const total = a + opts.withdraw_fee;

  // look up the account name once a bank and 10 digits are entered
  const lookupKey = bank && acct.length === 10 ? `${bank.code}:${acct}` : null;
  const [lookup, setLookup] = useState<{ key: string; name: string | null } | null>(null);
  useEffect(() => {
    if (!lookupKey) return;
    const [bank_code, account_number] = lookupKey.split(':');
    let live = true;
    api<{ account_name: string }>('/wallet/resolve-account', { body: { bank_code, account_number } })
      .then((r) => { if (live) setLookup({ key: lookupKey, name: r.account_name }); })
      .catch((e) => { if (live) { setLookup({ key: lookupKey, name: null }); setError(errMsg(e)); } });
    return () => { live = false; };
  }, [lookupKey]);
  const acctName = lookup?.key === lookupKey ? lookup.name : null;
  const resolving = !!lookupKey && lookup?.key !== lookupKey;

  const valid = !!acctName && a >= opts.min_withdraw && total <= cash;

  const withdraw = async () => {
    setError(null);
    setLoading(true);
    try {
      const r = await api<{ cash: number }>('/wallet/withdraw', { body: { bank_code: bank!.code, account_number: acct, amount: a } });
      setBalances({ cash: r.cash });
      setAmount('');
      onDone({ icon: 'time', title: 'Withdrawal requested', text: `${naira(a)} to ${bank!.name} •••• ${acct.slice(-4)}. We'll notify you once it's paid (usually within 24 hours).` });
    } catch (e) {
      setError(errMsg(e));
    } finally {
      setLoading(false);
    }
  };

  return (
    <View style={{ marginTop: 16 }}>
      <Card style={{ backgroundColor: '#FBFAFF', padding: 12 }}>
        <Row gap={8}><Icon name="information-circle-outline" size={18} color={C.p} /><Small style={{ flex: 1 }}>Min {naira(opts.min_withdraw)} · Fee {naira(opts.withdraw_fee)} · Paid within 24h after approval</Small></Row>
      </Card>

      <T style={st.label}>Bank</T>
      <Pressable onPress={() => setPicking(true)} style={st.select}>
        <Icon name="business-outline" size={18} color={C.mut} />
        <T style={{ flex: 1, fontSize: 15, color: bank ? C.ink : '#A9A6B9' }}>{bank?.name ?? 'Choose your bank'}</T>
        <Icon name="chevron-down" size={18} color={C.mut} />
      </Pressable>

      <View style={{ marginTop: 14 }}>
        <Field label="Account number" icon="card-outline" value={acct} onChangeText={(v) => { setError(null); setAcct(v.replace(/\D/g, '').slice(0, 10)); }}
          keyboardType="number-pad" placeholder="10-digit account number"
          hint={<View style={{ minHeight: 18, marginTop: 6 }}>
            {resolving ? <Small>Checking account…</Small> : acctName ? <Row gap={4}><Icon name="checkmark-circle" size={14} color={C.cash} /><T style={{ color: C.cash, fontWeight: '700', fontSize: 12 }}>{acctName}</T></Row> : null}
          </View>} />
        <Field label="Amount" prefix="₦" value={amount} onChangeText={(v) => setAmount(v.replace(/\D/g, ''))} keyboardType="number-pad" placeholder="0"
          right={<Pressable onPress={() => setAmount(String(Math.max(0, cash - opts.withdraw_fee)))}><T style={{ color: C.p, fontWeight: '700', fontSize: 13 }}>Max</T></Pressable>}
          hint={a > 0 ? (a < opts.min_withdraw
            ? <Small style={{ marginTop: 6, color: C.red }}>Minimum withdrawal is {naira(opts.min_withdraw)}</Small>
            : <Small style={{ marginTop: 6, color: total > cash ? C.red : C.mut }}>Total deducted: {naira(total)}{total > cash ? ' — more than your balance' : ''}</Small>) : null} />
      </View>
      {cash < opts.min_withdraw + opts.withdraw_fee && (
        <Small style={{ marginBottom: 12, textAlign: 'center' }}>
          You need {naira(opts.min_withdraw + opts.withdraw_fee - cash)} more cash to withdraw. Special tasks pay cash.
        </Small>
      )}

      {error && <ErrorNote text={error} />}
      <Button title={a ? `Withdraw ${naira(a)}` : 'Withdraw'} variant="cash" onPress={withdraw} loading={loading} disabled={!valid} />

      <Modal visible={picking} animationType="slide" transparent onRequestClose={() => setPicking(false)}>
        <Pressable style={st.sheetBg} onPress={() => setPicking(false)}>
          <Pressable onPress={() => {}} style={[st.sheet, { maxHeight: '70%', paddingHorizontal: 0 }]}>
            <H2 style={{ paddingHorizontal: 20, marginBottom: 8 }}>Choose bank</H2>
            <ScrollView>
              {opts.banks.map((b) => (
                <Pressable key={b.code} onPress={() => { setBank(b); setPicking(false); }} style={st.bankRow}>
                  <T style={{ flex: 1, fontSize: 15 }}>{b.name}</T>
                  {bank?.code === b.code && <Icon name="checkmark" size={18} color={C.p} />}
                </Pressable>
              ))}
            </ScrollView>
          </Pressable>
        </Pressable>
      </Modal>
    </View>
  );
}

const st = StyleSheet.create({
  bal: { flex: 1 },
  balV: { fontSize: 22, fontWeight: '800', marginTop: 4 },
  label: { fontSize: 14, fontWeight: '700', marginTop: 16, marginBottom: 8 },
  net: { flex: 1, borderWidth: 1.5, borderColor: C.line, backgroundColor: C.white, borderRadius: 14, paddingVertical: 10, alignItems: 'center', gap: 6 },
  netDot: { width: 34, height: 34, borderRadius: 17, alignItems: 'center', justifyContent: 'center' },
  on: { borderColor: C.p, backgroundColor: C.pl },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10, marginTop: 8 },
  amt: { width: '48%', flexGrow: 1, borderWidth: 1.5, borderColor: C.line, backgroundColor: C.white, borderRadius: 14, padding: 12 },
  select: { flexDirection: 'row', alignItems: 'center', gap: 10, height: 52, backgroundColor: C.white, borderWidth: 1.5, borderColor: C.line, borderRadius: 14, paddingHorizontal: 14 },
  sheetBg: { flex: 1, backgroundColor: 'rgba(10,8,20,.45)', justifyContent: 'flex-end' },
  sheet: { backgroundColor: C.white, borderTopLeftRadius: 26, borderTopRightRadius: 26, padding: 20, paddingBottom: 34 },
  bankRow: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 20, paddingVertical: 15, borderBottomWidth: 1, borderBottomColor: C.line },
});
