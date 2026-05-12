"""
Extract AcroForm field names from every PDF in the documents folder.
Outputs a structured report for PHP field mapping.
"""
import os
import json
from pypdf import PdfReader

DOCS_ROOT = os.path.join(os.path.dirname(os.path.dirname(__file__)), "documents")

def get_fields(pdf_path):
    try:
        reader = PdfReader(pdf_path)
        fields = reader.get_fields()
        if not fields:
            return []
        result = []
        for name, field in fields.items():
            ftype = field.get("/FT", "unknown")
            if hasattr(ftype, 'name'):
                ftype = ftype.name
            flags = field.get("/Ff", 0)
            result.append({
                "name": name,
                "type": str(ftype),
                "flags": int(flags) if isinstance(flags, (int, float)) else 0,
            })
        return result
    except Exception as e:
        return [{"error": str(e)}]

report = {}

for root, dirs, files in os.walk(DOCS_ROOT):
    for fname in sorted(files):
        if not fname.lower().endswith(".pdf"):
            continue
        full_path = os.path.join(root, fname)
        rel_path  = os.path.relpath(full_path, DOCS_ROOT)
        fields = get_fields(full_path)
        report[rel_path] = fields

# Print as JSON for easy parsing
print(json.dumps(report, indent=2))
