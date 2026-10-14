"""Turns the captured Teacher pages into self-contained documents for the phone preview.

Usage:  python build_teacher_preview.py <captured pages folder> <preview folder>
The viewer page (TaraBasa-Teacher-Phone-Preview.html) is copied next to the screens/ folder it creates.
"""
import base64
import mimetypes
import os
import re
import shutil
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.abspath(os.path.join(HERE, '..', '..'))
PUB = REPO + '/public'
SHOTS, OUT = sys.argv[1], sys.argv[2]
HOST = r'https?://localhost(?::8000)?'

# The pages are drawn inside a frame in the viewer. A tap on a link must not leave the page: it tells the viewer, which shows the
# matching screen. A button that would save something (a form, a request in the background) says it is preview only. The one
# request that works is opening an activity's window: the viewer hands back the page it saved for that activity.
BRIDGE = """<script>
(function () {
  function tell(m) { try { parent.postMessage(m, '*'); } catch (e) {} }
  var waiting = {}, n = 0;
  window.addEventListener('message', function (e) {
    var d = e.data;
    if (!d || d.tb !== 'fetched' || !waiting[d.id]) return;
    var w = waiting[d.id]; delete waiting[d.id];
    if (d.html == null) w.reject(new Error('not in the preview')); else w.resolve(new Response(d.html, { status: 200, headers: { 'Content-Type': 'text/html' } }));
  });
  var real = window.fetch;
  window.fetch = function (url, opts) {
    var u = String(url), method = ((opts && opts.method) || 'GET').toUpperCase();
    var win = u.match(/\\/teacher\\/activities\\/(\\d+)\\/window/);
    if (win) {
      return new Promise(function (resolve, reject) { var id = ++n; waiting[id] = { resolve: resolve, reject: reject }; tell({ tb: 'fetch', id: id, activity: win[1] }); });
    }
    if (method !== 'GET') { tell({ tb: 'form', action: u }); return new Promise(function () {}); }
    return new Promise(function () {}); // reads from the live site (status checks, warm-ups) are not part of the preview
  };
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a) return;
    var h = a.getAttribute('href');
    if (!h || h.charAt(0) === '#' || /^(mailto|tel):/.test(h)) return;
    e.preventDefault();
    tell({ tb: 'nav', href: h });
  }, true);
  document.addEventListener('submit', function (e) {
    e.preventDefault();
    var f = e.target;
    tell({ tb: 'form', action: (f.getAttribute && f.getAttribute('action')) || '' });
  }, true);
})();
</script>"""


def read(p):
    return open(p, encoding='utf-8').read()


def data_uri(rel):
    p = PUB + '/' + rel
    mime = mimetypes.guess_type(p)[0] or 'application/octet-stream'
    return 'data:%s;base64,%s' % (mime, base64.b64encode(open(p, 'rb').read()).decode())


def sprite():
    t = re.sub(r'<\?xml[^>]*\?>', '', read(PUB + '/icons/badges.svg')).strip()
    return t.replace('<svg', '<svg style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true"', 1)


SPRITE = sprite()


def prep(raw):
    s = raw
    # stylesheets from the app become inline styles
    s = re.sub(r'<link[^>]+href="%s/css/([\w.-]+?)\.css[^"]*"[^>]*>' % HOST,
               lambda m: '<style>' + read(PUB + '/css/' + m.group(1) + '.css') + '</style>', s)
    # the page's own script is inlined; the bridge goes first so its fetch is the one the page uses
    s = re.sub(r'<script[^>]+src="%s/js/([\w.-]+?)\.js[^"]*"[^>]*>\s*</script>' % HOST,
               lambda m: BRIDGE + '<script>' + read(PUB + '/js/' + m.group(1) + '.js').replace('</script', '<\\/script') + '</script>', s)
    # icons: the sheet is inlined once, so a reference keeps only the part after the #
    s = re.sub(r'%s/icons/badges\.svg' % HOST, '', s)
    s = re.sub(r'(<body[^>]*>)', lambda m: m.group(1) + SPRITE, s, count=1)
    # pictures
    s = re.sub(r'%s/images/([\w/.-]+)' % HOST, lambda m: data_uri('images/' + m.group(1)), s)
    s = re.sub(r'(<img\b[^>]*?)\s+src=""', r'\1', s)
    if 'tb:' not in s or BRIDGE not in s:
        s = s.replace('</body>', BRIDGE + '</body>')  # pages with no script of their own (sign up, log in) still report taps
    return s


def main():
    out_dir = os.path.join(OUT, 'screens')
    os.makedirs(out_dir, exist_ok=True)
    n = 0
    for name in sorted(os.listdir(SHOTS)):
        if not name.endswith('.html'):
            continue
        raw = read(os.path.join(SHOTS, name))
        if name.startswith('window-'):
            # a window is a fragment that is put into a page which already holds the icon sheet, so only the address is cut
            html = re.sub(r'%s/icons/badges\.svg' % HOST, '', raw)
            html = re.sub(r'%s/images/([\w/.-]+)' % HOST, lambda m: data_uri('images/' + m.group(1)), html)
        else:
            html = prep(raw)
        with open(os.path.join(out_dir, name), 'w', encoding='utf-8') as f:
            f.write(html)
        n += 1
    shutil.copyfile(os.path.join(HERE, 'TaraBasa-Teacher-Phone-Preview.html'), os.path.join(OUT, 'TaraBasa-Teacher-Phone-Preview.html'))
    leftovers = 0
    for name in os.listdir(out_dir):
        leftovers += len(re.findall(r'(?:href|src)="%s/(?:css|vendor|images|icons|js)/' % HOST, read(os.path.join(out_dir, name))))
    print('files:', n, '| leftover asset links:', leftovers)


main()
