import { useEffect, useState } from 'react';
import { Alert, Linking, Platform, Pressable, StyleSheet, View } from 'react-native';
import { router, type Href } from 'expo-router';
import Constants from 'expo-constants';
import { useAuth } from '@/lib/auth';
import { naira } from '@/lib/format';
import { bioEnabled, disableBio } from '@/lib/quickLogin';
import { C } from '@/lib/theme';
import { Avatar, Button, Card, Header, Icon, Row, Screen, Small, T, type IconName } from '@/components/ui';

export default function Profile() {
  const { me, config, signOut } = useAuth();
  const u = me?.user;
  const [bio, setBio] = useState(false);
  useEffect(() => { bioEnabled().then(setBio).catch(() => {}); }, []);

  const fingerprintOff = () => Alert.alert('Turn off fingerprint login?', 'You will log in with your password. You can turn it on again the next time you log in.', [
    { text: 'Cancel', style: 'cancel' },
    { text: 'Turn off', style: 'destructive', onPress: () => void disableBio().then(() => setBio(false)) },
  ]);

  const logout = () => {
    if (Platform.OS === 'web') return void signOut();
    Alert.alert('Log out?', 'You can log back in anytime.', [
      { text: 'Cancel', style: 'cancel' },
      { text: 'Log out', style: 'destructive', onPress: () => signOut() },
    ]);
  };

  const items: [IconName, string, string, () => void][] = [
    ['time-outline', 'Transaction history', '', () => router.push('/history')],
    ['gift-outline', 'Refer & earn', u?.referral_code ?? '', () => router.push('/referral')],
    ['notifications-outline', 'Notifications', me?.unread_notifications ? `${me.unread_notifications} new` : '', () => router.push('/notifications')],
    ['lock-closed-outline', 'Change password', '', () => router.push('/password' as Href)],
    ...(bio ? [['finger-print', 'Fingerprint login', 'On', fingerprintOff] as [IconName, string, string, () => void]] : []),
    ['help-circle-outline', 'Help & support', '', () => config.support_email && Linking.openURL(`mailto:${config.support_email}`)],
  ];

  return (
    <Screen header={<Header title="Profile" back={false} />}>
      <Card>
        <Row>
          <Avatar name={u?.name ?? '?'} size={58} />
          <View style={{ flex: 1 }}>
            <T style={{ fontSize: 16, fontWeight: '700' }}>{u?.name}</T>
            <Small>{u?.email}</Small>
            <Small>{u?.phone}</Small>
          </View>
        </Row>
      </Card>

      <View style={{ flexDirection: 'row', gap: 10, marginTop: 12 }}>
        {[[String(me?.stats.tasks_done ?? 0), 'Tasks done'], [String(me?.stats.special_done ?? 0), 'Special done'], [naira(me?.stats.total_cashout ?? 0), 'Paid out']].map(([v, l]) => (
          <Card key={l} style={{ flex: 1, alignItems: 'center', paddingVertical: 12, paddingHorizontal: 6 }}>
            <T style={{ fontSize: 17, fontWeight: '800' }}>{v}</T>
            <Small>{l}</Small>
          </Card>
        ))}
      </View>

      <Card style={{ marginTop: 16, paddingVertical: 2 }}>
        {items.map(([icon, label, value, onPress], i) => (
          <Pressable key={label} onPress={onPress} style={[st.item, i === items.length - 1 && { borderBottomWidth: 0 }]}>
            <View style={st.ic}><Icon name={icon} size={18} color={C.p} /></View>
            <T style={{ flex: 1, fontWeight: '600' }}>{label}</T>
            {value ? <Small>{value}</Small> : null}
            <Icon name="chevron-forward" size={16} color="#B5B2C6" />
          </Pressable>
        ))}
      </Card>

      <Button title="Log out" icon="log-out-outline" variant="danger" style={{ marginTop: 16 }} onPress={logout} />
      <Small style={{ textAlign: 'center', marginTop: 16 }}>Enro v{Constants.expoConfig?.version ?? '1.0.0'}</Small>
    </Screen>
  );
}

const st = StyleSheet.create({
  item: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 13, borderBottomWidth: 1, borderBottomColor: C.line },
  ic: { width: 38, height: 38, borderRadius: 11, backgroundColor: C.pl, alignItems: 'center', justifyContent: 'center' },
});
