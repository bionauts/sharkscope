"""
Analyzes a GeoTIFF raster to find the coordinates of the top N highest values.
"""
import sys
import json
import numpy as np
import rasterio
from scipy.ndimage import maximum_filter


def find_hotspots(raster_path, top_n=10):
    try:
        with rasterio.open(raster_path) as src:
            data = src.read(1)
            
            # Use the nodata value from the file, or fall back to our known default
            nodata_val = src.nodata if src.nodata is not None else -9999
            
            # Create a mask for valid data
            valid_mask = data != nodata_val
            if not np.any(valid_mask):
                return []

            # Use a maximum filter to find local peaks (hotspots)
            # This is better than just the absolute top N pixels, which might be clustered together
            neighborhood_size = 15 # A pixel window to define a "local" area
            local_max = maximum_filter(data, size=neighborhood_size)
            is_peak = (data == local_max) & valid_mask
            
            peak_rows, peak_cols = np.where(is_peak)
            peak_values = data[peak_rows, peak_cols]
            
            # Get the indices of the top N peaks
            # Use argpartition for efficiency, it's faster than a full sort
            num_peaks = len(peak_values)
            n_to_find = min(top_n, num_peaks)
            if n_to_find == 0:
                return []

            top_indices = np.argpartition(peak_values, -n_to_find)[-n_to_find:]

            # Get the coordinates and values for the top peaks
            hotspots = []
            for i in top_indices:
                row, col = peak_rows[i], peak_cols[i]
                # Convert pixel coordinates (row, col) to geographic coordinates (lon, lat)
                lon, lat = src.xy(row, col)
                hotspots.append({
                    'lat': lat,
                    'lon': lon,
                    'tchi_score': float(peak_values[i])
                })

            # Sort the final list from highest to lowest score and tag rank
            hotspots.sort(key=lambda x: x['tchi_score'], reverse=True)
            for idx, hotspot in enumerate(hotspots, start=1):
                hotspot['rank'] = idx
            return hotspots

    except Exception:
        return []

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"error": "Usage: python find_hotspots.py <raster_path> [top_n]"}), file=sys.stderr)
        sys.exit(1)

    raster_file = sys.argv[1]
    top_n = 10
    if len(sys.argv) >= 3:
        try:
            top_n = max(1, int(sys.argv[2]))
        except ValueError:
            top_n = 10

    results = find_hotspots(raster_file, top_n=top_n)
    print(json.dumps(results, indent=4))