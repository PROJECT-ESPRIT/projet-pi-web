#!/usr/bin/env python3

import argparse
import json
from pathlib import Path

SUPPORTED_IMAGE_EXTENSIONS = {
    "bmp",
    "dng",
    "heic",
    "jpeg",
    "jpg",
    "mpo",
    "pfm",
    "png",
    "tif",
    "tiff",
    "webp",
}


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Scan an image with YOLO and return JSON detections.")
    parser.add_argument("image", help="Absolute path to the image file")
    parser.add_argument("--conf", type=float, default=0.45, help="Confidence threshold")
    parser.add_argument("--model", default="yolov8n.pt", help="YOLO model file or model name")
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    image_path = Path(args.image)

    if not image_path.is_file():
        print(json.dumps({"success": False, "error": "Image not found", "detections": []}))
        return 1

    try:
        from ultralytics import YOLO

        model = YOLO(args.model)
        suffix = image_path.suffix.lower().lstrip(".")

        # Uploads from Symfony/PHP often use extensionless temp files like /tmp/phpXYZ.
        # YOLO path-based source detection can reject these, so we fallback to PIL loading.
        if suffix in SUPPORTED_IMAGE_EXTENSIONS:
            try:
                results = model.predict(source=str(image_path), conf=args.conf, verbose=False)
            except Exception:
                from PIL import Image

                with Image.open(image_path) as pil_image:
                    results = model.predict(source=pil_image.convert("RGB"), conf=args.conf, verbose=False)
        else:
            from PIL import Image

            with Image.open(image_path) as pil_image:
                results = model.predict(source=pil_image.convert("RGB"), conf=args.conf, verbose=False)
    except Exception as exc:
        print(json.dumps({"success": False, "error": str(exc), "detections": []}))
        return 1

    detections = []
    for result in results:
        boxes = getattr(result, "boxes", None)
        if boxes is None:
            continue

        for box in boxes:
            class_idx = int(box.cls)
            detections.append(
                {
                    "class": result.names[class_idx],
                    "confidence": round(float(box.conf), 4),
                    "coordinates": [round(float(value), 2) for value in box.xyxy[0].tolist()],
                }
            )

    payload = {
        "success": True,
        "count": len(detections),
        "detections": detections,
    }
    print(json.dumps(payload))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
