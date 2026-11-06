import { Fragment, useState } from 'react';
import { View } from 'react-native';
import { api } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { fmt } from '@/lib/format';
import { useLoad } from '@/lib/useLoad';
import type { Task } from '@/lib/types';
import { C } from '@/lib/theme';
import { AdNative } from '@/components/ads';
import { TaskCard } from '@/components/TaskCard';
import { Card, Chips, Empty, ErrorBox, Header, Loading, Pill, ProgressBar, Screen, Small, T } from '@/components/ui';

type Filter = 'all' | Task['type'];

export default function Tasks() {
  const { me } = useAuth();
  const [filter, setFilter] = useState<Filter>('all');
  const { data, error, refreshing, reload, loading } = useLoad(() =>
    api<{ items: Task[]; done_today: number; daily_limit: number }>('/tasks'));

  const items = (data?.items ?? []).filter((t) => filter === 'all' || t.type === filter);
  const done = data?.done_today ?? 0;
  const limit = data?.daily_limit ?? 10;

  return (
    <Screen
      header={<Header title="Tasks" back={false} right={<Pill text={fmt(me?.user.coins ?? 0) + ' coins'} tone="gold" icon="logo-bitcoin" />} />}
      refreshing={refreshing} onRefresh={reload}
    >
      <Card style={{ backgroundColor: C.goldl, borderColor: '#F8E3A8' }}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
          <View style={{ flex: 1 }}>
            <T style={{ fontWeight: '700' }}>Today’s progress</T>
            <Small style={{ color: C.amber }}>{done} of {limit} tasks · coins swap for airtime & data</Small>
          </View>
          <T style={{ fontSize: 18, fontWeight: '800', color: C.amber }}>{Math.min(100, Math.round((done / limit) * 100))}%</T>
        </View>
        <View style={{ marginTop: 12 }}><ProgressBar pct={(done / limit) * 100} color={C.gold} track={C.white} /></View>
      </Card>

      <View style={{ marginTop: 16, marginBottom: 16 }}>
        <Chips<Filter> value={filter} onChange={setFilter}
          items={[['all', 'All'], ['watch', 'Watch'], ['visit', 'Visit'], ['social', 'Social'], ['daily', 'Daily']]} />
      </View>

      {loading ? <Loading /> : error && !data ? <ErrorBox message={error} onRetry={reload} /> :
        items.length === 0 ? <Empty icon="checkmark-done-circle-outline" title="No tasks here right now" text="New tasks are added regularly. Pull down to refresh." /> :
        items.map((t, i) => (
          <Fragment key={t.id}>
            <TaskCard task={t} />
            {i === 2 && <AdNative />}
          </Fragment>
        ))}
    </Screen>
  );
}
