// Learner codes, the same rules as the website (app/Support/LearnerCode.php). A new code looks like TB26-48293: TB, the
// two digit year, four digits and a check digit that catches a mistyped or misread code on the phone before the
// server is asked. An older code looks like TB-48293 and has no check digit. A card holds the code only, never the PIN.

/** What was typed or scanned, tidied the way the server stores it: TB2648293, tb26 48293 and TB26-48293 are the same. */
export function normalizeCode(raw: string): string {
  const code = raw.toUpperCase().replace(/\s+/g, '');
  let m = code.match(/^TB(\d{2})-?(\d{5})$/);
  if (m) return `TB${m[1]}-${m[2]}`;
  m = code.match(/^TB-?([0-9A-Z]{5})$/);
  if (m) return `TB-${m[1]}`;
  return code;
}

/** True when the text has the shape of a learner code, new or old. */
export function looksLikeCode(raw: string): boolean {
  return /^TB(\d{2}-\d{5}|-[0-9A-Z]{5})$/.test(normalizeCode(raw));
}

function checkDigit(digits: string): string {
  let sum = 0;
  let double = true;
  for (let i = digits.length - 1; i >= 0; i--) {
    let d = Number(digits[i]);
    if (double) {
      d *= 2;
      if (d > 9) d -= 9;
    }
    sum += d;
    double = !double;
  }
  return String((10 - (sum % 10)) % 10);
}

/** New codes carry a check digit; old ones cannot be checked, so they pass. */
export function checkDigitOk(raw: string): boolean {
  const m = normalizeCode(raw).match(/^TB(\d{2})-(\d{4})(\d)$/);
  if (!m) return true;
  return checkDigit(m[1] + m[2]) === m[3];
}

/** A scanned card is good only if it looks like a code and its check digit is right. */
export function isGoodCard(raw: string): boolean {
  return looksLikeCode(raw) && checkDigitOk(raw);
}
