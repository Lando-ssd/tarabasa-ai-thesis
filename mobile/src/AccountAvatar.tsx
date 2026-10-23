import { useEffect, useState, type ReactNode } from 'react';
import { Image, View } from 'react-native';
import * as api from './api';
import { photoCache, rememberPhoto } from './photoCache';

// A Teacher's or Parent's own picture, drawn over the circle that already shows their initial. The photo is private (only its owner
// can open it), so it is downloaded with the sign in token and kept in memory (see photoCache). If the photo cannot be had, the
// initial simply stays.

/** The photo as an address the app can draw, or null while it loads, when there is none, or when it could not be had. */
export function usePhotoUri(photoUrl?: string | null): string | null {
  const [uri, setUri] = useState<string | null>(photoUrl ? (photoCache.get(photoUrl) ?? null) : null);

  useEffect(() => {
    if (!photoUrl) {
      setUri(null);
      return;
    }
    const kept = photoCache.get(photoUrl);
    if (kept) {
      setUri(kept);
      return;
    }
    setUri(null);
    let live = true;
    api.fetchPhotoDataUri(photoUrl).then((data) => {
      if (!data) return;
      rememberPhoto(photoUrl, data);
      if (live) setUri(data);
    });
    return () => {
      live = false;
    };
  }, [photoUrl]);

  return uri;
}

/** Wraps the circle that shows the initial and, when the account has a photo, draws the photo over it. */
export function AccountAvatar({ account, px, children }: { account: api.Account; px: number; children: ReactNode }) {
  const uri = usePhotoUri(account.photoUrl);
  return (
    <View style={{ width: px, height: px, borderRadius: px / 2, overflow: 'hidden' }}>
      {children}
      {uri ? (
        <Image
          source={{ uri }}
          accessible={false}
          style={{ position: 'absolute', top: 0, left: 0, right: 0, bottom: 0, width: px, height: px, borderRadius: px / 2 }}
          resizeMode="cover"
        />
      ) : null}
    </View>
  );
}
