import { DEMO, SERVICE_AWAKE_MS, SERVICE_HEALTH_URLS, SERVICE_RETRY_PAUSE_MS, SERVICE_WAKE_WAIT_MS } from './config';

// Waking the sleeping helper services from the phone itself.
//
// They sleep on free hosting after about 15 quiet minutes. Our own server cannot wake them: a request from one hosted
// service to a sleeping one is turned away at once ("429 Too Many Requests"). A request from a normal connection is held
// while the service starts, and then answered. The website does this from the visitor's browser
// (resources/views/partials/wake-from-browser.blade.php); this is the phone's version of the same bare request. Nothing about
// anyone is sent: no token, no cookie, no recording. The Privacy Policy says so.

export type Service = keyof typeof SERVICE_HEALTH_URLS;

const awakeAt: Partial<Record<Service, number>> = {};
const asking: Partial<Record<Service, Promise<boolean>>> = {};

const pause = (ms: number) => new Promise<void>((resolve) => setTimeout(resolve, ms));

/** Asks the health page until it answers or the time is up. */
async function ask(service: Service): Promise<boolean> {
  const end = Date.now() + SERVICE_WAKE_WAIT_MS;
  for (;;) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), Math.max(1_000, end - Date.now()));
    try {
      // A browser (the web preview) may not read another site's answer: "no-cors" still reaches it, and the answer is "opaque".
      const response = await fetch(SERVICE_HEALTH_URLS[service], { mode: 'no-cors', cache: 'no-store', credentials: 'omit', signal: controller.signal });
      if (response.ok || response.type === 'opaque') {
        awakeAt[service] = Date.now();
        return true;
      }
    } catch {
      // No connection, or it took too long: tried again below while there is time.
    } finally {
      clearTimeout(timer);
    }
    if (Date.now() + SERVICE_RETRY_PAUSE_MS >= end) return false;
    await pause(SERVICE_RETRY_PAUSE_MS);
  }
}

/**
 * Resolves when the service has answered (true), or when the app has waited long enough (false). Never throws. Does not
 * ask again while it answered recently, and two callers at once share one request.
 */
export function ensureAwake(service: Service): Promise<boolean> {
  if (DEMO) return Promise.resolve(true);
  const seen = awakeAt[service];
  if (seen && Date.now() - seen < SERVICE_AWAKE_MS) return Promise.resolve(true);
  let running = asking[service];
  if (!running) {
    running = ask(service).finally(() => {
      delete asking[service];
    });
    asking[service] = running;
  }
  return running;
}

/** Wakes services as a screen opens, without making the screen wait. */
export function wake(...services: Service[]) {
  services.forEach((service) => void ensureAwake(service));
}

/** The service just refused or failed: the next try asks it again instead of trusting an old answer. */
export function forgetAwake(service: Service) {
  delete awakeAt[service];
}
