import { Platform } from 'react-native';
import * as ImagePicker from 'expo-image-picker';
import { api } from './api';
import type { UnlockInfo } from './types';

export type PickedImage = { uri: string; name: string; type: string };

/** Lets the user choose a screenshot from their gallery. Returns null if they cancel. Throws if access is denied. */
export async function pickImage(): Promise<PickedImage | null> {
  const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
  if (!perm.granted) throw new Error('Allow photo access to upload your receipt');
  const r = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], quality: 0.7 });
  if (r.canceled || !r.assets[0]) return null;
  const a = r.assets[0];
  return { uri: a.uri, name: a.fileName ?? 'receipt.jpg', type: a.mimeType ?? 'image/jpeg' };
}

/** Adds an image to a FormData the way each platform expects. */
export async function appendImage(form: FormData, field: string, img: PickedImage) {
  if (Platform.OS === 'web') {
    form.append(field, await (await fetch(img.uri)).blob(), img.name);
  } else {
    // React Native's FormData accepts {uri, name, type} file objects
    form.append(field, { uri: img.uri, name: img.name, type: img.type } as unknown as Blob);
  }
}

/** Sends the bank-transfer receipt for the unlock fee. Admins are notified and confirm it. */
export async function uploadReceipt(img: PickedImage): Promise<UnlockInfo> {
  const form = new FormData();
  await appendImage(form, 'proof', img);
  return api<UnlockInfo>('/special-tasks/unlock/receipt', { method: 'POST', form });
}
