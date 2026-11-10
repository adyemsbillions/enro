import { useState } from 'react';
import { Image, Platform, Pressable, StyleSheet, TextInput, View } from 'react-native';
import { useLocalSearchParams } from 'expo-router';
import * as ImagePicker from 'expo-image-picker';
import * as WebBrowser from 'expo-web-browser';
import { api, errMsg } from '@/lib/api';
import { naira } from '@/lib/format';
import { useLoad } from '@/lib/useLoad';
import type { SpecialTask } from '@/lib/types';
import { C } from '@/lib/theme';
import { useToast } from '@/components/toast';
import { UnlockCard } from '@/components/UnlockCard';
import { Button, Card, ErrorBox, ErrorNote, Header, Icon, IconBox, Loading, Pill, Row, Screen, Small, Sub, T } from '@/components/ui';

type Picked = { uri: string; name: string; type: string };

export default function SpecialDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const toast = useToast();
  const { data, error, reload, setData } = useLoad(() => api<{ task: SpecialTask; unlocked: boolean; unlock_fee: number }>(`/special-tasks/${id}`));
  const [proof, setProof] = useState<Picked | null>(null);
  const [note, setNote] = useState('');
  const [sending, setSending] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  const t = data?.task;
  const locked = data?.unlocked === false && data.unlock_fee > 0;
  if (!t) return <Screen header={<Header title="Special task" />}>{error ? <ErrorBox message={error} onRetry={reload} /> : <Loading />}</Screen>;

  if (locked && !t.my_status) {
    return (
      <Screen header={<Header title="Special task" />}>
        <UnlockCard fee={data.unlock_fee} onUnlocked={reload} />
      </Screen>
    );
  }

  const needsImage = t.proof_type !== 'text';
  const needsText = t.proof_type !== 'screenshot';
  const canSubmit = (!needsImage || proof) && (!needsText || note.trim().length > 0);
  const open = !locked && (!t.my_status || t.my_status === 'rejected');

  const pick = async () => {
    const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) return setErr('Allow photo access to upload your screenshot');
    const r = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], quality: 0.7 });
    if (r.canceled || !r.assets[0]) return;
    const a = r.assets[0];
    setProof({ uri: a.uri, name: a.fileName ?? 'proof.jpg', type: a.mimeType ?? 'image/jpeg' });
  };

  const submit = async () => {
    setErr(null);
    setSending(true);
    try {
      const form = new FormData();
      form.append('note', note.trim());
      if (proof) {
        if (Platform.OS === 'web') {
          form.append('proof', await (await fetch(proof.uri)).blob(), proof.name);
        } else {
          // React Native's FormData accepts {uri, name, type} file objects
          form.append('proof', { uri: proof.uri, name: proof.name, type: proof.type } as unknown as Blob);
        }
      }
      await api(`/special-tasks/${t.id}/submit`, { method: 'POST', form });
      setData({ ...data, task: { ...t, my_status: 'pending', reject_reason: null } });
      toast('Proof submitted for review');
    } catch (e) {
      setErr(errMsg(e));
    } finally {
      setSending(false);
    }
  };

  return (
    <Screen header={<Header title="Special task" />}>
      <Card>
        <Row>
          <IconBox name="star" tone="cash" size={56} />
          <View style={{ flex: 1 }}>
            <T style={{ fontSize: 16, fontWeight: '700', lineHeight: 21 }}>{t.title}</T>
            <Row gap={6} style={{ marginTop: 8 }}>
              <Pill text={`${naira(t.reward)} cash`} tone="cash" />
              {t.eta ? <Pill text={t.eta} tone="grey" /> : null}
            </Row>
          </View>
        </Row>
        {t.description ? <Sub style={{ marginTop: 12 }}>{t.description}</Sub> : null}
      </Card>

      {locked && <View style={{ marginTop: 12 }}><UnlockCard fee={data.unlock_fee} onUnlocked={reload} /></View>}
      {t.my_status === 'pending' && (
        <StatusCard tone="amber" icon="time" title="Submitted — pending review" text={`We'll add ${naira(t.reward)} to your cash balance once approved, usually within 24 hours.`} />
      )}
      {t.my_status === 'approved' && (
        <StatusCard tone="cash" icon="checkmark-circle" title="Approved and paid" text={`${naira(t.reward)} was added to your cash balance.`} />
      )}
      {t.my_status === 'rejected' && (
        <StatusCard tone="red" icon="close-circle" title="Proof rejected" text={`${t.reject_reason ?? 'Your proof did not meet the requirements'}. Fix it and submit again below.`} />
      )}

      {open && (
        <>
          <Card style={{ marginTop: 12 }}>
            <T style={{ fontWeight: '700', fontSize: 15 }}>Steps</T>
            {t.steps.map((s, i) => (
              <Row key={i} gap={10} style={{ marginTop: 10, alignItems: 'flex-start' }}>
                <View style={st.num}><T style={{ color: C.p, fontWeight: '800', fontSize: 12 }}>{i + 1}</T></View>
                <T style={{ flex: 1, lineHeight: 20 }}>{s}</T>
              </Row>
            ))}
            {t.url ? <Button title="Open task link" icon="open-outline" variant="ghost" style={{ marginTop: 14, height: 46 }} onPress={() => WebBrowser.openBrowserAsync(t.url!)} /> : null}
          </Card>

          <Card style={{ marginTop: 12 }}>
            <T style={{ fontWeight: '700', fontSize: 15 }}>Submit proof</T>
            {needsImage && (
              <Pressable onPress={pick} style={[st.upload, proof && st.uploadOk]}>
                {proof ? (
                  <>
                    <Image source={{ uri: proof.uri }} style={st.preview} />
                    <T style={{ color: C.cash, fontWeight: '700' }}>Screenshot added · tap to change</T>
                  </>
                ) : (
                  <>
                    <Icon name="cloud-upload-outline" size={28} color={C.p} />
                    <T style={{ color: C.p, fontWeight: '700' }}>Upload screenshot</T>
                    <Small>PNG or JPG, max 5MB</Small>
                  </>
                )}
              </Pressable>
            )}
            {(needsText || needsImage) && (
              <View style={{ marginTop: 12 }}>
                <T style={{ fontSize: 13, fontWeight: '600', marginBottom: 6 }}>{needsText ? 'Username, email or code used' : 'Note (optional)'}</T>
                <TextInput value={note} onChangeText={setNote} multiline placeholder="e.g. the email you signed up with or the completion code"
                  placeholderTextColor="#A9A6B9" style={st.ta} />
              </View>
            )}
          </Card>

          <Card style={{ marginTop: 12, backgroundColor: C.redl, borderColor: '#F7C9CB' }}>
            <Row gap={10}><Icon name="ban" size={18} color={C.red} /><Small style={{ color: '#9B2C2F', flex: 1 }}>Fake or reused proof is rejected and can get your account banned.</Small></Row>
          </Card>

          <View style={{ marginTop: 20 }}>
            {err && <ErrorNote text={err} />}
            <Button title={t.my_status === 'rejected' ? 'Resubmit for review' : 'Submit for review'} variant="cash" onPress={submit} loading={sending} disabled={!canSubmit} />
          </View>
        </>
      )}
    </Screen>
  );
}

function StatusCard({ tone, icon, title, text }: { tone: 'amber' | 'cash' | 'red'; icon: 'time' | 'checkmark-circle' | 'close-circle'; title: string; text: string }) {
  const bg = { amber: C.amberl, cash: C.cashl, red: C.redl }[tone];
  const fg = { amber: C.amber, cash: C.cash, red: C.red }[tone];
  return (
    <Card style={{ marginTop: 12, backgroundColor: bg, borderColor: bg, alignItems: 'center', paddingVertical: 22 }}>
      <Icon name={icon} size={32} color={fg} />
      <T style={{ fontWeight: '700', marginTop: 8 }}>{title}</T>
      <Small style={{ color: fg, textAlign: 'center', marginTop: 6, lineHeight: 18 }}>{text}</Small>
    </Card>
  );
}

const st = StyleSheet.create({
  num: { width: 24, height: 24, borderRadius: 12, backgroundColor: C.pl, alignItems: 'center', justifyContent: 'center' },
  upload: { marginTop: 12, borderWidth: 1.5, borderStyle: 'dashed', borderColor: '#BDB6E3', borderRadius: 16, padding: 20, alignItems: 'center', gap: 6, backgroundColor: '#FBFAFF' },
  uploadOk: { borderStyle: 'solid', borderColor: C.cash, backgroundColor: C.cashl },
  preview: { width: 120, height: 160, borderRadius: 10, marginBottom: 4 },
  ta: { borderWidth: 1.5, borderColor: C.line, borderRadius: 14, padding: 12, minHeight: 80, textAlignVertical: 'top', fontSize: 14, color: C.ink, backgroundColor: C.white },
});
