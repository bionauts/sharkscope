# Mako-Sense Smart Tag Hardware Design

## 1. Introduction
Mako-Sense is an autonomous, energy-harvesting smart tag engineered to close a critical data gap in marine conservation: obtaining direct, empirical evidence of *what* apex predators actually consume in the wild. Traditional tags infer behavior (e.g., dive depth, acceleration, temperature). Mako-Sense directly detects biochemical prey signatures by performing in situ spectrochemical analysis of micro-volume seawater samples entrained in the shark’s boundary layer immediately after a feeding event. The result is a sparse but **high-confidence, low-bandwidth series of confirmed prey-type detections** transmitted globally via satellite—transforming predator–prey interaction modeling by anchoring it to real chemical evidence.

## 2. Component Deep Dive
### 2.1 Onboard Processor — Raspberry Pi Pico W
The Raspberry Pi Pico W (RP2040 + wireless) is selected for its **ultra-low standby current**, deterministic dual-core microcontroller architecture, broad community support, and robust peripheral set (SPI, I2C, UART, PIO). In Mako-Sense, **Core 0** orchestrates the supervisory state machine (power budgeting, wake scheduling, telemetry queueing) while **Core 1** handles time-bounded sensor operations (spectrometer acquisition, ADC sampling, piezo energy metrics). The chip’s flexible Phase-Locked Loops and clock gating allow throttling to deep sleep (tens of µA) between duty cycles, drastically reducing average power. Although Wi‑Fi is not used in early deployments, the integrated radio and secure crypto primitives offer a forward-compatible path for pre-deployment calibration, short-range configuration, or opportunistic near-surface data offload—without redesign. The RP2040’s Programmable I/O (PIO) blocks are leveraged to implement precise spectrometer timing signals (laser trigger / integration window) without loading the main cores, increasing reliability and reducing jitter in spectral captures.

### 2.2 Satellite Telemetry — Iridium 9603N Short Burst Data (SBD)
The Iridium 9603N module provides **true pole-to-pole global coverage**, a non-negotiable requirement because mako and similar pelagic sharks can traverse ocean basins and subpolar regions. Its extremely compact footprint (~31 × 29 × 8 mm) and low mass minimize hydrodynamic penalties and ease encapsulation. Power-wise, the 9603N’s peak current bursts (on the order of < 200 mA during transmission) are infrequent and short; by constraining usage to only biologically significant detections, the *energy-per-actionable-datum* becomes exceptionally low. SBD’s binary payload model aligns perfectly with Mako-Sense’s sparse, event-driven output (single 32-byte packets). Store-and-forward resilience plus robust forward error correction mitigate transient cold-water attenuation and orientation-induced link variation during brief surface dwells. Alternative systems (ARGOS, GSM, LoRaWAN) either lack true global reach, require line-of-sight infrastructure, or impose larger form factors and/or higher energy per bit. Thus, Iridium SBD is the optimal intersection of coverage, energy efficiency, and latency tolerance for sparse ecological events.

### 2.3 Power Source — Piezoelectric Energy Harvester (with Buffer Storage)
Instead of a finite primary cell, Mako-Sense adopts a **piezoelectric bending beam / stack harvester** embedded within a dorsal clamp assembly. The shark’s powerful lateral undulations induce cyclic strain, generating AC charge that is rectified and funneled into a **hybrid energy buffer**: a high-cycle, moderate-capacity thin-film LiPo (or lithium titanate) cell paralleled with a supercapacitor for peak current dampening. This architecture supports **multi-year deployments** by continuously replenishing energy proportional to swimming activity—precisely when sampling opportunities also rise. Smart MPPT-like impedance tuning maximizes conversion at varying tail-beat frequencies. Duty cycling is tightly power-budgeted: the supervisory loop dynamically adjusts wake interval or defers non-essential sampling if the harvested charge per hour trends downward (e.g., during prolonged low-activity or thermal stress). Compared to primary batteries (logistics, disposal, finite mission), or solely inductive charging (impractical mid-ocean), piezoelectric harvesting provides sustainable autonomy while maintaining a compact envelope. Environmental sealing isolates the piezo driver from seawater while mechanical coupling ensures efficient strain transfer without overloading biological tissue.

### 2.4 Primary Sensor — Micro-Raman Spectrometer Module
A miniaturized, low-power **micro-Raman spectrometer** (integrating laser diode, edge filter, and CMOS detector) is the cornerstone innovation. Raman scattering supplies **molecularly specific vibrational fingerprints**, enabling discrimination among prey-derived organic residue classes that diffuse into the immediate post-prandial boundary layer: e.g., differential bands for lipid-dense marine mammal tissue vs. protein-rich teleost muscle vs. chitinous or keratinous fragments (squid beak, cephalopod structural proteins). Unlike fluorescence or bulk nutrient proxies, Raman provides *chemical specificity* with minimal sample prep and negligible consumables. Short integration windows (e.g., 250–750 ms) with adaptive signal averaging are synchronized to laminar-equivalent microflow through the passive flow cell. The non-destructive nature avoids contaminant accumulation, while on-device spectral compression (peak extraction + hashed feature codes) enables sub-10 byte prey descriptors. Emerging advancements in photonic integration and volume Bragg gratings reduce size and power sufficiently for marine bio-logging. Alternatives (eDNA sequencing, mass spectrometry) are either too energy-, reagent-, or latency-intensive for autonomous, real-time detection. Thus, Raman spectroscopy is the only feasible path to **on-animal biochemical event confirmation** at scale.

## 3. Firmware Logic (Expanded Pseudocode)
Below is an expanded, commented supervisory + sensor acquisition pseudocode representing intended firmware structure. This models dual-core partitioning, power governance, and conditional telemetry.

```pseudocode
// ------------------------------------------------------------------
// CONSTANTS & CONFIG
WAKE_INTERVAL_BASE_SEC = 300                 // Nominal 5-minute low-power duty cycle
HARVEST_LOW_THRESHOLD_mV = 3550              // Below this: stretch interval / conserve
HARVEST_RECOVERY_THRESHOLD_mV = 3680         // Resume normal cadence once recharged
MIN_SEND_GPS_HDOP = 3.0                      // Only transmit if GPS quality acceptable
SPECTRAL_INTEGRATION_MS = 500                // Base Raman integration window
MAX_AVERAGES = 3                             // Adaptive SNR improvement ceiling
PREY_CONFIDENCE_MIN = 0.78                   // Bayesian / ML threshold for event
IRIDIUM_RETRY_MAX = 2                        // Limited retries to cap energy cost
PACKET_SIZE = 32                             // Fixed SBD payload
SLEEP_GUARD_MS = 50                          // Pad for clock drift / stabilization

// SHARED (Dual-Core) RING BUFFERS
telemetry_queue = CircularBuffer(capACITY=8)

// CORE 0: SUPERVISORY / POWER / TELEMETRY
core0_main():
    init_clocks_low_power()
    init_rtc()
    init_energy_harvester_monitor()
    init_gps_module(low_duty_assist=True)
    spawn_core1(sensor_task_loop)

    while True:
        vbatt_mV = read_battery_millivolts()
        harvested_recent_mWh = energy_harvest_delta(last=WAKE_INTERVAL_BASE_SEC)

        // Adaptive wake modulation based on energy trends
        if vbatt_mV < HARVEST_LOW_THRESHOLD_mV:
            wake_interval = WAKE_INTERVAL_BASE_SEC * 2   // Conserve
        else if vbatt_mV > HARVEST_RECOVERY_THRESHOLD_mV:
            wake_interval = WAKE_INTERVAL_BASE_SEC       // Normal
        else:
            wake_interval = WAKE_INTERVAL_BASE_SEC * 1.25

        gps_fix = attempt_timeboxed_gps_fix(timeout=25s)
        if gps_fix.valid: store_last_fix(gps_fix)

        // Drain any completed sensor analyses from Core 1
        while sensor_result_available():
            result = pop_sensor_result()
            if result.prey_detected and result.confidence >= PREY_CONFIDENCE_MIN:
                pkt = build_data_packet(result, last_fix=gps_fix or get_last_fix(), vbatt_mV)
                telemetry_queue.push(pkt)

        // Conditional Iridium send (only if high-value event present + quality OK)
        if not telemetry_queue.empty() and gps_quality_ok(gps_fix, MIN_SEND_GPS_HDOP):
            pkt = telemetry_queue.pop()
            success = iridium_transmit(pkt, retries=IRIDIUM_RETRY_MAX)
            log_transmission_attempt(success, pkt)
            if not success and telemetry_queue.space():
                telemetry_queue.push(pkt) // Requeue for next window (single defer)

        prepare_low_power_state()
        rtc_sleep(wake_interval - SLEEP_GUARD_MS)

// CORE 1: SENSOR ACQUISITION & CLASSIFICATION
sensor_task_loop():
    init_raman_laser_driver(safe_mode=True)
    init_spectrometer_adc()
    init_flow_cell_monitor(temp_sensor=True)

    while True:
        wait_for_core0_wake_signal()         // Synchronize to supervisory cadence

        if flow_cell_velocity_estimate() < MIN_FLOW_THRESHOLD:
            continue // Insufficient passive flushing; skip to conserve energy

        raw_accumulated = []
        for i in 1..MAX_AVERAGES:
            spectrum = capture_raman_spectrum(SPECTRAL_INTEGRATION_MS)
            spectrum = dark_current_subtract(spectrum)
            spectrum = baseline_correct(spectrum)
            raw_accumulated.append(spectrum)
            if estimate_snr(raw_accumulated) >= REQUIRED_SNR: break

        features = extract_peak_features(raw_accumulated)
        prey_class, confidence = classify_predefined_model(features)

        result = SensorResult(
            timestamp = current_unix_time(),
            prey_code = prey_class.code,        // e.g. 8-byte ASCII token
            confidence = confidence,
            spectral_hash = hash_feature_vector(features),
            battery_mV = read_battery_millivolts()
        )
        push_sensor_result(result)

        // Laser safety & thermal budget
        enforce_cooldown_if_temp_exceeds()
```

### Logic Flow Summary
1. **Sleep Dominant:** Core 0 remains in deep sleep between periodic supervisory wake cycles (adaptive interval based on energy reserves).  
2. **Check Resources:** Upon wake: assess battery, energy harvest trend, GPS fix quality.  
3. **Sensor Trigger:** Core 1 performs Raman acquisition only when hydrodynamic flow (proxy for forward motion) indicates adequate flushing.  
4. **Edge Classification:** A lightweight model (e.g., quantized Bayesian or micro-CNN inference) maps spectral peaks to a prey code and confidence.  
5. **Event Filtering:** Only events exceeding confidence threshold are enqueued for satellite transmission.  
6. **Sparse Telemetry:** Iridium modem is powered *only* when there’s a qualifying event and acceptable GPS context, drastically minimizing energy cost.  
7. **Adaptive Power Governance:** Sampling frequency stretches under low-harvest conditions; recovers automatically once energy improves.

## 4. Data Packet Structure (Iridium SBD, 32 Bytes)
A compact, fixed-length binary packet ensures deterministic parsing and minimal overhead. *Little-endian* encoding is used for multi-byte integers unless otherwise noted. Unused / reserved bytes permit forward-compatible extensions without protocol redesign.

| Offset (Byte) | Length | Field | Type | Description |
|---------------|--------|-------|------|-------------|
| 0             | 4      | Timestamp | uint32 | Unix epoch seconds at classification. |
| 4             | 4      | Latitude  | int32  | Degrees × 1e6 (signed). |
| 8             | 4      | Longitude | int32  | Degrees × 1e6 (signed). |
| 12            | 2      | Battery mV | uint16 | Instant battery voltage (millivolts). |
| 14            | 1      | FW Version | uint8 | Major (upper nibble) | Minor (lower). |
| 15            | 1      | Status Flags | bitfield | Bit0: GPS valid; Bit1: Requeued send; Bit2: Spectral avg >1; Bit3: Low power mode; Bits4–6: Classifier model ID (0–7); Bit7: Reserved. |
| 16            | 8      | Prey Code | ASCII[8] | Null-padded token (e.g., `SEAL_LIP`, `FISH_PROT`, `SQUID_BEK`, `AMB_WATR`). |
| 24            | 4      | Confidence | float32 | IEEE 754 (0.0–1.0). |
| 28            | 2      | Spectral Hash | uint16 | Truncated 16-bit hash of peak feature vector (for dedup / auditing). |
| 30            | 2      | CRC-16 | uint16 | CRC-16/CCITT-FALSE over bytes 0–29. |

### Total: 32 bytes

### Rationale
- **Signed microdegrees** enable ≈0.11 m resolution—exceeds ecological need but future-proofs.  
- **Prey Code ASCII** aids rapid human inspection while remaining compact; fixed 8 bytes simplifies onshore indexing.  
- **Confidence as float32** preserves probabilistic nuance for downstream Bayesian fusion (could be quantized later).  
- **Spectral Hash** supports distinguishing repeated transmissions from identical events when connectivity fluctuates.  
- **CRC-16** protects integrity independent of higher-layer Iridium checks (useful if packets are later repurposed in multi-hop relay contexts).  

### Example Encoding (Hypothetical)
Event: 2025-10-04 12:00:05Z, Lat 34.512345°, Lon -142.345678°, Prey = `SEAL_LIP`, Confidence 0.91, Battery 3.72 V.
```
Timestamp: 1759579205 -> 0x68 FF 6F 69 (LE)
Lat: 34.512345 * 1e6 = 34512345 -> 0xF9 34 0C 02 (LE)
Lon: -142.345678 *1e6 = -142345678 -> two's complement -> 0xB2 6E 91 F7
Battery: 3720 mV -> 0x88 0E
FW Version 1.2 -> 0x12
Status: GPS valid + spectral avg>1 -> Bit0=1, Bit2=1 => 00000101b -> 0x05
Prey Code: 'S','E','A','L','_','L','I','P'
Confidence 0.91 -> 0x3F 67 AE 14 (IEEE754 LE)
Spectral Hash: 0x9C 47
CRC-16(0..29) -> 0x3A 5F
```
(Shown bytewise; actual CRC value illustrative.)

---
## 5. Future Extensions
- **Delta Compression:** Batch up to N events when energy permits; transmit multi-packet bursts.
- **On-Tag Model Updates:** Opportunistic over-the-air (near-surface Wi‑Fi) classifier refresh to incorporate new prey spectral libraries.
- **Multi-Sensor Fusion:** Co-register rapid acceleration + jaw motion (accelerometer + acoustic) to improve prior probability for classification stage.
- **Adaptive Thresholding:** Dynamic prey confidence threshold tied to SNR and recent false-positive audit outcomes.

## 6. Disclaimer
Several aspects (miniaturized Raman module ruggedization, long-duration piezo harvesting efficiency in highly mobile pelagics) represent forward-leaning engineering assumptions. This document serves as a **conceptual prototype specification** guiding iterative feasibility trials, not a finalized manufacturing package.

---
**Contact:** For implementation discussions or model integration pathways, see accompanying project `README.md` or reach out to the SharkScope engineering team.
