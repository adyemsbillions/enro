import { useEffect, useRef, useState } from 'react';
import { AppState, View } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { api, errMsg } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import type { Task } from '@/lib/types';
import { C } from '@/lib/theme';
import { AdBanner, Interstitial } from '@/components/ads';
import { TASK_ICON, TASK_LABEL } from '@/components/TaskCard';
import { useToast } from '@/components/toast';
import { Button, Card, ErrorBox, ErrorNote, H2, Header, IconBox, Loading, Pill, ProgressBar, Row, Screen, Sub, T } from '@/components/ui';

type Phase = 'idle' | 'running' | 'ready' | 'claiming' | 'done';

export default function TaskDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { config, setBalances, refresh } = useAuth();
  const toast = useToast();
  const [task, setTask] = useState<Task | null>(null);
  const [loadErr, setLoadErr] = useState<string | null>(null);
  const [phase, setPhase] = useState<Phase>('idle');
  const [error, setError] = useState<string | null>(null);
  const [left, setLeft] = useState(0);
  const [showAd, setShowAd] = useState(false);
  const session = useRef<{ id: number; endsAt: number } | null>(null);
  const pending = useRef<{ reward: number; bonus: number } | null>(null);

  const load = () => {
    api<{ items: Task[] }>('/tasks')
      .then((r) => {
        const t = r.items.find((x) => String(x.id) === id) ?? null;
        setTask(t);
        if (t && !t.available) setPhase('done');
        if (!t) setLoadErr('This task is no longer available.');
      })
      .catch((e) => setLoadErr(errMsg(e)));
  };
  useEffect(load, [id]);

  // countdown based on wall-clock time so it keeps going while the user is in the browser
  useEffect(() => {
    if (phase !== 'running') return;
    const tick = () => {
      const s = session.current;
      if (!s) return;
      const l = Math.max(0, Math.ceil((s.endsAt - Date.now()) / 1000));
      setLeft(l);
      if (l === 0) setPhase('ready');
    };
    tick();
    const t = setInterval(tick, 500);
    const sub = AppState.addEventListener('change', tick);
    return () => { clearInterval(t); sub.remove(); };
  }, [phase]);

  const start = async () => {
    if (!task) return;
    setError(null);
    try {
      const r = await api<{ session_id: number; duration: number; url: string | null }>(`/tasks/${task.id}/start`, { method: 'POST' });
      session.current = { id: r.session_id, endsAt: Date.now() + r.duration * 1000 };
      setPhase('running');
      if (r.url) WebBrowser.openBrowserAsync(r.url).catch(() => {});
    } catch (e) {
      setError(errMsg(e));
    }
  };

  const finish = () => {
    const p = pending.current;
    setShowAd(false);
    setPhase('done');
    if (p) toast(`+${p.reward} coins added${p.bonus ? ` · +${p.bonus} streak bonus!` : ''}`);
    refresh().catch(() => {});
  };

  const claim = async () => {
    if (!task || !session.current) return;
    setPhase('claiming');
    setError(null);
    try {
      const r = await api<{ reward: number; bonus: number; coins: number; show_interstitial: boolean }>(
        `/tasks/${task.id}/claim`, { body: { session_id: session.current.id } });
      setBalances({ coins: r.coins });
      pending.current = { reward: r.reward, bonus: r.bonus };
      if (r.show_interstitial) setShowAd(true);
      else finish();
    } catch (e) {
      setError(errMsg(e));
      setPhase('ready');
    }
  };

  if (!task) {
    return <Screen header={<Header title="Task" />}>{loadErr ? <ErrorBox message={loadErr} onRetry={() => { setLoadErr(null); load(); }} /> : <Loading />}</Screen>;
  }

  const pct = task.duration ? ((task.duration - left) / task.duration) * 100 : 0;

  return (
    <Screen header={<Header title="Task" />}>
      <Card style={{ alignItems: 'center', paddingVertical: 24 }}>
        <IconBox name={TASK_ICON[task.type]} size={72} />
        <H2 style={{ fontSize: 19, textAlign: 'center', marginTop: 16 }}>{task.title}</H2>
        <Row gap={8} style={{ marginTop: 12, flexWrap: 'wrap', justifyContent: 'center' }}>
          <Pill text={`+${task.reward} coins`} tone="gold" icon="logo-bitcoin" />
          <Pill text={`${task.duration}s`} tone="grey" icon="time-outline" />
          <Pill text={TASK_LABEL[task.type]} />
        </Row>
      </Card>

      <Card style={{ marginTop: 12 }}>
        <T style={{ fontWeight: '700', fontSize: 15 }}>How it works</T>
        {task.description ? <Sub style={{ marginTop: 6 }}>{task.description}</Sub> : null}
        {['Tap Start task', task.type === 'daily' ? 'Wait for the short timer' : 'Complete the action and come back to Enro', 'Tap Claim when the timer ends'].map((s, i) => (
          <Row key={i} gap={10} style={{ marginTop: 10 }}>
            <View style={{ width: 24, height: 24, borderRadius: 12, backgroundColor: C.pl, alignItems: 'center', justifyContent: 'center' }}>
              <T style={{ color: C.p, fontWeight: '800', fontSize: 12 }}>{i + 1}</T>
            </View>
            <T style={{ flex: 1 }}>{s}</T>
          </Row>
        ))}
      </Card>

      <Card style={{ marginTop: 12, backgroundColor: '#FBFAFF' }}>
        <Row>
          <IconBox name="wallet" tone="gold" size={40} />
          <Sub style={{ flex: 1, fontSize: 13 }}>
            <T style={{ fontWeight: '700', fontSize: 13 }}>{(1000).toLocaleString()} coins = ₦{Math.round(1000 / config.coins_per_naira)} airtime.</T> Redeem from your wallet anytime.
          </Sub>
        </Row>
      </Card>

      <View style={{ marginTop: 20 }}>
        {error && <ErrorNote text={error} />}
        {phase === 'idle' && <Button title="Start task" icon="play" onPress={start} />}
        {phase === 'running' && (
          <>
            <Button title={`Keep going… ${left}s`} icon="time-outline" variant="soft" />
            <View style={{ marginTop: 10 }}><ProgressBar pct={pct} color={C.p} /></View>
          </>
        )}
        {(phase === 'ready' || phase === 'claiming') && <Button title={`Claim ${task.reward} coins`} icon="logo-bitcoin" variant="gold" onPress={claim} loading={phase === 'claiming'} />}
        {phase === 'done' && (
          <>
            <Button title="Completed — come back tomorrow" icon="checkmark" disabled />
            <Button title="More tasks" variant="ghost" style={{ marginTop: 10 }} onPress={() => router.back()} />
          </>
        )}
      </View>

      <AdBanner style={{ marginTop: 16 }} />
      <Interstitial visible={showAd} onClose={finish} />
    </Screen>
  );
}
