// Where the TaraBasa AI server lives. Set EXPO_PUBLIC_API_URL to point somewhere else
// (for example http://localhost:8123 while testing against the computer's own server).
export const API_URL = (process.env.EXPO_PUBLIC_API_URL ?? 'https://tarabasa-ai.onrender.com').replace(/\/+$/, '');

// The free host goes to sleep when nobody uses it, and the first request after that can take a minute.
export const REQUEST_TIMEOUT_MS = 90_000;

// Scoring a recording can wait on the sleeping scoring service too (the server waits up to about
// 100 seconds for it to wake, then scores), so an upload is given much longer than a plain request.
export const UPLOAD_TIMEOUT_MS = 240_000;
