import sys
import rasterio
import numpy as np

def query_raster_value(raster_path, lon, lat):
    try:
        with rasterio.open(raster_path) as src:
            # The 'index' method converts lon/lat to the raster's row/col
            row, col = src.index(lon, lat)
            
            # Read just the single pixel value we need
            value = src.read(1, window=((row, row + 1), (col, col + 1)))[0][0]
            
            # Check against the file's nodata value
            if src.nodata is not None and np.isclose(value, src.nodata):
                return "nan"

            # Return the value as a standard float
            return float(value)
    except IndexError:
        # This error occurs if the coordinate is outside the raster's bounds
        return "nan"
    except Exception as e:
        # Return any other errors
        return f"Error: {e}"

if __name__ == "__main__":
    if len(sys.argv) != 4:
        print("Usage: python query_raster.py <raster_path> <longitude> <latitude>")
        sys.exit(1)
    
    raster_file = sys.argv[1]
    longitude = float(sys.argv[2])
    latitude = float(sys.argv[3])
    
    result = query_raster_value(raster_file, longitude, latitude)
    print(result)