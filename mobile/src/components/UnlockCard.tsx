import { useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';
import * as Clipboard from 'expo-clipboard';
import { errMsg } from '@/lib/api';
import { naira } from '@/lib/format';
import { pickImage, uploadReceipt } from '@/lib/unlock';
import type { UnlockInfo } from '@/lib/types';
import { C } from '@/lib/theme';
import { useToast } from './toast';
import { Button, Card, ErrorNote, Icon, Row, Small, T } from './ui';

/**
 * Shown on special tasks until the user has paid the one-time unlock fee:
 * pay into the account shown, upload the receipt, an admin confirms it.
 * While a receipt is being confirmed it re-checks every few seconds (onChange reloads the screen quietly).
 */
export function UnlockCard({ info, onChange }: { info: UnlockInfo; onChange: () => void }) {
  const toast = useToast();
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(null);
  const pending = info.unlock_receipt?.status === 'pending';
  const bank = info.unlock_bank;

  useEffect(() => {
    if (!pending) return;
    const t = setInterval(onChange, 8000);
    return () => clearInterval(t);
  }, [pending, onChange]);

  const copy = async (text: string, what: string) => {
    await Clipboard.setStringAsync(text);
    toast(`${what} copied`);
  };

  const upload = async () => {
    setErr(null);
    try {
      const img = await pickImage();
      if (!img) return;
      setBusy(true);
      await uploadReceipt(img);
      toast('Receipt sent');
      onChange();
    } catch (e) {
      setErr(errMsg(e));
    } finally {
      setBusy(false);
    }
  };

  if (pending) {
    return (
      <Card style={{ alignItems: 'center', paddingVertical: 24, backgroundColor: C.amberl, borderColor: '#F6DDAA' }}>
        <ActivityIndicator color={C.amber} size="large" />
        <T style={{ fontWeight: '800', fontSize: 16, marginTop: 12 }}>Confirming your payment…</T>
        <Small style={{ textAlign: 'center', marginTop: 6, color: C.amber, lineHeight: 18 }}>
          {"You'll be unlocked in under 2 minutes."}
        </Small>
      </Card>
    );
  }

  return (
    <Card style={{ borderColor: '#D9D2FF', backgroundColor: '#FBFAFF' }}>
      <Row>
        <View style={st.lock}><Icon name="lock-closed" size={22} color={C.p} /></View>
        <View style={{ flex: 1 }}>
          <T style={{ fontWeight: '800', fontSize: 16 }}>Unlock real-cash tasks</T>
          <Small>One-time payment of {naira(info.unlock_fee)}</Small>
        </View>
      </Row>

      {!bank ? (
        <Small style={{ marginTop: 14, textAlign: 'center' }}>Payments open soon. Check back shortly.</Small>
      ) : (
        <>
          <T style={{ marginTop: 14, fontWeight: '600' }}>Send {naira(info.unlock_fee)} to this account, then upload your receipt.</T>

          <View style={st.bank}>
            <Line label="Amount" value={naira(info.unlock_fee)} onCopy={() => copy(String(info.unlock_fee), 'Amount')} />
            <Line label="Account number" value={bank.number} big onCopy={() => copy(bank.number, 'Account number')} />
            <Line label="Bank" value={bank.bank} />
            <Line label="Account name" value={bank.name} last />
          </View>

          {info.unlock_receipt?.status === 'rejected' && (
            <View style={{ marginTop: 12 }}>
              <ErrorNote text={`Payment not confirmed${info.unlock_receipt.reason ? `: ${info.unlock_receipt.reason}` : ''}. Upload the correct receipt.`} />
            </View>
          )}
          {err && <View style={{ marginTop: 12 }}><ErrorNote text={err} /></View>}

          <Button title="I've paid · Upload receipt" icon="cloud-upload-outline" style={{ marginTop: 14 }} onPress={upload} loading={busy} />
          <Row gap={6} style={{ justifyContent: 'center', marginTop: 10 }}>
            <Icon name="flash" size={14} color={C.cash} />
            <Small>Confirmed in under 2 minutes</Small>
          </Row>
        </>
      )}
    </Card>
  );
}

function Line({ label, value, big, last, onCopy }: { label: string; value: string; big?: boolean; last?: boolean; onCopy?: () => void }) {
  return (
    <View style={[st.line, last && { borderBottomWidth: 0 }]}>
      <View style={{ flex: 1 }}>
        <Small>{label}</Small>
        <T style={{ fontWeight: '800', fontSize: big ? 20 : 15, letterSpacing: big ? 1 : 0, marginTop: 2 }} selectable>{value}</T>
      </View>
      {onCopy && (
        <Pressable onPress={onCopy} hitSlop={8} style={st.copy}>
          <Icon name="copy-outline" size={15} color={C.p} />
          <T style={{ color: C.p, fontWeight: '700', fontSize: 12.5 }}>Copy</T>
        </Pressable>
      )}
    </View>
  );
}

const st = StyleSheet.create({
  lock: { width: 46, height: 46, borderRadius: 14, backgroundColor: C.pl, alignItems: 'center', justifyContent: 'center' },
  bank: { marginTop: 12, backgroundColor: C.white, borderRadius: 14, borderWidth: 1, borderColor: C.line, paddingHorizontal: 14 },
  line: { flexDirection: 'row', alignItems: 'center', paddingVertical: 11, borderBottomWidth: 1, borderBottomColor: C.line },
  copy: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: C.pl, paddingHorizontal: 10, paddingVertical: 6, borderRadius: 10 },
});
