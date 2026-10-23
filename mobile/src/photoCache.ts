// Profile photos already downloaded in this session, by address. The address ends in ?v=<version>, so a changed photo is a new
// address and the old picture is never reused. Cleared at sign out, so the next person on the phone never sees it.

export const photoCache = new Map<string, string>();

/** Only the few most recent are kept: a person has one photo, so this is only a safety limit. */
export const MAX_PHOTOS_KEPT = 4;

export function rememberPhoto(url: string, data: string) {
  if (photoCache.size >= MAX_PHOTOS_KEPT) photoCache.delete(photoCache.keys().next().value as string);
  photoCache.set(url, data);
}

export function forgetPhotos() {
  photoCache.clear();
}
