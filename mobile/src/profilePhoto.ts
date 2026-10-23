import { Platform } from 'react-native';
import * as ImagePicker from 'expo-image-picker';
import * as ImageManipulator from 'expo-image-manipulator';
import { File } from 'expo-file-system';

// A Teacher's or Parent's own profile photo. The picture is chosen from the phone's photos (never the camera, so the privacy
// page stays true), cut to a centred square, shrunk to 480 by 480 and saved as a small JPEG, like the website does, so it is
// tens of kilobytes and never stretched. The server still checks that it is a real picture of at most 1 MB.

/** The side of the finished square, in pixels. Matches the website. */
export const PHOTO_SIDE = 480;
/** A little under the server's 1 MB limit, so a picture that would be refused is never sent. */
const MAX_BYTES = 900 * 1024;
/** How long cutting and shrinking one picture may take before the screen stops waiting. */
const PREPARE_LIMIT_MS = 30_000;

export type PhotoChoice =
  | { kind: 'cancelled' }
  | { kind: 'error'; message: string }
  | { kind: 'ready'; bytes: Uint8Array };

function within<T>(work: Promise<T>, ms: number): Promise<T> {
  return new Promise<T>((resolve, reject) => {
    const timer = setTimeout(() => reject(new Error('The picture took too long to prepare.')), ms);
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

async function readBytes(uri: string): Promise<Uint8Array> {
  if (Platform.OS === 'web') return new Uint8Array(await (await fetch(uri)).arrayBuffer());
  return new File(uri).bytes();
}

function discard(uri: string) {
  if (Platform.OS === 'web') return;
  try {
    const f = new File(uri);
    if (f.exists) f.delete();
  } catch {
    // the phone clears its own cache later
  }
}

/** Opens the phone's photos and returns the chosen picture as a small square JPEG, or says why it could not. */
export async function chooseProfilePhoto(): Promise<PhotoChoice> {
  let picked: ImagePicker.ImagePickerResult;
  try {
    picked = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsEditing: true,
      aspect: [1, 1],
      quality: 1,
    });
  } catch {
    return { kind: 'error', message: 'We could not open your photos. Check that TaraBasa AI may use them, then try again.' };
  }
  if (picked.canceled || !picked.assets?.length) return { kind: 'cancelled' };

  const asset = picked.assets[0];
  let madeUri: string | null = null;
  try {
    const bytes = await within(
      (async () => {
        const context = ImageManipulator.ImageManipulator.manipulate(asset.uri);
        const w = asset.width;
        const h = asset.height;
        const side = w && h ? Math.min(w, h) : 0;
        // The phone's own cropper already gives a square. If it did not (some phones), cut the middle square here.
        if (side > 0 && w !== h) context.crop({ originX: Math.floor((w - side) / 2), originY: Math.floor((h - side) / 2), width: side, height: side });
        context.resize({ width: side > 0 ? Math.min(PHOTO_SIDE, side) : PHOTO_SIDE });
        const rendered = await context.renderAsync();
        const saved = await rendered.saveAsync({ format: ImageManipulator.SaveFormat.JPEG, compress: 0.8 });
        madeUri = saved.uri;
        return readBytes(saved.uri);
      })(),
      PREPARE_LIMIT_MS,
    );
    if (bytes.length === 0) return { kind: 'error', message: 'We could not read that picture. Please choose a different one.' };
    if (bytes.length > MAX_BYTES) return { kind: 'error', message: 'That picture is too large even after shrinking it. Please choose a different one.' };
    return { kind: 'ready', bytes };
  } catch {
    return { kind: 'error', message: 'We could not prepare that picture. Please choose a different one.' };
  } finally {
    if (madeUri) discard(madeUri);
  }
}
