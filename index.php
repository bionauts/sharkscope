<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>SharkScope — Shark Habitat Support (Demo, Mock Only)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <!-- Leaflet -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <!-- Chart.js -->
  <script defer src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>
  <!-- html2canvas for export -->
  <script defer src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
  <style>
    :root{
      --bg:#0A192F; --panel:#0F2547; --fg:#E6EDF3; --muted:#9AA4AE; --border:#1C325A;
      --accent:#3BA3FF; --good:#2ECC71; --mid:#F1C40F; --bad:#E74C3C; --focus:#86B7FE;
      --violet:#9b6cff;
    }
    *{box-sizing:border-box}
    html,body{height:100%;margin:0;background:var(--bg);color:var(--fg);font-family:Inter,system-ui,Segoe UI,Arial,sans-serif}
    a{color:#8bc1ff}
    .app{display:grid;grid-template-rows:auto 1fr auto;min-height:100%}
    header{display:flex;align-items:center;gap:12px;padding:10px 14px;border-bottom:1px solid var(--border);position:sticky;top:0;background:rgba(10,25,47,.9);backdrop-filter:saturate(120%) blur(4px);z-index:10}
    .brand{font-weight:700;letter-spacing:.3px}
    .pill{border:1px solid var(--border);padding:2px 8px;border-radius:999px;font-size:12px;color:var(--muted)}
    .grow{flex:1}
    .toolbar{display:flex;gap:8px;align-items:center}
    .btn{background:transparent;border:1px solid var(--border);color:var(--fg);padding:6px 10px;border-radius:8px;cursor:pointer}
    .btn:hover{border-color:#2b4b86}
    .btn-primary{background:#1b3a6a;border-color:#1b3a6a}
    .btn-primary:hover{background:#234a86}
    .btn-toggle[aria-pressed="true"]{background:#18335f;border-color:#2b4b86}
    .seg{display:inline-flex;border:1px solid var(--border);border-radius:10px;overflow:hidden}
    .seg button{border:0;padding:6px 10px;background:transparent;color:var(--fg);cursor:pointer}
    .seg button[aria-pressed="true"]{background:#16315a}
    .kbd{border:1px solid var(--border);border-bottom-width:2px;border-radius:6px;padding:0 6px;font:12px/18px ui-monospace,Menlo,Consolas,monospace;color:var(--muted)}
    main{display:grid;grid-template-columns:2fr 1fr;gap:12px;padding:12px}
    @media (max-width: 980px){ main{grid-template-columns:1fr} }
    .map-wrap{position:relative;min-height:60vh;border:1px solid var(--border);border-radius:12px;overflow:hidden}
    #map{position:absolute;inset:0}
    .overlay{position:absolute;z-index:5}
    .top-center{top:10px;left:50%;transform:translateX(-50%)}
    .bottom-left{left:10px;bottom:10px}
    .bottom-center{left:50%;bottom:10px;transform:translateX(-50%)}
    .legend{background:var(--panel);border:1px solid var(--border);padding:10px;border-radius:10px}
    .sidebar{background:var(--panel);border:1px solid var(--border);border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:12px;min-height:60vh}
    .cards{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .card{border:1px solid var(--border);border-radius:10px;padding:10px}
    .title{font-weight:600;margin-bottom:6px}
    .big{font-size:28px;font-weight:700}
    .muted{color:var(--muted)}
    .bars{display:grid;gap:8px}
    .bar{display:grid;grid-template-columns:110px 1fr 48px;gap:8px;align-items:center}
    .bar .track{height:10px;border-radius:6px;background:#0b1a33;border:1px solid var(--border);overflow:hidden}
    .bar .fill{height:100%;background:linear-gradient(90deg,var(--good),var(--mid),var(--bad))}
    .row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    footer{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 14px;border-top:1px solid var(--border)}
    input[type="date"]{background:#fff;color:#000;border:1px solid #D0D7DE;padding:6px 8px;border-radius:8px}
    input[type="range"]{accent-color:#4ea1ff}
    .range-hint{font-size:11px;color:var(--muted);text-align:center;margin-top:4px}
    .cross{width:18px;height:18px;border:2px solid #fff;border-radius:50%;box-shadow:0 0 0 2px rgba(0,0,0,.3);position:relative;background:rgba(255,255,255,.1)}
    .cross:before,.cross:after{content:"";position:absolute;background:#fff}
    .cross:before{left:50%;top:2px;bottom:2px;width:2px;transform:translateX(-50%)}
    .cross:after{top:50%;left:2px;right:2px;height:2px;transform:translateY(-50%)}
    .fin{display:grid;place-items:center;width:28px;height:28px;border-radius:50%;background:rgba(155,108,255,.18);border:1px solid var(--violet);box-shadow:0 0 0 2px rgba(0,0,0,.25)}
    .fin span{font-size:16px}
    .fin-label{background:rgba(0,0,0,.45);color:#fff;padding:2px 6px;border-radius:6px;font-size:11px;margin-top:2px;white-space:nowrap; min-width: fit-content;}
    .backdrop{position:fixed; inset:0; background:rgba(0,0,0,.55); display:none; align-items:center; justify-content:center; z-index:4000}
    .backdrop.open{display:flex}
    .modal{background:var(--panel); border:1px solid var(--border); border-radius:12px; max-width:1100px; width:min(96vw,1100px); max-height:90vh; display:flex; flex-direction:column; z-index:5000}
    .modal header,.modal footer{padding:10px 14px;border-bottom:1px solid var(--border)}
    .modal footer{border-top:1px solid var(--border);border-bottom:none;display:flex;gap:8px;justify-content:flex-end}
    .modal .body{padding:12px;display:grid;gap:12px}
    .compare{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .compare .panel{border:1px solid var(--border);border-radius:10px;overflow:hidden;position:relative;min-height:340px}
    .compare .label{position:absolute;top:8px;left:8px;background:rgba(0,0,0,.4);padding:4px 8px;border-radius:6px;font-size:12px}
    .kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
    .kpis .kpi{border:1px solid var(--border);border-radius:10px;padding:10px}
    .pos{color:var(--good)} .neg{color:var(--bad)}
    .popover{position:fixed;right:12px;top:58px;background:var(--panel);border:1px solid var(--border);border-radius:10px;padding:12px;max-width:420px;z-index:15;display:none}
    .popover.open{display:block}
    .btn:focus, .seg button:focus, input:focus, a:focus {outline:2px solid var(--focus);outline-offset:2px}
    #map, .leaflet-container { z-index: 0 !important; }
    body.modal-open .leaflet-container,
    body.modal-open .leaflet-control-container { pointer-events:none !important; }
    body.modal-open .map-wrap { filter: saturate(80%) blur(1px); }
    .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
  </style>
</head>
<body>
<div id="app" class="app">
  <header>
  <div class="brand">SharkScope <span class="pill">Live</span></div>
  <div class="muted">Shark Habitat Support</div>
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
  <div class="muted">Click ocean to analyze. Halo intensity = TCHI suitability. SHSR = (1 − TCHI) × 100</div>
    <div class="row"><span class="kbd">?</span> for help</div>
  </footer>
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

<script>
// Quiet canvas warning; prefer willReadFrequently for 2D contexts
(function(){
  const orig = HTMLCanvasElement.prototype.getContext;
  HTMLCanvasElement.prototype.getContext = function(type, opts){
    if (type === '2d'){
      const o = Object.assign({ willReadFrequently: true }, opts || {});
      try { return orig.call(this, type, o); } catch(e){ return orig.call(this, type, opts); }
    }
    return orig.call(this, type, opts);
  };
})();

(function(){
  // ------------------ Utilities ------------------
  const $ = sel => document.querySelector(sel);
  const clamp = (v, lo, hi) => Math.max(lo, Math.min(hi, v));
  const toFixed = (n, k=2) => Number.isFinite(n) ? n.toFixed(k) : "–";
  const fmtLatLon = (lat, lon) => `${Math.abs(lat).toFixed(2)}°${lat>=0?'N':'S'}, ${Math.abs(lon).toFixed(2)}°${lon>=0?'E':'W'}`;
  const todayISO = new Date().toISOString().slice(0,10);
  const parseISO = s => { const d = new Date(s); return isNaN(d) ? new Date() : d; };
  const addDays = (d, n) => { const x = new Date(d); x.setUTCDate(x.getUTCDate()+n); return x; };
  const iso = d => new Date(d.getTime() - d.getTimezoneOffset()*60000).toISOString().slice(0,10);
  const debounce = (fn, ms)=>{ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; };

  // ------------------ State & Date Handling ------------------
  const url = new URL(location.href);
  const SITE_BASE_URL = new URL('.', window.location.href);
  const API_BASE_URL = new URL('api/', SITE_BASE_URL).toString();
  const TILE_BASE_URL = apiPath('tiles.php');

  function apiPath(endpoint){
    const clean = endpoint.startsWith('/') ? endpoint.slice(1) : endpoint;
    return `${API_BASE_URL}${clean}`;
  }
  const state = {
    availableDates: [],
    dateIndex: 0,
    date: url.searchParams.get('date') || '2025-09-05',
    selected: url.searchParams.has('lat') && url.searchParams.has('lon')
      ? [parseFloat(url.searchParams.get('lat')), parseFloat(url.searchParams.get('lon'))]
      : [68.6131, 16.4671],
    heatMode: url.searchParams.get('mode') || 'prob',
    restaurantsOn: url.searchParams.get('rest') === 'off' ? false : true,
    playTimer: null,
    hotspots: [],
    hotspotDate: null
  };

  function updateURL(){
    const u = new URL(location.href);
    u.searchParams.set('date', state.date);
    u.searchParams.set('mode', state.heatMode);
    u.searchParams.set('rest', state.restaurantsOn ? 'on' : 'off');
    if (state.selected){
      u.searchParams.set('lat', state.selected[0].toFixed(4));
      u.searchParams.set('lon', state.selected[1].toFixed(4));
    } else {
      u.searchParams.delete('lat');
      u.searchParams.delete('lon');
    }
    history.replaceState(null, '', u.toString());
  }

  const formatDate = (isoDate) => {
    if (!isoDate) return '—';
    const dt = new Date(`${isoDate}T00:00:00Z`);
    if (Number.isNaN(dt.getTime())) return isoDate;
    return dt.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
  };

  function setAvailableDates(dates){
    state.availableDates = Array.isArray(dates) ? dates.slice() : [];
    if (!state.availableDates.length){
      state.dateIndex = 0;
      state.date = todayISO;
      return;
    }
    const requested = url.searchParams.get('date');
    const initialIndex = requested ? state.availableDates.indexOf(requested) : -1;
    state.dateIndex = initialIndex >= 0 ? initialIndex : state.availableDates.length - 1;
    state.date = state.availableDates[state.dateIndex];
  }

  let map, baseLayer, markerDivIcon, hotspotMarkerGroup, hotspotHeatGroup;
  let gibsFailed = false;

  function initMap(){
    map = L.map('map', {worldCopyJump:true, preferCanvas:true, minZoom:2}).setView([23.7,90.4], 3);
    L.control.scale({ imperial: false }).addTo(map); 

    baseLayer = createBaseLayer().addTo(map);

    markerDivIcon = L.divIcon({className:'', html:'<div class="cross" role="img" aria-label="Selected location"></div>', iconSize:[18,18], iconAnchor:[9,9]});
    hotspotMarkerGroup = L.layerGroup().addTo(map);

    map.createPane('hotspot-overlay');
    const overlayPane = map.getPane('hotspot-overlay');
    if (overlayPane){
      overlayPane.style.zIndex = 420;
      overlayPane.style.pointerEvents = 'none';
    }
    hotspotHeatGroup = L.layerGroup().addTo(map);

    map.on('click', (e)=>{
      state.selected = [e.latlng.lat, e.latlng.lng];
      updateURL();
      setMarker();
      analyzePoint();
      $('#simulateBtn').disabled = false;
      $('#locLabel').textContent = fmtLatLon(...state.selected);
    });

    syncDateInputs();
    setMode(state.heatMode);
    setRestaurantsToggle(state.restaurantsOn);

    if (state.selected){
      setMarker();
      analyzePoint();
      $('#simulateBtn').disabled = false;
      $('#locLabel').textContent = fmtLatLon(...state.selected);
    }

    refreshLegend();
  }

  function syncDateInputs(){
    const dateInput = $('#date');
    const slider = $('#timeSlider');
    const label = $('#timeLabel');
    const hasDates = state.availableDates.length > 0;

    if (dateInput){
      dateInput.value = state.date;
      dateInput.disabled = !hasDates;
    }
    if (slider){
      slider.min = 0;
      slider.max = Math.max(0, state.availableDates.length - 1);
      slider.value = hasDates ? state.dateIndex : 0;
      slider.disabled = !hasDates;
    }
    if (label) label.textContent = hasDates ? formatDate(state.date) : 'No data';
    const playBtn = $('#play');
    if (playBtn){
      if (state.availableDates.length <= 1 && state.playTimer){
        clearInterval(state.playTimer);
        state.playTimer = null;
        playBtn.textContent = 'Play';
      }
      playBtn.disabled = state.availableDates.length <= 1;
    }
  }

  function setDateIndex(index){
    if (!state.availableDates.length) return;
    const nextIndex = clamp(index, 0, state.availableDates.length - 1);
    if (nextIndex === state.dateIndex && state.date === state.availableDates[nextIndex]) return;
    state.dateIndex = nextIndex;
    state.date = state.availableDates[state.dateIndex];
    syncDateInputs();
    drawRestaurants(true);
    updateURL();
    analyzePoint();
  }

  function setDateFromInput(value){
    if (!state.availableDates.length) return;
    const idx = state.availableDates.indexOf(value);
    if (idx >= 0){
      setDateIndex(idx);
    } else {
      syncDateInputs();
    }
  }

  function refreshLegend(){
    const legendTitle = $('#legendTitle');
    const legendBody = document.querySelector('.legend .muted');
    if (!legendTitle || !legendBody) return;

    if (state.restaurantsOn){
      legendTitle.textContent = state.heatMode === 'risk' ? 'SHSR Hotspot Risk' : 'TCHI Hotspot Intensity';
      legendBody.textContent = 'Red halos highlight peak suitability within approximately 100 km.';
    } else {
      legendTitle.textContent = 'No overlay active';
      legendBody.textContent = 'Toggle Hotspots to visualize shark habitat models.';
    }
  }

  async function loadAvailableDates(){
    try {
      const response = await fetch(apiPath('get_dates.php'));
      const responseText = await response.text();
      let dates;
      try {
        dates = JSON.parse(responseText);
      } catch (parseError){
        throw new Error('Invalid JSON when fetching available dates');
      }
      if (!Array.isArray(dates) || !dates.length){
        throw new Error('No dates available');
      }
      setAvailableDates(dates);
    } catch (error){
      console.error('Failed to load available dates:', error);
      setAvailableDates([]);
    } finally {
      syncDateInputs();
    }
  }

  // ------------------ Map init and layers ------------------
  let selMarker;
  function setMarker(){
    if (selMarker) selMarker.remove();
    selMarker = L.marker(state.selected, {icon: markerDivIcon, keyboard:false}).addTo(map);
  }

  function setMode(mode){
    state.heatMode = mode==='risk' ? 'risk' : 'prob';
    $('#modeRisk').setAttribute('aria-pressed', state.heatMode==='risk' ? 'true':'false');
    $('#modeProb').setAttribute('aria-pressed', state.heatMode==='prob' ? 'true':'false');
    renderHotspotHeat(state.hotspots);
    refreshLegend();
    updateURL();
  }
  $('#modeRisk').addEventListener('click', ()=> setMode('risk'));
  $('#modeProb').addEventListener('click', ()=> setMode('prob'));


  // ------------------ Hotspots ------------------
  function updateHotspotSummary(hotspots){
    const summary = $('#restSummary');
    if (!summary) return;
    if (!state.restaurantsOn){
      summary.textContent = 'Hotspots hidden';
      return;
    }
    if (!Array.isArray(hotspots) || !hotspots.length){
      summary.textContent = 'No hotspots detected';
      return;
    }
    const heatCount = Math.min(10, hotspots.length);
    const labeledCount = Math.min(5, hotspots.length);
    summary.textContent = `Highlighting top ${heatCount} hotspots (${labeledCount} labeled)`;
  }

  function renderHotspotMarkers(hotspots){
    if (!hotspotMarkerGroup) return;
    hotspotMarkerGroup.clearLayers();
    if (!state.restaurantsOn || !Array.isArray(hotspots) || !hotspots.length) return;

    const markerCount = Math.min(5, hotspots.length);
    for (let i = 0; i < markerCount; i += 1){
      const spot = hotspots[i];
      if (!spot) continue;
      const rank = spot.rank || (i + 1);
      const name = spot.name || `Hotspot #${rank}`;
      const icon = L.divIcon({
        className: '',
        iconSize: [28, 32],
        iconAnchor: [14, 14],
        html: `<div class="fin"><span>🔥</span></div><div class="fin-label">${name}</div>`
      });
      L.marker([spot.lat, spot.lon], { icon, keyboard: false, interactive: false }).addTo(hotspotMarkerGroup);
    }
  }

  function renderHotspotHeat(hotspots){
    if (!hotspotHeatGroup) return;
    hotspotHeatGroup.clearLayers();
    if (!state.restaurantsOn || !Array.isArray(hotspots) || !hotspots.length) return;

    const usable = hotspots.slice(0, 10);
    const scores = usable
      .map(h => Number(h.tchi_score))
      .filter(v => Number.isFinite(v));

    const maxScore = scores.length ? Math.max(...scores) : 1;
    const minScore = scores.length ? Math.min(...scores) : 0;
    const span = Math.max(maxScore - minScore, 1e-6);

    usable.forEach((spot)=>{
      if (!spot) return;
      const score = Number(spot.tchi_score);
      const normalized = Number.isFinite(score) ? (score - minScore) / span : 0.5;
      const intensity = 0.45 + normalized * 0.55;
      const circleStops = [
        { radius: 24000, color: '#ff2d55', opacity: 0.55 },
        { radius: 50000, color: '#ff4f45', opacity: 0.33 },
        { radius: 80000, color: '#ff6f3d', opacity: 0.22 },
        { radius: 100000, color: '#ff8f3a', opacity: 0.14 }
      ];

      circleStops.forEach((stop)=>{
        const circle = L.circle([spot.lat, spot.lon], {
          radius: stop.radius,
          stroke: false,
          fillColor: stop.color,
          fillOpacity: stop.opacity * intensity,
          pane: 'hotspot-overlay',
          interactive: false,
          bubblingMouseEvents: false
        });
        hotspotHeatGroup.addLayer(circle);
      });
    });
  }

  async function drawRestaurants(force = false){
    if (!map) return;

    if (!state.restaurantsOn){
      if (hotspotMarkerGroup) hotspotMarkerGroup.clearLayers();
      if (hotspotHeatGroup) hotspotHeatGroup.clearLayers();
      updateHotspotSummary([]);
      return;
    }

    if (!state.date) return;

    if (!force && state.hotspotDate === state.date && Array.isArray(state.hotspots) && state.hotspots.length){
      renderHotspotMarkers(state.hotspots);
      renderHotspotHeat(state.hotspots);
      updateHotspotSummary(state.hotspots);
      refreshLegend();
      return;
    }

    const summary = $('#restSummary');
    if (summary) summary.textContent = 'Loading hotspots…';

    try {
      const url = new URL(apiPath('get_hotspots.php'));
      url.searchParams.set('date', state.date);
      url.searchParams.set('count', 10);
      const response = await fetch(url);
      if (!response.ok) throw new Error(`Request failed (${response.status})`);
      const hotspots = await response.json();
      if (!Array.isArray(hotspots)) throw new Error('Invalid hotspot payload');

      state.hotspotDate = state.date;
      state.hotspots = hotspots;

      renderHotspotMarkers(hotspots);
      renderHotspotHeat(hotspots);
      updateHotspotSummary(hotspots);
      refreshLegend();
    } catch (error){
      console.error('Failed to fetch hotspots:', error);
      if (hotspotMarkerGroup) hotspotMarkerGroup.clearLayers();
      if (hotspotHeatGroup) hotspotHeatGroup.clearLayers();
      state.hotspots = [];
      state.hotspotDate = null;
      const summaryEl = $('#restSummary');
      if (summaryEl) summaryEl.textContent = 'Error loading hotspots';
    }
  }

  function setRestaurantsToggle(on){
    state.restaurantsOn = !!on;
    $('#restaurantsBtn').setAttribute('aria-pressed', state.restaurantsOn ? 'true' : 'false');
    if (state.restaurantsOn){
      drawRestaurants(true);
    } else {
      if (hotspotMarkerGroup) hotspotMarkerGroup.clearLayers();
      if (hotspotHeatGroup) hotspotHeatGroup.clearLayers();
      state.hotspots = [];
      state.hotspotDate = null;
      const summaryEl = $('#restSummary');
      if (summaryEl) summaryEl.textContent = 'Hotspots hidden';
    }
    refreshLegend();
    updateURL();
  }
  $('#restaurantsBtn').addEventListener('click', ()=> setRestaurantsToggle(!state.restaurantsOn));

  // ------------------ Sidebar analysis ------------------
  const FACTOR_CONFIG = [
    { key:'sst', label:'SST', units:'°C', normalize: (v)=>normalizeRange(v, -2, 35) },
    { key:'chla', label:'Chl‑a', units:'mg/m³', normalize: (v)=>normalizeRange(v, 0, 10) },
    { key:'tfg', label:'TFG', units:'index', normalize: (v)=>normalizeRange(v, 0, 1) },
    { key:'eke', label:'EKE', units:'cm²/s²', normalize: (v)=>normalizeRange(v, 0, 500) },
    { key:'bathy', label:'Depth', units:'m', normalize: (v)=>normalizeRange(v, 0, 6000) }
  ];

  function normalizeRange(value, min, max){
    if (!Number.isFinite(value)) return null;
    if (max <= min) return null;
    return clamp((value - min) / (max - min), 0, 1);
  }

  function renderFactors(factors){
    const wrap = $('#factorBars');
    wrap.innerHTML = '';
    if (!factors || typeof factors !== 'object'){
      const empty = document.createElement('div');
      empty.className = 'muted';
      empty.textContent = 'No factor data available for this location.';
      wrap.appendChild(empty);
      return;
    }

    FACTOR_CONFIG.forEach(({key, label, units, normalize})=>{
      const raw = Number(factors[key]);
      const normalized = normalize(raw);
      const pct = normalized === null ? 0 : Math.round(normalized * 100);
      const display = Number.isFinite(raw) ? `${raw.toFixed(2)}${units ? ` ${units}` : ''}` : '—';
      const row = document.createElement('div'); row.className='bar';
      row.innerHTML = `<div class="muted">${label}</div><div class="track"><div class="fill" style="width:${pct}%"></div></div><div>${display}</div>`;
      wrap.appendChild(row);
    });
  }

  function normalizeTchi(value){
    if (!Number.isFinite(value)) return 0;
    const v = value > 1 ? value / 100 : value;
    return clamp(v, 0, 1);
  }

  let sparkChart;
  function renderSpark(series){
    const canvas = $('#spark');
    const ctx = canvas.getContext('2d');
    if (sparkChart){ sparkChart.destroy(); sparkChart = null; }

    if (!Array.isArray(series) || !series.length){
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      ctx.save();
      ctx.fillStyle = '#9AA4AE';
      ctx.font = '14px Inter, sans-serif';
      ctx.fillText('No data available', 10, 24);
      ctx.restore();
      return;
    }

    const labels = series.map(s=> s.date ? s.date.slice(5) : '');
    const vals = series.map(s=> Math.round(normalizeTchi(s.tchi_score) * 10000) / 100);

    sparkChart = new Chart(ctx, {
      type:'line',
      data:{ labels, datasets:[{ label:'TCHI (/100)', data:vals, borderColor:'#3BA3FF', backgroundColor:'rgba(59,163,255,.15)', tension:.25, fill:true, pointRadius:3 }]},
      options:{ responsive:true, plugins:{legend:{display:false}}, scales:{ y:{min:0,max:100,grid:{color:'rgba(255,255,255,.06)'}}, x:{grid:{display:false}} } }
    });
  }

  async function analyzePoint(){
    if (!state.selected) return;
    const [lat, lon] = state.selected;
    $('#locLabel').textContent = fmtLatLon(lat, lon);

    const tchiEl = $('#tchiScore');
    const shsrEl = $('#shsrScore');
    tchiEl.textContent = 'Loading…';
    shsrEl.textContent = 'Loading…';

    try {
  const pointUrl = new URL(apiPath('point_analysis.php'));
  pointUrl.searchParams.set('lat', lat);
  pointUrl.searchParams.set('lon', lon);
  const response = await fetch(pointUrl);
      if (!response.ok) throw new Error(`Request failed (${response.status})`);
      const data = await response.json();
      if (data.error) throw new Error(data.message || 'API error');

      const timeseries = Array.isArray(data.timeseries) ? data.timeseries : [];
      if (!timeseries.length){
        tchiEl.textContent = 'No data';
        shsrEl.textContent = '—';
        renderFactors(null);
        renderSpark([]);
        return;
      }

      const current = timeseries.find(entry => entry.date === state.date) || timeseries[0];
      const tchiValue = normalizeTchi(current.tchi_score);
      const tchiDisplay = Math.round(tchiValue * 100);
      const shsr = Math.round((1 - tchiValue) * 100);

      tchiEl.textContent = `${tchiDisplay}/100`;
      shsrEl.textContent = `${shsr}%`;

      renderFactors(current.factors || {});
      renderSpark(timeseries);
    } catch (error){
      console.error('Point analysis failed:', error);
      tchiEl.textContent = 'Error';
      shsrEl.textContent = '—';
      renderFactors(null);
      renderSpark([]);
    }
  }

  // ------------------ Date & controls ------------------
  $('#date').addEventListener('change', (e)=>{
    const value = e.target.value;
    if (value) setDateFromInput(value);
  });
  $('#timeSlider').addEventListener('input', (e)=>{
    const idx = parseInt(e.target.value || '0', 10);
    const date = state.availableDates[idx];
    $('#timeLabel').textContent = date ? formatDate(date) : '—';
  });
  $('#timeSlider').addEventListener('change', (e)=>{
    const idx = parseInt(e.target.value || '0', 10);
    if (!Number.isNaN(idx)) setDateIndex(idx);
  });
  $('#prevDay').addEventListener('click', ()=> setDateIndex(state.dateIndex - 1));
  $('#nextDay').addEventListener('click', ()=> setDateIndex(state.dateIndex + 1));
  document.addEventListener('keydown', (e)=>{
    if (e.altKey && e.key==='ArrowLeft') $('#prevDay').click();
    if (e.altKey && e.key==='ArrowRight') $('#nextDay').click();
    if (e.key==='?') togglePop($('#infoPop'));
  });

  // ------------------ Info & Credits ------------------
  function togglePop(pop){ const open = pop.classList.toggle('open'); pop.setAttribute('aria-modal', open ? 'true' : 'false'); }
  $('#infoBtn').addEventListener('click', ()=>togglePop($('#infoPop')));
  $('#creditsBtn').addEventListener('click', ()=>togglePop($('#creditsPop')));
  document.addEventListener('click', (e)=>{ if (!$('#infoPop').contains(e.target) && e.target !== $('#infoBtn')) $('#infoPop').classList.remove('open');
                                            if (!$('#creditsPop').contains(e.target) && e.target !== $('#creditsBtn')) $('#creditsPop').classList.remove('open'); });

  // ------------------ Export PNG ------------------
  $('#exportBtn').addEventListener('click', async ()=>{
    const node = document.getElementById('app');
    const canvas = await html2canvas(node, {backgroundColor: null, scale: 2, useCORS: true});
  const link = document.createElement('a'); link.download = `sharkscope_${state.date}_${state.heatMode}_hotspots.png`;
    link.href = canvas.toDataURL('image/png'); link.click();
  });

  // ------------------ Simulation Modal (unchanged mock) ------------------
  const sim = { open:false, maps:{before:null, after:null}, circle:null };
  async function openSim(){
    if (!state.selected) return;
    const [lat, lon] = state.selected;

    $('#simBackdrop').classList.add('open');
    document.body.style.overflow='hidden';
    document.body.classList.add('modal-open');
    initSimMaps(lat, lon);
    sim.open = true;

    const prevEl = $('#prevTchi');
    const refinedEl = $('#refinedTchi');
    const deltaEl = $('#deltaTchi');

    prevEl.textContent = 'Loading…';
    refinedEl.textContent = 'Loading…';
    deltaEl.innerHTML = '<span class="muted">Fetching simulation…</span>';

    try {
      const response = await fetch(apiPath('simulate.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ lat, lon, prey_code: 'SEAL_01' })
      });

      const responseText = await response.text();
      let payload;
      try {
        payload = JSON.parse(responseText);
      } catch (parseError){
        throw new Error('Invalid JSON response from simulation API');
      }

      if (!response.ok || payload.error){
        throw new Error(payload.message || `Simulation failed (${response.status})`);
      }

      const prev = normalizeTchi(Number(payload.previous_tchi));
      const refined = normalizeTchi(Number(payload.refined_tchi));
      const delta = refined - prev;
      const deltaShsr = -((1 - refined) - (1 - prev)) * 100;

      prevEl.textContent = toFixed(prev, 2);
      refinedEl.textContent = toFixed(refined, 2);
      deltaEl.innerHTML = `<span class="${delta>=0?'pos':'neg'}">${delta>=0?'+':''}${toFixed(delta,2)}  /  ${deltaShsr>=0?'-':''}${Math.abs(deltaShsr).toFixed(0)}%</span>`;
    } catch (error){
      console.error('Simulation failed:', error);
      prevEl.textContent = 'Error';
      refinedEl.textContent = 'Error';
      deltaEl.innerHTML = '<span class="neg">Simulation unavailable</span>';
    }
  }

function createBaseLayer() {
    const gibsUrl = 'https://gibs.earthdata.nasa.gov/wmts/epsg4326/best/wmts.cgi';
    const layer = L.tileLayer.wms(gibsUrl, {
        layers: 'MODIS_Terra_CorrectedReflectance_TrueColor',
        tileSize: 512,
        format: 'image/png',
        transparent: false,
        attribution: 'NASA GIBS'
    });

    layer.on('tileerror', () => {
        // Fallback to OpenStreetMap if GIBS fails
        const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OSM contributors'
        });
        if (layer._map) {
            const map = layer._map;
            map.removeLayer(layer);
            osm.addTo(map);
        }
    });
    return layer;
}

function initSimMaps(lat, lon) {
    // Clear any previous map instances
    if (sim.maps.before) { sim.maps.before.remove(); }
    if (sim.maps.after) { sim.maps.after.remove(); }
    
    // Ensure containers are clean
    document.getElementById('mapBefore').innerHTML = '';
    document.getElementById('mapAfter').innerHTML = '';

    const mapOptions = {
        zoom: 8,
        center: [lat, lon],
        attributionControl: false,
        zoomControl: false,
        scrollWheelZoom: false,
        dragging: false,
    };

    // Initialize both maps using our robust helper function
    sim.maps.before = L.map('mapBefore', mapOptions);
    createBaseLayer().addTo(sim.maps.before);
    
    sim.maps.after = L.map('mapAfter', mapOptions);
    createBaseLayer().addTo(sim.maps.after);

    // Add the simulation visualization circle
    L.circle([lat, lon], {
        radius: 5000,
        color: '#2ECC71',
        weight: 2,
        fillColor: '#2ECC71',
        fillOpacity: 0.25
    }).addTo(sim.maps.after);

    // Refresh map sizes after modal is visible
    setTimeout(() => {
        if (sim.maps.before) sim.maps.before.invalidateSize();
        if (sim.maps.after) sim.maps.after.invalidateSize();
    }, 100);
}
  $('#simulateBtn').addEventListener('click', openSim);
  $('#closeSim').addEventListener('click', ()=>{ $('#simBackdrop').classList.remove('open'); document.body.style.overflow=''; document.body.classList.remove('modal-open'); sim.open=false; });
  $('#rerunSim').addEventListener('click', openSim);
  $('#simBackdrop').addEventListener('click', (e)=>{ if (e.target.id==='simBackdrop') $('#closeSim').click(); });

  // ------------------ Boot ------------------
  async function boot(){
    await loadAvailableDates();
    initMap();
    updateURL();

    const playBtn = $('#play');
    playBtn.addEventListener('click', ()=>{
      if (state.playTimer){
        clearInterval(state.playTimer);
        state.playTimer = null;
        playBtn.textContent = 'Play';
        return;
      }
      if (state.availableDates.length <= 1) return;
      playBtn.textContent = 'Pause';
      state.playTimer = setInterval(()=>{
        let next = state.dateIndex + 1;
        if (next >= state.availableDates.length) next = 0;
        setDateIndex(next);
      }, 900);
    });
  }

  window.addEventListener('load', boot);
})();
</script>
</body>
</html>