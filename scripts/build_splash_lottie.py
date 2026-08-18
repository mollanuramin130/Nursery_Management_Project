#!/usr/bin/env python3
"""Strip phone frame + FOZZY lettering from the source splash Lottie.

Keeps only the motion layers (seed drop, green bloom, basket) for use with
Flutter-drawn GreenLeaf wordmark overlays.
"""
from __future__ import annotations

import copy
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / "splash creen.json"
OUT_PATHS = [
    ROOT / "apps/nursery_app/assets/lottie/splash_motion.json",
    ROOT / "apps/nursery_admin_mobile/assets/lottie/splash_motion.json",
]

KEEP_TOKENS = ("Red Dot", "Baskett", "BG to Square", "Red BG for Oval")
DROP_NAMES = {"F", "O", "Z", "Z-2", "Y", "1", "2", "3", "4", "5", "Neutral Grey BG"}
DROP_FRAGMENTS = ("Device", "Splash Screen", "E4E4E4", "Square Red")
END_FRAME = 100.0


def should_keep(layer: dict) -> bool:
    name = layer.get("nm", "")
    if any(token in name for token in KEEP_TOKENS):
        return True
    if name in DROP_NAMES:
        return False
    if any(fragment in name for fragment in DROP_FRAGMENTS):
        return False
    if "Neutral Grey" in name:
        return False
    return False


def trim_layer(layer: dict) -> dict:
    layer = copy.deepcopy(layer)
    layer["op"] = END_FRAME
    ks = layer.get("ks", {})
    for prop in ks.values():
        if (
            isinstance(prop, dict)
            and prop.get("a") == 1
            and isinstance(prop.get("k"), list)
        ):
            prop["k"] = [k for k in prop["k"] if k.get("t", 0) <= END_FRAME]
    return layer


def build() -> dict:
    data = json.loads(SRC.read_text())
    kept = [trim_layer(layer) for layer in data["layers"] if should_keep(layer)]
    if not kept:
        raise SystemExit("No motion layers kept — check source Lottie structure.")

    return {
        "v": data["v"],
        "fr": data["fr"],
        "ip": 0,
        "op": END_FRAME,
        "w": data["w"],
        "h": data["h"],
        "nm": "GreenLeaf Splash Motion",
        "ddd": 0,
        "assets": [],
        "layers": kept,
        "markers": [],
    }


def main() -> None:
    if not SRC.exists():
        raise SystemExit(f"Missing source file: {SRC}")

    payload = build()
    for path in OUT_PATHS:
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(json.dumps(payload, separators=(",", ":")))
        print(f"Wrote {path.relative_to(ROOT)}")

    duration = END_FRAME / payload["fr"]
    print(f"Layers: {len(payload['layers'])}, duration: {duration:.2f}s")


if __name__ == "__main__":
    main()
