"""Helpers on top of diagram.Diagram so every figure is built the same way."""
from diagram import Diagram, C, text_w, wrap

OUT = "../out/figures"


class Flow:
    """Swimlane flow: lanes are columns, rows are steps. Nodes are placed by (lane, row)."""

    def __init__(self, name, title, subtitle, lanes, w=760, top=76, row_h=76, rows=10, pad_bottom=52, lane_gap=8,
                 margin=14, header=30, show_title=True, lane_tones=None):
        # lanes: list of (id, title, width_weight, tone)
        self.rows_n = rows
        self.top = top
        self.row_h = row_h
        self.header = header
        total_w = w - 2 * margin - lane_gap * (len(lanes) - 1)
        wsum = sum(l[2] for l in lanes)
        h = top + header + 18 + rows * row_h + pad_bottom
        self.d = Diagram(name, title, w, h, subtitle, show_title=show_title)
        self.lane_x = {}
        x = margin
        for lid, ltitle, wt, tone in lanes:
            lw = total_w * wt / wsum
            self.d.lane(lid, x, top, lw, h - top - 36, ltitle, tone, header=header, size=12)
            self.lane_x[lid] = (x, lw)
            x += lw + lane_gap
        self.y0 = top + header + 18

    def cx(self, lane):
        x, w = self.lane_x[lane]
        return x + w / 2

    def N(self, id, kind, lane, row, text, w=None, h=None, size=12, tone=None, dx=0, dy=0, rows=1):
        x, lw = self.lane_x[lane]
        if w is None:
            w = lw - 22
            if kind == "dec":
                w = lw - 8
            if kind == "term":
                w = min(w, 110)
        if h is None:
            h = 56 if kind in ("proc", "io", "ext", "sub") else (66 if kind == "dec" else 30)
            if rows > 1:
                h = h + (rows - 1) * self.row_h
        cx = x + lw / 2 + dx
        cy = self.y0 + row * self.row_h + (self.row_h - 18) / 2 - 4 + dy
        if rows > 1:
            cy += (rows - 1) * self.row_h / 2
        return self.d.node(id, kind, cx - w / 2, cy - h / 2, w, h, text, size=size, tone=tone)

    def E(self, a, b, exit="b", entry="t", label="", via=None, dashed=False, color="line", lx=None, ly=None):
        self.d.edge(a, b, exit, entry, label, via, dashed, color, lx, ly)

    def C(self, a, b, side="right", label="", dashed=False, margin=7, color="line", lbl_dy=-12, lane=None):
        """C shaped connector in the gutter on one side: used for loops and 'No' branches.
        With lane=<id> the line runs just inside that lane's edge so it never crosses a node."""
        n1, n2 = self.d.nodes[a], self.d.nodes[b]
        y1, y2 = n1["y"] + n1["h"] / 2, n2["y"] + n2["h"] / 2
        if lane:
            lx0, lw = self.lane_x[lane]
            x = lx0 + lw - 5 if side == "right" else lx0 + 5
        elif side == "right":
            x = max(n1["x"] + n1["w"], n2["x"] + n2["w"]) + margin
        else:
            x = min(n1["x"], n2["x"]) - margin
        lx = x - 22 if side == "right" else x + 34
        self.d.edge(a, b, side[0], side[0], label, [(x, y1), (x, y2)], dashed, color, lx=lx, ly=y1 + lbl_dy)

    def legend(self, items):
        self._legend = items

    def save(self, outdir=OUT):
        d = self.d
        maxy = max(n["y"] + n["h"] for n in d.nodes.values())
        lane_bottom = maxy + 26
        for L in d.lanes:
            L["h"] = lane_bottom - L["y"]
        d.h = int(lane_bottom + 52)
        if getattr(self, "_legend", None):
            d.legend(self._legend, 24, d.h - 14)
        return d.save(outdir)


def banner(d, x, y, w, text, tone="blue", size=11):
    """A flat label strip used to name a group of nodes."""
    d.node(f"bn_{x}_{y}", "pill", x, y, w, 20, text, size=size, tone=tone)


class Cols:
    """Branching columns: one lane per branch, each a vertical chain. Used for 'menu like' workflows.
    steps: (id, kind, text) or (id, kind, text, arrow_label)."""

    def __init__(self, name, title, subtitle, top_text, cols, bottom_text=None, w=760, margin=14, gap=8, size=12):
        n = len(cols)
        cw = (w - 2 * margin - gap * (n - 1)) / n
        node_w = cw - 20
        # measure heights
        heights = []
        for c in cols:
            hs = []
            for st in c["steps"]:
                kind, text = st[1], st[2]
                inner = node_w - 14 if kind != "dec" else node_w * 0.64
                lines = wrap(text, inner, size)
                hh = max(40, len(lines) * size * 1.25 + 16)
                if kind == "dec":
                    hh = max(62, len(lines) * size * 1.25 / 0.64 + 18)
                hs.append(hh)
            heights.append(hs)
        gapv = 24
        tallest = max(34 + gapv + sum(h) + gapv * (len(h) - 1) for h in heights)
        top_y = 76
        lane_y = top_y + 56
        lane_h = tallest + 36
        H = lane_y + lane_h + (74 if bottom_text else 52)
        self.d = Diagram(name, title, w, H, subtitle)
        d = self.d
        d.node("top", "term", w / 2 - 70, top_y, 140, 30, top_text, size=12)
        xs = []
        x = margin
        for c in cols:
            d.lane(f"l{len(xs)}", x, lane_y, cw, lane_h, c["title"], c.get("tone", "blue"), header=0)
            xs.append(x + cw / 2)
            x += cw + gap
        for i, c in enumerate(cols):
            cx = xs[i]
            d.node(f"h{i}", "box", cx - node_w / 2, lane_y + 12, node_w, 34, c["title"], size=size, tone=c.get("tone", "blue"))
            d.edge("top", f"h{i}", "b", "t", via=[(w / 2, lane_y - 12), (cx, lane_y - 12)] if abs(cx - w / 2) > 1 else None)
            prev = f"h{i}"
            y = lane_y + 12 + 34 + gapv
            last = None
            for st, hh in zip(c["steps"], heights[i]):
                sid, kind, text = st[0], st[1], st[2]
                lab = st[3] if len(st) > 3 else ""
                tone = c.get("step_tone", {}).get(sid)
                d.node(sid, kind, cx - node_w / 2, y, node_w, hh, text, size=size, tone=tone)
                d.edge(prev, sid, "b", "t", label=lab)
                prev = sid
                y += hh + gapv
                last = sid
            c["_last"] = last
        if bottom_text:
            by = lane_y + lane_h + 26
            d.node("bot", "term", w / 2 - 80, by, 160, 30, bottom_text, size=12)
            for i, c in enumerate(cols):
                d.edge(c["_last"], "bot", "b", "t", via=[(xs[i], lane_y + lane_h + 12), (w / 2, lane_y + lane_h + 12)] if abs(xs[i] - w / 2) > 1 else None)

    def save(self, outdir=OUT):
        return self.d.save(outdir)
