import { Platform } from 'react-native';
import * as SecureStore from 'expo-secure-store';
import { getItem, removeItem, setItem } from './storage';

/*
 * "Welcome back" login: the phone remembers who logged in last (name + email only), so the login screen
 * greets them and asks for just the password. With fingerprint login switched on, the email and password
 * are kept in the phone's encrypted store and can only be read after a fingerprint / face check.
 */
const LAST = 'enro_last_user';
const BIO_ON = 'enro_bio_on';
const BIO_ASKED = 'enro_bio_asked';
const BIO_LOGIN = 'enro_bio_login';
const bioOpts: SecureStore.SecureStoreOptions = { requireAuthentication: true, authenticationPrompt: 'Confirm it is you to log in to Enro' };

export type LastUser = { name: string; login: string };

export async function getLastUser(): Promise<LastUser | null> {
  try {
    const v = JSON.parse((await getItem(LAST)) ?? 'null');
    return v && typeof v.login === 'string' && v.login ? { name: String(v.name ?? ''), login: v.login } : null;
  } catch {
    return null;
  }
}

export async function rememberUser(u: LastUser): Promise<void> {
  const last = await getLastUser();
  // a different person logged in on this phone: the old fingerprint login must not open their account
  if (last && last.login.toLowerCase() !== u.login.toLowerCase()) await disableBio();
  await setItem(LAST, JSON.stringify(u));
}

/** "Not you?" — forget the remembered person and their fingerprint login. */
export async function forgetUser(): Promise<void> {
  await disableBio();
  await removeItem(LAST);
}

export function bioAvailable(): boolean {
  if (Platform.OS === 'web') return false;
  try {
    return SecureStore.canUseBiometricAuthentication();
  } catch {
    return false;
  }
}

export async function bioEnabled(): Promise<boolean> {
  return bioAvailable() && (await getItem(BIO_ON)) === '1';
}

export async function bioAsked(): Promise<boolean> {
  return (await getItem(BIO_ASKED)) === '1';
}

export async function setBioAsked(): Promise<void> {
  await setItem(BIO_ASKED, '1');
}

/** Saves the login behind the fingerprint. Returns false if the fingerprint check was cancelled or failed. */
export async function enableBio(login: string, password: string): Promise<boolean> {
  try {
    await SecureStore.setItemAsync(BIO_LOGIN, JSON.stringify({ login, password }), bioOpts);
    await setItem(BIO_ON, '1');
    await setBioAsked();
    return true;
  } catch {
    return false;
  }
}

export async function disableBio(): Promise<void> {
  await removeItem(BIO_ON).catch(() => {});
  await removeItem(BIO_ASKED).catch(() => {});
  if (Platform.OS !== 'web') await SecureStore.deleteItemAsync(BIO_LOGIN, bioOpts).catch(() => {});
}

/** Shows the fingerprint prompt and returns the saved login, or null if it was cancelled or can't be read. */
export async function bioCredentials(): Promise<{ login: string; password: string } | null> {
  try {
    const v = JSON.parse((await SecureStore.getItemAsync(BIO_LOGIN, bioOpts)) ?? 'null');
    return v && v.login && v.password ? v : null;
  } catch {
    return null;
  }
}
