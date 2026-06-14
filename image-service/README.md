# KonnectFind Image Embedding Service

This FastAPI service provides image embeddings for the production multimodal
retrieval path. Its default local representation is lightweight and
reproducible; OpenCLIP is available as the higher-quality model option.

```bash
pip install -r requirements.txt
uvicorn app.main:app --host 0.0.0.0 --port 8001
```

Readiness endpoint: `GET /health`.

Set `IMAGE_SERVICE_TOKEN` in production. Requests to `/embed` and `/embed-url`
must then include `Authorization: Bearer <token>`.

For OpenCLIP, install `requirements-openclip.txt`, set `IMAGE_MODEL=openclip`,
and configure the corresponding embedding dimensions before database migration.

## Deploy On Render

The repository includes a root-level `render.yaml` Blueprint. In Render:

1. Create a new Blueprint from the repository.
2. Deploy `konnectfind-image-service`.
3. Copy its generated `IMAGE_SERVICE_TOKEN` and public HTTPS URL.
4. Configure Laravel Cloud with:

```dotenv
SEARCH_IMAGE_PROVIDER=service
IMAGE_EMBEDDING_SERVICE_URL=https://konnectfind-image-service.onrender.com
IMAGE_EMBEDDING_SERVICE_TOKEN=the-same-render-token
SEARCH_IMAGE_DIMENSIONS=64
```

Verify `GET /health`, then redeploy Laravel Cloud.

Run the authenticated smoke test from the repository root:

```bash
IMAGE_SERVICE_TOKEN=the-generated-render-token \
  ./scripts/image-service-smoke-test.sh https://konnectfind-image-service.onrender.com
```

The default `local` model emits 64-dimensional deterministic embeddings and is
appropriate for a reliable demonstration deployment. OpenCLIP provides stronger
semantic image retrieval but has a substantially larger image, memory
requirement, and embedding dimension. Configure its dimension before running
the PostgreSQL vector migration.
