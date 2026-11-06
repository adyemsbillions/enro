import { Platform } from 'react-native';
import * as Application from 'expo-application';
import { getItem, setItem } from './storage';

/** Stable-ish device id sent on sign-up to limit one account per device. */
export async function deviceId(): Promise<string> {
  try {
    if (Platform.OS === 'android') {
      const id = Application.getAndroidId();
      if (id) return 'a_' + id;
    }
    if (Platform.OS === 'ios') {
      const id = await Application.getIosIdForVendorAsync();
      if (id) return 'i_' + id;
    }
  } catch {
    // fall through
  }
  let id = await getItem('enro_device');
  if (!id) {
    id = 'w_' + Math.random().toString(36).slice(2) + Date.now().toString(36);
    await setItem('enro_device', id);
  }
  return id;
}
