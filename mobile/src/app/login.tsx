import { useEffect, useState } from 'react';
import { Alert, Pressable, View } from 'react-native';
import { Link, router, useLocalSearchParams } from 'expo-router';
import { api, ApiError, errMsg } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { deviceId } from '@/lib/device';
import { bioAsked, bioAvailable, bioCredentials, bioEnabled, disableBio, enableBio, forgetUser, getLastUser, setBioAsked, type LastUser } from '@/lib/quickLogin';
import type { User } from '@/lib/types';
import { Avatar, Button, ErrorNote, Field, H1, Icon, Screen, Sub, T } from '@/components/ui';
import { C } from '@/lib/theme';

type LoginRes = { token?: string; user?: User; need_otp?: boolean; user_id?: number; sent_to?: string; debug_otp?: string };

/** Asks once, after a password login, whether to use the fingerprint next time. */
function offerFingerprint(login: string, password: string): Promise<void> {
  return new Promise((resolve) => {
    Alert.alert('Log in faster next time?', 'Use your fingerprint instead of typing your password.', [
      { text: 'Not now', style: 'cancel', onPress: () => void setBioAsked().finally(resolve) },
      { text: 'Use fingerprint', onPress: () => void enableBio(login, password).finally(resolve) },
    ], { cancelable: false });
  });
}

export default function Login() {
  const { signIn } = useAuth();
  const { notice } = useLocalSearchParams<{ notice?: string }>();
  const [last, setLast] = useState<LastUser | null>(null);
  const [bio, setBio] = useState(false);
  const [checked, setChecked] = useState(false);
  const [login, setLogin] = useState('');
  const [password, setPassword] = useState('');
  const [show, setShow] = useState(false);
  const [loading, setLoading] = useState<'pw' | 'bio' | null>(null);
  const [error, setError] = useState<string | null>(notice ?? null);

  useEffect(() => {
    (async () => {
      const u = await getLastUser().catch(() => null);
      setLast(u);
      setBio(u ? await bioEnabled().catch(() => false) : false);
      setChecked(true);
    })();
  }, []);

  const first = (last?.name ?? '').trim().split(/\s+/)[0] || 'there';

  const doLogin = async (id: string, pw: string, viaBio: boolean) => {
    const r = await api<LoginRes>('/auth/login', { body: { login: id, password: pw, device_id: await deviceId() } });
    if (r.need_otp) {
      router.push({ pathname: '/otp', params: { user_id: String(r.user_id), to: r.sent_to ?? '', debug: r.debug_otp ?? '' } });
    } else if (r.token) {
      await signIn(r.token);
      if (!viaBio && bioAvailable() && !(await bioEnabled()) && !(await bioAsked())) await offerFingerprint(id, pw);
      router.replace('/(tabs)');
    }
  };

  const submit = async () => {
    setError(null);
    const id = last ? last.login : login.trim();
    if (!id || !password) return setError(last ? 'Enter your password' : 'Enter your email/phone and password');
    setLoading('pw');
    try {
      await doLogin(id, password, false);
    } catch (e) {
      setError(errMsg(e));
    } finally {
      setLoading(null);
    }
  };

  const fingerprint = async () => {
    setError(null);
    setLoading('bio');
    try {
      const c = await bioCredentials();
      if (!c) return setError('Fingerprint not confirmed. Try again or enter your password.');
      await doLogin(c.login, c.password, true);
    } catch (e) {
      if (e instanceof ApiError && e.status >= 400 && e.status < 500 && e.status !== 429) {
        // the saved password no longer works (e.g. it was changed): go back to password login
        await disableBio();
        setBio(false);
        setError('Please enter your password to log in.');
      } else {
        setError(errMsg(e));
      }
    } finally {
      setLoading(null);
    }
  };

  const switchAccount = async () => {
    await forgetUser();
    setLast(null);
    setBio(false);
    setPassword('');
    setError(null);
  };

  if (!checked) return <Screen><View /></Screen>;

  return (
    <Screen>
      <View style={{ paddingTop: 24 }}>
        {last ? <Avatar name={last.name || last.login} size={58} /> : (
          <View style={{ width: 52, height: 52, borderRadius: 16, backgroundColor: C.p, alignItems: 'center', justifyContent: 'center' }}>
            <T style={{ color: C.white, fontSize: 24, fontWeight: '800' }}>E</T>
          </View>
        )}
        <H1 style={{ marginTop: 20 }}>{last ? `Welcome back, ${first} 👋` : 'Welcome back 👋'}</H1>
        <Sub style={{ marginTop: 6 }}>{last ? last.login : 'Log in to continue earning.'}</Sub>
      </View>
      <View style={{ marginTop: 26 }}>
        {error && <ErrorNote text={error} />}
        {bio && (
          <>
            <Button title="Log in with fingerprint" icon="finger-print" onPress={fingerprint} loading={loading === 'bio'} disabled={loading === 'pw'} />
            <Sub style={{ textAlign: 'center', marginVertical: 16 }}>or enter your password</Sub>
          </>
        )}
        {!last && <Field label="Email or phone" icon="mail-outline" value={login} onChangeText={setLogin} autoCapitalize="none" keyboardType="email-address" autoComplete="username" placeholder="you@email.com or 0803…" />}
        <Field label="Password" icon="lock-closed-outline" value={password} onChangeText={setPassword} secureTextEntry={!show} autoComplete="password" placeholder="Your password"
          onSubmitEditing={submit} returnKeyType="go"
          right={<Pressable onPress={() => setShow(!show)} hitSlop={8}><Icon name={show ? 'eye-off-outline' : 'eye-outline'} size={19} color={C.mut} /></Pressable>} />
        <Link href="/forgot" asChild><Pressable style={{ alignSelf: 'flex-end', marginTop: -4, marginBottom: 20 }}><T style={{ color: C.p, fontWeight: '700', fontSize: 13 }}>Forgot password?</T></Pressable></Link>
        <Button title="Log in" variant={bio ? 'ghost' : undefined} onPress={submit} loading={loading === 'pw'} disabled={loading === 'bio'} />
        {last ? (
          <Pressable onPress={switchAccount} style={{ alignSelf: 'center', marginTop: 24 }} hitSlop={8}>
            <T style={{ color: C.p, fontWeight: '700' }}>Not {first}? Use another account</T>
          </Pressable>
        ) : (
          <View style={{ flexDirection: 'row', justifyContent: 'center', marginTop: 24 }}>
            <Sub>New to Enro? </Sub>
            <Link href="/signup" asChild><Pressable><T style={{ color: C.p, fontWeight: '700' }}>Create account</T></Pressable></Link>
          </View>
        )}
      </View>
    </Screen>
  );
}
