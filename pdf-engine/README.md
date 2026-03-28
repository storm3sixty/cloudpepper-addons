# PDF Engine

`pdf-engine` is a beginner-friendly FastAPI backend for a print/PDF SaaS.

It accepts uploaded PDF files, processes them in one of three modes, stores the original and processed files on disk, and exposes a download endpoint for the output PDF.

## Features

- Health check endpoint (`GET /health`)
- PDF processing endpoint (`POST /process`) with modes:
  - `watermark` (diagonal semi-transparent text on every page)
  - `numbering` (page numbers on every page)
  - `booklet` (page reordering for booklet print order)
- Output download endpoint (`GET /download/{filename}`)
- Safe filename generation
- Non-PDF upload rejection

## Project structure

```text
pdf-engine/
├── main.py
├── requirements.txt
├── Dockerfile
├── README.md
├── uploads/
├── output/
└── temp/
```

## Requirements

- Python 3.11

## Run locally

1. Create and activate a virtual environment.
2. Install dependencies.
3. Start the API.

```bash
cd pdf-engine
python3.11 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
uvicorn main:app --reload --host 0.0.0.0 --port 8000
```

Open docs at: <http://localhost:8000/docs>

## Docker

Build and run:

```bash
docker build -t pdf-engine .
docker run --rm -p 8000:8000 pdf-engine
```

## API endpoints

### `GET /health`

Response:

```json
{ "ok": true }
```

### `POST /process`

Multipart form fields:

- `file` (required): uploaded PDF
- `mode` (required): `watermark`, `numbering`, or `booklet`
- `watermark_text` (optional): only used in `watermark` mode, defaults to `SAMPLE`
- `number_position` (optional): `bottom-center` or `top-right`, defaults to `bottom-center`

Successful response example:

```json
{
  "success": true,
  "mode": "watermark",
  "original_filename": "brochure.pdf",
  "output_filename": "watermark_brochure_<id>.pdf",
  "download_path": "/download/watermark_brochure_<id>.pdf"
}
```

### `GET /download/{filename}`

Downloads the processed file if it exists.
Returns `404` if the file is missing.

## cURL examples

### Health check

```bash
curl -X GET http://localhost:8000/health
```

### Watermark mode

```bash
curl -X POST http://localhost:8000/process \
  -F "file=@./sample.pdf" \
  -F "mode=watermark" \
  -F "watermark_text=CONFIDENTIAL"
```

### Numbering mode

```bash
curl -X POST http://localhost:8000/process \
  -F "file=@./sample.pdf" \
  -F "mode=numbering" \
  -F "number_position=top-right"
```

### Booklet mode

```bash
curl -X POST http://localhost:8000/process \
  -F "file=@./sample.pdf" \
  -F "mode=booklet"
```

### Download output

```bash
curl -L -o processed.pdf http://localhost:8000/download/<output_filename>.pdf
```

## Notes for v1

- No database yet
- No authentication yet
- No Fiery or Adobe integration yet
- No 2-up or 4-up imposition yet
- Focused on stable single-service PDF processing
