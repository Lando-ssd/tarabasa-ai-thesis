"""Turns the captured pages into self-contained documents for the legal preview.

Usage:  python build_legal_preview.py <captured pages folder> <preview folder>
The viewer page (TaraBasa-Legal-Preview.html) is copied next to the screens/ folder it creates.
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

# A tap on a link or a button must not leave the page: it tells the viewer, which shows the matching screen (or says the
# action is not part of the preview).
BRIDGE = """<script>
(function () {
  function tell(m) { try { parent.postMessage(m, '*'); } catch (e) {} }
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

# Some pages draw animations with the lottie-web library. The preview does not load it, so a stand-in keeps their own scripts
# running instead of stopping on a missing name. The animations themselves are simply not drawn.
LOTTIE_STUB = """<script>
window.lottie = { loadAnimation: function () { var n = function () {}; return { addEventListener: n, removeEventListener: n, destroy: n, play: n, stop: n, pause: n, goToAndStop: n, goToAndPlay: n, setSpeed: n, setDirection: n }; } };
</script>"""

# Screens that start part way in: the add a child steps are one page with five steps, and the Generate window is a dialog.
PHOTO_STEP = "try { avatarMode = 'photo'; photoReady = true; showStep(5); } catch (e) {}"
VARIANTS = {
    'add-child-consent': ('add-child', "try { showStep(5); } catch (e) {}"),
    'add-child-photo': ('add-child', PHOTO_STEP),
    'teacher-generate': ('teacher-activities', "try { document.getElementById('genDlg').showModal(); } catch (e) {}"),
}
SOURCES_ONLY = {'add-child', 'teacher-activities'}


def read(p):
    return open(p, encoding='utf-8').read()


def data_uri(path):
    mime = mimetypes.guess_type(path)[0] or 'application/octet-stream'
    return 'data:%s;base64,%s' % (mime, base64.b64encode(open(path, 'rb').read()).decode())


def css(name):
    text = read(PUB + '/css/' + name + '.css')
    # fonts: ../fonts/file.woff2 becomes the file itself, inline
    return re.sub(r"url\((['\"]?)\.\./fonts/([\w.-]+)\1\)", lambda m: "url('%s')" % data_uri(PUB + '/fonts/' + m.group(2)), text)


def sprite():
    t = re.sub(r'<\?xml[^>]*\?>', '', read(PUB + '/icons/badges.svg')).strip()
    return t.replace('<svg', '<svg style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true"', 1)


SPRITE = sprite()


def prep(raw, extra=''):
    s = raw
    s = re.sub(r'<link[^>]+href="%s/css/([\w.-]+?)\.css[^"]*"[^>]*>' % HOST, lambda m: '<style>' + css(m.group(1)) + '</style>', s)
    # outside script libraries and the photo crop tool are not part of the preview
    s = re.sub(r'<script[^>]+src="%s/[^"]*"[^>]*>\s*</script>' % HOST, '', s)
    s = re.sub(r'<link[^>]+href="%s/vendor/[^"]*"[^>]*>' % HOST, '', s)
    s = re.sub(r'%s/icons/badges\.svg' % HOST, '', s)
    s = re.sub(r'(<body[^>]*>)', lambda m: m.group(1) + SPRITE, s, count=1)
    s = re.sub(r'%s/images/([\w/.-]+)' % HOST, lambda m: data_uri(PUB + '/images/' + m.group(1)), s)
    s = re.sub(r'(<img\b[^>]*?)\s+src=""', r'\1', s)
    s = s.replace('</head>', LOTTIE_STUB + '</head>', 1)
    s = s.replace('</body>', BRIDGE + ('<script>' + extra + '</script>' if extra else '') + '</body>')
    return s


def main():
    out_dir = os.path.join(OUT, 'screens')
    os.makedirs(out_dir, exist_ok=True)
    n = 0
    for name in sorted(os.listdir(SHOTS)):
        if not name.endswith('.html'):
            continue
        sid = name[:-5]
        if sid in SOURCES_ONLY:
            continue
        with open(os.path.join(out_dir, name), 'w', encoding='utf-8') as f:
            f.write(prep(read(os.path.join(SHOTS, name))))
        n += 1
    for sid, (source, script) in VARIANTS.items():
        with open(os.path.join(out_dir, sid + '.html'), 'w', encoding='utf-8') as f:
            f.write(prep(read(os.path.join(SHOTS, source + '.html')), script))
        n += 1
    shutil.copyfile(os.path.join(HERE, 'TaraBasa-Legal-Preview.html'), os.path.join(OUT, 'TaraBasa-Legal-Preview.html'))
    leftovers = 0
    for name in os.listdir(out_dir):
        leftovers += len(re.findall(r'(?:href|src)="%s/(?:css|vendor|images|icons|js|fonts)/' % HOST, read(os.path.join(out_dir, name))))
    total = sum(os.path.getsize(os.path.join(out_dir, f)) for f in os.listdir(out_dir))
    print('screens:', n, '| leftover asset links:', leftovers, '| total MB: %.1f' % (total / 1048576))


main()
