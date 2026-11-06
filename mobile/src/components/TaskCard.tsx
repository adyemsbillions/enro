import { Pressable, StyleSheet, View } from 'react-native';
import { router } from 'expo-router';
import type { Task } from '@/lib/types';
import { C } from '@/lib/theme';
import { IconBox, Pill, Small, T, type IconName } from './ui';

export const TASK_ICON: Record<Task['type'], IconName> = {
  watch: 'play-circle',
  visit: 'link',
  social: 'people',
  daily: 'calendar',
};
export const TASK_LABEL: Record<Task['type'], string> = { watch: 'Watch', visit: 'Visit', social: 'Social', daily: 'Daily' };

export function TaskCard({ task }: { task: Task }) {
  const done = !task.available;
  return (
    <Pressable onPress={() => router.push(`/task/${task.id}`)} style={({ pressed }) => [st.card, done && { opacity: 0.55 }, pressed && { opacity: 0.8 }]}>
      <IconBox name={TASK_ICON[task.type]} />
      <View style={{ flex: 1 }}>
        <T style={st.title}>{task.title}</T>
        <Small>{TASK_LABEL[task.type]} · {task.duration}s{task.daily_cap > 1 ? ` · ${task.done_today}/${task.daily_cap} today` : ''}</Small>
      </View>
      {done ? <Pill text="Done" tone="cash" icon="checkmark" /> : <Pill text={`+${task.reward}`} tone="gold" />}
    </Pressable>
  );
}

const st = StyleSheet.create({
  card: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: C.white, borderWidth: 1, borderColor: C.line, borderRadius: 16, padding: 12, marginBottom: 10 },
  title: { fontSize: 14, fontWeight: '700', lineHeight: 19, marginBottom: 2 },
});
