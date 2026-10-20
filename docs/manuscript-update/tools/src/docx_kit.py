"""Word helpers. Everything the writer will paste into the manuscript is formatted exactly like the manuscript:
Letter paper, margins left 1.5 in and right, top, bottom 1 in; Times New Roman 11 pt, justified, 1.5 line spacing,
first line indent 0.5 in, no space before or after; Heading 1 TNR 14 bold centered, Heading 2 TNR 12 bold centered,
Heading 3 TNR 11 bold left with 10 pt before; table caption centered above (label line, then TITLE IN CAPITALS);
figure caption centered below ("Figure N: " plain, title bold and underlined); tables full width with thin black borders.
"""
import copy
from docx import Document
from docx.enum.section import WD_ORIENT
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_LINE_SPACING, WD_BREAK
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Inches, Pt, RGBColor, Emu

TNR = "Times New Roman"
SANS = "Calibri"

TEXT_W_IN = 6.0   # 8.5 - 1.5 - 1.0


def _set_font(style_or_run, name, size=None, bold=None, italic=None, color=None):
    f = style_or_run.font
    f.name = name
    el = style_or_run.element if hasattr(style_or_run, "element") else style_or_run._element
    rpr = el.get_or_add_rPr() if hasattr(el, "get_or_add_rPr") else el.find(qn("w:rPr"))
    rfonts = rpr.find(qn("w:rFonts"))
    if rfonts is None:
        rfonts = OxmlElement("w:rFonts")
        rpr.insert(0, rfonts)
    for a in ("w:ascii", "w:hAnsi", "w:eastAsia", "w:cs"):
        rfonts.set(qn(a), name)
    if size:
        f.size = Pt(size)
    if bold is not None:
        f.bold = bold
    if italic is not None:
        f.italic = italic
    if color is not None:
        f.color.rgb = RGBColor.from_string(color)


def manuscript_doc(landscape=False):
    d = Document()
    s = d.sections[0]
    s.page_width, s.page_height = Inches(8.5), Inches(11)
    if landscape:
        s.orientation = WD_ORIENT.LANDSCAPE
        s.page_width, s.page_height = Inches(11), Inches(8.5)
    s.left_margin, s.right_margin, s.top_margin, s.bottom_margin = Inches(1.5), Inches(1), Inches(1), Inches(1)
    if landscape:
        s.left_margin, s.right_margin, s.top_margin, s.bottom_margin = Inches(1), Inches(1), Inches(1.5), Inches(1)
    st = d.styles
    n = st["Normal"]
    _set_font(n, TNR, 11)
    n.font.color.rgb = RGBColor(0, 0, 0)
    pf = n.paragraph_format
    pf.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
    pf.line_spacing = 1.5
    pf.space_before = Pt(0)
    pf.space_after = Pt(0)
    pf.first_line_indent = Inches(0.5)
    for name, size, align, bold, before in (("Heading 1", 14, WD_ALIGN_PARAGRAPH.CENTER, True, 0), ("Heading 2", 12, WD_ALIGN_PARAGRAPH.CENTER, True, 0),
                                            ("Heading 3", 11, WD_ALIGN_PARAGRAPH.LEFT, True, 10), ("Heading 4", 11, WD_ALIGN_PARAGRAPH.CENTER, False, 0)):
        h = st[name]
        _set_font(h, TNR, size, bold=bold, italic=False, color="000000")
        hp = h.paragraph_format
        hp.alignment = align
        hp.line_spacing = 1.5
        hp.space_before = Pt(before)
        hp.space_after = Pt(0)
        hp.first_line_indent = Inches(0)
        hp.keep_with_next = True
    return d


def body(d, text, indent=True, align=None, bold_lead=None, italic=False):
    """Body paragraph. bold_lead: text in bold at the start (the manuscript's 'Learners. ...' pattern)."""
    p = d.add_paragraph()
    p.style = d.styles["Normal"]
    if not indent:
        p.paragraph_format.first_line_indent = Inches(0)
    if align == "left":
        p.alignment = WD_ALIGN_PARAGRAPH.LEFT
    if bold_lead:
        r = p.add_run(bold_lead)
        _set_font(r, TNR, 11, bold=True)
        r = p.add_run(" " + text)
    else:
        r = p.add_run(text)
    _set_font(r, TNR, 11, italic=italic)
    return p


def h1(d, t):
    p = d.add_paragraph(t, style="Heading 1")
    return p


def h2(d, t):
    return d.add_paragraph(t, style="Heading 2")


def h3(d, t):
    return d.add_paragraph(t, style="Heading 3")


def table_caption(d, label, title):
    """Above the table: 'Table 23' then the title in capitals, both centered (the manuscript's Heading 4)."""
    p = d.add_paragraph(label, style="Heading 4")
    p2 = d.add_paragraph(title.upper(), style="Heading 4")
    return p2


def fig_caption(d, label, title):
    p = d.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.first_line_indent = Inches(0)
    r = p.add_run(label + " ")
    _set_font(r, TNR, 11)
    r2 = p.add_run(title)
    _set_font(r2, TNR, 11, bold=True)
    r2.font.underline = True
    return p


def picture(d, path, width_in=TEXT_W_IN, max_h_in=8.6):
    from PIL import Image
    im = Image.open(path)
    w, h = im.size
    wi = width_in
    hi = wi * h / w
    if hi > max_h_in:
        hi = max_h_in
        wi = hi * w / h
    p = d.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.first_line_indent = Inches(0)
    p.paragraph_format.line_spacing = 1.0
    p.add_run().add_picture(path, width=Inches(wi))
    return p


def _shade(cell, hexfill):
    tcPr = cell._tc.get_or_add_tcPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:val"), "clear")
    shd.set(qn("w:color"), "auto")
    shd.set(qn("w:fill"), hexfill)
    tcPr.append(shd)


def _borders(table, sz=4, color="000000"):
    tblPr = table._tbl.tblPr
    b = OxmlElement("w:tblBorders")
    for edge in ("top", "left", "bottom", "right", "insideH", "insideV"):
        e = OxmlElement(f"w:{edge}")
        e.set(qn("w:val"), "single")
        e.set(qn("w:sz"), str(sz))
        e.set(qn("w:space"), "0")
        e.set(qn("w:color"), color)
        b.append(e)
    tblPr.append(b)


def _cell_text(cell, text, size, bold=False, align="left", font=TNR, line=1.0, color=None, italic=False):
    cell.text = ""
    p = cell.paragraphs[0]
    p.paragraph_format.first_line_indent = Inches(0)
    p.paragraph_format.line_spacing = line
    p.paragraph_format.space_before = Pt(2)
    p.paragraph_format.space_after = Pt(2)
    p.alignment = {"left": WD_ALIGN_PARAGRAPH.LEFT, "center": WD_ALIGN_PARAGRAPH.CENTER, "justify": WD_ALIGN_PARAGRAPH.JUSTIFY}[align]
    lines = str(text).split("\n")
    for i, ln in enumerate(lines):
        if i:
            p = cell.add_paragraph()
            p.paragraph_format.first_line_indent = Inches(0)
            p.paragraph_format.line_spacing = line
            p.paragraph_format.space_before = Pt(0)
            p.paragraph_format.space_after = Pt(2)
            p.alignment = {"left": WD_ALIGN_PARAGRAPH.LEFT, "center": WD_ALIGN_PARAGRAPH.CENTER, "justify": WD_ALIGN_PARAGRAPH.JUSTIFY}[align]
        r = p.add_run(ln)
        _set_font(r, font, size, bold=bold, italic=italic, color=color)


def table(d, header, rows, widths_in, size=8.5, font=TNR, header_fill=None, bold_first_col=False, line=1.0, total_w=TEXT_W_IN, repeat_header=True, col_align=None, zebra=False):
    """Full width table with thin black borders, as the manuscript's tables."""
    t = d.add_table(rows=1, cols=len(header))
    t.alignment = WD_TABLE_ALIGNMENT.CENTER
    t.autofit = False
    _borders(t)
    scale = total_w / sum(widths_in)
    ws = [w * scale for w in widths_in]
    for i, htxt in enumerate(header):
        c = t.rows[0].cells[i]
        _cell_text(c, htxt, size, bold=True, align="center", font=font, line=line)
        if header_fill:
            _shade(c, header_fill)
    if repeat_header:
        trPr = t.rows[0]._tr.get_or_add_trPr()
        th = OxmlElement("w:tblHeader")
        th.set(qn("w:val"), "true")
        trPr.append(th)
    for ri, row in enumerate(rows):
        cells = t.add_row().cells
        for i, txt in enumerate(row):
            al = (col_align[i] if col_align else "left")
            _cell_text(cells[i], txt, size, bold=(bold_first_col and i == 0), align=al, font=font, line=line)
            if zebra and ri % 2 == 1:
                _shade(cells[i], "F5F7FA")
    for row in t.rows:
        # do not split a row across pages
        trPr = row._tr.get_or_add_trPr()
        cs = OxmlElement("w:cantSplit")
        cs.set(qn("w:val"), "true")
        trPr.append(cs)
        for i, w in enumerate(ws):
            row.cells[i].width = Inches(w)
    return t


TAG_FILL = {"NEW": "D9F2E3", "UPDATED": "FFF1CC", "FIX": "FADBD8", "DELETE": "E5E7EB", "DECISION": "E8DAF7", "CHECK": "D6E4F5"}


def meta_box(d, bid, tag, place, replaces="", basis="", note=""):
    """A small box above each block: it is for the writer, it is NOT manuscript text."""
    brk = _PENDING["flag"]
    _PENDING["flag"] = False
    t = d.add_table(rows=1, cols=2)
    t.autofit = False
    _borders(t, sz=6, color="7A869A")
    cs = OxmlElement("w:cantSplit")
    cs.set(qn("w:val"), "true")
    t.rows[0]._tr.get_or_add_trPr().append(cs)
    left, right = t.rows[0].cells
    left.width, right.width = Inches(1.15), Inches(TEXT_W_IN - 1.15)
    _shade(left, TAG_FILL.get(tag.split()[0], "EEEEEE"))
    _cell_text(left, f"{bid}\n{tag}", 10, bold=True, align="center", font=SANS)
    lines = [f"Place at: {place}"]
    if replaces:
        lines.append(f"Replaces: {replaces}")
    if basis:
        lines.append(f"Basis: {basis}")
    if note:
        lines.append(f"Note: {note}")
    _cell_text(right, "\n".join(lines), 9, align="left", font=SANS, color="1F2937")
    for c in (left, right):
        for para in c.paragraphs:
            para.paragraph_format.keep_with_next = True
    if brk:
        left.paragraphs[0].paragraph_format.page_break_before = True
    sp = d.add_paragraph()
    sp.paragraph_format.first_line_indent = Inches(0)
    sp.paragraph_format.line_spacing = 1.0
    sp.paragraph_format.space_after = Pt(4)
    sp.paragraph_format.keep_with_next = True
    return t


def end_marker(d, bid):
    p = d.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.first_line_indent = Inches(0)
    p.paragraph_format.line_spacing = 1.0
    p.paragraph_format.space_before = Pt(6)
    p.paragraph_format.space_after = Pt(14)
    r = p.add_run(f"----- end of {bid}: copy only the text above this line -----")
    _set_font(r, SANS, 8, italic=True, color="7A869A")


_PENDING = {"flag": False}
from docx.document import Document as _DocClass
_orig_add_paragraph = _DocClass.add_paragraph


def _add_paragraph(self, text="", style=None):
    p = _orig_add_paragraph(self, text, style)
    if _PENDING["flag"]:
        p.paragraph_format.page_break_before = True
        _PENDING["flag"] = False
    return p


_DocClass.add_paragraph = _add_paragraph


def page_break(d):
    """Start the next paragraph (or the next block box) on a new page. Uses 'page break before', so a full page never leaves a blank page."""
    _PENDING["flag"] = True


def work_doc(landscape=False, margins=0.8):
    """A plain working document for the writer (not manuscript text): Calibri 10, left aligned."""
    d = Document()
    s = d.sections[0]
    s.page_width, s.page_height = Inches(8.5), Inches(11)
    if landscape:
        s.orientation = WD_ORIENT.LANDSCAPE
        s.page_width, s.page_height = Inches(11), Inches(8.5)
    s.left_margin = s.right_margin = Inches(margins)
    s.top_margin = s.bottom_margin = Inches(0.8)
    n = d.styles["Normal"]
    _set_font(n, SANS, 10.5)
    n.paragraph_format.space_after = Pt(5)
    n.paragraph_format.line_spacing = 1.15
    for name, size, color in (("Heading 1", 18, "0B5CAD"), ("Heading 2", 14, "0B5CAD"), ("Heading 3", 11.5, "1F2937")):
        h = d.styles[name]
        _set_font(h, SANS, size, bold=True, color=color)
        h.paragraph_format.space_before = Pt(12)
        h.paragraph_format.space_after = Pt(4)
        h.paragraph_format.keep_with_next = True
    return d


def wp(d, text, bold=False, italic=False, size=None, color=None, align=None, after=None):
    p = d.add_paragraph()
    r = p.add_run(text)
    _set_font(r, SANS, size or 10.5, bold=bold, italic=italic, color=color)
    if align == "center":
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    if after is not None:
        p.paragraph_format.space_after = Pt(after)
    return p


def wbullets(d, items, size=10.5):
    for it in items:
        p = d.add_paragraph(style="List Bullet")
        p.paragraph_format.space_after = Pt(2)
        if isinstance(it, tuple):
            r = p.add_run(it[0])
            _set_font(r, SANS, size, bold=True)
            r2 = p.add_run(" " + it[1])
            _set_font(r2, SANS, size)
        else:
            r = p.add_run(it)
            _set_font(r, SANS, size)


def wtable(d, header, rows, widths_in, size=9, fills=None, total_w=None):
    """Working table with a shaded header and light borders."""
    sec = d.sections[0]
    tw = total_w or (sec.page_width.inches - sec.left_margin.inches - sec.right_margin.inches)
    t = d.add_table(rows=1, cols=len(header))
    t.autofit = False
    _borders(t, sz=4, color="9AA5B5")
    scale = tw / sum(widths_in)
    ws = [w * scale for w in widths_in]
    for i, htxt in enumerate(header):
        c = t.rows[0].cells[i]
        _cell_text(c, htxt, size, bold=True, align="left", font=SANS, color="FFFFFF")
        _shade(c, "0B5CAD")
        for para in c.paragraphs:
            para.paragraph_format.keep_with_next = True
    trPr = t.rows[0]._tr.get_or_add_trPr()
    th = OxmlElement("w:tblHeader")
    th.set(qn("w:val"), "true")
    trPr.append(th)
    for ri, row in enumerate(rows):
        cells = t.add_row().cells
        for i, txt in enumerate(row):
            _cell_text(cells[i], txt, size, align="left", font=SANS)
            if fills and fills[i]:
                f = fills[i](txt) if callable(fills[i]) else None
                if f:
                    _shade(cells[i], f)
    for row in t.rows:
        trPr = row._tr.get_or_add_trPr()
        cs = OxmlElement("w:cantSplit")
        cs.set(qn("w:val"), "true")
        trPr.append(cs)
        for i, w in enumerate(ws):
            row.cells[i].width = Inches(w)
    return t
