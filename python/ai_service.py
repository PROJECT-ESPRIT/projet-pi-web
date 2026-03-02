#!/usr/bin/env python3
import json
import os
import time
from typing import Optional

from fastapi import FastAPI, File, Form, UploadFile


app = FastAPI()
_MODEL = None
_MODEL_ERROR = None
_LABEL_GROUPS = None


def default_path(rel):
    base = os.path.dirname(os.path.abspath(__file__))
    return os.path.join(os.path.dirname(base), rel)


def ensure_tmpdir():
    path = os.environ.get("TMPDIR", "").strip()
    if not path:
        path = default_path("var/tmp")
    if path:
        try:
            os.makedirs(path, exist_ok=True)
        except Exception:
            pass
        os.environ["TMPDIR"] = path


def load_label_groups():
    path = os.environ.get("DONATION_AI_LABEL_GROUPS", "").strip()
    if not path:
        path = default_path("config/ai_label_groups.json")
    if not path or not os.path.isfile(path):
        return None
    try:
        with open(path, "r", encoding="utf-8") as f:
            data = json.load(f)
        if isinstance(data, dict):
            return {str(k).lower(): [str(x).lower() for x in v if str(x)] for k, v in data.items() if isinstance(v, list)}
    except Exception:
        return None
    return None


def matches_group(expected, predicted, groups):
    if not groups or expected not in groups:
        return False
    for keyword in groups[expected]:
        if keyword and keyword in predicted:
            return True
    return False


def normalize(text):
    return (text or "").strip().lower()


def prepare_image_bytes(raw_bytes):
    try:
        from PIL import Image
        import numpy as np
    except Exception:
        return None

    try:
        from io import BytesIO
        img = Image.open(BytesIO(raw_bytes))
        max_dim = int(os.environ.get("DONATION_AI_MAX_IMAGE", "1024"))
        needs_resize = max(img.size) > max_dim
        needs_rgb = img.mode != "RGB"
        if needs_rgb:
            img = img.convert("RGB")
        if needs_resize:
            img.thumbnail((max_dim, max_dim))
        return np.asarray(img)
    except Exception:
        return None


def _label_for_idx(names, idx):
    if names and idx in names:
        return names[idx]
    return str(idx)


def pick_top_predictions(result, top_k=5):
    names = getattr(result, "names", None)
    probs = getattr(result, "probs", None)
    if probs is not None:
        data = getattr(probs, "data", None)
        if data is not None:
            try:
                import torch
                if isinstance(data, torch.Tensor):
                    values, indices = torch.topk(data, k=min(top_k, data.numel()))
                    values = values.tolist()
                    indices = indices.tolist()
                else:
                    values = []
                    indices = []
            except Exception:
                values = []
                indices = []
        else:
            values = []
            indices = []

        if not indices:
            top_idx = int(getattr(probs, "top1", 0))
            conf = float(getattr(probs, "top1conf", 0.0))
            return [(_label_for_idx(names, top_idx), conf)]

        return [(_label_for_idx(names, int(i)), float(v)) for i, v in zip(indices, values)]

    boxes = getattr(result, "boxes", None)
    if boxes is None or boxes.cls is None or boxes.conf is None:
        return []

    confs = boxes.conf.tolist()
    classes = boxes.cls.tolist()
    if not confs:
        return []

    # Return top-K detections instead of just the best one to reduce false negatives.
    ranked = sorted(range(len(confs)), key=lambda i: confs[i], reverse=True)[: max(1, top_k)]
    out = []
    for idx in ranked:
        class_id = int(classes[idx])
        label = _label_for_idx(names, class_id)
        out.append((label, float(confs[idx])))
    return out


def load_model():
    global _MODEL, _MODEL_ERROR
    model_path = os.environ.get("DONATION_AI_MODEL", "").strip()
    if not model_path:
        model_path = default_path("models/donation.pt")
    if not model_path or not os.path.isfile(model_path):
        _MODEL_ERROR = f"Model file not found: {model_path}"
        return None
    try:
        from ultralytics import YOLO
        _MODEL = YOLO(model_path)
        _MODEL_ERROR = None
        return _MODEL
    except Exception as exc:
        _MODEL_ERROR = str(exc) or "Failed to load model."
        return None


@app.on_event("startup")
def startup_event():
    global _LABEL_GROUPS
    ensure_tmpdir()
    _LABEL_GROUPS = load_label_groups()
    load_model()


@app.get("/health")
def health():
    ok = _MODEL is not None
    payload = {"ok": ok}
    if not ok:
        payload["error"] = _MODEL_ERROR or "Model not loaded."
    return payload


@app.post("/predict")
async def predict(
    image: UploadFile = File(...),
    expected: Optional[str] = Form("")
):
    allow_skip = os.environ.get("DONATION_AI_ALLOW_SKIP", "0").strip() == "1"
    if _MODEL is None:
        load_model()
    if _MODEL is None:
        msg = "Validation ignorée (modèle absent)."
        if allow_skip:
            return {"ok": True, "message": msg, "skipped": True}
        detail = _MODEL_ERROR or "Modèle IA non configuré."
        return {"ok": False, "message": detail}

    raw = await image.read()
    prepared = prepare_image_bytes(raw)
    if prepared is None:
        return {"ok": False, "message": "Image invalide."}

    imgsz = int(os.environ.get("YOLO_IMG_SIZE", "224"))
    conf = float(os.environ.get("DONATION_AI_CONFIDENCE", "0.45"))
    max_det = int(os.environ.get("YOLO_MAX_DET", "1"))
    device = os.environ.get("YOLO_DEVICE", "cpu").strip()
    half = os.environ.get("YOLO_HALF", "0").strip() == "1"

    start = time.time()
    try:
        results = _MODEL(
            prepared,
            verbose=False,
            imgsz=imgsz,
            conf=conf,
            max_det=max_det,
            device=device or "cpu",
            half=half,
            save=False,
            save_txt=False,
            save_conf=False,
            save_crop=False,
        )
    except Exception:
        return {"ok": False, "message": "Erreur lors de l'analyse de l'image."}

    duration_ms = int((time.time() - start) * 1000)
    if not results:
        return {"ok": False, "message": "Aucune prédiction trouvée."}

    predictions = pick_top_predictions(results[0], top_k=int(os.environ.get("DONATION_AI_TOPK", "5")))
    if not predictions:
        return {"ok": False, "message": "Impossible d'interpréter la prédiction."}

    expected_norm = normalize(expected)
    best_label, best_conf = predictions[0]

    if expected_norm == "":
        return {"ok": True, "message": "Prédiction générée.", "label": best_label, "confidence": best_conf, "duration_ms": duration_ms}

    margin = float(os.environ.get("DONATION_AI_MARGIN", "0.0") or "0.0")
    best_match_conf = None
    best_match_label = None
    best_other_conf = 0.0

    for label, confidence in predictions:
        if confidence is None:
            continue
        predicted_norm = normalize(str(label))
        is_match = predicted_norm == expected_norm or matches_group(expected_norm, predicted_norm, _LABEL_GROUPS)
        if is_match:
            if best_match_conf is None or confidence > best_match_conf:
                best_match_conf = confidence
                best_match_label = label
        else:
            if confidence > best_other_conf:
                best_other_conf = confidence

    if best_match_conf is not None and best_match_conf >= conf:
        if margin <= 0.0 or (best_match_conf - best_other_conf) >= margin:
            return {"ok": True, "message": "OK", "label": best_match_label, "confidence": best_match_conf, "duration_ms": duration_ms}
        return {"ok": False, "message": "Confiance insuffisante pour valider le type de don.", "label": best_label, "confidence": best_conf, "duration_ms": duration_ms}

    return {"ok": False, "message": "L'image ne correspond pas au type de don.", "label": best_label, "confidence": best_conf, "duration_ms": duration_ms}
