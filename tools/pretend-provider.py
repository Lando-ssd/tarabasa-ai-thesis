"""
A PRETEND payment provider, for trying TaraBasa's test payments on one computer without a PayMongo account (config/payments.php).

It is a development tool only. It is NOT PayMongo, it never touches money, and nothing here is part of the website. It does what TaraBasa asks of PayMongo's hosted
checkout, as far as PayMongo's documentation describes it (read on 2026-11-20, see docs/HISTORY.md, "Test payments"), so the whole path can be walked on this computer:

    POST /v1/checkout_sessions              makes a session and gives back a checkout address (needs a key that starts with sk_test_)
    GET  /v1/checkout_sessions/{id}         what the session looks like now, with its payments
    POST /v1/checkout_sessions/{id}/expire  closes it (refused when it is already paid)
    GET  /pay/{id}                          a plain grey test page: choose Card, GCash or Maya, then Authorize, Fail or Cancel
    after Authorize it sends TaraBasa a SIGNED message (Paymongo-Signature, test signature) like the real provider, then sends the browser back to success_url

How to use it (two programs at once, both on this computer):
  1. In the hosting or local settings of the website, put:
         PAYMENTS_ENABLED=true
         PAYMONGO_SECRET_KEY=sk_test_pretend
         PAYMONGO_WEBHOOK_SECRET=whsk_pretend
         PAYMONGO_BASE_URL=http://127.0.0.1:8125
  2. Start this:   python tools/pretend-provider.py --webhook http://localhost:8123/api/webhooks/paymongo
     (use the address your local website really has; add --no-webhook to see what happens when the message never arrives: the page the parent comes back to
      then asks this provider itself)
  3. Open the Parent's Activities page and press Go to test payment.

It uses only Python's standard library, listens only on 127.0.0.1 and keeps everything in memory. A real test with a real PayMongo test key is still the proof: this
tool can only be as right as what was read in the documentation.
"""
import argparse
import base64
import hashlib
import hmac
import html
import json
import sys
import threading
import time
import urllib.error
import urllib.request
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import parse_qs, urlparse

SESSIONS = {}
LOCK = threading.Lock()
CONFIG = {'secret': 'whsk_pretend', 'webhook': None, 'seq': 0}


def now():
    return int(time.time())


RUN = format(int(time.time()), 'x')  # so a restart never hands out an id that an earlier run already used, as the real provider never does


def new_id(prefix):
    CONFIG['seq'] += 1
    return f'{prefix}_pretend_{RUN}_{CONFIG["seq"]}'


def view(session):
    return {'id': session['id'], 'type': 'checkout_session', 'attributes': {
        'status': session['status'], 'reference_number': session['reference_number'], 'livemode': False,
        'payments': session['payments'], 'line_items': session['line_items'], 'success_url': session['success_url'], 'cancel_url': session['cancel_url'],
    }}


def deliver_webhook(session):
    if not CONFIG['webhook']:
        print('  (no message sent: --no-webhook)')
        return
    event = {'data': {'id': new_id('evt'), 'type': 'event', 'attributes': {
        'type': 'checkout_session.payment.paid', 'livemode': False, 'created_at': now(), 'updated_at': now(), 'data': view(session)}}}
    body = json.dumps(event).encode('utf-8')
    t = str(now())
    signature = hmac.new(CONFIG['secret'].encode(), t.encode() + b'.' + body, hashlib.sha256).hexdigest()
    req = urllib.request.Request(CONFIG['webhook'], data=body, method='POST', headers={
        'Content-Type': 'application/json', 'Accept': 'application/json', 'Paymongo-Signature': f't={t},te={signature},li='})
    try:
        with urllib.request.urlopen(req, timeout=15) as res:
            print(f'  message sent, TaraBasa answered {res.status}: {res.read().decode()[:120]}')
    except urllib.error.HTTPError as e:
        print(f'  message sent, TaraBasa answered {e.code}: {e.read().decode()[:120]}')
    except Exception as e:  # noqa: BLE001
        print(f'  message could not be sent: {e}')


PAGE = """<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pretend payment provider</title>
<style>body{{font:16px/1.5 system-ui,sans-serif;background:#eef1f5;color:#2b3b4a;margin:0;padding:24px}}main{{max-width:420px;margin:0 auto;background:#fff;border:2px dashed #b9c4d0;border-radius:16px;padding:20px}}
h1{{font-size:20px;margin:0 0 4px}}.amount{{font-size:30px;font-weight:700;margin:8px 0}}.tag{{display:inline-block;background:#fff3d6;color:#7a5200;border-radius:8px;padding:3px 8px;font-size:13px;font-weight:700;text-transform:uppercase}}
label{{display:block;border:2px solid #b9c4d0;border-radius:12px;padding:12px;margin:8px 0;cursor:pointer}}button{{font:inherit;font-weight:700;width:100%;min-height:48px;border:0;border-radius:12px;margin-top:8px;cursor:pointer}}
.go{{background:#1f9e83;color:#fff}}.bad{{background:#fff;color:#c53a3a;border:2px solid #f0b3b3}}.no{{background:#fff;color:#587086;border:2px solid #dbe7f3}}small{{color:#587086}}.err{{background:#fdecec;color:#9c2c2c;border-radius:10px;padding:10px;margin:8px 0}}</style>
<main><span class="tag">Pretend provider, test mode</span><h1>Pay TaraBasa AI</h1><div>{item}</div><div class="amount">&#8369;{amount}</div><small>Reference {ref}</small>
{error}<form method="post" action="/pay/{id}/authorize">
<label><input type="radio" name="method" value="card" checked> Card</label><label><input type="radio" name="method" value="gcash"> GCash</label><label><input type="radio" name="method" value="paymaya"> Maya</label>
<button class="go" type="submit">Authorize test payment</button></form>
<form method="post" action="/pay/{id}/fail"><button class="bad" type="submit">Fail test payment</button></form>
<form method="post" action="/pay/{id}/cancel"><button class="no" type="submit">Cancel and go back</button></form>
<p><small>This is a stand in on this computer. No card or wallet number is asked for, and no money exists.</small></p></main></html>"""


class Handler(BaseHTTPRequestHandler):
    server_version = 'PretendProvider/1'

    def log_message(self, fmt, *args):  # quieter
        sys.stdout.write('  ' + (fmt % args) + '\n')

    # -------------------------------------------------------------- helpers
    def send(self, status, body, ctype='application/json', headers=None):
        data = body if isinstance(body, bytes) else (json.dumps(body) if ctype == 'application/json' else body).encode('utf-8')
        self.send_response(status)
        self.send_header('Content-Type', ctype + '; charset=utf-8')
        self.send_header('Content-Length', str(len(data)))
        for k, v in (headers or {}).items():
            self.send_header(k, v)
        self.end_headers()
        self.wfile.write(data)

    def redirect(self, url):
        self.send(303, b'', 'text/plain', {'Location': url})

    def key_ok(self):
        auth = self.headers.get('Authorization', '')
        if not auth.startswith('Basic '):
            return False
        try:
            user = base64.b64decode(auth[6:]).decode().split(':', 1)[0]
        except Exception:  # noqa: BLE001
            return False
        return user.startswith('sk_test_')

    def error(self, status, detail):
        self.send(status, {'errors': [{'code': 'pretend_error', 'detail': detail}]})

    def body(self):
        length = int(self.headers.get('Content-Length') or 0)
        return self.rfile.read(length) if length else b''

    # -------------------------------------------------------------- routes
    def do_GET(self):
        path = urlparse(self.path).path
        if path.startswith('/v1/checkout_sessions/'):
            if not self.key_ok():
                return self.error(401, 'Authentication failed: use a test key (sk_test_...).')
            session = SESSIONS.get(path.rsplit('/', 1)[1])
            return self.send(200, {'data': view(session)}) if session else self.error(404, 'No such session.')
        if path.startswith('/pay/'):
            session = SESSIONS.get(path.split('/')[2])
            if not session:
                return self.send(404, 'Not found', 'text/plain')
            qs = parse_qs(urlparse(self.path).query)
            error = f'<div class="err">{html.escape(qs["error"][0])}</div>' if 'error' in qs else ''
            item = html.escape(session['line_items'][0]['name']) if session['line_items'] else ''
            return self.send(200, PAGE.format(item=item, amount=f'{session["line_items"][0]["amount"] / 100:,.2f}', ref=html.escape(session['reference_number']), id=session['id'], error=error), 'text/html')
        self.send(404, 'Not found', 'text/plain')

    def do_POST(self):
        path = urlparse(self.path).path
        raw = self.body()

        if path == '/v1/checkout_sessions':
            if not self.key_ok():
                return self.error(401, 'Authentication failed: use a test key (sk_test_...).')
            try:
                attrs = json.loads(raw)['data']['attributes']
                item = attrs['line_items'][0]
                assert item['currency'] == 'PHP' and int(item['amount']) > 0
            except Exception:  # noqa: BLE001
                return self.error(400, 'The request was not understood: line_items, amount and currency are needed.')
            with LOCK:
                sid = new_id('cs')
                SESSIONS[sid] = {'id': sid, 'status': 'active', 'reference_number': attrs.get('reference_number', ''), 'line_items': attrs['line_items'],
                                 'success_url': attrs.get('success_url', ''), 'cancel_url': attrs.get('cancel_url', ''), 'payments': []}
            host = self.headers.get('Host', '127.0.0.1:8125')
            print(f'  made {sid} for {attrs.get("reference_number")}: {item["amount"]} centavos, {item["name"]!r}; methods {attrs.get("payment_method_types")}')
            return self.send(200, {'data': {'id': sid, 'type': 'checkout_session', 'attributes': {'checkout_url': f'http://{host}/pay/{sid}', 'status': 'active'}}})

        if path.startswith('/v1/checkout_sessions/') and path.endswith('/expire'):
            if not self.key_ok():
                return self.error(401, 'Authentication failed: use a test key (sk_test_...).')
            session = SESSIONS.get(path.split('/')[3])
            if not session:
                return self.error(404, 'No such session.')
            if session['status'] == 'expired' or any(p['attributes']['status'] == 'paid' for p in session['payments']):
                return self.error(400, 'This session cannot be expired: it is already expired or has a paid payment.')
            session['status'] = 'expired'
            print(f'  expired {session["id"]}')
            return self.send(200, {})

        if path.startswith('/pay/'):
            parts = path.split('/')
            session = SESSIONS.get(parts[2])
            if not session or len(parts) < 4:
                return self.send(404, 'Not found', 'text/plain')
            action = parts[3]
            if session['status'] != 'active':
                return self.send(410, 'This checkout is closed.', 'text/plain')
            if action == 'cancel':
                return self.redirect(session['cancel_url'] or '/')
            if action == 'fail':
                return self.redirect(f'/pay/{session["id"]}?error=' + 'The test payment was declined. You can choose another way and try again.'.replace(' ', '+'))
            if action == 'authorize':
                method = (parse_qs(raw.decode()).get('method') or ['card'])[0]
                with LOCK:
                    session['payments'].append({'id': new_id('pay'), 'type': 'payment', 'attributes': {
                        'amount': session['line_items'][0]['amount'], 'currency': 'PHP', 'status': 'paid', 'source': {'type': method}}})
                print(f'  paid {session["id"]} with {method}')
                threading.Thread(target=deliver_webhook, args=(session,), daemon=True).start()
                return self.redirect(session['success_url'] or '/')
        self.send(404, 'Not found', 'text/plain')


def main():
    ap = argparse.ArgumentParser(description='A pretend payment provider for TaraBasa test payments (development tool).')
    ap.add_argument('--port', type=int, default=8125)
    ap.add_argument('--webhook', help='where to send the signed message after a payment, e.g. http://localhost:8123/api/webhooks/paymongo')
    ap.add_argument('--no-webhook', action='store_true', help='never send the message (the page the parent comes back to must then ask this provider itself)')
    ap.add_argument('--secret', default='whsk_pretend', help='the webhook secret (the same as PAYMONGO_WEBHOOK_SECRET)')
    args = ap.parse_args()
    CONFIG['secret'] = args.secret
    CONFIG['webhook'] = None if args.no_webhook else args.webhook
    if not args.no_webhook and not args.webhook:
        print('Tip: give --webhook <address> so TaraBasa gets the signed message, or --no-webhook to leave it out on purpose.')
    print(f'Pretend provider on http://127.0.0.1:{args.port}  (message to: {CONFIG["webhook"] or "nobody"})')
    ThreadingHTTPServer(('127.0.0.1', args.port), Handler).serve_forever()


if __name__ == '__main__':
    main()
