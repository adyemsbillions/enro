import { useEffect, useRef, useState } from 'react';
import { View } from 'react-native';
import { api } from '@/lib/api';
import { dateLabel, fmt, naira } from '@/lib/format';
import { useLoad } from '@/lib/useLoad';
import type { Txn } from '@/lib/types';
import { C } from '@/lib/theme';
import { Card, Chips, Empty, ErrorBox, Header, IconBox, Loading, Pill, Screen, Small, T, type IconName } from '@/components/ui';

type Filter = 'all' | 'coin' | 'cash';

const ICONS: Record<string, IconName> = {
  task: 'checkmark-done', special: 'star', redeem: 'phone-portrait', withdraw: 'business', refund: 'arrow-undo',
  referral: 'gift', bonus: 'gift', streak: 'flame', adjust: 'construct',
};
const STATUS: Record<Txn['status'], ['Success' | 'Pending' | 'Failed' | 'Rejected', 'cash' | 'amber' | 'red']> = {
  success: ['Success', 'cash'], pending: ['Pending', 'amber'], failed: ['Failed', 'red'], rejected: ['Rejected', 'red'],
};

export default function History() {
  const [filter, setFilter] = useState<Filter>('all');
  const { data, error, refreshing, reload, loading } = useLoad(() =>
    api<{ items: Txn[] }>(`/transactions${filter === 'all' ? '' : `?currency=${filter}`}`));

  const first = useRef(true);
  useEffect(() => {
    if (first.current) { first.current = false; return; }
    reload();
  }, [filter]); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <Screen header={<Header title="History" />} refreshing={refreshing} onRefresh={reload}>
      <Chips<Filter> value={filter} onChange={setFilter} items={[['all', 'All'], ['coin', 'Coins'], ['cash', 'Cash']]} />
      {loading ? <Loading /> : error && !data ? <ErrorBox message={error} onRetry={reload} /> :
        !data?.items.length ? <Empty icon="receipt-outline" title="No activity yet" text="Complete a task and it will show up here." /> : (
          <Card style={{ marginTop: 16, paddingVertical: 4 }}>
            {data.items.map((h, i) => {
              const coin = h.currency === 'coin';
              const neg = h.amount < 0;
              const [label, tone] = STATUS[h.status];
              return (
                <View key={h.id} style={{ flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 12, borderBottomWidth: i === data.items.length - 1 ? 0 : 1, borderBottomColor: C.line }}>
                  <IconBox name={ICONS[h.kind] ?? (coin ? 'logo-bitcoin' : 'cash')} tone={coin ? 'gold' : 'cash'} size={40} />
                  <View style={{ flex: 1 }}>
                    <T style={{ fontWeight: '700' }} numberOfLines={1}>{h.description}</T>
                    <Small>{dateLabel(h.created_at)}</Small>
                  </View>
                  <View style={{ alignItems: 'flex-end', gap: 4 }}>
                    <T style={{ fontWeight: '700', color: neg ? C.ink : coin ? C.amber : C.cash }}>
                      {neg ? '−' : '+'}{coin ? fmt(Math.abs(h.amount)) : naira(Math.abs(h.amount))}
                    </T>
                    <Pill text={label} tone={tone} />
                  </View>
                </View>
              );
            })}
          </Card>
        )}
    </Screen>
  );
}
