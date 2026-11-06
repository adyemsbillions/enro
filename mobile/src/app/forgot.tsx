import { useState } from 'react';
import { router } from 'expo-router';
import { api, errMsg } from '@/lib/api';
import { Button, ErrorNote, Field, H1, Header, Screen, Sub } from '@/components/ui';

export default function Forgot() {
  const [login, setLogin] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const submit = async () => {
    setError(null);
    setLoading(true);
    try {
      const r = await api<{ user_id: number; phone: string; debug_otp?: string }>('/auth/forgot-password', { body: { login } });
      router.push({ pathname: '/otp', params: { user_id: String(r.user_id), phone: r.phone, debug: r.debug_otp ?? '', mode: 'reset' } });
    } catch (e) {
      setError(errMsg(e));
    } finally {
      setLoading(false);
    }
  };

  return (
    <Screen header={<Header title="" />}>
      <H1>Forgot password?</H1>
      <Sub style={{ marginTop: 6, marginBottom: 22 }}>Enter your email or phone. We’ll text a code to your verified number.</Sub>
      {error && <ErrorNote text={error} />}
      <Field label="Email or phone" icon="mail-outline" value={login} onChangeText={setLogin} autoCapitalize="none" placeholder="you@email.com or 0803…" />
      <Button title="Send code" onPress={submit} loading={loading} disabled={!login} />
    </Screen>
  );
}
