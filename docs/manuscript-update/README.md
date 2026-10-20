# Manuscript update pack (20 October 2026)

For the writer of the manuscript. Open `00_README_START_HERE.docx` first.

| File | What it is |
|---|---|
| `00_README_START_HERE.docx` | Work order, formatting rules, decisions the team must make, limits. |
| `01_Review_and_Change_Register.docx` | 64 findings against manuscript version 29 (page, action, type, priority), numbering map, page number errors. |
| `02_Insert_Ready_Text.docx` | Every new or replacement text, table and picture, in the manuscript's own formatting, each with a "Place at" box. |
| `03_FDD_and_Use_Case_Panel_Review.docx` | The new eleven-module FDD, every function marked new, updated or unchanged, and 12 questions for the panel. |
| `04_New_References_and_Basis.docx` | New references (APA) with how far each was checked, citation corrections, feature to source table. |
| `05_Figures/` | Every new figure: `for_Word_PNG` (300 dpi), `svg`, `editable_drawio` (open at app.diagrams.net), `review_versions`. |
| `tools/` | The Python scripts that make all of the above from one model, so the figures, FDD, use cases and List of Modules cannot disagree. |

Reviewed against the system as built on 20 October 2026 (code, routes, migrations, project notes). No real child, classroom or phone test has been done; see the "not proven" list in `00_README_START_HERE.docx`.
The adaptive support described here is rule based. No model was trained.

## Regenerating (optional)

```
cd tools/src
python build_all.py     # figures (needs Pillow; uses Windows Arial fonts)
python build_docs.py    # the five Word files (needs python-docx)
```
The output goes to `tools/out/`. Edit the model in `model.py` and the texts in `text_*.py`, then rebuild.
