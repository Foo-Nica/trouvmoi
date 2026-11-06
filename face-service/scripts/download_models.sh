#!/usr/bin/env sh
# Télécharge les modèles OpenCV Zoo et vérifie leur empreinte SHA-256.
# YuNet : licence MIT. SFace : licence Apache 2.0. Usage commercial autorisé.
set -eu

DIR="${1:-models}"
BASE="https://media.githubusercontent.com/media/opencv/opencv_zoo/main/models"
mkdir -p "$DIR"

fetch() {
  name="$1"; path="$2"; sum="$3"
  if [ ! -f "$DIR/$name" ]; then
    echo "Téléchargement de $name..."
    curl -fsSL -o "$DIR/$name" "$BASE/$path/$name"
  fi
  echo "$sum  $DIR/$name" | sha256sum -c -
}

fetch face_detection_yunet_2023mar.onnx face_detection_yunet \
  8f2383e4dd3cfbb4553ea8718107fc0423210dc964f9f4280604804ed2552fa4
fetch face_recognition_sface_2021dec.onnx face_recognition_sface \
  0ba9fbfa01b5270c96627c4ef784da859931e02f04419c829e83484087c34e79
