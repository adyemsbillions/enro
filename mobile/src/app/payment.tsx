import { useEffect, useState } from 'react';
import { ActivityIndicator, View } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { useAuth } from '@/lib/auth';
import { errMsg } from '@/lib/api';
import { verifyUnlock } from '@/lib/unlock';
import { C } from '@/lib/theme';
import { Button, IconBox, Screen, Sub, T } from '@/components/ui';

/**
 * Opened by the enro://payment?reference=… link when a payment finishes outside the in-app browser
 * (e.g. the user paid in their bank app). Confirms with the server, then goes to special tasks.
 */
export default function Payment() {
  const { reference } = useLocalSearchParams<{ reference?: string }>();
  const { refresh, ready, token } = useAuth();
  const [state, setState] = useState<'checking' | 'ok' | 'pending' | 'error'>('checking');
  const [message, setMessage] = useState('');

  useEffect(() => {
    if (!ready) return;
    if (!token) { router.replace('/login'); return; }
    if (!reference) { router.replace('/special'); return; }
    let live = true;
    verifyUnlock(reference)
      .then(async (r) => {
        await refresh().catch(() => {});
        if (!live) return;
        setState(r.unlocked ? 'ok' : r.status === 'pending' ? 'pending' : 'error');
        if (!r.unlocked && r.status !== 'pending') setMessage('The payment was not completed. You have not been charged.');
      })
      .catch((e) => { if (live) { setState('error'); setMessage(errMsg(e)); } });
    return () => { live = false; };
  }, [ready, token, reference, refresh]);

  return (
    <Screen scroll={false}>
      <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', gap: 12, paddingHorizontal: 12 }}>
        {state === 'checking' ? (
          <>
            <ActivityIndicator size="large" color={C.p} />
            <T style={{ fontWeight: '700', fontSize: 16 }}>Confirming your payment…</T>
          </>
        ) : (
          <>
            <IconBox name={state === 'ok' ? 'checkmark-circle' : state === 'pending' ? 'time' : 'close-circle'} tone={state === 'ok' ? 'cash' : state === 'pending' ? 'amber' : 'red'} size={64} />
            <T style={{ fontWeight: '800', fontSize: 19, textAlign: 'center' }}>
              {state === 'ok' ? 'Special tasks unlocked!' : state === 'pending' ? 'Payment still processing' : 'Not unlocked'}
            </T>
            <Sub style={{ textAlign: 'center' }}>
              {state === 'ok' ? 'You can now do special tasks and earn real cash.'
                : state === 'pending' ? 'If you paid, open Special tasks again in a minute and it will unlock.' : message}
            </Sub>
            <Button title="Go to special tasks" style={{ alignSelf: 'stretch', marginTop: 10 }} onPress={() => router.replace('/special')} />
          </>
        )}
      </View>
    </Screen>
  );
}
