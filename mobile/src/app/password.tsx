import { useState } from 'react';
import { router } from 'expo-router';
import { api, errMsg } from '@/lib/api';
import { useToast } from '@/components/toast';
import { Button, ErrorNote, Field, Header, Screen } from '@/components/ui';

export default function ChangePassword() {
  const toast = useToast();
  const [current, setCurrent] = useState('');
  const [next, setNext] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const save = async () => {
    setError(null);
    if (next.length < 8) return setError('New password must be at least 8 characters');
    setLoading(true);
    try {
      await api('/me/password', { body: { current_password: current, password: next } });
      toast('Password changed');
      router.back();
    } catch (e) {
      setError(errMsg(e));
    } finally {
      setLoading(false);
    }
  };

  return (
    <Screen header={<Header title="Change password" />}>
      {error && <ErrorNote text={error} />}
      <Field label="Current password" icon="lock-closed-outline" value={current} onChangeText={setCurrent} secureTextEntry autoComplete="current-password" />
      <Field label="New password" icon="key-outline" value={next} onChangeText={setNext} secureTextEntry autoComplete="new-password" placeholder="At least 8 characters" />
      <Button title="Save password" onPress={save} loading={loading} disabled={!current || !next} />
    </Screen>
  );
}
