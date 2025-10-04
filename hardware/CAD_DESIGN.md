# Mako-Sense Tag CAD Design Specification

> Textual mechanical design brief for creating a manufacturable, hydrodynamically efficient enclosure for the Mako-Sense autonomous biochemical prey-detection tag.

## 1. Hydrodynamics & External Form Factor
The enclosure adopts a **fusiform (teardrop) profile**: a rounded elliptical nose transitioning into a gently tapering tail section ending in a narrow trailing edge. This minimizes pressure drag and suppresses flow separation across a wide Reynolds number range encountered by a mako shark (high cruising speeds with rapid acceleration spurts). The cross-section is quasi-elliptical (not circular) to lower vertical profile and reduce pitching torque. Longitudinal curvature is smoothed using a minimum-curvature spline (no abrupt radius transitions), ensuring laminar-like boundary adherence for the forward 60–70% of body length before controlled pressure recovery.

Key hydrodynamic features:
- **Nose Radius:** ~6–8 mm to avoid stagnation-point erosion and blunt impact stress.
- **Maximum Thickness Position:** At 30% of body length to balance internal volume vs. drag.
- **Tail Taper Ratio:** Linear-exponential hybrid, reducing cross-section to <12% of max within final 20 mm.
- **Surface Finish:** Polished to Ra ≤ 0.8 μm (epoxy final coat + micro-sand) to reduce skin friction.
- **Edge Softening:** All transitions filleted (≥1.0 mm) to mitigate hydrodynamic noise and biofouling adhesion points.

Dimensions (concept): Length 110 mm, Max Width 34 mm, Max Height 28 mm. These values may iterate following CFD validation or bioattachment constraints.

## 2. Materials & Construction
| Component | Material | Rationale |
|----------|----------|-----------|
| Primary Housing Shell | **Biocompatible marine-grade epoxy resin** (filled with glass microballoons + fumed silica for thixotropy) | Corrosion proof, pressure-capable to 2000 m, low water absorption, tunable density. |
| Internal Structural Frame | Carbon fiber-reinforced nylon (PA-CF) | High stiffness-to-weight, vibration damping with micro-Raman isolation gaskets. |
| Fasteners / Inserts | Grade 5 Titanium (Ti-6Al-4V) | Corrosion resistance, biocompatibility, strength. |
| Attachment Clamp | Machined Titanium with elastomeric (TPU Shore 85A) pad | Secure dorsal fin interface, minimizes abrasion, galvanic isolation. |
| Optical Window (Flow Cell) | Sapphire or fused silica (AR-coated) | Scratch resistance, optical clarity for Raman excitation/collection. |
| Piezo Harvester Beam Capsule | Parylene-C coated stainless or titanium sleeve | Mechanical fatigue resistance, dielectric barrier, corrosion protection. |
| Gaskets / Seals | Fluorosilicone O-rings | Low compression set, chemical and thermal stability. |

The housing consists of **two interlocking shells** (upper + lower) joined via a tongue-and-groove channel with dual O-ring redundancy along the mid-body separation plane. Four recessed titanium cap screws (Torx T6) apply uniform compression. Embedded helicoil or molded titanium threaded inserts prevent thread wear after multiple lab openings.

## 3. Internal Component Layout
Internal layout prioritizes: (1) center-of-mass alignment with clamp axis, (2) minimal flexural coupling to spectrometer optics, (3) short RF path for Iridium patch antenna.

Layered stack (top-to-bottom when mounted):
1. **Iridium 9603N Module + Ceramic Patch Antenna:** Positioned beneath the upper shell’s RF window region (epoxy with reduced carbon loading to maintain dielectric transparency). Clear sky exposure when shark surfaces; slight recess prevents abrasion.
2. **Main PCB (RP2040 + Power Management + Connectors):** Mounted on four silicone-damped standoffs (shore hardness tuned) to absorb high-frequency mechanical noise from swimming-induced vibrations.
3. **Micro-Raman Spectrometer Subassembly:** Orthogonal to PCB plane, isolated on a mini optical bench plate with elastomeric isolation pillars. Laser path aligned horizontally through the flow cell window. Internal baffle walls (matte black PEEK) reduce stray light and internal reflections.
4. **Energy Storage & Harvest Interface:** A thin-film LiPo (or LTO cell) lies ventrally, framed by two supercapacitors (symmetrically placed to balance lateral mass). Piezo beam route enters via potting feedthrough and connects to rectifier board edge.
5. **Environmental Sensors (temp / flow proxy):** A MEMS thermistor + differential pressure micro-port pair flanking the flow cell intake/exhaust for quality gating of spectral acquisition.

Harnessing uses FFC or micro-coax as appropriate; high-vibration lines are strain-relieved via printed arches. Cable corridors are chamfered to avoid insulation chafe.

## 4. Sensor Interface: Passive Flow Cell System
The **flow cell** is an internal micro-channel volume (~250–400 µL) that continuously exchanges ambient seawater while the animal moves, eliminating active pumping. Its geometry ensures rapid turnover while maintaining a stable interrogation zone for Raman excitation.

Design elements:
- **Forward-Facing Intake Port:** 2.2–2.5 mm diameter circular orifice flush with shell; lip chamfer at 30° to reduce vortex shedding.
- **Converging Inlet Channel:** Smooth 3:1 taper to stabilize velocity profile entering the optical window chamber.
- **Optical Chamber:** Rectangular or ovalized cavity (approx. 6 × 4 × 3 mm) aligned with spectrometer lens and laser focus point (working distance ~4 mm). Sapphire window integrated into inner wall; O-ring compressed axial seat ensures pressure integrity.
- **Exhaust / Aft Port:** 2.8–3.0 mm diameter slightly larger than intake to encourage through-flow via Bernoulli differential; angled 10–15° aftward to exploit slipstream suction.
- **Anti-Fouling Strategy:** Internal surfaces coated with silicone-based foul-release; periodic laser pulses at low integration used as an optical self-clean routine (monitor baseline reflectance drift).
- **Bubble Management:** Ceiling includes a micro-dome trap and hydrophobic vent membrane (ePTFE) allowing gas egress without liquid intrusion at depth.

Flow velocity estimate derived from differential pressure across intake/exhaust micro-ports (or optional MEMS thermal flow sensor). Raman acquisition is inhibited unless turnover exceeds a threshold ensuring fresh analyte exposure.

## 5. Assembly & Service Strategy
1. **Subassemblies:** (a) Optical bench + spectrometer, (b) Main PCB + Iridium, (c) Energy module + piezo harness. Each validated independently for leak integrity before final stack.  
2. **Sealing:** Dual O-rings lightly silicone-greased; compression verified with go/no-go feeler gauge.  
3. **Potting Regions:** Piezo feedthrough junction and antenna coax transition cavities are selectively backfilled with low-modulus epoxy to prevent capillary ingress.  
4. **Calibration Access:** Hidden magnetic reed switch allows entering calibration/config mode (external magnet swipe) without breaching seal.  
5. **Traceability:** Laser-etched serial and QR code on inner shell; exterior marking minimized to preserve surface smoothness.  

## 6. Tolerancing & Manufacturing Notes
- Nominal wall thickness: 2.2 mm (reinforced ribs to 3.0 mm near clamp).  
- Dimensional tolerance: ±0.15 mm for printed prototypes (SLS PA-CF), tightened to ±0.05 mm for production epoxy castings.  
- Optical window seat flatness ≤ 25 µm to ensure O-ring uniform compression.  
- Screw torque: 0.35–0.40 N·m (wet torque spec with anti-seize).  
- Internal matte coating: Black polyurethane aerosol with VOC cure schedule ≥ 48 h before optics install.  

## 7. Thermal & Pressure Considerations
- Operating thermal range: 0–30 °C typical; transient up to 35 °C internal acceptable. Heat from laser diode dissipated through copper slug embedded under spectrometer, thermally coupled to shell via TIM pad.  
- Pressure: Static rated to 200 bar (2000 m) with 2.0× safety factor validated by hydrostatic bench test (incremental pressurization).  
- Differential Stress Control: Finite Element Analysis to confirm < 40% of epoxy tensile strength at max depth including thermal contraction differentials between sapphire window and epoxy.  

## 8. Risk & Mitigation Summary
| Risk | Potential Issue | Mitigation |
|------|-----------------|-----------|
| Biofouling | Optical attenuation | Foul-release coating + periodic baseline scan | 
| Bubble Entrapment | Spectral noise artifacts | Dome trap + vent membrane | 
| Vibration Coupling | Spectral baseline instability | Elastomeric isolation + mass balancing | 
| Piezo Overstrain | Mechanical fatigue | Strain-limiting stops in clamp design | 
| Antenna Detuning | Water film / angle variability | Elevated patch recess + hydrophobic surface | 
| Seal Creep | Long-term compression set | Dual O-rings + periodic lab validation cycles |

## 9. CFD & Validation Roadmap (Conceptual)
1. Low-fidelity STL loft → initial drag coefficient estimation across 2–12 m/s.  
2. Iterate nose radius & tail taper to minimize pressure drag; target Cd reduction ≥ 12% vs. simple cylindrical fairing baseline.  
3. Particle tracing for intake/exhaust ports to ensure volumetric refresh < 3 s at cruise speed 5 m/s.  
4. Evaluate risk of recirculation eddies near intake—refine chamfer angle or add micro-lip if necessary.  
5. Model internal convective heat paths for laser duty cycles (peak thermal rise < 7 °C).  

## 10. Version Control & Parametric Strategy
Recommend building the enclosure in a parametric CAD system (e.g., Fusion 360 or Onshape) with the following master parameters: `Body_Length`, `Max_Width`, `Max_Height`, `Nose_Radius`, `Intake_Dia`, `Exhaust_Dia`, `Window_Offset_Z`, `PCB_Standoff_Height`, `Clamp_Spread`. Derived sketches reference these to allow automated exploration scripts (Design of Experiments) to export variant STLs for CFD batch processing.

## 11. Disclaimer
This CAD specification is a **conceptual design document** for early-stage prototyping. All dimensions, materials, and structural assumptions must be validated through iterative mechanical, hydrodynamic, and bioethical review before field deployment.
