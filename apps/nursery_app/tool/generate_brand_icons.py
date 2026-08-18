#!/usr/bin/env python3
"""Generate distinct GreenLeaf / GreenHub launcher PNGs (legacy mipmap densities).

Customer: young two-leaf seedling on fresh green.
Admin: leaf inside a shield on deep green.

Vector adaptive foreground XML remains the source of truth on API 26+.
"""
from __future__ import annotations

import math
import struct
import zlib
from pathlib import Path

HERE = Path(__file__).resolve()
CUSTOMER_RES = HERE.parents[1] / "android/app/src/main/res"
ADMIN_RES = HERE.parents[2] / "nursery_admin_mobile/android/app/src/main/res"

CUSTOMER_BG = (45, 106, 79, 255)  # #2D6A4F
ADMIN_BG = (10, 36, 24, 255)  # #0A2418
STEM = (15, 61, 40, 255)
LEAF_LEFT = (149, 213, 178, 255)
LEAF_RIGHT = (232, 242, 235, 255)
SHIELD_FILL = (18, 61, 40, 255)
SHIELD_LINE = (212, 232, 219, 255)


def write_png(path: Path, w: int, h: int, rgba_rows: list[bytes]) -> None:
    raw = b"".join(b"\x00" + row for row in rgba_rows)

    def chunk(tag: bytes, data: bytes) -> bytes:
        crc = zlib.crc32(tag + data) & 0xFFFFFFFF
        return struct.pack(">I", len(data)) + tag + data + struct.pack(">I", crc)

    ihdr = struct.pack(">IIBBBBB", w, h, 8, 6, 0, 0, 0)
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("wb") as f:
        f.write(b"\x89PNG\r\n\x1a\n")
        f.write(chunk(b"IHDR", ihdr))
        f.write(chunk(b"IDAT", zlib.compress(raw, 9)))
        f.write(chunk(b"IEND", b""))


def dist_seg(px: float, py: float, ax: float, ay: float, bx: float, by: float) -> float:
    vx, vy = bx - ax, by - ay
    wx, wy = px - ax, py - ay
    c2 = vx * vx + vy * vy
    if c2 <= 1e-9:
        dx, dy = px - ax, py - ay
        return (dx * dx + dy * dy) ** 0.5
    t = max(0.0, min(1.0, (wx * vx + wy * vy) / c2))
    dx, dy = px - (ax + t * vx), py - (ay + t * vy)
    return (dx * dx + dy * dy) ** 0.5


def in_ellipse(px: float, py: float, cx: float, cy: float, rx: float, ry: float, rot: float) -> bool:
    c, s = math.cos(rot), math.sin(rot)
    dx, dy = px - cx, py - cy
    x = c * dx + s * dy
    y = -s * dx + c * dy
    if rx <= 0 or ry <= 0:
        return False
    return (x / rx) ** 2 + (y / ry) ** 2 <= 1.0


def in_shield(px: float, py: float, size: float) -> bool:
    # Convex-ish shield via two checks: top inverted-U + bottom point.
    cx, cy = size * 0.5, size * 0.5
    nx = (px - cx) / (size * 0.38)
    ny = (py - cy) / (size * 0.42)
    # Rounded top
    if ny < -0.15:
        return nx * nx + ((ny + 0.15) / 0.72) ** 2 <= 1.0
    # Taper to a point at the bottom
    t = (ny + 0.15) / 1.05
    half = 1.0 - t * t
    return abs(nx) <= max(0.0, half) and ny <= 0.95


def sample(kind: str, px: float, py: float, size: float, circular: bool) -> tuple[int, int, int, int]:
    cx = cy = size * 0.5
    r = size * 0.49
    dx, dy = px - cx, py - cy
    if circular and dx * dx + dy * dy > r * r:
        return (0, 0, 0, 0)

    bg = CUSTOMER_BG if kind == "customer" else ADMIN_BG
    col = bg

    if kind == "customer":
        if dist_seg(px, py, cx, size * 0.72, cx, size * 0.46) < size * 0.035:
            col = STEM
        if in_ellipse(px, py, size * 0.38, size * 0.50, size * 0.16, size * 0.09, -0.7):
            col = LEAF_LEFT
        if in_ellipse(px, py, size * 0.62, size * 0.46, size * 0.18, size * 0.10, 0.55):
            col = LEAF_RIGHT
    else:
        if in_shield(px, py, size):
            col = SHIELD_FILL
            shrunk = in_shield(cx + (px - cx) / 0.90, cy + (py - cy) / 0.90, size)
            if not shrunk:
                col = SHIELD_LINE
            if in_ellipse(px, py, size * 0.44, size * 0.50, size * 0.11, size * 0.07, -0.6):
                col = LEAF_LEFT
            if in_ellipse(px, py, size * 0.56, size * 0.46, size * 0.13, size * 0.08, 0.5):
                col = LEAF_RIGHT
        else:
            col = bg

    return col


def render(size: int, kind: str, *, circular: bool, ssaa: int = 1) -> list[bytes]:
    hi = size * ssaa
    acc = [[(0, 0, 0, 0) for _ in range(size)] for _ in range(size)]
    counts = [[0 for _ in range(size)] for _ in range(size)]
    for y in range(hi):
        oy = y // ssaa
        py = (y + 0.5) / hi * size
        for x in range(hi):
            ox = x // ssaa
            px = (x + 0.5) / hi * size
            r, g, b, a = sample(kind, px, py, size, circular)
            prev = acc[oy][ox]
            acc[oy][ox] = (prev[0] + r, prev[1] + g, prev[2] + b, prev[3] + a)
            counts[oy][ox] += 1
    rows = []
    n = ssaa * ssaa
    for y in range(size):
        row = bytearray()
        for x in range(size):
            c = counts[y][x] or n
            p = acc[y][x]
            row.extend(
                (
                    min(255, p[0] // c),
                    min(255, p[1] // c),
                    min(255, p[2] // c),
                    min(255, p[3] // c),
                )
            )
        rows.append(bytes(row))
    return rows


DENSITIES = {
    "mipmap-mdpi": 48,
    "mipmap-hdpi": 72,
    "mipmap-xhdpi": 96,
    "mipmap-xxhdpi": 144,
    "mipmap-xxxhdpi": 192,
}


def write_set(res: Path, kind: str) -> None:
    for folder, size in DENSITIES.items():
        square = render(size, kind, circular=False)
        round_ = render(size, kind, circular=True)
        write_png(res / folder / "ic_launcher.png", size, size, square)
        write_png(res / folder / "ic_launcher_round.png", size, size, round_)
    print("wrote", kind, res)


def main() -> None:
    write_set(CUSTOMER_RES, "customer")
    write_set(ADMIN_RES, "admin")


if __name__ == "__main__":
    main()
