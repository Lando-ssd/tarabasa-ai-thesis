from model import MODULES
from model_notes import FN_NOTES
miss = [n for m in MODULES for a, f in m["actors"] for n, s in f if s != "same" and n not in FN_NOTES]
print("missing notes:", miss)
extra = [k for k in FN_NOTES if not any(k == n for m in MODULES for a, f in m["actors"] for n, s in f)]
print("notes for unknown functions:", extra)
