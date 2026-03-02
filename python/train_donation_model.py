#!/usr/bin/env python3
import argparse
import os
import shutil
import sys


def resolve_default_data():
    base = os.path.dirname(os.path.abspath(__file__))
    candidate = os.path.join(os.path.dirname(base), "datasets", "donation")
    return candidate


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--data", default=None, help="Dataset root or data YAML.")
    parser.add_argument("--model", default="yolov8n-cls.pt", help="Base model (classification).")
    parser.add_argument("--epochs", type=int, default=30)
    parser.add_argument("--imgsz", type=int, default=224)
    parser.add_argument("--batch", type=int, default=16)
    parser.add_argument("--device", default=os.environ.get("YOLO_DEVICE", "cpu"))
    parser.add_argument("--name", default="donation_cls")
    parser.add_argument("--output", default=None, help="Where to copy best model (e.g. models/donation.pt).")
    args = parser.parse_args()

    data = args.data or os.environ.get("DONATION_AI_DATASET") or resolve_default_data()
    if not os.path.exists(data):
        print(f"ERROR: dataset not found at {data}", file=sys.stderr)
        print("Expected structure: <data>/train/<class>/..., <data>/val/<class>/...", file=sys.stderr)
        return 2

    try:
        from ultralytics import YOLO
    except Exception as exc:
        print(f"ERROR: ultralytics import failed: {exc}", file=sys.stderr)
        return 3

    model = YOLO(args.model)
    model.train(
        data=data,
        epochs=args.epochs,
        imgsz=args.imgsz,
        batch=args.batch,
        device=args.device,
        name=args.name,
    )

    best_path = None
    trainer = getattr(model, "trainer", None)
    if trainer is not None:
        best_path = getattr(trainer, "best", None)

    if best_path and os.path.isfile(best_path):
        output = args.output or os.environ.get("DONATION_AI_MODEL") or os.path.join(
            os.path.dirname(os.path.abspath(__file__)), "..", "models", "donation.pt"
        )
        output = os.path.abspath(output)
        os.makedirs(os.path.dirname(output), exist_ok=True)
        shutil.copy(best_path, output)
        print(f"Saved best model to: {output}")
    else:
        print("Training completed, but best model path was not found.")
        print("Check runs/classify/*/weights/best.pt")

    return 0


if __name__ == "__main__":
    sys.exit(main())
