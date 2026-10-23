import * as ImagePicker from 'expo-image-picker';
import { scanFromURLAsync } from 'expo-camera';
import { isGoodCard, normalizeCode } from './learnerCode';

// "Use a photo of the card": the code on a learner card read from a picture the person chooses from their photos (a photo of the
// printed card, or a picture of it that a parent sent). The same check as the live camera: only a real TaraBasa card counts, so a
// foreign QR code or a blurred one is turned away, and the code is checked on the phone before the server is asked. The picture is
// read on the phone and never leaves it.

export type PhotoOutcome =
  | { kind: 'cancelled' }
  | { kind: 'error'; message: string }
  | {
      kind: 'cards';
      /** The good learner codes found, each once. */
      codes: string[];
      photos: number;
      /** Photos where no QR code could be read at all (too blurry, too dark, not a card). */
      unreadable: number;
      /** QR codes that were read but are not a TaraBasa learner card, or have a wrong check digit. */
      foreign: number;
    };

/** Gives up on a read that takes too long, so a stuck photo can never leave the screen waiting for ever. */
function within<T>(work: Promise<T>, ms: number): Promise<T> {
  return new Promise<T>((resolve, reject) => {
    const timer = setTimeout(() => reject(new Error('The photo took too long to read.')), ms);
    work.then(
      (value) => {
        clearTimeout(timer);
        resolve(value);
      },
      (error) => {
        clearTimeout(timer);
        reject(error);
      },
    );
  });
}

/** How long one photo may take to read. Reading a QR code from a picture on a phone takes well under a second. */
const READ_LIMIT_MS = 15_000;

/** Opens the phone's photos and reads the learner codes in what was chosen. One photo, or several when `multiple` is true. */
export async function readCardsFromPhotos(multiple: boolean): Promise<PhotoOutcome> {
  let picked: ImagePicker.ImagePickerResult;
  try {
    picked = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsMultipleSelection: multiple,
      selectionLimit: multiple ? 30 : 1,
      quality: 1,
    });
  } catch {
    return { kind: 'error', message: 'The phone could not open your photos. Try again, or type the code.' };
  }
  if (picked.canceled || !picked.assets?.length) return { kind: 'cancelled' };

  const codes = new Set<string>();
  let unreadable = 0;
  let foreign = 0;
  for (const asset of picked.assets) {
    try {
      const found = await within(scanFromURLAsync(asset.uri, ['qr']), READ_LIMIT_MS);
      if (found.length === 0) {
        unreadable += 1;
        continue;
      }
      for (const item of found) {
        if (isGoodCard(item.data)) codes.add(normalizeCode(item.data));
        else foreign += 1;
      }
    } catch {
      unreadable += 1;
    }
  }
  return { kind: 'cards', codes: [...codes], photos: picked.assets.length, unreadable, foreign };
}

/** What to tell the person when no good card was found in what they chose. */
export function noCardMessage(photos: number): string {
  return photos > 1
    ? 'No TaraBasa learner card was found in those photos. Use a clear, straight photo of the QR code, or type the code.'
    : 'No TaraBasa learner card was found in that photo. Use a clear, straight photo of the QR code, or type the code.';
}
