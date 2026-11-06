import { useEffect } from 'react';
import { Pressable, View } from 'react-native';
import { api } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { ago } from '@/lib/format';
import { useLoad } from '@/lib/useLoad';
import type { Notice } from '@/lib/types';
import { C } from '@/lib/theme';
import { Card, Empty, ErrorBox, Header, IconBox, Loading, Screen, Small, T, type IconName } from '@/components/ui';

const ICONS: Record<string, [IconName, 'cash' | 'gold' | 'p' | 'red']> = {
  check: ['checkmark-circle', 'cash'], phone: ['phone-portrait', 'gold'], bank: ['business', 'cash'], gift: ['gift', 'gold'],
  ban: ['close-circle', 'red'], wallet: ['wallet', 'p'], bell: ['notifications', 'p'], star: ['star', 'p'],
};

export default function Notifications() {
  const { refresh } = useAuth();
  const { data, error, refreshing, reload, loading } = useLoad(() => api<{ items: Notice[] }>('/notifications'));

  // mark everything read once the list has been seen
  useEffect(() => {
    if (data?.items.some((n) => !n.read_at)) {
      api('/notifications/read-all', { method: 'POST' }).then(() => refresh()).catch(() => {});
    }
  }, [data, refresh]);

  return (
    <Screen header={<Header title="Notifications" />} refreshing={refreshing} onRefresh={reload}>
      {loading ? <Loading /> : error && !data ? <ErrorBox message={error} onRetry={reload} /> :
        !data?.items.length ? <Empty icon="notifications-outline" title="No notifications yet" text="Approvals, payouts and new special tasks will show up here." /> : (
          <Card style={{ paddingVertical: 4 }}>
            {data.items.map((n, i) => {
              const [icon, tone] = ICONS[n.icon] ?? ICONS.bell;
              return (
                <Pressable key={n.id} style={{ flexDirection: 'row', gap: 12, paddingVertical: 13, borderBottomWidth: i === data.items.length - 1 ? 0 : 1, borderBottomColor: C.line }}>
                  <IconBox name={icon} tone={tone} size={40} />
                  <View style={{ flex: 1 }}>
                    <T style={{ fontWeight: '700' }}>{n.title}</T>
                    <Small style={{ lineHeight: 18, marginTop: 2 }}>{n.body}</Small>
                  </View>
                  <View style={{ alignItems: 'flex-end', gap: 6 }}>
                    <Small>{ago(n.created_at)}</Small>
                    {!n.read_at && <View style={{ width: 8, height: 8, borderRadius: 4, backgroundColor: C.p }} />}
                  </View>
                </Pressable>
              );
            })}
          </Card>
        )}
    </Screen>
  );
}
