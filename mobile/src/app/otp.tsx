import { useEffect, useRef, useState } from 'react';
import { Pressable, StyleSheet, TextInput, View, type TextInputInstance } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { api, errMsg } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { Button, ErrorNote, H1, Header, IconBox, Screen, Small, Sub, T } from '@/components/ui';
import { useToast } from '@/components/toast';
import { C } from '@/lib/theme';

/** Phone verification (sign-up) and password reset both use this screen. mode=reset adds a new-password field. */
export default function Otp() {
  const { user_id, to, debug, mode } = useLocalSearchParams<{ user_id: string; to: string; debug?: string; mode?: string }>();
  const { signIn } = useAuth();
  const toast = useToast();
  const [code, setCode] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [wait, setWait] = useState(60);
  const [devCode, setDevCode] = useState(debug || '');
  const input = useRef<TextInputInstance>(null);
  const reset = mode === 'reset';

  useEffect(() => {
    const t = setInterval(() => setWait((w) => (w > 0 ? w - 1 : 0)), 1000);
    return () => clearInterval(t);
  }, []);

  const verify = async () => {
    setError(null);
    if (code.length !== 6) return setError('Enter the 6-digit code');
    if (reset && password.length < 8) return setError('New password must be at least 8 characters');
    setLoading(true);
    try {
      const r = reset
        ? await api<{ token: string }>('/auth/reset-password', { body: { user_id, code, password } })
        : await api<{ token: string }>('/auth/verify-otp', { body: { user_id, code } });
      await signIn(r.token);
      router.replace('/(tabs)');
      toast(reset ? 'Password changed' : 'Welcome to Enro! 🎉 Bonus coins added');
    } catch (e) {
      setError(errMsg(e));
    } finally {
      setLoading(false);
    }
  };

  const resend = async () => {
    try {
      const r = await api<{ debug_otp?: string }>('/auth/resend-otp', { body: { user_id } });
      setWait(60);
      if (r.debug_otp) setDevCode(r.debug_otp);
      toast('New code sent');
    } catch (e) {
      setError(errMsg(e));
    }
  };

  return (
    <Screen header={<Header title="" />}>
      <IconBox name="mail-open" size={64} />
      <H1 style={{ marginTop: 20 }}>{reset ? 'Reset your password' : 'Check your email'}</H1>
      <Sub style={{ marginTop: 6 }}>Enter the 6-digit code we emailed to <T style={{ fontWeight: '700' }}>{to}</T>. Can’t find it? Check your spam folder.</Sub>
      {devCode ? <Small style={{ marginTop: 8, color: C.amber }}>Test mode: your code is {devCode}</Small> : null}

      <Pressable onPress={() => input.current?.focus()} style={st.boxes}>
        {Array.from({ length: 6 }).map((_, i) => (
          <View key={i} style={[st.box, code.length === i && st.boxOn]}><T style={st.digit}>{code[i] ?? ''}</T></View>
        ))}
      </Pressable>
      <TextInput ref={input} value={code} onChangeText={(v) => setCode(v.replace(/\D/g, '').slice(0, 6))} keyboardType="number-pad"
        autoFocus maxLength={6} style={st.hidden} textContentType="oneTimeCode" autoComplete="sms-otp" />

      {reset && (
        <View style={{ marginTop: 20 }}>
          <T style={{ fontSize: 13, fontWeight: '600', marginBottom: 6 }}>New password</T>
          <TextInput value={password} onChangeText={setPassword} secureTextEntry placeholder="At least 8 characters" placeholderTextColor="#A9A6B9" style={st.pw} />
        </View>
      )}

      <View style={{ marginTop: 22 }}>
        {error && <ErrorNote text={error} />}
        <Button title={reset ? 'Reset password' : 'Verify & continue'} onPress={verify} loading={loading} />
      </View>
      <View style={{ flexDirection: 'row', justifyContent: 'center', marginTop: 20 }}>
        <Sub>Didn’t get it? </Sub>
        {wait > 0 ? <T style={{ color: C.mut, fontWeight: '700' }}>Resend in 0:{String(wait).padStart(2, '0')}</T>
          : <Pressable onPress={resend}><T style={{ color: C.p, fontWeight: '700' }}>Resend code</T></Pressable>}
      </View>
    </Screen>
  );
}

const st = StyleSheet.create({
  boxes: { flexDirection: 'row', gap: 10, justifyContent: 'center', marginTop: 28 },
  box: { width: 46, height: 56, borderRadius: 14, borderWidth: 1.5, borderColor: C.line, backgroundColor: C.white, alignItems: 'center', justifyContent: 'center' },
  boxOn: { borderColor: C.p },
  digit: { fontSize: 22, fontWeight: '800' },
  hidden: { position: 'absolute', opacity: 0, height: 1, width: 1 },
  pw: { height: 52, backgroundColor: C.white, borderWidth: 1.5, borderColor: C.line, borderRadius: 14, paddingHorizontal: 14, fontSize: 15, color: C.ink },
});
