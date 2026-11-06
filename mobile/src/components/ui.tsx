import { forwardRef, type ComponentProps, type ReactNode } from 'react';
import {
  ActivityIndicator, Pressable, RefreshControl, ScrollView, StyleSheet, Text, TextInput, View, type TextInputInstance,
  type StyleProp, type TextInputProps, type TextStyle, type ViewStyle,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Ionicons from '@expo/vector-icons/Ionicons';
import { router } from 'expo-router';
import { C, R } from '@/lib/theme';

export type IconName = ComponentProps<typeof Ionicons>['name'];
export const Icon = Ionicons;

type Tone = 'p' | 'gold' | 'cash' | 'red' | 'grey' | 'amber';
const TONES: Record<Tone, { bg: string; fg: string }> = {
  p: { bg: C.pl, fg: C.p },
  gold: { bg: C.goldl, fg: C.gold },
  cash: { bg: C.cashl, fg: C.cash },
  red: { bg: C.redl, fg: C.red },
  grey: { bg: '#EEEDF3', fg: C.mut },
  amber: { bg: C.amberl, fg: C.amber },
};

export function T({ style, children, ...p }: ComponentProps<typeof Text>) {
  return <Text style={[s.text, style]} {...p}>{children}</Text>;
}

export function H1({ children, style }: { children: ReactNode; style?: StyleProp<TextStyle> }) {
  return <T style={[s.h1, style]}>{children}</T>;
}

export function H2({ children, style }: { children: ReactNode; style?: StyleProp<TextStyle> }) {
  return <T style={[s.h2, style]}>{children}</T>;
}

export function Sub({ children, style }: { children: ReactNode; style?: StyleProp<TextStyle> }) {
  return <T style={[s.sub, style]}>{children}</T>;
}

export function Small({ children, style }: { children: ReactNode; style?: StyleProp<TextStyle> }) {
  return <T style={[s.small, style]}>{children}</T>;
}

type ScreenProps = {
  children: ReactNode;
  scroll?: boolean;
  pad?: boolean;
  refreshing?: boolean;
  onRefresh?: () => void;
  header?: ReactNode;
  footer?: ReactNode;
  bg?: string;
};

export function Screen({ children, scroll = true, pad = true, refreshing, onRefresh, header, footer, bg = C.bg }: ScreenProps) {
  return (
    <SafeAreaView style={{ flex: 1, backgroundColor: bg }} edges={['top']}>
      {header}
      {scroll ? (
        <ScrollView
          contentContainerStyle={[pad && { paddingHorizontal: 20 }, { paddingBottom: 32 }]}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
          refreshControl={onRefresh ? <RefreshControl refreshing={!!refreshing} onRefresh={onRefresh} tintColor={C.p} /> : undefined}
        >
          {children}
        </ScrollView>
      ) : (
        <View style={[{ flex: 1 }, pad && { paddingHorizontal: 20 }]}>{children}</View>
      )}
      {footer}
    </SafeAreaView>
  );
}

export function Header({ title, back = true, right }: { title: string; back?: boolean; right?: ReactNode }) {
  return (
    <View style={s.header}>
      {back && (
        <Pressable onPress={() => (router.canGoBack() ? router.back() : router.replace('/'))} style={s.iconBtn} hitSlop={8}>
          <Icon name="chevron-back" size={20} color={C.ink} />
        </Pressable>
      )}
      <H2 style={{ flex: 1, fontSize: back ? 17 : 22 }}>{title}</H2>
      {right}
    </View>
  );
}

export function IconBtn({ name, onPress, dot }: { name: IconName; onPress: () => void; dot?: boolean }) {
  return (
    <Pressable onPress={onPress} style={s.iconBtn} hitSlop={8}>
      <Icon name={name} size={19} color={C.ink} />
      {dot && <View style={s.dot} />}
    </Pressable>
  );
}

type ButtonProps = {
  title: string;
  onPress?: () => void;
  variant?: 'primary' | 'ghost' | 'cash' | 'gold' | 'danger' | 'soft';
  loading?: boolean;
  disabled?: boolean;
  icon?: IconName;
  style?: StyleProp<ViewStyle>;
  small?: boolean;
};

export function Button({ title, onPress, variant = 'primary', loading, disabled, icon, style, small }: ButtonProps) {
  const bg = { primary: C.p, ghost: C.white, cash: C.cash, gold: C.gold, danger: C.white, soft: C.pl }[variant];
  const fg = { primary: C.white, ghost: C.ink, cash: C.white, gold: C.white, danger: C.red, soft: C.p }[variant];
  const off = disabled || loading;
  return (
    <Pressable
      onPress={off ? undefined : onPress}
      style={({ pressed }) => [
        s.btn, small && s.btnSm, { backgroundColor: off && variant !== 'ghost' ? '#C9C5DA' : bg },
        (variant === 'ghost' || variant === 'danger') && { borderWidth: 1, borderColor: C.line },
        pressed && !off && { transform: [{ scale: 0.98 }], opacity: 0.92 }, style,
      ]}
    >
      {loading ? <ActivityIndicator color={fg} /> : (
        <>
          {icon && <Icon name={icon} size={small ? 15 : 18} color={off && variant !== 'ghost' ? C.white : fg} />}
          <T style={[s.btnText, small && { fontSize: 13 }, { color: off && variant !== 'ghost' ? C.white : fg }]}>{title}</T>
        </>
      )}
    </Pressable>
  );
}

type FieldProps = TextInputProps & { label?: string; icon?: IconName; right?: ReactNode; prefix?: string; hint?: ReactNode };

export const Field = forwardRef<TextInputInstance, FieldProps>(function Field({ label, icon, right, prefix, hint, style, ...p }, ref) {
  return (
    <View style={{ marginBottom: 14 }}>
      {label && <T style={s.label}>{label}</T>}
      <View style={s.inp}>
        {icon && <Icon name={icon} size={18} color={C.mut} />}
        {prefix && <T style={{ fontWeight: '700', fontSize: 15 }}>{prefix}</T>}
        <TextInput ref={ref} placeholderTextColor="#A9A6B9" style={[s.input, style]} {...p} />
        {right}
      </View>
      {hint}
    </View>
  );
});

export function Card({ children, style, onPress }: { children: ReactNode; style?: StyleProp<ViewStyle>; onPress?: () => void }) {
  if (onPress) {
    return <Pressable onPress={onPress} style={({ pressed }) => [s.card, style, pressed && { opacity: 0.85 }]}>{children}</Pressable>;
  }
  return <View style={[s.card, style]}>{children}</View>;
}

export function Pill({ text, tone = 'p', icon }: { text: string; tone?: Tone; icon?: IconName }) {
  const t = TONES[tone];
  return (
    <View style={[s.pill, { backgroundColor: t.bg }]}>
      {icon && <Icon name={icon} size={12} color={t.fg} />}
      <T style={[s.pillText, { color: t.fg }]}>{text}</T>
    </View>
  );
}

export function IconBox({ name, tone = 'p', size = 44 }: { name: IconName; tone?: Tone; size?: number }) {
  const t = TONES[tone];
  return (
    <View style={[s.iconBox, { width: size, height: size, borderRadius: size * 0.3, backgroundColor: t.bg }]}>
      <Icon name={name} size={size * 0.48} color={t.fg} />
    </View>
  );
}

export function Row({ children, style, gap = 12 }: { children: ReactNode; style?: StyleProp<ViewStyle>; gap?: number }) {
  return <View style={[{ flexDirection: 'row', alignItems: 'center', gap }, style]}>{children}</View>;
}

export function Chips<K extends string>({ items, value, onChange }: { items: [K, string][]; value: K; onChange: (k: K) => void }) {
  return (
    <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
      {items.map(([k, l]) => (
        <Pressable key={k} onPress={() => onChange(k)} style={[s.chip, value === k && s.chipOn]}>
          <T style={[s.chipText, value === k && { color: C.white }]}>{l}</T>
        </Pressable>
      ))}
    </ScrollView>
  );
}

export function Tabs<K extends string>({ items, value, onChange }: { items: [K, string][]; value: K; onChange: (k: K) => void }) {
  return (
    <View style={s.tabs}>
      {items.map(([k, l]) => (
        <Pressable key={k} onPress={() => onChange(k)} style={[s.tab, value === k && s.tabOn]}>
          <T style={[s.tabText, value === k && { color: C.ink }]}>{l}</T>
        </Pressable>
      ))}
    </View>
  );
}

export function ProgressBar({ pct, color = C.cash, track = '#ECEAF3', height = 6 }: { pct: number; color?: string; track?: string; height?: number }) {
  return (
    <View style={{ height, borderRadius: 9, backgroundColor: track, overflow: 'hidden' }}>
      <View style={{ width: `${Math.max(0, Math.min(100, pct))}%`, height: '100%', backgroundColor: color, borderRadius: 9 }} />
    </View>
  );
}

export function Empty({ icon, title, text, action }: { icon: IconName; title: string; text?: string; action?: ReactNode }) {
  return (
    <View style={{ alignItems: 'center', paddingVertical: 40, paddingHorizontal: 20, gap: 8 }}>
      <IconBox name={icon} size={56} tone="grey" />
      <T style={{ fontWeight: '700', fontSize: 15, marginTop: 6 }}>{title}</T>
      {text && <Sub style={{ textAlign: 'center' }}>{text}</Sub>}
      {action}
    </View>
  );
}

export function Loading() {
  return <View style={{ paddingVertical: 60 }}><ActivityIndicator color={C.p} size="large" /></View>;
}

export function ErrorBox({ message, onRetry }: { message: string; onRetry: () => void }) {
  return <Empty icon="cloud-offline-outline" title="Couldn't load" text={message} action={<Button title="Try again" small variant="soft" onPress={onRetry} style={{ marginTop: 8 }} />} />;
}

export function Avatar({ name, size = 42 }: { name: string; size?: number }) {
  const parts = name.trim().split(/\s+/);
  const ini = ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase();
  return (
    <View style={{ width: size, height: size, borderRadius: size / 2, backgroundColor: '#FF8A7A', alignItems: 'center', justifyContent: 'center' }}>
      <T style={{ color: C.white, fontWeight: '800', fontSize: size * 0.36 }}>{ini}</T>
    </View>
  );
}

export function ErrorNote({ text }: { text: string }) {
  return (
    <View style={{ backgroundColor: C.redl, borderRadius: 12, padding: 12, marginBottom: 14, flexDirection: 'row', gap: 8, alignItems: 'center' }}>
      <Icon name="alert-circle" size={18} color={C.red} />
      <T style={{ color: '#9B2C2F', flex: 1, fontWeight: '600', fontSize: 13 }}>{text}</T>
    </View>
  );
}

export const s = StyleSheet.create({
  text: { color: C.ink, fontSize: 14 },
  h1: { fontSize: 26, fontWeight: '800', letterSpacing: -0.5, lineHeight: 32 },
  h2: { fontSize: 20, fontWeight: '800', letterSpacing: -0.3 },
  sub: { color: C.mut, fontSize: 14, lineHeight: 21 },
  small: { color: C.mut, fontSize: 12 },
  header: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 20, paddingTop: 6, paddingBottom: 12 },
  iconBtn: { width: 40, height: 40, borderRadius: 12, backgroundColor: C.white, borderWidth: 1, borderColor: C.line, alignItems: 'center', justifyContent: 'center' },
  dot: { position: 'absolute', top: 8, right: 9, width: 8, height: 8, borderRadius: 4, backgroundColor: C.red, borderWidth: 1.5, borderColor: C.white },
  btn: { height: 52, borderRadius: R.md, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, paddingHorizontal: 18 },
  btnSm: { height: 38, borderRadius: R.sm, paddingHorizontal: 14, alignSelf: 'flex-start' },
  btnText: { fontWeight: '700', fontSize: 15 },
  label: { fontSize: 13, fontWeight: '600', marginBottom: 6 },
  inp: { flexDirection: 'row', alignItems: 'center', gap: 10, height: 52, backgroundColor: C.white, borderWidth: 1.5, borderColor: C.line, borderRadius: R.md, paddingHorizontal: 14 },
  input: { flex: 1, fontSize: 15, color: C.ink, height: '100%' },
  card: { backgroundColor: C.card, borderRadius: R.lg, padding: 16, borderWidth: 1, borderColor: C.line },
  pill: { flexDirection: 'row', alignItems: 'center', gap: 4, paddingHorizontal: 9, paddingVertical: 3, borderRadius: 99, alignSelf: 'flex-start' },
  pillText: { fontSize: 11.5, fontWeight: '700' },
  iconBox: { alignItems: 'center', justifyContent: 'center' },
  chip: { paddingHorizontal: 14, paddingVertical: 8, borderRadius: 99, backgroundColor: C.white, borderWidth: 1, borderColor: C.line },
  chipOn: { backgroundColor: C.ink, borderColor: C.ink },
  chipText: { fontSize: 13, fontWeight: '600' },
  tabs: { flexDirection: 'row', backgroundColor: '#ECEAF3', borderRadius: 13, padding: 4 },
  tab: { flex: 1, height: 38, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
  tabOn: { backgroundColor: C.white },
  tabText: { fontSize: 13, fontWeight: '700', color: C.mut },
});
