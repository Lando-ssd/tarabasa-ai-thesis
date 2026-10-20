"""
A small diagram engine: ONE description of a diagram becomes
  - an editable draw.io file (.drawio, open it at app.diagrams.net)
  - a vector picture (.svg)
  - a high resolution picture (.png, drawn with Arial so it looks like the .svg)
so the team can drop the picture into Word, or open the .drawio and change it.

Shapes: term (start/end), proc, dec (decision), io, sub (sub-process), db, ext (outside service),
        note, conn (off-page connector), box (heading box for trees), fn (small function box),
        uc (use case ellipse), actor (stick figure), ent (database table card), pill (small label).
"""
import html
import os
from PIL import Image, ImageDraw, ImageFont

FONT = "C:/Windows/Fonts/arial.ttf"
FONT_B = "C:/Windows/Fonts/arialbd.ttf"
_cache = {}


def _font(size, bold=False):
    key = (round(size * 4), bold)
    if key not in _cache:
        _cache[key] = ImageFont.truetype(FONT_B if bold else FONT, size)
    return _cache[key]


def text_w(s, size, bold=False):
    return _font(size, bold).getlength(s)


def wrap(text, width, size, bold=False):
    """Word wrap to a pixel width. An explicit newline starts a new line."""
    lines = []
    for para in str(text).split("\n"):
        words = para.split(" ")
        cur = ""
        for w in words:
            t = (cur + " " + w).strip()
            if text_w(t, size, bold) <= width or not cur:
                cur = t
            else:
                lines.append(cur)
                cur = w
        lines.append(cur)
    return lines


# ---- palette (restrained, print friendly) -------------------------------------------------------
C = {
    "ink": "#1F2937", "muted": "#5B6675", "line": "#44546A",
    "blue": "#0B5CAD", "blue_l": "#E8F1FB", "blue_s": "#7FA8D6",
    "teal": "#0F766E", "teal_l": "#E4F4F1", "teal_s": "#79BDB5",
    "orange": "#C2680C", "orange_l": "#FFF0DC", "orange_s": "#E8A04F",
    "green": "#1E8449", "green_l": "#E3F5E8", "green_s": "#6CBF86",
    "amber": "#B7791F", "amber_l": "#FFF3D6", "amber_s": "#E0B04A",
    "red": "#B42318", "red_l": "#FDE8E6", "red_s": "#E58B84",
    "gray": "#4B5563", "gray_l": "#F3F5F8", "gray_s": "#B8C0CC", "white": "#FFFFFF",
    "violet": "#5B3FA6", "violet_l": "#EEE9FA", "violet_s": "#A894DA",
}

# kind: (fill, stroke, text colour, bold)
KIND_STYLE = {
    "term": ("blue", "blue", "white", True),
    "proc": ("blue_l", "blue_s", "ink", False),
    "dec": ("amber_l", "amber_s", "ink", False),
    "io": ("teal_l", "teal_s", "ink", False),
    "sub": ("violet_l", "violet_s", "ink", False),
    "db": ("gray_l", "line", "ink", False),
    "ext": ("orange_l", "orange_s", "ink", False),
    "note": ("white", "white", "muted", False),
    "conn": ("white", "line", "ink", True),
    "box": ("blue_l", "blue_s", "ink", True),
    "fn": ("blue_l", "blue_s", "ink", False),
    "uc": ("white", "line", "ink", False),
    "actor": ("white", "white", "ink", True),
    "ent": ("white", "line", "ink", False),
    "pill": ("gray_l", "gray_s", "muted", False),
}


def _rgb(h):
    h = h.lstrip("#")
    return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4))


def _blend(h, a):
    r, g, b = _rgb(h)
    return (int(r * a + 255 * (1 - a)), int(g * a + 255 * (1 - a)), int(b * a + 255 * (1 - a)))


class Diagram:
    def __init__(self, name, title, w, h, subtitle="", show_title=True):
        self.name, self.title, self.subtitle = name, title, subtitle
        self.show_title = show_title
        self.w, self.h = w, h
        self.nodes, self.edges, self.lanes, self.texts, self.lines = {}, [], [], [], []
        self.legend_items = None
        self.order = []
        self.warn = []

    # ---- building ----
    def lane(self, id, x, y, w, h, title, tone="blue", header=30, size=13, align="center"):
        self.lanes.append(dict(id=id, x=x, y=y, w=w, h=h, title=title, tone=tone, header=header, size=size, align=align))

    def node(self, id, kind, x, y, w, h, text, size=12, tone=None, bold=None, dashed=False, lines=None, align="center"):
        fill, stroke, tcol, b = KIND_STYLE[kind]
        if tone:
            fill, stroke = tone + "_l", tone + "_s"
            if kind == "term":
                fill, stroke, tcol = tone, tone, "white"
        if bold is not None:
            b = bold
        n = dict(id=id, kind=kind, x=x, y=y, w=w, h=h, text=text, size=size, fill=C[fill], stroke=C[stroke],
                 tcol=C[tcol], bold=b, dashed=dashed or kind == "ext", rows=lines or [], align=align)
        inner = w - 14
        if kind == "dec":
            inner = w * 0.64
        if kind == "io":
            inner = w - 36
        if kind == "uc":
            inner = w * 0.80
        if kind == "ent":
            inner = w - 12
        lines_ = wrap(text, inner, size, b) if text else []
        need = len(lines_) * size * 1.25 + 6
        avail = h * (0.64 if kind == "dec" else (0.8 if kind == "uc" else 1))
        if kind == "ent":
            need, avail = 0, 1
        if need > avail + 1 and kind not in ("note", "actor"):
            self.warn.append(f"{self.name}:{id} text needs {need:.0f}px but has {avail:.0f}px -> '{text[:40]}'")
        n["lines"] = lines_
        self.nodes[id] = n
        self.order.append(id)
        return n

    def text(self, x, y, s, size=12, bold=False, color="ink", anchor="start"):
        self.texts.append(dict(x=x, y=y, s=s, size=size, bold=bold, color=C[color], anchor=anchor))

    def line(self, pts, color="line", width=1.4, dashed=False):
        """A plain line (no arrow, not attached to a shape): used for tree connectors."""
        self.lines.append(dict(pts=[tuple(p) for p in pts], color=color, width=width, dashed=dashed))

    def edge(self, a, b, exit="b", entry="t", label="", via=None, dashed=False, color="line", lx=None, ly=None, bi=False, arrow=True, eo=0.5, no=0.5):
        self.edges.append(dict(a=a, b=b, exit=exit, entry=entry, label=label, via=via or [], dashed=dashed,
                               color=color, lx=lx, ly=ly, bi=bi, arrow=arrow, eo=eo, no=no))

    def legend(self, items, x, y):
        self.legend_items = (x, y, items)

    # ---- geometry ----
    def port(self, nid, side, off=0.5):
        n = self.nodes[nid]
        x, y, w, h = n["x"], n["y"], n["w"], n["h"]
        if side == "t":
            return (x + w * off, y)
        if side == "b":
            return (x + w * off, y + h)
        if side == "l":
            return (x, y + h * off)
        return (x + w, y + h * off)

    def route(self, e):
        a, b = e["a"], e["b"]
        p0 = self.port(a, e["exit"], e.get("eo", 0.5))
        p1 = self.port(b, e["entry"], e.get("no", 0.5))
        if e["via"]:
            return [p0] + [tuple(p) for p in e["via"]] + [p1]
        ex, en = e["exit"], e["entry"]
        (x0, y0), (x1, y1) = p0, p1
        if abs(x0 - x1) < 1 and ex in "bt" and en in "bt":
            return [p0, p1]
        if abs(y0 - y1) < 1 and ex in "lr" and en in "lr":
            return [p0, p1]
        if ex in "bt" and en in "bt":
            my = (y0 + y1) / 2
            return [p0, (x0, my), (x1, my), p1]
        if ex in "lr" and en in "lr":
            mx = (x0 + x1) / 2
            return [p0, (mx, y0), (mx, y1), p1]
        if ex in "bt" and en in "lr":
            return [p0, (x0, y1), p1]
        return [p0, (x1, y0), p1]

    def _label_pos(self, pts, e):
        if e["lx"] is not None:
            return e["lx"], e["ly"]
        best, bl = None, -1
        for i in range(len(pts) - 1):
            (x0, y0), (x1, y1) = pts[i], pts[i + 1]
            L = abs(x1 - x0) + abs(y1 - y0)
            if L > bl:
                bl, best = L, ((x0 + x1) / 2, (y0 + y1) / 2)
        return best

    # ================================================================ SVG
    def svg(self):
        o = []
        W, H = self.w, self.h
        o.append(f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}" '
                 f'font-family="Arial, Helvetica, sans-serif">')
        o.append(f'<rect x="0" y="0" width="{W}" height="{H}" fill="#FFFFFF"/>')
        if self.show_title:
            o.append(f'<text x="24" y="30" font-size="19" font-weight="bold" fill="{C["blue"]}">{html.escape(self.title)}</text>')
            for si, sl in enumerate(wrap(self.subtitle, self.w - 48, 12) if self.subtitle else []):
                o.append(f'<text x="24" y="{50 + si * 15}" font-size="12" fill="{C["muted"]}">{html.escape(sl)}</text>')
        for L in self.lanes:
            tone = L["tone"]
            o.append(f'<rect x="{L["x"]}" y="{L["y"]}" width="{L["w"]}" height="{L["h"]}" rx="8" fill="{C[tone + "_l"]}" '
                     f'fill-opacity="0.55" stroke="{C[tone + "_s"]}" stroke-width="1.2"/>')
            if L["header"]:
                o.append(f'<rect x="{L["x"]}" y="{L["y"]}" width="{L["w"]}" height="{L["header"]}" rx="8" fill="{C[tone + "_s"]}" fill-opacity="0.55"/>')
                ax = {"center": L["x"] + L["w"] / 2, "left": L["x"] + 12, "right": L["x"] + L["w"] - 12}[L.get("align", "center")]
                an = {"center": "middle", "left": "start", "right": "end"}[L.get("align", "center")]
                o.append(f'<text x="{ax}" y="{L["y"] + L["header"] / 2 + L["size"] * 0.36}" font-size="{L["size"]}" font-weight="bold" text-anchor="{an}" '
                         f'fill="{C["ink"]}">{html.escape(L["title"])}</text>')
        for ln in self.lines:
            d = " ".join(f"{x:.1f},{y:.1f}" for x, y in ln["pts"])
            dash = ' stroke-dasharray="6 4"' if ln["dashed"] else ""
            o.append(f'<polyline points="{d}" fill="none" stroke="{C.get(ln["color"], ln["color"])}" stroke-width="{ln["width"]}"{dash}/>')
        for e in self.edges:
            pts = self.route(e)
            d = " ".join(f"{x:.1f},{y:.1f}" for x, y in pts)
            dash = ' stroke-dasharray="6 4"' if e["dashed"] else ""
            col = C.get(e["color"], e["color"])
            o.append(f'<polyline points="{d}" fill="none" stroke="{col}" stroke-width="1.6"{dash}/>')
            if e["arrow"]:
                o.append(self._arrow(pts[-2], pts[-1], col))
            if e["bi"]:
                o.append(self._arrow(pts[1], pts[0], col))
            if e["label"]:
                lx, ly = self._label_pos(pts, e)
                tw = text_w(e["label"], 11, True) + 8
                o.append(f'<rect x="{lx - tw / 2:.1f}" y="{ly - 9:.1f}" width="{tw:.1f}" height="16" rx="3" fill="#FFFFFF" fill-opacity="0.92"/>')
                o.append(f'<text x="{lx:.1f}" y="{ly + 3.5:.1f}" font-size="11" font-weight="bold" text-anchor="middle" fill="{col}">{html.escape(e["label"])}</text>')
        for nid in self.order:
            o.append(self._node_svg(self.nodes[nid]))
        for t in self.texts:
            bold = ' font-weight="bold"' if t["bold"] else ""
            o.append(f'<text x="{t["x"]}" y="{t["y"]}" font-size="{t["size"]}"{bold} fill="{t["color"]}" '
                     f'text-anchor="{t["anchor"]}">{html.escape(t["s"])}</text>')
        if self.legend_items:
            x, y, items = self.legend_items
            o.append(f'<text x="{x}" y="{y}" font-size="11" font-weight="bold" fill="{C["muted"]}">LEGEND</text>')
            cx = x + 56
            for tone, label in items:
                o.append(f'<rect x="{cx}" y="{y - 11}" width="16" height="12" rx="3" fill="{C[tone + "_l"]}" stroke="{C[tone + "_s"]}" stroke-width="1.4"/>')
                o.append(f'<text x="{cx + 22}" y="{y - 1}" font-size="11" fill="{C["ink"]}">{html.escape(label)}</text>')
                cx += 22 + text_w(label, 11) + 22
        o.append("</svg>")
        return "\n".join(o)

    def _arrow(self, p, q, col):
        (x0, y0), (x1, y1) = p, q
        dx, dy = x1 - x0, y1 - y0
        L = (dx * dx + dy * dy) ** 0.5 or 1
        ux, uy = dx / L, dy / L
        s = 8
        bx, by = x1 - ux * s, y1 - uy * s
        px, py = -uy * s * 0.5, ux * s * 0.5
        return f'<polygon points="{x1:.1f},{y1:.1f} {bx + px:.1f},{by + py:.1f} {bx - px:.1f},{by - py:.1f}" fill="{col}"/>'

    def _lines_svg(self, n, cx, cy, top=None):
        size = n["size"]
        lh = size * 1.25
        lines = n["lines"]
        total = lh * len(lines)
        y0 = (cy - total / 2 + lh * 0.78) if top is None else top + lh * 0.78
        weight = ' font-weight="bold"' if n["bold"] else ""
        out = []
        for i, ln in enumerate(lines):
            out.append(f'<text x="{cx:.1f}" y="{y0 + i * lh:.1f}" font-size="{size}"{weight} text-anchor="middle" '
                       f'fill="{n["tcol"]}">{html.escape(ln)}</text>')
        return "".join(out)

    def _node_svg(self, n):
        x, y, w, h, k = n["x"], n["y"], n["w"], n["h"], n["kind"]
        f, s = n["fill"], n["stroke"]
        dash = ' stroke-dasharray="6 4"' if n["dashed"] else ""
        cx, cy = x + w / 2, y + h / 2
        g = []
        if k == "term":
            g.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="{h / 2}" fill="{f}" stroke="{s}" stroke-width="1.6"/>')
        elif k == "dec":
            g.append(f'<polygon points="{cx},{y} {x + w},{cy} {cx},{y + h} {x},{cy}" fill="{f}" stroke="{s}" stroke-width="1.6"/>')
        elif k == "io":
            sk = 16
            g.append(f'<polygon points="{x + sk},{y} {x + w},{y} {x + w - sk},{y + h} {x},{y + h}" fill="{f}" stroke="{s}" stroke-width="1.6"/>')
        elif k == "sub":
            g.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="3" fill="{f}" stroke="{s}" stroke-width="1.6"/>')
            g.append(f'<line x1="{x + 9}" y1="{y}" x2="{x + 9}" y2="{y + h}" stroke="{s}" stroke-width="1.4"/>')
            g.append(f'<line x1="{x + w - 9}" y1="{y}" x2="{x + w - 9}" y2="{y + h}" stroke="{s}" stroke-width="1.4"/>')
        elif k == "db":
            ry = 8
            g.append(f'<path d="M{x},{y + ry} a{w / 2},{ry} 0 0 1 {w},0 v{h - 2 * ry} a{w / 2},{ry} 0 0 1 -{w},0 z" fill="{f}" stroke="{s}" stroke-width="1.6"/>')
            g.append(f'<path d="M{x},{y + ry} a{w / 2},{ry} 0 0 0 {w},0" fill="none" stroke="{s}" stroke-width="1.4"/>')
        elif k in ("conn", "uc"):
            g.append(f'<ellipse cx="{cx}" cy="{cy}" rx="{w / 2}" ry="{h / 2}" fill="{f}" stroke="{s}" stroke-width="1.5"/>')
        elif k == "actor":
            hx, hy = cx, y + 9
            g.append(f'<circle cx="{hx}" cy="{hy}" r="8" fill="#FFFFFF" stroke="{C["line"]}" stroke-width="1.6"/>')
            g.append(f'<polyline points="{hx},{hy + 8} {hx},{hy + 32} " fill="none" stroke="{C["line"]}" stroke-width="1.6"/>')
            g.append(f'<polyline points="{hx - 14},{hy + 18} {hx + 14},{hy + 18}" fill="none" stroke="{C["line"]}" stroke-width="1.6"/>')
            g.append(f'<polyline points="{hx - 12},{hy + 50} {hx},{hy + 32} {hx + 12},{hy + 50}" fill="none" stroke="{C["line"]}" stroke-width="1.6"/>')
            g.append(self._lines_svg(n, cx, cy, top=y + 62))
            return "".join(g)
        elif k == "ent":
            g.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="4" fill="#FFFFFF" stroke="{s}" stroke-width="1.5"/>')
            g.append(f'<rect x="{x}" y="{y}" width="{w}" height="22" rx="4" fill="{n["fill"] if n["fill"] != C["white"] else C["blue_l"]}" stroke="{s}" stroke-width="1.5"/>')
            g.append(f'<text x="{cx}" y="{y + 15}" font-size="{n["size"] + 1}" font-weight="bold" text-anchor="middle" fill="{C["ink"]}">{html.escape(n["text"])}</text>')
            for i, r in enumerate(n["rows"]):
                ry = y + 22 + 13 + i * 13
                bold = ' font-weight="bold"' if r.endswith(" PK") else ""
                col = C["red"] if " FK" in r else C["ink"]
                g.append(f'<text x="{x + 6}" y="{ry}" font-size="{n["size"] - 1}"{bold} fill="{col}">{html.escape(r)}</text>')
            return "".join(g)
        elif k == "note":
            pass
        else:
            rx = 7 if k != "fn" else 6
            g.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="{rx}" fill="{f}" stroke="{s}" stroke-width="1.5"{dash}/>')
        g.append(self._lines_svg(n, cx, cy))
        return "".join(g)

    # ================================================================ draw.io
    def drawio(self):
        cells = ['<mxCell id="0"/>', '<mxCell id="1" parent="0"/>']
        i = 2
        idmap = {}

        def esc(s):
            return html.escape(str(s), quote=True)

        if self.show_title:
            cells.append(f'<mxCell id="t{i}" value="{esc(self.title)}" style="text;html=1;align=left;verticalAlign=middle;fontSize=19;fontStyle=1;fontColor={C["blue"]};" vertex="1" parent="1"><mxGeometry x="24" y="10" width="{self.w - 48}" height="28" as="geometry"/></mxCell>')
            i += 1
            if self.subtitle:
                nl = len(wrap(self.subtitle, self.w - 48, 12))
                cells.append(f'<mxCell id="t{i}" value="{esc(self.subtitle)}" style="text;html=1;whiteSpace=wrap;align=left;verticalAlign=top;fontSize=12;fontColor={C["muted"]};" vertex="1" parent="1"><mxGeometry x="24" y="38" width="{self.w - 48}" height="{15 * nl + 6}" as="geometry"/></mxCell>')
                i += 1
        for L in self.lanes:
            tone = L["tone"]
            st = (f"rounded=1;arcSize=2;whiteSpace=wrap;html=1;verticalAlign=top;align={L.get('align', 'center')};spacingLeft=12;spacingRight=12;fontStyle=1;fontSize={L['size']};spacingTop=6;"
                  f"fillColor={C[tone + '_l']};strokeColor={C[tone + '_s']};fontColor={C['ink']};opacity=70;")
            cells.append(f'<mxCell id="l{i}" value="{esc(L["title"] if L["header"] else "")}" style="{st}" vertex="1" parent="1"><mxGeometry x="{L["x"]}" y="{L["y"]}" width="{L["w"]}" height="{L["h"]}" as="geometry"/></mxCell>')
            i += 1
        base_styles = {
            "term": "rounded=1;arcSize=50;",
            "proc": "rounded=1;arcSize=12;",
            "box": "rounded=1;arcSize=12;",
            "fn": "rounded=1;arcSize=14;",
            "pill": "rounded=1;arcSize=50;",
            "ext": "rounded=1;arcSize=12;dashed=1;",
            "dec": "rhombus;",
            "io": "shape=parallelogram;perimeter=parallelogramPerimeter;fixedSize=1;size=16;",
            "sub": "shape=process;backgroundOutline=1;size=0.08;",
            "db": "shape=cylinder3;boundedLbl=1;backgroundOutline=1;size=8;",
            "conn": "ellipse;",
            "uc": "ellipse;",
            "note": "text;strokeColor=none;fillColor=none;",
            "actor": "shape=umlActor;verticalLabelPosition=bottom;verticalAlign=top;",
            "ent": "swimlane;fontStyle=1;childLayout=stackLayout;horizontal=1;startSize=22;horizontalStack=0;resizeParent=1;resizeParentMax=0;resizeLast=0;collapsible=0;marginBottom=0;align=center;",
        }
        for nid in self.order:
            n = self.nodes[nid]
            idmap[nid] = f"n{i}"
            fs = n["size"]
            fill = n["fill"] if not (n["kind"] == "ent" and n["fill"] == C["white"]) else C["blue_l"]
            style = (f"{base_styles[n['kind']]}whiteSpace=wrap;html=1;fontSize={fs};fontStyle={1 if n['bold'] else 0};"
                     f"fillColor={fill};strokeColor={n['stroke']};fontColor={n['tcol']};strokeWidth=1.6;")
            if n["dashed"] and n["kind"] != "ext":
                style += "dashed=1;"
            if n["kind"] == "ent":
                txt = esc(n["text"])
                cells.append(f'<mxCell id="{idmap[nid]}" value="{txt}" style="{style}" vertex="1" parent="1"><mxGeometry x="{n["x"]}" y="{n["y"]}" width="{n["w"]}" height="{n["h"]}" as="geometry"/></mxCell>')
                for ri, r in enumerate(n["rows"]):
                    col = C["red"] if " FK" in r else C["ink"]
                    fstyle = 1 if r.endswith(" PK") else 0
                    cells.append(f'<mxCell id="{idmap[nid]}r{ri}" value="{esc(r)}" style="text;strokeColor=none;fillColor=#FFFFFF;align=left;verticalAlign=middle;spacingLeft=4;spacingRight=4;overflow=hidden;rotatable=0;fontSize={fs - 1};fontStyle={fstyle};fontColor={col};whiteSpace=wrap;html=1;" vertex="1" parent="{idmap[nid]}"><mxGeometry y="{22 + ri * 13}" width="{n["w"]}" height="13" as="geometry"/></mxCell>')
                i += 1
                continue
            txt = esc(n["text"]).replace("\n", "&lt;br&gt;")
            cells.append(f'<mxCell id="{idmap[nid]}" value="{txt}" style="{style}" vertex="1" parent="1"><mxGeometry x="{n["x"]}" y="{n["y"]}" width="{n["w"]}" height="{n["h"]}" as="geometry"/></mxCell>')
            i += 1
        for t in self.texts:
            align = {"start": "left", "middle": "center", "end": "right"}[t["anchor"]]
            w = max(60, text_w(t["s"], t["size"], t["bold"]) + 10)
            x = t["x"] if t["anchor"] == "start" else (t["x"] - w / 2 if t["anchor"] == "middle" else t["x"] - w)
            cells.append(f'<mxCell id="x{i}" value="{esc(t["s"])}" style="text;html=1;align={align};verticalAlign=middle;fontSize={t["size"]};fontStyle={1 if t["bold"] else 0};fontColor={t["color"]};" vertex="1" parent="1"><mxGeometry x="{x}" y="{t["y"] - t["size"]}" width="{w}" height="{t["size"] + 8}" as="geometry"/></mxCell>')
            i += 1
        for ln in self.lines:
            pts = ln["pts"]
            col = C.get(ln["color"], ln["color"])
            style = f"edgeStyle=none;html=1;endArrow=none;startArrow=none;strokeColor={col};strokeWidth={ln['width']};" + ("dashed=1;" if ln["dashed"] else "")
            mid = "".join(f'<mxPoint x="{px:.1f}" y="{py:.1f}"/>' for px, py in pts[1:-1])
            arr = f'<Array as="points">{mid}</Array>' if mid else ""
            cells.append(f'<mxCell id="ln{i}" value="" style="{style}" edge="1" parent="1"><mxGeometry relative="1" as="geometry"><mxPoint x="{pts[0][0]:.1f}" y="{pts[0][1]:.1f}" as="sourcePoint"/><mxPoint x="{pts[-1][0]:.1f}" y="{pts[-1][1]:.1f}" as="targetPoint"/>{arr}</mxGeometry></mxCell>')
            i += 1
        for e in self.edges:
            pts = self.route(e)
            col = C.get(e["color"], e["color"])
            eo, no = e.get("eo", 0.5), e.get("no", 0.5)
            sx = {"l": 0, "r": 1}.get(e["exit"], eo)
            sy = {"t": 0, "b": 1}.get(e["exit"], eo)
            tx = {"l": 0, "r": 1}.get(e["entry"], no)
            ty = {"t": 0, "b": 1}.get(e["entry"], no)
            start = "startArrow=block;startFill=1;" if e["bi"] else ""
            end = "endArrow=block;endFill=1;" if e["arrow"] else "endArrow=none;"
            style = (f"edgeStyle=orthogonalEdgeStyle;rounded=0;html=1;{end}{start}"
                     f"strokeColor={col};strokeWidth=1.6;fontSize=11;fontStyle=1;fontColor={col};labelBackgroundColor=#FFFFFF;"
                     f"exitX={sx};exitY={sy};exitDx=0;exitDy=0;entryX={tx};entryY={ty};entryDx=0;entryDy=0;")
            if e["dashed"]:
                style += "dashed=1;"
            arr = "".join(f'<mxPoint x="{px:.1f}" y="{py:.1f}"/>' for px, py in pts[1:-1])
            inner = f'<Array as="points">{arr}</Array>' if arr else ""
            cells.append(f'<mxCell id="e{i}" value="{esc(e["label"])}" style="{style}" edge="1" parent="1" source="{idmap[e["a"]]}" target="{idmap[e["b"]]}"><mxGeometry relative="1" as="geometry">{inner}</mxGeometry></mxCell>')
            i += 1
        xml = (f'<mxfile host="app.diagrams.net" agent="TaraBasa AI" version="24.0.0"><diagram id="{esc(self.name)}" name="{esc(self.title[:30])}">'
               f'<mxGraphModel dx="1400" dy="900" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="1" '
               f'pageScale="1" pageWidth="{self.w}" pageHeight="{self.h}" math="0" shadow="0"><root>{"".join(cells)}</root></mxGraphModel></diagram></mxfile>')
        return xml

    # ================================================================ PNG (Pillow, Arial)
    def png(self, path, scale=3.0):
        S = scale
        img = Image.new("RGB", (int(self.w * S), int(self.h * S)), (255, 255, 255))
        d = ImageDraw.Draw(img)

        def P(v):
            return v * S

        def txt(x, y, s, size, bold=False, fill="#1F2937", anchor="ms"):
            d.text((P(x), P(y)), s, font=_font(size * S, bold), fill=_rgb(fill) if isinstance(fill, str) else fill, anchor=anchor)

        def poly(pts, fill=None, outline=None, width=1.6):
            pp = [(P(x), P(y)) for x, y in pts]
            if fill:
                d.polygon(pp, fill=fill)
            if outline:
                d.line(pp + [pp[0]], fill=outline, width=max(1, int(width * S)), joint="curve")

        def rrect(x, y, w, h, r, fill=None, outline=None, width=1.6):
            d.rounded_rectangle((P(x), P(y), P(x + w), P(y + h)), radius=P(r), fill=fill, outline=outline, width=max(1, int(width * S)))

        def pline(pts, color, width, dashed=False):
            pp = [(P(x), P(y)) for x, y in pts]
            if not dashed:
                d.line(pp, fill=color, width=max(1, int(width * S)), joint="curve")
                return
            dash, gap = 6 * S, 4 * S
            for a, b in zip(pp, pp[1:]):
                dx, dy = b[0] - a[0], b[1] - a[1]
                L = (dx * dx + dy * dy) ** 0.5
                if L == 0:
                    continue
                ux, uy = dx / L, dy / L
                t = 0
                while t < L:
                    t2 = min(t + dash, L)
                    d.line((a[0] + ux * t, a[1] + uy * t, a[0] + ux * t2, a[1] + uy * t2), fill=color, width=max(1, int(width * S)))
                    t += dash + gap

        def arrow(p, q, color):
            (x0, y0), (x1, y1) = p, q
            dx, dy = x1 - x0, y1 - y0
            L = (dx * dx + dy * dy) ** 0.5 or 1
            ux, uy = dx / L, dy / L
            s = 8
            bx, by = x1 - ux * s, y1 - uy * s
            px, py = -uy * s * 0.5, ux * s * 0.5
            d.polygon([(P(x1), P(y1)), (P(bx + px), P(by + py)), (P(bx - px), P(by - py))], fill=color)

        if self.show_title:
            txt(24, 30, self.title, 19, True, C["blue"], "ls")
            for si, sl in enumerate(wrap(self.subtitle, self.w - 48, 12) if self.subtitle else []):
                txt(24, 50 + si * 15, sl, 12, False, C["muted"], "ls")
        for L in self.lanes:
            tone = L["tone"]
            rrect(L["x"], L["y"], L["w"], L["h"], 8, fill=_blend(C[tone + "_l"], 0.55), outline=_rgb(C[tone + "_s"]), width=1.2)
            if L["header"]:
                rrect(L["x"], L["y"], L["w"], L["header"], 8, fill=_blend(C[tone + "_s"], 0.55))
                al = L.get("align", "center")
                ax = {"center": L["x"] + L["w"] / 2, "left": L["x"] + 12, "right": L["x"] + L["w"] - 12}[al]
                txt(ax, L["y"] + L["header"] / 2 + L["size"] * 0.36, L["title"], L["size"], True, C["ink"], {"center": "ms", "left": "ls", "right": "rs"}[al])
        for ln in self.lines:
            pline(ln["pts"], _rgb(C.get(ln["color"], ln["color"])), ln["width"], ln["dashed"])
        for e in self.edges:
            pts = self.route(e)
            col = _rgb(C.get(e["color"], e["color"]))
            pline(pts, col, 1.6, e["dashed"])
            if e["arrow"]:
                arrow(pts[-2], pts[-1], col)
            if e["bi"]:
                arrow(pts[1], pts[0], col)
            if e["label"]:
                lx, ly = self._label_pos(pts, e)
                tw = text_w(e["label"], 11, True) + 8
                d.rounded_rectangle((P(lx - tw / 2), P(ly - 9), P(lx + tw / 2), P(ly + 7)), radius=P(3), fill=(255, 255, 255))
                txt(lx, ly + 3.5, e["label"], 11, True, C.get(e["color"], e["color"]), "ms")
        for nid in self.order:
            self._node_png(self.nodes[nid], d, P, txt, poly, rrect, pline, S)
        for t in self.texts:
            anchor = {"start": "ls", "middle": "ms", "end": "rs"}[t["anchor"]]
            txt(t["x"], t["y"], t["s"], t["size"], t["bold"], t["color"], anchor)
        if self.legend_items:
            x, y, items = self.legend_items
            txt(x, y, "LEGEND", 11, True, C["muted"], "ls")
            cx = x + 56
            for tone, label in items:
                rrect(cx, y - 11, 16, 12, 3, fill=_rgb(C[tone + "_l"]), outline=_rgb(C[tone + "_s"]), width=1.4)
                txt(cx + 22, y - 1, label, 11, False, C["ink"], "ls")
                cx += 22 + text_w(label, 11) + 22
        img.save(path, dpi=(300, 300))

    def _node_png(self, n, d, P, txt, poly, rrect, pline, S):
        x, y, w, h, k = n["x"], n["y"], n["w"], n["h"], n["kind"]
        f, s = _rgb(n["fill"]), _rgb(n["stroke"])
        cx, cy = x + w / 2, y + h / 2
        lw = 1.6

        def lines(top=None):
            size = n["size"]
            lh = size * 1.25
            ls = n["lines"]
            total = lh * len(ls)
            y0 = (cy - total / 2 + lh * 0.78) if top is None else top + lh * 0.78
            for i, ln in enumerate(ls):
                txt(cx, y0 + i * lh, ln, size, n["bold"], n["tcol"], "ms")

        if k == "term":
            rrect(x, y, w, h, h / 2, fill=f, outline=s, width=lw)
        elif k == "dec":
            poly([(cx, y), (x + w, cy), (cx, y + h), (x, cy)], fill=f, outline=s)
        elif k == "io":
            poly([(x + 16, y), (x + w, y), (x + w - 16, y + h), (x, y + h)], fill=f, outline=s)
        elif k == "sub":
            rrect(x, y, w, h, 3, fill=f, outline=s, width=lw)
            pline([(x + 9, y), (x + 9, y + h)], s, 1.4)
            pline([(x + w - 9, y), (x + w - 9, y + h)], s, 1.4)
        elif k == "db":
            ry = 8
            d.rectangle((P(x), P(y + ry), P(x + w), P(y + h - ry)), fill=f)
            d.ellipse((P(x), P(y), P(x + w), P(y + 2 * ry)), fill=f, outline=s, width=max(1, int(lw * S)))
            d.ellipse((P(x), P(y + h - 2 * ry), P(x + w), P(y + h)), fill=f, outline=s, width=max(1, int(lw * S)))
            d.rectangle((P(x) + 2, P(y + ry), P(x + w) - 2, P(y + h - ry)), fill=f)
            pline([(x, y + ry), (x, y + h - ry)], s, lw)
            pline([(x + w, y + ry), (x + w, y + h - ry)], s, lw)
            d.arc((P(x), P(y), P(x + w), P(y + 2 * ry)), 0, 180, fill=s, width=max(1, int(lw * S)))
        elif k in ("conn", "uc"):
            d.ellipse((P(x), P(y), P(x + w), P(y + h)), fill=f, outline=s, width=max(1, int(lw * S)))
        elif k == "actor":
            hx, hy = cx, y + 9
            d.ellipse((P(hx - 8), P(hy - 8), P(hx + 8), P(hy + 8)), fill=(255, 255, 255), outline=_rgb(C["line"]), width=max(1, int(lw * S)))
            for seg in ([(hx, hy + 8), (hx, hy + 32)], [(hx - 14, hy + 18), (hx + 14, hy + 18)], [(hx - 12, hy + 50), (hx, hy + 32), (hx + 12, hy + 50)]):
                pline(seg, _rgb(C["line"]), lw)
            lines(top=y + 62)
            return
        elif k == "ent":
            head = f if n["fill"] != C["white"] else _rgb(C["blue_l"])
            rrect(x, y, w, h, 4, fill=(255, 255, 255), outline=s, width=1.5)
            rrect(x, y, w, 22, 4, fill=head, outline=s, width=1.5)
            txt(cx, y + 15, n["text"], n["size"] + 1, True, C["ink"], "ms")
            for i, r in enumerate(n["rows"]):
                ry = y + 22 + 13 + i * 13
                bold = r.endswith(" PK")
                col = C["red"] if " FK" in r else C["ink"]
                txt(x + 6, ry, r, n["size"] - 1, bold, col, "ls")
            return
        elif k == "note":
            pass
        else:
            if n["dashed"]:
                rrect(x, y, w, h, 7, fill=f)
                # dashed outline
                r = 7
                pts = [(x + r, y), (x + w - r, y), (x + w, y + r), (x + w, y + h - r), (x + w - r, y + h), (x + r, y + h), (x, y + h - r), (x, y + r), (x + r, y)]
                pline(pts, s, lw, dashed=True)
            else:
                rrect(x, y, w, h, 7 if k != "fn" else 6, fill=f, outline=s, width=1.5)
        lines()

    # ================================================================ save
    def save(self, outdir, png_scale=3.0, formats=("svg", "drawio", "png")):
        os.makedirs(outdir, exist_ok=True)
        base = os.path.join(outdir, self.name)
        if "svg" in formats:
            with open(base + ".svg", "w", encoding="utf-8") as f:
                f.write(self.svg())
        if "drawio" in formats:
            with open(base + ".drawio", "w", encoding="utf-8") as f:
                f.write(self.drawio())
        if "png" in formats:
            self.png(base + ".png", png_scale)
        return self.warn
