// Builds a multipart/form-data body by hand, as plain bytes.
//
// Why this exists: on Android, React Native sends a file given as { uri } by opening it in its own network code, and when
// that cannot be done the request fails instantly with a generic "Network request failed". Sending the recording as bytes
// we read ourselves avoids that step, and a body of known length (not chunked) is also easier on the servers in front of us.
// This file imports nothing from React Native so it can be tested on any computer.

export type MultipartPart =
  | { name: string; value: string }
  | { name: string; filename: string; type: string; bytes: Uint8Array };

/** Text to UTF-8 bytes (written out so it does not depend on TextEncoder being there). */
export function utf8(text: string): Uint8Array {
  const out: number[] = [];
  for (const ch of text) {
    const c = ch.codePointAt(0) as number;
    if (c < 0x80) out.push(c);
    else if (c < 0x800) out.push(0xc0 | (c >> 6), 0x80 | (c & 0x3f));
    else if (c < 0x10000) out.push(0xe0 | (c >> 12), 0x80 | ((c >> 6) & 0x3f), 0x80 | (c & 0x3f));
    else out.push(0xf0 | (c >> 18), 0x80 | ((c >> 12) & 0x3f), 0x80 | ((c >> 6) & 0x3f), 0x80 | (c & 0x3f));
  }
  return Uint8Array.from(out);
}

export function newBoundary(): string {
  let id = '';
  for (let i = 0; i < 24; i++) id += Math.floor(Math.random() * 16).toString(16);
  return `----TaraBasaForm${id}`;
}

/** A header value must not be able to break out of its quotes or its line. */
function safe(value: string): string {
  return value.replace(/[\r\n"\\]/g, '_');
}

export function buildMultipart(parts: MultipartPart[], boundary: string): Uint8Array {
  const chunks: Uint8Array[] = [];
  for (const part of parts) {
    let head = `--${boundary}\r\nContent-Disposition: form-data; name="${safe(part.name)}"`;
    if ('bytes' in part) {
      head += `; filename="${safe(part.filename)}"\r\nContent-Type: ${safe(part.type)}`;
    }
    chunks.push(utf8(head + '\r\n\r\n'));
    chunks.push('bytes' in part ? part.bytes : utf8(part.value));
    chunks.push(utf8('\r\n'));
  }
  chunks.push(utf8(`--${boundary}--\r\n`));

  let total = 0;
  for (const c of chunks) total += c.length;
  const body = new Uint8Array(total);
  let at = 0;
  for (const c of chunks) {
    body.set(c, at);
    at += c.length;
  }
  return body;
}
