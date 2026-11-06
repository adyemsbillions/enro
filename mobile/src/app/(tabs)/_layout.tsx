import { Redirect, Tabs } from 'expo-router';
import type { ColorValue } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { useAuth } from '@/lib/auth';
import { C } from '@/lib/theme';

const tab = (title: string, icon: keyof typeof Ionicons.glyphMap, iconOn: keyof typeof Ionicons.glyphMap) => ({
  title,
  tabBarIcon: ({ color, focused }: { color: ColorValue; focused: boolean }) => <Ionicons name={focused ? iconOn : icon} size={23} color={color as string} />,
});

export default function TabsLayout() {
  const { token, ready } = useAuth();
  if (ready && !token) return <Redirect href="/login" />;

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: C.p,
        tabBarInactiveTintColor: '#9A97AC',
        tabBarLabelStyle: { fontSize: 11, fontWeight: '600' },
        tabBarStyle: { borderTopColor: C.line, backgroundColor: C.white },
        sceneStyle: { backgroundColor: C.bg },
      }}
    >
      <Tabs.Screen name="index" options={tab('Home', 'home-outline', 'home')} />
      <Tabs.Screen name="tasks" options={tab('Tasks', 'list-outline', 'list')} />
      <Tabs.Screen name="special" options={tab('Special', 'star-outline', 'star')} />
      <Tabs.Screen name="wallet" options={tab('Wallet', 'wallet-outline', 'wallet')} />
      <Tabs.Screen name="profile" options={tab('Profile', 'person-outline', 'person')} />
    </Tabs>
  );
}
