// Where the TaraBasa AI server lives. Set EXPO_PUBLIC_API_URL to point somewhere else
// (for example http://localhost:8123 while testing against the computer's own server).
export const API_URL = (process.env.EXPO_PUBLIC_API_URL ?? 'https://tarabasa-ai.onrender.com').replace(/\/+$/, '');

// The legal pages the website serves (the same ones the website's sign up forms link to). Opened in the phone's browser.
export const LEGAL_URLS = {
  privacy: `${API_URL}/privacy`,
  terms: `${API_URL}/terms`,
  cookies: `${API_URL}/cookies`,
  refunds: `${API_URL}/refunds`,
  consent: `${API_URL}/parent-consent`,
} as const;

// The free host goes to sleep when nobody uses it, and the first request after that can take a minute.
export const REQUEST_TIMEOUT_MS = 90_000;

// Scoring a recording can wait on the sleeping scoring service too (the server waits up to about
// 100 seconds for it to wake, then scores), so an upload is given much longer than a plain request.
export const UPLOAD_TIMEOUT_MS = 240_000;

// A failed upload is tried again by itself only when the connection failed this quickly (so nothing reached the server),
// after these pauses. A request that waited longer is never repeated by itself, so a reading cannot be scored twice.
export const UPLOAD_QUICK_FAILURE_SECONDS = 10;
export const UPLOAD_RETRY_PAUSES_MS = [3_000, 6_000];

// The version of this build. Keep it equal to "version" in app.json (tests/Feature/MobileVersionTest.php checks). It is shown in the
// technical note of a failed send, so a screenshot says which build it came from.
export const APP_VERSION = '0.6.1';

/**
 * The part of the page address after the #, browser only. The hosted preview puts the screen to show in a global instead
 * (window.__TB_HASH), because the frame it draws the app in has no address of its own.
 */
export function startHash(): string {
  if (typeof window === 'undefined') return '';
  return (window as unknown as { __TB_HASH?: string }).__TB_HASH ?? window.location.hash;
}

// Demo mode, browser only: the app answers from built-in sample data instead of a server (src/demoServer.ts), for the hosted
// preview. It is on when the web version is built with EXPO_PUBLIC_DEMO=1, or in the developer's browser at an address that
// has #demo in it. A phone build never sets it.
export const DEMO =
  process.env.EXPO_PUBLIC_DEMO === '1' || (__DEV__ && /#demo/.test(startHash()));

/** Which tab demo mode opens on, from the address (#demo=read, games, badges, shelf). */
export function demoStartTab(): 'home' | 'read' | 'games' | 'badges' | 'shelf' {
  if (!DEMO || typeof window === 'undefined') return 'home';
  const m = /#demo=(home|read|games|badges|shelf)/.exec(startHash());
  return (m?.[1] as 'home' | 'read' | 'games' | 'badges' | 'shelf') ?? 'home';
}
