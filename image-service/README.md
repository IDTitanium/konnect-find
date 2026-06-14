# KonnectFind Image Embedding Service

This FastAPI service provides image embeddings for the production multimodal
retrieval path. Its default local representation is lightweight and
reproducible; OpenCLIP is available as the higher-quality model option.

```bash
pip install -r requirements.txt
uvicorn app.main:app --host 0.0.0.0 --port 8001
```

Readiness endpoint: `GET /health`.

For OpenCLIP, install `requirements-openclip.txt`, set `IMAGE_MODEL=openclip`,
and configure the corresponding embedding dimensions before database migration.
