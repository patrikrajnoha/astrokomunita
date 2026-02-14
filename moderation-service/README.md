# Moderation Service (local, free)

FastAPI microservice for text toxicity/hate and NSFW image moderation.

## Endpoints
- `GET /health`
- `POST /moderate/text`
- `POST /moderate/image` (multipart file or `image_base64` field)

All endpoints require `X-Internal-Token` header.

## Run locally
```powershell
cd moderation-service
python -m venv .venv
.\.venv\Scripts\activate
pip install -r requirements.txt
copy .env.example .env
uvicorn app.main:app --host 127.0.0.1 --port 8090
```

## Docker
```powershell
docker compose -f moderation-service/docker-compose.example.yml up --build
```

## Default models
- Text toxicity: `unitary/toxic-bert`
- Text hate (optional): `cardiffnlp/twitter-roberta-base-hate-latest`
- Image NSFW: `Falconsai/nsfw_image_detection`
