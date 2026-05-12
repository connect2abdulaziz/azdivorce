"""Save PDF field extraction to JSON file + print compact per-PDF summary."""
import os, json
from pypdf import PdfReader

DOCS_ROOT = os.path.join(os.path.dirname(os.path.dirname(__file__)), "documents")
OUT_FILE  = os.path.join(os.path.dirname(__file__), "pdf_fields.json")

def get_fields(pdf_path):
    try:
        r = PdfReader(pdf_path)
        fields = r.get_fields()
        if not fields:
            return []
        out = []
        for name, field in fields.items():
            ft = field.get("/FT", "")
            if hasattr(ft, 'name'):
                ft = ft.name
            out.append({"name": name, "type": str(ft)})
        return out
    except Exception as e:
        return [{"error": str(e)}]

report = {}
for root, dirs, files in os.walk(DOCS_ROOT):
    for fname in sorted(files):
        if not fname.lower().endswith(".pdf"):
            continue
        path     = os.path.join(root, fname)
        rel_path = os.path.relpath(path, DOCS_ROOT)
        fields   = get_fields(path)
        report[rel_path] = fields

with open(OUT_FILE, "w", encoding="utf-8") as f:
    json.dump(report, f, indent=2)

print("=== SAVED TO", OUT_FILE)
print()

# Print compact summary: file name + field names only
for rel_path, fields in report.items():
    print("---")
    print(rel_path)
    if fields and "error" in fields[0]:
        print("  ERROR:", fields[0]["error"])
    else:
        names = [f["name"] for f in fields]
        print("  Fields (%d):" % len(names))
        for n in names:
            print("   ", repr(n))
    print()
