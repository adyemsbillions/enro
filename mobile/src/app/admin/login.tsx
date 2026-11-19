import { useState } from 'react';
import { Pressable, View } from 'react-native';
import { router, type Href } from 'expo-router';
import { errMsg } from '@/lib/api';
import { adminLogin, registerAdminPush } from '@/lib/admin';
import { Button, ErrorNote, Field, H1, Header, Icon, Screen, Sub } from '@/components/ui';
import { C } from '@/lib/theme';

export default function AdminLogin() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [show, setShow] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const submit = async () => {
    setError(null);
    if (!email.trim() || !password) return setError('Enter your admin email and password');
    setLoading(true);
    try {
      await adminLogin(email.trim(), password);
      registerAdminPush().catch(() => {});
      router.replace('/admin' as Href);
    } catch (e) {
      setError(errMsg(e));
    } finally {
      setLoading(false);
    }
  };

  return (
    <Screen header={<Header title="" />}>
      <View style={{ paddingTop: 8 }}>
        <View style={{ width: 52, height: 52, borderRadius: 16, backgroundColor: C.ink, alignItems: 'center', justifyContent: 'center' }}>
          <Icon name="shield-checkmark" size={26} color={C.white} />
        </View>
        <H1 style={{ marginTop: 20 }}>Admin login</H1>
        <Sub style={{ marginTop: 6 }}>Use your Enro admin email and password.</Sub>
      </View>
      <View style={{ marginTop: 26 }}>
        {error && <ErrorNote text={error} />}
        <Field label="Email" icon="mail-outline" value={email} onChangeText={setEmail} autoCapitalize="none" keyboardType="email-address" autoComplete="email" placeholder="admin@email.com" />
        <Field label="Password" icon="lock-closed-outline" value={password} onChangeText={setPassword} secureTextEntry={!show} placeholder="Password"
          onSubmitEditing={submit} returnKeyType="go"
          right={<Pressable onPress={() => setShow(!show)} hitSlop={8}><Icon name={show ? 'eye-off-outline' : 'eye-outline'} size={19} color={C.mut} /></Pressable>} />
        <Button title="Log in as admin" onPress={submit} loading={loading} style={{ marginTop: 6 }} />
      </View>
    </Screen>
  );
}
