import { useState } from 'react';
import { Pressable, View } from 'react-native';
import { Link, router, useLocalSearchParams } from 'expo-router';
import { api, errMsg } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { deviceId } from '@/lib/device';
import type { User } from '@/lib/types';
import { Button, ErrorNote, Field, H1, Icon, Screen, Sub, T } from '@/components/ui';
import { C } from '@/lib/theme';

type LoginRes = { token?: string; user?: User; need_otp?: boolean; user_id?: number; sent_to?: string; debug_otp?: string };

export default function Login() {
  const { signIn } = useAuth();
  const { notice } = useLocalSearchParams<{ notice?: string }>();
  const [login, setLogin] = useState('');
  const [password, setPassword] = useState('');
  const [show, setShow] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(notice ?? null);

  const submit = async () => {
    setError(null);
    if (!login || !password) return setError('Enter your email/phone and password');
    setLoading(true);
    try {
      const r = await api<LoginRes>('/auth/login', { body: { login, password, device_id: await deviceId() } });
      if (r.need_otp) {
        router.push({ pathname: '/otp', params: { user_id: String(r.user_id), to: r.sent_to ?? '', debug: r.debug_otp ?? '' } });
      } else if (r.token) {
        await signIn(r.token);
        router.replace('/(tabs)');
      }
    } catch (e) {
      setError(errMsg(e));
    } finally {
      setLoading(false);
    }
  };

  return (
    <Screen>
      <View style={{ paddingTop: 24 }}>
        <View style={{ width: 52, height: 52, borderRadius: 16, backgroundColor: C.p, alignItems: 'center', justifyContent: 'center' }}>
          <T style={{ color: C.white, fontSize: 24, fontWeight: '800' }}>E</T>
        </View>
        <H1 style={{ marginTop: 20 }}>Welcome back 👋</H1>
        <Sub style={{ marginTop: 6 }}>Log in to continue earning.</Sub>
      </View>
      <View style={{ marginTop: 26 }}>
        {error && <ErrorNote text={error} />}
        <Field label="Email or phone" icon="mail-outline" value={login} onChangeText={setLogin} autoCapitalize="none" keyboardType="email-address" autoComplete="username" placeholder="you@email.com or 0803…" />
        <Field label="Password" icon="lock-closed-outline" value={password} onChangeText={setPassword} secureTextEntry={!show} autoComplete="password" placeholder="Your password"
          onSubmitEditing={submit} returnKeyType="go"
          right={<Pressable onPress={() => setShow(!show)} hitSlop={8}><Icon name={show ? 'eye-off-outline' : 'eye-outline'} size={19} color={C.mut} /></Pressable>} />
        <Link href="/forgot" asChild><Pressable style={{ alignSelf: 'flex-end', marginTop: -4, marginBottom: 20 }}><T style={{ color: C.p, fontWeight: '700', fontSize: 13 }}>Forgot password?</T></Pressable></Link>
        <Button title="Log in" onPress={submit} loading={loading} />
        <View style={{ flexDirection: 'row', justifyContent: 'center', marginTop: 24 }}>
          <Sub>New to Enro? </Sub>
          <Link href="/signup" asChild><Pressable><T style={{ color: C.p, fontWeight: '700' }}>Create account</T></Pressable></Link>
        </View>
      </View>
    </Screen>
  );
}

