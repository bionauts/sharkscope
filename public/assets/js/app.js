// SharkScope Application JavaScript
// Configuration will be injected from PHP
window.SHARKSCOPE_CONFIG = window.SHARKSCOPE_CONFIG || {};

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
  const BASE_URL = window.SHARKSCOPE_CONFIG.baseUrl || '';
  const API_BASE_URL = BASE_URL + '/src/api/';

  function apiPath(endpoint){
    const clean = endpoint.startsWith('/') ? endpoint.slice(1) : endpoint;
    return `${API_BASE_URL}${clean}`;
  }
  
  const TILE_BASE_URL = apiPath('tiles.php');
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
  let sharkTrackGroup, sharkTrackPolyline, sharkEventMarkers = [];
  let sharkTrackVisible = false;
  let gibsFailed = false;

  function initMap(){
  // Initial view fixed per requirement: 60.73°N, 73.07°W
  map = L.map('map', {worldCopyJump:true, preferCanvas:true, minZoom:2}).setView([60.73,-73.07], 3);
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
  sharkTrackGroup = L.layerGroup().addTo(map);

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

  // ------------------ Shark Track (Mako-Sense simulation) ------------------
  async function toggleSharkTrack(){
    sharkTrackVisible = !sharkTrackVisible;
    const btn = $('#trackBtn');
    btn.setAttribute('aria-pressed', sharkTrackVisible ? 'true' : 'false');
    if (!sharkTrackVisible){
      sharkTrackGroup.clearLayers();
      sharkTrackPolyline = null;
      sharkEventMarkers = [];
      return;
    }
    // Load CSV from hardware directory
    try {
      const resp = await fetch('../hardware/makosense_data.csv');
      if (!resp.ok) throw new Error('Failed to fetch makosense_data.csv');
      const text = await resp.text();
      const rows = parseCsv(text);
      if (!rows.length) return;
      const latlngs = rows.map(r => [parseFloat(r.latitude), parseFloat(r.longitude)]);
      sharkTrackPolyline = L.polyline(latlngs, {color:'#3BA3FF', weight:2, opacity:0.85}).addTo(sharkTrackGroup);
      // Fit bounds lightly padded
      map.fitBounds(sharkTrackPolyline.getBounds().pad(0.2));
      rows.forEach(r => {
        if (r.prey_code && r.prey_code !== 'AMBIENT_WATER'){
          const icon = L.divIcon({className:'', html:`<div style="width:14px;height:14px;background:#e74c3c;border:2px solid #fff;border-radius:50%;box-shadow:0 0 4px rgba(0,0,0,.4)" title="${r.prey_code}"></div>`, iconSize:[14,14], iconAnchor:[7,7]});
          const m = L.marker([parseFloat(r.latitude), parseFloat(r.longitude)], {icon});
          m.bindPopup(`<div style='font-size:12px'><strong>${r.prey_code}</strong><br>${r.timestamp}</div>`);
          m.addTo(sharkTrackGroup);
          sharkEventMarkers.push(m);
        }
      });
      // Populate packet decoder events if modal has been opened later
      cachedMakoRows = rows; // store globally for decoder
    } catch(err){
      console.error('Shark track failed:', err);
    }
  }

  function parseCsv(text){
    const lines = text.trim().split(/\r?\n/);
    if (lines.length < 2) return [];
    const header = lines[0].split(',').map(h=>h.trim());
    return lines.slice(1).map(line => {
      const cols = line.split(',');
      const obj = {};
      header.forEach((h,i)=> obj[h] = (cols[i]||'').trim());
      return obj;
    });
  }

  $('#trackBtn').addEventListener('click', toggleSharkTrack);

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
      const url = new URL(apiPath('get_hotspots.php'), window.location.origin);
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
  const pointUrl = new URL(apiPath('point_analysis.php'), window.location.origin);
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

  // ------------------ Packet Decoder Modal ------------------
  let decoderOpen = false;
  let cachedMakoRows = null;
  function openDecoder(){
    $('#packetDecoderBackdrop').classList.add('open');
    document.body.style.overflow='hidden';
    decoderOpen = true;
    ensureMakoRows().then(populateDecoderEvents);
  }
  function closeDecoder(){
    $('#packetDecoderBackdrop').classList.remove('open');
    document.body.style.overflow='';
    decoderOpen = false;
  }
  async function ensureMakoRows(){
    if (cachedMakoRows) return cachedMakoRows;
    try {
      const resp = await fetch('../hardware/makosense_data.csv');
      if (!resp.ok) throw new Error('Failed to fetch data');
      cachedMakoRows = parseCsv(await resp.text());
    } catch(e){
      console.error(e);
      cachedMakoRows = [];
    }
    return cachedMakoRows;
  }
  function populateDecoderEvents(rows){
    const sel = $('#packetEventSelect');
    sel.innerHTML = '';
    rows.forEach((r,i)=>{
      const opt = document.createElement('option');
      opt.value = i;
      opt.textContent = `${r.timestamp} | ${r.prey_code}`;
      sel.appendChild(opt);
    });
  }
  function genMockPacket(row){
    // Build a binary buffer then hex encode
    // Helpers
    function toUint32LE(num){ const b = new ArrayBuffer(4); new DataView(b).setUint32(0, num>>>0, true); return new Uint8Array(b);}    
    function toInt32LE(num){ const b = new ArrayBuffer(4); new DataView(b).setInt32(0, num|0, true); return new Uint8Array(b);}    
    function toUint16LE(num){ const b = new ArrayBuffer(2); new DataView(b).setUint16(0, num & 0xFFFF, true); return new Uint8Array(b);}  
    function toFloat32LE(f){ const b = new ArrayBuffer(4); new DataView(b).setFloat32(0, f, true); return new Uint8Array(b);}  
    function asciiBytes(str, len){ const out = new Uint8Array(len); for(let i=0;i<len;i++){ out[i] = i<str.length ? str.charCodeAt(i) : 0x00; } return out; }
    function crc16CCITT(buf){ let crc = 0xFFFF; for (let b of buf){ crc ^= (b<<8); for (let i=0;i<8;i++){ if (crc & 0x8000) crc=(crc<<1)^0x1021; else crc<<=1; crc &= 0xFFFF; } } return crc; }

    const pkt = new Uint8Array(32);
    // Timestamp
    const ts = Date.parse(row.timestamp)/1000|0; pkt.set(toUint32LE(ts),0);
    // Lat/Lon scaled
    const lat = Math.round(parseFloat(row.latitude)*1e6); pkt.set(toInt32LE(lat),4);
    const lon = Math.round(parseFloat(row.longitude)*1e6); pkt.set(toInt32LE(lon),8);
    // Battery mV (mock ~ 3.70–3.85V)
    const batt = Math.round(3700 + Math.random()*150); pkt.set(toUint16LE(batt),12);
    // FW Version 1.2
    pkt[14] = (1<<4)|2;
    // Flags: GPS valid + maybe spectral avg
    pkt[15] = 0b00000101;
    // Prey Code (truncate/pad to 8)
    const preyCode = (row.prey_code||'').replace(/[^A-Z_]/g,'').slice(0,8).padEnd(8,'_');
    pkt.set(asciiBytes(preyCode,8),16);
    // Confidence (mock) if prey else 0
    const conf = row.prey_code==='AMBIENT_WATER'? 0 : 0.75 + Math.random()*0.2; pkt.set(toFloat32LE(conf),24);
    // Spectral hash mock
    const hash = Math.floor(Math.random()*0xFFFF); pkt.set(toUint16LE(hash),28);
    // CRC
    const crc = crc16CCITT(pkt.slice(0,30)); pkt.set(toUint16LE(crc),30);
    return Array.from(pkt).map(b=> b.toString(16).padStart(2,'0')).join('');
  }
  function decodePacket(hex){
    if (!/^[0-9a-fA-F]{64}$/.test(hex)) throw new Error('Hex must be 64 chars (32 bytes)');
    const bytes = new Uint8Array(hex.match(/.{2}/g).map(h=>parseInt(h,16)));
    const dv = new DataView(bytes.buffer);
    function getStr(off,len){ return Array.from(bytes.slice(off,off+len)).map(c=> c?String.fromCharCode(c):'').join('').replace(/\0+$/,''); }
    const ts = dv.getUint32(0, true);
    const lat = dv.getInt32(4, true) / 1e6;
    const lon = dv.getInt32(8, true) / 1e6;
    const batt = dv.getUint16(12, true);
    const fw = dv.getUint8(14); const fwMaj = fw>>4; const fwMin = fw & 0x0F;
    const flags = dv.getUint8(15);
    const prey = getStr(16,8).replace(/_+$/,'');
    const conf = dv.getFloat32(24, true);
    const hash = dv.getUint16(28, true);
    const crc = dv.getUint16(30, true);
    // Recompute CRC
    let recompute = 0xFFFF; for (let i=0;i<30;i++){ let b = bytes[i]; recompute ^= (b<<8); for(let k=0;k<8;k++){ if (recompute & 0x8000) recompute=(recompute<<1)^0x1021; else recompute<<=1; recompute &= 0xFFFF; } }
    const tsISO = new Date(ts*1000).toISOString();
    return {timestamp: tsISO, latitude: lat, longitude: lon, battery_mV: batt, fw:`${fwMaj}.${fwMin}`, flags: '0x'+flags.toString(16).padStart(2,'0'), prey_code: prey, confidence: conf, spectral_hash: hash, crc_ok: crc===recompute, crc: '0x'+crc.toString(16).padStart(4,'0')};
  }
  function renderDecoded(obj){
    const el = $('#decodedPacket');
    if (!obj){ el.textContent = 'No packet decoded.'; return; }
    el.textContent = `Timestamp: ${obj.timestamp}\nLatitude: ${obj.latitude.toFixed(6)}\nLongitude: ${obj.longitude.toFixed(6)}\nBattery: ${obj.battery_mV} mV\nFW: ${obj.fw}\nFlags: ${obj.flags}\nPrey Code: ${obj.prey_code||'(ambient)'}\nConfidence: ${obj.confidence.toFixed(2)}\nSpectral Hash: ${obj.spectral_hash}\nCRC: ${obj.crc} (${obj.crc_ok?'OK':'FAIL'})`;
  }
  $('#packetDecoderBtn').addEventListener('click', openDecoder);
  $('#closePacketDecoder').addEventListener('click', closeDecoder);
  $('#packetDecoderBackdrop').addEventListener('click', (e)=>{ if (e.target.id==='packetDecoderBackdrop') closeDecoder(); });
  $('#generatePacketBtn').addEventListener('click', async ()=>{
    const rows = await ensureMakoRows();
    if (!rows.length){ alert('No Mako-Sense data available.'); return; }
    const sel = $('#packetEventSelect');
    const row = rows[parseInt(sel.value||'0',10)] || rows[0];
    const hex = genMockPacket(row);
    $('#rawPacketHex').value = hex;
    $('#decodePacketBtn').disabled = false;
  });
  $('#decodePacketBtn').addEventListener('click', ()=>{
    const hex = $('#rawPacketHex').value.trim();
    try { const decoded = decodePacket(hex); renderDecoded(decoded); }
    catch(err){ alert(err.message); }
  });

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
