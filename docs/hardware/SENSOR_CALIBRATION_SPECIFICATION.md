# Spesifikasi Register Modbus & Profil Kalibrasi Sensor

## 1. Ikhtisar & Status Dependensi Fisik

Dokumen ini mendefinisikan pemetaan register fisik Modbus RTU RS485, satuan fisik, rentang ukur operasional, dan metodologi transformasi kalibrasi untuk stasiun Trainer Kit IoT SMK Pertanian.

> ⚠️ **Status Dependensi Fisik (Downlink): `BLOCKED`**
> Firmware mikrokontroler ESP32 pada Trainer Kit saat ini belum mengimplementasikan skema penulisan command register Modbus (holding register write) yang diautentikasi dan terverifikasi secara aman. Oleh karena itu, seluruh profil kalibrasi dijalankan di layer software (application cloud layer) dan transmisi downlink ke register fisik mikrokontroler ditandai secara jujur sebagai **`BLOCKED`**.

---

## 2. Katalog Sensor & Register Mapping (Modbus RS485 7-in-1 Soil Sensor)

Model: **`RS485-SOIL-7IN1`**
Protokol: Modbus RTU via RS485 (Baudrate: 9600, 8-N-1)
Kompatibilitas Firmware: `ESP32-BTLP-v1.0+`

| Parameter | Nama Metrik | Register Address | Tipe Register | Format Data | Faktor Skala | Rentang Fisik Valid | Satuan | Metode Kalibrasi Diizinkan |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **Nitrogen (N)** | `nitrogen` | `0x0000` (0) | Holding Register | `uint16` | 1.0 | 0.0 – 1999.0 | mg/kg | Offset terikat (±100) |
| **Phosphorus (P)** | `phosphorus` | `0x0001` (1) | Holding Register | `uint16` | 1.0 | 0.0 – 1999.0 | mg/kg | Offset terikat (±100) |
| **Kelembapan** | `humidity` | `0x0002` (2) | Holding Register | `uint16` | 0.1 | 0.0 – 100.0 | % | Offset (±20.0), Vektor 2-titik |
| **Suhu Tanah** | `temperature` | `0x0003` (3) | Holding Register | `int16` | 0.1 | -40.0 – 80.0 | °C | Offset (±10.0), Vektor 2-titik |
| **Potassium (K)** | `potassium` | `0x0004` (4) | Holding Register | `uint16` | 1.0 | 0.0 – 1999.0 | mg/kg | Offset terikat (±100) |
| **Konduktivitas (EC)** | `ec` | `0x0005` (5) | Holding Register | `uint16` | 1.0 | 0.0 – 20000.0 | µS/cm | Offset (±500.0), Vektor 2-titik |
| **Derajat Asam (pH)** | `ph` | `0x0006` (6) | Holding Register | `uint16` | 0.1 | 3.0 – 10.0 | pH | Offset (±2.0), Vektor 2-titik |

---

## 3. Metodologi Kalibrasi Terverifikasi

### A. Metode Offset (Zero-Shift / Span-Shift Tunggal)
Formula:
$$\text{Calibrated} = \text{Raw} + \text{Offset}$$
Syarat: Nilai `Offset` wajib berada di dalam batas yang diizinkan untuk parameter tersebut, dan hasil akhir diklem sesuai rentang valid sensor.

### B. Metode Vektor 2-Titik (Two-Point Reference Vector)
Formula interpolasi linear berdasarkan 2 larutan standar acuan (contoh larutan buffer pH 4.01 dan 7.00):
$$\text{Calibrated} = \text{Ref}_{low} + \frac{(\text{Raw} - \text{Raw}_{low}) \times (\text{Ref}_{high} - \text{Ref}_{low})}{\text{Raw}_{high} - \text{Raw}_{low}}$$
Syarat:
- $\text{Raw}_{high} > \text{Raw}_{low}$
- $\text{Ref}_{high} > \text{Ref}_{low}$
- Nilai referensi wajib berada dalam rentang fisik yang ditentukan.

### C. Larangan Formula Universal Tanpa Spesifikasi
Sistem secara tegas **menolak** pembuatan rumus kalibrasi polinomial arbitrer atau spekulatif tanpa referensi manual pabrikan sensor yang terverifikasi.

---

## 4. Kebijakan Imutabilitas Data Riwayat

1. **Raw Payload Preserved**: Setiap pembacaan telemetri wajib menyimpan `raw_payload` utuh yang dikirim mikrokontroler tanpa modifikasi.
2. **Derived Traceability**: Nilai metrik yang dikalibrasi merekam `calibration_profile_id`, `calibration_version`, dan `calibration_applied_at`.
3. **Audit Trail**: Pembaruan profil kalibrasi wajib mencantumkan alasan (`reason`) dan tercatat pada `audit_logs`.
4. **No Rewriting History**: Aktivasi profil kalibrasi baru **TIDAK MENGUBAH** rekaman telemetri masa lalu.
