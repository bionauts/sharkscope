# SharkScope Project Data Guide 🦈

This guide explains how to manage the NetCDF (`.nc`) data files required for the SharkScope project. All raw data is excluded from our Git repository to keep it fast and lightweight.

-----

## 🚀 Quickstart

1.  **Create a `.netrc` file** in your user home directory for Earthdata authentication.
2.  Download the required sample datasets (see "How to Get the Data" below).
3.  Place the downloaded `.nc` files into the `data/raw/<dataset-name>/` directory.
4.  You're ready to run the processing scripts like `run_processor.php`\!

-----

## 📂 Data Directory Structure

All data should live inside the `/data` directory at the project root. The code will look for input files here by default.

  * **`data/raw/`**: This is where you must place the original, unmodified `.nc` files you download.
  * **`data/processed/`**: Our scripts will automatically save their final outputs (like our daily TCHI maps) here. You don't need to create anything in this folder manually.
  * **`data/static/`**: For datasets that don't change, like our Bathymetry map.

Example Layout:

```
data/
├── raw/
│   ├── MUR-SST/
│   │   └── 20250905090000-JPL-L4_GHRSST-SSTfnd-MUR-GLOB-v02.0-fv04.1.nc
│   └── VIIRS-CHLA/
│       └── ...some_chla_file.nc
├── processed/
└── static/
    └── bathymetry.tif
```

-----

## 📥 How to Get the Data

You must download the datasets required by our `TchiProcessor.php` script from their official sources.

1.  **Sea Surface Temperature (SST):**

      * **Product:** MUR L4 Global SST
      * **Source:** [NASA PO.DAAC](https://www.google.com/search?q=https://podaac.earthdata.nasa.gov/)
      * **Action:** Place the downloaded `.nc` file(s) in `data/raw/MUR-SST/`.

2.  **Chlorophyll-a (Chl-a):**

      * **Product:** VIIRS L3 Chl-a
      * **Source:** [NASA Ocean Color](https://oceancolor.gsfc.nasa.gov/)
      * **Action:** Place the downloaded `.nc` file(s) in `data/raw/VIIRS-CHLA/`.

3.  **(And so on for EKE and Bathymetry)...**

-----

## 🔐 Authentication (Earthdata Login)

Most NASA data portals require you to log in to download files. Our scripts handle this automatically, but you **must** configure it first.

  * **Requirement:** Create a text file named `_netrc` (on Windows) or `.netrc` (on Mac/Linux) in your main user home directory (`C:\Users\<YourUsername>` or `~`).
  * **Contents:**
    ```
    machine urs.earthdata.nasa.gov
    login <your_username_here>
    password <your_password_here>
    ```

-----

## Git Policy & Reproducibility

  * **Do not commit `.nc` files\!** Our `.gitignore` file is set up to prevent this. Committing large data files will slow down the repository for everyone.
  * To ensure our work is reproducible, we will document the exact **version, source URL, and download date** of the datasets we use in our main project `README.md`.

-----

## 🛠️ Troubleshooting

  * **File Not Found Error:** Make sure the `.nc` files are in the correct subdirectory inside `data/raw/`.
  * **Authentication Error (401 Unauthorized):** Double-check that your `.netrc` file is in the correct location and its contents are correct.