#!/usr/bin/env python3
import argparse
import json
import os
import sys
import tempfile
import time


def emit(ok, message="", label=None, confidence=None, skipped=False, duration_ms=None):
    payload = {"ok": bool(ok)}
    if message:
        payload["message"] = message
    if label is not None:
        payload["label"] = label
    if confidence is not None:
        payload["confidence"] = float(confidence)
    if skipped:
        payload["skipped"] = True
    if duration_ms is not None:
        payload["duration_ms"] = int(duration_ms)
    print(json.dumps(payload))
    return 0


def ensure_tmpdir():
    path = os.environ.get("TMPDIR", "").strip()
    if not path:
        base = os.path.dirname(os.path.abspath(__file__))
        path = os.path.join(os.path.dirname(base), "var/tmp")
    if path:
        try:
            os.makedirs(path, exist_ok=True)
        except Exception:
            pass
        os.environ["TMPDIR"] = path


def normalize(text):
    return (text or "").strip().lower()


def prepare_image(path):
    try:
        from PIL import Image
    except Exception:
        return path, None

    try:
        img = Image.open(path)
        max_dim = int(os.environ.get("DONATION_AI_MAX_IMAGE", "1024"))
        needs_resize = max(img.size) > max_dim
        needs_rgb = img.mode != "RGB"
        if not needs_resize and not needs_rgb:
            return path, None
        if needs_rgb:
            img = img.convert("RGB")
        if needs_resize:
            img.thumbnail((max_dim, max_dim))
        fd, tmp_path = tempfile.mkstemp(suffix=".jpg")
        os.close(fd)
        img.save(tmp_path, format="JPEG", quality=85, optimize=True)
        return tmp_path, tmp_path
    except Exception:
        return path, None


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


def load_label_groups():
    path = os.environ.get("DONATION_AI_LABEL_GROUPS", "").strip()
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


def main():
    ensure_tmpdir()
    parser = argparse.ArgumentParser()
    parser.add_argument("--image", required=True)
    parser.add_argument("--expected", required=True)
    args = parser.parse_args()

    if not os.path.isfile(args.image):
        return emit(False, "Image introuvable pour validation.")

    model_path = os.environ.get("DONATION_AI_MODEL", "").strip()
    allow_skip = os.environ.get("DONATION_AI_ALLOW_SKIP", "1").strip() == "1"
    if not model_path or not os.path.isfile(model_path):
        msg = "Validation ignorée (modèle absent)."
        return emit(True, msg, skipped=True) if allow_skip else emit(False, "Modèle IA non configuré.")

    try:
        from ultralytics import YOLO
    except Exception:
        msg = "Validation ignorée (ultralytics manquant)."
        return emit(True, msg, skipped=True) if allow_skip else emit(False, "YOLO n'est pas installé.")

    normalized_path, temp_path = prepare_image(args.image)
    try:
        model = YOLO(model_path)
        imgsz = int(os.environ.get("YOLO_IMG_SIZE", "224"))
        conf = float(os.environ.get("DONATION_AI_CONFIDENCE", "0.45"))
        max_det = int(os.environ.get("YOLO_MAX_DET", "1"))
        device = os.environ.get("YOLO_DEVICE", "cpu").strip()
        half = os.environ.get("YOLO_HALF", "0").strip() == "1"
        start = time.time()
        results = model(
            normalized_path,
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
        duration_ms = int((time.time() - start) * 1000)
        if not results:
            return emit(False, "Aucune prédiction trouvée.")
        predictions = pick_top_predictions(results[0], top_k=int(os.environ.get("DONATION_AI_TOPK", "5")))
        if not predictions:
            return emit(False, "Impossible d'interpréter la prédiction.")
    except Exception:
        return emit(False, "Erreur lors de l'analyse de l'image.")
    finally:
        if temp_path and os.path.isfile(temp_path):
            try:
                os.remove(temp_path)
            except Exception:
                pass

    expected = normalize(args.expected)
    best_label, best_conf = predictions[0]
    groups = load_label_groups()
    if expected == "":
        return emit(True, "Prédiction générée.", label=best_label, confidence=best_conf, duration_ms=duration_ms)

    margin = float(os.environ.get("DONATION_AI_MARGIN", "0.0") or "0.0")
    best_match_conf = None
    best_match_label = None
    best_other_conf = 0.0

    for label, confidence in predictions:
        if confidence is None:
            continue
        predicted = normalize(str(label))
        is_match = predicted == expected or matches_group(expected, predicted, groups)
        if is_match:
            if best_match_conf is None or confidence > best_match_conf:
                best_match_conf = confidence
                best_match_label = label
        else:
            if confidence > best_other_conf:
                best_other_conf = confidence

    if best_match_conf is not None and best_match_conf >= conf:
        if margin <= 0.0 or (best_match_conf - best_other_conf) >= margin:
            return emit(True, "OK", label=best_match_label, confidence=best_match_conf, duration_ms=duration_ms)
        return emit(
            False,
            "Confiance insuffisante pour valider le type de don.",
            label=best_label,
            confidence=best_conf,
            duration_ms=duration_ms,
        )

    return emit(
        False,
        "L'image ne correspond pas au type de don.",
        label=best_label,
        confidence=best_conf,
        duration_ms=duration_ms,
    )


if __name__ == "__main__":
    sys.exit(main())
