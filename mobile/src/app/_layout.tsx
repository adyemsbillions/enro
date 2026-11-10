import { Platform } from 'react-native';
import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { AuthProvider } from '@/lib/auth';
import { ToastProvider } from '@/components/toast';
import { PushManager } from '@/components/PushManager';
import { UpdateManager } from '@/components/UpdateManager';
import { C } from '@/lib/theme';

const isWeb = Platform.OS === 'web';

export default function RootLayout() {
  return (
    <SafeAreaProvider>
      <AuthProvider>
        <ToastProvider>
          <StatusBar style="dark" />
          {/* push + OTA updates are phone-only; their hooks throw on web (browser preview) */}
          {!isWeb && <PushManager />}
          <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: C.bg } }}>
            <Stack.Screen name="index" options={{ animation: 'fade' }} />
            <Stack.Screen name="(tabs)" options={{ animation: 'fade' }} />
          </Stack>
          {/* after the Stack so the "new version" bar floats above every screen */}
          {!isWeb && <UpdateManager />}
        </ToastProvider>
      </AuthProvider>
    </SafeAreaProvider>
  );
}
