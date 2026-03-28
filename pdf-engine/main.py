from __future__ import annotations

import re
from io import BytesIO
from pathlib import Path
from typing import Literal
from uuid import uuid4

from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from fastapi.responses import FileResponse
from pypdf import PageObject, PdfReader, PdfWriter
from reportlab.lib.colors import Color
from reportlab.pdfgen import canvas

# Base folders for file storage.
BASE_DIR = Path(__file__).resolve().parent
UPLOAD_DIR = BASE_DIR / "uploads"
OUTPUT_DIR = BASE_DIR / "output"
TEMP_DIR = BASE_DIR / "temp"

# Create folders on startup so the app is ready immediately.
for folder in (UPLOAD_DIR, OUTPUT_DIR, TEMP_DIR):
    folder.mkdir(parents=True, exist_ok=True)

app = FastAPI(title="PDF Engine", version="1.0.0")


@app.get("/health")
def health() -> dict[str, bool]:
    """Simple readiness endpoint."""
    return {"ok": True}


@app.post("/process")
async def process_pdf(
    file: UploadFile = File(...),
    mode: Literal["watermark", "numbering", "booklet"] = Form(...),
    watermark_text: str | None = Form(default=None),
    number_position: Literal["bottom-center", "top-right"] | None = Form(default=None),
) -> dict[str, str | bool]:
    """
    Process a PDF file based on mode and return metadata for downloading output.
    """
    validate_upload(file)

    upload_name = build_safe_filename(file.filename or "upload.pdf", prefix="upload")
    upload_path = UPLOAD_DIR / upload_name

    file_bytes = await file.read()
    if not file_bytes:
        raise HTTPException(status_code=400, detail="Uploaded file is empty.")

    upload_path.write_bytes(file_bytes)

    # Validate PDF parsing before processing.
    try:
        reader = PdfReader(BytesIO(file_bytes))
        if len(reader.pages) == 0:
            raise HTTPException(status_code=400, detail="Uploaded PDF has no pages.")
    except HTTPException:
        raise
    except Exception as exc:  # noqa: BLE001 - return friendly API error
        raise HTTPException(status_code=400, detail="Invalid PDF file.") from exc

    output_name = build_safe_filename(file.filename or "output.pdf", prefix=mode)
    output_path = OUTPUT_DIR / output_name

    if mode == "watermark":
        process_watermark(reader, output_path, watermark_text or "SAMPLE")
    elif mode == "numbering":
        process_numbering(reader, output_path, number_position or "bottom-center")
    elif mode == "booklet":
        process_booklet(reader, output_path)

    return {
        "success": True,
        "mode": mode,
        "original_filename": file.filename or "upload.pdf",
        "output_filename": output_name,
        "download_path": f"/download/{output_name}",
    }


@app.get("/download/{filename}")
def download_file(filename: str) -> FileResponse:
    """Download a processed PDF if it exists."""
    safe_name = sanitize_name(filename)
    if not safe_name.endswith(".pdf"):
        raise HTTPException(status_code=404, detail="File not found")

    target_path = OUTPUT_DIR / safe_name
    if not target_path.exists() or not target_path.is_file():
        raise HTTPException(status_code=404, detail="File not found")

    return FileResponse(path=target_path, filename=safe_name, media_type="application/pdf")


def validate_upload(file: UploadFile) -> None:
    """Accept only PDF uploads."""
    name = file.filename or ""
    if not name.lower().endswith(".pdf"):
        raise HTTPException(status_code=400, detail="Only PDF files are allowed.")

    if file.content_type and file.content_type not in ("application/pdf", "application/x-pdf"):
        raise HTTPException(status_code=400, detail="File content type must be PDF.")


def sanitize_name(name: str) -> str:
    """Keep a filename safe for filesystem usage and remove path traversal."""
    base = Path(name).name
    safe = re.sub(r"[^A-Za-z0-9._-]", "_", base)
    return safe or "file.pdf"


def build_safe_filename(original_name: str, prefix: str) -> str:
    """Create a unique output filename that still keeps the .pdf extension."""
    cleaned = sanitize_name(original_name)
    stem = Path(cleaned).stem[:80] or "document"
    unique = uuid4().hex[:10]
    return f"{prefix}_{stem}_{unique}.pdf"


def process_watermark(reader: PdfReader, output_path: Path, text: str) -> None:
    """Add diagonal semi-transparent watermark text on each page."""
    writer = PdfWriter()

    for page in reader.pages:
        width = float(page.mediabox.width)
        height = float(page.mediabox.height)
        overlay_page = make_watermark_overlay(width, height, text)
        page.merge_page(overlay_page)
        writer.add_page(page)

    with output_path.open("wb") as output_file:
        writer.write(output_file)


def make_watermark_overlay(width: float, height: float, text: str) -> PageObject:
    """Create a one-page PDF overlay containing watermark text."""
    packet = BytesIO()
    c = canvas.Canvas(packet, pagesize=(width, height))

    # Semi-transparent gray text. Some reportlab builds provide setFillAlpha.
    c.setFillColor(Color(0.55, 0.55, 0.55, alpha=0.25))
    if hasattr(c, "setFillAlpha"):
        c.setFillAlpha(0.25)

    font_size = min(max(width, height) / 8, 72)
    c.setFont("Helvetica-Bold", font_size)

    c.saveState()
    c.translate(width / 2, height / 2)
    c.rotate(35)
    c.drawCentredString(0, 0, text)
    c.restoreState()
    c.save()

    packet.seek(0)
    return PdfReader(packet).pages[0]


def process_numbering(reader: PdfReader, output_path: Path, position: str) -> None:
    """Draw page numbers on each page."""
    writer = PdfWriter()

    for idx, page in enumerate(reader.pages, start=1):
        width = float(page.mediabox.width)
        height = float(page.mediabox.height)
        overlay_page = make_number_overlay(width, height, idx, position)
        page.merge_page(overlay_page)
        writer.add_page(page)

    with output_path.open("wb") as output_file:
        writer.write(output_file)


def make_number_overlay(width: float, height: float, page_number: int, position: str) -> PageObject:
    """Create overlay containing a page number in requested position."""
    packet = BytesIO()
    c = canvas.Canvas(packet, pagesize=(width, height))
    c.setFont("Helvetica", 11)
    c.setFillColor(Color(0, 0, 0))

    label = str(page_number)
    if position == "top-right":
        c.drawRightString(width - 30, height - 20, label)
    else:  # default bottom-center
        c.drawCentredString(width / 2, 20, label)

    c.save()
    packet.seek(0)
    return PdfReader(packet).pages[0]


def process_booklet(reader: PdfReader, output_path: Path) -> None:
    """Reorder pages for booklet printing order, padding to multiples of 4."""
    original_pages = list(reader.pages)
    total = len(original_pages)

    width = float(original_pages[0].mediabox.width)
    height = float(original_pages[0].mediabox.height)

    padded_total = total if total % 4 == 0 else total + (4 - total % 4)
    pages: list[PageObject] = list(original_pages)

    # Pad missing pages with blank pages so booklet ordering works.
    for _ in range(padded_total - total):
        pages.append(PageObject.create_blank_page(width=width, height=height))

    order: list[int] = []
    # For each sheet, place pages as: last, first, second, second-last.
    for i in range(padded_total // 4):
        order.extend([
            padded_total - 1 - (2 * i),
            2 * i,
            2 * i + 1,
            padded_total - 2 - (2 * i),
        ])

    writer = PdfWriter()
    for page_index in order:
        writer.add_page(pages[page_index])

    with output_path.open("wb") as output_file:
        writer.write(output_file)
