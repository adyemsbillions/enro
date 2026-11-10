import { View } from 'react-native';
import { naira } from '@/lib/format';
import { useUnlock } from '@/lib/unlock';
import { C } from '@/lib/theme';
import { useToast } from './toast';
import { Button, Card, Icon, Row, Small, Sub, T } from './ui';

/** Shown on special tasks until the user has paid the one-time unlock fee. */
export function UnlockCard({ fee, onUnlocked }: { fee: number; onUnlocked: () => void }) {
  const toast = useToast();
  const { start, busy } = useUnlock((message, unlocked) => {
    toast(message);
    if (unlocked) onUnlocked();
  });

  return (
    <Card style={{ borderColor: '#D9D2FF', backgroundColor: '#FBFAFF' }}>
      <Row>
        <View style={{ width: 46, height: 46, borderRadius: 14, backgroundColor: C.pl, alignItems: 'center', justifyContent: 'center' }}>
          <Icon name="lock-closed" size={22} color={C.p} />
        </View>
        <View style={{ flex: 1 }}>
          <T style={{ fontWeight: '800', fontSize: 16 }}>Unlock real-cash tasks</T>
          <Small>One-time activation · you only pay once</Small>
        </View>
      </Row>
      <Sub style={{ marginTop: 12, fontSize: 13.5 }}>
        Pay a one-time fee of {naira(fee)} to start doing special tasks that pay real cash to your bank. Coin tasks stay free.
      </Sub>
      <Button title={`Unlock for ${naira(fee)}`} icon="lock-open-outline" style={{ marginTop: 14 }} onPress={start} loading={busy} />
      <Row gap={6} style={{ justifyContent: 'center', marginTop: 10 }}>
        <Icon name="shield-checkmark" size={14} color={C.cash} />
        <Small>Secure payment by Paystack · card, bank transfer or USSD</Small>
      </Row>
    </Card>
  );
}
