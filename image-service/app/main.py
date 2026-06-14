import os
from io import BytesIO
from urllib.parse import urlparse

import httpx
from fastapi import Depends, FastAPI, File, Header, HTTPException, UploadFile
from PIL import Image

app = FastAPI(title="KonnectFind Image Embedding Service", version="0.2.0")
model_name = os.getenv("IMAGE_MODEL", "local")
service_token = os.getenv("IMAGE_SERVICE_TOKEN", "")
clip_model = None
clip_preprocess = None


def authorize(authorization: str | None = Header(default=None)) -> None:
    if service_token and authorization != f"Bearer {service_token}":
        raise HTTPException(status_code=401, detail="A valid service token is required.")


def load_openclip() -> None:
    global clip_model, clip_preprocess
    if model_name != "openclip" or clip_model is not None:
        return

    import open_clip

    clip_model, _, clip_preprocess = open_clip.create_model_and_transforms(
        os.getenv("OPENCLIP_MODEL", "ViT-B-32"),
        pretrained=os.getenv("OPENCLIP_PRETRAINED", "laion2b_s34b_b79k"),
    )
    clip_model.eval()


def parse_image(content: bytes) -> Image.Image:
    try:
        image = Image.open(BytesIO(content)).convert("RGB")
        image.load()
        return image
    except Exception as exc:
        raise HTTPException(status_code=422, detail="A valid image is required.") from exc


def local_embedding(image: Image.Image) -> list[float]:
    pixels = image.convert("L").resize((8, 8)).getdata()
    vector = [(pixel / 127.5) - 1 for pixel in pixels]
    magnitude = sum(value * value for value in vector) ** 0.5
    return [round(value / magnitude, 8) for value in vector] if magnitude else vector


def create_embedding(image: Image.Image) -> list[float]:
    if model_name != "openclip":
        return local_embedding(image)

    load_openclip()
    import torch

    tensor = clip_preprocess(image).unsqueeze(0)
    with torch.no_grad():
        features = clip_model.encode_image(tensor)
        features /= features.norm(dim=-1, keepdim=True)
    return features[0].cpu().tolist()


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok", "model": model_name}


@app.post("/embed", dependencies=[Depends(authorize)])
async def embed(image: UploadFile = File(...)) -> dict[str, object]:
    vector = create_embedding(parse_image(await image.read()))
    return {"model": model_name, "dimension": len(vector), "embedding": vector}


@app.post("/embed-url", dependencies=[Depends(authorize)])
async def embed_url(payload: dict[str, str]) -> dict[str, object]:
    url = payload.get("url", "")
    if urlparse(url).scheme not in {"http", "https"}:
        raise HTTPException(status_code=422, detail="A public HTTP(S) image URL is required.")

    try:
        async with httpx.AsyncClient(follow_redirects=True, timeout=20) as client:
            response = await client.get(url)
            response.raise_for_status()
    except httpx.HTTPError as exc:
        raise HTTPException(status_code=422, detail="The image URL could not be retrieved.") from exc

    vector = create_embedding(parse_image(response.content))
    return {"model": model_name, "dimension": len(vector), "embedding": vector}
