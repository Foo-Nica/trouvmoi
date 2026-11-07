FROM python:3.13-slim

ENV PYTHONDONTWRITEBYTECODE=1 PYTHONUNBUFFERED=1
WORKDIR /srv

RUN apt-get update && apt-get install -y --no-install-recommends curl ca-certificates \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY scripts/download_models.sh scripts/
RUN sh scripts/download_models.sh /srv/models

COPY app app

ENV FACE_MODELS_DIR=/srv/models FACE_DATA_DIR=/data
VOLUME /data
EXPOSE 8001

# Un worker par cœur CPU ; les modèles sont chargés une fois par thread.
# FACE_HOST=:: pour un réseau privé IPv6 (anciens environnements Railway).
ENV FACE_HOST=0.0.0.0 FACE_PORT=8001 FACE_WORKERS=2
CMD ["sh", "-c", "exec uvicorn app.main:app_factory --factory --host \"$FACE_HOST\" --port \"${PORT:-$FACE_PORT}\" --workers \"$FACE_WORKERS\""]
