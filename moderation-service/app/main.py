import base64
import io
import os
import re
import time
import unicodedata
from typing import Any

import torch
from fastapi import Depends, FastAPI, File, Header, HTTPException, UploadFile
from fastapi.responses import JSONResponse
from PIL import Image, UnidentifiedImageError
from pydantic import BaseModel, Field
from transformers import pipeline


TEXT_MODEL_NAME = os.getenv('MODERATION_TEXT_MODEL', 'unitary/toxic-bert')
HATE_MODEL_NAME = os.getenv('MODERATION_HATE_MODEL', 'cardiffnlp/twitter-roberta-base-hate-latest')
ENABLE_HATE_MODEL = os.getenv('MODERATION_ENABLE_HATE_MODEL', 'true').lower() == 'true'
IMAGE_MODEL_NAME = os.getenv('MODERATION_IMAGE_MODEL', 'Falconsai/nsfw_image_detection')
INTERNAL_TOKEN = os.getenv('MODERATION_INTERNAL_TOKEN', '')
MAX_IMAGE_BYTES = int(os.getenv('MODERATION_IMAGE_MAX_BYTES', str(5 * 1024 * 1024)))

TEXT_FLAG_THRESHOLD = float(os.getenv('MODERATION_TEXT_FLAG_THRESHOLD', '0.70'))
TEXT_BLOCK_THRESHOLD = float(os.getenv('MODERATION_TEXT_BLOCK_THRESHOLD', '0.90'))
IMAGE_FLAG_THRESHOLD = float(os.getenv('MODERATION_IMAGE_FLAG_THRESHOLD', '0.60'))
IMAGE_BLOCK_THRESHOLD = float(os.getenv('MODERATION_IMAGE_BLOCK_THRESHOLD', '0.85'))

DEVICE = 0 if torch.cuda.is_available() else -1

app = FastAPI(title='astrokomunita-moderation', version='1.0.0')

text_classifier = None
hate_classifier = None
image_classifier = None


class TextModerationRequest(BaseModel):
    text: str = Field(min_length=1, max_length=20000)
    lang: str | None = None


class ImageModerationRequest(BaseModel):
    image_base64: str = Field(min_length=10)


def decision_from_score(score: float, flag_threshold: float, block_threshold: float) -> str:
    if score >= block_threshold:
        return 'blocked'
    if score >= flag_threshold:
        return 'flagged'
    return 'ok'


def ensure_internal_token(x_internal_token: str | None = Header(default=None, alias='X-Internal-Token')) -> None:
    if not INTERNAL_TOKEN:
        raise HTTPException(status_code=500, detail='MODERATION_INTERNAL_TOKEN is not configured.')

    if not x_internal_token or x_internal_token != INTERNAL_TOKEN:
        raise HTTPException(status_code=401, detail='Unauthorized internal token.')


def parse_image_bytes(raw: bytes) -> Image.Image:
    if len(raw) > MAX_IMAGE_BYTES:
        raise HTTPException(status_code=413, detail='Image payload too large.')

    try:
        image = Image.open(io.BytesIO(raw))
        image.verify()
    except (UnidentifiedImageError, OSError):
        raise HTTPException(status_code=422, detail='Invalid or corrupted image file.')

    return Image.open(io.BytesIO(raw)).convert('RGB')


def normalize_label(label: str) -> str:
    return label.strip().lower().replace(' ', '_')


def parse_text_scores(results: list[dict[str, Any]]) -> dict[str, float]:
    scores: dict[str, float] = {}
    for row in results:
        label = normalize_label(str(row.get('label', '')))
        score = float(row.get('score', 0.0))
        scores[label] = score
    return scores


def toxicity_from_labels(scores: dict[str, float]) -> float:
    keys = ['toxic', 'severe_toxic', 'identity_hate', 'threat', 'insult', 'obscene']
    return max([float(scores.get(key, 0.0)) for key in keys] + [0.0])

def hate_from_labels(scores: dict[str, float]) -> float:
    # Cardiff hate model returns probabilities for "hate" and "not-hate".
    # We must only use the positive class; taking max() would treat not-hate as toxic.
    if 'hate' in scores:
        return float(scores.get('hate', 0.0))

    # Fallback for model variants with different positive class names.
    candidate_keys = ['toxic', 'abusive', 'offensive', 'hs']
    return max([float(scores.get(key, 0.0)) for key in candidate_keys] + [0.0])

def normalize_for_rules(text: str) -> str:
    lowered = text.lower()
    normalized = unicodedata.normalize('NFKD', lowered)
    ascii_only = ''.join(ch for ch in normalized if not unicodedata.combining(ch))
    return re.sub(r'\s+', ' ', ascii_only).strip()

def rule_based_text_decision(text: str) -> tuple[str, str | None]:
    normalized = normalize_for_rules(text)

    threat_patterns = [
        r'\bzabijem ta\b',
        r'\bzabijem\b',
        r'\bchcipni\b',
        r'\bumri\b',
        r'\bkill you\b',
        r'\bi will kill you\b',
    ]

    insult_patterns = [
        r'\bidiot\b',
        r'\bdebil\b',
        r'\bkreten\b',
        r'\bkokot\b',
        r'\bsvina\b',
    ]

    for pattern in threat_patterns:
        if re.search(pattern, normalized):
            return 'blocked', f'threat:{pattern}'

    for pattern in insult_patterns:
        if re.search(pattern, normalized):
            return 'flagged', f'insult:{pattern}'

    return 'ok', None

def combine_text_decisions(model_decision: str, rule_decision: str) -> str:
    if 'blocked' in (model_decision, rule_decision):
        return 'blocked'
    if 'flagged' in (model_decision, rule_decision):
        return 'flagged'
    return 'ok'


@app.on_event('startup')
def load_models() -> None:
    global text_classifier, hate_classifier, image_classifier

    text_classifier = pipeline(
        'text-classification',
        model=TEXT_MODEL_NAME,
        return_all_scores=True,
        device=DEVICE,
    )

    if ENABLE_HATE_MODEL:
        hate_classifier = pipeline(
            'text-classification',
            model=HATE_MODEL_NAME,
            return_all_scores=True,
            device=DEVICE,
        )

    image_classifier = pipeline(
        'image-classification',
        model=IMAGE_MODEL_NAME,
        device=DEVICE,
    )


@app.exception_handler(HTTPException)
async def http_exception_handler(_, exc: HTTPException):
    detail = exc.detail if isinstance(exc.detail, str) else 'Request failed.'
    code = 'http_error'
    if exc.status_code == 401:
        code = 'unauthorized'
    elif exc.status_code == 413:
        code = 'payload_too_large'
    elif exc.status_code == 422:
        code = 'validation_error'

    return JSONResponse(
        status_code=exc.status_code,
        content={
            'error': {
                'code': code,
                'message': detail,
            }
        },
    )


@app.get('/health')
def health(_: None = Depends(ensure_internal_token)) -> dict[str, Any]:
    return {
        'status': 'ok',
        'device': 'cuda' if DEVICE == 0 else 'cpu',
        'models': {
            'text': TEXT_MODEL_NAME,
            'hate': HATE_MODEL_NAME if ENABLE_HATE_MODEL else None,
            'image': IMAGE_MODEL_NAME,
        },
    }


@app.post('/moderate/text')
def moderate_text(payload: TextModerationRequest, _: None = Depends(ensure_internal_token)) -> dict[str, Any]:
    started_at = time.perf_counter()

    text_results = text_classifier(payload.text, truncation=True, max_length=512)[0]
    text_scores = parse_text_scores(text_results)
    toxicity_score = toxicity_from_labels(text_scores)

    hate_score = 0.0
    hate_scores: dict[str, float] = {}
    if ENABLE_HATE_MODEL and hate_classifier is not None:
        hate_results = hate_classifier(payload.text, truncation=True, max_length=512)[0]
        hate_scores = parse_text_scores(hate_results)
        hate_score = hate_from_labels(hate_scores)

    max_text_score = max(toxicity_score, hate_score)
    model_decision = decision_from_score(max_text_score, TEXT_FLAG_THRESHOLD, TEXT_BLOCK_THRESHOLD)
    rule_decision, rule_label = rule_based_text_decision(payload.text)
    decision = combine_text_decisions(model_decision, rule_decision)

    latency_ms = int((time.perf_counter() - started_at) * 1000)

    return {
        'decision': decision,
        'toxicity_score': toxicity_score,
        'hate_score': hate_score,
        'scores': {
            'toxicity_labels': text_scores,
            'hate_labels': hate_scores,
        },
        'labels': {
            'toxicity': max(text_scores, key=text_scores.get, default='none'),
            'hate': max(hate_scores, key=hate_scores.get, default='none') if hate_scores else 'disabled',
            'rule_match': rule_label or 'none',
        },
        'model_versions': {
            'text': TEXT_MODEL_NAME,
            'hate': HATE_MODEL_NAME if ENABLE_HATE_MODEL else None,
        },
        'latency_ms': latency_ms,
    }


@app.post('/moderate/image')
async def moderate_image(
    image: UploadFile | None = File(default=None),
    image_base64: str | None = None,
    _: None = Depends(ensure_internal_token),
) -> dict[str, Any]:
    started_at = time.perf_counter()

    raw_bytes: bytes | None = None

    if image is not None:
        raw_bytes = await image.read()

    if raw_bytes is None and image_base64:
        try:
            raw_bytes = base64.b64decode(image_base64, validate=True)
        except ValueError:
            raise HTTPException(status_code=422, detail='Invalid base64 image payload.')

    if not raw_bytes:
        raise HTTPException(status_code=422, detail='Missing image payload.')

    decoded_image = parse_image_bytes(raw_bytes)
    result = image_classifier(decoded_image)

    scores = {normalize_label(str(item.get('label', ''))): float(item.get('score', 0.0)) for item in result}
    nsfw_score = max(scores.get('nsfw', 0.0), scores.get('porn', 0.0), scores.get('sexy', 0.0), 0.0)
    decision = decision_from_score(nsfw_score, IMAGE_FLAG_THRESHOLD, IMAGE_BLOCK_THRESHOLD)

    latency_ms = int((time.perf_counter() - started_at) * 1000)

    return {
        'decision': decision,
        'nsfw_score': nsfw_score,
        'scores': scores,
        'labels': {
            'top_label': max(scores, key=scores.get, default='none'),
        },
        'model_versions': {
            'image': IMAGE_MODEL_NAME,
        },
        'latency_ms': latency_ms,
    }


@app.post('/moderate/image/base64')
def moderate_image_base64(payload: ImageModerationRequest, _: None = Depends(ensure_internal_token)) -> dict[str, Any]:
    try:
        raw_bytes = base64.b64decode(payload.image_base64, validate=True)
    except ValueError:
        raise HTTPException(status_code=422, detail='Invalid base64 image payload.')

    decoded_image = parse_image_bytes(raw_bytes)
    started_at = time.perf_counter()
    result = image_classifier(decoded_image)

    scores = {normalize_label(str(item.get('label', ''))): float(item.get('score', 0.0)) for item in result}
    nsfw_score = max(scores.get('nsfw', 0.0), scores.get('porn', 0.0), scores.get('sexy', 0.0), 0.0)
    decision = decision_from_score(nsfw_score, IMAGE_FLAG_THRESHOLD, IMAGE_BLOCK_THRESHOLD)

    latency_ms = int((time.perf_counter() - started_at) * 1000)

    return {
        'decision': decision,
        'nsfw_score': nsfw_score,
        'scores': scores,
        'labels': {
            'top_label': max(scores, key=scores.get, default='none'),
        },
        'model_versions': {
            'image': IMAGE_MODEL_NAME,
        },
        'latency_ms': latency_ms,
    }
