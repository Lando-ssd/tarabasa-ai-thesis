import { Platform } from 'react-native';
import * as SecureStore from 'expo-secure-store';

// The sign-in token lives in the phone's secure storage. SecureStore does not exist in a web browser,
// so the browser preview used while building falls back to localStorage (never used on a real phone).
const isWeb = Platform.OS === 'web';
// A browser can refuse local storage (a private window, a sandboxed frame). The browser preview then keeps the value for this visit only.
const memory = new Map<string, string>();

export async function getItem(key: string): Promise<string | null> {
  if (isWeb) {
    try {
      return globalThis.localStorage?.getItem(key) ?? memory.get(key) ?? null;
    } catch {
      return memory.get(key) ?? null;
    }
  }
  return SecureStore.getItemAsync(key);
}

export async function setItem(key: string, value: string): Promise<void> {
  if (isWeb) {
    memory.set(key, value);
    try {
      globalThis.localStorage?.setItem(key, value);
    } catch {
      /* private browsing or a sandboxed frame: it is kept for this visit only */
    }
    return;
  }
  await SecureStore.setItemAsync(key, value);
}

export async function removeItem(key: string): Promise<void> {
  if (isWeb) {
    memory.delete(key);
    try {
      globalThis.localStorage?.removeItem(key);
    } catch {
      /* nothing to remove */
    }
    return;
  }
  await SecureStore.deleteItemAsync(key);
}
