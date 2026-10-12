import { Platform } from 'react-native';
import { Directory, File, Paths } from 'expo-file-system';

// A child's voice is recorded to a file in the app's private cache, sent to be scored, and then not needed again.
// Nothing here may ever stop a reading, so every call swallows its own errors.

/** Removes one recording from the phone. A missing file, a web browser, or any error is simply ignored. */
export function discardRecording(uri: string | null | undefined) {
  if (!uri || Platform.OS === 'web') return;
  try {
    const file = new File(uri);
    if (file.exists) file.delete();
  } catch {
    /* the system also clears the cache by itself when the phone runs low on space */
  }
}

const AUDIO_NAME = /\.(m4a|aac|mp4|3gp|caf|wav)$/i;

function sweep(dir: Directory, depth: number) {
  let items: (Directory | File)[];
  try {
    items = dir.list();
  } catch {
    return;
  }
  for (const item of items) {
    try {
      if (item instanceof File) {
        if (AUDIO_NAME.test(item.name)) item.delete();
      } else if (depth > 0) {
        sweep(item, depth - 1);
      }
    } catch {
      /* one stubborn file never stops the rest */
    }
  }
}

/**
 * Removes any recording still lying in the app's cache: ones that were never sent (too quiet, a warm-up, a screen the
 * child left, a send that failed and was abandoned). Called when the app opens and when a child logs out, so no
 * recording of a child's voice stays on the phone longer than one visit.
 */
export function sweepRecordings() {
  if (Platform.OS === 'web') return;
  try {
    sweep(Paths.cache, 2);
  } catch {
    /* nothing to clean */
  }
}
