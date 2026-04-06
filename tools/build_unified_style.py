"""
Merge assets/css/*.css into a single style.css.
- Collects :root custom properties into one block
- Removes duplicate :root from fragments
- Normalizes leading indentation from extracted files
"""
from __future__ import annotations

import re
from pathlib import Path

CSS_DIR = Path(__file__).resolve().parents[1] / "assets" / "css"

ROOT_BLOCK = re.compile(r":root\s*\{([^}]*)\}", re.MULTILINE | re.DOTALL)

# Order: global base first, then layout/theme, then modules (roughly A–Z by area)
MERGE_ORDER = [
    "style.css",  # base; we'll patch before read
    "sidebar.css",
    "patient.css",
    "auth.css",
    "dashboard.css",
    "appointments-add.css",
    "appointments-edit.css",
    "appointments-index.css",
    "appointments-test-whatsapp.css",
    "appointments-view.css",
    "assistant-subscriptions.css",
    "inventory-add.css",
    "inventory-edit.css",
    "inventory-index.css",
    "inventory-transaction.css",
    "inventory-view.css",
    "patients-add.css",
    "patients-edit.css",
    "patients-index.css",
    "queue-index.css",
    "settings-index.css",
    "treatments.css",
    "patient-index.css",
    "patient-owo-payment.css",
    "patient-points.css",
    "patient-profile.css",
    "patient-queue.css",
    "patient-referrals.css",
    "patient-subscription.css",
    "patient-teeth.css",
]


def parse_root_properties(block_inner: str) -> dict[str, str]:
    out: dict[str, str] = {}
    # crude split on ; — sufficient for our :root blocks (no semicolons in values)
    for part in block_inner.split(";"):
        part = part.strip()
        if not part or part.startswith("/*"):
            continue
        if ":" not in part:
            continue
        name, val = part.split(":", 1)
        name = name.strip()
        val = val.strip()
        if name.startswith("--"):
            out[name] = val
    return out


def extract_all_roots(text: str) -> dict[str, str]:
    merged: dict[str, str] = {}
    for m in ROOT_BLOCK.finditer(text):
        merged.update(parse_root_properties(m.group(1)))
    return merged


def strip_root_blocks(text: str) -> str:
    return ROOT_BLOCK.sub("", text).strip()


def dedent_css(text: str) -> str:
    lines = text.splitlines()
    if not lines:
        return text
    # strip common leading whitespace if file was uniformly indented
    ws = []
    for ln in lines:
        if ln.strip():
            ws.append(len(ln) - len(ln.lstrip(" \t")))
    if not ws:
        return text
    common = min(ws)
    if common == 0:
        return text
    return "\n".join(ln[common:] if len(ln) >= common else ln for ln in lines)


def patch_base_style(text: str) -> str:
    """Remove redundant .sidebar / .main-content rules from first @media (max-width: 768px)."""
    old = """@media (max-width: 768px) {
    .sidebar {
        width: 0;
    }
    .main-content {
        margin-left: 0;
    }
    .stats-card .stats-number {
        font-size: 24px;
    }
}"""
    new = """@media (max-width: 768px) {
    .stats-card .stats-number {
        font-size: 24px;
    }
}"""
    if old in text:
        text = text.replace(old, new, 1)
    return text


def scoped_treatment_plan_print() -> str:
    """Original treatment-plans-print.css scoped to body.tp-print-page (standalone print view)."""
    return """
/* ----- treatment_plans/print.php (body.tp-print-page) ----- */
body.tp-print-page {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    margin: 20px;
    background: #f4f6f9;
}

@media print {
    body.tp-print-page {
        margin: 0;
        padding: 0;
        font-size: 12pt;
        line-height: 1.4;
    }
    body.tp-print-page .no-print {
        display: none !important;
    }
    body.tp-print-page .page-break {
        page-break-before: always;
    }
    body.tp-print-page a {
        text-decoration: none;
        color: #000;
    }
    body.tp-print-page .badge {
        display: inline-block;
        padding: 2px 6px;
        border-radius: 4px;
        color: #fff;
        font-size: 10pt;
    }
}

body.tp-print-page .print-container {
    max-width: 1100px;
    margin: 0 auto;
    background: #fff;
    padding: 20px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
}

body.tp-print-page .header {
    text-align: center;
    margin-bottom: 20px;
    border-bottom: 2px solid #ddd;
    padding-bottom: 10px;
}

body.tp-print-page .header h1 {
    margin: 0;
    font-size: 24pt;
}

body.tp-print-page .header p {
    margin: 5px 0 0;
    color: #666;
}

body.tp-print-page .section {
    margin-bottom: 20px;
}

body.tp-print-page .section-title {
    font-size: 18pt;
    font-weight: bold;
    border-left: 4px solid #0d6efd;
    padding-left: 10px;
    margin-bottom: 15px;
}

body.tp-print-page .info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-bottom: 15px;
}

body.tp-print-page .info-item {
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
}

body.tp-print-page .info-label {
    font-weight: bold;
    width: 140px;
    flex-shrink: 0;
}

body.tp-print-page .info-value {
    flex: 1;
}

body.tp-print-page table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 15px;
}

body.tp-print-page th,
body.tp-print-page td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
    vertical-align: top;
}

body.tp-print-page th {
    background-color: #f2f2f2;
    font-weight: bold;
}

body.tp-print-page .footer {
    text-align: center;
    font-size: 10pt;
    color: #666;
    margin-top: 30px;
    padding-top: 10px;
    border-top: 1px solid #ddd;
}

body.tp-print-page .btn-print {
    background-color: #0d6efd;
    color: #fff;
    border: none;
    padding: 10px 20px;
    font-size: 16px;
    cursor: pointer;
    border-radius: 5px;
    margin-bottom: 20px;
}

body.tp-print-page .btn-print:hover {
    background-color: #0b5ed7;
}

body.tp-print-page .text-center {
    text-align: center;
}

body.tp-print-page .text-right {
    text-align: right;
}
"""


def scoped_patient_invoice_print() -> str:
    return """
/* ----- patient/view_invoice.php (.patient-invoice-print-root) ----- */
@media print {
    .patient-invoice-print-root .btn,
    .patient-invoice-print-root .mb-3 a,
    .patient-invoice-print-root .card-header .btn {
        display: none !important;
    }
    .patient-invoice-print-root.container-fluid {
        padding: 0;
        margin: 0;
    }
    .patient-invoice-print-root .card {
        border: none;
        box-shadow: none;
    }
}
"""


def main() -> None:
    all_root: dict[str, str] = {}
    chunks: list[str] = []

    for name in MERGE_ORDER:
        path = CSS_DIR / name
        if not path.exists():
            raise SystemExit(f"Missing {path}")
        raw = path.read_text(encoding="utf-8")
        if name == "style.css":
            raw = patch_base_style(raw)
        all_root.update(extract_all_roots(raw))
        body = strip_root_blocks(raw)
        body = dedent_css(body)
        if body:
            chunks.append(f"\n\n/* ===== {name} ===== */\n{body}")

    root_css = ":root {\n"
    for k in sorted(all_root.keys()):
        root_css += f"    {k}: {all_root[k]};\n"
    root_css += "}\n"

    out = root_css + "\n" + "\n".join(c for c in chunks if c.strip())
    out += scoped_treatment_plan_print()
    out += scoped_patient_invoice_print()

    out_path = CSS_DIR / "style.css"
    out_path.write_text(out.strip() + "\n", encoding="utf-8")

    # Remove other css files
    for p in CSS_DIR.glob("*.css"):
        if p.name != "style.css":
            p.unlink()

    print("Wrote", out_path, "bytes", out_path.stat().st_size)


if __name__ == "__main__":
    main()
