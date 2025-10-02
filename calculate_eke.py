#!/usr/bin/env python3
"""
Simple EKE calculation script using rasterio.
EKE = 0.5 * (u^2 + v^2)
"""
import sys
import numpy as np
import rasterio

def calculate_eke(ugos_path, vgos_path, output_path):
    """Calculate EKE from u and v velocity components."""
    try:
        with rasterio.open(ugos_path) as ugos_src:
            with rasterio.open(vgos_path) as vgos_src:
                # Read the data
                ugos_data = ugos_src.read(1)
                vgos_data = vgos_src.read(1)
                
                # Calculate EKE = 0.5 * (u^2 + v^2)
                eke_data = 0.5 * (ugos_data**2 + vgos_data**2)
                
                # Create output profile based on input
                profile = ugos_src.profile.copy()
                profile.update({
                    'dtype': 'float32',
                    'compress': 'lzw'
                })
                
                # Write the result
                with rasterio.open(output_path, 'w', **profile) as dst:
                    dst.write(eke_data.astype(np.float32), 1)
                    # Copy nodata value if available
                    if ugos_src.nodata is not None:
                        dst.nodata = ugos_src.nodata
        
        print(f"EKE calculation completed: {output_path}")
        return True
        
    except Exception as e:
        print(f"Error calculating EKE: {e}")
        import traceback
        traceback.print_exc()
        return False

if __name__ == "__main__":
    if len(sys.argv) != 4:
        print("Usage: python calculate_eke.py <ugos_file> <vgos_file> <output_file>")
        sys.exit(1)
    
    ugos_path = sys.argv[1]
    vgos_path = sys.argv[2]
    output_path = sys.argv[3]
    
    success = calculate_eke(ugos_path, vgos_path, output_path)
    sys.exit(0 if success else 1)