import { useCallback, useState } from 'react';
import * as Linking from 'expo-linking';
import * as WebBrowser from 'expo-web-browser';
import { api, errMsg } from './api';
import { useAuth } from './auth';

type InitRes = { unlocked: boolean; reference?: string; url?: string };
type VerifyRes = { status: 'paid' | 'pending' | 'failed' | 'abandoned'; unlocked: boolean };

const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms));

/** Asks the server to confirm a payment with Paystack; retries briefly because bank transfers can take a moment. */
export async function verifyUnlock(reference: string, tries = 6): Promise<VerifyRes> {
  let last: VerifyRes = { status: 'pending', unlocked: false };
  for (let i = 0; i < tries; i++) {
    last = await api<VerifyRes>('/special-tasks/unlock/verify', { body: { reference } });
    if (last.unlocked || last.status === 'failed' || last.status === 'abandoned') return last;
    await sleep(2500);
  }
  return last;
}

/**
 * One-time fee that unlocks special tasks: opens Paystack checkout in an in-app browser,
 * then asks the server to verify the payment with Paystack before unlocking.
 */
export function useUnlock(onDone: (message: string, unlocked: boolean) => void) {
  const { refresh } = useAuth();
  const [busy, setBusy] = useState(false);

  const start = useCallback(async () => {
    setBusy(true);
    try {
      const init = await api<InitRes>('/special-tasks/unlock', { method: 'POST' });
      if (init.unlocked || !init.url || !init.reference) {
        await refresh().catch(() => {});
        onDone('Special tasks are unlocked', true);
        return;
      }
      // Paystack redirects to our /pay/return.php, which hands back to enro://payment and closes this browser
      await WebBrowser.openAuthSessionAsync(init.url, Linking.createURL('payment'));
      const r = await verifyUnlock(init.reference);
      await refresh().catch(() => {});
      if (r.unlocked) onDone('Payment confirmed. Special tasks unlocked! 🎉', true);
      else if (r.status === 'pending') onDone('Payment not confirmed yet. If you paid, pull down to refresh in a minute.', false);
      else onDone('Payment was not completed. You have not been charged.', false);
    } catch (e) {
      onDone(errMsg(e), false);
    } finally {
      setBusy(false);
    }
  }, [onDone, refresh]);

  return { start, busy };
}
