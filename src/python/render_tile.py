#!/usr/bin/env python3
"""
Render a 256x256 PNG tile from a raster given geographic bounds.

Usage:
  python render_tile.py <raster_path> <minLon> <minLat> <maxLon> <maxLat> <out_png>

Notes:
- Expects TCHI-like values in [0,1]. Values outside are clamped.
- Uses Rasterio + GDAL PNG driver to write the image.
"""
import sys
import math
import numpy as np
import rasterio
from rasterio.warp import reproject, Resampling
from rasterio.transform import from_bounds

def parse_args(argv):
    if len(argv) != 7:
        print("Usage: render_tile.py <raster_path> <minLon> <minLat> <maxLon> <maxLat> <out_png>", file=sys.stderr)
        sys.exit(2)
    raster_path = argv[1]
    minLon = float(argv[2]); minLat = float(argv[3]); maxLon = float(argv[4]); maxLat = float(argv[5])
    out_png = argv[6]
    return raster_path, minLon, minLat, maxLon, maxLat, out_png

def color_map(values):
    # values: 2D array in [0,1] with NaN for nodata
    # Color stops (similar to tiles.php gdaldem color-relief)
    stops = [
        (0.0, (10, 25, 47)),    # deep blue
        (0.2, (20, 50, 94)),    # medium blue
        (0.4, (46, 204, 113)),  # green
        (0.6, (241, 196, 15)),  # yellow
        (0.8, (231, 76, 60)),   # red
        (1.0, (231, 76, 60)),   # red
    ]

    v = np.clip(values, 0.0, 1.0)
    h, w = v.shape
    r = np.zeros((h, w), dtype=np.uint8)
    g = np.zeros((h, w), dtype=np.uint8)
    b = np.zeros((h, w), dtype=np.uint8)
    a = np.where(np.isnan(values), 0, 255).astype(np.uint8)

    # For non-NaN pixels, interpolate between nearest stops
    mask = ~np.isnan(values)
    vv = v[mask]
    if vv.size == 0:
        return r, g, b, a

    pos = np.array([s[0] for s in stops])
    cols = np.array([s[1] for s in stops], dtype=np.float32)  # shape (6,3)

    # For each pixel value, find the segment index i such that pos[i] <= val <= pos[i+1]
    # Use searchsorted over the flattened array
    indices = np.searchsorted(pos, vv, side='right') - 1
    indices = np.clip(indices, 0, len(stops)-2)
    left = pos[indices]
    right = pos[indices+1]
    span = (right - left)
    span[span == 0] = 1.0
    t = (vv - left) / span

    left_col = cols[indices]
    right_col = cols[indices+1]
    interp = (1.0 - t)[:, None] * left_col + t[:, None] * right_col

    # assign back
    r_arr = r.copy(); g_arr = g.copy(); b_arr = b.copy()
    r_arr[mask] = interp[:,0].astype(np.uint8)
    g_arr[mask] = interp[:,1].astype(np.uint8)
    b_arr[mask] = interp[:,2].astype(np.uint8)
    return r_arr, g_arr, b_arr, a

def main():
    raster_path, minLon, minLat, maxLon, maxLat, out_png = parse_args(sys.argv)

    width = 256; height = 256
    dst_transform = from_bounds(minLon, minLat, maxLon, maxLat, width, height)

    # Destination array (float32), will hold reprojected values
    dst = np.zeros((height, width), dtype=np.float32)
    dst[:] = np.nan

    with rasterio.Env():
        with rasterio.open(raster_path) as src:
            # Use reproject directly from the raster band to the destination grid in EPSG:4326
            reproject(
                source=rasterio.band(src, 1),
                destination=dst,
                src_transform=src.transform,
                src_crs=src.crs,
                dst_transform=dst_transform,
                dst_crs='EPSG:4326',
                resampling=Resampling.bilinear,
                dst_nodata=np.nan,
            )

    r, g, b, a = color_map(dst)

    # Write PNG via GDAL's PNG driver through Rasterio
    meta = {
        'driver': 'PNG',
        'dtype': 'uint8',
        'count': 4,
        'width': width,
        'height': height,
    }
    with rasterio.open(out_png, 'w', **meta) as dst_png:
        dst_png.write(r, 1)
        dst_png.write(g, 2)
        dst_png.write(b, 3)
        dst_png.write(a, 4)

if __name__ == '__main__':
    main()
