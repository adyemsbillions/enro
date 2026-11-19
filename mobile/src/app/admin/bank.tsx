import { useEffect, useState } from 'react';
import { router, type Href } from 'expo-router';
import { errMsg } from '@/lib/api';
import { adminApi } from '@/lib/admin';
import { useToast } from '@/components/toast';
import { Button, ErrorNote, Field, Header, Screen, Sub } from '@/components/ui';

type Bank = { bank: string; number: string; name: string } | null;

/** The account users pay the unlock fee into. Changes show in the app straight away. */
export default function AdminBank() {
  const toast = useToast();
  const [bank, setBank] = useState('');
  const [number, setNumber] = useState('');
  const [name, setName] = useState('');
  const [saving, setSaving] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    adminApi<{ bank: Bank }>('/me').then((r) => {
      if (!r.bank) return;
      setBank(r.bank.bank);
      setNumber(r.bank.number);
      setName(r.bank.name);
    }).catch(() => {});
  }, []);

  const save = async () => {
    setErr(null);
    setSaving(true);
    try {
      await adminApi('/bank', { body: { bank, number, name } });
      toast('Payment account saved');
      if (router.canGoBack()) router.back();
      else router.replace('/admin' as Href);
    } catch (e) {
      setErr(errMsg(e));
    } finally {
      setSaving(false);
    }
  };

  return (
    <Screen header={<Header title="Payment account" />}>
      <Sub style={{ marginBottom: 18 }}>Users send the unlock fee to this account. Change it any time — the app shows the new one immediately.</Sub>
      {err && <ErrorNote text={err} />}
      <Field label="Bank" icon="business-outline" value={bank} onChangeText={setBank} placeholder="e.g. Opay, Moniepoint, GTBank" />
      <Field label="Account number" icon="keypad-outline" value={number} onChangeText={(v) => setNumber(v.replace(/\D/g, ''))} keyboardType="number-pad" maxLength={12} placeholder="10 digits" />
      <Field label="Account name" icon="person-outline" value={name} onChangeText={setName} placeholder="Name on the account" />
      <Button title="Save account" onPress={save} loading={saving} style={{ marginTop: 6 }} />
    </Screen>
  );
}
