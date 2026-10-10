import { Platform } from 'react-native';
import * as SecureStore from 'expo-secure-store';

// The sign-in token lives in the phone's secure storage. SecureStore does not exist in a web browser,
// so the browser preview used while building falls back to localStorage (never used on a real phone).
const isWeb = Platform.OS === 'web';

export async function getItem(key: string): Promise<string | null> {
  if (isWeb) {
    try {
      return globalThis.localStorage?.getItem(key) ?? null;
    } catch {
      return null;
    }
  }
  return SecureStore.getItemAsync(key);
}

export async function setItem(key: string, value: string): Promise<void> {
  if (isWeb) {
    try {
      globalThis.localStorage?.setItem(key, value);
    } catch {
      /* private browsing: the token just will not survive a reload */
    }
    return;
  }
  await SecureStore.setItemAsync(key, value);
}

export async function removeItem(key: string): Promise<void> {
  if (isWeb) {
    try {
      globalThis.localStorage?.removeItem(key);
    } catch {
      /* nothing to remove */
    }
    return;
  }
  await SecureStore.deleteItemAsync(key);
}
