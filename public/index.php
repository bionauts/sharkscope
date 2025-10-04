<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>SharkScope by Team Bionauts</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Favicon -->
  <link rel="icon" type="image/png" href="assets/images/fav.png" />
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <!-- Bootstrap 5 for form controls -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Leaflet -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <!-- Chart.js -->
  <script defer src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>
  <!-- html2canvas for export -->
  <script defer src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
  <!-- Custom Styles -->
  <link rel="stylesheet" href="assets/css/style.css" />
  <?php
  // Load config to inject base URL
  $config = require_once __DIR__ . '/../config/config.php';
  $baseUrl = $config['app']['base_url'] ?? '';
  ?>
  <script>
    // Inject configuration from PHP
    window.SHARKSCOPE_CONFIG = {
      baseUrl: '<?php echo addslashes($baseUrl); ?>'
    };
  </script>
  <!-- Application JavaScript -->
  <script defer src="assets/js/app.js"></script>
</head>
<body>
<div id="app" class="app">
  <header>
    <div class="brand-logos">
      <img src="assets/images/sharkscope.png" alt="SharkScope" class="logo-sharkscope" />
      <span class="logo-divider">|</span>
      <img src="assets/images/bionauts.png" alt="Bionauts" class="logo-bionauts" />
    </div>
    <div class="grow"></div>
    <div class="toolbar" role="group" aria-label="Top controls">
      <div class="seg" role="group" aria-label="View mode">
        <button id="modeRisk" class="btn-toggle" aria-pressed="false" title="Show risk (SHSR)">Risk</button>
        <button id="modeProb" class="btn-toggle" aria-pressed="true" title="Show shark probability (TCHI)">Probability</button>
      </div>
  <button id="restaurantsBtn" class="btn btn-toggle" aria-pressed="true" title="Toggle TCHI hotspots">Hotspots</button>
      <button id="infoBtn" class="btn" aria-haspopup="dialog" aria-controls="infoPop">Info</button>
      <button id="creditsBtn" class="btn" aria-haspopup="dialog" aria-controls="creditsPop">Credits</button>
      <button id="exportBtn" class="btn">Export PNG</button>
      <button id="trackBtn" class="btn btn-toggle" aria-pressed="false" title="Toggle simulated shark track">Show Shark Track</button>
      <button id="packetDecoderBtn" class="btn" aria-haspopup="dialog" aria-controls="packetDecoderModal">Packet Decoder</button>
    </div>
  </header>

  <main>
    <section class="map-wrap" aria-label="Map">
      <div id="map" role="application" aria-label="Interactive map"></div>

      <div class="overlay top-center">
        <div class="row" style="justify-content:center">
          <button id="prevDay" class="btn" title="Previous day"><span class="kbd">Alt</span> ←</button>
          <label for="date" class="sr-only">Date</label>
          <input id="date" type="date" />
          <button id="nextDay" class="btn" title="Next day"><span class="kbd">Alt</span> →</button>
          <button id="play" class="btn">Play</button>
        </div>
      </div>

      <!-- <div class="overlay bottom-left">
  <div class="legend" aria-label="TCHI hotspot legend">
          <div id="legendTitle" style="font-size:12px;margin-bottom:4px">TCHI Hotspot Intensity</div>
          <div class="muted" style="font-size:12px">Red halos show peak shark suitability within a 100 km radius.</div>
        </div>
      </div> -->

      <div class="overlay bottom-center">
        <div style="background:var(--panel);border:1px solid var(--border);padding:8px 10px;border-radius:10px;min-width:280px">
          <div style="font-size:12px;margin-bottom:6px" class="muted">Available data dates</div>
          <input id="timeSlider" type="range" min="0" max="0" step="1" style="width:100%">
          <div class="range-hint"><span id="timeLabel">Loading…</span></div>
        </div>
      </div>
    </section>

    <aside class="sidebar" role="complementary" aria-labelledby="sideTitle">
      <div class="row" style="justify-content:space-between;align-items:center">
        <div id="sideTitle" class="title">Analysis</div>
        <div id="locLabel" class="muted">Click the map</div>
      </div>

      <div class="cards">
        <div class="card">
          <div class="title">TCHI Support Score</div>
          <div class="big" id="tchiScore">–/100</div>
        </div>
        <div class="card">
          <div class="title">SHSR Risk</div>
          <div class="big" id="shsrScore">–%</div>
        </div>
      </div>

      <div class="card">
        <div class="title">Factor contributions</div>
        <div class="bars" id="factorBars"></div>
      </div>

      <div class="card">
        <div class="title">Variable Map Layers</div>
        <div class="list-group">
          <label class="list-group-item d-flex justify-content-between align-items-center">
            Temperature (SST)
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" role="switch" id="toggle-sst">
            </div>
          </label>
          <label class="list-group-item d-flex justify-content-between align-items-center">
            Food-Rich Water (Chl-a)
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" role="switch" id="toggle-chla">
            </div>
          </label>
          <label class="list-group-item d-flex justify-content-between align-items-center">
            Ocean Whirlpools (EKE)
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" role="switch" id="toggle-eke">
            </div>
          </label>
          <label class="list-group-item d-flex justify-content-between align-items-center">
            Bathymetry
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" role="switch" id="toggle-bathy">
            </div>
          </label>
        </div>
      </div>

      <div class="card">
        <div class="title">Time-series</div>
        <canvas id="spark" height="120"></canvas>
      </div>

      <div class="row" style="margin-top:auto;justify-content:space-between">
        <button id="simulateBtn" class="btn btn-primary" disabled>Run Mako‑Sense Simulation</button>
        <div id="restSummary" class="muted" style="font-size:12px"></div>
      </div>
    </aside>
  </main>

  <footer>
  <div class="muted">SharkScope by Team Bionauts. <a href="https://github.com/bionauts/sharkscope" style="text-decoration: none;" target="_blank" rel="noopener noreferrer">Click here for License &amp; Usage</a></div>
    <div class="row"><span class="kbd">?</span> for help</div>
  </footer>
</div>

<!-- Packet Decoder Modal -->
<div id="packetDecoderBackdrop" class="backdrop" aria-hidden="true">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="packetDecoderTitle">
    <header><div id="packetDecoderTitle" class="title">Mako‑Sense Packet Decoder</div></header>
    <div class="body" style="display:flex;flex-direction:column;gap:12px">
      <div class="row" style="align-items:flex-end;gap:12px;flex-wrap:wrap">
        <label style="font-size:12px;display:flex;flex-direction:column;gap:4px">Select Event Timestamp
          <select id="packetEventSelect" style="min-width:220px"></select>
        </label>
        <button id="generatePacketBtn" class="btn">Generate Mock Packet</button>
        <button id="decodePacketBtn" class="btn btn-primary" disabled>Decode</button>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;min-height:140px">
        <div style="background:var(--panel);border:1px solid var(--border);padding:8px;border-radius:8px;display:flex;flex-direction:column">
          <div style="font-size:12px;font-weight:600;margin-bottom:6px">Raw Packet (Hex, 32 bytes)</div>
          <textarea id="rawPacketHex" spellcheck="false" style="flex:1;font-family:monospace;font-size:12px;line-height:1.3;resize:vertical;min-height:110px" placeholder="Click 'Generate Mock Packet'"></textarea>
        </div>
        <div style="background:var(--panel);border:1px solid var(--border);padding:8px;border-radius:8px;display:flex;flex-direction:column">
          <div style="font-size:12px;font-weight:600;margin-bottom:6px">Decoded Data</div>
          <div id="decodedPacket" class="muted" style="font-size:12px;white-space:pre-wrap;flex:1">No packet decoded yet.</div>
        </div>
      </div>
      <div class="muted" style="font-size:11px">Structure: [0-3] Timestamp | [4-7] Lat | [8-11] Lon | [12-13] Batt | [14] FW | [15] Flags | [16-23] Prey Code | [24-27] Confidence | [28-29] Spectral Hash | [30-31] CRC16</div>
    </div>
    <footer>
      <button id="closePacketDecoder" class="btn btn-primary">Close</button>
    </footer>
  </div>
</div>

<!-- Info and Credits popovers -->
<div id="infoPop" class="popover" role="dialog" aria-modal="false" aria-labelledby="infoTitle">
  <div id="infoTitle" class="title">How to Use SharkScope</div>
  <p class="muted" style="margin:6px 0 8px">
    <strong>Analyze Habitat:</strong> Click anywhere on the ocean to generate a detailed analysis in the sidebar.
    <br><strong>Change Layers:</strong> Use the toggle buttons in the header to switch between the TCHI Probability, SHSR Risk, and TCHI/SST blended heatmaps.
    <br><strong>Explore Time:</strong> Use the date controls and the time-slider at the bottom to see how shark habitats change over time.
  </p>
</div>

<div id="creditsPop" class="popover" role="dialog" aria-modal="false" aria-labelledby="credTitle">
  <div id="credTitle" class="title">Data Sources & Licensing</div>
  <p class="muted" style="margin:6px 0 8px">
    <strong>NASA Data (Public Domain):</strong> SST from PODAAC, Chl-a from OceanData, Base Map from GIBS.
    <br><strong>Partner Data:</strong> EKE from Copernicus Marine Service (CMEMS), Base Map Fallback from OpenStreetMap (ODbL).
    <br><strong>Process:</strong> All layers are processed daily by the SharkScope TCHI model. This project was created for the NASA Space Apps Challenge and all code is open-source (MIT License).
  </p>
  <div style="margin-top:12px;padding:8px;background:rgba(241,196,15,0.1);border:1px solid rgba(241,196,15,0.3);border-radius:6px">
    <div style="font-size:12px;font-weight:600;color:#F1C40F;margin-bottom:4px">⚠️ Demo Dataset Notice</div>
    <p class="muted" style="font-size:11px;margin:0">
      Due to data size constraints, this demo currently displays <strong>September 5, 2025</strong> only. 
      The complete processing pipeline, data acquisition scripts, and setup guidelines are included in the source code. 
      <a href="https://github.com/bionauts/sharkscope" target="_blank" style="color:#3BA3FF">Check the repository</a> to process additional dates.
    </p>
  </div>
</div>

<!-- Simulation Modal -->
<div id="simBackdrop" class="backdrop" aria-hidden="true">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="simTitle">
    <header><div id="simTitle" class="title">Mako‑Sense Simulation</div></header>
    <div class="body">
      <div class="kpis">
        <div class="kpi"><div class="muted">Previous TCHI</div><div class="big" id="prevTchi">–</div></div>
        <div class="kpi"><div class="muted">Refined TCHI</div><div class="big" id="refinedTchi">–</div></div>
        <div class="kpi"><div class="muted">Δ TCHI / Δ SHSR</div><div class="big" id="deltaTchi">–</div></div>
      </div>
      <div class="compare">
        <div class="panel"><div class="label">Before</div><div id="mapBefore" style="position:absolute;inset:0"></div></div>
        <div class="panel"><div class="label">After</div><div id="mapAfter" style="position:absolute;inset:0"></div></div>
      </div>
      <div class="muted">Visualization: Localized prey confirmation increases suitability within ~5 km (green circle).</div>
    </div>
    <footer>
      <button id="rerunSim" class="btn">Re‑run</button>
      <button id="closeSim" class="btn btn-primary">Close</button>
    </footer>
  </div>
</div>

</body>
</html>