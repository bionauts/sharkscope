"""Generate mock Mako-Sense tag data.

Produces a CSV: makosense_data.csv with columns:
    timestamp (ISO 8601, Z suffix)
    latitude  (decimal degrees)
    longitude (decimal degrees)
    prey_code (categorical event label)

Simulation model (conceptual):
- Shark starts at seed lat/lon.
- Position evolves via a simple 2D correlated random walk (bearing + step length jitter).
- At each step, a stochastic feeding event may occur (low probability base rate).
- Feeding event prey code sampled from weighted distribution.
- Non-feeding steps recorded as AMBIENT_WATER (to retain context) but we cap total rows ~15-20.

This script intentionally limits complexity (no bathymetry or ocean current coupling) while
emulating sparse prey detections.
"""
from __future__ import annotations
import csv
import math
import random
from datetime import datetime, timedelta, timezone
from pathlib import Path

OUTPUT_FILE = Path("makosense_data.csv")
N_ROWS_TARGET = 18  # 15-20 rows as specified
TIME_STEP_MIN = 17  # irregular cadence (minutes) base
TIME_STEP_JITTER_MIN = 8

# Starting geolocation (mid-ocean arbitrary)
START_LAT = 32.25
START_LON = -142.40

# Correlated random walk params
INITIAL_BEARING_DEG = 70.0
BEARING_DRIFT_STD = 25.0   # degrees
SPEED_MEAN_KMH = 7.5       # representative cruising speed
SPEED_STD_KMH = 2.0

# Earth radius mean (km)
EARTH_RADIUS_KM = 6371.0

PREY_CODES = [
    ("SEAL_LIPID", 0.10),   # Rarer, high-value detection
    ("FISH_PROTEIN", 0.50), # Most common
    ("SQUID_BEAK", 0.20),   # Moderate frequency
    ("AMBIENT_WATER", 0.20) # Background / no event marker
]

FEEDING_BASE_PROB = 0.25  # Probability that a non-ambient prey detection occurs at a step

random.seed(42)

def weighted_choice(pairs):
    r = random.random()
    acc = 0.0
    for item, w in pairs:
        acc += w
        if r <= acc:
            return item
    return pairs[-1][0]

def choose_prey():
    # Decide if an actual prey detection vs ambient
    if random.random() < FEEDING_BASE_PROB:
        # Re-weight excluding ambient
        non_ambient = [(c, w) for c, w in PREY_CODES if c != "AMBIENT_WATER"]
        # Normalize weights
        total = sum(w for _, w in non_ambient)
        scaled = [(c, w/total) for c, w in non_ambient]
        return weighted_choice(scaled)
    else:
        return "AMBIENT_WATER"

def step_position(lat, lon, bearing_deg, distance_km):
    """Great-circle forward computation (spherical approximation)."""
    lat_r = math.radians(lat)
    lon_r = math.radians(lon)
    brng = math.radians(bearing_deg)
    ang = distance_km / EARTH_RADIUS_KM

    new_lat = math.asin(math.sin(lat_r)*math.cos(ang) + math.cos(lat_r)*math.sin(ang)*math.cos(brng))
    new_lon = lon_r + math.atan2(math.sin(brng)*math.sin(ang)*math.cos(lat_r), math.cos(ang)-math.sin(lat_r)*math.sin(new_lat))
    return math.degrees(new_lat), (math.degrees(new_lon) + 540) % 360 - 180  # normalize lon

def main():
    rows = []
    now = datetime.now(timezone.utc).replace(microsecond=0)
    lat = START_LAT
    lon = START_LON
    bearing = INITIAL_BEARING_DEG

    while len(rows) < N_ROWS_TARGET:
        # Time increment
        dt_min = TIME_STEP_MIN + random.uniform(-TIME_STEP_JITTER_MIN, TIME_STEP_JITTER_MIN)
        now += timedelta(minutes=dt_min)

        # Movement
        speed_kmh = max(1.0, random.gauss(SPEED_MEAN_KMH, SPEED_STD_KMH))
        distance_km = speed_kmh * (dt_min / 60.0)
        bearing += random.gauss(0, BEARING_DRIFT_STD)
        bearing %= 360
        lat, lon = step_position(lat, lon, bearing, distance_km)

        prey_code = choose_prey()

        rows.append({
            "timestamp": now.isoformat().replace('+00:00', 'Z'),
            "latitude": f"{lat:.6f}",
            "longitude": f"{lon:.6f}",
            "prey_code": prey_code
        })

    # Ensure at least one of each non-ambient prey present (adjust last entries if required)
    ensure_codes = ["SEAL_LIPID", "FISH_PROTEIN", "SQUID_BEAK"]
    present = {r['prey_code'] for r in rows}
    for code in ensure_codes:
        if code not in present:
            # Replace a random AMBIENT_WATER
            for r in rows:
                if r['prey_code'] == 'AMBIENT_WATER':
                    r['prey_code'] = code
                    break

    with OUTPUT_FILE.open('w', newline='') as f:
        writer = csv.DictWriter(f, fieldnames=["timestamp", "latitude", "longitude", "prey_code"])
        writer.writeheader()
        writer.writerows(rows)

    print(f"Wrote {len(rows)} rows to {OUTPUT_FILE}")

if __name__ == "__main__":
    main()
