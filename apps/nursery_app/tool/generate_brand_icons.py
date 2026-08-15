#!/usr/bin/env python3
"""Generate GreenLeaf launcher PNGs (legacy mipmap densities)."""
from __future__ import annotations

import math
import os
import struct
import zlib

ROOTS = [
    "/Users/nuramin/Desktop/Nursery_Platform/apps/nursery_app/android/app/src/main/res",
    "/Users/nuramin/Desktop/Nursery_Platform/apps/nursery_admin_mobile/android/app/src/main/res",
]

BG = (15, 61, 40, 255)  # #0F3D28
LEAF = (232, 242, 235, 255)  # #E8F2EB
VEIN = (15, 61, 40, 90)


def cubic(p0, p1, p2, p3, t):
    u = 1 - t
    return (
        u**3 * p0[0] + 3 * u**2 * t * p1[0] + 3 * u * t**2 * p2[0] + t**3 * p3[0],
        u**3 * p0[1] + 3 * u**2 * t * p1[1] + 3 * u * t**2 * p2[1] + t**3 * p3[1],
    )


def leaf_polygon(size: int, pad: float):
    pts = []
    segs = [
        ((0.52, 0.18), (0.78, 0.28), (0.86, 0.55), (0.62, 0.78)),
        ((0.62, 0.78), (0.48, 0.90), (0.32, 0.82), (0.28, 0.62)),
        ((0.28, 0.62), (0.22, 0.38), (0.34, 0.22), (0.52, 0.18)),
    ]
    inner = size * (1 - 2 * pad)
    origin = size * pad
    for a, b, c, d in segs:
        for i in range(24):
            x, y = cubic(a, b, c, d, i / 24)
            pts.append((origin + x * inner, origin + y * inner))
    return pts


def point_in_poly(x, y, poly):
    n = len(poly)
    inside = False
    j = n - 1
    for i in range(n):
        xi, yi = poly[i]
        xj, yj = poly[j]
        if ((yi > y) != (yj > y)) and (x < (xj - xi) * (y - yi) / (yj - yi + 1e-9) + xi):
            inside = not inside
        j = i
    return inside


def write_png(path, w, h, rgba_rows):
    raw = b"".join(b"\x00" + row for row in rgba_rows)

    def chunk(tag, data):
        crc = zlib.crc32(tag + data) & 0xFFFFFFFF
        return struct.pack(">I", len(data)) + tag + data + struct.pack(">I", crc)

    ihdr = struct.pack(">IIBBBBB", w, h, 8, 6, 0, 0, 0)
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, "wb") as f:
        f.write(b"\x89PNG\r\n\x1a\n")
        f.write(chunk(b"IHDR", ihdr))
        f.write(chunk(b"IDAT", zlib.compress(raw, 9)))
        f.write(chunk(b"IEND", b""))


def render(size: int, *, pad: float, transparent_bg: bool):
    poly = leaf_polygon(size, pad)
    rows = []
    cx = cy = size / 2
    r = size * 0.48
    for y in range(size):
        row = bytearray()
        for x in range(size):
            if transparent_bg:
                px = (0, 0, 0, 0)
                # circular mask slightly inside for adaptive foreground
                if (x - cx) ** 2 + (y - cy) ** 2 <= (size * 0.48) ** 2 and point_in_poly(
                    x + 0.5, y + 0.5, poly
                ):
                    px = LEAF
            else:
                if (x - cx) ** 2 + (y - cy) ** 2 <= r * r:
                    px = BG
                else:
                    px = (0, 0, 0, 0)
                if point_in_poly(x + 0.5, y + 0.5, poly):
                    px = LEAF
            row.extend(px)
        rows.append(bytes(row))
    return rows


DENSITIES = {
    "mipmap-mdpi": 48,
    "mipmap-hdpi": 72,
    "mipmap-xhdpi": 96,
    "mipmap-xxhdpi": 144,
    "mipmap-xxxhdpi": 192,
}

FG = {
    "mipmap-mdpi": 108,
    "mipmap-hdpi": 162,
    "mipmap-xhdpi": 216,
    "mipmap-xxhdpi": 324,
    "mipmap-xxxhdpi": 432,
}


def main():
    for root in ROOTS:
        for folder, size in DENSITIES.items():
            rows = render(size, pad=0.18, transparent_bg=False)
            write_png(os.path.join(root, folder, "ic_launcher.png"), size, size, rows)
            write_png(os.path.join(root, folder, "ic_launcher_round.png"), size, size, rows)
        # Adaptive foreground PNGs (also referenced from XML)
        rows = render(432, pad=0.22, transparent_bg=True)
        write_png(os.path.join(root, "drawable-xxxhdpi", "ic_launcher_foreground.png"), 432, 432, rows)
        rows = render(192, pad=0.18, transparent_bg=False)
        write_png(os.path.join(root, "drawable-xxxhdpi", "splash_logo.png"), 192, 192, rows)
        print("wrote", root)


if __name__ == "__main__":
    main()
