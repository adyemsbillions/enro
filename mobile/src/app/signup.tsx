import { useState } from 'react';
import { Pressable, View } from 'react-native';
import { Link, router } from 'expo-router';
import { api, errMsg } from '@/lib/api';
import { deviceId } from '@/lib/device';
import { Button, ErrorNote, Field, H1, Header, Icon, Screen, Sub, T } from '@/components/ui';
import { C } from '@/lib/theme';

export default function Signup() {
  const [f, setF] = useState({ name: '', email: '', phone: '', password: '', referral_code: '' });
  const [agree, setAgree] = useState(true);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const set = (k: keyof typeof f) => (v: string) => setF((p) => ({ ...p, [k]: v }));

  const submit = async () => {
    setError(null);
    if (!agree) return setError('Please accept the Terms to continue');
    setLoading(true);
    try {
      const r = await api<{ user_id: number; phone: string; debug_otp?: string }>('/auth/register', {
        body: { ...f, device_id: await deviceId() },
      });
      router.push({ pathname: '/otp', params: { user_id: String(r.user_id), phone: r.phone, debug: r.debug_otp ?? '' } });
    } catch (e) {
      setError(errMsg(e));
    } finally {
      setLoading(false);
    }
  };

  return (
    <Screen header={<Header title="" />}>
      <H1>Create your account</H1>
      <Sub style={{ marginTop: 6, marginBottom: 22 }}>Start earning airtime, data and cash today.</Sub>
      {error && <ErrorNote text={error} />}
      <Field label="Full name" icon="person-outline" value={f.name} onChangeText={set('name')} placeholder="Adaeze Okafor" autoComplete="name" />
      <Field label="Email" icon="mail-outline" value={f.email} onChangeText={set('email')} placeholder="you@email.com" keyboardType="email-address" autoCapitalize="none" autoComplete="email" />
      <Field label="Phone number" prefix="🇳🇬 +234" value={f.phone} onChangeText={set('phone')} placeholder="803 123 4567" keyboardType="phone-pad" autoComplete="tel" />
      <Field label="Password" icon="lock-closed-outline" value={f.password} onChangeText={set('password')} placeholder="At least 8 characters" secureTextEntry autoComplete="new-password" />
      <Field label="Referral code (optional)" icon="gift-outline" value={f.referral_code} onChangeText={(v) => set('referral_code')(v.toUpperCase())} placeholder="e.g. ENRO-TUNDE12" autoCapitalize="characters" />
      <Pressable onPress={() => setAgree(!agree)} style={{ flexDirection: 'row', gap: 10, marginBottom: 18, marginTop: 2 }}>
        <Icon name={agree ? 'checkbox' : 'square-outline'} size={20} color={agree ? C.p : C.mut} />
        <Sub style={{ flex: 1, fontSize: 13 }}>I agree to the Terms of Use and Privacy Policy. One account per person and device.</Sub>
      </Pressable>
      <Button title="Create account" onPress={submit} loading={loading} />
      <View style={{ flexDirection: 'row', justifyContent: 'center', marginTop: 22 }}>
        <Sub>Already have an account? </Sub>
        <Link href="/login" asChild><Pressable><T style={{ color: C.p, fontWeight: '700' }}>Log in</T></Pressable></Link>
      </View>
    </Screen>
  );
}
