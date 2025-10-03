from flask import Flask, request, jsonify
import json
import numpy as np
import rasterio
from scipy.ndimage import maximum_filter

# --- Core Logic from find_hotspots.py ---
def find_hotspots(raster_path, top_n=10):
    try:
        with rasterio.open(raster_path) as src:
            data = src.read(1)
            nodata_val = src.nodata if src.nodata is not None else -9999
            valid_mask = data != nodata_val
            if not np.any(valid_mask):
                return []

            neighborhood_size = 15
            local_max = maximum_filter(data, size=neighborhood_size)
            is_peak = (data == local_max) & valid_mask
            
            peak_rows, peak_cols = np.where(is_peak)
            peak_values = data[peak_rows, peak_cols]
            
            num_peaks = len(peak_values)
            n_to_find = min(top_n, num_peaks)
            if n_to_find == 0:
                return []

            top_indices = np.argpartition(peak_values, -n_to_find)[-n_to_find:]
            
            hotspots = []
            for i in top_indices:
                row, col = peak_rows[i], peak_cols[i]
                lon, lat = src.xy(row, col)
                hotspots.append({
                    'lat': lat,
                    'lon': lon,
                    'tchi_score': float(peak_values[i])
                })

            hotspots.sort(key=lambda x: x['tchi_score'], reverse=True)
            for idx, hotspot in enumerate(hotspots, start=1):
                hotspot['rank'] = idx
            return hotspots
    except Exception:
        return []

# --- Core Logic from query_raster.py ---
def query_raster_value(raster_path, lon, lat):
    try:
        with rasterio.open(raster_path) as src:
            row, col = src.index(lon, lat)
            value = src.read(1, window=((row, row + 1), (col, col + 1)))[0][0]
            if src.nodata is not None and np.isclose(value, src.nodata):
                return "nan"
            return float(value)
    except IndexError:
        return "nan"
    except Exception as e:
        return f"Error: {e}"

# --- Flask Web Service ---
app = Flask(__name__)

@app.route('/')
def index():
    return "SharkScope Python Analysis Service is running."

@app.route('/hotspots')
def get_hotspots_api():
    raster_path = request.args.get('path')
    try:
        count = int(request.args.get('count', 10))
    except (ValueError, TypeError):
        count = 10
    
    if not raster_path:
        return jsonify({"error": "Missing 'path' parameter"}), 400
    
    results = find_hotspots(raster_path, top_n=count)
    return jsonify(results)

@app.route('/query')
def query_raster_api():
    raster_path = request.args.get('path')
    lon = request.args.get('lon')
    lat = request.args.get('lat')

    if not all([raster_path, lon, lat]):
        return jsonify({"error": "Missing 'path', 'lon', or 'lat' parameter"}), 400

    try:
        result = query_raster_value(raster_path, float(lon), float(lat))
        return jsonify({"value": result})
    except (ValueError, TypeError):
        return jsonify({"error": "Invalid lon/lat parameters"}), 400

# The passenger_wsgi.py file created by cPanel will import and run this 'app' object.