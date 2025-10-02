#!/usr/bin/env python3
"""
General raster calculation script using rasterio.
Replaces gdal_calc.py functionality for basic mathematical operations.
"""
import sys
import numpy as np
import rasterio
import argparse
from rasterio.enums import Resampling

def safe_eval(expression, data_dict):
    """Safely evaluate mathematical expressions with numpy functions."""
    # Replace common mathematical functions
    expression = expression.replace('exp(', 'np.exp(')
    expression = expression.replace('log(', 'np.log(')
    expression = expression.replace('sqrt(', 'np.sqrt(')
    expression = expression.replace('sin(', 'np.sin(')
    expression = expression.replace('cos(', 'np.cos(')
    expression = expression.replace('tan(', 'np.tan(')
    
    # Create a safe environment for evaluation
    safe_env = {
        "__builtins__": {},
        "__import__": None,
        "__name__": None,
        "__file__": None,
        "np": np,
        "exp": np.exp,
        "log": np.log,
        "sqrt": np.sqrt,
        "sin": np.sin,
        "cos": np.cos,
        "tan": np.tan,
    }
    safe_env.update(data_dict)
    
    return eval(expression, safe_env)

def calculate_raster(input_files, output_file, calc_expression, nodata_value=None):
    """Perform raster calculations similar to gdal_calc.py."""
    try:
        # Open all input files
        datasets = {}
        data_arrays = {}
        reference_profile = None
        
        for var, filepath in input_files.items():
            with rasterio.open(filepath) as src:
                datasets[var] = src
                data_arrays[var] = src.read(1)
                if reference_profile is None:
                    reference_profile = src.profile.copy()
        
        # Perform the calculation
        result = safe_eval(calc_expression, data_arrays)
        
        # Update profile for output
        reference_profile.update({
            'dtype': 'float32',
            'compress': 'lzw'
        })
        
        if nodata_value is not None:
            reference_profile['nodata'] = nodata_value
        
        # Write the result
        with rasterio.open(output_file, 'w', **reference_profile) as dst:
            dst.write(result.astype(np.float32), 1)
        
        print(f"Calculation completed: {output_file}")
        return True
        
    except Exception as e:
        print(f"Error in calculation: {e}")
        import traceback
        traceback.print_exc()
        return False

def main():
    parser = argparse.ArgumentParser(description='Raster calculator using rasterio')
    parser.add_argument('-A', '--input-a', help='Input file A')
    parser.add_argument('-B', '--input-b', help='Input file B')
    parser.add_argument('-C', '--input-c', help='Input file C')
    parser.add_argument('-D', '--input-d', help='Input file D')
    parser.add_argument('-E', '--input-e', help='Input file E')
    parser.add_argument('--calc', required=True, help='Calculation expression')
    parser.add_argument('--outfile', required=True, help='Output file')
    parser.add_argument('--NoDataValue', type=float, help='NoData value for output')
    parser.add_argument('--overwrite', action='store_true', help='Overwrite existing files')
    parser.add_argument('-co', action='append', help='Creation options (ignored)')
    
    args = parser.parse_args()
    
    # Build input files dictionary
    input_files = {}
    if args.input_a:
        input_files['A'] = args.input_a
    if args.input_b:
        input_files['B'] = args.input_b
    if args.input_c:
        input_files['C'] = args.input_c
    if args.input_d:
        input_files['D'] = args.input_d
    if args.input_e:
        input_files['E'] = args.input_e
    
    if not input_files:
        print("Error: No input files specified")
        return False
    
    success = calculate_raster(input_files, args.outfile, args.calc, args.NoDataValue)
    return success

if __name__ == "__main__":
    success = main()
    sys.exit(0 if success else 1)